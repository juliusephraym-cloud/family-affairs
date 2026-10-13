<?php

namespace Tests;

use App\Models\ContributionRule;
use App\Models\Member;
use App\Models\PolicyRule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\FinanceStructureSeeder;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\PolicyRulesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    private static int $phoneSeq = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, FinanceStructureSeeder::class, PolicyRulesSeeder::class, NotificationSeeder::class]);
    }

    /** A verified, consenting, active member with the given roles. */
    protected function makeUser(array $roles = ['member'], array $memberAttrs = []): User
    {
        $phone = '2557000'.str_pad((string) self::$phoneSeq++, 5, '0', STR_PAD_LEFT);
        $member = Member::create($memberAttrs + [
            'full_name' => 'Test Person '.self::$phoneSeq, 'phone_e164' => $phone, 'membership_status' => 'active',
            'joined_on' => '2020-01-01', 'whatsapp_opt_in_at' => now(),
        ]);
        $user = User::create(['member_id' => $member->id, 'phone_e164' => $member->phone_e164 ?? $phone, 'phone_verified_at' => now(), 'status' => 'active']);
        $user->roles()->sync(Role::whereIn('key', array_unique(array_merge($roles, ['member'])))->pluck('id'));

        return $user->fresh();
    }

    protected function confirmRule(string $key, mixed $value = null): void
    {
        $rule = PolicyRule::where('key', $key)->firstOrFail();
        $rule->update(['status' => 'confirmed', 'value' => $value === null ? $rule->value : (is_int($value) ? ['amount' => $value] : ['value' => $value])]);
    }

    protected function approvedRule(int $general = 10000, int $welfare = 10000, string $from = '2026-01-01'): ContributionRule
    {
        return ContributionRule::create([
            'name' => 'Test rule', 'amount' => $general + $welfare,
            'fund_splits' => [['fund' => 'general', 'amount' => $general], ['fund' => 'welfare', 'amount' => $welfare]],
            'due_day' => 28, 'effective_from' => $from, 'status' => 'approved', 'approved_at' => now(),
        ]);
    }

    protected function assertLedgerBalanced(): void
    {
        $rows = \DB::table('ledger_entries')->selectRaw('ledger_transaction_id, fund_id, SUM(debit) d, SUM(credit) c')->groupBy('ledger_transaction_id', 'fund_id')->get();
        foreach ($rows as $r) {
            $this->assertSame((int) $r->d, (int) $r->c, "Transaction {$r->ledger_transaction_id} unbalanced in fund {$r->fund_id}");
        }
    }
}
