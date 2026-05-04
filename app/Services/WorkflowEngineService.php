<?php

namespace App\Services;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use InvalidArgumentException;

class WorkflowEngineService
{
    public function createInstance(
        int $tenantId,
        WorkflowDefinition $definition,
        string $entityType,
        int $entityId,
        array $context = []
    ): WorkflowInstance {
        if ($definition->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('Workflow definition does not belong to tenant.');
        }

        $states = data_get($definition->definition_json, 'states', []);
        $initialState = data_get($definition->definition_json, 'initial_state') ?? ($states[0]['key'] ?? 'draft');

        return WorkflowInstance::query()->create([
            'tenant_id' => $tenantId,
            'workflow_definition_id' => $definition->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'state' => $initialState,
            'due_at' => now()->addDays((int) data_get($definition->definition_json, 'sla_days', 3)),
            'context_json' => $context,
        ]);
    }

    public function transition(WorkflowInstance $instance, string $nextState, array $context = []): WorkflowInstance
    {
        $instance->state = $nextState;
        $instance->context_json = array_merge($instance->context_json ?? [], $context);
        $instance->save();

        return $instance;
    }
}

