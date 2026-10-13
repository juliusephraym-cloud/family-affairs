<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Member;
use App\Models\OtpChallenge;
use App\Services\Messaging\FakeProvider;
use App\Services\Messaging\WhatsAppProvider;
use App\Services\OtpService;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    private function lastCode(): string
    {
        $row = DB::table('dev_outbox')->latest('id')->first();

        return json_decode($row->payload, true)['button']['personalisation']['copy_otp_code']['parameters'][0];
    }

    public function test_registered_member_can_sign_in_with_whatsapp_otp(): void
    {
        $user = $this->makeUser();
        $this->post('/login', ['phone' => '0'.substr($user->phone_e164, 3)])->assertRedirect('/verify');
        $code = $this->lastCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->post('/verify', ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_unknown_number_gets_identical_response_and_no_message(): void
    {
        $known = $this->makeUser();
        $r1 = $this->post('/login', ['phone' => '+'.$known->phone_e164]);
        $this->flushSession();
        $r2 = $this->post('/login', ['phone' => '+255711999888']);

        $this->assertSame($r1->headers->get('Location'), $r2->headers->get('Location'));
        $this->assertSame(1, DB::table('dev_outbox')->count(), 'No message may be sent to an unregistered number');
        // The decoy challenge never verifies.
        $this->post('/verify', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_otp_is_stored_hashed_never_in_plaintext_or_logs(): void
    {
        Log::spy();
        $user = $this->makeUser();
        $this->post('/login', ['phone' => '+'.$user->phone_e164]);
        $code = $this->lastCode();
        $challenge = OtpChallenge::first();

        $this->assertNotSame($code, $challenge->code_hash);
        $this->assertStringNotContainsString($code, json_encode($challenge->getAttributes()));
        $this->assertStringNotContainsString($code, json_encode(DB::table('audit_logs')->get()));
        Log::shouldNotHaveReceived('info', fn ($m, $ctx = []) => str_contains($m.json_encode($ctx), $code));
        // Response bodies never contain the code.
        $this->get('/verify')->assertDontSee($code);
    }

    public function test_wrong_codes_lock_the_challenge_after_max_attempts(): void
    {
        $user = $this->makeUser();
        $this->post('/login', ['phone' => '+'.$user->phone_e164]);
        $code = $this->lastCode();
        $wrong = $code === '000000' ? '111111' : '000000';
        for ($i = 0; $i < config('family.otp.max_attempts'); $i++) {
            $this->post('/verify', ['code' => $wrong])->assertSessionHasErrors('code');
        }
        // Even the right code is now refused.
        $this->post('/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_code_cannot_be_replayed_and_expires(): void
    {
        $user = $this->makeUser();
        $this->post('/login', ['phone' => '+'.$user->phone_e164]);
        $code = $this->lastCode();
        $otp = session('otp');
        $this->post('/verify', ['code' => $code])->assertRedirect();
        $this->post('/logout');

        $result = app(OtpService::class)->verify($otp['challenge'], $code, $otp['phone']);
        $this->assertFalse($result['ok'], 'A consumed code must not verify again');

        $this->travel(config('family.otp.resend_seconds') + 1)->seconds();
        $this->post('/login', ['phone' => '+'.$user->phone_e164]);
        $code2 = $this->lastCode();
        $this->travel(config('family.otp.ttl_minutes') + 1)->minutes();
        $this->post('/verify', ['code' => $code2])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_newer_code_invalidates_older_one_and_resend_is_rate_limited(): void
    {
        $user = $this->makeUser();
        $this->post('/login', ['phone' => '+'.$user->phone_e164]);
        $first = $this->lastCode();
        // Immediate resend is refused (cooldown).
        $this->post('/verify/resend')->assertSessionHas('error');
        $this->travel(config('family.otp.resend_seconds') + 1)->seconds();
        $this->post('/verify/resend');
        $second = $this->lastCode();

        if ($first !== $second) {
            $this->post('/verify', ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->post('/verify', ['code' => $second])->assertRedirect(route('dashboard'));
    }

    public function test_provider_failure_never_pretends_success(): void
    {
        $user = $this->makeUser();
        FakeProvider::$failNext = true;
        $this->post('/login', ['phone' => '+'.$user->phone_e164])->assertSessionHas('error');
        $c = OtpChallenge::first();
        $this->assertSame('failed', $c->delivery_status);
        $this->assertNotNull($c->invalidated_at);
    }

    public function test_invitation_registration_requires_the_invited_phone(): void
    {
        $admin = $this->makeUser(['super_admin']);
        $member = Member::create(['full_name' => 'New Cousin', 'phone_e164' => '255700099001', 'membership_status' => 'invited']);
        $token = Str::random(48);
        $inv = Invitation::create(['member_id' => $member->id, 'token_hash' => hash('sha256', $token), 'phone_e164' => $member->phone_e164, 'invited_by' => $admin->id, 'expires_at' => now()->addDays(3)]);

        // Someone holding a forwarded link but a different phone gets nothing.
        $this->post("/invite/{$token}", ['phone' => '+255700099002'])->assertSessionHasErrors('phone');
        $this->assertSame(0, DB::table('dev_outbox')->count());

        $this->post("/invite/{$token}", ['phone' => '+255700099001'])->assertRedirect('/verify');
        $this->post('/verify', ['code' => $this->lastCode()])->assertRedirect(route('welcome.show'));

        $member->refresh();
        $this->assertSame('active', $member->membership_status);
        $this->assertNotNull($member->user->phone_verified_at);
        $this->assertNotNull($inv->fresh()->accepted_at);
        // Consent screen before anything else.
        $this->get('/dashboard')->assertRedirect(route('welcome.show'));
        $this->post('/welcome', ['birthday_visibility' => 'members', 'whatsapp_consent' => '1'])->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk();
        // The link is single-use.
        $this->post('/logout');
        $this->get("/invite/{$token}")->assertRedirect(route('login'));
    }

    public function test_unverified_or_suspended_or_disabled_users_cannot_access_family_data(): void
    {
        $user = $this->makeUser();
        $user->update(['phone_verified_at' => null]);
        $this->actingAs($user)->get('/members')->assertRedirect(route('login'));
        $this->assertGuest();

        $user2 = $this->makeUser();
        $user2->update(['status' => 'disabled']);
        $this->actingAs($user2)->get('/dashboard')->assertRedirect(route('login'));

        $user3 = $this->makeUser();
        $user3->member->update(['membership_status' => 'archived']);
        $this->actingAs($user3)->get('/dashboard')->assertRedirect(route('login'));

        $this->get('/members')->assertRedirect(route('login'));
    }

    public function test_phone_change_requires_otp_to_the_new_number(): void
    {
        $user = $this->makeUser();
        $old = $user->phone_e164;
        $this->actingAs($user)->post('/profile/phone', ['new_phone' => '0700 077 077'])->assertRedirect('/verify');
        $outbox = DB::table('dev_outbox')->latest('id')->first();
        $this->assertSame('255700077077', $outbox->to);
        $this->assertSame($old, $user->fresh()->phone_e164, 'Number must not change before verification');
        $this->post('/verify', ['code' => $this->lastCode()]);
        $this->assertSame('255700077077', $user->fresh()->phone_e164);
        $this->assertSame('255700077077', $user->member->fresh()->phone_e164);
    }

    public function test_production_refuses_the_fake_provider(): void
    {
        $this->app['env'] = 'production';
        $this->app->forgetInstance(WhatsAppProvider::class);
        $this->expectException(RuntimeException::class);
        $this->app->make(WhatsAppProvider::class);
    }

    public function test_phone_normalisation(): void
    {
        $n = fn ($v) => Phone::normalize($v);
        $this->assertSame('255712345678', $n('0712 345 678'));
        $this->assertSame('255712345678', $n('+255 712-345-678'));
        $this->assertSame('255712345678', $n('712345678'));
        $this->assertSame('255612345678', $n('00255612345678'));
        $this->assertSame('14155550123', $n('+1 415 555 0123'));
        $this->assertNull($n('0512345678'));
        $this->assertNull($n('12345'));
        $this->assertNull($n('2558123'));
    }
}
