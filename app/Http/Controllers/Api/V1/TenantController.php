<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function current(): JsonResponse
    {
        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant context not resolved.'], 404);
        }

        return response()->json([
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'plan' => $tenant->plan,
            'settings' => $tenant->settings,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->tenant();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant context not resolved.'], 404);
        }

        $data = $request->validate([
            'branding_json' => ['nullable', 'array'],
            'security_json' => ['nullable', 'array'],
            'workflow_json' => ['nullable', 'array'],
        ]);

        $settings = TenantSetting::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            $data
        );

        return response()->json($settings);
    }
}

