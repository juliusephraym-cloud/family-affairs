<?php

namespace Database\Seeders;

use App\Models\Fund;
use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

/**
 * Funds and chart of accounts. Structure only — no balances. Opening
 * balances are entered by the treasurer in the app (Finance → Opening balances).
 */
class FinanceStructureSeeder extends Seeder
{
    public function run(): void
    {
        $funds = [
            ['general', 'Mfuko Mkuu', 'General family fund', 10, 'Monthly contributions (general portion), penalties, donations.'],
            ['welfare', 'Mfuko wa Jamii', 'Welfare (jamii) fund', 20, 'Welfare portion of contributions and bereavement levies. Pays condolence support.'],
            ['emergency', 'Akiba ya Dharura', 'Emergency reserve', 30, 'Ring-fenced reserve. The June 2026 minutes record TSh 1,000,000 — needs confirmation.'],
            ['investment', 'Uwekezaji', 'Investment / savings', 40, 'Money placed in UTT or other approved investments.'],
            ['charity', 'Sadaka ya Wahitaji', 'Charity (sadaka)', 50, 'Offerings collected for people in need.'],
            ['events', 'Matukio', 'Events', 60, 'Money raised for specific gatherings.'],
        ];
        foreach ($funds as [$key, $sw, $en, $sort, $desc]) {
            Fund::firstOrCreate(['key' => $key], ['name_sw' => $sw, 'name_en' => $en, 'sort' => $sort, 'description' => $desc]);
        }

        $accounts = [
            ['1000', 'Akaunti ya Pamoja ya Benki (Joint bank account)', 'asset', 'bank', true],
            ['1010', 'Pesa ya Simu ya Mhazini (Treasurer mobile money)', 'asset', 'mobile_money', true],
            ['1020', 'Fedha Taslimu (Cash on hand)', 'asset', 'cash', true],
            ['1100', 'Uwekezaji UTT (UTT investment, at cost)', 'asset', 'investment', false],
            ['2100', 'Malipo ya Ziada ya Wanachama (Member credit)', 'liability', 'member_credit', false],
            ['3000', 'Salio la Kuanzia (Opening balances)', 'equity', null, false],
            ['3100', 'Uhamisho kati ya Mifuko (Inter-fund transfers)', 'equity', null, false],
            ['4000', 'Michango ya Mwezi (Monthly contributions)', 'income', null, false],
            ['4010', 'Kiingilio (Entry fees)', 'income', null, false],
            ['4100', 'Michango ya Jamii/Rambirambi (Welfare levies)', 'income', null, false],
            ['4200', 'Faini (Penalties)', 'income', null, false],
            ['4300', 'Sadaka (Charity offerings)', 'income', null, false],
            ['4400', 'Michango Mingine (Donations & other)', 'income', null, false],
            ['4500', 'Faida ya Uwekezaji (Investment returns)', 'income', null, false],
            ['5000', 'Gharama za Matukio (Event expenses)', 'expense', null, false],
            ['5100', 'Msaada wa Jamii na Rambirambi (Welfare payouts)', 'expense', null, false],
            ['5200', 'Sadaka Iliyotolewa (Charity disbursed)', 'expense', null, false],
            ['5300', 'Zawadi na Pongezi (Gifts & celebrations)', 'expense', null, false],
            ['5400', 'Gharama za Uendeshaji (Administration)', 'expense', null, false],
            ['5500', 'Marejesho kwa Wanachama (Member refunds)', 'expense', null, false],
        ];
        foreach ($accounts as [$code, $name, $type, $subtype, $deposits]) {
            LedgerAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'accepts_deposits' => $deposits]);
        }
    }
}
