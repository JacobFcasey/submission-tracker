<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use App\Services\TenantResolverService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantResolverService $resolver,
        private readonly TenantContext $tenantContext
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolveFromRequest($request);
        $this->tenantContext->setTenant($tenant);

        if ($tenant) {
            $request->attributes->set('tenant', $tenant);
        }

        return $next($request);
    }
}

