<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some CAPS companies are registered in the org master before they are
 * assigned any deduction codes, which means CAPS has no area (and therefore
 * no municipality) scope for them yet. Rather than skip these rows during
 * the reference-data sync, we import them with `municipality_id = null` so
 * the Tracker reflects the full CAPS company list and picks up the
 * relationship automatically once CAPS assigns the company an area.
 *
 * This migration:
 *   1. Drops the existing NOT-NULL foreign key on companies.municipality_id.
 *   2. Changes the column to nullable.
 *   3. Re-adds the foreign key with ON DELETE SET NULL so orphaning a
 *      municipality clears the link instead of cascading the delete.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['municipality_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('municipality_id')->nullable()->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('municipality_id')
                ->references('id')
                ->on('municipalities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Cannot safely restore the NOT NULL constraint without knowing what
        // to do with existing NULL rows. Best-effort rollback: drop the FK,
        // attempt to mark the column NOT NULL again, and re-create the FK
        // with the original cascade-on-delete behaviour. Callers running
        // this down-migration must ensure no NULL values exist first.
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['municipality_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('municipality_id')->nullable(false)->change();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('municipality_id')
                ->references('id')
                ->on('municipalities')
                ->cascadeOnDelete();
        });
    }
};
