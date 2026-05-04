<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Controllers
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\UploadsController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\MunicipalityController;
use App\Http\Controllers\MunicipalityDeadlineController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SupportTicketController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\CaseySsoController;

// Admin Controllers
use App\Http\Controllers\Admin\{
    UserController,
    RoleController,
    PermissionController,
    ReportController,
    AuditController,
    CapsDataSyncController
};

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Redirect root to dashboard (guests will get redirected to login)
Route::get('/', fn () => redirect()->route('dashboard'));

// =========================================================================
// AUTHENTICATION ROUTES
// =========================================================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

// Single sign-on bridge from CAPS. Lives outside the `guest` middleware so a
// user already logged in (e.g. switching tabs) can also re-bridge cleanly.
// Toggle with CASEY_SSO_ENABLED in the environment.
Route::match(['get', 'post'], '/auth/casey-sso', [CaseySsoController::class, 'login'])
    ->name('auth.casey.sso');

// Silent SSO logout — called from a hidden iframe when the user signs out
// of CAPS. Destroys the Tracker session and returns a minimal HTML response.
Route::get('/auth/casey-sso-logout', [CaseySsoController::class, 'silentLogout'])
    ->name('auth.casey.sso.logout');

// Minimal success page for silent SSO login — auto-closes the popup window.
Route::get('/auth/casey-sso-ok', fn () => response(
    '<html><body><script>window.close();</script>Signed in. You may close this window.</body></html>', 200)
    ->header('Content-Type', 'text/html')
    ->header('Cache-Control', 'no-store'))
    ->name('auth.casey.sso.ok');

// =========================================================================
// AUTHENTICATED ROUTES
// =========================================================================
Route::middleware('auth')->group(function () {

    // ---------------------------------------------------------------------
    // DASHBOARD
    // ---------------------------------------------------------------------
    Route::get('/dashboard', DashboardController::class)
        ->name('dashboard')
        ->middleware('permission:view dashboard');

    // ---------------------------------------------------------------------
    // SUBMISSIONS
    // ---------------------------------------------------------------------
    Route::get('/submissions', [SubmissionController::class, 'index'])
        ->name('submissions.index')
        ->middleware('permission:view submissions');

    Route::post('/submissions', [SubmissionController::class, 'store'])
        ->name('submissions.store')
        ->middleware('permission:create submissions');

    // =====================================================================
    // UPLOADS - COMPLETE ROUTE GROUP
    // =====================================================================

    // Resource routes for uploads
    Route::resource('uploads', UploadsController::class)->only([
        'index', 'store', 'destroy'
    ]);

    // Upload completion routes - MUST come before other parameter routes
    Route::get('/uploads/{upload}/complete', [UploadsController::class, 'showCompleteForm'])
        ->name('uploads.complete')
        ->middleware('permission:create upload');

    Route::post('/uploads/{upload}/complete', [UploadsController::class, 'complete'])
        ->name('uploads.complete.submit')
        ->middleware('permission:create upload');

    // Upload history and export
    Route::get('/uploads/history', [UploadsController::class, 'history'])
        ->name('uploads.history')
        ->middleware('permission:view uploads');

    Route::get('/uploads/export', [UploadsController::class, 'export'])
        ->name('uploads.export')
        ->middleware('permission:export uploads');

    // Check existing uploads for municipality
    Route::get('/uploads/existing/{municipality}', [UploadsController::class, 'existingUploads'])
        ->name('uploads.existing')
        ->middleware('permission:view uploads');

    // File download routes - specific routes first
    Route::get('/uploads/{upload}/download/{which}/{index?}', [UploadsController::class, 'download'])
        ->whereIn('which', ['original', 'workings', 'systems'])
        ->where('index', '[0-9]+')
        ->name('uploads.download')
        ->middleware('permission:view uploads');

    // File preview routes — index can be a numeric original-file index
    // OR the literal strings "workings" / "systems" for those file slots.
    Route::get('/uploads/{upload}/preview/{index?}', [UploadsController::class, 'preview'])
        ->where('index', '[0-9]+|workings|systems')
        ->name('uploads.preview')
        ->middleware('permission:view uploads');

    // JSON endpoint for inline spreadsheet preview (used by the popup modal).
    Route::get('/uploads/{upload}/preview-data/{index?}', [UploadsController::class, 'previewData'])
        ->where('index', '[0-9]+|workings|systems')
        ->name('uploads.preview-data')
        ->middleware('permission:view uploads');

    // Email view route
    Route::get('/uploads/{upload}/view-email/{index?}', [UploadsController::class, 'viewEmail'])
        ->name('uploads.view-email')
        ->where('index', '[0-9]+')
        ->middleware('permission:view uploads');

    Route::get('/uploads/{upload}/view-email-data/{index?}', [UploadsController::class, 'viewEmailData'])
        ->name('uploads.view-email-data')
        ->where('index', '[0-9]+')
        ->middleware('permission:view uploads');

    Route::get('/uploads/{upload}/view-email/{index}/attachments/{attachmentIndex}', [UploadsController::class, 'downloadEmailAttachment'])
        ->name('uploads.view-email.attachment')
        ->where('index', '[0-9]+')
        ->where('attachmentIndex', '[0-9]+')
        ->middleware('permission:view uploads');

    // MSG to EML conversion
    Route::get('/uploads/{upload}/convert-msg-to-eml/{index}', [UploadsController::class, 'convertMsgToEml'])
        ->name('uploads.convert-msg-to-eml')
        ->where('index', '[0-9]+')
        ->middleware('permission:view uploads');

    // CAPS member/policy comparison
    Route::post('/uploads/{upload}/compare-caps', [UploadsController::class, 'compareWithCaps'])
        ->name('uploads.compare-caps')
        ->middleware('permission:view uploads');

    // =====================================================================
    // DEADLINES - USER SPECIFIC
    // =====================================================================

    // Deadline views
    Route::get('/deadlines/municipalities', [MunicipalityDeadlineController::class, 'index'])
        ->name('deadlines.municipalities.index')
        ->middleware('permission:view deadlines');

    Route::get('/deadlines/companies', [MunicipalityDeadlineController::class, 'companies'])
        ->name('deadlines.companies.index')
        ->middleware('permission:view deadlines');

    Route::get('/deadlines/companies/{company}/submissions', [MunicipalityDeadlineController::class, 'companySubmissions'])
        ->name('deadlines.companies.submissions')
        ->middleware('permission:view deadlines');

    // Calendar events
    Route::get('/calendar/events', [MunicipalityDeadlineController::class, 'calendarEvents'])
        ->name('calendar.events')
        ->middleware('permission:view deadlines');

    // Deadline CRUD
    Route::post('/deadlines/municipalities', [MunicipalityDeadlineController::class, 'store'])
        ->name('deadlines.municipalities.store')
        ->middleware('permission:create deadline');

    Route::put('/deadlines/municipalities/{deadline}', [MunicipalityDeadlineController::class, 'update'])
        ->name('deadlines.municipalities.update')
        ->middleware('permission:edit deadline');

    Route::delete('/deadlines/municipalities/{deadline}', [MunicipalityDeadlineController::class, 'destroy'])
        ->name('deadlines.municipalities.destroy')
        ->middleware('permission:delete deadline');

    // Company assignment for deadlines
    Route::get('/deadlines/municipalities/{municipality}/companies', [MunicipalityDeadlineController::class, 'getMunicipalityCompanies'])
        ->name('deadlines.municipality.companies')
        ->middleware('permission:create deadline');

    // Deadline assignments
    Route::get('/deadlines/assignments', [MunicipalityDeadlineController::class, 'getAssignments'])
        ->name('deadlines.assignments.get')
        ->middleware('permission:view deadlines');

    Route::post('/deadlines/assignments', [MunicipalityDeadlineController::class, 'storeAssignment'])
        ->name('deadlines.assignments.store')
        ->middleware('permission:create deadline');

    Route::put('/deadlines/assignments/{assignment}', [MunicipalityDeadlineController::class, 'updateAssignment'])
        ->name('deadlines.assignments.update')
        ->middleware('permission:edit deadline');

    Route::delete('/deadlines/assignments/{assignment}', [MunicipalityDeadlineController::class, 'destroyAssignment'])
        ->name('deadlines.assignments.destroy')
        ->middleware('permission:delete deadline');

    // Combined deadline and assignment creation
    Route::post('/deadlines/create-with-assignments', [MunicipalityDeadlineController::class, 'createWithAssignments'])
        ->name('deadlines.create-with-assignments')
        ->middleware('permission:create deadline');

    // Sync assignments
    Route::get('/api/deadlines/sync-assignments', [MunicipalityDeadlineController::class, 'syncAssignments'])
        ->name('deadlines.assignments.sync')
        ->middleware('permission:view deadlines');

    // =====================================================================
    // DEADLINES API - USER SPECIFIC
    // =====================================================================
    Route::get('/api/deadlines/upcoming', [MunicipalityDeadlineController::class, 'upcomingDeadlines'])
        ->name('deadlines.upcoming')
        ->middleware('permission:view deadlines');

    Route::get('/api/deadlines/pending-submissions', [MunicipalityDeadlineController::class, 'pendingSubmissions'])
        ->name('deadlines.pending-submissions')
        ->middleware('permission:view deadlines');

    Route::get('/api/deadlines/municipalities', [MunicipalityDeadlineController::class, 'getMunicipalitiesForDeadline'])
        ->name('deadlines.municipalities.get')
        ->middleware('permission:create deadline');

    Route::get('/api/deadlines/users', [MunicipalityDeadlineController::class, 'getUsersForAssignment'])
        ->name('deadlines.users.get')
        ->middleware('permission:create deadline');

    Route::get('/api/deadlines/municipalities/{municipality}/companies', [MunicipalityDeadlineController::class, 'getMunicipalityCompanies'])
        ->name('deadlines.municipality.companies.get')
        ->middleware('permission:create deadline');

    // =====================================================================
    // SUPPORT TICKETS
    // =====================================================================
    Route::get('/support', [SupportTicketController::class, 'index'])->name('support.index');
    Route::get('/support/create', [SupportTicketController::class, 'create'])->name('support.create');
    Route::post('/support', [SupportTicketController::class, 'store'])->name('support.store');
    Route::get('/support/{ticket}', [SupportTicketController::class, 'show'])->name('support.show');
    Route::post('/support/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.reply');
    Route::put('/support/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->name('support.update-status');
    Route::get('/support/{ticket}/messages/{message}/attachments/{index}', [SupportTicketController::class, 'downloadAttachment'])->name('support.attachment');

    // =====================================================================
    // NOTIFICATIONS
    // =====================================================================
    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index')
        ->middleware('auth');

    Route::post('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])
        ->name('notifications.markAsRead')
        ->middleware('auth');

    Route::post('/notifications/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])
        ->name('notifications.markAllAsRead')
        ->middleware('auth');

    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])
        ->name('notifications.destroy')
        ->middleware('auth');

    Route::delete('/notifications/clear-all', [NotificationController::class, 'clearAll'])
        ->name('notifications.clearAll')
        ->middleware('auth');

    // =====================================================================
    // DASHBOARD API - USER SPECIFIC
    // =====================================================================
    Route::get('/api/dashboard/recent-uploads', [DashboardController::class, 'getRecentUploads'])
        ->middleware('permission:view dashboard');

    Route::get('/api/dashboard/search-uploads', [DashboardController::class, 'searchUploads'])
        ->middleware('permission:view dashboard');

    Route::get('/api/dashboard/stats', [DashboardController::class, 'getStats'])
        ->middleware('permission:view dashboard');

    // =====================================================================
    // ADMIN ROUTES
    // =====================================================================
    Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {

        // -----------------------------------------------------------------
        // USERS MANAGEMENT
        // -----------------------------------------------------------------
        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index')
            ->middleware('permission:manage users');

        Route::get('/users/create', [UserController::class, 'create'])
            ->name('users.create')
            ->middleware('permission:manage users');

        Route::post('/users', [UserController::class, 'store'])
            ->name('users.store')
            ->middleware('permission:manage users');

        Route::get('/users/{user}/edit', [UserController::class, 'edit'])
            ->name('users.edit')
            ->middleware('permission:manage users');

        Route::put('/users/{user}', [UserController::class, 'update'])
            ->name('users.update')
            ->middleware('permission:manage users');

        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->name('users.destroy')
            ->middleware('permission:manage users');

        // User Assignments
        Route::post('/users/{user}/assignments', [UserController::class, 'assignMunicipality'])
            ->name('users.assignments.store')
            ->middleware('permission:manage users');

        Route::delete('/users/{user}/assignments/{assignment}', [UserController::class, 'removeAssignment'])
            ->name('users.assignments.destroy')
            ->middleware('permission:manage users');

        // -----------------------------------------------------------------
        // ROLES MANAGEMENT
        // -----------------------------------------------------------------
        Route::get('/roles', [RoleController::class, 'index'])
            ->name('roles.index')
            ->middleware('permission:manage roles');

        Route::get('/roles/create', [RoleController::class, 'create'])
            ->name('roles.create')
            ->middleware('permission:manage roles');

        Route::post('/roles', [RoleController::class, 'store'])
            ->name('roles.store')
            ->middleware('permission:manage roles');

        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])
            ->name('roles.edit')
            ->middleware('permission:manage roles');

        Route::put('/roles/{role}', [RoleController::class, 'update'])
            ->name('roles.update')
            ->middleware('permission:manage roles');

        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])
            ->name('roles.destroy')
            ->middleware('permission:manage roles');

        Route::post('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])
            ->name('roles.permissions.update')
            ->middleware('permission:manage roles');

        // -----------------------------------------------------------------
        // PERMISSIONS
        // -----------------------------------------------------------------
        Route::get('/permissions/data', [PermissionController::class, 'data'])
            ->name('permissions.data')
            ->middleware('permission:manage permissions');

        // -----------------------------------------------------------------
        // COMPANIES MANAGEMENT
        // -----------------------------------------------------------------
        Route::get('/companies', [CompanyController::class, 'index'])
            ->name('companies.index')
            ->middleware('permission:view companies');

        Route::post('/companies', [CompanyController::class, 'store'])
            ->name('companies.store')
            ->middleware('permission:manage companies');

        Route::put('/companies/{company}', [CompanyController::class, 'update'])
            ->name('companies.update')
            ->middleware('permission:manage companies');

        Route::delete('/companies/{company}', [CompanyController::class, 'destroy'])
            ->name('companies.destroy')
            ->middleware('permission:manage companies');

        Route::get('/companies/{company}/assignments', [CompanyController::class, 'assignments'])
            ->name('companies.assignments')
            ->middleware('permission:view companies');

        // -----------------------------------------------------------------
        // MUNICIPALITIES MANAGEMENT
        // -----------------------------------------------------------------
        Route::get('/municipalities', [MunicipalityController::class, 'index'])
            ->name('municipalities.index')
            ->middleware('permission:view municipalities');

        Route::post('/municipalities', [MunicipalityController::class, 'store'])
            ->name('municipalities.store')
            ->middleware('permission:manage municipalities');

        Route::put('/municipalities/{municipality}', [MunicipalityController::class, 'update'])
            ->name('municipalities.update')
            ->middleware('permission:manage municipalities');

        Route::delete('/municipalities/{municipality}', [MunicipalityController::class, 'destroy'])
            ->name('municipalities.destroy')
            ->middleware('permission:manage municipalities');

        Route::get('/municipalities/{municipality}/companies', [MunicipalityController::class, 'companies'])
            ->name('municipalities.companies')
            ->middleware('permission:view municipalities');

        Route::get('/municipalities/{municipality}/deadlines', [MunicipalityController::class, 'deadlines'])
            ->name('municipalities.deadlines')
            ->middleware('permission:view municipalities');

        Route::get('/municipalities/{municipality}/assignments', [MunicipalityController::class, 'assignments'])
            ->name('municipalities.assignments')
            ->middleware('permission:view municipalities');

        // -----------------------------------------------------------------
        // REPORTS
        // -----------------------------------------------------------------
        Route::get('/reports', [ReportController::class, 'index'])
            ->name('reports.index')
            ->middleware('permission:view reports');

        Route::get('/reports/export', [ReportController::class, 'export'])
            ->name('reports.export')
            ->middleware('permission:view reports');

        Route::get('/reports/upload-summary', [ReportController::class, 'uploadSummary'])
            ->name('reports.upload-summary')
            ->middleware('permission:view reports');

        Route::get('/reports/deadline-summary', [ReportController::class, 'deadlineSummary'])
            ->name('reports.deadline-summary')
            ->middleware('permission:view reports');

        // -----------------------------------------------------------------
        // CAPS DATA SYNC
        // -----------------------------------------------------------------
        Route::post('/caps-sync', [CapsDataSyncController::class, 'sync'])
            ->name('caps-sync.run')
            ->middleware('permission:manage companies');

        Route::get('/caps-sync/status', [CapsDataSyncController::class, 'status'])
            ->name('caps-sync.status')
            ->middleware('permission:manage companies');

        // -----------------------------------------------------------------
        // AUDITS
        // -----------------------------------------------------------------
        Route::get('/audits', [AuditController::class, 'index'])
            ->name('audits.index')
            ->middleware('permission:view audits');

        Route::get('/audits/{audit}', [AuditController::class, 'show'])
            ->name('audits.show')
            ->middleware('permission:view audits');

        Route::get('/audits/user/{user}', [AuditController::class, 'userAudits'])
            ->name('audits.user')
            ->middleware('permission:view audits');
    });

    // =====================================================================
    // LOGOUT
    // =====================================================================
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // SSO-triggered logout via GET (no CSRF needed) — used by the
    // client-side polling when it detects the SSO session was removed.
    Route::get('/sso-logout', [AuthenticatedSessionController::class, 'ssoLogout'])
        ->name('sso.logout');
});

// =========================================================================
// FALLBACK ROUTE - Must be last
// =========================================================================
Route::fallback(fn () => Inertia::render('Errors/404', ['status' => 404]))
    ->name('fallback');
