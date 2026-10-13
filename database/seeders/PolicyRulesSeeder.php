<?php

namespace Database\Seeders;

use App\Models\PolicyRule;
use Illuminate\Database\Seeder;

/**
 * Rules found in the source material, ALL seeded as "source_unconfirmed".
 * The app shows them to leaders with their source and refuses to act on
 * them until someone with governance.confirm_rules confirms (or edits) them.
 * Re-running never overwrites a rule leadership has already touched.
 *
 * Sources:
 *  [M] Minutes "KIKAO CHA FAMILY AFFAIRS 27/7/2026" (the meeting was actually
 *      held 27 June 2026; a member pointed out the date error in the chat on 16/07/2026)
 *  [C] WhatsApp group chat export (Oct 2025 – Oct 2026)
 *  [S] Spreadsheet "FAMILY AFFAIRS-LATEST UPDATE-MAY 2026"
 */
class PolicyRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['contribution.monthly_amount', 'Mchango wa mwezi', 'Monthly contribution', 'money', ['amount' => 20000],
                '[S] Every member paid TSh 20,000/month from Aug 2025 (15,000 Jul 2024–Jul 2025; 10,000 before). No minute found that sets the current amount.'],
            ['contribution.welfare_portion', 'Sehemu ya mchango kwenda mfuko wa jamii', 'Welfare (jamii) portion of the monthly contribution', 'money', ['amount' => 10000],
                '[S] INFERRED, not stated: "JAMII FROM JULY 2024" totals match 5,000/month Jul 2024–Jul 2025 and 10,000/month from Aug 2025. Treasurer must confirm.'],
            ['contribution.due_day', 'Siku ya mwisho ya mchango wa mwezi', 'Monthly contribution due day', 'number', ['value' => null],
                '[C] 05/07/2026: "kama mwezi wa sita unadaiwa … baada ya saa6 usiku leo una fine" suggests June was due by 5 July, but no rule is written down. VALUE MISSING.'],
            ['contribution.entry_fee', 'Kiingilio', 'Entry fee for new members', 'money', ['amount' => 20000],
                '[S] 2021 sheet column KIINGILIO = 20,000 per founding member. Current amount for new members not stated; new members were asked to clear "madeni ya nyuma" [M].'],
            ['payments.penalties_first', 'Faini hukatwa kwanza kwenye malipo', 'Payments clear penalties first', 'boolean', ['value' => true],
                '[M] "mtu akitoa mchango wa mwezi kama ana penalty atakatwa kwenye michango yake ya mwezi"; repeated by the Vice-Chair in [C] 12/10/2026.'],
            ['penalty.late_monthly_amount', 'Faini ya kuchelewa mchango', 'Late monthly contribution penalty', 'money', ['amount' => null],
                '[M] "ifikapo July penalty itaanza rasmi bila huruma" — but the AMOUNT is not recorded in the minutes or the chat. VALUE MISSING.'],
            ['penalty.meeting_absence', 'Faini ya kutohudhuria kikao', 'Fine for missing a meeting', 'money', ['amount' => 10000],
                '[C] Secretary, 11/10/2026: "Kutohudhuria kikao hiki fain yake ni Sh 10000". Not found in the minutes.'],
            ['membership.arrears_months_exit', 'Miezi ya kutolipa mfululizo kabla ya kuondoka', 'Consecutive unpaid months before a member is treated as leaving', 'number', ['value' => 3],
                '[M] Constitution clause 6:02 amended: 3 consecutive unpaid months = member has chosen to leave. The app NEVER removes anyone automatically: warning → reminder → review → recorded decision.'],
            ['meetings.online_months', 'Vikao vya mtandaoni', 'Online meetings', 'list', ['value' => ['September', 'December']],
                '[M] Clause 6:03: two online meetings, in September and December.'],
            ['bereavement.fund_payout', 'Rambirambi kutoka mfuko', 'Bereavement payout from the fund', 'money', ['amount' => 200000],
                '[C] Secretary quoting the constitution, 31/01/2026 and 26/07/2026: "mfuko utatoa shilingi 200,000". Paid in both 2026 cases.'],
            ['bereavement.member_levy', 'Mchango wa kila mwanachama msibani', 'Per-member bereavement contribution', 'money', ['amount' => 30000],
                '[C] Same quote: "kila mwanachama atachanga 30,000/= ndani ya siku saba". Vice-Chair: "Huu mchango sio hiari".'],
            ['bereavement.levy_days', 'Siku za kukusanya mchango wa msiba', 'Days to collect the bereavement contribution', 'number', ['value' => 7],
                '[C] Same quote: "ndani ya siku saba".'],
            ['bereavement.eligible_relationships', 'Ndugu wanaohusika', 'Eligible relationships', 'list', ['value' => ['parent', 'child', 'spouse']],
                '[C] Same quote: "baba, mama, mtoto wa kuzaa mwenyewe, mke au mume".'],
            ['bereavement.one_payout_per_deceased', 'Rambirambi moja kwa marehemu mmoja', 'One payout per deceased person', 'boolean', ['value' => true],
                '[C] Same quote: "Ikiwa aliefariki ana ndugu zaidi ya mmoja katika kikundi … rambirambi itatolewa kwa ndugu mmoja tu."'],
            ['reserve.emergency_minimum', 'Akiba ya dharura inayobaki benki', 'Emergency reserve kept in the bank', 'money', ['amount' => 1000000],
                '[M] "pesa yote iwekwe UTT isipokuwa sh 1000000 kwa ajili ya dharura pamoja na pesa ya jamii".'],
            ['investment.policy', 'Sera ya uwekezaji', 'Investment policy', 'text', ['value' => 'Weka fedha UTT isipokuwa akiba ya dharura na mfuko wa jamii.'],
                '[M] As above. Shares proposal assigned to a member for research — not a decision.'],
            ['secreto.gift_minimum', 'Thamani ya chini ya zawadi ya Secreto', 'Secreto minimum gift value', 'money', ['amount' => 100000],
                '[M] "zawadi ya kuanzia sh 100000 na kuendelea inaweza kuwa kitu au gift voucher". Recorded as a proposal ("mapendekezo") at the meeting.'],
            ['secreto.proxy_allowed', 'Mwakilishi anaweza kuleta zawadi', 'A proxy may bring the gift', 'boolean', ['value' => true],
                '[M] "km haupo basi mkaimishe mtu wa kuiwakilisha".'],
            ['gatherings.annual_photo', 'Picha ya pamoja kila kikao cha Juni', 'Annual family photo at the June gathering', 'boolean', ['value' => true],
                '[C] Chairperson, 29/05/2026: "Ni rasmi sasa kutakuwa na picha ya pamoja kila kikao cha mwezi wa 6".'],
            ['gifts.newborn', 'Zawadi ya mtoto mchanga', 'Gift for a newborn', 'money', ['amount' => 150000],
                '[S] MATUMIZI sheet: five newborn gifts of 150,000 each. A PRACTICE, not a written rule.'],
            ['gifts.wedding', 'Zawadi ya harusi / send-off', 'Wedding / send-off gift', 'money', ['amount' => 200000],
                '[S] MATUMIZI sheet: wedding 200,000 and send-off 200,000. A PRACTICE, not a written rule.'],
            ['membership.refund_on_exit_percent', 'Marejesho kwa anayeondoka', 'Refund for a leaving member', 'text', ['value' => null],
                '[S] One historical exit: "2,000,000/21 … -30%" refund calculation. Method unclear. VALUE MISSING.'],
        ];

        foreach ($rules as [$key, $sw, $en, $type, $value, $source]) {
            PolicyRule::firstOrCreate(['key' => $key], [
                'title_sw' => $sw, 'title_en' => $en, 'value_type' => $type, 'value' => $value,
                'status' => 'source_unconfirmed', 'source_reference' => $source,
            ]);
        }
    }
}
