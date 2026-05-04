<?php

namespace App\Notifications;

use App\Models\Municipality;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DeadlineDeleted extends Notification implements ShouldQueue
{
    use Queueable;

    public $municipality;
    public $deadlineDate;

    public function __construct(Municipality $municipality, $deadlineDate)
    {
        $this->municipality = $municipality;
        $this->deadlineDate = $deadlineDate;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => "Deadline deleted for {$this->municipality->name} that was scheduled for " .
                $this->deadlineDate->format('M j, Y'),
            'municipality_id' => $this->municipality->id,
            'municipality_name' => $this->municipality->name,
            'deadline_date' => $this->deadlineDate,
            'type' => 'deadline_deleted',
            'timestamp' => now(),
        ];
    }
}
