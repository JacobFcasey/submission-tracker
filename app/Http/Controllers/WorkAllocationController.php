<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Municipality;
use App\Models\MunicipalityDeadline;
use App\Models\User;
use App\Models\UserAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkAllocationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view deadlines');

        $user = $request->user();
        $isAdmin = $user->hasRole(['admin', 'super-admin', 'superadmin']);

        // Get all municipalities with their upcoming deadlines
        $municipalities = Municipality::with(['deadlines' => function ($q) {
            $q->where('deadline_date', '>=', now()->startOfDay())
                ->orderBy('deadline_date');
        }])->get();

        // Get all assignments grouped by municipality
        $assignments = UserAssignment::with(['user:id,name,email', 'company:id,name', 'municipality:id,name'])
            ->when(!$isAdmin, function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        // Build allocation data grouped by municipality
        $allocations = [];
        foreach ($municipalities as $muni) {
            $muniAssignments = $assignments->where('municipality_id', $muni->id);
            if ($muniAssignments->isEmpty() && !$isAdmin) continue;

            $deadline = $muni->deadlines->first();

            $companies = $muniAssignments->groupBy('company_id')->map(function ($group) {
                $first = $group->first();
                return [
                    'company_id' => $first->company_id,
                    'company_name' => $first->company?->name ?? '—',
                    'assigned_users' => $group->map(fn ($a) => [
                        'id' => $a->user?->id,
                        'name' => $a->user?->name ?? '—',
                        'email' => $a->user?->email,
                    ])->unique('id')->values(),
                    'deadline_date' => $first->deadline_date,
                    'notes' => $first->notes,
                ];
            })->values();

            $allocations[] = [
                'municipality_id' => $muni->id,
                'municipality_name' => $muni->name,
                'deadline_date' => $deadline?->deadline_date?->format('Y-m-d'),
                'deadline_day' => $deadline?->deadline_date?->day,
                'total_companies' => $companies->count(),
                'companies' => $companies,
            ];
        }

        // Get unique assigned users for the filter
        $assignedUsers = $assignments->pluck('user')->filter()->unique('id')
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values();

        return Inertia::render('Allocations/Index', [
            'allocations' => $allocations,
            'assignedUsers' => $assignedUsers,
            'filters' => $request->only(['search', 'user_id']),
        ]);
    }
}
