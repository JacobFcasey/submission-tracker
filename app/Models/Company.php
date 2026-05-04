<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory;
    use RecordsAuditTrail;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'registration_number',
        'status',
        'contact_email',
        'municipality_id',
        'casey_id',
        'casey_synced_at',
    ];

    /**
     * Boot: add global scope so only CAPS-synced companies (with casey_id)
     * are returned by default. Legacy seeded duplicates are excluded.
     * Use Company::withoutGlobalScope('capsOnly') to bypass.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('capsOnly', function ($query) {
            $query->whereNotNull('casey_id')->where('casey_id', '!=', '');
        });
    }

    protected $casts = [
        'casey_synced_at' => 'datetime',
    ];

    /**
     * Relationship: Municipality this company belongs to
     */
    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Relationship: Uploads for this company
     */
    public function uploads()
    {
        return $this->hasMany(Uploads::class);
    }

    /**
     * Relationship: User assignments for this company
     */
    public function assignments()
    {
        return $this->hasMany(UserAssignment::class);
    }

    /**
     * Relationship: Deadlines for this company through municipality
     * This assumes deadlines are at municipality level, not company level
     */
    public function deadlines()
    {
        return $this->municipality()->with('deadlines')->get()
            ->flatMap(function ($municipality) {
                return $municipality->deadlines;
            });
    }

    /**
     * Check if company has an active deadline
     * Companies inherit deadlines from their municipality. Companies synced
     * from CAPS without a resolvable municipality return false rather than
     * raising a null-pointer on the relation.
     */
    public function hasActiveDeadline()
    {
        return $this->municipality?->hasActiveDeadline() ?? false;
    }

    /**
     * Get active deadlines for this company's municipality. Returns an
     * empty collection when the company has no municipality scope.
     */
    public function getActiveDeadlines()
    {
        return $this->municipality?->getActiveDeadlines() ?? collect();
    }

    /**
     * Scope: Companies with active deadlines in their municipality
     */
    public function scopeWithActiveDeadlines($query)
    {
        return $query->whereHas('municipality.deadlines', function ($q) {
            $q->where('deadline_date', '>=', now());
        });
    }

    /**
     * Scope: Companies in a specific municipality with active deadlines
     */
    public function scopeWithActiveDeadlinesForMunicipality($query, $municipalityId)
    {
        return $query->where('municipality_id', $municipalityId)
            ->whereHas('municipality.deadlines', function ($q) {
                $q->where('deadline_date', '>=', now());
            });
    }
}
