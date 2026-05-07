<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CapsPolicy extends Model
{
    use BelongsToTenant;

    protected $table = 'caps_policies';

    protected $fillable = [
        'tenant_id',
        'casey_id',
        'policy_code',
        'member_casey_id',
        'company_casey_id',
        'company_name',
        'premium_amount',
        'balance_amount',
        'deduction_code',
        'policy_status',
        'term',
        'casey_synced_at',
    ];

    protected $casts = [
        'premium_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'casey_synced_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(CapsMember::class, 'member_casey_id', 'casey_id');
    }
}
