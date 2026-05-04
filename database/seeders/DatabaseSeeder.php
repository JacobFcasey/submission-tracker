<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $defaultTenant = Tenant::query()->firstOrCreate(
            ['slug' => 'default'],
            [
                'name' => 'Default Tenant',
                'status' => 'active',
                'plan' => 'starter',
            ]
        );

        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

       // $this->call([
           // MunicipalitiesSeeder::class,
           // CompaniesSeeder::class,
        //]);

        // Create comprehensive permissions that match the sidebar checks
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

        // Define roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $companyUserRole = Role::firstOrCreate(['name' => 'company-user', 'guard_name' => 'web']);

        // Assign permissions
        $superAdminRole->givePermissionTo(Permission::all());

        $adminRole->givePermissionTo([
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

        $companyUserRole->givePermissionTo([
            'view dashboard',
            'view uploads',
            'create upload',
            'view submissions',
            'create submissions',
        ]);

        // Create users
        $superAdmin = User::firstOrCreate(
            ['email' => 'super-admin@example.com'],
            [
                'tenant_id' => $defaultTenant->id,
                'name' => 'Super Admin',
                'employee_number' => 'superadmin001',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => '1',
            ]
        );
        $superAdmin->assignRole($superAdminRole);

        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'tenant_id' => $defaultTenant->id,
                'employee_number' => 'USER001',
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => '1',
            ]
        );
        $adminUser->assignRole($adminRole);

        $companyUser = User::factory()->create([
            'tenant_id' => $defaultTenant->id,
            'employee_number' => 'companyuser001',
            'name' => 'Company User',
            'email' => 'company@example.com',
            'is_active' => '1',
        ]);
        $companyUser->assignRole($companyUserRole);

        $this->call([
            ExternalSystemUsersSeeder::class,
        ]);
    }
}
