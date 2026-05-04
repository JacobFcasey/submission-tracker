<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Audit extends Model
{
    use BelongsToTenant;

    protected $table = 'audits';

    protected $guarded = [];

    protected $appends = [
        'auditable_label',
        'changes_count',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getAuditableLabelAttribute(): string
    {
        return match ($this->auditable_type) {
            User::class => 'User',
            Company::class => 'Company',
            Municipality::class => 'Municipality',
            MunicipalityDeadline::class => 'MunicipalityDeadline',
            Uploads::class => 'Uploads',
            UserAssignment::class => 'UserAssignment',
            default => class_basename((string) $this->auditable_type),
        };
    }

    public function getChangesCountAttribute(): int
    {
        return count($this->old_values ?? []) + count($this->new_values ?? []);
    }
}
