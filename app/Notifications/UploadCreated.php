<?php

namespace App\Notifications;

use App\Models\Uploads;
use App\Models\Municipality;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class UploadCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public $upload;
    public $municipality;
    public $company;
    public $totalCount;

    // In UploadCreated.php
    public function __construct(Uploads $upload, Municipality $municipality, Company $company, $totalCount = 1)
    {
        $this->upload = $upload;
        $this->municipality = $municipality;
        $this->company = $company;
        $this->totalCount = $totalCount;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => $this->totalCount > 1
                ? "You submitted {$this->totalCount} uploads for {$this->municipality->name}"
                : "Upload submitted for {$this->company->name} in {$this->municipality->name}",
            'upload_id' => $this->upload->id,
            'reference' => $this->upload->reference,
            'municipality_id' => $this->municipality->id,
            'municipality_name' => $this->municipality->name,
            'company_id' => $this->company->id,
            'company_name' => $this->company->name,
            'total_count' => $this->totalCount,
            'type' => 'upload_created',
            'timestamp' => now(),
            'user_id' => $this->upload->user_id, // Add user_id for filtering
            'for_admin' => true, // Flag for admin notifications
        ];
    }

// Add this method to specify who should receive this notification
    public function shouldSend($notifiable)
    {
        // Send to the user who created the upload AND to admins
        return $notifiable->id === $this->upload->user_id ||
            $notifiable->hasRole('admin') ||
            $notifiable->hasRole('superadmin');
    }
}
