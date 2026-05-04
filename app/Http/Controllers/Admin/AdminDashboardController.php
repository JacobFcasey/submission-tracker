<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Municipality;
use App\Models\UserAssignment as Assignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Get the logged-in user
        $currentUser = $request->user();

        // ---- Filters ----
        $now    = Carbon::now();
        $month  = (int) ($request->input('month') ?: $now->month);
        $year   = (int) ($request->input('year') ?: $now->year);
        $userId = $request->input('user_id'); // nullable

        $filters = [
            'month'   => $month,
            'year'    => $year,
            'user_id' => $userId ?: null,
        ];

        // ---- Get user's assigned municipalities ----
        $userMunicipalities = $currentUser->municipalities ?? collect();
        $hasMunicipalityRestriction = $userMunicipalities->isNotEmpty();

        // ---- Get user's assigned companies ----
        $userCompanies = $currentUser->companies ?? collect();
        $hasCompanyRestriction = $userCompanies->isNotEmpty();

        // ---- Filter options for dropdowns (SCOPED TO USER) ----

        // Years - from assignments the user can see
        $yearsQuery = Assignment::query()->where('user_id', $currentUser->id);
        if ($hasMunicipalityRestriction) {
            $yearsQuery->whereIn('municipality_id', $userMunicipalities->pluck('id'));
        }
        if ($hasCompanyRestriction) {
            $yearsQuery->whereIn('company_id', $userCompanies->pluck('id'));
        }

        $years = $yearsQuery
            ->selectRaw('YEAR(deadline_date) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        if ($years->isEmpty()) {
            $years = collect([$now->year]);
        }

        // Months - always all 12
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];

        // Users - only show current user for non-admins
        $usersQuery = User::select('id', 'name', 'email')->orderBy('name');

        // Check if user is admin (adjust this check based on your permission system)
        $isAdmin = $currentUser->hasRole('admin') ||
            $currentUser->can('view all users') ||
            $currentUser->is_admin === true;

        if (!$isAdmin) {
            // Non-admin users only see themselves in the dropdown
            $usersQuery->where('id', $currentUser->id);
        }

        $users = $usersQuery->get();

        // ---- Municipalities dropdown - SCOPED TO USER ----
        $municipalitiesQuery = Municipality::select('id', 'name', 'code')
            ->whereHas('assignments', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            })
            ->orderBy('name');

        if ($hasMunicipalityRestriction) {
            $municipalitiesQuery->whereIn('id', $userMunicipalities->pluck('id'));
        }
        $municipalities = $municipalitiesQuery->get();

        // ---- Companies dropdown - SCOPED TO USER ----
        $companiesQuery = Company::select('id', 'name')
            ->whereHas('assignments', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            })
            ->orderBy('name');

        if ($hasCompanyRestriction) {
            $companiesQuery->whereIn('id', $userCompanies->pluck('id'));
        }
        $companies = $companiesQuery->get();

        // ---- Global counts (scoped to user's access) ----
        $statsUsersQuery = User::query();
        $statsCompaniesQuery = Company::query();
        $statsMunicipalitiesQuery = Municipality::query();
        $statsAssignmentsQuery = Assignment::query()->where('user_id', $currentUser->id);

        // Apply user restrictions to global counts
        if ($hasMunicipalityRestriction) {
            $statsAssignmentsQuery->whereIn('municipality_id', $userMunicipalities->pluck('id'));
            $statsMunicipalitiesQuery->whereIn('id', $userMunicipalities->pluck('id'));
        }
        if ($hasCompanyRestriction) {
            $statsAssignmentsQuery->whereIn('company_id', $userCompanies->pluck('id'));
            $statsCompaniesQuery->whereIn('id', $userCompanies->pluck('id'));
        }

        // Non-admins only see themselves
        if (!$isAdmin) {
            $statsUsersQuery->where('id', $currentUser->id);
            $statsCompaniesQuery->whereHas('assignments', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
            $statsMunicipalitiesQuery->whereHas('assignments', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            });
        }

        $stats = [
            'total_users'         => $statsUsersQuery->count(),
            'total_companies'     => $statsCompaniesQuery->count(),
            'total_municipalities' => $statsMunicipalitiesQuery->count(),
            'total_assignments'   => $statsAssignmentsQuery->count(),
        ];

        // ---- Filtered base query (applies to assignment widgets) ----
        $base = Assignment::query()
            ->where('user_id', $currentUser->id) // Always filter by current user
            ->when($userId && $isAdmin, fn ($q) => $q->where('user_id', $userId))
            ->whereYear('deadline_date', $year)
            ->whereMonth('deadline_date', $month);

        // Apply user restrictions to filtered query
        if ($hasMunicipalityRestriction) {
            $base->whereIn('municipality_id', $userMunicipalities->pluck('id'));
        }
        if ($hasCompanyRestriction) {
            $base->whereIn('company_id', $userCompanies->pluck('id'));
        }

        $stats['filtered_assignments'] = (clone $base)->count();

        // Today reference for status blocks
        $today = Carbon::today();

        // ---- Assignment status breakdown (filtered) ----
        $assignmentStatus = [
            'overdue'       => (clone $base)->where('deadline_date', '<', $today)->count(),
            'due_today'     => (clone $base)->whereDate('deadline_date', $today)->count(),
            'due_this_week' => (clone $base)->whereBetween('deadline_date', [
                $today,
                $today->copy()->addDays(7)
            ])->count(),
            'upcoming'      => (clone $base)->where('deadline_date', '>', $today->copy()->addDays(7))->count(),
        ];

        // ---- Completion rate & quality metrics ----
        $totalFiltered = $stats['filtered_assignments'];
        $overdueCount  = $assignmentStatus['overdue'];
        $completedCount = (clone $base)->where('deadline_date', '<', $today)->count();

        $stats['completion_rate'] = $totalFiltered > 0
            ? round(($completedCount / $totalFiltered) * 100, 1)
            : 0;

        $stats['avg_assignments_per_user'] = $stats['total_users'] > 0
            ? round($stats['total_assignments'] / $stats['total_users'], 1)
            : 0;

        // ---- Assignments by municipality (top 10, filtered) ----
        $assignmentsByMunicipality = (clone $base)
            ->select('municipality_id', DB::raw('count(*) as count'))
            ->with('municipality:id,name,code')
            ->groupBy('municipality_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'municipality' => $item->municipality->name ?? 'Unknown',
                'code'        => $item->municipality->code ?? 'N/A',
                'count'       => (int) $item->count,
            ]);

        // ---- Assignments by company (top 10, filtered) ----
        $assignmentsByCompany = (clone $base)
            ->select('company_id', DB::raw('count(*) as count'))
            ->with('company:id,name')
            ->groupBy('company_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'company' => $item->company->name ?? 'Unknown',
                'count'   => (int) $item->count,
            ]);

        // ---- Recent assignments (filtered) ----
        $recentAssignments = (clone $base)
            ->with(['user:id,name,email', 'municipality:id,name', 'company:id,name'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($a) {
                return [
                    'id'                 => $a->id,
                    'user_name'          => $a->user->name ?? 'N/A',
                    'municipality_name'  => $a->municipality->name ?? 'N/A',
                    'company_name'       => $a->company->name ?? 'N/A',
                    'deadline_date'      => $a->deadline_date ? Carbon::parse($a->deadline_date)->format('Y-m-d') : 'N/A',
                    'created_at'         => $a->created_at ? $a->created_at->diffForHumans() : 'N/A',
                    'is_overdue'         => $a->deadline_date ? Carbon::parse($a->deadline_date)->isPast() : false,
                ];
            });

        // ---- Recent users (last 10 registered) - Only for admins ----
        $recentUsers = collect();
        if ($isAdmin) {
            $recentUsers = User::select('id', 'name', 'email', 'created_at')
                ->withCount(['assignments' => function ($q) use ($year, $month, $hasMunicipalityRestriction, $hasCompanyRestriction, $userMunicipalities, $userCompanies, $currentUser) {
                    $q->whereYear('deadline_date', $year)
                        ->whereMonth('deadline_date', $month)
                        ->where('user_id', $currentUser->id); // Filter by current user

                    if ($hasMunicipalityRestriction) {
                        $q->whereIn('municipality_id', $userMunicipalities->pluck('id'));
                    }
                    if ($hasCompanyRestriction) {
                        $q->whereIn('company_id', $userCompanies->pluck('id'));
                    }
                }])
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn ($u) => [
                    'id'                 => $u->id,
                    'name'               => $u->name,
                    'email'              => $u->email,
                    'assignments_count'  => $u->assignments_count,
                    'created_at'         => $u->created_at->diffForHumans(),
                ]);
        }

        // ---- Top users by assignment count (filtered by month/year) ----
        $topUsersQuery = User::select('id', 'name', 'email')
            ->withCount(['assignments' => function ($q) use ($year, $month, $hasMunicipalityRestriction, $hasCompanyRestriction, $userMunicipalities, $userCompanies, $currentUser) {
                $q->whereYear('deadline_date', $year)
                    ->whereMonth('deadline_date', $month)
                    ->where('user_id', $currentUser->id); // Filter by current user

                if ($hasMunicipalityRestriction) {
                    $q->whereIn('municipality_id', $userMunicipalities->pluck('id'));
                }
                if ($hasCompanyRestriction) {
                    $q->whereIn('company_id', $userCompanies->pluck('id'));
                }
            }])
            ->having('assignments_count', '>', 0)
            ->orderByDesc('assignments_count')
            ->limit(10);

        if (!$isAdmin) {
            $topUsersQuery->where('id', $currentUser->id);
        }

        $topUsers = $topUsersQuery->get()
            ->map(fn ($u) => [
                'name'  => $u->name,
                'email' => $u->email,
                'count' => $u->assignments_count,
            ]);

        // ---- Daily trends within selected month ----
        $dailyTrendsQuery = Assignment::query()
            ->where('user_id', $currentUser->id) // Filter by current user
            ->select(
                DB::raw('DATE(deadline_date) as date'),
                DB::raw('count(*) as count')
            )
            ->whereYear('deadline_date', $year)
            ->whereMonth('deadline_date', $month)
            ->when($userId && $isAdmin, fn ($q) => $q->where('user_id', $userId));

        if ($hasMunicipalityRestriction) {
            $dailyTrendsQuery->whereIn('municipality_id', $userMunicipalities->pluck('id'));
        }
        if ($hasCompanyRestriction) {
            $dailyTrendsQuery->whereIn('company_id', $userCompanies->pluck('id'));
        }

        $dailyTrends = $dailyTrendsQuery
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($i) => [
                'date'  => Carbon::parse($i->date)->format('M d'),
                'count' => (int) $i->count,
            ]);

        // ---- Monthly comparison (last 6 months) ----
        $monthlyComparisonQuery = Assignment::query()
            ->where('user_id', $currentUser->id) // Filter by current user
            ->select(
                DB::raw('YEAR(deadline_date) as year'),
                DB::raw('MONTH(deadline_date) as month'),
                DB::raw('count(*) as count')
            )
            ->where('deadline_date', '>=', $now->copy()->subMonths(6)->startOfMonth())
            ->when($userId && $isAdmin, fn ($q) => $q->where('user_id', $userId));

        if ($hasMunicipalityRestriction) {
            $monthlyComparisonQuery->whereIn('municipality_id', $userMunicipalities->pluck('id'));
        }
        if ($hasCompanyRestriction) {
            $monthlyComparisonQuery->whereIn('company_id', $userCompanies->pluck('id'));
        }

        $monthlyComparison = $monthlyComparisonQuery
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($item) => [
                'label' => Carbon::create($item->year, $item->month, 1)->format('M Y'),
                'count' => (int) $item->count,
            ]);

        // ---- Assignment trends (for compatibility) ----
        $assignmentTrends = $dailyTrends;

        // ---- Deadline urgency breakdown for chart ----
        $urgencyBreakdown = [
            ['label' => 'Overdue',   'count' => $assignmentStatus['overdue'],      'color' => '#ef4444'],
            ['label' => 'Today',     'count' => $assignmentStatus['due_today'],    'color' => '#f97316'],
            ['label' => 'This Week', 'count' => $assignmentStatus['due_this_week'], 'color' => '#eab308'],
            ['label' => 'Upcoming',  'count' => $assignmentStatus['upcoming'],     'color' => '#6b7280'],
        ];

        // ---- Municipality performance (completion rates) ----
        $municipalityPerformanceQuery = Municipality::select('municipalities.id', 'municipalities.name')
            ->whereHas('assignments', function ($q) use ($currentUser) {
                $q->where('user_id', $currentUser->id);
            })
            ->withCount([
                'assignments as total_assignments' => function ($query) use ($year, $month, $hasCompanyRestriction, $userCompanies, $currentUser) {
                    $query->whereYear('deadline_date', $year)
                        ->whereMonth('deadline_date', $month)
                        ->where('user_id', $currentUser->id);

                    if ($hasCompanyRestriction) {
                        $query->whereIn('company_id', $userCompanies->pluck('id'));
                    }
                },
                'assignments as completed_assignments' => function ($query) use ($year, $month, $hasCompanyRestriction, $userCompanies, $currentUser) {
                    $query->whereYear('deadline_date', $year)
                        ->whereMonth('deadline_date', $month)
                        ->where('deadline_date', '<', Carbon::today())
                        ->where('user_id', $currentUser->id);

                    if ($hasCompanyRestriction) {
                        $query->whereIn('company_id', $userCompanies->pluck('id'));
                    }
                }
            ])
            ->having('total_assignments', '>', 0)
            ->orderByDesc('total_assignments')
            ->limit(10);

        if ($hasMunicipalityRestriction) {
            $municipalityPerformanceQuery->whereIn('municipalities.id', $userMunicipalities->pluck('id'));
        }

        $municipalityPerformance = $municipalityPerformanceQuery->get()
            ->map(function ($municipality) {
                $completionRate = $municipality->total_assignments > 0
                    ? round(($municipality->completed_assignments / $municipality->total_assignments) * 100, 1)
                    : 0;

                return [
                    'municipality' => $municipality->name,
                    'total' => $municipality->total_assignments,
                    'completed' => $municipality->completed_assignments,
                    'completion_rate' => $completionRate,
                ];
            });

        // ---- Active vs Inactive Companies (within filter period) ----
        $activeCompaniesQuery = Company::whereHas('assignments', function ($query) use ($year, $month, $hasMunicipalityRestriction, $userMunicipalities, $currentUser) {
            $query->whereYear('deadline_date', $year)
                ->whereMonth('deadline_date', $month)
                ->where('user_id', $currentUser->id);

            if ($hasMunicipalityRestriction) {
                $query->whereIn('municipality_id', $userMunicipalities->pluck('id'));
            }
        });

        if ($hasCompanyRestriction) {
            $activeCompaniesQuery->whereIn('id', $userCompanies->pluck('id'));
        }

        $activeCompaniesCount = $activeCompaniesQuery->count();

        $stats['active_companies'] = $activeCompaniesCount;
        $stats['inactive_companies'] = $stats['total_companies'] - $activeCompaniesCount;

        // ---- Quality metrics (on-time vs overdue ratio) ----
        $onTime = max($totalFiltered - $overdueCount, 0);
        $simpleQuality = [
            'total_filtered' => $totalFiltered,
            'on_time'        => $onTime,
            'overdue'        => $overdueCount,
            'on_time_percentage' => $totalFiltered > 0
                ? round(($onTime / $totalFiltered) * 100, 1)
                : 0,
        ];

        // ---- Upcoming deadlines for current user ----
        $upcomingDeadlines = Assignment::where('user_id', $currentUser->id)
            ->with(['company:id,name', 'municipality:id,name'])
            ->where('deadline_date', '>=', Carbon::today())
            ->where('deadline_date', '<=', Carbon::today()->addDays(7))
            ->orderBy('deadline_date')
            ->get()
            ->map(function ($assignment) {
                $daysUntil = Carbon::parse($assignment->deadline_date)->diffInDays(Carbon::today());
                return [
                    'id' => $assignment->id,
                    'company_name' => $assignment->company->name ?? 'Unknown',
                    'municipality_name' => $assignment->municipality->name ?? 'Unknown',
                    'deadline_date' => $assignment->deadline_date->format('Y-m-d'),
                    'days_until' => $daysUntil,
                    'is_today' => $daysUntil === 0,
                    'is_tomorrow' => $daysUntil === 1,
                    'notes' => $assignment->notes,
                ];
            });

        // ---- Overdue assignments for current user ----
        $overdueAssignments = Assignment::where('user_id', $currentUser->id)
            ->with(['company:id,name', 'municipality:id,name'])
            ->where('deadline_date', '<', Carbon::today())
            ->orderBy('deadline_date')
            ->get()
            ->map(function ($assignment) {
                $daysOverdue = Carbon::parse($assignment->deadline_date)->diffInDays(Carbon::today());
                return [
                    'id' => $assignment->id,
                    'company_name' => $assignment->company->name ?? 'Unknown',
                    'municipality_name' => $assignment->municipality->name ?? 'Unknown',
                    'deadline_date' => $assignment->deadline_date->format('Y-m-d'),
                    'days_overdue' => $daysOverdue,
                    'notes' => $assignment->notes,
                ];
            });

        // ---- Recent activity for current user ----
        $recentActivity = Assignment::where('user_id', $currentUser->id)
            ->with(['company:id,name', 'municipality:id,name'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'company_name' => $assignment->company->name ?? 'Unknown',
                    'municipality_name' => $assignment->municipality->name ?? 'Unknown',
                    'deadline_date' => $assignment->deadline_date->format('Y-m-d'),
                    'created_at' => $assignment->created_at->diffForHumans(),
                ];
            });

        // ---- Progress stats for current user ----
        $progress = [
            'completed' => Assignment::where('user_id', $currentUser->id)
                ->where('deadline_date', '<', Carbon::today())
                ->count(),
            'pending' => Assignment::where('user_id', $currentUser->id)
                ->where('deadline_date', '>=', Carbon::today())
                ->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            // Filters & options
            'filters'  => $filters,
            'filterOptions' => [
                'months'        => $months,
                'years'         => $years,
                'users'         => $users,
                'municipalities' => $municipalities, // SCOPED
                'companies'     => $companies,      // SCOPED
            ],

            // User permissions info
            'userPermissions' => [
                'is_admin' => $isAdmin,
                'has_municipality_restriction' => $hasMunicipalityRestriction,
                'has_company_restriction' => $hasCompanyRestriction,
            ],

            // Stats & data
            'stats'                      => $stats,
            'assignmentStatus'           => $assignmentStatus,
            'assignmentsByMunicipality'  => $assignmentsByMunicipality,
            'assignmentsByCompany'       => $assignmentsByCompany,
            'recentAssignments'          => $recentAssignments,
            'recentUsers'                => $recentUsers,
            'topUsers'                   => $topUsers,
            'dailyTrends'                => $dailyTrends,
            'monthlyComparison'          => $monthlyComparison,
            'assignmentTrends'           => $assignmentTrends,
            'urgencyBreakdown'           => $urgencyBreakdown,
            'municipalityPerformance'    => $municipalityPerformance,
            'simpleQuality'              => $simpleQuality,

            // New data for dashboard
            'upcomingDeadlines'          => $upcomingDeadlines,
            'overdueAssignments'         => $overdueAssignments,
            'recentActivity'             => $recentActivity,
            'progress'                   => $progress,
        ]);
    }
}
