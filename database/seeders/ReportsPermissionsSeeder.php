<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Run once on an existing database:  php artisan db:seed --class=ReportsPermissionsSeeder
 * It only ADDS permissions (givePermissionTo), so roles you have edited keep their other settings.
 */
class ReportsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = ['history.view', 'reports.view'];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        foreach (['Principal', 'Deputy Principal', 'Admin (Juja Road)', 'Admin (Kitisuru)', 'Admin (South C)'] as $role) {
            Role::where('name', $role)->first()?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
