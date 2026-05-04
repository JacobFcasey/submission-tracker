<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'workflow_definition_id',
        'entity_type',
        'entity_id',
        'state',
        'due_at',
        'context_json',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'context_json' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }
}

