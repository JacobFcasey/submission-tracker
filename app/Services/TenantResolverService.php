<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Http\Request;

class TenantResolverService
{
    public function resolveFromRequest(Request $request): ?Tenant
    {
        $tenantHeader = $request->header('X-Tenant');
        if (is_string($tenantHeader) && $tenantHeader !== '') {
            $tenant = Tenant::query()->where('slug', $tenantHeader)->where('status', 'active')->first();
            if ($tenant) {
                return $tenant;
            }
        }

        $host = strtolower((string) $request->getHost());
        if ($host !== '') {
            $domain = TenantDomain::query()
                ->where('domain', $host)
                ->with('tenant')
                ->first();

            if ($domain?->tenant && $domain->tenant->status === 'active') {
                return $domain->tenant;
            }
        }

        $user = $request->user();
        if ($user && ! empty($user->tenant_id)) {
            return Tenant::query()->where('id', $user->tenant_id)->where('status', 'active')->first();
        }

        return Tenant::query()->where('slug', 'default')->where('status', 'active')->first();
    }
}
