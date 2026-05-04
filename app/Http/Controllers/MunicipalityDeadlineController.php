<?php

namespace App\Http\Controllers;

use App\Models\Municipality;
use App\Models\MunicipalityDeadline;
use App\Models\UserAssignment;
use App\Models\Company;
use App\Models\User;
use App\Models\Uploads;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MunicipalityDeadlineController extends Controller
{
    /**
     * Display deadlines and assignments for municipalities.
     */
    public function index(Request $request)
    {
        $this->authorize('view deadlines');

        $municipalityId = $request->get('municipality_id');
        $date = $request->get('date', now()->format('Y-m-d'));

        // Only show municipalities synced from CAPS (those with a casey_id).
        // Global scope on both models filters to CAPS-synced only.
        $municipalities = Municipality::orderBy('name')->get();
        $allCompanies = Company::orderBy('name')->get(['id', 'name']);

        $selectedMunicipality = $municipalityId
            ? Municipality::find($municipalityId)
            : null;

        $startOfMonth = Carbon::parse($date)->startOfMonth();
        $endOfMonth = Carbon::parse($date)->endOfMonth();

        // Deadlines for current month
        $deadlines = MunicipalityDeadline::with('municipality')
            ->whereBetween('deadline_date', [$startOfMonth, $endOfMonth])
            ->get()
            ->groupBy('deadline_date')
            ->map(fn($group) => $group->first());

        // Assignments for current month
        $assignments = UserAssignment::with(['user', 'municipality', 'company'])
            ->whereBetween('deadline_date', [$startOfMonth, $endOfMonth])
            ->get()
            ->map(fn($assignment) => [
                'id' => $assignment->id,
                'user_id' => $assignment->user_id,
                'user_name' => $assignment->user->name,
                'municipality_id' => $assignment->municipality_id,
                'municipality_name' => $assignment->municipality->name,
                'company_id' => $assignment->company_id,
                'company_name' => $assignment->company->name,
                'deadline_date' => $assignment->deadline_date->format('Y-m-d'),
                'notes' => $assignment->notes,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ]);

        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Deadlines/Municipalities', [
            'municipalities' => $municipalities,
            'allCompanies' => $allCompanies,
            'selectedMunicipality' => $selectedMunicipality,
            'selectedDate' => $date,
            'deadlines' => $deadlines,
            'assignments' => $assignments,
            'users' => $users,
        ]);
    }
    /**
     * Get calendar events for the FullCalendar
     */
// In MunicipalityDeadlineController.php - Update the calendarEvents method:
    /**
     * Get calendar events for the FullCalendar - Grouped by municipality
     */
    public function calendarEvents(Request $request)
    {
        $this->authorize('view deadlines');

        $start = $request->get('start');
        $end = $request->get('end');
        $search = $request->get('search'); // Add search parameter

        // Get deadlines within the date range
        $deadlines = MunicipalityDeadline::with('municipality.companies')
            ->when($start, function($query) use ($start) {
                $query->where('deadline_date', '>=', $start);
            })
            ->when($end, function($query) use ($end) {
                $query->where('deadline_date', '<=', $end);
            })
            ->when($search, function($query) use ($search) {
                $query->whereHas('municipality', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->get();

        // Get assignments within the date range
        $assignments = UserAssignment::with(['user', 'municipality.companies', 'company'])
            ->when($start, function($query) use ($start) {
                $query->where('deadline_date', '>=', $start);
            })
            ->when($end, function($query) use ($end) {
                $query->where('deadline_date', '<=', $end);
            })
            ->when($search, function($query) use ($search) {
                $query->whereHas('municipality', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })->orWhereHas('company', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                })->orWhereHas('user', function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->get();

        $events = [];

        // Group assignments by municipality and date
        $assignmentsByMunicipality = [];
        foreach ($assignments as $assignment) {
            $key = $assignment->municipality_id . '_' . $assignment->deadline_date->format('Y-m-d');

            if (!isset($assignmentsByMunicipality[$key])) {
                $assignmentsByMunicipality[$key] = [
                    'municipality' => $assignment->municipality,
                    'date' => $assignment->deadline_date,
                    'companies' => [],
                    'assignments' => [],
                    'user_count' => 0,
                    'company_count' => 0
                ];
            }

            $assignmentsByMunicipality[$key]['companies'][] = $assignment->company;
            $assignmentsByMunicipality[$key]['assignments'][] = $assignment;
            $assignmentsByMunicipality[$key]['company_count'] = count(array_unique(
                array_column($assignmentsByMunicipality[$key]['companies'], 'id')
            ));

            // Count unique users
            $userIds = array_column($assignmentsByMunicipality[$key]['assignments'], 'user_id');
            $assignmentsByMunicipality[$key]['user_count'] = count(array_unique($userIds));
        }

        // Create events for each municipality with assignments
        foreach ($assignmentsByMunicipality as $key => $data) {
            $municipality = $data['municipality'];
            $date = $data['date'];

            // Fix: Use floor() to get whole days
            $daysUntil = now()->startOfDay()->diffInDays($date, false);
            if ($daysUntil < 0) {
                $daysUntil = -abs($daysUntil); // Ensure negative for overdue
            }

            // Determine color based on status
            $color = $this->getEventColor($daysUntil);

            // Get all unique companies for this municipality/date
            $uniqueCompanies = array_unique($data['companies'], SORT_REGULAR);

            // Get unique users for this municipality/date
            $uniqueUserIds = [];
            $uniqueUsers = [];
            $allSameUser = true;
            $firstUserId = null;

            foreach ($data['assignments'] as $assignment) {
                if (!in_array($assignment->user_id, $uniqueUserIds)) {
                    $uniqueUserIds[] = $assignment->user_id;
                    $uniqueUsers[] = [
                        'id' => $assignment->user_id,
                        'name' => $assignment->user->name,
                        'email' => $assignment->user->email
                    ];
                }

                // Check if all assignments are to the same user
                if ($firstUserId === null) {
                    $firstUserId = $assignment->user_id;
                } elseif ($assignment->user_id !== $firstUserId) {
                    $allSameUser = false;
                }
            }

            $events[] = [
                'id' => 'municipality_' . $municipality->id . '_' . $date->format('Y-m-d'),
                'title' => ($municipality->code ?: $municipality->name) . ' - ' . $data['company_count'] . ' companies',
                'start' => $date->format('Y-m-d'),
                'allDay' => true,
                'color' => $color,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'type' => 'municipality',
                    'municipality_id' => $municipality->id,
                    'municipality_name' => $municipality->name,
                    'municipality_code' => $municipality->code,
                    'deadline_date' => $date->format('Y-m-d'),
                    'company_count' => $data['company_count'],
                    'user_count' => $data['user_count'],
                    'companies' => array_map(function($company) {
                        return [
                            'id' => $company->id,
                            'name' => $company->name,
                            'code' => $company->code
                        ];
                    }, $uniqueCompanies),
                    'assignments' => array_map(function($assignment) {
                        return [
                            'id' => $assignment->id,
                            'user_id' => $assignment->user_id,
                            'user_name' => $assignment->user->name,
                            'user_email' => $assignment->user->email,
                            'company_id' => $assignment->company_id,
                            'company_name' => $assignment->company->name,
                            'notes' => $assignment->notes
                        ];
                    }, $data['assignments']),
                    'users' => $uniqueUsers,
                    'all_same_user' => $allSameUser,
                    'primary_user' => $allSameUser && count($uniqueUsers) > 0 ? $uniqueUsers[0] : null,
                    'days_until' => $daysUntil,
                    'is_overdue' => $daysUntil < 0,
                    'is_today' => $daysUntil === 0,
                    'is_tomorrow' => $daysUntil === 1,
                    'status' => 'Assigned'
                ]
            ];
        }

        // Add pure deadlines (without assignments) as separate events
        foreach ($deadlines as $deadline) {
            // Fix: Use floor() to get whole days
            $daysUntil = now()->startOfDay()->diffInDays($deadline->deadline_date, false);
            if ($daysUntil < 0) {
                $daysUntil = -abs($daysUntil); // Ensure negative for overdue
            }

            // Check if this municipality/date already has an event from assignments
            $hasAssignmentEvent = false;
            foreach ($events as $event) {
                if ($event['extendedProps']['municipality_id'] == $deadline->municipality_id &&
                    $event['start'] == $deadline->deadline_date->format('Y-m-d')) {
                    $hasAssignmentEvent = true;
                    break;
                }
            }

            // Only add if no assignment event exists
            if (!$hasAssignmentEvent) {
                $color = $this->getEventColor($daysUntil);

                // Get companies for this municipality
                $companies = $deadline->municipality->companies->map(function($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'code' => $company->code
                    ];
                })->toArray();

                $events[] = [
                    'id' => 'deadline_' . $deadline->id,
                    'title' => 'Deadline: ' . ($deadline->municipality->code ?: $deadline->municipality->name),
                    'start' => $deadline->deadline_date->format('Y-m-d'),
                    'allDay' => true,
                    'color' => $color,
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'type' => 'deadline',
                        'municipality_id' => $deadline->municipality_id,
                        'municipality_name' => $deadline->municipality->name,
                        'municipality_code' => $deadline->municipality->code,
                        'deadline_date' => $deadline->deadline_date->format('Y-m-d'),
                        'company_count' => count($companies),
                        'user_count' => 0,
                        'companies' => $companies,
                        'assignments' => [],
                        'users' => [],
                        'all_same_user' => false,
                        'primary_user' => null,
                        'notes' => $deadline->notes,
                        'days_until' => $daysUntil,
                        'is_overdue' => $daysUntil < 0,
                        'is_today' => $daysUntil === 0,
                        'is_tomorrow' => $daysUntil === 1,
                        'status' => 'Deadline'
                    ]
                ];
            }
        }

        return response()->json($events);
    }

// Add this helper method to the controller class
    private function getEventColor($daysUntil)
    {
        if ($daysUntil < 0) return '#ef4444'; // red-500
        if ($daysUntil === 0) return '#f97316'; // orange-500
        if ($daysUntil === 1) return '#eab308'; // yellow-500
        return '#059669'; // green-600
    }
    /**
     * Store a new deadline.
     */
    public function store(Request $request)
    {
        $this->authorize('create deadline');

        $validated = $request->validate([
            'municipality_id' => 'required|exists:municipalities,id',
            'deadline_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ]);

        // Prevent duplicates
        $existingDeadline = MunicipalityDeadline::where('municipality_id', $validated['municipality_id'])
            ->where('deadline_date', $validated['deadline_date'])
            ->first();

        if ($existingDeadline) {
            return redirect()->back()->withErrors([
                'deadline_date' => 'A deadline already exists for this municipality on the selected date.'
            ]);
        }

        $deadline = MunicipalityDeadline::create($validated);
        $municipality = Municipality::find($validated['municipality_id']);

        // Notify users about the new deadline
        $users = User::where('is_active', true)->get();
        foreach ($users as $user) {
            if ($user->hasPermissionTo('view deadlines')) {
                $user->notify(new \App\Notifications\DeadlineCreated($deadline, $municipality));
            }
        }

        return redirect()->back()->with('success', 'Deadline created successfully!');
    }

    /**
     * Update an existing deadline.
     */
    public function update(Request $request, MunicipalityDeadline $deadline)
    {
        $this->authorize('edit deadline');

        $validated = $request->validate([
            'deadline_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:500',
        ]);

        // Prevent duplicates for other records
        $existingDeadline = MunicipalityDeadline::where('municipality_id', $deadline->municipality_id)
            ->where('deadline_date', $validated['deadline_date'])
            ->where('id', '!=', $deadline->id)
            ->first();

        if ($existingDeadline) {
            return redirect()->back()->withErrors([
                'deadline_date' => 'A deadline already exists for this municipality on the selected date.'
            ]);
        }

        $deadline->update($validated);

        // Notify about the update
        $municipality = $deadline->municipality;
        $users = User::where('is_active', true)->get();
        foreach ($users as $user) {
            if ($user->hasPermissionTo('view deadlines')) {
                $user->notify(new \App\Notifications\DeadlineUpdated($deadline, $municipality));
            }
        }

        return redirect()->back()->with('success', 'Deadline updated successfully!');
    }

    /**
     * Delete a deadline and related assignments.
     */
    public function destroy(MunicipalityDeadline $deadline)
    {
        $this->authorize('delete deadline');

        $municipality = $deadline->municipality;
        $deadlineDate = $deadline->deadline_date;

        UserAssignment::where('municipality_id', $deadline->municipality_id)
            ->where('deadline_date', $deadline->deadline_date)
            ->delete();

        $deadline->delete();

        // Notify about the deletion
        $users = User::where('is_active', true)->get();
        foreach ($users as $user) {
            if ($user->hasPermissionTo('view deadlines')) {
                $user->notify(new \App\Notifications\DeadlineDeleted($municipality, $deadlineDate));
            }
        }

        return redirect()->back()->with('success', 'Deadline deleted successfully!');
    }

    /**
     * Store multiple assignments at once.
     */
    public function storeAssignment(Request $request)
    {
        $this->authorize('create deadline');

        $validated = $request->validate([
            'assignments' => 'required|array',
            'assignments.*.user_id' => 'required|exists:users,id',
            'assignments.*.municipality_id' => 'required|exists:municipalities,id',
            'assignments.*.company_id' => 'required|exists:companies,id',
            'assignments.*.deadline_date' => 'required|date|after_or_equal:today',
            'assignments.*.notes' => 'nullable|string|max:500',
        ]);

        $createdAssignments = [];

        DB::transaction(function () use ($validated, &$createdAssignments) {
            foreach ($validated['assignments'] as $data) {
                $existing = UserAssignment::where('municipality_id', $data['municipality_id'])
                    ->where('company_id', $data['company_id'])
                    ->where('deadline_date', $data['deadline_date'])
                    ->first();

                if ($existing) continue;

                $assignment = UserAssignment::create($data);
                $createdAssignments[] = $assignment;

                // Notify the assigned user
                $user = User::find($data['user_id']);
                $municipality = Municipality::find($data['municipality_id']);
                $company = Company::find($data['company_id']);

                $user->notify(new \App\Notifications\DeadlineAssigned(
                    $municipality,
                    $company,
                    $data['deadline_date']
                ));
            }
        });

        if (count($createdAssignments)) {
            return redirect()->back()->with('success', 'Assignments created successfully!');
        }

        return redirect()->back()->with('error', 'No new assignments were created. Some assignments may already exist.');
    }

    /**
     * Delete a single assignment.
     */
    public function destroyAssignment(UserAssignment $assignment)
    {
        $this->authorize('delete deadline');

        $user = $assignment->user;
        $municipality = $assignment->municipality;
        $company = $assignment->company;
        $deadlineDate = $assignment->deadline_date;

        $assignment->delete();

        // Notify the user about assignment removal
        $user->notify(new \App\Notifications\AssignmentRemoved(
            $municipality,
            $company,
            $deadlineDate
        ));

        return redirect()->back()->with('success', 'Assignment deleted successfully!');
    }

    /**
     * Create deadline with assignments in a single transaction.
     */
    public function createWithAssignments(Request $request)
    {
        $this->authorize('create deadline');

        $validated = $request->validate([
            'municipality_id' => 'required|exists:municipalities,id',
            'deadline_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'assigned_user_id' => 'required|exists:users,id',
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'exists:companies,id',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                // Create or update deadline
                $deadline = MunicipalityDeadline::updateOrCreate(
                    [
                        'municipality_id' => $validated['municipality_id'],
                        'deadline_date' => $validated['deadline_date'],
                    ],
                    ['notes' => $validated['notes']]
                );

                // Create or update assignments
                foreach ($validated['company_ids'] as $companyId) {
                    UserAssignment::updateOrCreate(
                        [
                            'municipality_id' => $validated['municipality_id'],
                            'company_id' => $companyId,
                            'deadline_date' => $validated['deadline_date'],
                        ],
                        [
                            'user_id' => $validated['assigned_user_id'],
                            'notes' => $validated['notes'],
                        ]
                    );
                }

                $municipality = Municipality::find($validated['municipality_id']);
                $assignedUser = User::find($validated['assigned_user_id']);
                $companies = Company::whereIn('id', $validated['company_ids'])->get();

                // Notify the assigned user
                $assignedUser->notify(new \App\Notifications\DeadlineAssigned(
                    $deadline,
                    $municipality,
                    count($validated['company_ids'])
                ));

                // Notify admins and users with deadline permissions
                $usersToNotify = User::where('is_active', true)
                    ->where(function($query) {
                        $query->whereHas('roles', function($q) {
                            $q->where('name', 'admin');
                        })->orWhereHas('permissions', function($q) {
                            $q->where('name', 'view deadlines');
                        });
                    })
                    ->get();

                foreach ($usersToNotify as $user) {
                    $user->notify(new \App\Notifications\DeadlineCreated($deadline, $municipality));
                }
            });

            return redirect()->back()->with('success', 'Deadline and assignments created successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create deadline and assignments: ' . $e->getMessage());
        }
    }

    /**
     * Get upcoming deadlines for notifications
     */
    public function upcomingDeadlines(Request $request)
    {
        $this->authorize('view deadlines');

        $days = $request->get('days', 7); // Default to 7 days
        $upcomingDate = now()->addDays($days);

        $upcomingDeadlines = MunicipalityDeadline::with('municipality')
            ->whereBetween('deadline_date', [now(), $upcomingDate])
            ->orderBy('deadline_date')
            ->get()
            ->map(function($deadline) {
                $daysUntil = now()->startOfDay()->diffInDays($deadline->deadline_date);
                return [
                    'id' => $deadline->id,
                    'municipality_name' => $deadline->municipality->name,
                    'deadline_date' => $deadline->deadline_date->format('Y-m-d'),
                    'days_until' => $daysUntil,
                    'notes' => $deadline->notes,
                    'is_urgent' => $daysUntil <= 3,
                ];
            });

        return response()->json([
            'upcoming_deadlines' => $upcomingDeadlines,
            'total_count' => $upcomingDeadlines->count(),
            'urgent_count' => $upcomingDeadlines->where('is_urgent', true)->count(),
        ]);
    }

    /**
     * Get pending submissions for deadlines
     */
    public function pendingSubmissions(Request $request)
    {
        $this->authorize('view deadlines');

        $municipalityId = $request->get('municipality_id');
        $deadlineId = $request->get('deadline_id');

        $query = MunicipalityDeadline::with(['municipality.companies']);

        if ($municipalityId) {
            $query->where('municipality_id', $municipalityId);
        }

        if ($deadlineId) {
            $query->where('id', $deadlineId);
        }

        $deadlines = $query->where('deadline_date', '>=', now())
            ->orderBy('deadline_date')
            ->get();

        $pendingSubmissions = [];

        foreach ($deadlines as $deadline) {
            $submittedCompanyIds = Uploads::where('municipality_id', $deadline->municipality_id)
                ->where('submitted_at', '>=', $deadline->deadline_date->subDays(30))
                ->pluck('company_id')
                ->unique()
                ->toArray();

            $pendingCompanies = $deadline->municipality->companies()
                ->whereNotIn('id', $submittedCompanyIds)
                ->get();

            if ($pendingCompanies->count() > 0) {
                $pendingSubmissions[] = [
                    'deadline_id' => $deadline->id,
                    'municipality_name' => $deadline->municipality->name,
                    'deadline_date' => $deadline->deadline_date->format('Y-m-d'),
                    'pending_companies' => $pendingCompanies->pluck('name'),
                    'pending_count' => $pendingCompanies->count(),
                    'total_companies' => $deadline->municipality->companies->count(),
                    'days_until_deadline' => now()->startOfDay()->diffInDays($deadline->deadline_date),
                ];
            }
        }

        return response()->json([
            'pending_submissions' => $pendingSubmissions,
            'total_pending' => collect($pendingSubmissions)->sum('pending_count'),
        ]);
    }

    /**
     * Display companies and their submission status.
     */
    public function companies(Request $request)
    {
        $this->authorize('view deadlines');

        $perPage = (int) ($request->per_page ?: 20);
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        $companies = Company::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->withCount([
                'uploads as total_submissions',
                'uploads as submissions_this_month' => fn ($q) =>
                    $q->whereBetween('submitted_at', [$startOfMonth, $endOfMonth]),
            ])
            ->with(['uploads' => fn ($q) => $q->latest('submitted_at')->limit(1)])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        // Summary stats.
        $totalCompanies = Company::count();
        $submittedThisMonth = Company::whereHas('uploads', fn ($q) =>
            $q->whereBetween('submitted_at', [$startOfMonth, $endOfMonth])
        )->count();

        // Upcoming deadlines count.
        $upcomingDeadlines = MunicipalityDeadline::where('deadline_date', '>=', $now)
            ->where('deadline_date', '<=', $now->copy()->addDays(30))
            ->count();

        return Inertia::render('Deadlines/Companies', [
            'companies' => $companies,
            'stats' => [
                'total' => $totalCompanies,
                'submitted_this_month' => $submittedThisMonth,
                'pending_this_month' => $totalCompanies - $submittedThisMonth,
                'upcoming_deadlines' => $upcomingDeadlines,
            ],
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    /**
     * JSON endpoint returning a company's recent submissions for the detail modal.
     */
    public function companySubmissions(Company $company)
    {
        $this->authorize('view deadlines');

        $submissions = Uploads::where('company_id', $company->id)
            ->with('municipality:id,name')
            ->latest('submitted_at')
            ->limit(50)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'reference' => $u->reference,
                'municipality' => $u->municipality?->name ?? '—',
                'status' => $u->status,
                'caps_status' => $u->caps_status,
                'submitted_at' => $u->submitted_at?->format('Y-m-d H:i'),
            ]);

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'contact_email' => $company->contact_email,
                'casey_id' => $company->casey_id,
                'casey_synced_at' => $company->casey_synced_at?->format('Y-m-d H:i'),
                'status' => $company->status,
            ],
            'submissions' => $submissions,
        ]);
    }

    /**
     * Get companies for a municipality (for dropdowns).
     *
     * Every company submits to every municipality, so we return ALL active
     * companies regardless of which municipality was selected.
     */
    public function getMunicipalityCompanies(Municipality $municipality)
    {
        $this->authorize('view deadlines');

        $companies = Company::orderBy('name')
            ->get(['id', 'name']);

        return response()->json($companies);
    }
    /**
     * Sync assignments with existing municipality deadlines
     */
    public function syncAssignments(Request $request)
    {
        $this->authorize('view deadlines');

        $municipalityId = $request->get('municipality_id');
        $deadlineDate = $request->get('deadline_date');

        $assignments = UserAssignment::with(['user', 'municipality', 'company'])
            ->when($municipalityId, function ($query) use ($municipalityId) {
                $query->where('municipality_id', $municipalityId);
            })
            ->when($deadlineDate, function ($query) use ($deadlineDate) {
                $query->where('deadline_date', $deadlineDate);
            })
            ->latest()
            ->get();

        return response()->json([
            'assignments' => $assignments,
            'count' => $assignments->count(),
        ]);
    }
    /**
     * Get assignments for filtering
     */
    public function getAssignments(Request $request)
    {
        $this->authorize('view deadlines');

        $assignments = UserAssignment::query()
            ->when($request->municipality_id, function ($query, $municipalityId) {
                $query->where('municipality_id', $municipalityId);
            })
            ->when($request->company_id, function ($query, $companyId) {
                $query->where('company_id', $companyId);
            })
            ->when($request->deadline_date, function ($query, $date) {
                $query->where('deadline_date', $date);
            })
            ->with(['user', 'municipality', 'company'])
            ->get();

        return response()->json($assignments);
    }

    /**
     * Update an assignment
     */
    public function updateAssignment(Request $request, UserAssignment $assignment)
    {
        $this->authorize('edit deadline');

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:500',
        ]);

        $assignment->update($validated);

        return redirect()->back()->with('success', 'Assignment updated successfully!');
    }

    /**
     * Get municipalities for deadline creation dropdown
     */
    public function getMunicipalitiesForDeadline(Request $request)
    {
        $this->authorize('create deadline');

        $municipalities = Municipality::orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($municipalities);
    }

    /**
     * Get users for assignment creation dropdown
     */
    public function getUsersForAssignment(Request $request)
    {
        $this->authorize('create deadline');

        $users = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }
}
