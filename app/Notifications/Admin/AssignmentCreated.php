<?php

namespace App\Notifications\Admin;

use App\Models\UserAssignment;
use App\Models\User;
use App\Models\Municipality;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class AssignmentCreated extends Notification implements ShouldQueue
{
    use Queueable;

    protected $assignment;
    protected $assignedUser;
    protected $municipality;
    protected $company;

    public function __construct(UserAssignment $assignment, User $assignedUser, Municipality $municipality, ?Company $company = null)
    {
        $this->assignment = $assignment;
        $this->assignedUser = $assignedUser;
        $this->municipality = $municipality;
        $this->company = $company;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'assignment_created',
            'message' => "Assignment created for {$this->assignedUser->name}",
            'user_id' => $this->assignedUser->id,
            'user_name' => $this->assignedUser->name,
            'municipality_id' => $this->municipality->id,
            'municipality_name' => $this->municipality->name,
            'company_id' => $this->company ? $this->company->id : null,
            'company_name' => $this->company ? $this->company->name : 'No specific company',
            'deadline_date' => $this->assignment->deadline_date,
            'assignment_id' => $this->assignment->id,
            'notes' => $this->assignment->notes,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'type' => 'assignment_created',
            'data' => $this->toDatabase($notifiable),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function toArray($notifiable)
    {
        return $this->toDatabase($notifiable);
    }
}
