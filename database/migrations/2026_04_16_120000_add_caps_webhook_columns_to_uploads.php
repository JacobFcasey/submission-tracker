<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layer 3 – Status Echo-back from CAPS.
 *
 * Adds columns that let the Tracker reflect the real processing status of a
 * payment batch inside CAPS, received via webhook events.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            // The CAPS payment batch ID that this upload corresponds to.
            // Populated when a webhook event arrives carrying a reference
            // or batch ID that matches this upload.
            $table->string('caps_payment_batch_id')->nullable()->after('status');
            $table->index('caps_payment_batch_id', 'uploads_caps_batch_id_idx');

            // Mirrors the processing status reported by CAPS:
            //   imported, allocated, failed, refund_created, etc.
            $table->string('caps_status')->nullable()->after('caps_payment_batch_id');

            // Free-text detail — error messages from a failed event, or
            // summary info from a successful one.
            $table->text('caps_status_detail')->nullable()->after('caps_status');

            // Timestamp of the most recent webhook received for this upload.
            $table->timestamp('caps_last_webhook_at')->nullable()->after('caps_status_detail');
        });

        // Separate table to log every inbound webhook for auditability and
        // idempotency (duplicate detection via event_id).
        Schema::create('caps_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->string('payments_batch_id')->nullable()->index();
            $table->string('submission_reference')->nullable()->index();
            $table->string('status')->nullable();
            $table->json('payload');
            $table->foreignId('upload_id')->nullable()->constrained('uploads')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caps_webhook_events');

        Schema::table('uploads', function (Blueprint $table) {
            $table->dropIndex('uploads_caps_batch_id_idx');
            $table->dropColumn([
                'caps_payment_batch_id',
                'caps_status',
                'caps_status_detail',
                'caps_last_webhook_at',
            ]);
        });
    }
};
