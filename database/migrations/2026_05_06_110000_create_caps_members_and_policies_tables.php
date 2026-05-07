<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates synced copies of CAPS member and policy records.
 *
 * These are read-only reference tables: CAPS is the system of record.
 * The Tracker uses them for verification, reporting, and cross-referencing
 * uploaded files against the canonical member/policy dataset.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('caps_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('casey_id')->unique()->comment('CAPS member.id');
            $table->string('id_number', 20)->nullable()->index();
            $table->string('pay_number', 30)->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('surname')->nullable();
            $table->string('municipality_casey_id')->nullable()->comment('CAPS organization_id for the municipality');
            $table->string('area_code', 10)->nullable();
            $table->string('status', 30)->nullable();
            $table->string('cell_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->date('employment_start_date')->nullable();
            $table->date('employment_end_date')->nullable();
            $table->timestamp('casey_synced_at')->nullable();
            $table->timestamps();

            $table->index(['surname', 'first_name']);
        });

        Schema::create('caps_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('casey_id')->unique()->comment('CAPS policy.id');
            $table->string('policy_code', 50)->nullable()->index();
            $table->string('member_casey_id')->nullable()->index()->comment('CAPS member_id FK');
            $table->string('company_casey_id')->nullable()->index()->comment('CAPS organization_id FK');
            $table->string('company_name')->nullable();
            $table->decimal('premium_amount', 12, 2)->nullable();
            $table->decimal('balance_amount', 12, 2)->nullable();
            $table->string('deduction_code', 50)->nullable();
            $table->string('policy_status', 30)->nullable();
            $table->integer('term')->nullable();
            $table->timestamp('casey_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caps_policies');
        Schema::dropIfExists('caps_members');
    }
};
