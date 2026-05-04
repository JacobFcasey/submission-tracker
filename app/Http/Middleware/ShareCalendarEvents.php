<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\MunicipalityDeadline;
use App\Models\UserAssignment;
use Carbon\Carbon;

class ShareCalendarEvents
{
    public function handle(Request $request, Closure $next)
    {
        $events = [];

        if ($request->user() && $request->user()->can('view deadlines')) {
            // Get events for the current month
            $startOfMonth = Carbon::now()->startOfMonth();
            $endOfMonth = Carbon::now()->endOfMonth();

            // Get deadlines
            $deadlines = MunicipalityDeadline::with('municipality')
                ->whereBetween('deadline_date', [$startOfMonth, $endOfMonth])
                ->get();

            // Get assignments
            $assignments = UserAssignment::with(['user', 'municipality', 'company'])
                ->whereBetween('deadline_date', [$startOfMonth, $endOfMonth])
                ->get();

            // Combine deadlines and assignments
            foreach ($deadlines as $deadline) {
                $daysUntil = now()->diffInDays($deadline->deadline_date, false);

                $events[] = [
                    'id' => 'deadline_' . $deadline->id,
                    'title' => 'Deadline: ' . $deadline->municipality->name,
                    'start' => $deadline->deadline_date->format('Y-m-d'),
                    'allDay' => true,
                    'color' => $this->getEventColor($daysUntil),
                    'extendedProps' => [
                        'type' => 'deadline',
                        'company_name' => '',
                        'municipality_name' => $deadline->municipality->name,
                        'user_name' => '',
                        'notes' => $deadline->notes,
                        'days_until' => $daysUntil,
                        'is_overdue' => $daysUntil < 0,
                        'is_today' => $daysUntil === 0,
                        'is_tomorrow' => $daysUntil === 1,
                        'status' => 'Deadline'
                    ]
                ];
            }

            foreach ($assignments as $assignment) {
                $daysUntil = now()->diffInDays($assignment->deadline_date, false);

                $events[] = [
                    'id' => 'assignment_' . $assignment->id,
                    'title' => $assignment->company->name . ' - ' . $assignment->user->name,
                    'start' => $assignment->deadline_date->format('Y-m-d'),
                    'allDay' => true,
                    'color' => $this->getEventColor($daysUntil),
                    'extendedProps' => [
                        'type' => 'assignment',
                        'company_name' => $assignment->company->name,
                        'municipality_name' => $assignment->municipality->name,
                        'user_name' => $assignment->user->name,
                        'notes' => $assignment->notes,
                        'days_until' => $daysUntil,
                        'is_overdue' => $daysUntil < 0,
                        'is_today' => $daysUntil === 0,
                        'is_tomorrow' => $daysUntil === 1,
                        'status' => 'Assigned'
                    ]
                ];
            }
        }

        // Share with all views
        view()->share('calendarEvents', $events);

        return $next($request);
    }

    private function getEventColor($daysUntil)
    {
        if ($daysUntil < 0) return '#ef4444'; // red-500
        if ($daysUntil === 0) return '#f97316'; // orange-500
        if ($daysUntil === 1) return '#eab308'; // yellow-500
        return '#059669'; // green-600
    }
}
