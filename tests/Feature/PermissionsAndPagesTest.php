<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\Announcement;
use App\Models\CharityActivity;
use App\Models\ConstitutionVersion;
use App\Models\Event;
use App\Models\LedgerTransaction;
use App\Models\MediaAsset;
use App\Models\Meeting;
use App\Models\Member;
use App\Models\NotificationJob;
use App\Models\NotificationTemplate;
use App\Models\Permission;
use App\Models\Poll;
use App\Models\Role;
use App\Models\User;
use App\Models\WelfareCase;
use App\Services\NotificationService;
use Database\Seeders\DemoSeeder;
use Tests\TestCase;

class PermissionsAndPagesTest extends TestCase
{
    private function demoUser(string $phoneSuffix): User
    {
        return User::where('phone_e164', '255700000'.$phoneSuffix)->firstOrFail();
    }

    public function test_every_page_renders_for_each_role(): void
    {
        $this->seed(DemoSeeder::class);
        // A notification job so the log pages have content.
        $sec = $this->demoUser('003');
        app(NotificationService::class)->schedule(NotificationTemplate::where('key', 'meeting_reminder')->first(), 'Smoke', ['type' => 'leaders'], now()->addHour(), [], $sec);
        ConstitutionVersion::create(['version_label' => 'T1', 'title' => 'Katiba', 'body' => 'Kifungu 1', 'status' => 'draft', 'created_by' => $sec->id]);

        $common = ['/dashboard', '/members', '/finance', '/finance/pay', '/events', '/meetings', '/welfare', '/secreto', '/memories', '/community', '/governance', '/profile', '/treasury', '/treasury/reports',
            route('members.show', Member::where('is_demo', true)->where('membership_status', 'active')->first()),
            route('events.show', Event::first()), route('meetings.show', Meeting::first()), route('community.show', Announcement::first()),
            route('polls.show', Poll::first()), route('memories.show', Album::first()), route('charity.show', CharityActivity::first()),
            route('welfare.show', WelfareCase::first()), route('governance.version', ConstitutionVersion::first()),
            route('events.show', [Event::first(), 'view' => 'list']), route('events.show', [Event::first(), 'view' => 'timeline'])];
        $leaders = ['/notifications', '/notifications/compose', route('notifications.show', NotificationJob::first()), '/treasury/submissions', '/treasury/members', '/treasury/ledger',
            '/treasury/accounts', '/treasury/expenses', '/treasury/rules', '/treasury/penalties', route('treasury.member', Member::where('is_demo', true)->first()),
            route('treasury.ledger.show', LedgerTransaction::first()), '/treasury/payments/record', '/governance/compliance', '/admin', '/admin/roles', '/admin/audit', '/admin/templates', '/admin/imports',
            '/members/create', route('minutes.edit', Meeting::where('status', 'planned')->first())];

        foreach (['001' => array_merge($common, $leaders), '005' => $common, '003' => $common, '009' => $common] as $suffix => $urls) {
            $user = $this->demoUser($suffix);
            foreach ($urls as $url) {
                $res = $this->actingAs($user)->get($url);
                $this->assertContains($res->status(), [200, 403, 404], "{$url} as {$suffix} returned {$res->status()}");
                if ($suffix === '001') {
                    $this->assertSame(200, $res->status(), "{$url} must open for the chair/super admin");
                }
            }
        }
    }

    public function test_a_general_member_is_kept_out_of_leadership_and_other_peoples_money(): void
    {
        $this->seed(DemoSeeder::class);
        $peter = $this->demoUser('009');
        $other = Member::where('phone_e164', '255700000008')->first();

        foreach (['/treasury/submissions', '/treasury/members', '/treasury/ledger', '/treasury/accounts', '/treasury/rules', '/admin/roles', '/admin/audit',
            '/notifications/compose', '/governance/compliance', '/members/create', route('treasury.member', $other), route('treasury.member.pdf', $other)] as $url) {
            $this->actingAs($peter)->get($url)->assertForbidden();
        }
        $this->actingAs($peter)->get('/admin')->assertForbidden();
        $this->actingAs($peter)->get('/notifications')->assertForbidden();

        // Directory shows no private fields and no children.
        $html = $this->actingAs($peter)->get('/members')->getContent();
        $this->assertStringNotContainsString('Amani Mwakyusa', $html);
        $this->assertStringNotContainsString('+255 700 000 008', $this->actingAs($peter)->get(route('members.show', $other))->getContent());
        $child = Member::where('full_name', 'Amani Mwakyusa')->first();
        $this->actingAs($peter)->get(route('members.show', $child))->assertNotFound();

        // Own statement only.
        $mine = $this->actingAs($peter)->get('/finance')->getContent();
        $this->assertStringNotContainsString($other->full_name, $mine);

        // Cannot grant himself roles, cannot post official notices.
        $this->actingAs($peter)->post(route('members.roles', $peter->member), ['roles' => [1]])->assertForbidden();
        $this->actingAs($peter)->post(route('community.store'), ['title' => 'T', 'body' => 'B', 'kind' => 'official', 'visibility' => 'members']);
        $this->assertSame('post', Announcement::where('title', 'T')->value('kind'));
    }

    public function test_receipts_and_photos_are_never_public(): void
    {
        $this->seed(DemoSeeder::class);
        $media = MediaAsset::first();
        $this->get(route('memories.file', [$media, 'full']))->assertRedirect(route('login'));
        $this->actingAs($this->demoUser('009'))->get(route('memories.file', [$media, 'full']))->assertOk();
        $this->assertFalse(is_dir(public_path('storage/albums')), 'nothing family-related under /public');
    }

    public function test_role_permissions_come_from_the_database_not_the_role_name(): void
    {
        $sec = $this->makeUser(['secretary']);
        $this->assertTrue($sec->hasPermission('governance.manage_meetings'));
        Role::where('key', 'secretary')->first()->permissions()->detach(Permission::where('key', 'governance.manage_meetings')->value('id'));
        $sec->flushPermissionCache();
        $this->assertFalse($sec->fresh()->hasPermission('governance.manage_meetings'));
        $this->actingAs($sec->fresh())->post(route('meetings.store'), ['title' => 'x', 'type' => 'general', 'starts_at' => now()->addDay()])->assertForbidden();
    }

    public function test_security_headers_present(): void
    {
        $this->get('/login')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
