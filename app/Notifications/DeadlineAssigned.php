<?php

namespace App\Notifications;

use App\Models\MunicipalityDeadline;
use App\Models\Municipality;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class DeadlineAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public $deadline;
    public $municipality;
    public $company;
    public $companyCount;
    public $deadlineDate;

    public function __construct($param1, $param2, $param3 = null)
    {
        // Handle different constructor signatures
        if ($param1 instanceof MunicipalityDeadline) {
            $this->deadline = $param1;
            $this->municipality = $param2;
            $this->companyCount = $param3 ?? 0;
        } elseif ($param1 instanceof Municipality) {
            $this->municipality = $param1;
            $this->company = $param2;
            $this->deadlineDate = $param3;
        }
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        if ($this->deadline) {
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
                'message' => "You have been assigned to {$this->companyCount} compan" .
                    ($this->companyCount > 1 ? 'ies' : 'y') .
                    " for {$this->municipality->name} deadline on " .
                    $this->deadline->deadline_date->format('M j, Y') .
                    " ({$timeRemaining} {$timeSuffix})",
                'deadline_id' => $this->deadline->id,
                'municipality_id' => $this->municipality->id,
                'municipality_name' => $this->municipality->name,
                'deadline_date' => $this->deadline->deadline_date,
                'company_count' => $this->companyCount,
                'days_until_deadline' => $daysUntilDeadline,
                'hours_until_deadline' => $hoursUntilDeadline,
                'type' => 'deadline_assigned',
                'timestamp' => now(),
            ];
        } else {
            $now = now();
            $rawDaysDelta = $now->diffInDays($this->deadlineDate, false);
            $isFuture = $rawDaysDelta >= 0;
            $daysComponent = (int) floor(abs($rawDaysDelta));
            $totalHoursDelta = (int) floor(abs($now->diffInHours($this->deadlineDate, false)));
            $hoursUntilDeadline = max(0, $totalHoursDelta - ($daysComponent * 24));
            $daysUntilDeadline = $isFuture ? $daysComponent : -$daysComponent;
            $timeRemaining = $daysComponent > 0
                ? $daysComponent . " day" . ($daysComponent === 1 ? '' : 's') . " and {$hoursUntilDeadline} hour" . ($hoursUntilDeadline === 1 ? '' : 's')
                : "{$hoursUntilDeadline} hour" . ($hoursUntilDeadline === 1 ? '' : 's');
            $timeSuffix = $isFuture ? 'remaining' : 'overdue';

            return [
                'message' => "You have been assigned to {$this->company->name} in {$this->municipality->name} for deadline on " .
                    $this->deadlineDate->format('M j, Y') .
                    " ({$timeRemaining} {$timeSuffix})",
                'municipality_id' => $this->municipality->id,
                'municipality_name' => $this->municipality->name,
                'company_id' => $this->company->id,
                'company_name' => $this->company->name,
                'deadline_date' => $this->deadlineDate,
                'days_until_deadline' => $daysUntilDeadline,
                'hours_until_deadline' => $hoursUntilDeadline,
                'type' => 'deadline_assigned',
                'timestamp' => now(),
            ];
        }
    }
}
