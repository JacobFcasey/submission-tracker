<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use BelongsToTenant;

    protected $table = 'uploads';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'municipality_id',
        'reference',
        'status',
        'submitted_at',
        'original_file_path',
        'original_file_names',
        'workings_file_path',
        'workings_file_name',
        'systems_import_file_path',
        'systems_import_file_name',
        'extracted_dates',
        'system_import_date',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'extracted_dates' => 'array',
        'system_import_date' => 'datetime',
        'original_file_path' => 'array',
        'original_file_names' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }
}
