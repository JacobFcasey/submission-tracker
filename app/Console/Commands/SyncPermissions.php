<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync';
    protected $description = 'Sync all permissions and roles';

    public function handle()
    {
        // Define all permissions
        $permissions = [
            // Dashboard
            'view dashboard',

            // Users
            'view users',
            'create user',
            'edit user',
            'delete user',
            'manage users',

            // Companies
            'view companies',
            'create company',
            'edit company',
            'delete company',
            'assign company',
            'manage companies',

            // Municipalities
            'view municipalities',
            'create municipality',
            'edit municipality',
            'delete municipality',
            'manage municipalities',

            // Uploads
            'view uploads',
            'create upload',
            'edit upload',
            'delete upload',
            'export uploads',
            'manage uploads',

            // Deadlines
            'view deadlines',
            'create deadline',
            'edit deadline',
            'delete deadline',
            'manage deadlines',

            // Submissions
            'view submissions',
            'create submission',
            'edit submission',
            'delete submission',
            'manage submissions',

            // Notifications
            'view notifications',
            'manage notifications',

            // Roles & Permissions
            'view roles',
            'create role',
            'edit role',
            'delete role',
            'manage roles',
            'view permissions',
            'manage permissions',

            // Reports
            'view reports',
            'generate reports',
            'export reports',

            // Audits
            'view audits',
            'manage audits',

            // Assignments
            'create assignment',
            'edit assignment',
            'delete assignment',
            'manage assignments',
        ];

        $this->info('Syncing permissions...');

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $this->line("✓ {$permission}");
        }

        // Create roles with permissions
        $this->info("\nSyncing roles...");

        // Super Admin Role
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());
        $this->info('✓ Super Admin role created with all permissions');

        // Admin Role
        $adminPermissions = [
            'view dashboard',
            'view users', 'create user', 'edit user', 'delete user', 'manage users',
            'view companies', 'create company', 'edit company', 'delete company', 'assign company', 'manage companies',
            'view municipalities', 'create municipality', 'edit municipality', 'delete municipality', 'manage municipalities',
            'view uploads', 'create upload', 'edit upload', 'delete upload', 'export uploads', 'manage uploads',
            'view deadlines', 'create deadline', 'edit deadline', 'delete deadline', 'manage deadlines',
            'view notifications', 'manage notifications',
            'view roles', 'manage roles',
            'view reports', 'generate reports', 'export reports',
            'view audits',
            'create assignment', 'edit assignment', 'delete assignment', 'manage assignments',
        ];

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($adminPermissions);
        $this->info('✓ Admin role created');

        // Manager Role
        $managerPermissions = [
            'view dashboard',
            'view companies', 'view municipalities',
            'view uploads', 'create upload', 'edit upload',
            'view deadlines',
            'view notifications',
            'view reports',
            'create assignment', 'edit assignment',
        ];

        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $manager->syncPermissions($managerPermissions);
        $this->info('✓ Manager role created');

        // User Role
        $userPermissions = [
            'view dashboard',
            'view uploads', 'create upload',
            'view deadlines',
            'view notifications',
        ];

        $user = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->syncPermissions($userPermissions);
        $this->info('✓ User role created');

        $this->info("\n✅ Permissions and roles synced successfully!");
    }
}
