<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use App\Models\Uploads;
use App\Models\Company;
use App\Models\Municipality;
use App\Models\User;
use App\Models\MunicipalityDeadline;
use App\Models\UserAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\UploadsExports;
use App\Services\CaseyPremiumBatchService;
use App\Services\CapsSubmissionService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetException;
use PhpMimeMailParser\Parser as MimeMailParser;

class UploadsController extends Controller
{
    /**
     * Display the uploads page - USER SPECIFIC
     */
    public function index(Request $request)
    {
        $this->authorize('view uploads');

        $user = $request->user();
        $perPage = (int) $request->get('per_page', 12);
        if (!in_array($perPage, [12, 20, 50, 100], true)) {
            $perPage = 12;
        }

        // Query based on user's assignments - ONLY USER'S UPLOADS
        $query = Uploads::query()
            ->where('user_id', $user->id)
            ->with(['company:id,name', 'municipality:id,name', 'user:id,name,email'])
            ->where(function ($q) use ($user) {
                $q->whereHas('company.assignments', function ($assignmentQuery) use ($user) {
                    $assignmentQuery->where('user_id', $user->id);
                })
                    ->orWhereHas('municipality.assignments', function ($assignmentQuery) use ($user) {
                        $assignmentQuery->where('user_id', $user->id);
                    });
            })
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, function ($q, $s) {
                $q->where('reference', 'like', "%$s%")
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%$s%"))
                    ->orWhereHas('municipality', fn ($m) => $m->where('name', 'like', "%$s%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%$s%"));
            })
            ->latest('submitted_at');

        $isAdmin = $user->hasRole(['admin', 'super-admin', 'superadmin']);

        // Global scope on Municipality/Company filters to CAPS-synced only.
        // Admins see all; regular users see only their assigned ones.
        if ($isAdmin) {
            $assignedMunicipalities = Municipality::with(['deadlines'])->get();
        } else {
            $assignedMunicipalityIds = $user->assignments()
                ->pluck('municipality_id')
                ->unique()
                ->toArray();

            $assignedMunicipalities = Municipality::whereIn('id', $assignedMunicipalityIds)
                ->with(['deadlines'])
                ->get();
        }

        $municipalitiesWithDeadlines = [];
        $pendingDeadlines = [];

        foreach ($assignedMunicipalities as $municipality) {
            // Always scope companies to what the user is assigned to
            // for this municipality — even admins only see assigned companies.
            $assignedCompanyIds = $user->assignments()
                ->where('municipality_id', $municipality->id)
                ->pluck('company_id')
                ->filter()
                ->unique()
                ->toArray();

            if (empty($assignedCompanyIds)) {
                continue;
            }

            $upcomingDeadline = $municipality->deadlines()
                ->where('deadline_date', '>=', now()->startOfDay())
                ->orderBy('deadline_date')
                ->first();

            // Load companies directly (not filtered by municipality_id FK
            // since every company can submit to every municipality).
            $assignedCompanies = Company::whereIn('id', $assignedCompanyIds)
                ->get(['id', 'name', 'municipality_id']);

            if ($assignedCompanies->isEmpty()) {
                continue;
            }

            // ->values() ensures Inertia serializes as a JSON array, not an object
            $municipalitiesWithDeadlines[] = [
                'id' => $municipality->id,
                'name' => $municipality->name,
                'has_deadline' => !is_null($upcomingDeadline),
                'deadline_date' => $upcomingDeadline ? $upcomingDeadline->deadline_date->format('Y-m-d') : null,
                'assigned_companies' => $assignedCompanies->values(),
                'companies_count' => $assignedCompanies->count(),
            ];

            if ($upcomingDeadline && $user->hasPermissionTo('view deadlines')) {
                $submittedCompanyIds = Uploads::where('user_id', $user->id)
                    ->where('municipality_id', $municipality->id)
                    ->where('submitted_at', '>=', $upcomingDeadline->deadline_date->copy()->subDays(30))
                    ->where('submitted_at', '<=', $upcomingDeadline->deadline_date)
                    ->pluck('company_id')
                    ->unique()
                    ->toArray();

                $pendingCompanies = $assignedCompanies
                    ->whereNotIn('id', $submittedCompanyIds)
                    ->values();

                if ($pendingCompanies->count() > 0) {
                    $pendingDeadlines[] = [
                        'municipality' => $municipality->name,
                        'municipality_id' => $municipality->id,
                        'deadline_date' => $upcomingDeadline->deadline_date->format('Y-m-d'),
                        'pending_companies' => $pendingCompanies->pluck('name')->values(),
                        'pending_count' => $pendingCompanies->count(),
                        'total_companies_with_deadlines' => $assignedCompanies->count(),
                    ];
                }
            }
        }

        $municipalitiesWithDeadlines = collect($municipalitiesWithDeadlines)->unique('id')->values()->all();

        $paginated = $query->paginate($perPage)->withQueryString();

        $uploads = $paginated->through(function (Uploads $u) {
            $origNames = is_array($u->original_file_names)
                ? $u->original_file_names
                : (json_decode($u->original_file_names ?? '[]', true) ?: []);
            $origPaths = is_array($u->original_file_path)
                ? $u->original_file_path
                : (json_decode($u->original_file_path ?? '[]', true) ?: []);

            $originalUrls = [];
            foreach ($origPaths as $i => $path) {
                $originalUrls[] = route('uploads.download', ['upload' => $u->id, 'which' => 'original', 'index' => $i]);
            }

            $workingsUrl = $u->workings_file_path
                ? route('uploads.download', ['upload' => $u->id, 'which' => 'workings'])
                : null;

            $systemsUrl = $u->systems_import_file_path
                ? route('uploads.download', ['upload' => $u->id, 'which' => 'systems'])
                : null;

            $previewUrls = [];
            $emailPreviewDataUrls = [];
            foreach ($origNames as $i => $name) {
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($extension, ['eml', 'msg'], true)) {
                    $previewUrls[$i] = route('uploads.view-email', ['upload' => $u->id, 'index' => $i]);
                    $emailPreviewDataUrls[$i] = route('uploads.view-email-data', ['upload' => $u->id, 'index' => $i]);
                } elseif (in_array($extension, ['xls', 'xlsx', 'xlsm', 'xlsb', 'csv'], true)) {
                    $previewUrls[$i] = route('uploads.preview', ['upload' => $u->id, 'index' => $i]);
                }
            }

            // Build a unified list that lets the UI offer one "preview"
            // dropdown covering all three file slots, not just original emails.
            $allPreviewableFiles = $this->buildAllPreviewableFiles($u, $previewUrls);

            $uploadStats = $this->getUploadStats($u->company_id, $u->municipality_id, $u->id);

            return [
                'id' => $u->id,
                'reference' => $u->reference,
                'status' => $u->status,
                'company' => $u->company ? ['id' => $u->company->id, 'name' => $u->company->name] : null,
                'municipality' => $u->municipality ? ['id' => $u->municipality->id, 'name' => $u->municipality->name] : null,
                'user' => $u->user ? [
                    'id' => $u->user->id,
                    'name' => $u->user->name,
                    'email' => $u->user->email,
                    'initials' => $this->getUserInitials($u->user->name),
                ] : null,
                'created_at' => $u->created_at ? $u->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $u->updated_at ? $u->updated_at->format('Y-m-d H:i:s') : null,
                'submitted_at_formatted' => $u->submitted_at ? $u->submitted_at->format('Y-m-d H:i:s') : null,
                'submitted_at_human' => $u->submitted_at ? $u->submitted_at->diffForHumans() : null,
                'original_file_names' => $origNames,
                'original_file_urls' => $originalUrls,
                'preview_urls' => $previewUrls,
                'email_preview_data_urls' => $emailPreviewDataUrls,
                'all_previewable_files' => $allPreviewableFiles,
                'workings_file_name' => $u->workings_file_name,
                'workings_file_url' => $workingsUrl,
                'systems_import_file_name' => $u->systems_import_file_name,
                'systems_import_file_url' => $systemsUrl,
                'extracted_dates' => is_array($u->extracted_dates)
                    ? $u->extracted_dates
                    : (json_decode($u->extracted_dates ?? '[]', true) ?: []),
                'system_import_date' => optional($u->system_import_date)->toDateString(),
                'system_import_date_formatted' => $u->system_import_date ? $u->system_import_date->format('Y-m-d H:i:s') : null,
                'system_import_date_human' => $u->system_import_date ? $u->system_import_date->diffForHumans() : null,
                'submitted_at' => optional($u->submitted_at)->toDateString(),
                'reupload_reason_type' => $u->reupload_reason_type,
                'reupload_reason_note' => $u->reupload_reason_note,
                'stats' => $uploadStats,
                'completion_percentage' => $this->calculateCompletionPercentage($u),
                'missing_files' => $u->getMissingFilesList(),
                'next_required_file' => $u->getNextRequiredFile(),
                'can_be_completed' => in_array($u->status, ['Pending', 'Processing']) && !$u->hasAllRequiredFiles(),
                'caps_dispatch_status' => $u->caps_dispatch_status ?? 'draft',
                'caps_payment_batch_id' => $u->caps_payment_batch_id,
                'caps_status' => $u->caps_status,
                'caps_batch_type' => $u->caps_batch_type,
                'caps_retry_count' => $u->caps_retry_count ?? 0,
                'can_dispatch_to_caps' => $u->canDispatchToCaps(),
                'can_retry_caps' => $u->canRetryDispatch(),
            ];
        });

        return Inertia::render('Uploads/Index', [
            'filters' => $request->only(['status', 'search', 'per_page', 'view']),
            'uploads' => $uploads,
            'companies' => Company::whereIn('id', function ($query) use ($user) {
                $query->select('company_id')
                    ->from('user_assignments')
                    ->where('user_id', $user->id);
            })->get(['id', 'name', 'municipality_id']),
            'municipalities' => $municipalitiesWithDeadlines,
            'pendingDeadlines' => $pendingDeadlines,
        ]);
    }

    /**
     * Calculate completion percentage for upload
     */
    private function calculateCompletionPercentage(Uploads $upload): int
    {
        $total = 3; // Original, Workings, Systems
        $completed = 0;

        if (!empty($upload->original_file_path)) $completed++;
        if (!empty($upload->workings_file_path)) $completed++;
        if (!empty($upload->systems_import_file_path)) $completed++;

        return round(($completed / $total) * 100);
    }

    /**
     * Get user initials for avatar display
     */
    private function getUserInitials($name): string
    {
        $initials = '';
        $words = explode(' ', $name);

        if (count($words) >= 2) {
            $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        } elseif (count($words) === 1) {
            $initials = strtoupper(substr($words[0], 0, 2));
        }

        return $initials;
    }

    /**
     * Get upload statistics for a company/municipality - USER SPECIFIC
     */
    private function getUploadStats(int $companyId, int $municipalityId, int $currentUploadId): array
    {
        $allUploads = Uploads::where('company_id', $companyId)
            ->where('municipality_id', $municipalityId)
            ->where('user_id', auth()->id())
            ->orderBy('submitted_at', 'desc')
            ->get();

        $totalUploads = $allUploads->count();
        $currentIndex = $allUploads->search(fn ($upload) => $upload->id === $currentUploadId);

        $statusCounts = [
            'completed' => $allUploads->where('status', 'Completed')->count(),
            'pending' => $allUploads->where('status', 'Pending')->count(),
            'processing' => $allUploads->where('status', 'Processing')->count(),
            'rejected' => $allUploads->where('status', 'Rejected')->count(),
        ];

        $firstUpload = $allUploads->last();
        $averageDaysBetween = 0;

        if ($totalUploads > 1) {
            $firstDate = $firstUpload->submitted_at ?? $firstUpload->created_at;
            $lastDate = $allUploads->first()->submitted_at ?? $allUploads->first()->created_at;

            if ($firstDate && $lastDate) {
                $daysDiff = $firstDate->diffInDays($lastDate);
                $averageDaysBetween = $daysDiff / ($totalUploads - 1);
            }
        }

        $reuploads = $allUploads->filter(fn ($upload) => !empty($upload->reupload_reason_type));
        $commonReuploadReason = $reuploads->count() > 0
            ? $reuploads->groupBy('reupload_reason_type')
                ->sortDesc()
                ->keys()
                ->first()
            : null;

        return [
            'total_uploads' => $totalUploads,
            'upload_number' => $currentIndex !== false ? $totalUploads - $currentIndex : 0,
            'status_counts' => $statusCounts,
            'first_upload_date' => $firstUpload ? optional($firstUpload->submitted_at ?? $firstUpload->created_at)->toDateString() : null,
            'days_since_first' => $firstUpload ? now()->diffInDays($firstUpload->submitted_at ?? $firstUpload->created_at) : null,
            'average_days_between' => round($averageDaysBetween, 1),
            'reupload_count' => $reuploads->count(),
            'common_reupload_reason' => $commonReuploadReason,
            'has_workings' => $allUploads->filter(fn ($u) => !empty($u->workings_file_path))->count(),
            'has_systems' => $allUploads->filter(fn ($u) => !empty($u->systems_import_file_path))->count(),
        ];
    }

    /**
     * Store a single upload (one company at a time)
     */
    public function store(Request $request)
    {
        $this->authorize('create upload');

        Log::info('Upload store request received', [
            'request_data' => $request->all(),
            'files' => [
                'original_files_count' => count($request->file('original_files', [])),
                'has_workings' => $request->hasFile('workings_file'),
                'has_systems_import' => $request->hasFile('systems_import_file'),
            ]
        ]);

        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'municipality_id' => 'required|exists:municipalities,id',
            'original_files' => 'required|array|min:1',
            'original_files.*' => 'file|max:10240',
            'workings_file' => 'nullable|file|max:10240',
            'systems_import_file' => 'nullable|file|max:10240',
            'reupload_reason_type' => 'nullable|string|max:255',
            'reupload_reason_note' => 'nullable|string|max:1000',
        ]);

        $municipality = Municipality::findOrFail($request->municipality_id);
        $company = Company::findOrFail($request->company_id);
        $user = $request->user();

        $isAdmin = $user->hasRole(['admin', 'super-admin', 'superadmin']);

        $isAssigned = UserAssignment::where('user_id', $user->id)
            ->where('municipality_id', $municipality->id)
            ->where('company_id', $company->id)
            ->exists();

        if (!$isAssigned && !$isAdmin) {
            return back()->withErrors([
                'company_id' => 'You are not assigned to this company for the selected municipality.'
            ])->withInput();
        }

        $hasDeadline = MunicipalityDeadline::where('municipality_id', $municipality->id)
            ->where('deadline_date', '>=', now()->startOfDay())
            ->exists();

        if (!$hasDeadline && !$isAdmin) {
            return back()->withErrors([
                'company_id' => 'This municipality does not have an active deadline.'
            ])->withInput();
        }

        $recentUpload = Uploads::where('user_id', $user->id)
            ->where('company_id', $company->id)
            ->where('municipality_id', $municipality->id)
            ->where('submitted_at', '>=', now()->subDays(30))
            ->first();

        if ($recentUpload && empty($request->reupload_reason_type)) {
            return back()->withErrors([
                'reupload_reason_type' => 'You already have a recent upload for this company and municipality. Please provide a re-upload reason.'
            ])->withInput();
        }

        DB::beginTransaction();

        try {
            $filesData = $this->processUploadedFilesForCompany(
                $request,
                $user->id,
                $municipality->id,
                $company->id
            );

            $status = $this->determineUploadStatus(
                hasOriginal: !empty($filesData['original_file_paths']),
                hasWorkings: !empty($filesData['workings_file_path']),
                hasSystemsImport: !empty($filesData['systems_import_file_path'])
            );

            $upload = Uploads::create([
                'reference' => strtoupper(Str::random(10)),
                'company_id' => $company->id,
                'municipality_id' => $municipality->id,
                'user_id' => $user->id,
                'status' => $status,
                'original_file_path' => $filesData['original_file_paths'],
                'original_file_names' => $filesData['original_file_names'],
                'workings_file_path' => $filesData['workings_file_path'],
                'workings_file_name' => $filesData['workings_file_name'],
                'systems_import_file_path' => $filesData['systems_import_file_path'],
                'systems_import_file_name' => $filesData['systems_import_file_name'],
                'submitted_at' => now(),
                'extracted_dates' => $filesData['extracted_dates'],
                'system_import_date' => $filesData['system_import_date'],
                'reupload_reason_type' => $request->reupload_reason_type,
                'reupload_reason_note' => $request->reupload_reason_note,
                'converted_eml_paths' => $filesData['converted_eml_paths'] ?? [],
            ]);

            DB::commit();

            Log::info('Upload created successfully', [
                'upload_id' => $upload->id,
                'company_id' => $company->id,
                'municipality_id' => $municipality->id,
                'user_id' => $user->id,
                'status' => $status,
                'file_counts' => [
                    'originals' => count($filesData['original_file_paths']),
                    'has_workings' => !empty($filesData['workings_file_path']),
                    'has_systems' => !empty($filesData['systems_import_file_path']),
                ]
            ]);

            $this->sendUploadNotifications($user, [$upload], $municipality, collect([$company]), $status);

            // Auto-dispatch to CAPS if systems import file exists
            $hasSystemsImport = !empty($filesData['systems_import_file_path']);
            if ($hasSystemsImport) {
                // Company name guard — check file content matches the selected company
                try {
                    $svc = app(CapsSubmissionService::class);
                    $mismatch = $svc->getCompanyMismatch($upload);
                    if ($mismatch) {
                        // Keep the upload but don't dispatch — show error
                        return redirect()
                            ->route('uploads.history')
                            ->with('error', $mismatch);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Company name check failed: ' . $e->getMessage());
                }

                try {
                    set_time_limit(120);
                    $capsResult = app(CapsSubmissionService::class)->previewOnCaps($upload);
                    $capsOk = $capsResult['ok'] ?? false;
                } catch (\Throwable $e) {
                    Log::warning('CAPS auto-dispatch failed: ' . $e->getMessage());
                    $capsOk = false;
                }

                if ($capsOk) {
                    $summary = $capsResult['summary'] ?? [];
                    $capsNew = $summary['caps_new'] ?? 0;
                    $capsErr = $summary['caps_errors'] ?? 0;
                    $total = $summary['total'] ?? 0;

                    // Notify user about CAPS result
                    try {
                        $user->notifications()->create([
                            'type' => 'caps_dispatch',
                            'data' => json_encode([
                                'title' => 'CAPS batch created',
                                'message' => "{$company->name}: {$total} records processed — {$capsNew} new, {$capsErr} errors.",
                                'upload_id' => $upload->id,
                                'batch_id' => $summary['caps_batch_id'] ?? null,
                                'url' => route('uploads.caps-batch-detail', $upload->id),
                            ]),
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('CAPS notification failed: ' . $e->getMessage());
                    }

                    return redirect()
                        ->route('uploads.caps-batch-detail', $upload->id)
                        ->with('success', "Sent to CAPS: {$total} records — {$capsNew} new, {$capsErr} errors. Review below and press Save Premiums.");
                }
            }

            return redirect()
                ->route('uploads.history')
                ->with('success', 'File uploaded for ' . $company->name . '.');

        } catch (\Throwable $e) {
            DB::rollBack();

            if (isset($filesData)) {
                $this->cleanupUploadedFiles($filesData);
            }

            Log::error('Upload creation failed: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'municipality_id' => $municipality->id,
                'company_id' => $company->id,
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->withErrors(['error' => 'Upload failed: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Complete a pending upload by adding missing files
     */
    public function complete(Request $request, Uploads $upload)
    {
        $this->authorize('view uploads');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'You do not have permission to complete this upload.');
        }

        if ($upload->status === 'Completed') {
            return back()->withErrors(['error' => 'This upload is already completed.']);
        }

        $request->validate([
            'workings_file' => 'nullable|file|max:10240',
            'systems_import_file' => 'nullable|file|max:10240',
            'original_files' => 'nullable|array',
            'original_files.*' => 'file|max:10240',
        ]);

        DB::beginTransaction();

        try {
            $disk = Storage::disk('private');
            $hasChanges = false;

            $originalPaths = is_array($upload->original_file_path)
                ? $upload->original_file_path
                : (json_decode($upload->original_file_path ?? '[]', true) ?: []);

            $originalNames = is_array($upload->original_file_names)
                ? $upload->original_file_names
                : (json_decode($upload->original_file_names ?? '[]', true) ?: []);

            if ($request->hasFile('original_files')) {
                $newFiles = $request->file('original_files');

                foreach ($newFiles as $file) {
                    if ($file->isValid()) {
                        $dir = !empty($originalPaths) ? dirname($originalPaths[0]) : "uploads/user-{$upload->user_id}/company-{$upload->company_id}/" . now()->format('Y-m-d-His');
                        $path = $file->store($dir, 'private');

                        $originalPaths[] = $path;
                        $originalNames[] = $file->getClientOriginalName();
                        $hasChanges = true;
                    }
                }
            }

            if (!$upload->workings_file_path && $request->hasFile('workings_file')) {
                $workingsFile = $request->file('workings_file');
                if ($workingsFile->isValid()) {
                    $dir = !empty($originalPaths) ? dirname($originalPaths[0]) : "uploads/user-{$upload->user_id}/company-{$upload->company_id}/" . now()->format('Y-m-d-His');
                    $upload->workings_file_path = $workingsFile->store($dir, 'private');
                    $upload->workings_file_name = $workingsFile->getClientOriginalName();
                    $hasChanges = true;
                }
            }

            if (!$upload->systems_import_file_path && $request->hasFile('systems_import_file')) {
                $systemsFile = $request->file('systems_import_file');
                if ($systemsFile->isValid()) {
                    $dir = !empty($originalPaths) ? dirname($originalPaths[0]) : "uploads/user-{$upload->user_id}/company-{$upload->company_id}/" . now()->format('Y-m-d-His');
                    $upload->systems_import_file_path = $systemsFile->store($dir, 'private');
                    $upload->systems_import_file_name = $systemsFile->getClientOriginalName();
                    $upload->system_import_date = now();
                    $hasChanges = true;
                }
            }

            if ($hasChanges) {
                $upload->original_file_path = $originalPaths;
                $upload->original_file_names = $originalNames;

                $status = $this->determineUploadStatus(
                    hasOriginal: !empty($upload->original_file_path),
                    hasWorkings: !empty($upload->workings_file_path),
                    hasSystemsImport: !empty($upload->systems_import_file_path)
                );

                $upload->status = $status;
                $upload->save();

                DB::commit();

                Log::info('Upload completed successfully', [
                    'upload_id' => $upload->id,
                    'new_status' => $status,
                    'user_id' => auth()->id()
                ]);

                return redirect()
                    ->route('uploads.index')
                    ->with('success', 'Upload completed successfully. New status: ' . $status);
            }

            DB::rollBack();
            return back()->withErrors(['error' => 'No files were added to complete the upload.']);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Failed to complete upload: ' . $e->getMessage(), [
                'upload_id' => $upload->id,
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->withErrors(['error' => 'Failed to complete upload: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Show complete form for pending upload
     */
    public function showCompleteForm(Uploads $upload)
    {
        $this->authorize('view uploads');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'You do not have permission to complete this upload.');
        }

        if ($upload->status === 'Completed') {
            return redirect()->route('uploads.index')
                ->with('info', 'This upload is already completed.');
        }

        $missingFiles = $upload->getMissingFilesList();
        $nextRequired = $upload->getNextRequiredFile();

        return Inertia::render('Uploads/Complete', [
            'upload' => [
                'id' => $upload->id,
                'reference' => $upload->reference,
                'status' => $upload->status,
                'company' => $upload->company ? [
                    'id' => $upload->company->id,
                    'name' => $upload->company->name
                ] : null,
                'municipality' => $upload->municipality ? [
                    'id' => $upload->municipality->id,
                    'name' => $upload->municipality->name
                ] : null,
                'original_file_names' => $upload->original_file_names,
                'workings_file_name' => $upload->workings_file_name,
                'systems_import_file_name' => $upload->systems_import_file_name,
                'submitted_at' => $upload->submitted_at ? $upload->submitted_at->format('Y-m-d H:i:s') : null,
                'submitted_at_human' => $upload->submitted_at ? $upload->submitted_at->diffForHumans() : null,
            ],
            'missing_files' => $missingFiles,
            'next_required' => $nextRequired,
            'can_complete' => in_array($upload->status, ['Pending', 'Processing']),
        ]);
    }

    /**
     * Process uploaded files for a specific company
     */
    private function processUploadedFilesForCompany(
        Request $request,
        int $userId,
        int $municipalityId,
        int $companyId
    ): array {
        $timestamp = now()->format('Y-m-d-His');
        $baseDir = "uploads/user-{$userId}/municipality-{$municipalityId}/company-{$companyId}/{$timestamp}";

        $workingsFilePath = null;
        $workingsFileName = null;
        $systemsImportFilePath = null;
        $systemsImportFileName = null;
        $systemImportDate = null;
        $originalFilePaths = [];
        $originalFileNames = [];
        $extractedDates = [];
        $convertedEmlPaths = [];

        Storage::disk('private')->makeDirectory($baseDir);

        Log::info("Processing files for company {$companyId}", [
            'base_dir' => $baseDir,
            'original_files_count' => $request->hasFile('original_files') ? count($request->file('original_files')) : 0,
            'has_workings' => $request->hasFile('workings_file'),
            'has_systems' => $request->hasFile('systems_import_file'),
        ]);

        if ($request->hasFile('workings_file')) {
            $workingsFile = $request->file('workings_file');
            if ($workingsFile->isValid()) {
                $workingsFilePath = $workingsFile->store("{$baseDir}", 'private');
                $workingsFileName = $workingsFile->getClientOriginalName();
                Log::info("Stored workings file for company {$companyId}", [
                    'path' => $workingsFilePath,
                    'filename' => $workingsFileName,
                ]);
            }
        }

        if ($request->hasFile('systems_import_file')) {
            $systemsImportFile = $request->file('systems_import_file');
            if ($systemsImportFile->isValid()) {
                $systemsImportFilePath = $systemsImportFile->store("{$baseDir}", 'private');
                $systemsImportFileName = $systemsImportFile->getClientOriginalName();
                $systemImportDate = now();
                Log::info("Stored systems import file for company {$companyId}", [
                    'path' => $systemsImportFilePath,
                    'filename' => $systemsImportFileName,
                ]);
            }
        }

        if ($request->hasFile('original_files')) {
            $originalFiles = $request->file('original_files');

            foreach ($originalFiles as $index => $originalFile) {
                if ($originalFile->isValid()) {
                    $originalFilePath = $originalFile->store("{$baseDir}", 'private');
                    $extension = strtolower($originalFile->getClientOriginalExtension());
                    $fileName = $originalFile->getClientOriginalName();

                    $extractedDate = null;
                    if (in_array($extension, ['eml', 'msg'])) {
                        $extractedDate = $this->extractDateFromEmailFile($originalFilePath, $extension);
                    }

                    $convertedEmlPath = null;
                    if ($extension === 'msg') {
                        $convertedEmlPath = $this->convertMsgFileToEml($originalFilePath);
                    }

                    $originalFilePaths[] = $originalFilePath;
                    $originalFileNames[] = $fileName;
                    $extractedDates[] = $extractedDate?->toDateTimeString();
                    $convertedEmlPaths[] = $convertedEmlPath;

                    Log::info("Stored original file {$index} for company {$companyId}", [
                        'path' => $originalFilePath,
                        'filename' => $fileName,
                        'extension' => $extension,
                    ]);
                }
            }
        }

        return [
            'workings_file_path' => $workingsFilePath,
            'workings_file_name' => $workingsFileName,
            'systems_import_file_path' => $systemsImportFilePath,
            'systems_import_file_name' => $systemsImportFileName,
            'system_import_date' => $systemImportDate,
            'original_file_paths' => $originalFilePaths,
            'original_file_names' => $originalFileNames,
            'extracted_dates' => $extractedDates,
            'converted_eml_paths' => $convertedEmlPaths,
        ];
    }

    /**
     * Analyze spreadsheet data for rejection reasons
     */
    private function analyzeSpreadsheetForRejections(string $filePath, string $extension): array
    {
        try {
            if (!file_exists($filePath)) {
                return [
                    'has_rejections' => false,
                    'total_records' => 0,
                    'rejection_count' => 0,
                    'rejection_reasons' => [],
                    'rejection_summary' => [],
                    'error' => 'File not found'
                ];
            }

            $spreadsheet = $this->loadSpreadsheet($filePath, $extension);
            $sheet = $spreadsheet->getActiveSheet();

            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            $headers = [];
            $rejectionReasons = [];
            $rejectionSummary = [
                'Member Not on System' => 0,
                'ID / Pay Number Mismatch' => 0,
                'Incorrect Division Loading' => 0,
                'Duplicate Record' => 0,
                'Invalid Format' => 0,
            ];

            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $sheet->getCellByColumnAndRow($col, 1);
                $headers[$col] = strtolower(trim($cell->getFormattedValue() ?? ''));
            }

            $rejectionColumn = null;
            $statusColumn = null;

            foreach ($headers as $col => $header) {
                if (str_contains($header, 'reject') || str_contains($header, 'reason') || str_contains($header, 'error')) {
                    $rejectionColumn = $col;
                }
                if (str_contains($header, 'status') || str_contains($header, 'result')) {
                    $statusColumn = $col;
                }
            }

            for ($row = 2; $row <= $highestRow; $row++) {
                $hasRejection = false;
                $reasons = [];

                if ($rejectionColumn) {
                    $cell = $sheet->getCellByColumnAndRow($rejectionColumn, $row);
                    $value = trim($cell->getFormattedValue() ?? '');

                    if (!empty($value)) {
                        $hasRejection = true;
                        $reasons[] = $value;
                        $this->categorizeRejectionReason($value, $rejectionSummary);
                    }
                }

                if ($statusColumn && !$hasRejection) {
                    $cell = $sheet->getCellByColumnAndRow($statusColumn, $row);
                    $value = strtolower(trim($cell->getFormattedValue() ?? ''));

                    if (str_contains($value, 'reject') || str_contains($value, 'fail') || str_contains($value, 'error')) {
                        $hasRejection = true;
                        $reasons[] = $value;
                        $this->categorizeRejectionReason($value, $rejectionSummary);
                    }
                }

                if (!$hasRejection) {
                    for ($col = 1; $col <= $highestColumnIndex; $col++) {
                        $cell = $sheet->getCellByColumnAndRow($col, $row);
                        $value = trim($cell->getFormattedValue() ?? '');

                        $pattern = $this->detectRejectionPattern($value);
                        if ($pattern) {
                            $hasRejection = true;
                            $reasons[] = $pattern;
                            $rejectionSummary[$pattern] = ($rejectionSummary[$pattern] ?? 0) + 1;
                            break;
                        }
                    }
                }

                if ($hasRejection) {
                    $rejectionReasons[] = [
                        'row' => $row,
                        'reasons' => $reasons,
                        'data' => $this->getRowData($sheet, $row, $highestColumnIndex)
                    ];
                }
            }

            $rejectionSummary = array_filter($rejectionSummary);

            return [
                'has_rejections' => count($rejectionReasons) > 0,
                'total_records' => $highestRow - 1,
                'rejection_count' => count($rejectionReasons),
                'rejection_reasons' => array_slice($rejectionReasons, 0, 100),
                'rejection_summary' => $rejectionSummary,
                'headers' => array_values($headers),
                'rejection_rate' => $highestRow > 1 ? round((count($rejectionReasons) / ($highestRow - 1)) * 100, 2) : 0,
                'analyzed_at' => now()->toDateTimeString(),
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to analyze spreadsheet for rejections', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            return [
                'has_rejections' => false,
                'total_records' => 0,
                'rejection_count' => 0,
                'rejection_reasons' => [],
                'rejection_summary' => [],
                'error' => 'Analysis failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Load spreadsheet with appropriate reader
     */
    private function loadSpreadsheet(string $filePath, string $extension)
    {
        switch ($extension) {
            case 'xlsx':
            case 'xlsm':
            case 'xlsb':
                $reader = IOFactory::createReader('Xlsx');
                break;
            case 'xls':
                $reader = IOFactory::createReader('Xls');
                break;
            case 'ods':
                $reader = IOFactory::createReader('Ods');
                break;
            case 'csv':
                $reader = IOFactory::createReader('Csv');
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                break;
            default:
                throw new \RuntimeException("Unsupported format: {$extension}");
        }

        $reader->setReadDataOnly(true);
        return $reader->load($filePath);
    }

    /**
     * Get row data as array
     */
    private function getRowData($sheet, int $row, int $maxCols): array
    {
        $data = [];
        for ($col = 1; $col <= $maxCols; $col++) {
            $cell = $sheet->getCellByColumnAndRow($col, $row);
            $value = $cell->getFormattedValue();

            if (is_string($value) && strlen($value) > 100) {
                $value = substr($value, 0, 100) . '...';
            }

            $data[] = $value;
        }
        return $data;
    }

    /**
     * Categorize rejection reason
     */
    private function categorizeRejectionReason(string $reason, array &$summary): void
    {
        $reason = strtolower(trim($reason));

        $patterns = [
            'Member Not on System' => ['member not on system', 'member not found', 'not in system', 'employee not found'],
            'ID / Pay Number Mismatch' => ['id mismatch', 'pay number mismatch', 'id number mismatch', 'employee id mismatch', 'payroll number mismatch'],
            'Incorrect Division Loading' => ['division', 'loading', 'incorrect division', 'wrong division', 'division code'],
            'Duplicate Record' => ['duplicate', 'already exists', 'duplicated', 'multiple entries'],
            'Invalid Format' => ['invalid format', 'incorrect format', 'wrong format', 'format error', 'malformed'],
        ];

        foreach ($patterns as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($reason, $keyword)) {
                    $summary[$category] = ($summary[$category] ?? 0) + 1;
                    return;
                }
            }
        }

        $summary['Other'] = ($summary['Other'] ?? 0) + 1;
    }

    /**
     * Detect rejection pattern in text
     */
    private function detectRejectionPattern(string $value): ?string
    {
        $value = strtolower(trim($value));

        if (str_contains($value, 'member not on system') || str_contains($value, 'member not found')) {
            return 'Member Not on System';
        }

        if (str_contains($value, 'id mismatch') || str_contains($value, 'pay number mismatch')) {
            return 'ID / Pay Number Mismatch';
        }

        if (str_contains($value, 'division')) {
            return 'Incorrect Division Loading';
        }

        if (str_contains($value, 'duplicate')) {
            return 'Duplicate Record';
        }

        if (str_contains($value, 'format')) {
            return 'Invalid Format';
        }

        return null;
    }

    /**
     * Enhanced spreadsheet / email preview with rejection analysis.
     *
     * $index can be:
     *   - a numeric index into original_file_path
     *   - the string "workings"  → preview the workings spreadsheet
     *   - the string "systems"   → preview the systems-import file
     */
    public function preview(Uploads $upload, string $index = '0')
    {
        $this->authorize('view uploads');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'You do not have permission to view this upload.');
        }

        $disk = Storage::disk('private');

        // Resolve the file path + display name from the correct slot.
        if ($index === 'workings') {
            $path = $upload->workings_file_path;
            $fileName = $upload->workings_file_name ?? basename((string) $path);
            $downloadWhich = 'workings';
            $downloadIndex = null;
        } elseif ($index === 'systems') {
            $path = $upload->systems_import_file_path;
            $fileName = $upload->systems_import_file_name ?? basename((string) $path);
            $downloadWhich = 'systems';
            $downloadIndex = null;
        } else {
            $numericIndex = (int) $index;
            $paths = is_array($upload->original_file_path)
                ? $upload->original_file_path
                : (json_decode($upload->original_file_path ?? '[]', true) ?: []);
            if (!isset($paths[$numericIndex])) {
                abort(404);
            }
            $path = $paths[$numericIndex];
            $names = is_array($upload->original_file_names)
                ? $upload->original_file_names
                : (json_decode($upload->original_file_names ?? '[]', true) ?: []);
            $fileName = $names[$numericIndex] ?? basename($path);
            $downloadWhich = 'original';
            $downloadIndex = $numericIndex;
        }

        abort_unless($path && $disk->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $fullPath = $disk->path($path);

        if (in_array($extension, ['eml', 'msg'])) {
            $emailData = $this->parseEmailFile($fullPath, $extension);

            return Inertia::render('Uploads/ViewEmail', [
                'upload' => $this->getUploadInfo($upload),
                'file_index' => $index,
                'file_name' => $fileName,
                'file_type' => $extension,
                'email_data' => $emailData,
                'download_url' => route('uploads.download', array_filter([
                    'upload' => $upload->id,
                    'which' => $downloadWhich,
                    'index' => $downloadIndex,
                ], fn ($v) => $v !== null)),
                'raw_content' => $this->getRawEmailContent($fullPath, $extension),
            ]);

        } elseif (in_array($extension, ['xls', 'xlsx', 'csv', 'ods', 'xlsm', 'xlsb'])) {
            $spreadsheetData = $this->parseSpreadsheetFile($fullPath, $extension);
            $rejectionAnalysis = $this->analyzeSpreadsheetForRejections($fullPath, $extension);

            return Inertia::render('Uploads/ViewSpreadsheet', [
                'upload' => $this->getUploadInfo($upload),
                'file_index' => $index,
                'file_name' => $fileName,
                'file_type' => $extension,
                'spreadsheet_data' => $spreadsheetData,
                'rejection_analysis' => $rejectionAnalysis,
                'download_url' => route('uploads.download', array_filter([
                    'upload' => $upload->id,
                    'which'  => $downloadWhich,
                    'index'  => $downloadIndex,
                ], fn ($v) => $v !== null)),
            ]);
        }

        return redirect()->route('uploads.download', array_filter([
            'upload' => $upload->id,
            'which' => $downloadWhich,
            'index' => $downloadIndex
        ], fn ($v) => $v !== null));
    }

    /**
     * Dedicated email preview endpoint
     */
    public function viewEmail(Uploads $upload, int $index = 0)
    {
        $this->authorize('view uploads');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'You do not have permission to view this upload.');
        }

        $paths = is_array($upload->original_file_path)
            ? $upload->original_file_path
            : (json_decode($upload->original_file_path ?? '[]', true) ?: []);
        $names = is_array($upload->original_file_names)
            ? $upload->original_file_names
            : (json_decode($upload->original_file_names ?? '[]', true) ?: []);

        if (!isset($paths[$index])) {
            abort(404, 'File not found.');
        }

        $extension = strtolower(pathinfo($paths[$index], PATHINFO_EXTENSION));
        if (!in_array($extension, ['eml', 'msg'], true)) {
            return redirect()->route('uploads.preview', ['upload' => $upload->id, 'index' => $index]);
        }

        $disk = Storage::disk('private');
        $path = $paths[$index];
        abort_unless($disk->exists($path), 404);
        $fullPath = $disk->path($path);

        $emailData = $this->parseEmailFile($fullPath, $extension);
        $emailData = $this->withAttachmentLinks($emailData, $upload->id, $index);

        return Inertia::render('Uploads/ViewEmail', [
            'upload' => $this->getUploadInfo($upload),
            'file_index' => $index,
            'file_name' => $names[$index] ?? basename($path),
            'file_type' => $extension,
            'email_data' => $emailData,
            'download_url' => route('uploads.download', ['upload' => $upload->id, 'which' => 'original', 'index' => $index]),
            'raw_content' => $this->getRawEmailContent($fullPath, $extension),
            'open_outlook_hint' => true,
        ]);
    }

    /**
     * JSON email preview endpoint for in-page modals.
     */
    public function viewEmailData(Uploads $upload, int $index = 0): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view uploads');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'You do not have permission to view this upload.');
        }

        $paths = is_array($upload->original_file_path)
            ? $upload->original_file_path
            : (json_decode($upload->original_file_path ?? '[]', true) ?: []);
        $names = is_array($upload->original_file_names)
            ? $upload->original_file_names
            : (json_decode($upload->original_file_names ?? '[]', true) ?: []);

        if (!isset($paths[$index])) {
            abort(404, 'File not found.');
        }

        $path = $paths[$index];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['eml', 'msg'], true)) {
            abort(422, 'File is not an email.');
        }

        $disk = Storage::disk('private');
        abort_unless($disk->exists($path), 404);
        $fullPath = $disk->path($path);

        $emailData = $this->parseEmailFile($fullPath, $extension);
        $emailData = $this->withAttachmentLinks($emailData, $upload->id, $index);

        return response()->json([
            'ok' => true,
            'file_name' => $names[$index] ?? basename($path),
            'file_type' => $extension,
            'email_data' => $emailData,
            'raw_content' => $this->getRawEmailContent($fullPath, $extension),
            'download_url' => route('uploads.download', ['upload' => $upload->id, 'which' => 'original', 'index' => $index]),
            'open_outlook_hint' => true,
        ]);
    }

    /**
     * JSON endpoint for spreadsheet preview data (used by the inline popup).
     * Mirrors the slot-resolution logic from preview() so it handles numeric
     * indices as well as the literal strings "workings" and "systems".
     */
    public function previewData(Uploads $upload, string $index = '0'): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view uploads');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403);
        }

        $disk = Storage::disk('private');

        if ($index === 'workings') {
            $path = $upload->workings_file_path;
            $fileName = $upload->workings_file_name ?? basename((string) $path);
        } elseif ($index === 'systems') {
            $path = $upload->systems_import_file_path;
            $fileName = $upload->systems_import_file_name ?? basename((string) $path);
        } else {
            $numericIndex = (int) $index;
            $paths = is_array($upload->original_file_path)
                ? $upload->original_file_path
                : (json_decode($upload->original_file_path ?? '[]', true) ?: []);
            $names = is_array($upload->original_file_names)
                ? $upload->original_file_names
                : (json_decode($upload->original_file_names ?? '[]', true) ?: []);
            $path = $paths[$numericIndex] ?? null;
            $fileName = $names[$numericIndex] ?? basename((string) $path);
        }

        abort_unless($path && $disk->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $fullPath = $disk->path($path);

        if (in_array($extension, ['xls', 'xlsx', 'csv', 'ods', 'xlsm', 'xlsb'])) {
            $spreadsheetData = $this->parseSpreadsheetFile($fullPath, $extension);
            return response()->json([
                'ok' => true,
                'kind' => 'spreadsheet',
                'file_name' => $fileName,
                'file_type' => $extension,
                'spreadsheet_data' => $spreadsheetData,
            ]);
        }

        return response()->json(['ok' => false, 'message' => 'Unsupported file type for inline preview'], 422);
    }

    /**
     * Download an attachment from EML/MSG preview.
     */
    public function downloadEmailAttachment(Uploads $upload, int $index, int $attachmentIndex)
    {
        $this->authorize('view uploads');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'You do not have permission to view this upload.');
        }

        $paths = is_array($upload->original_file_path)
            ? $upload->original_file_path
            : (json_decode($upload->original_file_path ?? '[]', true) ?: []);

        if (!isset($paths[$index])) {
            abort(404, 'File not found.');
        }

        $sourcePath = $paths[$index];
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        abort_unless(in_array($extension, ['eml', 'msg'], true), 404, 'Not an email file.');

        $disk = Storage::disk('private');
        abort_unless($disk->exists($sourcePath), 404);

        $pathForParsing = $sourcePath;
        if ($extension === 'msg') {
            $converted = $this->convertMsgFileToEml($sourcePath);
            if ($converted && $disk->exists($converted)) {
                $pathForParsing = $converted;
            } else {
                abort(404, 'Attachment extraction unavailable for this MSG file.');
            }
        }

        $fullPath = $disk->path($pathForParsing);

        try {
            $parser = new MimeMailParser();
            $parser->setPath($fullPath);
            $attachments = $parser->getAttachments();

            if (!isset($attachments[$attachmentIndex])) {
                abort(404, 'Attachment not found.');
            }

            $attachment = $attachments[$attachmentIndex];
            $filename = $attachment->getFilename() ?: ('attachment-' . ($attachmentIndex + 1));
            $contentType = $attachment->getContentType() ?: 'application/octet-stream';
            $content = $attachment->getContent();

            return response($content, 200, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . addslashes($filename) . '"',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to download email attachment', [
                'upload_id' => $upload->id,
                'index' => $index,
                'attachment_index' => $attachmentIndex,
                'error' => $e->getMessage(),
            ]);
            abort(404, 'Attachment not available.');
        }
    }

    /**
     * Convert a source MSG upload to EML and return it
     */
    public function convertMsgToEml(Uploads $upload, int $index)
    {
        $this->authorize('view uploads');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'You do not have permission to view this upload.');
        }

        $paths = is_array($upload->original_file_path)
            ? $upload->original_file_path
            : (json_decode($upload->original_file_path ?? '[]', true) ?: []);
        $names = is_array($upload->original_file_names)
            ? $upload->original_file_names
            : (json_decode($upload->original_file_names ?? '[]', true) ?: []);

        if (!isset($paths[$index])) {
            abort(404, 'File not found.');
        }

        $msgPath = $paths[$index];
        $extension = strtolower(pathinfo($msgPath, PATHINFO_EXTENSION));
        abort_unless($extension === 'msg', 404, 'Not a .msg file');

        $emlPath = $this->convertMsgFileToEml($msgPath);
        if (!$emlPath || !Storage::disk('private')->exists($emlPath)) {
            return redirect()->back()->with('error', 'Failed to convert .msg file to .eml.');
        }

        $downloadName = pathinfo($names[$index] ?? basename($msgPath), PATHINFO_FILENAME) . '.eml';
        return Storage::disk('private')->download($emlPath, $downloadName);
    }

    /**
     * Get upload info for preview
     */
    private function getUploadInfo(Uploads $upload): array
    {
        return [
            'id' => $upload->id,
            'reference' => $upload->reference,
            'status' => $upload->status,
            'company' => $upload->company ? [
                'id' => $upload->company->id,
                'name' => $upload->company->name
            ] : null,
            'municipality' => $upload->municipality ? [
                'id' => $upload->municipality->id,
                'name' => $upload->municipality->name
            ] : null,
            'user' => $upload->user ? [
                'id' => $upload->user->id,
                'name' => $upload->user->name,
                'email' => $upload->user->email,
            ] : null,
            'submitted_at' => $upload->submitted_at ? $upload->submitted_at->format('Y-m-d H:i:s') : null,
            'submitted_at_human' => $upload->submitted_at ? $upload->submitted_at->diffForHumans() : null,
        ];
    }

    /**
     * Parse spreadsheet file (Excel/CSV)
     */
    private function parseSpreadsheetFile(string $filePath, string $extension): array
    {
        try {
            if (!file_exists($filePath)) {
                throw new \RuntimeException('File not found');
            }

            $spreadsheet = $this->loadSpreadsheet($filePath, $extension);
            $sheet = $spreadsheet->getActiveSheet();

            $data = [];
            $maxRows = min(200, $sheet->getHighestRow());
            $maxCols = $sheet->getHighestColumn();
            $maxColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($maxCols);
            $maxColIndex = min($maxColIndex, 50);

            for ($row = 1; $row <= $maxRows; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $maxColIndex; $col++) {
                    $cell = $sheet->getCellByColumnAndRow($col, $row);
                    $value = $cell->getFormattedValue();

                    if (is_string($value) && strlen($value) > 500) {
                        $value = substr($value, 0, 500) . '...';
                    }
                    $rowData[] = $value;
                }
                $data[] = $rowData;
            }

            $sheetNames = [];
            foreach ($spreadsheet->getSheetNames() as $name) {
                $sheetNames[] = $name;
            }

            return [
                'data' => $data,
                'headers' => !empty($data) ? $data[0] : [],
                'rows' => count($data) > 1 ? array_slice($data, 1) : [],
                'sheet_names' => $sheetNames,
                'current_sheet' => $sheet->getTitle(),
                'total_rows' => $sheet->getHighestRow(),
                'total_columns' => $maxColIndex,
                'file_size' => filesize($filePath),
                'parsed_successfully' => true,
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to parse spreadsheet file', [
                'file' => $filePath,
                'extension' => $extension,
                'error' => $e->getMessage(),
            ]);

            return [
                'data' => [],
                'headers' => [],
                'rows' => [],
                'sheet_names' => [],
                'current_sheet' => '',
                'total_rows' => 0,
                'total_columns' => 0,
                'file_size' => filesize($filePath),
                'parsed_successfully' => false,
                'error' => 'Failed to parse file: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Convert .msg file to .eml format
     */
    private function convertMsgFileToEml(string $msgFilePath): ?string
    {
        try {
            $fullPath = Storage::disk('private')->path($msgFilePath);

            if (!file_exists($fullPath)) {
                return null;
            }

            $emlFilePath = preg_replace('/\.msg$/i', '.eml', $msgFilePath);

            if (class_exists(\Webklex\PHPIMAP\Message::class)) {
                $message = \Webklex\PHPIMAP\Message::fromFile($fullPath);
                $emlContent = $this->buildEmlContentFromMessage($message);
                Storage::disk('private')->put($emlFilePath, $emlContent);
                return $emlFilePath;
            }

            return $this->convertMsgUsingFallback($fullPath, $emlFilePath);

        } catch (\Throwable $e) {
            Log::error('Failed to convert .msg to .eml', [
                'file' => $msgFilePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Build EML content from PHP-IMAP Message object
     */
    private function buildEmlContentFromMessage($message): string
    {
        $headers = [];

        if ($message->getSubject()) {
            $headers[] = 'Subject: ' . $message->getSubject();
        }

        if ($message->getFrom()->first()) {
            $from = $message->getFrom()->first();
            $headers[] = 'From: ' . ($from->personal ? "\"{$from->personal}\" <{$from->mail}>" : $from->mail);
        }

        if ($message->getTo()->count() > 0) {
            $toAddresses = [];
            foreach ($message->getTo() as $to) {
                $toAddresses[] = $to->personal ? "\"{$to->personal}\" <{$to->mail}>" : $to->mail;
            }
            $headers[] = 'To: ' . implode(', ', $toAddresses);
        }

        if ($message->getCc()->count() > 0) {
            $ccAddresses = [];
            foreach ($message->getCc() as $cc) {
                $ccAddresses[] = $cc->personal ? "\"{$cc->personal}\" <{$cc->mail}>" : $cc->mail;
            }
            $headers[] = 'Cc: ' . implode(', ', $ccAddresses);
        }

        if ($message->getDate()) {
            $headers[] = 'Date: ' . $message->getDate();
        }

        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';

        $emlContent = implode("\r\n", $headers);
        $emlContent .= "\r\n\r\n";
        $emlContent .= $message->getTextBody() ?: $message->getHTMLBody() ?: '';

        return $emlContent;
    }

    /**
     * Fallback .msg to .eml conversion
     */
    private function convertMsgUsingFallback(string $msgPath, string $emlFilePath): ?string
    {
        try {
            $content = file_get_contents($msgPath);
            if ($content === false) {
                return null;
            }

            $subject = $this->extractMsgField($content, 'subject');
            $from = $this->extractMsgField($content, 'from');
            $to = $this->extractMsgField($content, 'to');
            $date = $this->extractMsgField($content, 'date');
            $body = $this->extractMsgBody($content);

            $emlContent = "Subject: $subject\r\n";
            $emlContent .= "From: $from\r\n";
            $emlContent .= "To: $to\r\n";
            if ($date) {
                $emlContent .= "Date: $date\r\n";
            }
            $emlContent .= "MIME-Version: 1.0\r\n";
            $emlContent .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $emlContent .= "\r\n";
            $emlContent .= $body;

            Storage::disk('private')->put($emlFilePath, $emlContent);

            return $emlFilePath;

        } catch (\Throwable $e) {
            Log::error('Fallback .msg conversion failed', [
                'file' => $msgPath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract date from email files
     */
    private function extractDateFromEmailFile(string $filePath, string $extension): ?Carbon
    {
        try {
            $fullPath = Storage::disk('private')->path($filePath);

            if (!file_exists($fullPath)) {
                return null;
            }

            if ($extension === 'eml') {
                return $this->extractDateFromEml($fullPath);
            } elseif ($extension === 'msg') {
                return $this->extractDateFromMsg($fullPath);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to extract date from email file', [
                'path' => $filePath,
                'extension' => $extension,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Extract date from .eml file
     */
    private function extractDateFromEml(string $filePath): ?Carbon
    {
        try {
            $content = file_get_contents($filePath);

            $patterns = [
                '/Date:\s*(.+?)(?:\r\n|\n)/i',
                '/date:\s*(.+?)(?:\r\n|\n)/i',
                '/^Date:\s*(.+)$/im',
                '/^date:\s*(.+)$/im',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    $dateString = trim($matches[1]);
                    $dateString = preg_replace('/\(.*\)/', '', $dateString);
                    $dateString = preg_replace('/\s+\([^)]+\)/', '', $dateString);
                    $dateString = trim($dateString);

                    try {
                        return Carbon::parse($dateString);
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }

            if (preg_match('/(Mon|Tue|Wed|Thu|Fri|Sat|Sun),\s+\d+\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}\s+\d{1,2}:\d{2}:\d{2}/', $content, $matches)) {
                try {
                    return Carbon::parse($matches[0]);
                } catch (\Exception $e) {
                }
            }

        } catch (\Exception $e) {
            Log::warning('Failed to extract date from .eml', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Extract date from .msg file
     */
    private function extractDateFromMsg(string $filePath): ?Carbon
    {
        try {
            $content = file_get_contents($filePath);

            $datePatterns = [
                '/(\d{8}T\d{6})/',
                '/(\d{4}\d{2}\d{2}\d{2}\d{2}\d{2})/',
                '/(Mon|Tue|Wed|Thu|Fri|Sat|Sun),\s+\d+\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}\s+\d{1,2}:\d{2}:\d{2}/',
                '/(\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}:\d{2}\s+[AP]M)/i',
                '/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/',
            ];

            foreach ($datePatterns as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    $dateString = $matches[1];

                    try {
                        if (preg_match('/^(\d{8})T(\d{6})$/', $dateString, $isoMatch)) {
                            $formatted = substr($isoMatch[1], 0, 4) . '-' .
                                substr($isoMatch[1], 4, 2) . '-' .
                                substr($isoMatch[1], 6, 2) . ' ' .
                                substr($isoMatch[2], 0, 2) . ':' .
                                substr($isoMatch[2], 2, 2) . ':' .
                                substr($isoMatch[2], 4, 2);
                            return Carbon::parse($formatted);
                        }

                        if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $dateString, $tsMatch)) {
                            $formatted = "{$tsMatch[1]}-{$tsMatch[2]}-{$tsMatch[3]} {$tsMatch[4]}:{$tsMatch[5]}:{$tsMatch[6]}";
                            return Carbon::parse($formatted);
                        }

                        return Carbon::parse($dateString);
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }

            $readableContent = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $content);

            if (preg_match('/Date[^:]*:\s*(.+?)(?:\r\n|\n)/i', $readableContent, $matches)) {
                $dateString = trim($matches[1]);
                try {
                    return Carbon::parse($dateString);
                } catch (\Exception $e) {
                }
            }

            $fileTime = filemtime($filePath);
            return Carbon::createFromTimestamp($fileTime);

        } catch (\Exception $e) {
            Log::warning('Failed to extract date from .msg file', [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract field from .msg file
     */
    private function extractMsgField(string $content, string $field): string
    {
        $readableContent = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $content);

        $patterns = [
            'subject' => '/subject[^:]*:\s*([^\r\n]+)/i',
            'from'    => '/from[^:]*:\s*([^\r\n]+)/i',
            'to'      => '/to[^:]*:\s*([^\r\n]+)/i',
            'cc'      => '/cc[^:]*:\s*([^\r\n]+)/i',
            'bcc'     => '/bcc[^:]*:\s*([^\r\n]+)/i',
            'date'    => '/date[^:]*:\s*([^\r\n]+)/i',
        ];

        if (isset($patterns[$field]) && preg_match($patterns[$field], (string) $readableContent, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * Extract body from .msg file
     */
    private function extractMsgBody(string $content): string
    {
        $readableContent = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $content);

        if (preg_match('/\r\n\r\n(.*)/s', (string) $readableContent, $matches)) {
            $body = trim($matches[1]);
            $body = preg_replace('/\s+/', ' ', (string) $body);
            $body = trim((string) $body);

            if (strlen($body) > 5000) {
                $body = substr($body, 0, 5000) . '... [truncated]';
            }

            return $body ?: '[Could not extract body from .msg file]';
        }

        return '[Could not extract body from .msg file]';
    }

    /**
     * Parse email file and extract information
     */
    private function parseEmailFile(string $fullPath, string $extension): array
    {
        try {
            if (!file_exists($fullPath)) {
                return ['error' => 'File not found'];
            }

            if ($extension === 'eml') {
                return $this->parseEmlFile($fullPath);
            }

            if ($extension === 'msg') {
                return $this->parseMsgFileForView($fullPath);
            }

            return ['error' => 'Unsupported email extension'];

        } catch (\Throwable $e) {
            Log::error('Failed to parse email file', [
                'file' => $fullPath,
                'extension' => $extension,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Failed to parse email: ' . $e->getMessage()];
        }
    }

    /**
     * Parse .eml file with proper MIME parsing
     */
    private function parseEmlFile(string $filePath): array
    {
        try {
            if (class_exists(\PhpMimeMailParser\Parser::class)) {
                $parser = new MimeMailParser();
                $parser->setPath($filePath);

                return [
                    'headers' => $parser->getHeaders(),
                    'subject' => $parser->getHeader('subject'),
                    'from' => $parser->getHeader('from'),
                    'to' => $parser->getHeader('to'),
                    'cc' => $parser->getHeader('cc'),
                    'bcc' => $parser->getHeader('bcc'),
                    'date' => $parser->getHeader('date'),
                    'body' => $parser->getMessageBody('text'),
                    'html_body' => $parser->getMessageBody('html'),
                    'attachments' => array_values(array_map(function ($attachment) {
                        return [
                            'name' => $attachment->getFilename(),
                            'type' => $attachment->getContentType(),
                            'size' => method_exists($attachment, 'getSize') ? $attachment->getSize() : null,
                        ];
                    }, $parser->getAttachments())),
                    'has_attachments' => count($parser->getAttachments()) > 0,
                    'parsed_successfully' => true,
                ];
            }

            return $this->parseEmlManually($filePath);

        } catch (\Throwable $e) {
            Log::error('Failed to parse .eml file', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return $this->parseEmlManually($filePath);
        }
    }

    /**
     * Manual .eml parsing (fallback)
     */
    private function parseEmlManually(string $filePath): array
    {
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \RuntimeException('Unable to read file');
            }

            $parts = preg_split("/\r?\n\r?\n/", $content, 2);
            $headersRaw = $parts[0] ?? '';
            $bodyRaw = $parts[1] ?? '';

            $parsedHeaders = $this->parseEmailHeaders($headersRaw);
            $extractedBody = $this->extractEmailBody($bodyRaw, $parsedHeaders);

            return [
                'headers' => $parsedHeaders,
                'subject' => $parsedHeaders['subject'] ?? '',
                'from' => $parsedHeaders['from'] ?? '',
                'to' => $parsedHeaders['to'] ?? '',
                'cc' => $parsedHeaders['cc'] ?? '',
                'bcc' => $parsedHeaders['bcc'] ?? '',
                'date' => isset($parsedHeaders['date']) ? $this->parseEmailDate($parsedHeaders['date']) : null,
                'body' => $extractedBody['text'],
                'html_body' => $extractedBody['html'],
                'attachments' => $extractedBody['attachments'],
                'has_attachments' => !empty($extractedBody['attachments']),
                'parsed_successfully' => true,
            ];
        } catch (\Throwable $e) {
            Log::error('Manual .eml parsing failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'headers' => [],
                'subject' => '',
                'from' => '',
                'to' => '',
                'cc' => '',
                'bcc' => '',
                'date' => null,
                'body' => 'Failed to parse email content.',
                'html_body' => '',
                'attachments' => [],
                'has_attachments' => false,
                'parsed_successfully' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse .msg file for view.
     *
     * Priority order:
     *   1. HFIG MAPI — dedicated OLE compound document parser for Outlook MSG.
     *      Handles complex Exchange messages, embedded attachments, etc.
     *   2. Webklex PHPIMAP — works for some simpler MSG files.
     *   3. Manual text extraction — last resort, lossy.
     */
    private function parseMsgFileForView(string $filePath): array
    {
        try {
            // --- Try 1: HFIG MAPI (best parser for Outlook .msg) ---
            if (class_exists(\Hfig\MAPI\OLE\Pear\DocumentFactory::class)) {
                try {
                    $factory = new \Hfig\MAPI\OLE\Pear\DocumentFactory();
                    $ole = $factory->createFromFile($filePath);
                    $msg = new \Hfig\MAPI\Message\Message($ole);

                    $props = $msg->properties;
                    $subject = (string) ($props['subject'] ?? '');
                    $senderName = (string) ($props['sender_name'] ?? '');
                    $senderEmail = (string) ($props['sender_email_address'] ?? '');
                    $from = $senderEmail !== ''
                        ? ($senderName !== '' ? "$senderName <$senderEmail>" : $senderEmail)
                        : $senderName;

                    // Separate TO, CC, BCC recipients.
                    $to = [];
                    $cc = [];
                    $bcc = [];
                    foreach ($msg->getRecipients() as $r) {
                        $name = (string) ($r->properties['display_name'] ?? '');
                        $email = (string) ($r->properties['email_address'] ?? '');
                        // Clean up Exchange DN addresses.
                        if (str_starts_with(strtolower($email), '/o=')) {
                            $email = (string) ($r->properties['smtp_address'] ?? $email);
                        }
                        $display = $email !== ''
                            ? ($name !== '' ? "$name <$email>" : $email)
                            : $name;
                        $type = (int) ($r->properties['recipient_type'] ?? 1);
                        match ($type) {
                            2 => $cc[] = $display,
                            3 => $bcc[] = $display,
                            default => $to[] = $display,
                        };
                    }

                    $body = (string) ($msg->getBody() ?? '');
                    $htmlBody = (string) ($msg->getBodyHTML() ?? '');

                    $attachments = [];
                    foreach ($msg->getAttachments() as $att) {
                        $attachments[] = [
                            'name' => $att->getFilename(),
                            'type' => $att->getMimeType() ?? 'application/octet-stream',
                            'size' => strlen($att->getData()),
                        ];
                    }

                    $date = $props['message_delivery_time']
                        ?? $props['client_submit_time']
                        ?? $props['creation_time']
                        ?? null;

                    return [
                        'headers' => [
                            'subject' => $subject,
                            'from' => $from,
                            'to' => implode(', ', $to),
                            'cc' => implode(', ', $cc),
                            'bcc' => implode(', ', $bcc),
                            'date' => $date,
                        ],
                        'subject' => $subject,
                        'from' => $from,
                        'to' => implode(', ', $to),
                        'cc' => implode(', ', $cc),
                        'bcc' => implode(', ', $bcc),
                        'date' => $date ? Carbon::parse($date)->toDateTimeString() : null,
                        'body' => $body,
                        'html_body' => $htmlBody,
                        'attachments' => $attachments,
                        'has_attachments' => count($attachments) > 0,
                        'parsed_successfully' => true,
                    ];
                } catch (\Throwable $e) {
                    Log::warning('HFIG MAPI .msg parser failed, trying Webklex', [
                        'file' => $filePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // --- Try 2: Webklex PHPIMAP ---
            if (class_exists(\Webklex\PHPIMAP\Message::class)) {
                try {
                    $message = \Webklex\PHPIMAP\Message::fromFile($filePath);

                    $safeAddr = fn ($collection) => (($first = $collection->first()) && $first !== false)
                        ? $first->mail ?? ''
                        : '';
                    $joinAddrs = fn ($collection) => $collection->count() > 0
                        ? implode(', ', array_filter(array_map(
                            fn ($a) => is_object($a) ? ($a->mail ?? '') : '',
                            $collection->all()
                        )))
                        : '';

                    $from = $safeAddr($message->getFrom());
                    $subject = $message->getSubject() ?? '';

                    // Only trust this result if it actually extracted the subject.
                    if ($subject !== '' && $from !== '') {
                        return [
                            'headers' => [
                                'subject' => $subject,
                                'from' => $from,
                                'to' => $joinAddrs($message->getTo()),
                                'cc' => $joinAddrs($message->getCc()),
                                'bcc' => $joinAddrs($message->getBcc()),
                                'date' => $message->getDate(),
                            ],
                            'subject' => $subject,
                            'from' => $from,
                            'to' => $joinAddrs($message->getTo()),
                            'cc' => $joinAddrs($message->getCc()),
                            'bcc' => $joinAddrs($message->getBcc()),
                            'date' => $message->getDate() ? Carbon::parse($message->getDate())->toDateTimeString() : null,
                            'body' => $message->getTextBody() ?? $message->getHTMLBody() ?? '',
                            'html_body' => $message->getHTMLBody() ?? '',
                            'attachments' => array_map(function ($attachment) {
                                return [
                                    'name' => $attachment->getName(),
                                    'type' => $attachment->getMimeType(),
                                    'size' => $attachment->getSize(),
                                ];
                            }, $message->getAttachments()->all()),
                            'has_attachments' => $message->getAttachments()->count() > 0,
                            'parsed_successfully' => true,
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Webklex .msg parser failed, falling back to text extraction', [
                        'file' => $filePath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // --- Try 3: Manual text extraction (last resort) ---
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \RuntimeException('Unable to read MSG file');
            }

            $subject = $this->extractMsgField($content, 'subject');
            $from    = $this->extractMsgField($content, 'from');
            $to      = $this->extractMsgField($content, 'to');
            $date    = $this->extractMsgDate($content);
            $body    = $this->extractMsgBody($content);

            return [
                'headers' => [
                    'subject' => $subject,
                    'from' => $from,
                    'to' => $to,
                    'date' => $date,
                ],
                'subject' => $subject,
                'from' => $from,
                'to' => $to,
                'cc' => $this->extractMsgField($content, 'cc'),
                'bcc' => $this->extractMsgField($content, 'bcc'),
                'date' => $date,
                'body' => $body,
                'html_body' => '',
                'attachments' => [],
                'has_attachments' => false,
                'parsed_successfully' => !empty($subject) || !empty($from) || !empty($to) || !empty($body),
            ];

        } catch (\Throwable $e) {
            Log::error('Failed to parse .msg file for view', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'headers' => [],
                'subject' => '',
                'from' => '',
                'to' => '',
                'cc' => '',
                'bcc' => '',
                'date' => null,
                'body' => 'Unable to parse .msg file. This is a binary Outlook Message file.',
                'html_body' => '',
                'attachments' => [],
                'has_attachments' => false,
                'parsed_successfully' => false,
            ];
        }
    }

    /**
     * Extract date from .msg file
     */
    private function extractMsgDate(string $content): ?string
    {
        $readableContent = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $content);

        $patterns = [
            '/Date[^:]*:\s*([^\r\n]+)/i',
            '/date[^:]*:\s*([^\r\n]+)/i',
            '/(\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}:\d{2}\s+[AP]M)/i',
            '/(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})/',
            '/(Mon|Tue|Wed|Thu|Fri|Sat|Sun),\s+\d+\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+\d{4}\s+\d{1,2}:\d{2}:\d{2}/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, (string) $readableContent, $matches)) {
                try {
                    $dateString = trim($matches[1]);
                    return Carbon::parse($dateString)->toDateTimeString();
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Parse email date string
     */
    private function parseEmailDate(string $dateString): ?string
    {
        try {
            $dateString = preg_replace('/\(.*\)/', '', $dateString);
            $dateString = trim($dateString);
            return Carbon::parse($dateString)->toDateTimeString();
        } catch (\Throwable $e) {
            return $dateString;
        }
    }

    /**
     * Parse email headers
     */
    private function parseEmailHeaders(string $headers): array
    {
        $parsed = [];
        $lines = preg_split("/\r\n|\n|\r/", $headers) ?: [];

        $currentKey = '';
        foreach ($lines as $line) {
            if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
                $currentKey = strtolower(trim($matches[1]));
                $parsed[$currentKey] = trim($matches[2]);
            } elseif ($currentKey && (str_starts_with($line, ' ') || str_starts_with($line, "\t"))) {
                $parsed[$currentKey] .= ' ' . trim($line);
            }
        }

        return $parsed;
    }

    /**
     * Extract email body from content
     */
    private function extractEmailBody(string $content, array $headers): array
    {
        $result = [
            'text' => '',
            'html' => '',
            'attachments' => [],
        ];

        $contentType = $headers['content-type'] ?? '';

        if (strpos($contentType, 'multipart/') === 0) {
            $boundary = '';

            if (preg_match('/boundary="([^"]+)"/', $contentType, $matches)) {
                $boundary = $matches[1];
            } elseif (preg_match('/boundary=([^\s;]+)/', $contentType, $matches)) {
                $boundary = $matches[1];
            }

            if ($boundary) {
                $parts = explode("--{$boundary}", $content);

                foreach ($parts as $part) {
                    $part = trim($part);
                    if (empty($part) || $part === '--') {
                        continue;
                    }

                    $partParts = preg_split("/\r?\n\r?\n/", $part, 2);
                    $partHeadersRaw = $partParts[0] ?? '';
                    $partBody = $partParts[1] ?? '';

                    $partHeadersRaw = str_replace(["\r\n", "\r"], "\n", $partHeadersRaw);
                    $partHeadersRaw = str_replace("\n", "\r\n", $partHeadersRaw);
                    $partHeaders = $this->parseEmailHeaders($partHeadersRaw);

                    $partContentType = $partHeaders['content-type'] ?? '';
                    $disposition = $partHeaders['content-disposition'] ?? '';

                    if (stripos($disposition, 'attachment') === 0) {
                        $filename = '';

                        if (preg_match('/filename="([^"]+)"/i', $disposition, $matches)) {
                            $filename = $matches[1];
                        } elseif (preg_match('/name="([^"]+)"/i', $partContentType, $matches)) {
                            $filename = $matches[1];
                        }

                        if ($filename) {
                            $result['attachments'][] = [
                                'name' => $filename,
                                'type' => $partContentType,
                                'size' => strlen($partBody),
                            ];
                        }
                    } elseif (stripos($partContentType, 'text/plain') === 0) {
                        $result['text'] = $partBody;
                    } elseif (stripos($partContentType, 'text/html') === 0) {
                        $result['html'] = $partBody;
                    }
                }
            }
        } else {
            if (stripos($contentType, 'text/plain') === 0) {
                $result['text'] = $content;
            } elseif (stripos($contentType, 'text/html') === 0) {
                $result['html'] = $content;
            } else {
                $result['text'] = $content;
            }
        }

        if (empty($result['text']) && !empty($result['html'])) {
            $result['text'] = strip_tags($result['html']);
        }

        return $result;
    }

    /**
     * Get raw email content for display
     */
    private function getRawEmailContent(string $filePath, string $extension): string
    {
        try {
            if (!file_exists($filePath)) {
                return 'File not found';
            }

            $content = file_get_contents($filePath);
            if ($content === false) {
                return 'Unable to read file';
            }

            if ($extension === 'msg') {
                // MSG files are binary; show a readable fallback to avoid invalid UTF-8 payloads.
                $content = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', ' ', $content) ?? '';
            }

            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            }

            if (strlen($content) > 10000) {
                $content = mb_substr($content, 0, 10000) . "\n\n... [Content truncated for preview]";
            }

            return (string) $content;
        } catch (\Throwable $e) {
            return 'Error reading file: ' . $e->getMessage();
        }
    }

    /**
     * Determine upload status based on which files are uploaded
     */
    private function determineUploadStatus(bool $hasOriginal, bool $hasWorkings, bool $hasSystemsImport): string
    {
        if ($hasOriginal && $hasWorkings && $hasSystemsImport) {
            return 'Completed';
        }

        if ($hasOriginal && $hasWorkings && !$hasSystemsImport) {
            return 'Pending';
        }

        if ($hasOriginal && !$hasWorkings && !$hasSystemsImport) {
            return 'Pending';
        }

        if ($hasOriginal && !$hasWorkings && $hasSystemsImport) {
            return 'Processing';
        }

        return 'Rejected';
    }

    /**
     * Send notifications with status information
     */
    private function sendUploadNotifications(User $user, array $uploads, Municipality $municipality, $companies, string $status): void
    {
        try {
            $user->notify(new \App\Notifications\UploadCreated(
                $uploads[0],
                $municipality,
                $companies->first(),
                1,
                $status
            ));

            $admins = User::role('admin')->get();
            Notification::send($admins, new \App\Notifications\NewUploadNotification(
                $user,
                $municipality,
                1,
                $status
            ));

        } catch (\Throwable $e) {
            Log::error('Upload notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Clean up uploaded files if transaction fails
     */
    private function cleanupUploadedFiles(array $filesData): void
    {
        try {
            if (!empty($filesData['workings_file_path'])) {
                Storage::disk('private')->delete($filesData['workings_file_path']);
            }

            if (!empty($filesData['systems_import_file_path'])) {
                Storage::disk('private')->delete($filesData['systems_import_file_path']);
            }

            if (!empty($filesData['original_file_paths'])) {
                foreach ($filesData['original_file_paths'] as $path) {
                    Storage::disk('private')->delete($path);
                }
            }

            if (!empty($filesData['converted_eml_paths'])) {
                foreach ($filesData['converted_eml_paths'] as $path) {
                    if ($path) {
                        Storage::disk('private')->delete($path);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('File cleanup failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete upload and associated files
     */
    public function destroy(Uploads $upload)
    {
        $this->authorize('delete upload');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'You do not have permission to delete this upload.');
        }

        DB::beginTransaction();

        try {
            if ($upload->original_file_path) {
                $originalFiles = is_array($upload->original_file_path)
                    ? $upload->original_file_path
                    : json_decode($upload->original_file_path, true);

                if (is_array($originalFiles)) {
                    foreach ($originalFiles as $filePath) {
                        if ($filePath) {
                            Storage::disk('private')->delete($filePath);
                        }
                    }
                }
            }

            if ($upload->workings_file_path) {
                Storage::disk('private')->delete($upload->workings_file_path);
            }

            if ($upload->systems_import_file_path) {
                Storage::disk('private')->delete($upload->systems_import_file_path);
            }

            if ($upload->converted_eml_paths) {
                $convertedFiles = is_array($upload->converted_eml_paths)
                    ? $upload->converted_eml_paths
                    : json_decode($upload->converted_eml_paths ?? '[]', true);

                if (is_array($convertedFiles)) {
                    foreach ($convertedFiles as $filePath) {
                        if ($filePath) {
                            Storage::disk('private')->delete($filePath);
                        }
                    }
                }
            }

            $upload->delete();
            DB::commit();

            return redirect()->route('uploads.index')->with('success', 'Upload deleted successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete upload: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to delete upload.']);
        }
    }

    /**
     * Display uploads history - USER SPECIFIC
     */
    public function history(Request $request, CaseyPremiumBatchService $caseyService)
    {
        $this->authorize('view uploads');

        $user = $request->user();
        $perPage = $request->get('per_page', 20);

        $allowedPerPage = [20, 50, 100, 200, 500];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 20;
        }

        $query = Uploads::query()
            ->where('user_id', $user->id)
            ->with(['company:id,name', 'municipality:id,name', 'user:id,name,email'])
            ->when($request->status, fn ($q, $s) => $q->where('caps_dispatch_status', $s))
            ->when($request->search, function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('reference', 'like', "%$s%")
                        ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%$s%"))
                        ->orWhereHas('municipality', fn ($m) => $m->where('name', 'like', "%$s%"))
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%$s%"));
                });
            })
            ->latest('submitted_at');

        $paginated = $query->paginate($perPage)->withQueryString();

        $uploads = $paginated->through(function (Uploads $u) {
            $origNames = is_array($u->original_file_names)
                ? $u->original_file_names
                : (json_decode($u->original_file_names ?? '[]', true) ?: []);
            $origPaths = is_array($u->original_file_path)
                ? $u->original_file_path
                : (json_decode($u->original_file_path ?? '[]', true) ?: []);

            $originalUrls = [];
            foreach ($origPaths as $i => $path) {
                $originalUrls[] = route('uploads.download', ['upload' => $u->id, 'which' => 'original', 'index' => $i]);
            }

            $previewUrls = [];
            $emailPreviewDataUrls = [];
            foreach ($origNames as $i => $name) {
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (in_array($extension, ['eml', 'msg'], true)) {
                    $previewUrls[$i] = route('uploads.view-email', ['upload' => $u->id, 'index' => $i]);
                    $emailPreviewDataUrls[$i] = route('uploads.view-email-data', ['upload' => $u->id, 'index' => $i]);
                } elseif (in_array($extension, ['xls', 'xlsx', 'xlsm', 'xlsb', 'csv'], true)) {
                    $previewUrls[$i] = route('uploads.preview', ['upload' => $u->id, 'index' => $i]);
                }
            }

            $allPreviewableFiles = $this->buildAllPreviewableFiles($u, $previewUrls);

            return [
                'id' => $u->id,
                'reference' => $u->reference,
                'status' => $u->status,
                'company' => $u->company ? ['id' => $u->company->id, 'name' => $u->company->name] : null,
                'municipality' => $u->municipality ? ['id' => $u->municipality->id, 'name' => $u->municipality->name] : null,
                'user' => $u->user ? [
                    'id' => $u->user->id,
                    'name' => $u->user->name,
                    'email' => $u->user->email,
                    'initials' => $this->getUserInitials($u->user->name),
                ] : null,
                'original_file_names' => $origNames,
                'original_file_urls' => $originalUrls,
                'preview_urls' => $previewUrls,
                'email_preview_data_urls' => $emailPreviewDataUrls,
                'all_previewable_files' => $allPreviewableFiles,
                'workings_file_name' => $u->workings_file_name,
                'workings_file_url' => $u->workings_file_path
                    ? route('uploads.download', ['upload' => $u->id, 'which' => 'workings'])
                    : null,
                'systems_import_file_name' => $u->systems_import_file_name,
                'systems_import_file_url' => $u->systems_import_file_path
                    ? route('uploads.download', ['upload' => $u->id, 'which' => 'systems'])
                    : null,
                'extracted_dates' => is_array($u->extracted_dates)
                    ? $u->extracted_dates
                    : (json_decode($u->extracted_dates ?? '[]', true) ?: []),
                'system_import_date' => optional($u->system_import_date)->toDateString(),
                'system_import_date_formatted' => $u->system_import_date ? $u->system_import_date->format('Y-m-d H:i:s') : null,
                'system_import_date_human' => $u->system_import_date ? $u->system_import_date->diffForHumans() : null,
                'submitted_at' => optional($u->submitted_at)->toDateString(),
                'submitted_at_formatted' => $u->submitted_at ? $u->submitted_at->format('Y-m-d H:i:s') : null,
                'submitted_at_human' => $u->submitted_at ? $u->submitted_at->diffForHumans() : null,
                'created_at' => $u->created_at ? $u->created_at->format('Y-m-d H:i:s') : null,
                'created_at_human' => $u->created_at ? $u->created_at->diffForHumans() : null,
                'reupload_reason_type' => $u->reupload_reason_type,
                'reupload_reason_note' => $u->reupload_reason_note,
                'caps_verification' => $u->caps_verification,
                'caps_verified_at' => $u->caps_verified_at?->format('Y-m-d H:i:s'),
                'caps_verified_at_human' => $u->caps_verified_at?->diffForHumans(),
                // CAPS dispatch fields
                'caps_dispatch_status' => $u->caps_dispatch_status ?? 'draft',
                'caps_payment_batch_id' => $u->caps_payment_batch_id,
                'caps_batch_type' => $u->caps_batch_type,
                'caps_status' => $u->caps_status,
                'caps_summary' => $u->caps_summary,
                'caps_errors' => $u->caps_errors,
                'caps_dispatched_at' => $u->caps_dispatched_at?->format('Y-m-d H:i:s'),
                'caps_retry_count' => $u->caps_retry_count ?? 0,
                'can_dispatch_to_caps' => $u->canDispatchToCaps(),
                'can_retry_caps' => $u->canRetryDispatch(),
            ];
        });

        $policyBatchId = (int) $request->query(
            'policy_batch_id',
            (int) config('services.casey.premium_batch_id', 5239)
        );
        if ($policyBatchId < 1) {
            $policyBatchId = 5239;
        }

        // Summary counts for the header
        $needsReview = Uploads::where('user_id', $user->id)->where('caps_dispatch_status', 'caps_processing')->count();
        $saved = Uploads::where('user_id', $user->id)->where('caps_dispatch_status', 'completed')->count();
        $failed = Uploads::where('user_id', $user->id)->where('caps_dispatch_status', 'failed')->count();
        $totalUploads = Uploads::where('user_id', $user->id)->count();

        return Inertia::render('Uploads/History', [
            'filters' => $request->only(['status', 'search']),
            'uploads' => $uploads,
            'statusOptions' => ['Pending', 'Processing', 'Completed', 'Rejected'],
            'perPageOptions' => [20, 50, 100, 200, 500],
            'currentPerPage' => (int)$perPage,
            'counts' => [
                'total' => $totalUploads,
                'needs_review' => $needsReview,
                'saved' => $saved,
                'failed' => $failed,
            ],
        ]);
    }

    /**
     * Returns a flat list of every previewable file across all three upload
     * slots (original emails, workings spreadsheet, systems-import CSV).
     * Each entry has { label, name, url, type } so the frontend can render
     * a dropdown without knowing the slot structure.
     */
    private function buildAllPreviewableFiles(Uploads $u, array $originalPreviewUrls): array
    {
        $files = [];

        // Original files (emails / spreadsheets).
        $origNames = is_array($u->original_file_names)
            ? $u->original_file_names
            : (json_decode($u->original_file_names ?? '[]', true) ?: []);
        foreach ($origNames as $i => $name) {
            if (isset($originalPreviewUrls[$i])) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $isEmail = in_array($ext, ['eml', 'msg'], true);
                $files[] = [
                    'label' => 'Original Email',
                    'name' => $name,
                    'url' => $originalPreviewUrls[$i],
                    'data_url' => $isEmail
                        ? route('uploads.view-email-data', ['upload' => $u->id, 'index' => $i])
                        : route('uploads.preview-data', ['upload' => $u->id, 'index' => $i]),
                    'type' => $isEmail ? 'email' : 'spreadsheet',
                ];
            }
        }

        // Workings spreadsheet.
        if ($u->workings_file_name && $u->workings_file_path) {
            $ext = strtolower(pathinfo($u->workings_file_name, PATHINFO_EXTENSION));
            $previewable = in_array($ext, ['xls', 'xlsx', 'xlsm', 'xlsb', 'csv', 'ods'], true);
            $files[] = [
                'label' => 'Workings',
                'name' => $u->workings_file_name,
                'url' => $previewable
                    ? route('uploads.preview', ['upload' => $u->id, 'index' => 'workings'])
                    : route('uploads.download', ['upload' => $u->id, 'which' => 'workings']),
                'data_url' => $previewable
                    ? route('uploads.preview-data', ['upload' => $u->id, 'index' => 'workings'])
                    : null,
                'type' => $previewable ? 'spreadsheet' : 'download',
            ];
        }

        // Systems-import CSV.
        if ($u->systems_import_file_name && $u->systems_import_file_path) {
            $ext = strtolower(pathinfo($u->systems_import_file_name, PATHINFO_EXTENSION));
            $previewable = in_array($ext, ['xls', 'xlsx', 'xlsm', 'xlsb', 'csv', 'ods'], true);
            $files[] = [
                'label' => 'Systems Import',
                'name' => $u->systems_import_file_name,
                'url' => $previewable
                    ? route('uploads.preview', ['upload' => $u->id, 'index' => 'systems'])
                    : route('uploads.download', ['upload' => $u->id, 'which' => 'systems']),
                'data_url' => $previewable
                    ? route('uploads.preview-data', ['upload' => $u->id, 'index' => 'systems'])
                    : null,
                'type' => $previewable ? 'spreadsheet' : 'download',
            ];
        }

        return $files;
    }

    private function withAttachmentLinks(array $emailData, int $uploadId, int $fileIndex): array
    {
        if (empty($emailData['attachments']) || !is_array($emailData['attachments'])) {
            $emailData['attachments'] = [];
            $emailData['has_attachments'] = false;
            return $emailData;
        }

        $emailData['attachments'] = array_values(array_map(function ($attachment, $idx) use ($uploadId, $fileIndex) {
            $attachment = is_array($attachment) ? $attachment : [];
            $attachment['download_url'] = route('uploads.view-email.attachment', [
                'upload' => $uploadId,
                'index' => $fileIndex,
                'attachmentIndex' => $idx,
            ]);
            return $attachment;
        }, $emailData['attachments'], array_keys($emailData['attachments'])));

        $emailData['has_attachments'] = count($emailData['attachments']) > 0;
        return $emailData;
    }

    /**
     * Export uploads to Excel - USER SPECIFIC
     */
    public function export(Request $request)
    {
        $this->authorize('export uploads');

        $user = $request->user();

        $uploads = Uploads::where('user_id', $user->id)
            ->with(['company', 'municipality', 'user'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, function ($q, $s) {
                $q->where('reference', 'like', "%$s%")
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%$s%"))
                    ->orWhereHas('municipality', fn ($m) => $m->where('name', 'like', "%$s%"))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%$s%"));
            })
            ->latest('submitted_at')
            ->get();

        return Excel::download(new UploadsExports($uploads), 'uploads-history-' . now()->format('Y-m-d') . '.xlsx');
    }

    /**
     * Get existing uploads for a municipality - USER SPECIFIC
     */
    public function existingUploads(Request $request, Municipality $municipality): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view uploads');

        $user = $request->user();
        $since = now()->subDays(90);

        $existingUploads = Uploads::where('user_id', $user->id)
            ->where('municipality_id', $municipality->id)
            ->where('submitted_at', '>=', $since)
            ->select('company_id', 'submitted_at', 'reference')
            ->orderBy('submitted_at', 'desc')
            ->get();

        $existingCompanyIds = $existingUploads->pluck('company_id')
            ->unique()
            ->values()
            ->all();

        $details = [];
        foreach ($existingUploads as $upload) {
            if (!isset($details[$upload->company_id])) {
                $details[$upload->company_id] = [
                    'last_upload_date' => $upload->submitted_at->toDateTimeString(),
                    'last_reference' => $upload->reference,
                    'upload_count' => 0,
                ];
            }
            $details[$upload->company_id]['upload_count']++;
        }

        return response()->json([
            'existing_company_ids' => $existingCompanyIds,
            'details' => $details,
            'since' => $since->toDateString(),
            'total_companies_with_uploads' => count($existingCompanyIds),
        ]);
    }

    /**
     * Download upload file
     */
    public function download(Uploads $upload, string $which, ?int $index = null)
    {
        $this->authorize('view uploads');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403, 'You do not have permission to download this upload.');
        }

        $disk = Storage::disk('private');

        if ($which === 'original') {
            $paths = is_array($upload->original_file_path)
                ? $upload->original_file_path
                : (json_decode($upload->original_file_path ?? '[]', true) ?: []);
            $names = is_array($upload->original_file_names)
                ? $upload->original_file_names
                : (json_decode($upload->original_file_names ?? '[]', true) ?: []);
            if (!is_numeric($index) || !isset($paths[$index])) {
                abort(404);
            }
            $path = $paths[$index];
            $name = $names[$index] ?? basename($path);
            abort_unless($disk->exists($path), 404);
            return $disk->download($path, $name);
        }

        if ($which === 'workings') {
            $path = $upload->workings_file_path;
            $name = $upload->workings_file_name ?: basename($path ?? '');
            abort_unless($path && $disk->exists($path), 404);
            return $disk->download($path, $name);
        }

        if ($which === 'systems') {
            $path = $upload->systems_import_file_path;
            $name = $upload->systems_import_file_name ?: basename($path ?? '');
            abort_unless($path && $disk->exists($path), 404);
            return $disk->download($path, $name);
        }

        abort(404);
    }

    /**
     * Compare an upload's spreadsheet data against CAPS members & policies.
     *
     * Pulls member and policy records from CAPS for the upload's company,
     * then checks each row in the uploaded file to see if the member exists,
     * if the policy exists, and if premium amounts match.
     */
    /**
     * Automatically parse the uploaded spreadsheet and run CAPS verification.
     * Stores results on the upload record so History.vue can display them
     * without requiring a manual "Verify" click.
     */
    public function runAutoVerification(Uploads $upload, Company $company): void
    {
        try {
            if (!$company->casey_id) {
                Log::info('Auto-verification skipped: company has no casey_id', ['upload_id' => $upload->id]);
                return;
            }

            // Determine which spreadsheet to parse: prefer systems_import, fall back to workings
            $filePath = $upload->systems_import_file_path ?: $upload->workings_file_path;
            if (!$filePath) {
                Log::info('Auto-verification skipped: no spreadsheet file', ['upload_id' => $upload->id]);
                return;
            }

            $fullPath = storage_path('app/private/' . $filePath);
            if (!file_exists($fullPath)) {
                Log::warning('Auto-verification skipped: file not found', ['path' => $fullPath]);
                return;
            }

            // Parse the spreadsheet server-side
            $rows = $this->parseSpreadsheetForVerification($fullPath);
            if (empty($rows)) {
                Log::info('Auto-verification skipped: no parseable rows', ['upload_id' => $upload->id]);
                return;
            }

            $service = app(\App\Services\CaseyMemberPolicyService::class);
            $results = $service->compareAgainstCaps($rows, $company->casey_id);

            $upload->update([
                'caps_verification' => $results,
                'caps_verified_at' => now(),
            ]);

            Log::info('Auto-verification complete', [
                'upload_id' => $upload->id,
                'members_found' => count($results['member_found'] ?? []),
                'members_missing' => count($results['member_not_found'] ?? []),
                'policies_found' => count($results['policy_found'] ?? []),
                'policies_missing' => count($results['policy_not_found'] ?? []),
                'premium_mismatches' => count($results['premium_mismatch'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Auto-verification failed (non-blocking)', [
                'upload_id' => $upload->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse a spreadsheet file and extract rows for CAPS verification.
     * Maps column headers to standard field names using fuzzy matching.
     */
    private function parseSpreadsheetForVerification(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, false);

            if (count($data) < 2) return []; // Need header + at least one row

            // Normalize headers
            $rawHeaders = array_shift($data);
            $headerMap = [];
            $aliases = [
                'memberId' => ['memberid', 'idnumber', 'idno', 'id_number', 'id_no', 'identitynumber', 'said', 'rsaid'],
                'personelNumber' => ['personelnumber', 'personnelnumber', 'paynumber', 'employeenumber', 'empno', 'empnumber', 'employeeno', 'staffnumber', 'staffno', 'payrollnumber', 'personpaynumber'],
                'policyCode' => ['policycode', 'policynumber', 'policyno', 'policy_code', 'policy_number', 'policynbr', 'schemecode'],
                'premiumAmount' => ['premiumamount', 'premium', 'amountpayable', 'amount', 'totalamount', 'totalpremium', 'monthlypremium', 'deduction', 'deductionamount'],
                'companyName' => ['companyname', 'company', 'deductioncompany', 'insurancecompany', 'provider', 'companyname'],
            ];

            foreach ($rawHeaders as $colIdx => $rawHeader) {
                $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $rawHeader));
                foreach ($aliases as $field => $fieldAliases) {
                    if (in_array($normalized, $fieldAliases, true)) {
                        $headerMap[$colIdx] = $field;
                        break;
                    }
                }
            }

            if (empty($headerMap)) return []; // No recognizable columns

            $rows = [];
            foreach ($data as $rowData) {
                $row = [];
                foreach ($headerMap as $colIdx => $field) {
                    $value = $rowData[$colIdx] ?? null;
                    if ($field === 'premiumAmount') {
                        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);
                        $row[$field] = is_numeric($cleaned) ? round((float) $cleaned, 2) : null;
                    } else {
                        $row[$field] = trim((string) ($value ?? ''));
                    }
                }

                // Skip rows with no useful data
                $hasMember = !empty($row['memberId'] ?? '') || !empty($row['personelNumber'] ?? '');
                $hasPolicy = !empty($row['policyCode'] ?? '');
                if ($hasMember || $hasPolicy) {
                    $rows[] = $row;
                }
            }

            return $rows;
        } catch (\Throwable $e) {
            Log::warning('Spreadsheet parsing for verification failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function compareWithCaps(Request $request, Uploads $upload)
    {
        $this->authorize('view uploads');

        if ($upload->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403);
        }

        $company = $upload->company;
        if (!$company || !$company->casey_id) {
            return response()->json([
                'ok' => false,
                'message' => 'This company does not have a CAPS (casey_id) mapping. Run the reference-data sync first.',
                'results' => null,
            ]);
        }

        $uploadedRows = $request->validate([
            'rows' => 'required|array',
            'rows.*.memberId' => 'nullable|string',
            'rows.*.personelNumber' => 'nullable|string',
            'rows.*.policyCode' => 'nullable|string',
            'rows.*.premiumAmount' => 'nullable|numeric',
            'rows.*.companyName' => 'nullable|string',
        ])['rows'];

        $service = app(\App\Services\CaseyMemberPolicyService::class);
        $results = $service->compareAgainstCaps($uploadedRows, $company->casey_id);

        // Persist results on the upload record
        $upload->update([
            'caps_verification' => $results,
            'caps_verified_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'company_name' => $company->name,
            'casey_id' => $company->casey_id,
            'results' => $results,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  CAPS DISPATCH WORKFLOW
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Dispatch a completed upload to CAPS for processing.
     *
     * POST /uploads/{upload}/dispatch-to-caps
     */
    /**
     * Dispatch to CAPS: Preview → Import (validates headers, stages records).
     */
    public function dispatchToCaps(Uploads $upload)
    {
        $this->authorize('create upload');

        $user = auth()->user();
        if ($upload->user_id !== $user->id && !$user->hasRole(['admin', 'super-admin', 'superadmin'])) {
            abort(403);
        }

        $result = app(CapsSubmissionService::class)->previewOnCaps($upload);

        if ($result['ok']) {
            $s = $result['summary'] ?? [];
            $phase = $s['phase'] ?? 'unknown';
            $batchId = $s['caps_batch_id'] ?? $result['batch_id'] ?? null;
            $msg = $phase === 'imported'
                ? "CAPS imported {$s['total']} records into batch #{$batchId}. Review and confirm to save."
                : "CAPS preview: {$s['total']} records. " . (($s['can_submit'] ?? false) ? 'Ready to import.' : 'Fix header issues first.');
            return back()->with('success', $msg);
        }

        return back()->withErrors(['error' => $result['message'] ?? 'CAPS dispatch failed.']);
    }

    /**
     * Save/finalize an imported CAPS batch (Phase 4).
     */
    public function saveToCaps(Uploads $upload)
    {
        $this->authorize('create upload');

        $user = auth()->user();
        if ($upload->user_id !== $user->id && !$user->hasRole(['admin', 'super-admin', 'superadmin'])) {
            abort(403);
        }

        if (!$upload->caps_payment_batch_id) {
            return back()->withErrors(['error' => 'No CAPS batch to save. Dispatch first.']);
        }

        $result = app(CapsSubmissionService::class)->save($upload);

        if ($result['ok']) {
            try {
                $user->notifications()->create([
                    'type' => 'caps_saved',
                    'data' => json_encode([
                        'title' => 'Premiums saved to CAPS',
                        'message' => ($upload->company?->name ?? 'Upload') . ': Batch #' . $upload->caps_payment_batch_id . ' finalized.',
                        'upload_id' => $upload->id,
                        'batch_id' => $upload->caps_payment_batch_id,
                        'url' => route('uploads.caps-batch-detail', $upload->id),
                    ]),
                ]);
            } catch (\Throwable $e) {
                Log::warning('CAPS save notification failed: ' . $e->getMessage());
            }

            return back()->with('success', 'Batch #' . $upload->caps_payment_batch_id . ' saved to CAPS.');
        }

        return back()->withErrors(['error' => $result['message'] ?? 'CAPS save failed.']);
    }

    /**
     * Retry a failed CAPS dispatch.
     *
     * POST /uploads/{upload}/retry-caps
     */
    public function retryCapsDispatch(Uploads $upload)
    {
        $this->authorize('create upload');

        $user = auth()->user();

        if ($upload->user_id !== $user->id && !$user->hasRole(['admin', 'super-admin', 'superadmin'])) {
            abort(403);
        }

        if (!$upload->canRetryDispatch()) {
            return back()->withErrors([
                'error' => 'This upload cannot be retried. Current CAPS status: ' . $upload->caps_dispatch_status,
            ]);
        }

        $service = app(CapsSubmissionService::class);
        $result = $service->retry($upload);

        if ($result['ok']) {
            return back()->with('success', 'Retry dispatched to CAPS. Batch ID: ' . ($result['batch_id'] ?? 'pending'));
        }

        return back()->withErrors([
            'error' => $result['message'] ?? 'CAPS retry failed.',
        ]);
    }

    /**
     * Poll CAPS for the status of a dispatched batch (AJAX).
     *
     * POST /uploads/{upload}/poll-caps-status
     */
    public function pollCapsStatus(Uploads $upload)
    {
        $this->authorize('view uploads');

        if (!$upload->caps_payment_batch_id) {
            return response()->json(['ok' => false, 'message' => 'No CAPS batch ID linked.'], 422);
        }

        $service = app(CapsSubmissionService::class);
        $result = $service->pollStatus($upload);

        return response()->json(array_merge($result, [
            'upload_id' => $upload->id,
            'caps_dispatch_status' => $upload->fresh()->caps_dispatch_status,
            'caps_status' => $upload->fresh()->caps_status,
            'caps_summary' => $upload->fresh()->caps_summary,
            'caps_errors' => $upload->fresh()->caps_errors,
        ]));
    }

    /**
     * Show detailed CAPS batch information for an upload.
     *
     * GET /uploads/{upload}/caps-batch-detail
     */
    public function capsBatchDetail(Uploads $upload)
    {
        $this->authorize('view uploads');

        $user = auth()->user();
        if ($upload->user_id !== $user->id && !$user->hasRole(['admin', 'super-admin', 'superadmin'])) {
            abort(403);
        }

        $webhookEvents = $upload->capsWebhookEvents()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'status' => $e->status,
                'payments_batch_id' => $e->payments_batch_id,
                'created_at' => $e->created_at?->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('Uploads/CapsBatchDetail', [
            'upload' => [
                'id' => $upload->id,
                'reference' => $upload->reference,
                'status' => $upload->status,
                'company' => $upload->company ? ['id' => $upload->company->id, 'name' => $upload->company->name] : null,
                'municipality' => $upload->municipality ? ['id' => $upload->municipality->id, 'name' => $upload->municipality->name] : null,
                'caps_payment_batch_id' => $upload->caps_payment_batch_id,
                'caps_dispatch_status' => $upload->caps_dispatch_status,
                'caps_batch_type' => $upload->caps_batch_type,
                'caps_status' => $upload->caps_status,
                'caps_status_detail' => $upload->caps_status_detail,
                'caps_dispatched_at' => $upload->caps_dispatched_at?->format('Y-m-d H:i:s'),
                'caps_errors' => $upload->caps_errors,
                'caps_summary' => $upload->caps_summary,
                'caps_retry_count' => $upload->caps_retry_count,
                'caps_last_retry_at' => $upload->caps_last_retry_at?->format('Y-m-d H:i:s'),
                'caps_last_webhook_at' => $upload->caps_last_webhook_at?->format('Y-m-d H:i:s'),
                'caps_downloadable_outputs' => $upload->caps_downloadable_outputs,
                'caps_verification' => $upload->caps_verification,
                'caps_verified_at' => $upload->caps_verified_at?->format('Y-m-d H:i:s'),
                'can_dispatch' => $upload->canDispatchToCaps(),
                'can_retry' => $upload->canRetryDispatch(),
                'is_pending' => $upload->isCapsPending(),
            ],
            'webhookEvents' => $webhookEvents,
        ]);
    }
}
