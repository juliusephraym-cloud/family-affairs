<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/** Production-safe and idempotent: never removes permissions an admin added to a role. */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Permissions::ALL as $key => [$group, $description]) {
            Permission::updateOrCreate(['key' => $key], ['group' => $group, 'description' => $description]);
        }
        $all = Permission::pluck('id', 'key');
        foreach (Permissions::roles() as $key => [$sw, $en, $sort, $perms]) {
            $role = Role::firstOrCreate(['key' => $key], ['name_sw' => $sw, 'name_en' => $en, 'sort' => $sort, 'is_system' => true]);
            if ($role->wasRecentlyCreated && $perms !== ['*']) {
                $role->permissions()->sync($all->only($perms)->values());
            }
        }
    }
}
