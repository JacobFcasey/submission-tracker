<?php

namespace App\Notifications;

use App\Models\Municipality;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AssignmentRemoved extends Notification implements ShouldQueue
{
    use Queueable;

    public $municipality;
    public $company;
    public $deadlineDate;

    public function __construct(Municipality $municipality, Company $company, $deadlineDate)
    {
        $this->municipality = $municipality;
        $this->company = $company;
        $this->deadlineDate = $deadlineDate;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'message' => "Your assignment for {$this->company->name} in {$this->municipality->name} on " .
                $this->deadlineDate->format('M j, Y') . ' has been removed',
            'municipality_id' => $this->municipality->id,
            'municipality_name' => $this->municipality->name,
            'company_id' => $this->company->id,
            'company_name' => $this->company->name,
            'deadline_date' => $this->deadlineDate,
            'type' => 'assignment_removed',
            'timestamp' => now(),
        ];
    }
}
