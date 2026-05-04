<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'key_hash',
        'scopes_json',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'scopes_json' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes_json ?? [];

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}

