<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;

class WebhookReplayController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function replay(int $id): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $delivery = WebhookDelivery::query()->where('tenant_id', $tenantId)->findOrFail($id);

        $delivery->attempts = $delivery->attempts + 1;
        $delivery->last_attempt_at = now();
        $delivery->status = 'replayed';
        $delivery->save();

        return response()->json([
            'message' => 'Webhook replay queued.',
            'delivery_id' => $delivery->id,
            'attempts' => $delivery->attempts,
            'status' => $delivery->status,
        ]);
    }
}

