# Submission Tracker -- Technical Documentation

| Attribute | Value |
|---|---|
| Document ID | ST-TD-003 |
| Version | 1.0.0 |
| Classification | Internal -- Engineering |
| Last Updated | 2026-04-23 |
| Audience | Backend engineers, frontend engineers, DevOps, QA |

---

## Table of Contents

1. [Technology Stack](#1-technology-stack)
2. [Architecture Overview](#2-architecture-overview)
3. [Directory Structure and Code Organization](#3-directory-structure-and-code-organization)
4. [Backend Architecture](#4-backend-architecture)
5. [Frontend Architecture](#5-frontend-architecture)
6. [Database Design](#6-database-design)
7. [Authentication and Authorization](#7-authentication-and-authorization)
8. [CAPS Integration Layer](#8-caps-integration-layer)
9. [Multi-Tenancy Architecture](#9-multi-tenancy-architecture)
10. [Audit and Compliance Subsystem](#10-audit-and-compliance-subsystem)
11. [File Processing Pipeline](#11-file-processing-pipeline)
12. [Notification System](#12-notification-system)
13. [API Reference](#13-api-reference)
14. [Console Commands and Scheduled Tasks](#14-console-commands-and-scheduled-tasks)
15. [Caching Strategy](#15-caching-strategy)
16. [Error Handling Patterns](#16-error-handling-patterns)
17. [Key Algorithms](#17-key-algorithms)
18. [Naming Conventions](#18-naming-conventions)
19. [Build and Development Toolchain](#19-build-and-development-toolchain)
20. [Environment Configuration](#20-environment-configuration)
21. [Security Considerations](#21-security-considerations)
22. [Performance Considerations](#22-performance-considerations)

---

## 1. Technology Stack

### 1.1 Backend

| Technology | Version | Purpose |
|---|---|---|
| PHP | 8.2+ | Server-side runtime |
| Laravel | 12.x | Application framework |
| Eloquent ORM | (bundled) | Database abstraction and model layer |
| Laravel Sanctum | 4.2 | API token authentication |
| Spatie Permission | 6.21 | Role-based access control (RBAC) |
| Maatwebsite Excel | 3.1 | Spreadsheet import/export |
| PhpOffice PhpSpreadsheet | 1.30 | Low-level spreadsheet reading (preview, cell-level parsing) |
| php-mime-mail-parser | 9.0 | EML email file parsing |
| hfig/mapi | 1.4 | MSG (Outlook) file parsing and MSG-to-EML conversion |
| zbateson/mail-mime-parser | 3.0 | Additional MIME parsing support |
| webklex/php-imap | 6.2 | IMAP protocol support |
| GuzzleHTTP | 7.10 | HTTP client for CAPS API integration |
| Predis | 3.3 | Redis client for caching and session management |
| Inertia.js (server) | 2.x | Server-side adapter for Inertia protocol |

### 1.2 Frontend

| Technology | Version | Purpose |
|---|---|---|
| Vue.js | 3.5 | Reactive UI framework (Composition API) |
| Inertia.js (client) | 2.1 | Client-side SPA adapter -- eliminates REST API boilerplate |
| Tailwind CSS | 4.1 | Utility-first CSS framework |
| FullCalendar (Vue 3) | 6.1 | Interactive calendar for deadlines and assignments |
| Lucide Vue Next | 0.544 | Icon library |
| Heroicons Vue | 2.2 | Additional icon set |
| PapaParse | 5.5 | Client-side CSV parsing |
| read-excel-file | 6.0 | Client-side Excel file reading |
| MSGReader | 1.0 | Client-side MSG file parsing |
| Ziggy | 2.6 | Laravel named routes available in JavaScript |
| date-fns | 4.1 | Date utility library |
| Lodash | 4.17 | Utility functions |
| vue3-select | 0.1 | Searchable dropdown component |
| Axios | 1.11 | HTTP client for AJAX requests |

### 1.3 Build and Infrastructure

| Technology | Version | Purpose |
|---|---|---|
| Vite | 7.1 | Frontend build tool and dev server |
| MySQL | 8.x | Primary relational database |
| Redis | (via Predis 3.3) | Caching layer, session store, queue backend |
| Node.js | (runtime) | SSO session microservice |
| Composer | 2.x | PHP dependency management |
| NPM | (bundled with Node) | JavaScript dependency management |

### 1.4 Development Tooling

| Tool | Purpose |
|---|---|
| Laravel Pint | PHP code style fixer (PSR-12) |
| PHP-CS-Fixer | Extended code style enforcement |
| ESLint (v9) + eslint-plugin-vue | JavaScript/Vue linting |
| Prettier + prettier-plugin-tailwindcss | Code formatting with Tailwind class sorting |
| Stylelint | CSS/Vue style linting |
| PHPUnit 11.5 | Unit and integration testing |
| Mockery | Test doubles |
| FakerPHP | Test data generation |
| Laravel Pail | Real-time log viewer |
| Concurrently | Parallel process runner for dev mode |

---

## 2. Architecture Overview

### 2.1 Architectural Pattern

The Submission Tracker follows a **monolithic server-driven SPA** architecture using the Inertia.js protocol. This pattern combines:

- A traditional Laravel backend handling routing, authentication, authorization, and data persistence.
- A Vue 3 SPA frontend that receives page components and props directly from Laravel controllers via the Inertia adapter, eliminating the need for a separate API layer for the web interface.
- A complementary REST API (`api/v1/`) for external integrations, partner access, and the CAPS webhook receiver.

```
+-------------------+     Inertia Protocol      +-------------------+
|                   | <========================> |                   |
|   Laravel 12      |    (JSON page objects)     |   Vue 3 SPA       |
|   Controllers     |                            |   Composition API  |
|   Middleware       |                            |   Tailwind CSS 4   |
|   Eloquent ORM    |                            |   FullCalendar     |
|                   |                            |                   |
+--------+----------+                            +-------------------+
         |
         |  Eloquent / Query Builder
         v
+-------------------+     HTTP (Guzzle)          +-------------------+
|                   | =========================> |                   |
|   MySQL 8.x       |                            |   CAPS API         |
|                   |                            |   (Java / Spring)  |
+-------------------+     HTTP                   +-------------------+
                          ========================>
+-------------------+                            +-------------------+
|   Redis            |                            |   SSO Session      |
|   (Cache/Session)  |                            |   Microservice     |
+-------------------+                            |   (Node.js)        |
                                                 +-------------------+
```

### 2.2 Key Architectural Decisions

1. **Inertia.js over REST SPA**: Eliminates the need for a dedicated API for the frontend while preserving full SPA-like navigation. Server-side controllers return `Inertia::render()` responses that include component names and serialized props.

2. **CAPS as System of Record**: Company and Municipality master data is owned by CAPS. The Submission Tracker syncs this data nightly and treats CAPS as authoritative. Local rows keyed by `casey_id` are overwritten on sync; rows without a `casey_id` are left untouched.

3. **Companies Belong to All Municipalities**: Every company can submit to every municipality. The `companies.municipality_id` foreign key represents the CAPS area-to-municipality mapping for reference data resolution, NOT a 1:1 scoping constraint. User assignments (the `user_assignments` pivot table) determine which company-municipality combinations a user works with.

4. **User's SSO JWT for API Calls**: When a user logs in via CAPS SSO, their JWT is persisted in the session (`session('caps_jwt')`). All subsequent CAPS API calls in that session authenticate as that user rather than using hardcoded service account credentials from `.env`. The service account credentials serve as a fallback for CLI/scheduler contexts where no user session exists.

5. **Multi-Tenancy via Shared Database**: The application uses a shared-database, shared-schema multi-tenancy model. Every tenant-scoped table has a `tenant_id` column, and the `BelongsToTenant` trait auto-populates this on creation.

---

## 3. Directory Structure and Code Organization

### 3.1 Backend (`app/`)

```
app/
+-- Console/
|   +-- Commands/
|       +-- SyncCaseyReferenceData.php      # Artisan command: casey:sync-reference-data
|       +-- SyncPermissions.php             # Artisan command: permissions:sync
+-- Exports/
|   +-- UploadsExports.php                  # Maatwebsite Excel export definition
+-- Http/
|   +-- Controllers/
|   |   +-- Controller.php                  # Base controller
|   |   +-- DashboardController.php         # Invokable: __invoke(), stats, uploads, CAPS sync status
|   |   +-- UploadsController.php           # ~2800 lines: upload CRUD, file preview, CAPS verification
|   |   +-- SubmissionController.php        # Submission tracking
|   |   +-- CompanyController.php           # Company management
|   |   +-- MunicipalityController.php      # Municipality management
|   |   +-- MunicipalityDeadlineController.php  # Deadline CRUD, calendar events, bulk assignment
|   |   +-- NotificationController.php      # Notification CRUD and mark-read
|   |   +-- Auth/
|   |   |   +-- AuthenticatedSessionController.php  # Local login/logout
|   |   |   +-- CaseySsoController.php      # CAPS SSO bridge: JWT verification, auto-provision
|   |   +-- Admin/
|   |   |   +-- AdminDashboardController.php
|   |   |   +-- UserController.php          # User CRUD + municipality assignment
|   |   |   +-- RoleController.php          # Role CRUD + permission assignment
|   |   |   +-- PermissionController.php    # Permission data endpoint
|   |   |   +-- CompanyController.php       # Admin company management
|   |   |   +-- MunicipalityController.php  # Admin municipality management
|   |   |   +-- ReportController.php        # Upload/deadline summary + export
|   |   |   +-- AuditController.php         # Audit log viewer
|   |   |   +-- CapsDataSyncController.php  # Manual CAPS sync trigger + status
|   |   +-- Api/V1/
|   |       +-- ApiKeyController.php        # API key CRUD
|   |       +-- CapsWebhookController.php   # CAPS status echo-back (Layer 3)
|   |       +-- CompanyController.php       # API resource: companies
|   |       +-- DeadlineController.php      # API resource: deadlines
|   |       +-- EventLogController.php      # Event timeline queries
|   |       +-- IntegrationController.php   # External integration management
|   |       +-- MunicipalityController.php  # API resource: municipalities
|   |       +-- OpsController.php           # Failed job management
|   |       +-- RoleController.php          # API resource: roles
|   |       +-- TenantController.php        # Tenant settings
|   |       +-- UploadController.php        # API resource: uploads + premium batch info
|   |       +-- WebhookReplayController.php # Webhook event replay
|   |       +-- WorkflowController.php      # Workflow definition and instances
|   +-- Middleware/
|       +-- HandleInertiaRequests.php       # Shares auth, notifications, flash, SSO config
|       +-- AuditTrailMiddleware.php        # Logs POST/PUT/PATCH/DELETE with sanitized payloads
|       +-- AuthenticateApiKey.php          # X-API-Key header validation with scope checking
|       +-- ResolveTenant.php               # Resolves tenant from header, domain, user, or default
|       +-- ShareCalendarEvents.php         # Injects deadline/assignment calendar events into views
|       +-- SsoSessionSync.php             # Bidirectional SSO sync with 15s rate limiting
+-- Models/
|   +-- User.php                            # Authenticatable + HasRoles + BelongsToTenant + RecordsAuditTrail
|   +-- Uploads.php                         # Upload record with multi-file support and CAPS verification
|   +-- Company.php                         # capsOnly global scope, casey_id keyed
|   +-- Municipality.php                    # capsOnly global scope, casey_id keyed
|   +-- MunicipalityDeadline.php            # Deadline dates per municipality
|   +-- UserAssignment.php                  # Pivot: user <-> municipality <-> company + deadline
|   +-- Tenant.php                          # Multi-tenant root entity
|   +-- TenantDomain.php                    # Custom domains per tenant
|   +-- TenantSetting.php                   # Per-tenant configuration
|   +-- Audit.php                           # Audit log entries (polymorphic auditable)
|   +-- ApiKey.php                          # Hashed API keys with scopes
|   +-- EventLog.php                        # Event timeline entries
|   +-- CapsWebhookEvent.php               # Idempotent webhook event store
|   +-- Submission.php                      # Submission tracking
|   +-- CompanyPolicy.php                   # Company policy records
|   +-- IntegrationConnection.php           # External integration configs
|   +-- WebhookDelivery.php                 # Outbound webhook delivery tracking
|   +-- WorkflowDefinition.php             # Workflow templates
|   +-- WorkflowInstance.php               # Active workflow executions
|   +-- Concerns/
|       +-- BelongsToTenant.php             # Auto-sets tenant_id on creating; provides scopeForTenant
|       +-- RecordsAuditTrail.php           # Hooks created/updated/deleted to AuditLogger
+-- Notifications/
|   +-- NewUploadNotification.php
|   +-- UploadCreated.php
|   +-- DeadlineCreated.php
|   +-- DeadlineUpdated.php
|   +-- DeadlineDeleted.php
|   +-- DeadlineAssigned.php
|   +-- AssignmentRemoved.php
|   +-- Admin/                              # Admin-specific notification classes
+-- Services/
|   +-- TenantContext.php                   # Singleton: holds the resolved tenant for the request
|   +-- TenantResolverService.php           # Resolves tenant from X-Tenant header, domain, user, or default
|   +-- CaseyJwtService.php                 # HS256 JWT verification (no third-party dependency)
|   +-- CaseyReferenceDataService.php       # Syncs Company/Municipality master data from CAPS
|   +-- CaseyMemberPolicyService.php        # Fetches members/policies from CAPS for verification
|   +-- CaseyPremiumBatchService.php        # Premium batch detailed info from CAPS
|   +-- SsoSessionService.php               # Client for the SSO Session microservice (Node.js)
|   +-- EventTimelineService.php            # Records structured events to the event_logs table
|   +-- WorkflowEngineService.php           # Workflow state machine execution
|   +-- Integrations/                       # External integration adapters
+-- Support/
    +-- AuditLogger.php                     # Static helper: writes audit entries for models, auth, and requests
```

### 3.2 Frontend (`resources/js/`)

```
resources/js/
+-- app.js                                  # Inertia app bootstrap with Vue 3 createApp
+-- Pages/
|   +-- Dashboard.vue                       # Main dashboard with stats, deadlines, calendar
|   +-- Auth/                               # Login page
|   +-- Uploads/
|   |   +-- Index.vue                       # Upload listing with filtering and pagination
|   |   +-- Complete.vue                    # Multi-step upload completion form
|   |   +-- History.vue                     # Upload history view
|   |   +-- ViewEmail.vue                   # EML/MSG email preview
|   |   +-- ViewSpreadsheet.vue             # Inline spreadsheet preview
|   +-- Deadlines/
|   |   +-- Municipalities.vue              # Municipality deadline management
|   |   +-- Companies.vue                   # Company-level deadline view
|   |   +-- Municipalities/                 # Nested municipality deadline pages
|   +-- Submissions/                        # Submission tracking pages
|   +-- Notifications/                      # Notification center
|   +-- Admin/
|   |   +-- Users/                          # User CRUD pages
|   |   +-- Roles/                          # Role CRUD pages
|   |   +-- Companies/                      # Company management pages
|   |   +-- Municipalities/                 # Municipality management pages
|   |   +-- Audits/                         # Audit log viewer
|   |   +-- Reports/                        # Report generation pages
|   +-- Analytics/                          # Analytics dashboards
|   +-- Settings/                           # Application settings
|   +-- Errors/                             # Error pages (404, etc.)
+-- Components/                             # Reusable Vue components
+-- Layouts/                                # Layout components (authenticated, guest)
+-- Composables/                            # Vue 3 composable functions
```

### 3.3 Database (`database/`)

```
database/
+-- migrations/
|   +-- 0001_01_01_000000_create_users_table.php
|   +-- 0001_01_01_000001_create_cache_table.php
|   +-- 0001_01_01_000002_create_jobs_table.php
|   +-- 2025_09_11_*_create_municipalities_table.php
|   +-- 2025_09_12_*_create_companies_table.php
|   +-- 2025_09_12_*_create_submissions_table.php
|   +-- 2025_09_15_*_create_uploads_table.php
|   +-- 2025_09_16_*_create_municipality_deadlines_table.php
|   +-- 2025_09_17_*_create_permission_tables.php          # Spatie permissions
|   +-- 2025_09_22_*_create_user_assignments_table.php
|   +-- 2025_09_23_*_create_audits_table.php
|   +-- 2025_09_23_*_create_notifications_table.php
|   +-- 2025_10_17_*_add_user_id_to_uploads_table.php
|   +-- 2025_10_20_*_add_reupload_reasons_to_uploads_table.php
|   +-- 2026_01_13_*_create_personal_access_tokens_table.php  # Sanctum
|   +-- 2026_01_16_*_add_converted_eml_paths_to_uploads_table.php
|   +-- 2026_02_16_*_add_external_password_hash_to_users_table.php
|   +-- 2026_04_14_*_add_profile_columns_to_users_table.php
|   +-- 2026_04_15_*_add_casey_id_to_companies_and_municipalities.php
|   +-- 2026_04_15_*_make_companies_municipality_id_nullable.php
|   +-- 2026_04_16_*_add_caps_webhook_columns_to_uploads.php
|   +-- 2026_04_23_*_create_multi_tenant_core_tables.php
|   +-- 2026_04_23_*_add_tenant_id_to_core_tables.php
|   +-- 2026_04_23_*_add_caps_verification_to_uploads_table.php
+-- factories/                              # Eloquent model factories for testing
+-- seeders/                                # Database seeders
```

---

## 4. Backend Architecture

### 4.1 Request Lifecycle

Every HTTP request passes through the following middleware stack (in order):

1. **ResolveTenant** -- Determines the active tenant from `X-Tenant` header, domain hostname, authenticated user's `tenant_id`, or falls back to the `default` tenant. Sets the resolved `Tenant` on the singleton `TenantContext` service.

2. **SsoSessionSync** -- When CASEY SSO is enabled, performs bidirectional session synchronization:
   - Logged-in users: checks the SSO microservice every 15 seconds (rate-limited via session key `sso_check_at`). If the session is confirmed gone (HTTP 404), the local session is invalidated.
   - Guest users: lists active SSO sessions. If a CAPS-sourced session with a valid JWT is found, redirects to `/auth/casey-sso` for automatic login.
   - Microservice unreachable: graceful degradation -- no action taken.
   - Skips paths: `auth/`, `login`, `logout`, `api/`, `health`, `_debugbar`.

3. **HandleInertiaRequests** -- Shares the following props with every Inertia response:
   - `csrf_token`: CSRF protection token.
   - `auth.user`: Authenticated user object including `id`, `name`, `email`, `employee_number`, `roles` (via Spatie), and `permissions` (all permission names).
   - `notifications.unread_count`: Count of unread database notifications.
   - `flash.success` / `flash.error`: Session flash messages (lazy-evaluated).
   - `appName`: Application name from config.
   - `sso`: SSO configuration object (`enabled`, `serviceUrl`, `apiSecret`).

4. **ShareCalendarEvents** -- Injects deadline and assignment calendar events for the current month into all views. Events are color-coded by urgency (red = overdue, orange = today, yellow = tomorrow, green = future).

5. **AuditTrailMiddleware** -- After the response is generated, logs all `POST`, `PUT`, `PATCH`, and `DELETE` requests to the audit trail. Sanitizes sensitive fields (`password`, `password_confirmation`, `_token`, `_method`). Skips login/logout routes and audit viewer routes. Only logs responses with status < 500.

6. **Permission middleware** -- Individual routes use `middleware('permission:...')` for fine-grained access control via Spatie Permission.

### 4.2 Controller Design Patterns

#### Invokable Controllers

The `DashboardController` uses the `__invoke()` pattern for its primary action, making it a single-action controller registered as `Route::get('/dashboard', DashboardController::class)`. Additional JSON API methods (`getRecentUploads`, `searchUploads`, `getStats`) are registered as separate routes.

#### Resource Controllers

Standard CRUD controllers follow Laravel resource conventions. The `UploadsController` uses `Route::resource('uploads', ...)->only(['index', 'store', 'destroy'])` supplemented by custom routes for the multi-step upload completion flow, file preview, email viewing, CAPS comparison, and download.

#### Admin Controllers

All admin controllers are grouped under the `App\Http\Controllers\Admin` namespace with routes prefixed by `/admin` and named with the `admin.` prefix. Each route requires the appropriate Spatie permission (e.g., `permission:manage users`).

#### API Controllers (V1)

RESTful API controllers under `App\Http\Controllers\Api\V1` use `apiResource` registrations (read-only for most resources). These are protected by either Sanctum token authentication or API key authentication.

### 4.3 Service Layer

The application employs a service layer for complex business logic, keeping controllers thin:

| Service | Responsibility |
|---|---|
| `TenantContext` | Request-scoped singleton holding the resolved tenant. Injected via constructor DI. |
| `TenantResolverService` | Resolves tenant from request headers, domain, user, or default. |
| `CaseyJwtService` | Verifies HS256 JWTs from CAPS. Zero external dependencies -- implemented from scratch for minimal attack surface. |
| `CaseyReferenceDataService` | Pulls Company/Municipality master data from CAPS. Upserts by `casey_id`. Handles Spring Pageable response envelopes. |
| `CaseyMemberPolicyService` | Fetches member and policy data from CAPS for upload verification. Supports multi-company comparison with fuzzy name matching. |
| `CaseyPremiumBatchService` | Retrieves premium batch detailed information from CAPS. |
| `SsoSessionService` | HTTP client for the Node.js SSO session microservice. Provides `registerSession`, `checkSession`, `removeSession`, `listSessions`. |
| `EventTimelineService` | Records structured events to `event_logs` for the event timeline feature. |
| `WorkflowEngineService` | Executes workflow state machine transitions. |

### 4.4 Model Layer

#### Model Traits

**`BelongsToTenant`** -- Applied to all tenant-scoped models. Provides:
- `bootBelongsToTenant()`: Automatically sets `tenant_id` on the `creating` event from the resolved `TenantContext`.
- `scopeForTenant(Builder $query, ?int $tenantId)`: Named scope for explicit tenant filtering. Passes through when `$tenantId` is null.

**`RecordsAuditTrail`** -- Applied to models requiring change tracking. Hooks into Eloquent's `created`, `updated`, and `deleted` events:
- `created`: Records the full attribute set as `newValues`.
- `updated`: Records only changed attributes (excluding `updated_at`). Skips the audit entry if no meaningful changes exist.
- `deleted`: Records the full original attribute set as `oldValues`.

**`HasRoles`** (Spatie) -- Applied to the `User` model. Provides role/permission checking and assignment methods.

#### Global Scopes

Both `Company` and `Municipality` register a `capsOnly` global scope in their `booted()` method:

```php
static::addGlobalScope('capsOnly', function ($query) {
    $query->whereNotNull('casey_id')->where('casey_id', '!=', '');
});
```

This ensures only CAPS-synced records appear by default. Legacy seeded duplicates without a `casey_id` are excluded. To bypass: `Company::withoutGlobalScope('capsOnly')`.

#### Key Model Relationships

```
User
 |-- hasMany(UserAssignment)
 |-- hasMany(Uploads)
 |-- belongsTo(Tenant)
 |-- belongsToMany(Municipality) via user_assignments
 |-- belongsToMany(Company) via user_assignments

Company
 |-- belongsTo(Municipality)
 |-- hasMany(Uploads)
 |-- hasMany(UserAssignment)

Municipality
 |-- hasMany(Company)
 |-- hasMany(MunicipalityDeadline)
 |-- hasMany(UserAssignment)
 |-- hasMany(Uploads)
 |-- belongsToMany(User) via user_assignments

Uploads
 |-- belongsTo(Company)
 |-- belongsTo(Municipality)
 |-- belongsTo(User)

UserAssignment
 |-- belongsTo(User)
 |-- belongsTo(Municipality)
 |-- belongsTo(Company)

Audit
 |-- belongsTo(User)
 |-- morphTo(auditable)

Tenant
 |-- hasOne(TenantSetting)
 |-- hasMany(TenantDomain)
```

### 4.5 Eloquent Scopes

The codebase makes extensive use of named scopes for reusable query logic:

| Model | Scope | Purpose |
|---|---|---|
| `User` | `scopeActive` | Filters `is_active = true` |
| `User` | `scopeByRole($role)` | Filters by Spatie role name |
| `User` | `scopeByDepartment($dept)` | Filters by department |
| `User` | `scopeSearch($search)` | LIKE search on name and email |
| `Uploads` | `scopeAccessibleToUser($userId)` | Filters by user's company/municipality assignments |
| `Uploads` | `scopeByMunicipality($id)` | Filters by municipality_id |
| `Uploads` | `scopeByCompany($id)` | Filters by company_id |
| `Uploads` | `scopeByStatus($status)` | Filters by status string |
| `Municipality` | `scopeAccessibleToUser($userId)` | Filters by user assignment existence |
| `Company` | `scopeWithActiveDeadlines` | Filters companies with future municipality deadlines |

---

## 5. Frontend Architecture

### 5.1 Inertia.js Integration

The frontend is a Vue 3 SPA orchestrated by Inertia.js. Key characteristics:

- **No client-side router**: Inertia replaces Vue Router. Navigation is handled via `<Link>` components or `router.visit()` / `router.post()` calls that make XHR requests to the server. The server returns JSON page objects containing the Vue component name and its props.
- **Server-driven page resolution**: Each `Inertia::render('Dashboard', [...])` call maps to a Vue component in `resources/js/Pages/Dashboard.vue`.
- **Shared props**: Global data (auth, notifications, flash messages, SSO config) is shared via `HandleInertiaRequests` middleware and available in every page component without explicit passing.
- **Lazy props**: Flash messages use lazy evaluation (`fn () => session()->get(...)`) so they are only serialized when actually accessed.
- **Named routes via Ziggy**: The `ziggy-js` package exposes all Laravel named routes to JavaScript. Components use `route('uploads.download', { upload: id, which: 'original' })` for URL generation.

### 5.2 Vue 3 Composition API

All page components and reusable components use the Vue 3 Composition API with `<script setup>` syntax. Key patterns:

- **`usePage()`**: Accesses shared Inertia props (auth, flash messages).
- **`useForm()`**: Inertia form helper for form submissions with automatic error handling and processing state.
- **`ref()` / `reactive()`**: Standard Vue reactivity primitives.
- **`computed()`**: Derived state calculations.
- **`watch()` / `watchEffect()`**: Side effects triggered by reactive state changes.

### 5.3 Tailwind CSS 4

Styling uses Tailwind CSS 4 with the Vite plugin (`@tailwindcss/vite`). The `@` alias resolves to `resources/js/` for clean imports. Tailwind class sorting is enforced by `prettier-plugin-tailwindcss`.

### 5.4 Vite Configuration

The Vite configuration (`vite.config.js`) includes:

- **Entry points**: `resources/css/app.css` and `resources/js/app.js`.
- **Vue plugin**: `@vitejs/plugin-vue` for `.vue` SFC compilation.
- **Tailwind plugin**: `@tailwindcss/vite` for CSS processing.
- **Laravel Vite plugin**: Handles manifest generation and HMR integration.
- **Path alias**: `@` maps to `resources/js/`.
- **HMR**: Supports both localhost development and Replit cloud environment.
- **Watch ignores**: `node_modules`, `.cache`, `storage`, `vendor` directories.

### 5.5 Page Component Inventory

| Page | Route | Description |
|---|---|---|
| `Dashboard.vue` | `/dashboard` | Main dashboard with statistics, calendar, deadlines, recent uploads |
| `Uploads/Index.vue` | `/uploads` | Upload listing with filtering, pagination, file preview links |
| `Uploads/Complete.vue` | `/uploads/{id}/complete` | Multi-step upload completion form |
| `Uploads/History.vue` | `/uploads/history` | Historical upload records |
| `Uploads/ViewEmail.vue` | `/uploads/{id}/view-email/{index}` | Email (EML/MSG) preview |
| `Uploads/ViewSpreadsheet.vue` | `/uploads/{id}/preview/{index}` | Spreadsheet preview modal |
| `Deadlines/Municipalities.vue` | `/deadlines/municipalities` | Municipality deadline management |
| `Deadlines/Companies.vue` | `/deadlines/companies` | Company-level deadline view |
| `Admin/Users/*` | `/admin/users/*` | User CRUD interface |
| `Admin/Roles/*` | `/admin/roles/*` | Role and permission management |
| `Admin/Companies/*` | `/admin/companies/*` | Company management |
| `Admin/Municipalities/*` | `/admin/municipalities/*` | Municipality management |
| `Admin/Audits/*` | `/admin/audits/*` | Audit log viewer |
| `Admin/Reports/*` | `/admin/reports/*` | Report generation |
| `Notifications/*` | `/notifications` | Notification center |

---

## 6. Database Design

### 6.1 Core Tables

#### `users`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `tenant_id` | bigint FK nullable | References `tenants.id` |
| `name` | varchar | Display name |
| `email` | varchar unique | Email address |
| `employee_number` | varchar nullable | CAPS employee number (SSO identifier) |
| `password` | varchar | Hashed (bcrypt via Laravel `hashed` cast) |
| `external_password_hash` | varchar nullable | Password hash from external system |
| `phone` | varchar nullable | Contact phone |
| `department` | varchar nullable | Organizational department |
| `position` | varchar nullable | Job title |
| `is_active` | boolean | Active status flag |
| `last_login_at` | datetime nullable | Last login timestamp |
| `last_login_ip` | varchar nullable | Last login IP address |
| `email_verified_at` | datetime nullable | Email verification timestamp |
| `remember_token` | varchar nullable | Session remember token |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

#### `uploads`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `tenant_id` | bigint FK nullable | References `tenants.id` |
| `company_id` | bigint FK | References `companies.id` |
| `municipality_id` | bigint FK | References `municipalities.id` |
| `user_id` | bigint FK | References `users.id` |
| `reference` | varchar | Unique submission reference |
| `status` | varchar | Upload status (`Pending`, `Completed`, etc.) |
| `submitted_at` | datetime nullable | Submission timestamp |
| `original_file_path` | json | Array of file storage paths |
| `original_file_names` | json | Array of original file names |
| `workings_file_path` | varchar nullable | Workings spreadsheet storage path |
| `workings_file_name` | varchar nullable | Workings spreadsheet original name |
| `systems_import_file_path` | varchar nullable | Systems import file storage path |
| `systems_import_file_name` | varchar nullable | Systems import file original name |
| `extracted_dates` | json | Dates extracted from uploaded files |
| `system_import_date` | datetime nullable | System import date |
| `reupload_reason_type` | varchar nullable | Reason code for re-upload |
| `reupload_reason_note` | text nullable | Freeform re-upload reason |
| `converted_eml_paths` | json | Paths to MSG-to-EML converted files |
| `caps_payment_batch_id` | varchar nullable | CAPS payment batch identifier |
| `caps_status` | varchar nullable | CAPS processing status |
| `caps_status_detail` | text nullable | CAPS status details / error messages |
| `caps_last_webhook_at` | datetime nullable | Last webhook received timestamp |
| `caps_verification` | json nullable | CAPS verification comparison results |
| `caps_verified_at` | datetime nullable | Verification timestamp |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

#### `companies`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `tenant_id` | bigint FK nullable | References `tenants.id` |
| `name` | varchar | Company name |
| `registration_number` | varchar nullable | Company registration number |
| `status` | varchar | `active` or `inactive` |
| `contact_email` | varchar nullable | Contact email |
| `municipality_id` | bigint FK nullable | References `municipalities.id` (CAPS area mapping, NOT a scoping constraint) |
| `casey_id` | varchar nullable | CAPS system identifier |
| `casey_synced_at` | datetime nullable | Last CAPS sync timestamp |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

#### `municipalities`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `tenant_id` | bigint FK nullable | References `tenants.id` |
| `name` | varchar | Municipality name |
| `province` | varchar nullable | South African province |
| `code` | varchar nullable | Municipality code |
| `casey_id` | varchar nullable | CAPS system identifier |
| `casey_synced_at` | datetime nullable | Last CAPS sync timestamp |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

#### `municipality_deadlines`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `municipality_id` | bigint FK | References `municipalities.id` |
| `deadline_date` | date | Submission deadline date |
| `notes` | text nullable | Additional notes |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

#### `user_assignments`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `user_id` | bigint FK | References `users.id` |
| `municipality_id` | bigint FK | References `municipalities.id` |
| `company_id` | bigint FK nullable | References `companies.id` |
| `deadline_date` | date nullable | Assignment-specific deadline |
| `notes` | text nullable | Assignment notes |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

#### `audits`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `tenant_id` | bigint FK nullable | References `tenants.id` |
| `user_type` | varchar nullable | Polymorphic user type |
| `user_id` | bigint nullable | Polymorphic user ID |
| `event` | varchar | Event type (`created`, `updated`, `deleted`, `logged_in`, etc.) |
| `auditable_type` | varchar | Polymorphic target type |
| `auditable_id` | bigint | Polymorphic target ID |
| `old_values` | json | Previous attribute values |
| `new_values` | json | New attribute values |
| `url` | varchar nullable | Request URL |
| `ip_address` | varchar nullable | Client IP address |
| `user_agent` | varchar(1023) nullable | Client user agent |
| `tags` | varchar nullable | Comma-separated event metadata tags |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

### 6.2 Multi-Tenancy Tables

#### `tenants`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `name` | varchar | Tenant display name |
| `slug` | varchar unique | URL-safe identifier |
| `status` | varchar | `active`, `suspended`, etc. |
| `plan` | varchar nullable | Subscription plan |
| `billing_customer_id` | varchar nullable | External billing reference |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

#### `tenant_domains`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `tenant_id` | bigint FK | References `tenants.id` |
| `domain` | varchar unique | Custom domain hostname |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

#### `tenant_settings`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `tenant_id` | bigint FK unique | References `tenants.id` |
| Settings columns | various | Per-tenant configuration options |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

### 6.3 CAPS Integration Tables

#### `caps_webhook_events`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | Auto-increment |
| `event_id` | varchar unique | Idempotency key |
| `event_type` | varchar | Event type from CAPS |
| `payments_batch_id` | varchar nullable | CAPS batch identifier |
| `submission_reference` | varchar nullable | Submission reference |
| `status` | varchar | Mapped status value |
| `payload` | json | Full webhook payload |
| `upload_id` | bigint FK nullable | References `uploads.id` |
| `created_at` / `updated_at` | timestamps | Standard timestamps |

### 6.4 Entity Relationship Summary

```
tenants 1--* users
tenants 1--* companies
tenants 1--* municipalities
tenants 1--* audits
tenants 1--1 tenant_settings
tenants 1--* tenant_domains

municipalities 1--* companies (reference mapping only)
municipalities 1--* municipality_deadlines
municipalities 1--* user_assignments
municipalities 1--* uploads

companies 1--* uploads
companies 1--* user_assignments

users 1--* uploads
users 1--* user_assignments
users 1--* audits (as actor)

uploads 1--* caps_webhook_events
```

---

## 7. Authentication and Authorization

### 7.1 Authentication Methods

The application supports three authentication methods:

#### 7.1.1 Local Authentication

Standard email/password login via `AuthenticatedSessionController`. The `password` attribute uses Laravel's `hashed` cast (bcrypt by default). Login records the IP address and timestamp via `User::updateLastLogin()`.

#### 7.1.2 CAPS SSO (Single Sign-On)

The primary authentication method for production. Implemented in `CaseySsoController`:

1. CAPS frontend redirects the user to `/auth/casey-sso?token=<jwt>` (GET) or posts the token (POST).
2. `CaseyJwtService::verify()` validates the HS256 signature using the base64-decoded shared secret (`CASEY_JWT_SHARED_SECRET`). Checks: algorithm (HS256 only), signature, expiry, not-before, issued-at (with configurable leeway, default 30s), and subject claim presence.
3. The `sub` claim is treated as the employee number. A `User` record is looked up by `employee_number`.
4. **Auto-provisioning** (enabled by default via `CASEY_SSO_AUTO_PROVISION=true`): If no local user exists, one is created from JWT claims (`email`, `name`, `given_name`, `family_name`) with a random password and the configured default role (`CASEY_SSO_DEFAULT_ROLE`, default: `user`).
5. **Guard checks**: The user must be `is_active = true` and must have at least one Spatie role assigned. Users who exist in CAPS but have no Tracker roles are blocked.
6. The CAPS JWT is stored in the session (`session('caps_jwt')`) for subsequent CAPS API calls.
7. The SSO microservice is notified via `SsoSessionService::registerSession()`.
8. If no CAPS-synced municipalities/companies exist, a reference data sync is triggered automatically.

Token extraction priority: Bearer header > request body > query string.

**Silent SSO**: Supports iframe-based login (`?silent=1`) and iframe-based logout (`/auth/casey-sso-logout`). The SSO skip cookie (`casey_sso_skip`) prevents redirect loops when SSO fails.

#### 7.1.3 API Token Authentication

Two mechanisms for API access:

1. **Sanctum tokens** (`auth:sanctum`): Used by authenticated users for the `api/v1/` routes. The standard `/api/v1/user` endpoint returns the authenticated user.

2. **API Key authentication** (`auth.apikey` middleware): Used by external partners. The `X-API-Key` header format is `<prefix>.<secret>`. The secret portion is SHA-256 hashed and matched against the `api_keys.key_hash` column. Supports scope-based access control. Updates `last_used_at` on each use and sets the tenant context.

### 7.2 Authorization (RBAC)

Authorization uses Spatie Laravel Permission (v6.21) with a hierarchical role system.

#### 7.2.1 Roles

| Role | Description | Typical Permissions |
|---|---|---|
| `super-admin` | Full system access | All permissions (synced automatically) |
| `admin` | Administrative access | All except `manage permissions` |
| `manager` | Team lead access | View + create/edit uploads, view reports, manage assignments |
| `user` | Standard user | View dashboard, view/create uploads, view deadlines, view notifications |

#### 7.2.2 Permission Categories

Permissions follow the pattern `{action} {resource}`:

- **Dashboard**: `view dashboard`
- **Users**: `view users`, `create user`, `edit user`, `delete user`, `manage users`
- **Companies**: `view companies`, `create company`, `edit company`, `delete company`, `assign company`, `manage companies`
- **Municipalities**: `view municipalities`, `create municipality`, `edit municipality`, `delete municipality`, `manage municipalities`
- **Uploads**: `view uploads`, `create upload`, `edit upload`, `delete upload`, `export uploads`, `manage uploads`
- **Deadlines**: `view deadlines`, `create deadline`, `edit deadline`, `delete deadline`, `manage deadlines`
- **Submissions**: `view submissions`, `create submission`, `edit submission`, `delete submission`, `manage submissions`
- **Notifications**: `view notifications`, `manage notifications`
- **Roles & Permissions**: `view roles`, `create role`, `edit role`, `delete role`, `manage roles`, `view permissions`, `manage permissions`
- **Reports**: `view reports`, `generate reports`, `export reports`
- **Audits**: `view audits`, `manage audits`
- **Assignments**: `create assignment`, `edit assignment`, `delete assignment`, `manage assignments`

#### 7.2.3 Permission Enforcement

Permissions are enforced at three levels:

1. **Route middleware**: `->middleware('permission:view uploads')` on route definitions.
2. **Controller authorization**: `$this->authorize('view uploads')` in controller methods.
3. **Frontend visibility**: Shared `auth.user.permissions` array enables conditional rendering in Vue components.

### 7.3 SSO Session Microservice

The SSO session microservice is a lightweight Node.js application that maintains a registry of active sessions across CAPS and the Submission Tracker.

**Endpoints** (consumed by `SsoSessionService`):

| Method | Path | Purpose |
|---|---|---|
| POST | `/sessions` | Register a new session |
| GET | `/sessions/{employeeNumber}` | Check session existence |
| GET | `/sessions` | List all active sessions |
| DELETE | `/sessions/{employeeNumber}?source=tracker` | Remove a session |

**Client configuration**:
- Timeout: 3 seconds
- Connect timeout: 2 seconds
- Authentication: `X-SSO-Key` header with shared secret

**Error handling**: All methods are wrapped in try/catch blocks. Network failures return `false` (register/remove), `null` (check -- interpreted as "unreachable, don't act"), or `[]` (list). This ensures the Tracker never crashes due to SSO microservice unavailability.

---

## 8. CAPS Integration Layer

The CAPS (Casey) integration is organized into four logical layers:

### 8.1 Layer 1 -- Reference Data Sync

**Service**: `CaseyReferenceDataService`
**Command**: `casey:sync-reference-data`
**Schedule**: Daily at 02:30, `withoutOverlapping(30)`, `runInBackground`

Pulls the canonical Company and Municipality master data from CAPS and upserts it into the Submission Tracker.

**Sync algorithm**:
1. Authenticate with CAPS (user JWT from session, or service account credentials with token caching).
2. Fetch the full list from the configured endpoint.
3. Normalize the response (handles Spring Pageable `data`/`content`/`items`/`results` envelopes).
4. For each row:
   - Extract `casey_id` from `id`, `orgId`, or `organizationId`.
   - Skip rows without a valid `casey_id`.
   - Look up existing record by `casey_id`.
   - If not found: create new record.
   - If found and dirty: update the record.
   - If found and clean: bump `casey_synced_at` timestamp only.
5. Wrap the entire operation in a database transaction.

**Company municipality resolution** (indirect mapping):
CAPS does not link companies directly to municipalities. Instead, each company has `deductionCodes`, each carrying an `areaId`. Each municipality maps to an `areaId`. The sync builds an `areaId -> municipalityCaseyId` lookup map by fetching the municipalities endpoint, then resolves each company's first area reference through this map.

**Field extraction** uses a candidate-list pattern to handle varying CAPS response shapes:
```php
$candidates = [$row['province'], data_get($row, 'province.province'), data_get($row, 'province.name'), ...];
```

**Status extraction** normalizes diverse CAPS status representations (string, boolean, integer, nested object) into `active`/`inactive`.

**Safety**: The `stringifyScalar()` helper prevents "Array to string conversion" crashes when CAPS returns a nested object where a scalar was expected.

### 8.2 Layer 2 -- Member and Policy Verification

**Service**: `CaseyMemberPolicyService`

Compares uploaded spreadsheet data against live CAPS member and policy records.

**Key methods**:

- `fetchMemberByIdNumber(string $idNumber)`: Exact member lookup using `?idNumber=X` query parameter.
- `fetchPoliciesByOrganization(string $organizationId, int $page, int $size)`: Fetches policies filtered by `?organizationId=X`. Note: the `companyId` parameter does NOT filter correctly in CAPS; `organizationId` must be used.
- `fetchAllPoliciesForCompany(string $companyId)`: Paginates through all policies for a company (500 per page, max 60 pages / 30K policies).
- `compareAgainstCaps(array $uploadedRows, string $fallbackCompanyId)`: Full comparison algorithm (see Section 17.1).

**Member indexing** (`indexMembers`): Builds a lowercase-keyed lookup by `idNumber` (SA ID), `payNumber`, and `personnelNumber`. Does NOT index by CAPS internal UUID. Deduplicates by keeping the first occurrence.

**Policy indexing** (`indexPolicies`): Builds a lowercase-keyed lookup by `policyCode` (or `policyNumber` / `policyNo`). On duplicate codes, keeps the entry with the highest `premiumAmount`.

### 8.3 Layer 3 -- Status Echo-back (Webhooks)

**Controller**: `Api\V1\CapsWebhookController`
**Endpoint**: `POST /api/v1/webhooks/caps`

Receives webhook events from CAPS when payment batch status changes. No Sanctum token required -- uses HMAC-SHA256 signature verification instead.

**Security**:
- `X-Caps-Signature` header must contain `HMAC-SHA256(raw_body, CAPS_WEBHOOK_SECRET)`.
- Timing-safe comparison via `hash_equals()`.

**Idempotency**:
- Each event carries a unique `eventId` (or one is generated from `event_type|batchId|reference|occurredAt`).
- Duplicate events are acknowledged with HTTP 200 without reprocessing.

**Event type mapping**:

| CAPS Event | Upload caps_status |
|---|---|
| `payment_batch.imported` | `imported` |
| `payment_batch.allocated` | `allocated` |
| `payment_batch.failed` | `failed` |
| `payment_batch.exported` | `exported` |
| `refund.created` | `refund_created` |
| `refund.allocated` | `refund_allocated` |

**Upload resolution**: Matches by `caps_payment_batch_id` first, then by `reference`.

### 8.4 Layer 4 -- Premium Batch Information

**Service**: `CaseyPremiumBatchService`

Fetches detailed premium batch information from CAPS for display in the upload detail view.

### 8.5 CAPS Authentication Strategy

All CAPS API calls follow the same authentication resolution order:

1. **User's SSO JWT** (`session('caps_jwt')`): If the user logged in via SSO, their CAPS JWT is used so the API call authenticates as that user. This is the preferred method for web requests.
2. **Cached service account token**: A bearer token obtained by POSTing service account credentials (`CASEY_API_USERNAME` / `CASEY_API_PASSWORD`) to the CAPS auth endpoint. Cached in Redis for a configurable TTL (default 50 minutes). The cache key incorporates the auth URL and username for uniqueness.
3. **Basic auth fallback**: If token acquisition fails, falls back to HTTP Basic Authentication with the service account credentials.

Token response parsing is resilient, checking multiple possible response shapes: `token`, `accessToken`, `access_token`, `jwt`, `data.token`, `data.accessToken`.

### 8.6 CAPS HTTP Client Configuration

All CAPS HTTP clients share consistent configuration:

| Setting | Value |
|---|---|
| Timeout | 20-30 seconds |
| Connect timeout | 8-10 seconds |
| Retry | 2 attempts, 500ms delay, no throw |
| Accept | `application/json` |
| SSL verification | Configurable via `CASEY_VERIFY_SSL` |

---

## 9. Multi-Tenancy Architecture

### 9.1 Tenant Resolution

The `ResolveTenant` middleware resolves the active tenant for each request using `TenantResolverService`. Resolution order (first match wins):

1. **`X-Tenant` header**: Matches against `tenants.slug` where `status = 'active'`.
2. **Request hostname**: Matches against `tenant_domains.domain`, loads the associated tenant if active.
3. **Authenticated user's `tenant_id`**: Falls back to the user's assigned tenant.
4. **Default tenant**: Looks up `tenants.slug = 'default'` as the final fallback.

The resolved `Tenant` is stored in the `TenantContext` singleton (bound as a singleton in the service container) and is available throughout the request lifecycle.

### 9.2 Tenant Scoping

The `BelongsToTenant` trait automatically:

1. **On creation**: Sets `tenant_id` from `TenantContext::tenantId()` if not already set.
2. **On query**: Provides `scopeForTenant(?int $tenantId)` for explicit filtering.

Models using this trait: `User`, `Uploads`, `Company`, `Municipality`, `Audit`.

### 9.3 API Key Tenant Resolution

When the `AuthenticateApiKey` middleware validates an API key, it sets the tenant context to the API key's `tenant_id`:

```php
$this->tenantContext->setTenantId($apiKey->tenant_id);
```

This ensures all subsequent database operations in the request are scoped to the correct tenant.

---

## 10. Audit and Compliance Subsystem

### 10.1 Audit Logger

The `AuditLogger` support class provides three entry points for creating audit records:

1. **`forModelEvent(string $event, Model $model, array $oldValues, array $newValues)`**: Called by the `RecordsAuditTrail` trait. Records model-level changes with before/after snapshots.

2. **`authEvent(string $event, ?Model $subject, array $meta)`**: Records authentication events (`logged_in`, `logged_out`, `failed_sso`, `provisioned_via_sso`).

3. **`requestEvent(string $event, ?Model $subject, array $meta)`**: Called by `AuditTrailMiddleware`. Records HTTP request metadata for state-changing operations.

### 10.2 Data Sanitization

The `AuditLogger::sanitize()` method strips sensitive fields before persistence:
- `password`
- `password_confirmation`
- `remember_token`
- `external_password_hash`

The `normalize()` method handles special types:
- `DateTimeInterface` objects are converted to ISO 8601 strings.
- Eloquent `Model` instances are reduced to `{type, id}`.
- Arrays are recursively normalized.
- Objects with `toArray()` are converted; others are JSON-encoded.

### 10.3 Audit Tags

Each audit entry receives comma-separated tags built from: the event name, the route name, and the HTTP method (lowercase). Example: `request,uploads.store,post`.

### 10.4 Audit Scope

The `AuditTrailMiddleware` applies the following filtering:

- **Included**: `POST`, `PUT`, `PATCH`, `DELETE` requests.
- **Excluded**: `login.store`, `logout` routes (handled by auth events); `admin.audits.*` routes (prevents recursive logging); requests resulting in HTTP 5xx responses.

### 10.5 Model Audit Trail

The `RecordsAuditTrail` trait hooks into Eloquent's lifecycle:

- `created`: Full new attribute set recorded.
- `updated`: Only changed attributes recorded (via `getChanges()`), with original values captured via `getOriginal()`. The `updated_at` timestamp is filtered out to reduce noise.
- `deleted`: Full original attribute set recorded as `oldValues`.

Models with audit trails: `User`, `Uploads`, `Company`, `Municipality`, `MunicipalityDeadline`, `UserAssignment`.

---

## 11. File Processing Pipeline

### 11.1 Upload Flow

An upload consists of up to three file categories:

1. **Original Files** (required): Email files (`.eml`, `.msg`) or spreadsheets (`.xls`, `.xlsx`, `.xlsm`, `.xlsb`, `.csv`). Stored as an array of paths in `original_file_path` (JSON column). Supports multiple files per upload.

2. **Workings File** (required for completion): The working spreadsheet used to prepare the submission. Single file stored in `workings_file_path`.

3. **Systems Import File** (required for completion): The file imported into the external systems. Single file stored in `systems_import_file_path`.

The upload process is split into two phases:
- **Phase 1 -- Initial Upload**: User selects company, municipality, and attaches original files. Upload is created with status `Pending`.
- **Phase 2 -- Completion**: User navigates to the completion form (`/uploads/{id}/complete`), attaches workings and systems import files, and the status transitions to `Completed`.

### 11.2 File Storage

Files are stored on the local filesystem via Laravel's Storage facade. Each upload gets a unique storage path. The `Uploads` model provides URL accessor attributes (`getOriginalFileUrlsAttribute`, `getWorkingsFileUrlAttribute`, `getSystemsImportFileUrlAttribute`) that generate download URLs using named routes.

### 11.3 File Preview

The `UploadsController` provides inline preview capabilities:

#### Spreadsheet Preview
- **Route**: `GET /uploads/{upload}/preview-data/{index?}`
- **Engine**: PhpOffice PhpSpreadsheet (`IOFactory`)
- Returns JSON with sheet names, headers, and row data for rendering in the Vue `ViewSpreadsheet.vue` component.

#### Email Preview (EML)
- **Route**: `GET /uploads/{upload}/view-email-data/{index?}`
- **Engine**: `php-mime-mail-parser` (`PhpMimeMailParser\Parser`)
- Extracts headers (from, to, cc, subject, date), HTML/text body, and attachment metadata.
- Attachments are downloadable via `GET /uploads/{upload}/view-email/{index}/attachments/{attachmentIndex}`.

#### Email Preview (MSG)
- **Route**: Same as EML preview (auto-detected by file extension).
- **Engine**: `hfig/mapi` for parsing Outlook MSG format.
- **Conversion**: `GET /uploads/{upload}/convert-msg-to-eml/{index}` converts MSG to EML format. Converted files are cached in `converted_eml_paths` (JSON column) to avoid repeated conversion.

### 11.4 Auto-Verification (CAPS Comparison)

After upload, the system can automatically verify uploaded data against CAPS:

- **Route**: `POST /uploads/{upload}/compare-caps`
- **Methods**: `parseSpreadsheetForVerification` extracts member IDs, personnel numbers, policy codes, and premium amounts from the uploaded spreadsheet.
- `runAutoVerification` calls `CaseyMemberPolicyService::compareAgainstCaps()` with the parsed rows and the upload's company `casey_id`.
- Results are stored in the `caps_verification` JSON column and `caps_verified_at` timestamp.

---

## 12. Notification System

### 12.1 Notification Types

| Notification Class | Trigger | Description |
|---|---|---|
| `NewUploadNotification` | Upload created | Notifies relevant users of a new upload |
| `UploadCreated` | Upload created | Admin notification variant |
| `DeadlineCreated` | Deadline created | Notifies assigned users of a new deadline |
| `DeadlineUpdated` | Deadline modified | Notifies assigned users of deadline changes |
| `DeadlineDeleted` | Deadline removed | Notifies assigned users of deadline removal |
| `DeadlineAssigned` | User assigned to deadline | Notifies the assigned user |
| `AssignmentRemoved` | Assignment removed | Notifies the user their assignment was removed |

### 12.2 Notification Channels

Notifications use Laravel's `database` channel, storing entries in the `notifications` table. The `HandleInertiaRequests` middleware shares the `unread_count` with every page load.

### 12.3 Notification Management Routes

| Method | Route | Action |
|---|---|---|
| GET | `/notifications` | List all notifications |
| POST | `/notifications/{id}/mark-as-read` | Mark single notification as read |
| POST | `/notifications/mark-all-as-read` | Mark all notifications as read |
| DELETE | `/notifications/{id}` | Delete single notification |
| DELETE | `/notifications/clear-all` | Delete all notifications |

---

## 13. API Reference

### 13.1 Web Routes (Inertia)

All web routes require the `auth` middleware and individual permission middleware as noted.

#### Authentication
| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `/login` | `login` | guest |
| POST | `/login` | `login.store` | guest |
| GET/POST | `/auth/casey-sso` | `auth.casey.sso` | -- |
| GET | `/auth/casey-sso-logout` | `auth.casey.sso.logout` | -- |
| GET | `/auth/casey-sso-ok` | `auth.casey.sso.ok` | -- |
| POST | `/logout` | `logout` | auth |
| GET | `/sso-logout` | `sso.logout` | auth |

#### Dashboard
| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `/dashboard` | `dashboard` | `view dashboard` |
| GET | `/api/dashboard/recent-uploads` | -- | `view dashboard` |
| GET | `/api/dashboard/search-uploads` | -- | `view dashboard` |
| GET | `/api/dashboard/stats` | -- | `view dashboard` |

#### Uploads
| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `/uploads` | `uploads.index` | `view uploads` |
| POST | `/uploads` | `uploads.store` | `create upload` |
| DELETE | `/uploads/{upload}` | `uploads.destroy` | `delete upload` |
| GET | `/uploads/{upload}/complete` | `uploads.complete` | `create upload` |
| POST | `/uploads/{upload}/complete` | `uploads.complete.submit` | `create upload` |
| GET | `/uploads/history` | `uploads.history` | `view uploads` |
| GET | `/uploads/export` | `uploads.export` | `export uploads` |
| GET | `/uploads/existing/{municipality}` | `uploads.existing` | `view uploads` |
| GET | `/uploads/{upload}/download/{which}/{index?}` | `uploads.download` | `view uploads` |
| GET | `/uploads/{upload}/preview/{index?}` | `uploads.preview` | `view uploads` |
| GET | `/uploads/{upload}/preview-data/{index?}` | `uploads.preview-data` | `view uploads` |
| GET | `/uploads/{upload}/view-email/{index?}` | `uploads.view-email` | `view uploads` |
| GET | `/uploads/{upload}/view-email-data/{index?}` | `uploads.view-email-data` | `view uploads` |
| GET | `/uploads/{upload}/view-email/{index}/attachments/{attachmentIndex}` | `uploads.view-email.attachment` | `view uploads` |
| GET | `/uploads/{upload}/convert-msg-to-eml/{index}` | `uploads.convert-msg-to-eml` | `view uploads` |
| POST | `/uploads/{upload}/compare-caps` | `uploads.compare-caps` | `view uploads` |

#### Deadlines
| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `/deadlines/municipalities` | `deadlines.municipalities.index` | `view deadlines` |
| GET | `/deadlines/companies` | `deadlines.companies.index` | `view deadlines` |
| GET | `/deadlines/companies/{company}/submissions` | `deadlines.companies.submissions` | `view deadlines` |
| GET | `/calendar/events` | `calendar.events` | `view deadlines` |
| POST | `/deadlines/municipalities` | `deadlines.municipalities.store` | `create deadline` |
| PUT | `/deadlines/municipalities/{deadline}` | `deadlines.municipalities.update` | `edit deadline` |
| DELETE | `/deadlines/municipalities/{deadline}` | `deadlines.municipalities.destroy` | `delete deadline` |
| GET | `/deadlines/municipalities/{municipality}/companies` | `deadlines.municipality.companies` | `create deadline` |
| GET | `/deadlines/assignments` | `deadlines.assignments.get` | `view deadlines` |
| POST | `/deadlines/assignments` | `deadlines.assignments.store` | `create deadline` |
| PUT | `/deadlines/assignments/{assignment}` | `deadlines.assignments.update` | `edit deadline` |
| DELETE | `/deadlines/assignments/{assignment}` | `deadlines.assignments.destroy` | `delete deadline` |
| POST | `/deadlines/create-with-assignments` | `deadlines.create-with-assignments` | `create deadline` |
| GET | `/api/deadlines/sync-assignments` | `deadlines.assignments.sync` | `view deadlines` |
| GET | `/api/deadlines/upcoming` | `deadlines.upcoming` | `view deadlines` |
| GET | `/api/deadlines/pending-submissions` | `deadlines.pending-submissions` | `view deadlines` |
| GET | `/api/deadlines/municipalities` | `deadlines.municipalities.get` | `create deadline` |
| GET | `/api/deadlines/users` | `deadlines.users.get` | `create deadline` |

#### Notifications
| Method | URI | Name | Permission |
|---|---|---|---|
| GET | `/notifications` | `notifications.index` | auth |
| POST | `/notifications/{id}/mark-as-read` | `notifications.markAsRead` | auth |
| POST | `/notifications/mark-all-as-read` | `notifications.markAllAsRead` | auth |
| DELETE | `/notifications/{id}` | `notifications.destroy` | auth |
| DELETE | `/notifications/clear-all` | `notifications.clearAll` | auth |

#### Admin Routes (prefix: `/admin`, name prefix: `admin.`)
| Method | URI | Name | Permission |
|---|---|---|---|
| GET/POST/PUT/DELETE | `/admin/users/*` | `admin.users.*` | `manage users` |
| POST | `/admin/users/{user}/assignments` | `admin.users.assignments.store` | `manage users` |
| DELETE | `/admin/users/{user}/assignments/{assignment}` | `admin.users.assignments.destroy` | `manage users` |
| GET/POST/PUT/DELETE | `/admin/roles/*` | `admin.roles.*` | `manage roles` |
| POST | `/admin/roles/{role}/permissions` | `admin.roles.permissions.update` | `manage roles` |
| GET | `/admin/permissions/data` | `admin.permissions.data` | `manage permissions` |
| GET/POST/PUT/DELETE | `/admin/companies/*` | `admin.companies.*` | `view/manage companies` |
| GET/POST/PUT/DELETE | `/admin/municipalities/*` | `admin.municipalities.*` | `view/manage municipalities` |
| GET | `/admin/reports/*` | `admin.reports.*` | `view reports` |
| POST | `/admin/caps-sync` | `admin.caps-sync.run` | `manage companies` |
| GET | `/admin/caps-sync/status` | `admin.caps-sync.status` | `manage companies` |
| GET | `/admin/audits/*` | `admin.audits.*` | `view audits` |

### 13.2 API Routes (`/api/v1/`)

#### CAPS Webhook (No Auth)
| Method | URI | Name | Authentication |
|---|---|---|---|
| POST | `/api/v1/webhooks/caps` | `api.webhooks.caps` | HMAC-SHA256 signature |

#### Sanctum-Authenticated Routes
| Method | URI | Description |
|---|---|---|
| GET | `/api/v1/user` | Current authenticated user |
| GET | `/api/v1/roles` | List roles |
| GET | `/api/v1/roles/{id}` | Show role |
| GET | `/api/v1/companies` | List companies |
| GET | `/api/v1/companies/{id}` | Show company |
| GET | `/api/v1/municipalities` | List municipalities |
| GET | `/api/v1/municipalities/{id}` | Show municipality |
| GET | `/api/v1/deadlines` | List deadlines |
| GET | `/api/v1/deadlines/{id}` | Show deadline |
| GET | `/api/v1/uploads` | List uploads |
| GET | `/api/v1/uploads/{id}` | Show upload |
| GET | `/api/v1/uploads/premium-batch` | Premium batch detailed info |
| GET | `/api/v1/tenants/current` | Current tenant info |
| PATCH | `/api/v1/tenants/current/settings` | Update tenant settings |
| GET/POST/DELETE | `/api/v1/api-keys` | API key management |
| GET/POST | `/api/v1/workflows` | Workflow management |
| POST | `/api/v1/workflows/{id}/publish` | Publish workflow |
| POST | `/api/v1/workflows/{id}/instances` | Create workflow instance |
| GET | `/api/v1/integrations` | List integrations |
| POST | `/api/v1/integrations/{provider}/connect` | Connect integration |
| POST | `/api/v1/integrations/{id}/sync` | Sync integration |
| GET | `/api/v1/integrations/{id}/health` | Integration health check |
| GET | `/api/v1/events` | Event log |
| POST | `/api/v1/webhooks/replay/{id}` | Replay webhook |
| GET | `/api/v1/ops/failed-jobs` | List failed jobs |
| POST | `/api/v1/ops/failed-jobs/{uuid}/retry` | Retry failed job |

#### Partner API (API Key Authenticated)
| Method | URI | Authentication |
|---|---|---|
| GET | `/api/v1/partner/events` | `auth.apikey:*` |
| POST | `/api/v1/partner/integrations/{id}/sync` | `auth.apikey:*` |
| POST | `/api/v1/partner/webhooks/replay/{id}` | `auth.apikey:*` |

Rate limited by `throttle:tenant-api`.

### 13.3 Fallback Route

All unmatched routes render `Errors/404` via Inertia:
```php
Route::fallback(fn () => Inertia::render('Errors/404', ['status' => 404]));
```

---

## 14. Console Commands and Scheduled Tasks

### 14.1 Artisan Commands

#### `casey:sync-reference-data`

**Signature**: `casey:sync-reference-data {--only= : Restrict to "companies" or "municipalities"}`

**Description**: Pulls the authoritative Company/Municipality lists from CAPS into the Submission Tracker.

**Usage**:
```bash
php artisan casey:sync-reference-data                    # Sync both
php artisan casey:sync-reference-data --only=companies   # Companies only
php artisan casey:sync-reference-data --only=municipalities  # Municipalities only
```

**Output**: Displays fetched/created/updated/skipped counts for each entity type.

**Exit codes**: `0` (SUCCESS) if all synced entities report `ok = true`; `1` (FAILURE) otherwise.

#### `permissions:sync`

**Signature**: `permissions:sync`

**Description**: Creates all application permissions and roles with their default permission sets. Idempotent -- uses `firstOrCreate` for permissions and roles, and `syncPermissions` for role-permission assignments.

**Usage**:
```bash
php artisan permissions:sync
```

### 14.2 Scheduled Tasks

Defined in `routes/console.php`:

| Command | Schedule | Options | Log |
|---|---|---|---|
| `casey:sync-reference-data` | Daily at 02:30 | `withoutOverlapping(30)`, `runInBackground` | `storage/logs/casey-reference-data-sync.log` |

**`withoutOverlapping(30)`**: Prevents concurrent runs. If a sync is already running, the new invocation is skipped. The lock expires after 30 minutes as a safety valve.

**`runInBackground`**: The command runs in a background process so the scheduler worker remains responsive to other scheduled tasks.

**`appendOutputTo`**: All command output is appended to a dedicated daily log file for troubleshooting sync issues.

### 14.3 Development Script

The `composer dev` script uses `concurrently` to run four processes in parallel:

```bash
npx concurrently \
  "php artisan serve" \
  "php artisan queue:listen --tries=1" \
  "php artisan pail --timeout=0" \
  "npm run dev" \
  --names=server,queue,logs,vite --kill-others
```

---

## 15. Caching Strategy

### 15.1 CAPS API Token Cache

The CAPS authentication token is cached in Redis to avoid re-authenticating on every API call:

- **Cache key**: `casey_api_token_{md5(authUrl|username)}`
- **TTL**: Configurable via `services.casey.token_cache_ttl` (default: 50 minutes)
- **Invalidation**: Token is re-fetched on cache miss. If the cached token expires or becomes invalid, the next API call triggers re-authentication.
- **Bypass**: When a user's SSO JWT is available in the session, it takes priority over the cached service account token.

### 15.2 Session-Based Caching

- **SSO check rate limiting**: `sso_check_at` session key stores the last SSO check timestamp. Checks are throttled to once every 15 seconds per user session.
- **CAPS JWT persistence**: The user's CAPS JWT is stored in the session (`caps_jwt`) for the duration of the login session.
- **Employee number breadcrumb**: `sso_last_employee` stores the last SSO employee number for session recovery.

### 15.3 Framework Caching

- **Configuration cache**: `php artisan config:cache` for production.
- **Route cache**: `php artisan route:cache` for production.
- **View cache**: Compiled Blade templates cached automatically.
- **Spatie Permission cache**: Role/permission lookups are cached by the Spatie package.

### 15.4 Converted File Cache

MSG-to-EML conversions are stored in the `converted_eml_paths` JSON column on the `uploads` table, avoiding repeated conversion of the same MSG file.

---

## 16. Error Handling Patterns

### 16.1 CAPS API Error Handling

All CAPS API calls follow a consistent error handling pattern:

```php
try {
    $response = $client->get($requestUrl, $params);

    if ($response->failed()) {
        Log::warning("CAPS {$context} API request failed", [...]);
        return ['ok' => false, 'message' => "Failed to fetch ... (HTTP {$response->status()}).", 'data' => []];
    }

    return ['ok' => true, 'data' => $response->json(), ...];
} catch (\Throwable $e) {
    Log::error("CAPS {$context} API exception", [...]);
    return ['ok' => false, 'message' => "Unable to reach CAPS API for {$context}.", 'data' => []];
}
```

Key characteristics:
- **Result envelope**: Every CAPS method returns `['ok' => bool, 'data' => mixed, 'message' => ?string]`.
- **No exceptions thrown to callers**: All exceptions are caught, logged, and converted to structured error responses.
- **Retry with backoff**: HTTP clients use `->retry(2, 500, null, false)` (2 retries, 500ms delay, no throw on final failure).
- **Graceful degradation**: If CAPS is unreachable, the Tracker continues operating with local data. Sync status is visible to admins on the dashboard.

### 16.2 SSO Error Handling

The SSO subsystem uses a three-state pattern for `checkSession()`:

- `['exists' => true, ...]`: Session found -- proceed normally.
- `['exists' => false]`: Session definitely gone (HTTP 404) -- trigger logout.
- `null`: Microservice unreachable -- do nothing (graceful degradation).

This prevents network blips from logging users out.

### 16.3 JWT Verification Errors

`CaseyJwtService::verify()` throws `RuntimeException` for any validation failure with specific messages:
- `CASEY_JWT_SHARED_SECRET is not configured.`
- `Malformed JWT.`
- `JWT header is not valid JSON.`
- `Unsupported JWT algorithm: {alg}.`
- `JWT signature mismatch.`
- `JWT has expired.`
- `JWT is not yet valid.`
- `JWT issued-at is in the future.`
- `JWT is missing the subject claim.`
- `CASEY_JWT_SHARED_SECRET is not valid base64.`

Callers catch `RuntimeException` and handle gracefully (log + redirect with error message).

### 16.4 SSO Failure Recovery

When SSO login fails, the `CaseySsoController::fail()` method:
1. Logs a `failed_sso` audit event.
2. Sets a `casey_sso_skip` cookie (configurable duration, default 60 seconds).
3. Redirects to `/login?sso=skip` with error message.
4. The login page checks for this cookie/parameter and does NOT auto-redirect back to SSO, breaking the redirect loop.

### 16.5 Upload Error Handling

The `UploadsController` wraps operations in try/catch blocks and returns appropriate Inertia responses or JSON errors. File processing errors (corrupt spreadsheet, unparseable email) are caught and reported to the user without crashing the application.

---

## 17. Key Algorithms

### 17.1 CAPS Comparison Algorithm

The `CaseyMemberPolicyService::compareAgainstCaps()` method implements a multi-company verification algorithm:

**Step 1 -- Group rows by company**:
```
For each uploaded row:
  If row has companyName: group by companyName
  Else: group under '__fallback__'
```

**Step 2 -- Resolve company names to casey_id**:
```
For each unique company name:
  Try exact match: Company::where('name', name)
  Try base name (strip trailing " - Suffix"): LIKE baseName%
  Try contains match: LIKE %name%
  Try first 3 significant words (>2 chars): LIKE %prefix%
  If no match: use fallbackCompanyId
```

**Step 3 -- Fetch CAPS data per resolved company** (deduplicated):
```
For each unique casey_id:
  Collect unique SA ID numbers from this company's rows
  Fetch members: 1 API call per unique idNumber (exact match)
  Fetch policies: paginated by organizationId (500 per page, max 30K)
  Index members by: idNumber, payNumber, personnelNumber (lowercase)
  Index policies by: policyCode (lowercase, dedup by highest premium)
```

**Step 4 -- Compare each row**:
```
For each uploaded row:
  Resolve to the correct company's CAPS data

  MEMBER CHECK:
    Look up by memberId (SA ID) in CAPS member index
    If not found: look up by personnelNumber
    Result: member_found or member_not_found

  POLICY CHECK:
    Look up by policyCode in CAPS policy index
    If found:
      Result: policy_found
      PREMIUM CHECK:
        Compare uploaded premiumAmount vs CAPS premiumAmount
        If difference > 0.01: premium_mismatch (records both values)
    If not found:
      Result: policy_not_found
```

### 17.2 Reference Data Area-to-Municipality Mapping

Company-to-Municipality resolution traverses an indirect chain:

```
company.deductionCodes[i].areaId  -->  municipality.areaId  -->  municipality.casey_id
```

The `buildAreaMunicipalityMap()` method:
1. Fetches the full municipalities list from CAPS.
2. For each municipality, extracts its `areaId`.
3. Builds a `Map<areaCaseyId, municipalityCaseyId>`.
4. During company sync, each company's first resolved `areaId` is looked up in this map.

### 17.3 JWT Verification Algorithm

The `CaseyJwtService::verify()` method implements HS256 verification from scratch:

```
1. Decode the base64-encoded shared secret from config
2. Split the JWT into 3 parts (header.payload.signature)
3. base64url-decode the header -> validate alg = HS256
4. Compute: expected = HMAC-SHA256(header.payload, decodedSecret)
5. Timing-safe compare: hash_equals(expected, decodedSignature)
6. base64url-decode the payload -> parse JSON claims
7. Check exp: (now - leeway) < exp
8. Check nbf: (now + leeway) >= nbf
9. Check iat: (now + leeway) >= iat
10. Check sub: must be present and non-empty
11. Return claims array
```

### 17.4 Calendar Event Color Coding

The `ShareCalendarEvents` middleware uses a days-until-deadline algorithm:

| Condition | Color | Tailwind Class |
|---|---|---|
| Overdue (< 0 days) | Red | `#ef4444` (red-500) |
| Due today (0 days) | Orange | `#f97316` (orange-500) |
| Due tomorrow (1 day) | Yellow | `#eab308` (yellow-500) |
| Future (> 1 day) | Green | `#059669` (green-600) |

### 17.5 Tenant Resolution Algorithm

The `TenantResolverService::resolveFromRequest()` method follows a priority chain:

```
1. Check X-Tenant header -> match tenants.slug (active only)
2. Check request hostname -> match tenant_domains.domain -> load tenant (active only)
3. Check authenticated user -> load by user.tenant_id (active only)
4. Fallback -> load tenants.slug = 'default' (active only)
5. Return null if no tenant resolved
```

### 17.6 API Key Authentication Algorithm

```
1. Extract X-API-Key header
2. Validate format contains '.' separator
3. Split into [prefix, secret]
4. Hash: sha256(secret)
5. Lookup: api_keys.key_hash = hash
6. Validate: key exists AND isActive()
7. Check scope: requiredScope = '*' OR key.hasScope(requiredScope)
8. Update: last_used_at = now()
9. Set tenant context: tenantContext.setTenantId(key.tenant_id)
```

---

## 18. Naming Conventions

### 18.1 PHP Naming

| Element | Convention | Example |
|---|---|---|
| Classes | PascalCase | `UploadsController`, `CaseyMemberPolicyService` |
| Methods | camelCase | `fetchMemberByIdNumber()`, `syncMunicipalities()` |
| Properties | camelCase | `$tenantId`, `$baseUrl` |
| Constants | UPPER_SNAKE_CASE | `TIMEOUT`, `EVENT_STATUS_MAP` |
| Config keys | snake_case, dot-separated | `services.casey.base_url` |
| Database columns | snake_case | `casey_id`, `municipality_id`, `caps_payment_batch_id` |
| Database tables | snake_case, plural | `uploads`, `companies`, `municipality_deadlines` |
| Middleware aliases | PascalCase | `ResolveTenant`, `AuthenticateApiKey` |
| Traits | PascalCase, descriptive | `BelongsToTenant`, `RecordsAuditTrail` |
| Scopes | `scope` prefix + PascalCase | `scopeAccessibleToUser()`, `scopeByStatus()` |

### 18.2 JavaScript/Vue Naming

| Element | Convention | Example |
|---|---|---|
| Components | PascalCase `.vue` files | `Dashboard.vue`, `ViewEmail.vue` |
| Pages | PascalCase in `Pages/` directory | `Pages/Uploads/Index.vue` |
| Composables | `use` prefix + camelCase | `usePage()`, `useForm()` |
| Props | camelCase | `capsSync`, `municipalitiesWithDeadlines` |
| Events | kebab-case | `@mark-as-read`, `@deadline-created` |
| CSS classes | Tailwind utility classes | `bg-red-500`, `text-sm` |

### 18.3 Route Naming

Routes follow a `{resource}.{action}` pattern:

- Resource routes: `uploads.index`, `uploads.store`, `uploads.destroy`
- Nested routes: `deadlines.municipalities.index`, `admin.users.assignments.store`
- API routes: `deadlines.upcoming`, `calendar.events`
- Auth routes: `auth.casey.sso`, `login.store`

### 18.4 Service Naming

CAPS-related services are prefixed with `Casey`:
- `CaseyJwtService` -- JWT verification
- `CaseyReferenceDataService` -- Reference data sync
- `CaseyMemberPolicyService` -- Member/policy verification
- `CaseyPremiumBatchService` -- Premium batch info

Internal services use descriptive names:
- `TenantContext` -- Request-scoped tenant state
- `TenantResolverService` -- Tenant resolution logic
- `SsoSessionService` -- SSO microservice client
- `EventTimelineService` -- Event recording
- `AuditLogger` -- Audit trail writing (support class, not service)

---

## 19. Build and Development Toolchain

### 19.1 Development Workflow

Start the full development environment:
```bash
composer dev
```

This runs concurrently:
1. **Laravel dev server** (`php artisan serve`) -- HTTP server on port 8000
2. **Queue worker** (`php artisan queue:listen --tries=1`) -- Processes queued jobs
3. **Log viewer** (`php artisan pail --timeout=0`) -- Real-time log output
4. **Vite dev server** (`npm run dev`) -- HMR on port 5173

### 19.2 Build Commands

| Command | Purpose |
|---|---|
| `npm run dev` | Start Vite dev server with HMR |
| `npm run build` | Production build (minified, tree-shaken) |
| `npm run preview` | Preview production build locally |
| `composer test` | Clear config + run PHPUnit test suite |

### 19.3 Vite Build Configuration

- **Entry points**: `resources/css/app.css`, `resources/js/app.js`
- **Output**: `public/build/` (manifest-based, cache-busted)
- **Plugins**: Laravel Vite Plugin, Vue SFC compiler, Tailwind CSS Vite plugin
- **Path alias**: `@` = `resources/js/`
- **Watch exclusions**: `node_modules`, `.cache`, `storage`, `vendor`

### 19.4 Code Quality

| Tool | Configuration | Scope |
|---|---|---|
| Laravel Pint | Default PSR-12 | PHP files |
| PHP-CS-Fixer | Project `.php-cs-fixer.php` | PHP files |
| ESLint v9 | `eslint-plugin-vue`, `eslint-config-prettier` | JS/Vue files |
| Prettier | `prettier-plugin-tailwindcss` | JS/Vue/CSS files |
| Stylelint | `stylelint-config-recommended-vue`, `stylelint-config-tailwindcss` | CSS/Vue styles |

---

## 20. Environment Configuration

### 20.1 Required Environment Variables

#### Application Core
| Variable | Description |
|---|---|
| `APP_NAME` | Application display name |
| `APP_ENV` | Environment (`local`, `staging`, `production`) |
| `APP_KEY` | Encryption key (base64-encoded) |
| `APP_DEBUG` | Debug mode toggle |
| `APP_URL` | Application base URL |

#### Database
| Variable | Description |
|---|---|
| `DB_CONNECTION` | Database driver (`mysql`) |
| `DB_HOST` | MySQL host |
| `DB_PORT` | MySQL port (default: 3306) |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database username |
| `DB_PASSWORD` | Database password |

#### Redis
| Variable | Description |
|---|---|
| `REDIS_HOST` | Redis server host |
| `REDIS_PORT` | Redis server port |
| `REDIS_PASSWORD` | Redis authentication password |

#### CAPS Integration
| Variable | Description |
|---|---|
| `CASEY_API_BASE_URL` | CAPS API base URL |
| `CASEY_API_USERNAME` | CAPS service account username (fallback) |
| `CASEY_API_PASSWORD` | CAPS service account password (fallback) |
| `CASEY_AUTH_ENDPOINT` | CAPS authentication endpoint (default: `/casey/auth/sign-in`) |
| `CASEY_MUNICIPALITIES_ENDPOINT` | Municipalities data endpoint |
| `CASEY_COMPANIES_ENDPOINT` | Companies data endpoint |
| `CASEY_MEMBERS_ENDPOINT` | Members API endpoint (default: `/v1/member/api/members`) |
| `CASEY_POLICIES_ENDPOINT` | Policies API endpoint (default: `/v1/premiums/status/fetch`) |
| `CASEY_PREMIUM_BATCH_ENDPOINT` | Premium batch endpoint (default: `/casey/v1/premiums/batch/detailed_info`) |
| `CASEY_VERIFY_SSL` | Enable/disable SSL verification for CAPS calls |
| `CASEY_SYNC_ONLY_ACTIVE` | Only sync active records from CAPS (default: true) |
| `CASEY_SYNC_DEFAULT_PROVINCE` | Default province for records missing province data |
| `CASEY_TOKEN_CACHE_TTL` | Token cache TTL in minutes (default: 50) |

#### CAPS SSO
| Variable | Description |
|---|---|
| `CASEY_SSO_ENABLED` | Enable/disable SSO functionality |
| `CASEY_JWT_SHARED_SECRET` | Base64-encoded HS256 shared secret |
| `CASEY_JWT_LEEWAY_SECONDS` | Clock skew tolerance in seconds (default: 30) |
| `CASEY_SSO_AUTO_PROVISION` | Auto-create users on first SSO login (default: true) |
| `CASEY_SSO_DEFAULT_ROLE` | Role assigned to auto-provisioned users (default: `user`) |
| `CASEY_SSO_REDIRECT_ROUTE` | Post-login redirect route name (default: `dashboard`) |
| `CASEY_SSO_SKIP_SECONDS` | SSO skip cookie duration on failure (default: 60) |
| `CASEY_SSO_SERVICE_URL` | SSO session microservice URL (default: `http://localhost:4000`) |
| `CASEY_SSO_API_SECRET` | SSO microservice shared secret |

#### CAPS Webhook
| Variable | Description |
|---|---|
| `CAPS_WEBHOOK_SECRET` | HMAC-SHA256 shared secret for webhook verification |

---

## 21. Security Considerations

### 21.1 Authentication Security

- **Password hashing**: Uses Laravel's `hashed` cast (bcrypt with automatic cost factor).
- **SSO JWT verification**: HS256 with timing-safe comparison (`hash_equals`), clock skew tolerance (configurable leeway), and subject claim validation.
- **Session regeneration**: Session ID is regenerated after login (`$request->session()->regenerate()`).
- **Session invalidation**: Full session destruction on logout (`invalidate()` + `regenerateToken()`).
- **Auto-provisioned user passwords**: Generated with `Str::random(48)` -- unusable for direct login.
- **CSRF protection**: Token shared via Inertia and validated on all state-changing requests.
- **SSO skip cookie**: Prevents redirect loops when SSO fails, with configurable expiry.

### 21.2 API Security

- **Webhook HMAC verification**: `X-Caps-Signature` header validated with `hash_equals()` to prevent timing attacks.
- **API key hashing**: API key secrets are stored as SHA-256 hashes (`key_hash`), never in plaintext.
- **API key scoping**: Keys can be restricted to specific scopes; the middleware enforces scope requirements.
- **Rate limiting**: Partner API routes are throttled via `throttle:tenant-api`.
- **Sanctum tokens**: Standard Laravel Sanctum token authentication for internal API routes.

### 21.3 Data Protection

- **Audit log sanitization**: Passwords, password confirmations, remember tokens, and external password hashes are stripped from audit entries.
- **Hidden model attributes**: `User` model hides `password`, `external_password_hash`, and `remember_token` from serialization.
- **Input sanitization**: `AuditTrailMiddleware` excludes `password`, `password_confirmation`, `_token`, and `_method` from logged request data.
- **User agent truncation**: User agents are truncated to 1023 characters to prevent storage overflow attacks.

### 21.4 Access Control

- **Permission-based routes**: Every route requires specific Spatie permissions.
- **Upload access scoping**: Regular users only see their own uploads (filtered by `user_id` and assignment relationships). Admins see all.
- **Inactive user blocking**: SSO login checks `is_active` flag and blocks deactivated users.
- **Role-less user blocking**: Users with CAPS access but no Tracker roles are blocked from SSO login.
- **Tenant isolation**: All tenant-scoped queries include `tenant_id` filtering via the `BelongsToTenant` trait.

### 21.5 SSL and Transport Security

- **CAPS SSL verification**: Configurable via `CASEY_VERIFY_SSL`. Should be `true` in production.
- **SSO microservice**: Communicates over HTTP with shared secret authentication (`X-SSO-Key` header). Should be deployed behind a reverse proxy with TLS in production.

---

## 22. Performance Considerations

### 22.1 Database Query Optimization

- **Eager loading**: Controllers consistently use `->with([...])` to prevent N+1 queries. Example: `Uploads::with(['company:id,name', 'municipality:id,name', 'user:id,name,email'])`.
- **Select column limiting**: Relationships use column restrictions (e.g., `'company:id,name'`) to reduce data transfer.
- **Pagination**: All list views use cursor-based or offset pagination with configurable page sizes (12, 20, 50, 100).
- **Index utilization**: Global scopes on `Company` and `Municipality` filter by `casey_id IS NOT NULL`, leveraging database indexes.
- **Batch operations**: The reference data sync pre-loads all municipality `casey_id` mappings into memory before iterating companies, avoiding per-row lookups.

### 22.2 CAPS API Performance

- **Token caching**: Authentication tokens cached for 50 minutes, reducing auth round-trips.
- **Retry with backoff**: 2 retries with 500ms delay for transient failures.
- **Pagination**: Policy fetching uses 500-record pages with a 60-page safety cap (30K max policies).
- **Deduplication**: When multiple company names resolve to the same `casey_id`, CAPS data is fetched only once.
- **Member lookup optimization**: Uses the `?idNumber=X` exact-match filter (1 API call per unique ID) rather than fetching the entire member list.

### 22.3 Frontend Performance

- **Vite production build**: Tree-shaking, minification, and code splitting.
- **Inertia partial reloads**: Only changed page props are transferred on navigation.
- **Lazy flash messages**: Flash messages use closures (`fn () => session()->get(...)`) and are only serialized when accessed.
- **Watch exclusions**: Vite ignores `node_modules`, `.cache`, `storage`, and `vendor` directories.

### 22.4 Background Processing

- **Queue worker**: `php artisan queue:listen --tries=1` for job processing.
- **Background sync**: The daily CAPS sync runs with `runInBackground` so the scheduler remains responsive.
- **Non-overlapping**: The CAPS sync uses `withoutOverlapping(30)` to prevent concurrent runs.

### 22.5 Connection Timeouts

All external HTTP clients use aggressive timeouts to prevent request pile-up:

| Service | Timeout | Connect Timeout |
|---|---|---|
| SSO microservice | 3s | 2s |
| CAPS reference data | 30s | 8s |
| CAPS member/policy | 30s | 10s |
| CAPS premium batch | 20s | 8s |
| CAPS auth | 15s | 8s |

---

*End of Technical Documentation*
