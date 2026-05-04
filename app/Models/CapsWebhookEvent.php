<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapsWebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'payments_batch_id',
        'submission_reference',
        'status',
        'payload',
        'upload_id',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function upload()
    {
        return $this->belongsTo(Uploads::class, 'upload_id');
    }
}
