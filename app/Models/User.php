<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use RecordsAuditTrail;
    use BelongsToTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'employee_number',
        'password',
        'external_password_hash',
        'phone',
        'department',
        'position',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'external_password_hash',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * Relationship: User's assignments
     */
    public function assignments()
    {
        return $this->hasMany(UserAssignment::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Municipalities assigned to user through assignments
     */
    public function assignedMunicipalities()
    {
        return $this->belongsToMany(Municipality::class, 'user_assignments')
            ->withPivot(['company_id', 'deadline_date', 'notes', 'created_at', 'updated_at'])
            ->withTimestamps();
    }

    /**
     * Relationship: Companies assigned to user through assignments
     */
    public function assignedCompanies()
    {
        return $this->belongsToMany(Company::class, 'user_assignments')
            ->withPivot(['municipality_id', 'deadline_date', 'notes', 'created_at', 'updated_at'])
            ->withTimestamps();
    }

    /**
     * Relationship: Uploads created by user
     */
    public function uploads()
    {
        return $this->hasMany(Uploads::class);
    }

    /**
     * Scope: Filter active users
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by role
     */
    public function scopeByRole($query, $roleName)
    {
        return $query->whereHas('roles', function ($q) use ($roleName) {
            $q->where('name', $roleName);
        });
    }

    /**
     * Scope: Filter by department
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope: Search users by name or email
     */
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'like', "%{$search}%")
            ->orWhere('email', 'like', "%{$search}%");
    }

    /**
     * Get user's full name with email (for display)
     */
    public function getFullNameWithEmailAttribute()
    {
        return "{$this->name} <{$this->email}>";
    }

    /**
     * Check if user is assigned to a specific municipality
     */
    public function isAssignedToMunicipality($municipalityId)
    {
        return $this->assignedMunicipalities()
            ->where('municipalities.id', $municipalityId)
            ->exists();
    }

    /**
     * Check if user is assigned to a specific company
     */
    public function isAssignedToCompany($companyId)
    {
        return $this->assignedCompanies()
            ->where('companies.id', $companyId)
            ->exists();
    }

    /**
     * Check if user is assigned to a specific company-municipality combination
     */
    public function isAssignedToCompanyInMunicipality($companyId, $municipalityId)
    {
        return $this->assignments()
            ->where('company_id', $companyId)
            ->where('municipality_id', $municipalityId)
            ->exists();
    }

    /**
     * Get user's assigned municipalities with deadlines
     */
    public function getMunicipalitiesWithDeadlines()
    {
        return $this->assignedMunicipalities()
            ->whereHas('deadlines', function ($query) {
                $query->where('deadline_date', '>=', now());
            })
            ->with(['deadlines' => function ($query) {
                $query->where('deadline_date', '>=', now())
                    ->orderBy('deadline_date', 'asc');
            }])
            ->get();
    }

    /**
     * Get user's assigned companies for a specific municipality
     */
    public function getCompaniesForMunicipality($municipalityId)
    {
        return $this->assignedCompanies()
            ->wherePivot('municipality_id', $municipalityId)
            ->get();
    }

    /**
     * Get user's pending uploads count
     */
    public function getPendingUploadsCountAttribute()
    {
        return $this->uploads()
            ->where('status', 'Pending')
            ->count();
    }

    /**
     * Get user's completed uploads count
     */
    public function getCompletedUploadsCountAttribute()
    {
        return $this->uploads()
            ->where('status', 'Completed')
            ->count();
    }

    /**
     * Get user's recent uploads
     */
    public function getRecentUploads($limit = 5)
    {
        return $this->uploads()
            ->with(['company', 'municipality'])
            ->latest('submitted_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Deactivate user
     */
    public function deactivate()
    {
        $this->update(['is_active' => false]);
        return $this;
    }

    /**
     * Activate user
     */
    public function activate()
    {
        $this->update(['is_active' => true]);
        return $this;
    }

    /**
     * Update last login information
     */
    public function updateLastLogin()
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);
    }

    /**
     * Check if user can access a specific upload
     */
    public function canAccessUpload($uploadId)
    {
        $upload = Uploads::find($uploadId);

        if (!$upload) {
            return false;
        }

        // Admin can access all uploads
        if ($this->hasRole('admin')) {
            return true;
        }

        // Check if user is assigned to the upload's company or municipality
        return $this->isAssignedToCompany($upload->company_id) ||
            $this->isAssignedToMunicipality($upload->municipality_id);
    }

    /**
     * Override the sendPasswordResetNotification method if needed
     */
    /* public function sendPasswordResetNotification($token)
     {
         // You can customize the password reset notification here
         $this->notify(new \App\Notifications\ResetPasswordNotification($token));
     }*/
}
