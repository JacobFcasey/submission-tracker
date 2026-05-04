<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkflowDefinition;
use App\Services\EventTimelineService;
use App\Services\TenantContext;
use App\Services\WorkflowEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly WorkflowEngineService $workflowEngine,
        private readonly EventTimelineService $eventTimeline
    ) {}

    public function index(): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $rows = WorkflowDefinition::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant context not resolved.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'definition_json' => ['required', 'array'],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);

        $definition = WorkflowDefinition::query()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'definition_json' => $data['definition_json'],
            'version' => $data['version'] ?? 1,
            'is_active' => false,
        ]);

        $this->eventTimeline->record($tenantId, 'workflow.created', WorkflowDefinition::class, $definition->id);

        return response()->json($definition, 201);
    }

    public function publish(int $id): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $definition = WorkflowDefinition::query()->where('tenant_id', $tenantId)->findOrFail($id);

        WorkflowDefinition::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $definition->name)
            ->update(['is_active' => false]);

        $definition->is_active = true;
        $definition->save();

        $this->eventTimeline->record($tenantId, 'workflow.published', WorkflowDefinition::class, $definition->id);

        return response()->json($definition);
    }

    public function createInstance(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $definition = WorkflowDefinition::query()->where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'entity_type' => ['required', 'string'],
            'entity_id' => ['required', 'integer', 'min:1'],
            'context_json' => ['nullable', 'array'],
        ]);

        $instance = $this->workflowEngine->createInstance(
            $tenantId,
            $definition,
            $data['entity_type'],
            (int) $data['entity_id'],
            $data['context_json'] ?? []
        );

        $this->eventTimeline->record($tenantId, 'workflow.instance.created', get_class($instance), $instance->id, [
            'definition_id' => $definition->id,
            'entity_type' => $data['entity_type'],
            'entity_id' => (int) $data['entity_id'],
        ]);

        return response()->json($instance, 201);
    }
}

