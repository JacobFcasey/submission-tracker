<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    private array $tables = [
        'users',
        'companies',
        'municipalities',
        'uploads',
        'audits',
        'notifications',
        'user_assignments',
        'municipality_deadlines',
        'submissions',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                $blueprint->index('tenant_id');
            });
        }

        if (Schema::hasTable('tenants')) {
            $defaultTenantId = DB::table('tenants')->where('slug', 'default')->value('id');

            if (! $defaultTenantId) {
                $defaultTenantId = DB::table('tenants')->insertGetId([
                    'name' => 'Default Tenant',
                    'slug' => 'default',
                    'status' => 'active',
                    'plan' => 'starter',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($this->tables as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                    continue;
                }

                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('tenant_id');
            });
        }
    }
};

