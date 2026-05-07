<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the dispatch-side CAPS integration columns that complement the existing
 * echo-back columns (caps_payment_batch_id, caps_status, etc.).
 *
 * New columns:
 *   caps_dispatch_status  – local workflow state: draft → validating → dispatched → caps_processing → completed → failed
 *   caps_batch_type       – the CAPS batch type dispatched (member_import, premium_import, payment_import, etc.)
 *   caps_dispatched_at    – when the package was sent to CAPS
 *   caps_errors           – structured JSON array of CAPS validation/processing errors
 *   caps_summary          – JSON summary counts returned by CAPS (total, processed, errors, etc.)
 *   caps_retry_count      – how many times dispatch was retried
 *   caps_last_retry_at    – timestamp of last retry
 *   caps_downloadable_outputs – JSON array of CAPS-generated file references
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->string('caps_dispatch_status', 30)->default('draft')->after('caps_verified_at')
                ->comment('Local workflow: draft|validating|dispatched|caps_processing|completed|failed');

            $table->string('caps_batch_type', 50)->nullable()->after('caps_dispatch_status')
                ->comment('CAPS batch type: member_import, premium_import, payment_import, etc.');

            $table->timestamp('caps_dispatched_at')->nullable()->after('caps_batch_type');

            $table->json('caps_errors')->nullable()->after('caps_dispatched_at')
                ->comment('Structured error array from CAPS validation/processing');

            $table->json('caps_summary')->nullable()->after('caps_errors')
                ->comment('Summary counts: {total, processed, errors, warnings}');

            $table->unsignedSmallInteger('caps_retry_count')->default(0)->after('caps_summary');

            $table->timestamp('caps_last_retry_at')->nullable()->after('caps_retry_count');

            $table->json('caps_downloadable_outputs')->nullable()->after('caps_last_retry_at')
                ->comment('CAPS-generated files: [{name, url, type}]');

            // Index for querying by dispatch status
            $table->index('caps_dispatch_status', 'idx_uploads_caps_dispatch_status');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropIndex('idx_uploads_caps_dispatch_status');
            $table->dropColumn([
                'caps_dispatch_status',
                'caps_batch_type',
                'caps_dispatched_at',
                'caps_errors',
                'caps_summary',
                'caps_retry_count',
                'caps_last_retry_at',
                'caps_downloadable_outputs',
            ]);
        });
    }
};
