<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CapsMember extends Model
{
    use BelongsToTenant;

    protected $table = 'caps_members';

    protected $fillable = [
        'tenant_id',
        'casey_id',
        'id_number',
        'pay_number',
        'first_name',
        'surname',
        'municipality_casey_id',
        'area_code',
        'status',
        'cell_number',
        'email',
        'employment_start_date',
        'employment_end_date',
        'casey_synced_at',
    ];

    protected $casts = [
        'employment_start_date' => 'date',
        'employment_end_date' => 'date',
        'casey_synced_at' => 'datetime',
    ];

    public function policies()
    {
        return $this->hasMany(CapsPolicy::class, 'member_casey_id', 'casey_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->surname ?? ''));
    }
}
