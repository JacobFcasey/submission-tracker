<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntegrationConnection;
use App\Services\EventTimelineService;
use App\Services\Integrations\IntegrationManager;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly IntegrationManager $integrationManager,
        private readonly EventTimelineService $eventTimeline
    ) {}

    public function index(): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $rows = IntegrationConnection::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('provider')
            ->get();

        return response()->json($rows);
    }

    public function connect(Request $request, string $provider): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant context not resolved.'], 404);
        }

        $data = $request->validate([
            'credentials' => ['required', 'array'],
            'meta_json' => ['nullable', 'array'],
        ]);

        $connection = IntegrationConnection::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'provider' => $provider],
            ['status' => 'disconnected', 'meta_json' => $data['meta_json'] ?? []]
        );

        $connection = $this->integrationManager->connect($connection, $data['credentials']);
        $this->eventTimeline->record($tenantId, 'integration.connected', IntegrationConnection::class, $connection->id, [
            'provider' => $provider,
        ]);

        return response()->json($connection);
    }

    public function sync(int $id): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $connection = IntegrationConnection::query()->where('tenant_id', $tenantId)->findOrFail($id);
        $result = $this->integrationManager->sync($connection);

        $this->eventTimeline->record($tenantId, 'integration.synced', IntegrationConnection::class, $connection->id, $result);

        return response()->json($result);
    }

    public function health(int $id): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $connection = IntegrationConnection::query()->where('tenant_id', $tenantId)->findOrFail($id);

        return response()->json($this->integrationManager->healthCheck($connection));
    }
}

