<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Uploads;
use App\Models\UserAssignment;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request (for invokable controller)
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        // Check if user is admin/superadmin
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin') || $user->is_admin;
        $isSuperAdmin = $user->hasRole('super-admin');

        // Only admins can use user_id filter
        $selectedUserId = $isAdmin ? $request->get('user_id') : null;

        // Determine which user's data to show
        // Super-admin sees everything by default, unless they filter by user
        $showAllData = $isSuperAdmin && !$selectedUserId;
        $dataUserId = $isAdmin && $selectedUserId ? $selectedUserId : $user->id;
        $dataUser = $dataUserId === $user->id ? $user : User::findOrFail($dataUserId);

        // Get assignments - super-admin sees all by default
        $assignmentsQuery = UserAssignment::with(['company', 'municipality']);
        if (!$showAllData) {
            $assignmentsQuery->where('user_id', $dataUserId);
        }
        $assignments = $assignmentsQuery->get();

        // Get overdue assignments
        $overdueAssignments = $assignments->filter(function ($assignment) {
            if (!$assignment->deadline_date) return false;
            return Carbon::parse($assignment->deadline_date)->lt(Carbon::today());
        })->map(function ($assignment) {
            $daysOverdue = $assignment->deadline_date ? Carbon::parse($assignment->deadline_date)->diffInDays(Carbon::today()) : 0;
            return [
                'id' => $assignment->id,
                'company_name' => $assignment->company->name ?? 'Unknown',
                'municipality_name' => $assignment->municipality->name ?? 'Unknown',
                'deadline_date' => $assignment->deadline_date ? $assignment->deadline_date->format('Y-m-d') : 'No deadline',
                'days_overdue' => $daysOverdue,
                'notes' => $assignment->notes,
            ];
        })->values();

        // Get upcoming deadlines (next 7 days) — from user assignments
        $upcomingDeadlines = $assignments->filter(function ($assignment) {
            if (!$assignment->deadline_date) return false;
            $deadlineDate = Carbon::parse($assignment->deadline_date);
            return $deadlineDate->gte(Carbon::today()) && $deadlineDate->lte(Carbon::today()->addDays(7));
        })->map(function ($assignment) {
            $daysUntil = $assignment->deadline_date ? Carbon::parse($assignment->deadline_date)->diffInDays(Carbon::today()) : 0;
            return [
                'id' => $assignment->id,
                'company_name' => $assignment->company->name ?? 'Unknown',
                'municipality_name' => $assignment->municipality->name ?? 'Unknown',
                'deadline_date' => $assignment->deadline_date ? $assignment->deadline_date->format('Y-m-d') : 'No deadline',
                'days_until' => $daysUntil,
                'is_today' => $daysUntil === 0,
                'is_tomorrow' => $daysUntil === 1,
                'notes' => $assignment->notes,
            ];
        })->sortBy('deadline_date')->values();

        // All municipality deadlines — visible to every user regardless of
        // role or assignment. Sourced from the municipality_deadlines table.
        $allMunicipalityDeadlines = \App\Models\MunicipalityDeadline::with('municipality')
            ->where('deadline_date', '>=', Carbon::today())
            ->orderBy('deadline_date')
            ->get()
            ->map(function ($deadline) {
                $daysUntil = Carbon::today()->diffInDays(Carbon::parse($deadline->deadline_date), false);
                return [
                    'id' => $deadline->id,
                    'municipality_id' => $deadline->municipality_id,
                    'municipality_name' => $deadline->municipality->name ?? 'Unknown',
                    'deadline_date' => $deadline->deadline_date->format('Y-m-d'),
                    'days_until' => $daysUntil,
                    'is_today' => $daysUntil === 0,
                    'is_tomorrow' => $daysUntil === 1,
                    'is_this_week' => $daysUntil >= 0 && $daysUntil <= 7,
                    'notes' => $deadline->notes,
                    'assigned_users' => UserAssignment::where('municipality_id', $deadline->municipality_id)
                        ->where('deadline_date', $deadline->deadline_date)
                        ->with('user:id,name')
                        ->get()
                        ->pluck('user.name')
                        ->filter()
                        ->unique()
                        ->values(),
                    'assigned_companies_count' => UserAssignment::where('municipality_id', $deadline->municipality_id)
                        ->where('deadline_date', $deadline->deadline_date)
                        ->distinct('company_id')
                        ->count('company_id'),
                ];
            });

        // Get recent activity
        $recentActivityQuery = UserAssignment::with(['company', 'municipality'])
            ->latest('created_at')
            ->limit(10);
        if (!$showAllData) {
            $recentActivityQuery->where('user_id', $dataUserId);
        }
        $recentActivity = $recentActivityQuery->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'company_name' => $assignment->company->name ?? 'Unknown',
                    'municipality_name' => $assignment->municipality->name ?? 'Unknown',
                    'deadline_date' => $assignment->deadline_date ? $assignment->deadline_date->format('Y-m-d') : 'No deadline',
                    'created_at' => $assignment->created_at->diffForHumans(),
                ];
            });

        // Get assignments by municipality
        $assignmentsByMunicipalityQuery = UserAssignment::select('municipality_id', \DB::raw('count(*) as count'))
            ->with('municipality:id,name')
            ->groupBy('municipality_id')
            ->orderByDesc('count');
        if (!$showAllData) {
            $assignmentsByMunicipalityQuery->where('user_id', $dataUserId);
        }
        $assignmentsByMunicipality = $assignmentsByMunicipalityQuery->get()
            ->map(function ($item) {
                return [
                    'municipality' => $item->municipality->name ?? 'Unknown',
                    'count' => $item->count,
                ];
            });

        // Progress stats
        $completedQuery = UserAssignment::where('deadline_date', '<', Carbon::today());
        $pendingQuery = UserAssignment::where('deadline_date', '>=', Carbon::today());
        $noDeadlineQuery = UserAssignment::whereNull('deadline_date');

        if (!$showAllData) {
            $completedQuery->where('user_id', $dataUserId);
            $pendingQuery->where('user_id', $dataUserId);
            $noDeadlineQuery->where('user_id', $dataUserId);
        }

        $completedCount = $completedQuery->count();
        $pendingCount = $pendingQuery->count();
        $noDeadlineCount = $noDeadlineQuery->count();

        $progress = [
            'completed' => $completedCount,
            'pending' => $pendingCount,
            'no_deadline' => $noDeadlineCount,
        ];

        // Overall stats
        $dueTodayCount = $assignments->filter(function ($assignment) {
            return $assignment->deadline_date && Carbon::parse($assignment->deadline_date)->isToday();
        })->count();

        $dueThisWeekCount = $assignments->filter(function ($assignment) {
            if (!$assignment->deadline_date) return false;
            $deadlineDate = Carbon::parse($assignment->deadline_date);
            return $deadlineDate->gte(Carbon::today()) && $deadlineDate->lte(Carbon::today()->addDays(7));
        })->count();

        $totalWithDeadline = $assignments->filter(function ($assignment) {
            return $assignment->deadline_date !== null;
        })->count();

        $completionRate = $totalWithDeadline > 0 ?
            round(($completedCount / $totalWithDeadline) * 100, 1) : 0;

        $stats = [
            'total_assignments' => $assignments->count(),
            'overdue' => $overdueAssignments->count(),
            'due_today' => $dueTodayCount,
            'due_this_week' => $dueThisWeekCount,
            'completion_rate' => $completionRate,
            'with_deadline' => $totalWithDeadline,
            'without_deadline' => $noDeadlineCount,
        ];

        // Get users list for admin filter
        $users = $isAdmin ? User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']) : collect();

        // Get recent uploads for the dashboard
        $recentUploadsQuery = Uploads::with(['company', 'municipality'])
            ->orderBy('created_at', 'desc')
            ->limit(5);

        if (!$showAllData) {
            $recentUploadsQuery->where('user_id', $dataUserId);
        }

        $recentUploads = $recentUploadsQuery->get()
            ->map(function ($upload) {
                return [
                    'id' => $upload->id,
                    'reference' => $upload->reference,
                    'company_name' => $upload->company->name ?? 'N/A',
                    'municipality_name' => $upload->municipality->name ?? 'N/A',
                    'status' => $upload->status,
                    'created_at' => $upload->created_at->diffForHumans(),
                    'submitted_at' => $upload->submitted_at ? $upload->submitted_at->format('Y-m-d') : null,
                ];
            });

        // CAPS sync status — shows admins when data was last pulled
        $capsSync = null;
        if ($isAdmin) {
            $municipalityCount = Municipality::count();
            $companyCount = Company::count();
            $lastSync = Municipality::whereNotNull('casey_synced_at')->max('casey_synced_at')
                ?? Company::whereNotNull('casey_synced_at')->max('casey_synced_at');

            $capsSync = [
                'municipalities' => $municipalityCount,
                'companies' => $companyCount,
                'lastSync' => $lastSync,
                'hasData' => $municipalityCount > 0 || $companyCount > 0,
            ];
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'upcomingDeadlines' => $upcomingDeadlines,
            'allMunicipalityDeadlines' => $allMunicipalityDeadlines,
            'overdueAssignments' => $overdueAssignments,
            'assignmentsByMunicipality' => $assignmentsByMunicipality,
            'recentActivity' => $recentActivity,
            'recentUploads' => $recentUploads,
            'progress' => $progress,
            'isAdmin' => $isAdmin,
            'isSuperAdmin' => $isSuperAdmin,
            'showAllData' => $showAllData,
            'users' => $users,
            'selectedUserId' => $selectedUserId,
            'currentUserName' => $showAllData ? 'All Users' : $dataUser->name,
            'capsSync' => $capsSync,
            'filters' => $request->only(['user_id']),
            'flash' => [
                'assignment_created' => session('assignment_created'),
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Get recent uploads for API with pagination
     */
    public function getRecentUploads(Request $request)
    {
        try {
            $user = $request->user();

            // Check if user is admin/superadmin
            $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin') || $user->is_admin;
            $isSuperAdmin = $user->hasRole('super-admin');

            // Only admins can use user_id filter
            $selectedUserId = $isAdmin ? $request->get('user_id') : null;

            // Super-admin sees everything by default, unless filtering by user
            $showAllData = $isSuperAdmin && !$selectedUserId;
            $dataUserId = $isAdmin && $selectedUserId ? $selectedUserId : $user->id;

            // Get pagination parameters
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 10);
            $search = $request->get('search', '');

            // Start building the query
            $query = Uploads::with(['company', 'municipality'])
                ->orderBy('submitted_at', 'desc')
                ->orderBy('created_at', 'desc');

            // Super-admin sees all, others see only their own
            if (!$showAllData) {
                $query->where('user_id', $dataUserId);
            }

            // Apply search filter if provided
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('company', function ($companyQuery) use ($search) {
                            $companyQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('municipality', function ($municipalityQuery) use ($search) {
                            $municipalityQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            // Get paginated results
            $paginator = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform the data
            $uploads = $paginator->getCollection()->map(function ($upload) {
                return [
                    'id' => $upload->id,
                    'reference' => $upload->reference,
                    'filename' => $upload->filename,
                    'file_size' => $upload->file_size,
                    'company_name' => $upload->company ? $upload->company->name : 'N/A',
                    'municipality' => $upload->municipality ? $upload->municipality->name : 'N/A',
                    'status' => $upload->status,
                    'created_at' => $upload->created_at,
                    'updated_at' => $upload->updated_at,
                    'formatted_created_at' => $upload->created_at ? $upload->created_at->format('Y-m-d H:i:s') : null,
                    'submitted_at' => $upload->submitted_at ? $upload->submitted_at->format('Y-m-d H:i:s') : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $uploads,
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'links' => $paginator->linkCollection()->toArray(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch recent uploads: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch recent uploads',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dashboard stats
     */
    public function getStats(Request $request)
    {
        $user = $request->user();

        // Check if user is admin/superadmin
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin') || $user->is_admin;
        $isSuperAdmin = $user->hasRole('super-admin');

        // Only admins can use user_id filter
        $selectedUserId = $isAdmin ? $request->get('user_id') : null;

        // Super-admin sees everything by default
        $showAllData = $isSuperAdmin && !$selectedUserId;
        $dataUserId = $isAdmin && $selectedUserId ? $selectedUserId : $user->id;

        // Build queries based on data scope
        if ($showAllData) {
            $stats = [
                'totalSubmissions' => Uploads::count(),
                'activeCompanies' => Uploads::distinct('company_id')->count(),
                'municipalities' => Uploads::distinct('municipality_id')->count(),
                'totalAssignments' => UserAssignment::count(),
                'overdueAssignments' => UserAssignment::where('deadline_date', '<', Carbon::today())->count(),
                'assignmentsWithoutDeadline' => UserAssignment::whereNull('deadline_date')->count(),
                'assignmentsWithDeadline' => UserAssignment::whereNotNull('deadline_date')->count(),
            ];
        } else {
            $stats = [
                'totalSubmissions' => Uploads::where('user_id', $dataUserId)->count(),
                'activeCompanies' => Uploads::where('user_id', $dataUserId)->distinct('company_id')->count(),
                'municipalities' => Uploads::where('user_id', $dataUserId)->distinct('municipality_id')->count(),
                'totalAssignments' => UserAssignment::where('user_id', $dataUserId)->count(),
                'overdueAssignments' => UserAssignment::where('user_id', $dataUserId)
                    ->where('deadline_date', '<', Carbon::today())
                    ->count(),
                'assignmentsWithoutDeadline' => UserAssignment::where('user_id', $dataUserId)
                    ->whereNull('deadline_date')
                    ->count(),
                'assignmentsWithDeadline' => UserAssignment::where('user_id', $dataUserId)
                    ->whereNotNull('deadline_date')
                    ->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Search uploads
     */
    public function searchUploads(Request $request)
    {
        $user = $request->user();

        // Check if user is admin/superadmin
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin') || $user->is_admin;
        $isSuperAdmin = $user->hasRole('super-admin');

        // Only admins can use user_id filter
        $selectedUserId = $isAdmin ? $request->get('user_id') : null;

        // Super-admin sees everything by default
        $showAllData = $isSuperAdmin && !$selectedUserId;
        $dataUserId = $isAdmin && $selectedUserId ? $selectedUserId : $user->id;

        $search = $request->get('search', '');

        $query = Uploads::with(['company', 'municipality']);

        // Super-admin sees all, others see only their own
        if (!$showAllData) {
            $query->where('user_id', $dataUserId);
        }

        $uploads = $query->where(function ($q) use ($search) {
            $q->where('reference', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhereHas('company', function ($companyQuery) use ($search) {
                    $companyQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('municipality', function ($municipalityQuery) use ($search) {
                    $municipalityQuery->where('name', 'like', "%{$search}%");
                });
        })
            ->orderBy('submitted_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($upload) {
                return [
                    'id' => $upload->id,
                    'reference' => $upload->reference,
                    'company_name' => $upload->company ? $upload->company->name : 'N/A',
                    'municipality' => $upload->municipality ? $upload->municipality->name : 'N/A',
                    'status' => $upload->status,
                    'submitted_at' => $upload->submitted_at ? $upload->submitted_at->format('Y-m-d') : null,
                    'created_at' => $upload->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'success' => true,
            'uploads' => $uploads,
        ]);
    }

    /**
     * Get assignment statistics
     */
    public function getAssignmentStats(Request $request)
    {
        $user = $request->user();

        // Check if user is admin/superadmin
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super-admin') || $user->is_admin;
        $isSuperAdmin = $user->hasRole('super-admin');

        // Only admins can use user_id filter
        $selectedUserId = $isAdmin ? $request->get('user_id') : null;

        // Super-admin sees everything by default
        $showAllData = $isSuperAdmin && !$selectedUserId;
        $dataUserId = $isAdmin && $selectedUserId ? $selectedUserId : $user->id;

        // Build base query
        $baseQuery = UserAssignment::query();
        if (!$showAllData) {
            $baseQuery->where('user_id', $dataUserId);
        }

        // Get counts
        $totalAssignments = $baseQuery->count();
        $assignmentsWithDeadline = $baseQuery->clone()->whereNotNull('deadline_date')->count();
        $assignmentsWithoutDeadline = $baseQuery->clone()->whereNull('deadline_date')->count();
        $overdueAssignments = $baseQuery->clone()
            ->where('deadline_date', '<', Carbon::today())
            ->count();

        // Get assignments by status
        $assignmentsByStatus = [
            'overdue' => $overdueAssignments,
            'due_today' => $baseQuery->clone()
                ->where('deadline_date', Carbon::today())
                ->count(),
            'due_this_week' => $baseQuery->clone()
                ->where('deadline_date', '>=', Carbon::today())
                ->where('deadline_date', '<=', Carbon::today()->addDays(7))
                ->count(),
            'future' => $baseQuery->clone()
                ->where('deadline_date', '>', Carbon::today()->addDays(7))
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => [
                'total' => $totalAssignments,
                'with_deadline' => $assignmentsWithDeadline,
                'without_deadline' => $assignmentsWithoutDeadline,
                'overdue' => $overdueAssignments,
                'by_status' => $assignmentsByStatus,
            ],
        ]);
    }
}
