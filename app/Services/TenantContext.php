<?php

namespace App\Services;

use App\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;
    private ?int $tenantId = null;

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->tenantId = $tenant?->id;
    }

    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->tenant = null;
    }

    public function tenant(): ?Tenant
    {
        if ($this->tenant !== null) {
            return $this->tenant;
        }

        return null;
    }

    public function tenantId(): ?int
    {
        return $this->tenantId ?? $this->tenant?->id;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId() !== null;
    }
}

