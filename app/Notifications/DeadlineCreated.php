<?php

namespace App\Notifications;

use App\Models\MunicipalityDeadline;
use App\Models\Municipality;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DeadlineCreated extends Notification implements ShouldQueue
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
        $deadlineDate = $this->deadline->deadline_date;

        $rawDaysDiff = $now->diffInDays($deadlineDate, false);
        $isFuture = $rawDaysDiff >= 0;
        $days = (int) floor(abs($rawDaysDiff));
        $totalHoursDiff = (int) floor(abs($now->diffInHours($deadlineDate, false)));
        $hours = max(0, $totalHoursDiff - ($days * 24));

        // Build the time string
        $timeParts = [];

        if ($days > 0) {
            $timeParts[] = "$days " . str('day')->plural($days);
        }

        if ($hours > 0 || $days === 0) {
            $timeParts[] = "$hours " . str('hour')->plural($hours);
        }

        $timeString = implode(' and ', $timeParts);

        // Add "from now" or "ago"
        if ($isFuture) {
            $timeString = $timeString ? "in {$timeString}" : "now";
        } else {
            $timeString = $timeString ? "{$timeString} ago" : "just now";
        }

        return [
            'message' => "New deadline created for {$this->municipality->name} on " .
                $this->deadline->deadline_date->format('M j, Y') .
                " ({$timeString})",
            'deadline_id' => $this->deadline->id,
            'municipality_id' => $this->municipality->id,
            'municipality_name' => $this->municipality->name,
            'deadline_date' => $this->deadline->deadline_date,
            'days_until_deadline' => $isFuture ? $days : -$days,
            'hours_until_deadline' => $isFuture ? $hours : 0,
            'type' => 'deadline_created',
            'timestamp' => $now,
        ];
    }
}
