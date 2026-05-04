<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function handle(Request $request, Closure $next, string $requiredScope = '*'): Response
    {
        $header = (string) $request->header('X-API-Key', '');
        if ($header === '' || ! str_contains($header, '.')) {
            return response()->json(['message' => 'Missing API key.'], 401);
        }

        [, $secret] = explode('.', $header, 2);
        $hash = hash('sha256', $secret);

        $apiKey = ApiKey::query()->where('key_hash', $hash)->first();
        if (! $apiKey || ! $apiKey->isActive()) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        if ($requiredScope !== '*' && ! $apiKey->hasScope($requiredScope)) {
            return response()->json(['message' => 'API key lacks required scope.'], 403);
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();
        $this->tenantContext->setTenantId($apiKey->tenant_id);
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
