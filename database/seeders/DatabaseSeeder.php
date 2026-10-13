<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe structure (idempotent, never overwrites leadership edits):
     * permissions & roles, funds & chart of accounts, source-derived rules
     * (all UNCONFIRMED), WhatsApp templates (all pending approval).
     *
     * Demo data is only added in local/testing; see DemoSeeder.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            FinanceStructureSeeder::class,
            PolicyRulesSeeder::class,
            NotificationSeeder::class,
        ]);

        if (app()->environment('local') || config('family.demo_mode')) {
            $this->call(DemoSeeder::class);
        }
    }
}
