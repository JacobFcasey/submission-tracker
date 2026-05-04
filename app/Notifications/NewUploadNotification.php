<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Municipality;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewUploadNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $user;
    public $municipality;
    public $uploadCount;

    public function __construct(User $user, Municipality $municipality, $uploadCount = 1)
    {
        $this->user = $user;
        $this->municipality = $municipality;
        $this->uploadCount = $uploadCount;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => "{$this->user->name} submitted {$this->uploadCount} upload(s) for {$this->municipality->name}",
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'municipality_id' => $this->municipality->id,
            'municipality_name' => $this->municipality->name,
            'upload_count' => $this->uploadCount,
            'type' => 'new_upload',
            'timestamp' => now(),
        ];
    }
}
