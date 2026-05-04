<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scoped_api_keys_can_be_created_and_listed(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Alpha',
            'slug' => 'tenant-alpha',
            'status' => 'active',
            'plan' => 'starter',
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($user);

        $create = $this->withHeader('X-Tenant', 'tenant-alpha')
            ->postJson('/api/v1/api-keys', [
                'name' => 'Partner Key',
                'scopes_json' => ['*'],
            ]);

        $create->assertCreated()->assertJsonStructure([
            'id',
            'name',
            'plain_key',
            'scopes_json',
        ]);

        $index = $this->withHeader('X-Tenant', 'tenant-alpha')
            ->getJson('/api/v1/api-keys');

        $index->assertOk()->assertJsonCount(1);
    }

    public function test_workflow_definition_and_instance_can_be_created(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant Beta',
            'slug' => 'tenant-beta',
            'status' => 'active',
            'plan' => 'pro',
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        Sanctum::actingAs($user);

        $definition = $this->withHeader('X-Tenant', 'tenant-beta')
            ->postJson('/api/v1/workflows', [
                'name' => 'Submission Approval',
                'definition_json' => [
                    'initial_state' => 'queued',
                    'states' => [
                        ['key' => 'queued'],
                        ['key' => 'approved'],
                    ],
                    'sla_days' => 2,
                ],
            ]);

        $definition->assertCreated();
        $workflowId = (int) $definition->json('id');

        $publish = $this->withHeader('X-Tenant', 'tenant-beta')
            ->postJson("/api/v1/workflows/{$workflowId}/publish");
        $publish->assertOk()->assertJsonPath('is_active', true);

        $instance = $this->withHeader('X-Tenant', 'tenant-beta')
            ->postJson("/api/v1/workflows/{$workflowId}/instances", [
                'entity_type' => 'uploads',
                'entity_id' => 1001,
                'context_json' => ['source' => 'test'],
            ]);
        $instance->assertCreated()->assertJsonPath('state', 'queued');

        $events = $this->withHeader('X-Tenant', 'tenant-beta')
            ->getJson('/api/v1/events?event_type=workflow.instance.created');
        $events->assertOk();
    }
}

