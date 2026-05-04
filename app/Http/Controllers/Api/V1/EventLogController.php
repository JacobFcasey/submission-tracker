<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventLog;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventLogController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $query = EventLog::query()->where('tenant_id', $tenantId);

        if ($request->filled('entity_type')) {
            $query->where('entity_type', (string) $request->query('entity_type'));
        }
        if ($request->filled('entity_id')) {
            $query->where('entity_id', (int) $request->query('entity_id'));
        }
        if ($request->filled('event_type')) {
            $query->where('event_type', (string) $request->query('event_type'));
        }

        return response()->json(
            $query->orderByDesc('occurred_at')->paginate((int) $request->query('per_page', 50))
        );
    }
}

