<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create comprehensive permissions that match AppLayout.vue checks
        $permissions = [
            // Dashboard
            'view dashboard',

            // Uploads
            'view uploads',
            'create upload',
            'delete upload',
            'export uploads',

            // Deadlines
            'view deadlines',
            'create deadline',
            'edit deadline',
            'delete deadline',

            // Submissions
            'view submissions',
            'create submissions',

            // Companies
            'view companies',
            'manage companies',

            // Municipalities
            'view municipalities',
            'manage municipalities',

            // Notifications
            'view notifications',

            // Admin permissions
            'manage users',
            'manage roles',
            'manage permissions',
            'view reports',
            'view audits',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
        $superAdminRole->syncPermissions(Permission::all());

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions([
            'view dashboard',
            'view uploads',
            'create upload',
            'export uploads',
            'view deadlines',
            'create deadline',
            'edit deadline',
            'delete deadline',
            'view submissions',
            'create submissions',
            'view companies',
            'view municipalities',
            'view notifications',
            'manage users',
            'view reports',
            'view audits',
        ]);

        $userRole = Role::firstOrCreate(['name' => 'user']);
        $userRole->syncPermissions([
            'view dashboard',
            'view uploads',
            'create upload',
            'view submissions',
            'create submissions',
        ]);

        $viewerRole = Role::firstOrCreate(['name' => 'viewer']);
        $viewerRole->syncPermissions([
            'view dashboard',
            'view uploads',
            'view deadlines',
        ]);

        // Assign super-admin role to first user
        $user = User::first();
        if ($user) {
            $user->assignRole('super-admin');
        }
    }
}
