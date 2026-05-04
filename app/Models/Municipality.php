<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Municipality extends Model
{
    use HasFactory;
    use RecordsAuditTrail;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'province',
        'code',
        'casey_id',
        'casey_synced_at',
    ];

    protected $casts = [
        'casey_synced_at' => 'datetime',
    ];

    /**
     * Boot: only CAPS-synced municipalities by default.
     * Use Municipality::withoutGlobalScope('capsOnly') to bypass.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('capsOnly', function ($query) {
            $query->whereNotNull('casey_id')->where('casey_id', '!=', '');
        });
    }

    /**
     * Relationship: Companies in this municipality
     */
    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Relationship: Deadlines for this municipality
     */
    public function deadlines()
    {
        return $this->hasMany(MunicipalityDeadline::class);
    }

    /**
     * Relationship: User assignments for this municipality
     */
    public function assignments()
    {
        return $this->hasMany(UserAssignment::class);
    }

    /**
     * Relationship: Users assigned to this municipality
     */
    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'user_assignments')
            ->withPivot('deadline_date', 'notes', 'company_id')
            ->withTimestamps();
    }

    /**
     * Scope: Filter municipalities accessible to a user
     */
    public function scopeAccessibleToUser($query, $userId)
    {
        return $query->whereHas('assignments', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    /**
     * Get companies that are not assigned to any user for a specific date
     */
    public function getUnassignedCompanies($deadlineDate)
    {
        $assignedCompanyIds = $this->assignments()
            ->where('deadline_date', $deadlineDate)
            ->pluck('company_id')
            ->filter()
            ->toArray();

        return $this->companies()
            ->whereNotIn('id', $assignedCompanyIds)
            ->get();
    }

    /**
     * Check if municipality has any assignments for a specific date
     */
    public function hasAssignmentsForDate($deadlineDate)
    {
        return $this->assignments()
            ->where('deadline_date', $deadlineDate)
            ->exists();
    }


    /**
     * Get uploads for this municipality
     */
    public function uploads()
    {
        return $this->hasMany(Uploads::class);
    }

    /**
     * Check if municipality has an active deadline
     */
    public function hasActiveDeadline(): bool
    {
        return $this->deadlines()
            ->where('deadline_date', '>=', now())
            ->exists();
    }

    /**
     * Get the next upcoming deadline
     */
    public function getUpcomingDeadline()
    {
        return $this->deadlines()
            ->where('deadline_date', '>=', now())
            ->orderBy('deadline_date')
            ->first();
    }

    /**
     * Get all active deadlines
     */
    public function getActiveDeadlines()
    {
        return $this->deadlines()
            ->where('deadline_date', '>=', now())
            ->orderBy('deadline_date')
            ->get();
    }

    /**
     * Check if a specific deadline is approaching (within X days)
     */
    public function hasDeadlineWithin(int $days = 7): bool
    {
        return $this->deadlines()
            ->where('deadline_date', '>=', now())
            ->where('deadline_date', '<=', now()->addDays($days))
            ->exists();
    }
}
