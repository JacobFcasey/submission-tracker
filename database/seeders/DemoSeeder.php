<?php

namespace Database\Seeders;

use App\Models\CapsMember;
use App\Models\CapsPolicy;
use App\Models\Company;
use App\Models\Municipality;
use App\Models\MunicipalityDeadline;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TicketMessage;
use App\Models\Uploads;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(
            file_get_contents(database_path('seeders/data/demo.json')),
            true
        );

        // ── Tenant ────────────────────────────────────────────────
        $tenant = Tenant::firstOrCreate(
            ['slug' => $data['tenant']['slug']],
            $data['tenant']
        );

        // ── Roles & Permissions (reuse from DatabaseSeeder logic) ─
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view dashboard', 'view uploads', 'create upload', 'delete upload',
            'export uploads', 'view deadlines', 'create deadline', 'edit deadline',
            'delete deadline', 'view submissions', 'create submissions',
            'view companies', 'manage companies', 'view municipalities',
            'manage municipalities', 'view notifications', 'manage users',
            'manage roles', 'manage permissions', 'view reports', 'view audits',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'view dashboard', 'view uploads', 'create upload', 'export uploads',
            'view deadlines', 'create deadline', 'edit deadline', 'delete deadline',
            'view submissions', 'create submissions', 'view companies',
            'view municipalities', 'view notifications', 'manage users',
            'view reports', 'view audits',
        ]);

        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $userRole->syncPermissions([
            'view dashboard', 'view uploads', 'create upload', 'view deadlines',
            'view submissions', 'create submissions', 'view companies',
            'view municipalities', 'view notifications', 'export uploads',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->syncPermissions([
            'view dashboard', 'view uploads', 'view deadlines',
        ]);

        $companyUser = Role::firstOrCreate(['name' => 'company-user', 'guard_name' => 'web']);
        $companyUser->syncPermissions([
            'view dashboard', 'view uploads', 'create upload',
            'view submissions', 'create submissions',
        ]);

        // ── Municipalities ────────────────────────────────────────
        $muniMap = []; // code => model
        foreach ($data['municipalities'] as $m) {
            $muniMap[$m['code']] = Municipality::firstOrCreate(
                ['casey_id' => $m['casey_id']],
                array_merge($m, [
                    'tenant_id' => $tenant->id,
                    'casey_synced_at' => now(),
                ])
            );
        }

        // ── Companies ─────────────────────────────────────────────
        $companyMap = []; // casey_id => model
        foreach ($data['companies'] as $c) {
            $muniCode = $c['municipality_code'];
            unset($c['municipality_code']);

            $companyMap[$c['casey_id']] = Company::firstOrCreate(
                ['casey_id' => $c['casey_id']],
                array_merge($c, [
                    'tenant_id' => $tenant->id,
                    'municipality_id' => $muniMap[$muniCode]->id,
                    'casey_synced_at' => now(),
                ])
            );
        }

        // ── Users ─────────────────────────────────────────────────
        $userMap = []; // email => model
        foreach ($data['users'] as $u) {
            $role = $u['role'];
            unset($u['role']);

            $user = User::firstOrCreate(
                ['email' => $u['email']],
                array_merge($u, [
                    'tenant_id' => $tenant->id,
                    'password' => Hash::make($u['password']),
                    'email_verified_at' => now(),
                ])
            );
            $user->syncRoles($role);
            $userMap[$u['email']] = $user;
        }

        // ── Deadlines ─────────────────────────────────────────────
        foreach ($data['deadlines'] as $d) {
            MunicipalityDeadline::firstOrCreate(
                [
                    'municipality_id' => $muniMap[$d['municipality_code']]->id,
                    'deadline_date' => $d['deadline_date'],
                ],
                [
                    'tenant_id' => $tenant->id,
                    'notes' => $d['notes'],
                ]
            );
        }

        // ── User Assignments ──────────────────────────────────────
        foreach ($data['user_assignments'] as $a) {
            UserAssignment::firstOrCreate(
                [
                    'user_id' => $userMap[$a['user_email']]->id,
                    'municipality_id' => $muniMap[$a['municipality_code']]->id,
                    'company_id' => $companyMap[$a['company_casey_id']]->id,
                    'deadline_date' => $a['deadline_date'],
                ],
                [
                    'tenant_id' => $tenant->id,
                    'notes' => $a['notes'],
                ]
            );
        }

        // ── Uploads ───────────────────────────────────────────────
        $uploadMap = []; // reference => model
        foreach ($data['uploads'] as $u) {
            $uploadData = [
                'tenant_id' => $tenant->id,
                'user_id' => $userMap[$u['user_email']]->id,
                'company_id' => $companyMap[$u['company_casey_id']]->id,
                'municipality_id' => $muniMap[$u['municipality_code']]->id,
                'status' => $u['status'],
                'caps_dispatch_status' => $u['caps_dispatch_status'],
                'caps_batch_type' => $u['caps_batch_type'] ?? null,
                'caps_payment_batch_id' => $u['caps_payment_batch_id'] ?? null,
                'caps_status' => $u['caps_status'] ?? null,
                'caps_status_detail' => $u['caps_status_detail'] ?? null,
                'caps_summary' => isset($u['caps_summary']) ? json_encode($u['caps_summary']) : null,
                'caps_errors' => isset($u['caps_errors']) ? json_encode($u['caps_errors']) : null,
                'caps_retry_count' => $u['caps_retry_count'] ?? 0,
                'original_file_path' => json_encode($u['original_file_names'] ?? []),
                'original_file_names' => json_encode($u['original_file_names'] ?? []),
                'workings_file_name' => $u['workings_file_name'] ?? null,
                'workings_file_path' => $u['workings_file_name'] ?? null,
                'systems_import_file_name' => $u['systems_import_file_name'] ?? null,
                'systems_import_file_path' => $u['systems_import_file_name'] ?? null,
                'reupload_reason_type' => $u['reupload_reason_type'] ?? null,
                'reupload_reason_note' => $u['reupload_reason_note'] ?? null,
                'extracted_dates' => json_encode($u['extracted_dates'] ?? []),
                'submitted_at' => $u['submitted_at'],
            ];

            if (isset($u['caps_dispatch_status']) && $u['caps_dispatch_status'] !== 'draft') {
                $uploadData['caps_dispatched_at'] = $u['submitted_at'];
            }

            $uploadMap[$u['reference']] = Uploads::firstOrCreate(
                ['reference' => $u['reference']],
                $uploadData
            );
        }

        // ── CAPS Members ──────────────────────────────────────────
        $memberMap = [];
        foreach ($data['caps_members'] as $m) {
            $memberMap[$m['casey_id']] = CapsMember::firstOrCreate(
                ['casey_id' => $m['casey_id']],
                array_merge($m, [
                    'tenant_id' => $tenant->id,
                    'casey_synced_at' => now(),
                ])
            );
        }

        // ── CAPS Policies ─────────────────────────────────────────
        foreach ($data['caps_policies'] as $p) {
            CapsPolicy::firstOrCreate(
                ['casey_id' => $p['casey_id']],
                array_merge($p, [
                    'tenant_id' => $tenant->id,
                    'casey_synced_at' => now(),
                ])
            );
        }

        // ── Support Tickets & Messages ────────────────────────────
        foreach ($data['support_tickets'] as $t) {
            $ticketData = [
                'tenant_id' => $tenant->id,
                'subject' => $t['subject'],
                'status' => $t['status'],
                'priority' => $t['priority'],
                'category' => $t['category'],
                'company_id' => isset($t['company_casey_id']) ? $companyMap[$t['company_casey_id']]->id : null,
                'municipality_id' => isset($t['municipality_code']) ? $muniMap[$t['municipality_code']]->id : null,
                'upload_id' => isset($t['upload_reference']) ? ($uploadMap[$t['upload_reference']]->id ?? null) : null,
                'created_by' => $userMap[$t['created_by_email']]->id,
                'assigned_to' => isset($t['assigned_to_email']) ? $userMap[$t['assigned_to_email']]->id : null,
                'message_count' => count($t['messages']),
            ];

            if (in_array($t['status'], ['resolved', 'closed'])) {
                $ticketData['resolved_at'] = now();
            }
            if ($t['status'] === 'closed') {
                $ticketData['closed_at'] = now();
            }

            $ticket = SupportTicket::firstOrCreate(
                ['reference' => $t['reference']],
                $ticketData
            );

            foreach ($t['messages'] as $msg) {
                TicketMessage::firstOrCreate(
                    [
                        'ticket_id' => $ticket->id,
                        'user_id' => $userMap[$msg['user_email']]->id,
                        'body' => $msg['body'],
                    ],
                    [
                        'is_internal' => $msg['is_internal'] ?? false,
                        'created_at' => $msg['created_at'],
                        'updated_at' => $msg['created_at'],
                    ]
                );
            }
        }

        // ── Affordability Demo Profiles (storage/app/demo/) ─────
        $demoDir = storage_path('app/demo');
        if (!is_dir($demoDir)) {
            mkdir($demoDir, 0775, true);
        }

        foreach ($data['affordability_profiles'] as $profile) {
            $path = $demoDir . '/' . $profile['pay_number'] . '.json';
            file_put_contents($path, json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Affordability demo profiles written to storage/app/demo/');
        $this->command->info('Login: thabo@casey-demo.co.za / Demo@2026! (super-admin)');
    }
}
