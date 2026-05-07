<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Uploads extends Model
{
    use HasFactory;
    use RecordsAuditTrail;
    use BelongsToTenant;

    /**
     * CAPS dispatch workflow states. Aligns with CAPS processing stages.
     */
    public const DISPATCH_DRAFT          = 'draft';
    public const DISPATCH_VALIDATING     = 'validating';
    public const DISPATCH_DISPATCHED     = 'dispatched';
    public const DISPATCH_CAPS_PROCESSING = 'caps_processing';
    public const DISPATCH_COMPLETED      = 'completed';
    public const DISPATCH_FAILED         = 'failed';

    public const DISPATCH_STATUSES = [
        self::DISPATCH_DRAFT,
        self::DISPATCH_VALIDATING,
        self::DISPATCH_DISPATCHED,
        self::DISPATCH_CAPS_PROCESSING,
        self::DISPATCH_COMPLETED,
        self::DISPATCH_FAILED,
    ];

    // Fillable fields
    protected $fillable = [
        'tenant_id',
        'company_id',
        'municipality_id',
        'reference',
        'status',
        'user_id',
        'submitted_at',
        'original_file_path',
        'original_file_names',
        'workings_file_path',
        'workings_file_name',
        'systems_import_file_path',
        'systems_import_file_name',
        'extracted_dates',
        'system_import_date',
        'reupload_reason_type',
        'reupload_reason_note',
        'converted_eml_paths',
        'caps_payment_batch_id',
        'caps_status',
        'caps_status_detail',
        'caps_last_webhook_at',
        'caps_verification',
        'caps_verified_at',
        'caps_dispatch_status',
        'caps_batch_type',
        'caps_dispatched_at',
        'caps_errors',
        'caps_summary',
        'caps_retry_count',
        'caps_last_retry_at',
        'caps_downloadable_outputs',
    ];

    // Update the casts array:
    protected $casts = [
        'submitted_at' => 'datetime',
        'extracted_dates' => 'array',
        'system_import_date' => 'datetime',
        'original_file_path' => 'array',
        'original_file_names' => 'array',
        'converted_eml_paths' => 'array',
        'caps_last_webhook_at' => 'datetime',
        'caps_verification' => 'array',
        'caps_verified_at' => 'datetime',
        'caps_dispatched_at' => 'datetime',
        'caps_errors' => 'array',
        'caps_summary' => 'array',
        'caps_last_retry_at' => 'datetime',
        'caps_downloadable_outputs' => 'array',
    ];

    /**
     * Relationship: Company this upload belongs to
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relationship: Municipality this upload belongs to
     */
    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    // Accessors for file URLs
    public function getOriginalFileUrlsAttribute()
    {
        $paths = $this->original_file_path;

        return array_map(fn ($path) => $path ? route('uploads.download', [
            'upload' => $this->id,
            'which' => 'original',
            'index' => array_search($path, (array) $paths)
        ]) : null, (array) $paths);
    }

    public function getWorkingsFileUrlAttribute()
    {
        return $this->workings_file_path ? route('uploads.download', [
            'upload' => $this->id,
            'which' => 'workings'
        ]) : null;
    }

    public function getSystemsImportFileUrlAttribute()
    {
        return $this->systems_import_file_path ? route('uploads.download', [
            'upload' => $this->id,
            'which' => 'systems'
        ]) : null;
    }

    /**
     * Get EML file URLs for MSG files
     */
    public function getEmlFileUrlsAttribute()
    {
        $urls = [];
        $originalNames = $this->original_file_names ?? [];

        foreach ($originalNames as $index => $name) {
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($extension === 'msg') {
                $urls[$index] = route('uploads.download', [
                    'upload' => $this->id,
                    'which' => 'original',
                    'index' => $index,
                    'format' => 'eml'
                ]);
            } else {
                $urls[$index] = null;
            }
        }

        return $urls;
    }

    protected function setExtractedDatesAttribute($value)
    {
        $this->attributes['extracted_dates'] = json_encode((array) $value);
    }

    public function getWorkingsFileNameAttribute()
    {
        return $this->attributes['workings_file_name'] ?? null;
    }

    public function getSystemsImportFileNameAttribute()
    {
        return $this->attributes['systems_import_file_name'] ?? null;
    }

    public function getOriginalFileNamesAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode($value, true) ?? [];
    }

    public function getConvertedEmlPathsAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode($value, true) ?? [];
    }

    // And the mutator for original_file_names:
    public function setOriginalFileNamesAttribute($value)
    {
        $this->attributes['original_file_names'] = json_encode((array) $value);
    }

    public function setConvertedEmlPathsAttribute($value)
    {
        $this->attributes['converted_eml_paths'] = json_encode((array) $value);
    }

    /**
     * Scope: Filter uploads accessible to a user based on assignments
     */
    public function scopeAccessibleToUser($query, $userId)
    {
        return $query->whereHas('company.assignments', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->orWhereHas('municipality.assignments', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    /**
     * Scope: Filter uploads by municipality
     */
    public function scopeByMunicipality($query, $municipalityId)
    {
        return $query->where('municipality_id', $municipalityId);
    }

    /**
     * Scope: Filter uploads by company
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope: Filter uploads by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    public function hasAllRequiredFiles(): bool
    {
        return !empty($this->original_file_path) &&
            !empty($this->workings_file_path) &&
            !empty($this->systems_import_file_path);
    }

    /**
     * Get the next required file type
     */
    public function getNextRequiredFile(): string
    {
        if (empty($this->original_file_path)) {
            return 'original_files';
        }

        if (empty($this->workings_file_path)) {
            return 'workings_file';
        }

        if (empty($this->systems_import_file_path)) {
            return 'systems_import_file';
        }

        return 'none';
    }

    /**
     * Get human-readable missing files list
     */
    public function getMissingFilesList(): array
    {
        $missing = [];

        if (empty($this->original_file_path)) {
            $missing[] = 'Email Files (EML/MSG)';
        }

        if (empty($this->workings_file_path)) {
            $missing[] = 'Workings File';
        }

        if (empty($this->systems_import_file_path)) {
            $missing[] = 'Systems Import File';
        }

        return $missing;
    }

    /**
     * Check if upload is accessible to a specific user
     */
    public function isAccessibleToUser($userId)
    {
        return $this->company->assignments()->where('user_id', $userId)->exists() ||
            $this->municipality->assignments()->where('user_id', $userId)->exists();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function capsWebhookEvents()
    {
        return $this->hasMany(CapsWebhookEvent::class, 'upload_id');
    }

    public function isComplete(): bool
    {
        return $this->status === 'Completed';
    }

    /**
     * Can this upload be dispatched to CAPS?
     */
    public function canDispatchToCaps(): bool
    {
        return $this->hasAllRequiredFiles()
            && in_array($this->caps_dispatch_status, [self::DISPATCH_DRAFT, self::DISPATCH_FAILED], true);
    }

    /**
     * Can this upload be retried (re-dispatched) to CAPS?
     */
    public function canRetryDispatch(): bool
    {
        return $this->caps_dispatch_status === self::DISPATCH_FAILED;
    }

    /**
     * Is this upload currently being processed by CAPS?
     */
    public function isCapsPending(): bool
    {
        return in_array($this->caps_dispatch_status, [
            self::DISPATCH_DISPATCHED,
            self::DISPATCH_CAPS_PROCESSING,
        ], true);
    }

    /**
     * Scope: uploads that are ready for CAPS dispatch.
     */
    public function scopeReadyForDispatch($query)
    {
        return $query->where('caps_dispatch_status', self::DISPATCH_DRAFT)
            ->whereNotNull('original_file_path')
            ->whereNotNull('workings_file_path')
            ->whereNotNull('systems_import_file_path');
    }

    /**
     * Scope: uploads that have been dispatched but not yet completed.
     */
    public function scopePendingCapsResult($query)
    {
        return $query->whereIn('caps_dispatch_status', [
            self::DISPATCH_DISPATCHED,
            self::DISPATCH_CAPS_PROCESSING,
        ]);
    }

    public function getMissingFiles(): array
    {
        $missing = [];

        if (empty($this->original_file_path)) {
            $missing[] = 'Original Files';
        }

        if (empty($this->workings_file_path)) {
            $missing[] = 'Workings File';
        }

        if (empty($this->systems_import_file_path)) {
            $missing[] = 'System Import File';
        }

        return $missing;
    }

}
