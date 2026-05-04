<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->string('plan')->default('starter');
            $table->string('billing_customer_id')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('tenant_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->json('branding_json')->nullable();
            $table->json('security_json')->nullable();
            $table->json('workflow_json')->nullable();
            $table->timestamps();
            $table->unique('tenant_id');
        });

        Schema::create('api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('key_hash', 128)->unique();
            $table->json('scopes_json')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'revoked_at']);
        });

        Schema::create('integration_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->json('credentials_encrypted')->nullable();
            $table->string('status')->default('disconnected');
            $table->json('meta_json')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'provider']);
        });

        Schema::create('workflow_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('version')->default(1);
            $table->json('definition_json');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('workflow_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('state');
            $table->timestamp('due_at')->nullable();
            $table->json('context_json')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'state']);
        });

        Schema::create('event_log', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('event_type');
            $table->json('payload_json')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'event_type']);
            $table->index(['tenant_id', 'occurred_at']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('event_id')->nullable();
            $table->string('signature')->nullable();
            $table->json('headers_json')->nullable();
            $table->json('payload_json')->nullable();
            $table->string('status')->default('received');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('event_log');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_definitions');
        Schema::dropIfExists('integration_connections');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};

