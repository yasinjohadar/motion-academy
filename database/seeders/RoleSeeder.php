<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (Permissions::panels() as $panelConfig) {
            foreach (array_keys($panelConfig['roles'] ?? []) as $roleName) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions(Permissions::forRole($roleName));
            }
        }
    }
}
