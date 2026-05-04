<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'provider',
        'credentials_encrypted',
        'status',
        'meta_json',
        'last_synced_at',
    ];

    protected $casts = [
        'credentials_encrypted' => 'encrypted:array',
        'meta_json' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

