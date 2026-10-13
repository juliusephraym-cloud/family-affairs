<?php

namespace Tests\Feature;

use App\Exceptions\DomainRuleException;
use App\Models\Meeting;
use App\Models\NotificationDelivery;
use App\Models\NotificationJob;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\ReminderRule;
use App\Services\Messaging\FakeProvider;
use App\Services\Messaging\NextSmsProvider;
use App\Services\Messaging\WhatsAppMessage;
use App\Services\NotificationService;
use App\Services\ReminderEngine;
use App\Support\Settings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    private NotificationService $n;

    protected function setUp(): void
    {
        parent::setUp();
        $this->n = app(NotificationService::class);
        Settings::set('notifications.quiet_hours', ['start' => null, 'end' => null]);
        NotificationTemplate::query()->update(['provider_template' => 'tpl', 'provider_approval' => 'approved']);
    }

    private function tpl(string $key): NotificationTemplate
    {
        return NotificationTemplate::where('key', $key)->firstOrFail();
    }

    public function test_dispatch_is_idempotent_and_never_claims_delivery(): void
    {
        $by = $this->makeUser(['secretary']);
        $this->makeUser();
        $this->makeUser();
        $job = $this->n->schedule($this->tpl('contribution_reminder'), 'Oct', ['type' => 'all_active'], now()->subMinute(), ['period' => 'Oktoba'], $by);

        $this->n->dispatchDue();
        $this->n->dispatchDue(); // second scheduler tick
        $this->assertSame(3, NotificationDelivery::count(), 'one row per recipient, never duplicated');
        $this->assertSame(3, NotificationDelivery::where('status', 'submitted')->count(), 'accepted by provider ≠ delivered');
        $this->assertSame(0, NotificationDelivery::where('status', 'delivered')->count());
        $this->assertSame(3, \DB::table('dev_outbox')->count(), 'exactly one send per recipient');
        $this->assertSame('completed', $job->fresh()->status);

        // Re-running the send for an already submitted delivery does nothing.
        $this->n->send(NotificationDelivery::first()->id);
        $this->assertSame(3, \DB::table('dev_outbox')->count());
    }

    public function test_webhook_requires_the_verify_token_and_updates_status(): void
    {
        $by = $this->makeUser(['secretary']);
        $this->n->schedule($this->tpl('meeting_reminder'), 'M', ['type' => 'members', 'member_ids' => [$by->member_id]], now()->subMinute(), [], $by);
        $this->n->dispatchDue();
        $d = NotificationDelivery::first();

        $this->postJson('/webhooks/nextsms', ['token' => 'wrong', 'messageId' => $d->provider_message_id, 'status' => 'DELIVERED', 'id' => 73])->assertStatus(401);
        $this->assertSame('submitted', $d->fresh()->status);
        $this->assertDatabaseHas('provider_webhook_events', ['is_valid' => false]);

        $this->postJson('/webhooks/nextsms', ['token' => 'test-webhook-token', 'messageId' => $d->provider_message_id, 'status' => 'DELIVERED', 'id' => 73])->assertOk();
        $this->assertSame('delivered', $d->fresh()->status);
        $this->postJson('/webhooks/nextsms', ['token' => 'test-webhook-token', 'messageId' => $d->provider_message_id, 'status' => 'READ', 'id' => 108]);
        $this->assertSame('read', $d->fresh()->status);
        // The token is never stored.
        $this->assertStringNotContainsString('test-webhook-token', json_encode(\DB::table('provider_webhook_events')->get()));
    }

    public function test_temporary_failure_is_retried_with_backoff_then_recorded(): void
    {
        $by = $this->makeUser(['secretary']);
        $this->n->schedule($this->tpl('meeting_reminder'), 'M', ['type' => 'members', 'member_ids' => [$by->member_id]], now()->subMinute(), [], $by);
        FakeProvider::$failNext = true;
        $this->n->dispatchDue();
        $d = NotificationDelivery::first();
        $this->assertSame('queued', $d->status);
        $this->assertNotNull($d->next_attempt_at);
        $this->assertNotNull($d->last_error);

        $this->travel(6)->minutes();
        $this->n->dispatchDue();
        $this->assertSame('submitted', $d->fresh()->status);
        $this->assertSame(2, $d->fresh()->attempts);
    }

    public function test_private_balances_never_go_to_a_group(): void
    {
        $by = $this->makeUser(['treasurer']);
        $this->expectException(DomainRuleException::class);
        $this->n->schedule($this->tpl('private_balance'), 'x', ['type' => 'all_active'], now(), [], $by);
    }

    public function test_opt_outs_quiet_hours_and_unapproved_templates(): void
    {
        $by = $this->makeUser(['secretary']);
        $out = $this->makeUser();
        NotificationPreference::create(['member_id' => $out->member_id, 'category' => 'events', 'enabled' => false]);
        $this->assertFalse($this->n->resolveAudience(['type' => 'all_active'], 'events')->contains('id', $out->member_id));
        $this->assertTrue($this->n->resolveAudience(['type' => 'all_active'], 'security')->contains('id', $out->member_id), 'mandatory categories ignore opt-out');

        Settings::set('notifications.quiet_hours', ['start' => '21:00', 'end' => '07:00']);
        $this->travelTo(now()->setTime(22, 30));
        $this->n->schedule($this->tpl('meeting_reminder'), 'Late', ['type' => 'members', 'member_ids' => [$by->member_id]], now()->subMinute(), [], $by);
        $this->n->dispatchDue();
        $d = NotificationDelivery::first();
        $this->assertSame('queued', $d->status);
        $this->assertSame('07:00', $d->next_attempt_at->format('H:i'));
        $this->assertSame(0, \DB::table('dev_outbox')->count());

        Settings::set('notifications.quiet_hours', ['start' => null, 'end' => null]);
        $t = $this->tpl('event_reminder');
        $t->update(['provider_approval' => 'pending']);
        $this->n->schedule($t, 'Ev', ['type' => 'members', 'member_ids' => [$by->member_id]], now()->subMinute(), [], $by);
        $this->n->dispatchDue();
        $this->assertSame('failed', NotificationDelivery::latest('id')->first()->status, 'unapproved template must not be sent');
    }

    public function test_large_broadcast_needs_a_second_leader_and_can_be_cancelled(): void
    {
        config(['family.messaging.approval_threshold' => 2]);
        $sec = $this->makeUser(['secretary']);
        $chair = $this->makeUser(['chairperson']);
        $this->makeUser();
        $job = $this->n->schedule($this->tpl('general_notice'), 'Big', ['type' => 'all_active'], now()->subMinute(), ['title' => 'x'], $sec);
        $this->assertSame('pending_approval', $job->status);
        $this->n->dispatchDue();
        $this->assertSame(0, NotificationDelivery::count(), 'nothing sent before approval');

        $this->expectException(DomainRuleException::class);
        try {
            $this->n->approve($job, $sec);
        } finally {
            $this->n->approve($job->fresh(), $chair);
            $this->assertSame('scheduled', $job->fresh()->status);
            $this->n->cancel($job->fresh(), $chair);
            $this->n->dispatchDue();
            $this->assertSame(0, NotificationDelivery::count());
        }
    }

    public function test_reminder_rules_are_idempotent(): void
    {
        $this->makeUser();
        ReminderRule::where('key', 'meeting_reminder')->update(['enabled' => true]);
        Meeting::create(['title' => 'Online', 'type' => 'online', 'starts_at' => now()->addHours(5), 'status' => 'planned']);
        app(ReminderEngine::class)->run();
        app(ReminderEngine::class)->run();
        $this->assertSame(1, NotificationJob::count());
    }

    // ---------------------------------------------------------------- NextSMS adapter

    private function provider(array $over = []): NextSmsProvider
    {
        return new NextSmsProvider(array_merge(config('family.nextsms'), ['token' => 'tkn', 'whatsapp_account' => 'FAMILY', 'test_mode' => false], $over));
    }

    public function test_nextsms_request_shape_for_otp_matches_the_documented_api(): void
    {
        Http::fake(['*/api/whatsapp/v2/text/single' => Http::response('{"messages":[{"to":"255700000001","status":{"groupId":18,"groupName":"PENDING","id":51,"name":"ENROUTE (SENT)"},"messageId":6342623755839986228,"message":"123456 is your code","price":12}]}', 200)]);
        $r = $this->provider()->sendTemplate(new WhatsAppMessage('255700000001', 'verify_code', button: ['personalisation' => ['copy_otp_code' => ['parameters' => ['123456']]]], reference: 'otp-x'));

        $this->assertTrue($r->accepted);
        $this->assertSame('6342623755839986228', $r->messageId, '19-digit id must keep full precision');
        $this->assertArrayNotHasKey('message', $r->raw['messages'][0], 'provider echo (may contain the OTP) is not kept');
        Http::assertSent(function (Request $req) {
            $b = $req->data();

            return $req->url() === 'https://messaging-service.co.tz/api/whatsapp/v2/text/single'
                && $req->hasHeader('Authorization', 'Bearer tkn')
                && $b['to'] === [255700000001] && $b['account'] === 'FAMILY' && $b['template'] === 'verify_code'
                && $b['button']['personalisation']['copy_otp_code']['parameters'] === ['123456'];
        });
    }

    public function test_nextsms_errors_are_classified(): void
    {
        Http::fake([
            '*' => Http::sequence()->push('', 401)->push('', 503)->push('{"messages":[{"status":{"groupId":19,"id":56,"name":"REJECTED_SOURCE","description":"Sender not registered"}}]}', 200),
        ]);
        $p = $this->provider();
        $msg = new WhatsAppMessage('255700000001', 't');
        $a = $p->sendTemplate($msg);
        $this->assertFalse($a->accepted);
        $this->assertFalse($a->retryable, 'bad credentials are not retried');
        $b = $p->sendTemplate($msg);
        $this->assertTrue($b->retryable, '5xx is retried');
        $c = $p->sendTemplate($msg);
        $this->assertFalse($c->accepted);
        $this->assertSame('Sender not registered', $c->error);
    }

    public function test_nextsms_test_mode_uses_the_documented_no_send_endpoint(): void
    {
        Http::fake(['*/api/whatsapp/v2/test/text/single' => Http::response('{"messages":[{"status":{"groupId":18,"id":51,"name":"ENROUTE (SENT)"},"messageId":1}]}')]);
        $this->assertTrue($this->provider(['test_mode' => true])->sendTemplate(new WhatsAppMessage('255700000001', 't'))->accepted);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/whatsapp/v2/test/text/single'));
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/api/whatsapp/v2/text/single'));
    }

    public function test_unconfigured_nextsms_fails_loudly(): void
    {
        $r = (new NextSmsProvider(array_merge(config('family.nextsms'), ['token' => null])))->sendTemplate(new WhatsAppMessage('255700000001', 't'));
        $this->assertFalse($r->accepted);
    }
}
