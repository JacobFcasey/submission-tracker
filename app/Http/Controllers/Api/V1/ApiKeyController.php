<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function index(): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $keys = ApiKey::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->get(['id', 'name', 'scopes_json', 'last_used_at', 'revoked_at', 'created_at']);

        return response()->json($keys);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        if (! $tenantId) {
            return response()->json(['message' => 'Tenant context not resolved.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes_json' => ['nullable', 'array'],
        ]);

        $prefix = 'tk_' . Str::lower(Str::random(10));
        $secret = Str::random(48);

        $key = ApiKey::query()->create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'scopes_json' => $data['scopes_json'] ?? ['*'],
            'key_hash' => hash('sha256', $secret),
        ]);

        return response()->json([
            'id' => $key->id,
            'name' => $key->name,
            'plain_key' => $prefix . '.' . $secret,
            'scopes_json' => $key->scopes_json,
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->tenantContext->tenantId();
        $key = ApiKey::query()->where('tenant_id', $tenantId)->findOrFail($id);
        $key->revoked_at = now();
        $key->save();

        return response()->json(['message' => 'API key revoked.']);
    }
}

