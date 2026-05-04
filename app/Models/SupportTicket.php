<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\RecordsAuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupportTicket extends Model
{
    use HasFactory, RecordsAuditTrail, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'reference',
        'subject',
        'status',
        'priority',
        'category',
        'company_id',
        'municipality_id',
        'upload_id',
        'created_by',
        'assigned_to',
        'last_message_at',
        'resolved_at',
        'closed_at',
        'message_count',
        'unread_casey',
        'unread_company',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    public function upload()
    {
        return $this->belongsTo(Uploads::class, 'upload_id');
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(TicketMessage::class, 'ticket_id')->latestOfMany();
    }

    public function scopeForUser($query, User $user)
    {
        $isStaff = $user->hasRole(['admin', 'super-admin', 'superadmin', 'manager']);
        if ($isStaff) return $query;
        return $query->where('created_by', $user->id);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['resolved', 'closed']);
    }

    public function isStaffUser(User $user): bool
    {
        return $user->hasRole(['admin', 'super-admin', 'superadmin', 'manager']);
    }
}
