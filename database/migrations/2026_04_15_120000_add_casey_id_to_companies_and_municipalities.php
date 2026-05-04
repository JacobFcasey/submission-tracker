<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add `casey_id` plus a `casey_synced_at` timestamp to the companies and
     * municipalities tables so each Tracker row can be linked back to the
     * canonical organisation in CAPS. CAPS organisation IDs are strings, so
     * we store them as strings rather than unsigned bigints.
     */
    public function up(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            if (! Schema::hasColumn('municipalities', 'casey_id')) {
                $table->string('casey_id')->nullable()->after('id');
                $table->unique('casey_id', 'municipalities_casey_id_unique');
            }
            if (! Schema::hasColumn('municipalities', 'casey_synced_at')) {
                $table->timestamp('casey_synced_at')->nullable()->after('code');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'casey_id')) {
                $table->string('casey_id')->nullable()->after('id');
                $table->unique('casey_id', 'companies_casey_id_unique');
            }
            if (! Schema::hasColumn('companies', 'casey_synced_at')) {
                $table->timestamp('casey_synced_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('municipalities', function (Blueprint $table) {
            if (Schema::hasColumn('municipalities', 'casey_id')) {
                $table->dropUnique('municipalities_casey_id_unique');
                $table->dropColumn('casey_id');
            }
            if (Schema::hasColumn('municipalities', 'casey_synced_at')) {
                $table->dropColumn('casey_synced_at');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'casey_id')) {
                $table->dropUnique('companies_casey_id_unique');
                $table->dropColumn('casey_id');
            }
            if (Schema::hasColumn('companies', 'casey_synced_at')) {
                $table->dropColumn('casey_synced_at');
            }
        });
    }
};
