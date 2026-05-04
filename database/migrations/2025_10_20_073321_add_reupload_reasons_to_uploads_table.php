<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (
            Schema::hasColumn('uploads', 'reupload_reason_type') &&
            Schema::hasColumn('uploads', 'reupload_reason_note')
        ) {
            return;
        }

        Schema::table('uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('uploads', 'reupload_reason_type')) {
                $table->string('reupload_reason_type')->nullable()->after('status');
            }

            if (! Schema::hasColumn('uploads', 'reupload_reason_note')) {
                $table->text('reupload_reason_note')->nullable()->after('reupload_reason_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            ! Schema::hasColumn('uploads', 'reupload_reason_type') &&
            ! Schema::hasColumn('uploads', 'reupload_reason_note')
        ) {
            return;
        }

        Schema::table('uploads', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('uploads', 'reupload_reason_type')) {
                $columns[] = 'reupload_reason_type';
            }

            if (Schema::hasColumn('uploads', 'reupload_reason_note')) {
                $columns[] = 'reupload_reason_note';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
