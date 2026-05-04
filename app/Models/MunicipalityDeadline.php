<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MunicipalityDeadline extends Model
{
    use HasFactory;
    use RecordsAuditTrail;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'municipality_id',
        'deadline_date',
        'notes',
    ];

    protected $casts = [
        'deadline_date' => 'date',
    ];

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }
}
