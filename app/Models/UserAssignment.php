<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserAssignment extends Model
{
    use HasFactory;
    use RecordsAuditTrail;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'municipality_id',
        'company_id',
        'deadline_date',
        'notes',
    ];

    protected $casts = [
        'deadline_date' => 'date',
    ];

    /**
     * Relationship: User who owns this assignment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Municipality for this assignment
     */
    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Relationship: Company for this assignment
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope: Filter assignments by municipality
     */
    public function scopeByMunicipality($query, $municipalityId)
    {
        return $query->where('municipality_id', $municipalityId);
    }

    /**
     * Scope: Filter assignments by company
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope: Filter assignments by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
