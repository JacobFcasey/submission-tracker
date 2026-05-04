<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventLog extends Model
{
    use HasFactory;

    protected $table = 'event_log';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'event_type',
        'payload_json',
        'occurred_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

