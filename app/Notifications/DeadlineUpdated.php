<?php

namespace App\Notifications;

use App\Models\MunicipalityDeadline;
use App\Models\Municipality;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DeadlineUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public $deadline;
    public $municipality;

    public function __construct(MunicipalityDeadline $deadline, Municipality $municipality)
    {
        $this->deadline = $deadline;
        $this->municipality = $municipality;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $now = now();
        $rawDaysDelta = $now->diffInDays($this->deadline->deadline_date, false);
        $isFuture = $rawDaysDelta >= 0;
        $daysComponent = (int) floor(abs($rawDaysDelta));
        $totalHoursDelta = (int) floor(abs($now->diffInHours($this->deadline->deadline_date, false)));
        $hoursUntilDeadline = max(0, $totalHoursDelta - ($daysComponent * 24));
        $daysUntilDeadline = $isFuture ? $daysComponent : -$daysComponent;
        $timeRemaining = $daysComponent > 0
            ? $daysComponent . " day" . ($daysComponent === 1 ? '' : 's') . " and {$hoursUntilDeadline} hour" . ($hoursUntilDeadline === 1 ? '' : 's')
            : "{$hoursUntilDeadline} hour" . ($hoursUntilDeadline === 1 ? '' : 's');
        $timeSuffix = $isFuture ? 'remaining' : 'overdue';

        return [
            'message' => "Deadline updated for {$this->municipality->name} - new date: " .
                $this->deadline->deadline_date->format('M j, Y') .
                " ({$timeRemaining} {$timeSuffix})",
            'deadline_id' => $this->deadline->id,
            'municipality_id' => $this->municipality->id,
            'municipality_name' => $this->municipality->name,
            'deadline_date' => $this->deadline->deadline_date,
            'days_until_deadline' => $daysUntilDeadline,
            'hours_until_deadline' => $hoursUntilDeadline,
            'type' => 'deadline_updated',
            'timestamp' => now(),
        ];
    }
}
