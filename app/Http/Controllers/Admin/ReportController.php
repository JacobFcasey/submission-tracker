<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UploadsExports;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Municipality;
use App\Models\MunicipalityDeadline;
use App\Models\Uploads;
use App\Models\User;
use App\Models\UserAssignment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view reports');

        $filters = $this->resolveFilters($request);
        $baseQuery = $this->buildUploadsQuery($filters);

        $uploads = (clone $baseQuery)
            ->orderByDesc('submitted_at')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Uploads $upload) => $this->transformUpload($upload));

        $summary = $this->buildUploadSummary($filters);
        $deadlineSummary = $this->buildDeadlineSummary($filters);

        return Inertia::render('Admin/Reports/Index', [
            'uploads' => $uploads,
            'stats' => $summary['stats'],
            'filters' => $filters,
            'municipalities' => Municipality::orderBy('name')->get(['id', 'name', 'code']),
            'companies' => Company::orderBy('name')->get(['id', 'name', 'municipality_id']),
            'statusOptions' => ['Pending', 'Processing', 'Completed', 'Rejected'],
            'perPageOptions' => [20, 50, 100],
            'statusBreakdown' => $summary['status_breakdown'],
            'municipalityPerformance' => $summary['municipality_performance'],
            'dailyVolume' => $summary['daily_volume'],
            'deadlineSummary' => $deadlineSummary,
            'downloadUrls' => [
                'uploads_csv' => route('admin.reports.export', array_merge($filters, ['format' => 'csv'])),
                'uploads_xlsx' => route('admin.reports.export', array_merge($filters, ['format' => 'xlsx'])),
                'upload_summary_csv' => route('admin.reports.upload-summary', array_merge($filters, ['download' => 1])),
                'deadline_summary_csv' => route('admin.reports.deadline-summary', array_merge($filters, ['download' => 1])),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('view reports');

        $filters = $this->resolveFilters($request);
        $uploads = $this->buildUploadsQuery($filters)
            ->orderByDesc('submitted_at')
            ->get();

        $format = strtolower((string) $request->get('format', 'csv'));
        $timestamp = now()->format('Y-m-d_H-i-s');

        if ($format === 'xlsx') {
            return Excel::download(new UploadsExports($uploads), "uploads_report_{$timestamp}.xlsx");
        }

        return response()->streamDownload(function () use ($uploads): void {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Reference',
                'Company',
                'Municipality',
                'Status',
                'Uploaded By',
                'User Email',
                'Submitted At',
                'System Import Date',
                'Original Files Count',
                'Original Files',
                'Workings File',
                'Systems Import File',
                'Re-upload Reason',
            ]);

            foreach ($uploads as $upload) {
                fputcsv($file, [
                    $upload->reference,
                    $upload->company?->name ?? 'N/A',
                    $upload->municipality?->name ?? 'N/A',
                    $upload->status,
                    $upload->user?->name ?? 'Unknown',
                    $upload->user?->email ?? 'Unknown',
                    optional($upload->submitted_at)->format('Y-m-d H:i:s') ?? 'N/A',
                    optional($upload->system_import_date)->format('Y-m-d H:i:s') ?? 'N/A',
                    count($upload->original_file_names ?? []),
                    implode(', ', $upload->original_file_names ?? []),
                    $upload->workings_file_name ?? '-',
                    $upload->systems_import_file_name ?? '-',
                    $upload->reupload_reason_type ?? '-',
                ]);
            }

            fclose($file);
        }, "uploads_report_{$timestamp}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function uploadSummary(Request $request)
    {
        $this->authorize('view reports');

        $filters = $this->resolveFilters($request);
        $summary = $this->buildUploadSummary($filters);

        if ($request->boolean('download')) {
            return response()->streamDownload(function () use ($summary): void {
                $file = fopen('php://output', 'w');

                fputcsv($file, ['Metric', 'Value']);
                foreach ($summary['stats'] as $label => $value) {
                    fputcsv($file, [$label, $value]);
                }

                fputcsv($file, []);
                fputcsv($file, ['Status', 'Count']);
                foreach ($summary['status_breakdown'] as $row) {
                    fputcsv($file, [$row['status'], $row['count']]);
                }

                fputcsv($file, []);
                fputcsv($file, ['Municipality', 'Uploads', 'Completed', 'Completion Rate']);
                foreach ($summary['municipality_performance'] as $row) {
                    fputcsv($file, [$row['municipality'], $row['uploads'], $row['completed'], $row['completion_rate']]);
                }

                fclose($file);
            }, 'upload_summary_' . now()->format('Y-m-d_H-i-s') . '.csv', [
                'Content-Type' => 'text/csv',
            ]);
        }

        return response()->json($summary);
    }

    public function deadlineSummary(Request $request)
    {
        $this->authorize('view reports');

        $filters = $this->resolveFilters($request);
        $summary = $this->buildDeadlineSummary($filters);

        if ($request->boolean('download')) {
            return response()->streamDownload(function () use ($summary): void {
                $file = fopen('php://output', 'w');

                fputcsv($file, ['Metric', 'Value']);
                foreach ($summary['stats'] as $label => $value) {
                    fputcsv($file, [$label, $value]);
                }

                fputcsv($file, []);
                fputcsv($file, ['Municipality', 'Deadline Date', 'Assignments', 'Submitted', 'Missing', 'Coverage']);
                foreach ($summary['rows'] as $row) {
                    fputcsv($file, [
                        $row['municipality'],
                        $row['deadline_date'],
                        $row['assigned_companies'],
                        $row['submitted_companies'],
                        $row['missing_companies'],
                        $row['coverage_rate'],
                    ]);
                }

                fclose($file);
            }, 'deadline_summary_' . now()->format('Y-m-d_H-i-s') . '.csv', [
                'Content-Type' => 'text/csv',
            ]);
        }

        return response()->json($summary);
    }

    private function resolveFilters(Request $request): array
    {
        $perPage = (int) $request->get('per_page', 20);
        if (! in_array($perPage, [20, 50, 100], true)) {
            $perPage = 20;
        }

        return [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'municipality_id' => $request->get('municipality_id'),
            'company_id' => $request->get('company_id'),
            'status' => $request->get('status'),
            'search' => $request->get('search'),
            'per_page' => $perPage,
        ];
    }

    private function buildUploadsQuery(array $filters): Builder
    {
        return Uploads::query()
            ->with(['company:id,name,municipality_id', 'municipality:id,name,code', 'user:id,name,email'])
            ->when($filters['date_from'], function (Builder $query, string $date): void {
                $query->where('submitted_at', '>=', Carbon::parse($date)->startOfDay());
            })
            ->when($filters['date_to'], function (Builder $query, string $date): void {
                $query->where('submitted_at', '<=', Carbon::parse($date)->endOfDay());
            })
            ->when($filters['municipality_id'], function (Builder $query, string $municipalityId): void {
                $query->where('municipality_id', $municipalityId);
            })
            ->when($filters['company_id'], function (Builder $query, string $companyId): void {
                $query->where('company_id', $companyId);
            })
            ->when($filters['status'], function (Builder $query, string $status): void {
                $query->where('status', $status);
            })
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $innerQuery) use ($search): void {
                    $innerQuery->where('reference', 'like', '%' . $search . '%')
                        ->orWhereHas('company', function (Builder $companyQuery) use ($search): void {
                            $companyQuery->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('municipality', function (Builder $municipalityQuery) use ($search): void {
                            $municipalityQuery->where('name', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            });
    }

    private function buildUploadSummary(array $filters): array
    {
        $uploads = $this->buildUploadsQuery($filters)->get();

        $statusBreakdown = $uploads
            ->groupBy('status')
            ->map(fn ($rows, $status) => [
                'status' => $status,
                'count' => $rows->count(),
            ])
            ->sortByDesc('count')
            ->values();

        $municipalityPerformance = $uploads
            ->groupBy(fn (Uploads $upload) => $upload->municipality?->name ?? 'Unknown')
            ->map(function ($rows, $municipality) {
                $completed = $rows->where('status', 'Completed')->count();
                $total = $rows->count();

                return [
                    'municipality' => $municipality,
                    'uploads' => $total,
                    'completed' => $completed,
                    'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) . '%' : '0%',
                ];
            })
            ->sortByDesc('uploads')
            ->take(8)
            ->values();

        $dailyVolume = $uploads
            ->groupBy(fn (Uploads $upload) => optional($upload->submitted_at)->format('Y-m-d') ?? 'N/A')
            ->map(fn ($rows, $date) => [
                'date' => $date,
                'count' => $rows->count(),
                'completed' => $rows->where('status', 'Completed')->count(),
            ])
            ->sortBy('date')
            ->take(-14)
            ->values();

        $stats = [
            'filtered_uploads' => $uploads->count(),
            'completed_uploads' => $uploads->where('status', 'Completed')->count(),
            'processing_uploads' => $uploads->where('status', 'Processing')->count(),
            'pending_uploads' => $uploads->where('status', 'Pending')->count(),
            'rejected_uploads' => $uploads->where('status', 'Rejected')->count(),
            'companies_covered' => $uploads->pluck('company_id')->filter()->unique()->count(),
            'municipalities_covered' => $uploads->pluck('municipality_id')->filter()->unique()->count(),
            'users_in_scope' => $uploads->pluck('user_id')->filter()->unique()->count(),
        ];

        return [
            'stats' => $stats,
            'status_breakdown' => $statusBreakdown,
            'municipality_performance' => $municipalityPerformance,
            'daily_volume' => $dailyVolume,
        ];
    }

    private function buildDeadlineSummary(array $filters): array
    {
        $deadlineQuery = MunicipalityDeadline::query()
            ->with([
                'municipality' => function ($query): void {
                    $query->withoutGlobalScopes()->select(['id', 'name']);
                },
            ])
            ->when($filters['municipality_id'], function (Builder $query, string $municipalityId): void {
                $query->where('municipality_id', $municipalityId);
            })
            ->when($filters['date_from'], function (Builder $query, string $date): void {
                $query->whereDate('deadline_date', '>=', Carbon::parse($date)->toDateString());
            })
            ->when($filters['date_to'], function (Builder $query, string $date): void {
                $query->whereDate('deadline_date', '<=', Carbon::parse($date)->toDateString());
            })
            ->orderBy('deadline_date');

        $deadlines = $deadlineQuery->get();

        $rows = $deadlines->map(function (MunicipalityDeadline $deadline) {
            $assignedCompanies = UserAssignment::query()
                ->where('municipality_id', $deadline->municipality_id)
                ->whereDate('deadline_date', $deadline->deadline_date)
                ->pluck('company_id')
                ->filter()
                ->unique();

            $submittedCompanies = Uploads::query()
                ->where('municipality_id', $deadline->municipality_id)
                ->whereBetween('submitted_at', [
                    $deadline->deadline_date->copy()->subDays(30)->startOfDay(),
                    $deadline->deadline_date->copy()->endOfDay(),
                ])
                ->pluck('company_id')
                ->filter()
                ->unique();

            $assignedCount = $assignedCompanies->count();
            $submittedCount = $submittedCompanies->count();
            $missingCount = max($assignedCount - $submittedCount, 0);

            return [
                'municipality' => $deadline->municipality?->name ?? 'Unknown',
                'deadline_date' => $deadline->deadline_date->format('Y-m-d'),
                'assigned_companies' => $assignedCount,
                'submitted_companies' => $submittedCount,
                'missing_companies' => $missingCount,
                'coverage_rate' => $assignedCount > 0 ? round(($submittedCount / $assignedCount) * 100, 1) . '%' : '0%',
                'is_overdue' => $deadline->deadline_date->isPast(),
            ];
        })->values();

        $stats = [
            'tracked_deadlines' => $rows->count(),
            'overdue_deadlines' => $rows->where('is_overdue', true)->count(),
            'assigned_companies' => $rows->sum('assigned_companies'),
            'submitted_companies' => $rows->sum('submitted_companies'),
            'missing_companies' => $rows->sum('missing_companies'),
        ];

        return [
            'stats' => $stats,
            'rows' => $rows,
        ];
    }

    private function transformUpload(Uploads $upload): array
    {
        $originalFiles = $upload->original_file_names ?? [];

        return [
            'id' => $upload->id,
            'reference' => $upload->reference,
            'status' => $upload->status,
            'company' => [
                'id' => $upload->company?->id,
                'name' => $upload->company?->name ?? 'N/A',
            ],
            'municipality' => [
                'id' => $upload->municipality?->id,
                'name' => $upload->municipality?->name ?? 'N/A',
            ],
            'user' => [
                'id' => $upload->user?->id,
                'name' => $upload->user?->name ?? 'Unknown',
                'email' => $upload->user?->email ?? 'Unknown',
            ],
            'submitted_at' => optional($upload->submitted_at)->toISOString(),
            'submitted_at_display' => optional($upload->submitted_at)->format('Y-m-d H:i'),
            'system_import_date_display' => optional($upload->system_import_date)->format('Y-m-d H:i') ?? 'N/A',
            'original_file_names' => $originalFiles,
            'original_files_count' => count($originalFiles),
            'workings_file_name' => $upload->workings_file_name ?? '-',
            'systems_import_file_name' => $upload->systems_import_file_name ?? '-',
            'reupload_reason_type' => $upload->reupload_reason_type ?? '-',
        ];
    }
}
