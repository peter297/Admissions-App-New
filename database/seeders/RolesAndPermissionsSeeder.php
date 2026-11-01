<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'learners.view', 'learners.create', 'learners.edit', 'learners.move', 'learners.import', 'learners.export',
            'classes.view',
            'admissions.view', 'admissions.manage', 'admissions.promote', 'admissions.export',
            'alumni.view',
            'transport.view', 'transport.manage', 'transport.export',
            'academic-years.manage',
            'settings.manage',   // roles, classes, streams
            'users.manage',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        $branchAdmin = [
            'dashboard.view',
            'learners.view', 'learners.create', 'learners.edit', 'learners.move', 'learners.import', 'learners.export',
            'classes.view', 'admissions.view', 'admissions.manage', 'admissions.promote', 'admissions.export',
            'alumni.view', 'transport.view', 'transport.manage', 'transport.export',
        ];

        $roles = [
            'Super Admin'        => $permissions,
            'Principal'          => ['dashboard.view', 'learners.view', 'learners.export', 'classes.view',
                                     'admissions.view', 'admissions.export', 'alumni.view',
                                     'transport.view', 'transport.export'],
            // Branch restriction comes from users.branch_id, not from the role.
            'Deputy Principal'   => ['dashboard.view', 'learners.view', 'learners.export', 'classes.view',
                                     'admissions.view', 'alumni.view', 'transport.view'],
            'Admin (Juja Road)'  => $branchAdmin,
            'Admin (Kitisuru)'   => $branchAdmin,
            'Admin (South C)'    => $branchAdmin,
            'Head of Admissions' => ['dashboard.view', 'admissions.view', 'admissions.manage',
                                     'admissions.promote', 'admissions.export', 'classes.view'],
        ];

        foreach ($roles as $name => $perms) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->syncPermissions($perms);
        }
    }
}
