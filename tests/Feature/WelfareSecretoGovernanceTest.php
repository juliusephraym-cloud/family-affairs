<?php

namespace Tests\Feature;

use App\Exceptions\DomainRuleException;
use App\Models\ComplianceCase;
use App\Models\Fund;
use App\Models\Household;
use App\Models\LedgerAccount;
use App\Models\Meeting;
use App\Models\Obligation;
use App\Models\PolicyRule;
use App\Models\SecretoAssignment;
use App\Models\SecretoParticipant;
use App\Models\SecretoSeason;
use App\Models\WelfareCase;
use App\Services\SecretoService;
use App\Services\TreasuryService;
use App\Services\WelfareService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WelfareSecretoGovernanceTest extends TestCase
{
    // ---------------------------------------------------------------- welfare

    public function test_bereavement_cannot_be_approved_on_unconfirmed_rules(): void
    {
        $vice = $this->makeUser(['vice_chairperson']);
        $sec = $this->makeUser(['secretary']);
        $bereaved = $this->makeUser();
        $case = WelfareCase::create(['type' => 'bereavement', 'title' => 'Msiba', 'summary' => 's', 'beneficiary_member_id' => $bereaved->member_id, 'relationship' => 'parent', 'visibility' => 'members', 'status' => 'submitted', 'submitted_by' => $sec->id]);

        $this->expectException(DomainRuleException::class);
        app(WelfareService::class)->approve($case, $vice);
    }

    public function test_confirmed_bereavement_rules_create_levies_for_everyone_but_the_bereaved(): void
    {
        foreach (['bereavement.fund_payout', 'bereavement.member_levy', 'bereavement.levy_days', 'bereavement.eligible_relationships'] as $k) {
            $this->confirmRule($k);
        }
        $vice = $this->makeUser(['vice_chairperson']);
        $sec = $this->makeUser(['secretary']);
        $treasurer = $this->makeUser(['treasurer']);
        $bereaved = $this->makeUser();
        $others = [$this->makeUser(), $this->makeUser()];
        $case = WelfareCase::create(['type' => 'bereavement', 'title' => 'Msiba', 'summary' => 's', 'private_details' => 'medical detail', 'beneficiary_member_id' => $bereaved->member_id, 'relationship' => 'parent', 'visibility' => 'members', 'status' => 'submitted', 'submitted_by' => $sec->id]);

        // Submitter cannot approve their own case.
        try {
            app(WelfareService::class)->approve($case, $sec);
            $this->fail('self approval');
        } catch (DomainRuleException) {
        }
        app(WelfareService::class)->approve($case->fresh(), $vice);

        $levies = Obligation::where('welfare_case_id', $case->id)->get();
        $this->assertSame(5, $levies->count(), 'vice, sec, treasurer and two members — not the bereaved');
        $this->assertFalse($levies->contains('member_id', $bereaved->member_id));
        $this->assertSame(30000, (int) $levies->first()->amount);
        $this->assertSame('collecting', $case->fresh()->status);
        $this->assertSame(now()->addDays(7)->toDateString(), $case->fresh()->contribution_deadline->toDateString());

        // Private details are encrypted at rest and hidden from ordinary members.
        $this->assertStringNotContainsString('medical detail', (string) DB::table('welfare_cases')->value('private_details'));
        $this->actingAs($others[0])->get(route('welfare.show', $case))->assertOk()->assertDontSee('medical detail');
        $this->actingAs($vice)->get(route('welfare.show', $case))->assertSee('medical detail');

        // Disbursement from the welfare fund hits the ledger.
        app(TreasuryService::class)->openingBalance(LedgerAccount::byCode('1000'), Fund::byKey('welfare'), 500000, '2026-01-01', 'cut', $treasurer);
        app(WelfareService::class)->disburse($case->fresh(), 200000, now()->toDateString(), 'Bereaved', 'REF', LedgerAccount::byCode('1000'), $treasurer);
        $this->assertSame('disbursed', $case->fresh()->status);
        $this->assertLedgerBalanced();
    }

    public function test_private_case_is_invisible_to_other_members(): void
    {
        $sec = $this->makeUser(['secretary']);
        $member = $this->makeUser();
        $case = WelfareCase::create(['type' => 'illness', 'title' => 'Private matter', 'summary' => 's', 'visibility' => 'private', 'status' => 'approved', 'submitted_by' => $sec->id]);
        $this->actingAs($member)->get(route('welfare.index'))->assertDontSee('Private matter');
        $this->actingAs($member)->get(route('welfare.show', $case))->assertNotFound();
        $this->actingAs($sec)->get(route('welfare.show', $case))->assertOk();
    }

    // ---------------------------------------------------------------- secreto

    private function seasonWith(int $n, bool $households = false): array
    {
        $season = SecretoSeason::create(['year' => 2027, 'title' => 'S', 'status' => 'registration', 'avoid_same_household' => $households]);
        $users = [];
        for ($i = 0; $i < $n; $i++) {
            $hh = $households ? Household::firstOrCreate(['name' => 'H'.intdiv($i, 2)])->id : null;
            $users[] = $u = $this->makeUser(memberAttrs: ['household_id' => $hh, 'full_name' => 'Secret Person '.chr(65 + $i)]);
            SecretoParticipant::create(['secreto_season_id' => $season->id, 'member_id' => $u->member_id, 'wishlist' => 'Wish of '.chr(65 + $i)]);
        }

        return [$season, $users];
    }

    public function test_draw_is_a_derangement_and_respects_households(): void
    {
        [$season, $users] = $this->seasonWith(8, true);
        app(SecretoService::class)->draw($season, $this->makeUser(['secretary']));
        $parts = SecretoParticipant::with('member')->get()->keyBy('id');
        $recipients = [];
        foreach (SecretoAssignment::all() as $a) {
            $r = $a->recipientParticipantId();
            $this->assertNotSame($a->giver_participant_id, $r, 'Nobody draws themselves');
            $this->assertNotSame($parts[$a->giver_participant_id]->member->household_id, $parts[$r]->member->household_id);
            $recipients[] = $r;
        }
        $this->assertCount(8, array_unique($recipients), 'Everyone receives exactly one gift');
    }

    public function test_pairs_are_encrypted_and_only_your_own_recipient_is_ever_shown(): void
    {
        [$season, $users] = $this->seasonWith(5);
        $organiser = $this->makeUser(['secretary']);
        app(SecretoService::class)->draw($season, $organiser);

        // No plaintext ids in the table.
        foreach (DB::table('secreto_assignments')->get() as $row) {
            $this->assertStringNotContainsString('recipient', $row->recipient_payload);
        }

        $me = $users[0];
        $mine = app(SecretoService::class)->myAssignment($season, $me);
        $html = $this->actingAs($me)->get(route('secreto.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Wish of', $html);
        // Exactly two wishlists are visible: my own (in my edit box) and my recipient's.
        preg_match_all('/Wish of [A-E]/', $html, $m);
        $own = SecretoParticipant::where('member_id', $me->member_id)->value('wishlist');
        $this->assertEqualsCanonicalizing([$own, $mine->wishlist], array_values(array_unique($m[0])));

        // The organiser sees progress, not pairs.
        $orgHtml = $this->actingAs($organiser)->get(route('secreto.index'))->getContent();
        $this->assertStringNotContainsString('→', $orgHtml);
        // And cannot use the break-glass route.
        $this->actingAs($organiser)->post(route('secreto.reveal', $season), ['reason' => 'curious'])->assertForbidden();
        // Model serialisation never leaks the payload.
        $this->assertArrayNotHasKey('recipient_payload', SecretoAssignment::first()->toArray());
    }

    public function test_break_glass_reveal_is_audited(): void
    {
        [$season] = $this->seasonWith(3);
        $admin = $this->makeUser(['super_admin']);
        app(SecretoService::class)->draw($season, $admin);
        $this->actingAs($admin)->post(route('secreto.reveal', $season), ['reason' => 'Member lost their slip'])->assertSessionHas('secreto_revealed');
        $this->assertDatabaseHas('audit_logs', ['action' => 'secreto.revealed']);
    }

    // ---------------------------------------------------------------- governance

    public function test_minutes_need_a_different_approver_and_freeze_after_approval(): void
    {
        $sec = $this->makeUser(['secretary']);
        $chair = $this->makeUser(['chairperson']);
        $member = $this->makeUser();
        $meeting = Meeting::create(['title' => 'Kikao', 'type' => 'general', 'starts_at' => now()->subDay(), 'status' => 'planned']);
        $item = $meeting->agendaItems()->create(['position' => 1, 'title' => 'Fedha']);

        $this->actingAs($sec)->get(route('minutes.edit', $meeting))->assertOk();
        $this->actingAs($sec)->put(route('minutes.update', $meeting), ['discussion' => [$item->id => 'Draft text']]);
        $this->actingAs($member)->get(route('meetings.show', $meeting))->assertDontSee('Draft text');

        $this->actingAs($sec)->post(route('minutes.submit', $meeting));
        $this->actingAs($sec)->post(route('minutes.decide', $meeting), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($chair)->post(route('minutes.decide', $meeting), ['decision' => 'approve']);
        $this->assertSame('approved', $meeting->minutes->fresh()->status);

        $this->actingAs($member)->get(route('meetings.show', $meeting))->assertSee('Draft text');
        $this->actingAs($sec)->get(route('minutes.edit', $meeting))->assertForbidden();
        $this->actingAs($sec)->put(route('meetings.agenda.update', [$meeting, $item]), ['title' => 'Changed', 'discussion' => 'Rewritten'])->assertSessionHas('error');
        $this->actingAs($member)->get(route('minutes.pdf', $meeting))->assertOk();
    }

    public function test_rule_confirmation_is_versioned_and_requires_permission(): void
    {
        $member = $this->makeUser();
        $chair = $this->makeUser(['chairperson']);
        $rule = PolicyRule::where('key', 'penalty.late_monthly_amount')->first();
        $this->assertNull(PolicyRule::confirmedValue('penalty.late_monthly_amount'));

        $this->actingAs($member)->post(route('governance.rules.confirm', $rule), ['action' => 'confirm', 'value' => '5000', 'reason' => 'Kikao cha Oktoba'])->assertForbidden();
        // Cannot confirm a rule that has no value.
        $this->actingAs($chair)->post(route('governance.rules.confirm', $rule), ['action' => 'confirm', 'reason' => 'no value given'])->assertSessionHas('error');
        $this->actingAs($chair)->post(route('governance.rules.confirm', $rule), ['action' => 'confirm', 'value' => '5,000', 'reason' => 'Kikao cha Oktoba']);

        $this->assertSame(5000, PolicyRule::confirmedValue('penalty.late_monthly_amount'));
        $this->assertSame(1, $rule->versions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'governance.rule_confirmed']);
    }

    public function test_compliance_process_cannot_skip_the_review_stage(): void
    {
        $chair = $this->makeUser(['chairperson']);
        $m = $this->makeUser();
        $this->actingAs($chair)->post(route('governance.compliance.open'), ['member_id' => $m->member_id]);
        $case = ComplianceCase::first();
        $this->actingAs($chair)->post(route('governance.compliance.advance', $case), ['stage' => 'decided', 'outcome' => 'status_change', 'outcome_status' => 'suspended', 'decision_text' => 'x'])->assertStatus(422);
        $this->assertSame('active', $m->member->fresh()->membership_status, 'No automatic or premature removal');

        $this->actingAs($chair)->post(route('governance.compliance.advance', $case), ['stage' => 'reminder']);
        $this->actingAs($chair)->post(route('governance.compliance.advance', $case), ['stage' => 'review']);
        $this->actingAs($chair)->post(route('governance.compliance.advance', $case), ['stage' => 'decided', 'outcome' => 'status_change', 'outcome_status' => 'suspended', 'decision_text' => 'Kikao kiliamua']);
        $this->assertSame('suspended', $m->member->fresh()->membership_status);
        $this->assertDatabaseHas('membership_status_histories', ['member_id' => $m->member_id, 'to_status' => 'suspended']);
    }
}
