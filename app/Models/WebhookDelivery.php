<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'provider',
        'event_id',
        'signature',
        'headers_json',
        'payload_json',
        'status',
        'attempts',
        'last_attempt_at',
        'processed_at',
    ];

    protected $casts = [
        'headers_json' => 'array',
        'payload_json' => 'array',
        'last_attempt_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

