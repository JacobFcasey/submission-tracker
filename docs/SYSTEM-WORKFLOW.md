# Submission Tracker — System Workflow Documentation

## Overview

The Submission Tracker is a Laravel/Vue application that manages file submissions for premium processing. It integrates with CAPS (Casey & Associates Payment Settlements) as the business processing engine. The Tracker handles submission intake, file management, and CAPS orchestration. CAPS handles the actual premium validation and processing.

---

## System Architecture

```
User -> Submission Tracker (Laravel + Vue)
            |
            |-- Uploads files (email, workings, systems import)
            |-- Auto-dispatches to CAPS via API
            |-- Displays CAPS processing results
            |-- User reviews and confirms (Save Premiums)
            |
            v
        CAPS (Spring Boot)
            |-- Validates file headers
            |-- Stages records (validates members, companies, policies)
            |-- Categorizes: New, Updated, Cancelled, Errors, etc.
            |-- Saves finalized premiums
            |
            v
        SSO Microservice (Node.js, port 4000)
            |-- Bidirectional session sync
            |-- CAPS login -> Tracker auto-login
            |-- Tracker logout -> CAPS session cleared
```

---

## Data Sync (CAPS -> Tracker)

### What Syncs

| Entity | CAPS Endpoint | Tracker Table | Frequency |
|--------|---------------|---------------|-----------|
| Municipalities | `/v1/admin/organization/municipalities` | `municipalities` | Every 4 hours + on login |
| Companies | `/v1/admin/organization/companies` | `companies` | Every 4 hours + on login |
| Members | `/v1/member/api/members` | `caps_members` | Daily at 02:30 |
| Policies | `/v1/premiums/status/fetch` | `caps_policies` | Daily at 02:30 |

### How Sync Works

1. **Scheduled**: `routes/console.php` runs `casey:sync-reference-data` every 4 hours for municipalities/companies, daily with `--include-members --include-policies` for full sync.

2. **On Login**: Both local login (`AuthenticatedSessionController::store`) and SSO login (`CaseySsoController::login`) trigger sync with a 4-hour cooldown. First login syncs immediately (blocking); subsequent logins sync in background.

3. **Manual**: Admin dashboard has a "Refresh Data" button that calls `POST /admin/caps-sync`.

### Sync Service: `CaseyReferenceDataService`

- Uses logged-in user's SSO JWT first, falls back to service account credentials
- Upserts by `casey_id` — CAPS is the system of record
- Locally-created rows (no `casey_id`) are untouched
- Members/policies use paginated fetches (500 per page)
- Nested CAPS objects (e.g., `memberStatus`, `area`) are properly extracted to scalar values

### CLI Command

```bash
php artisan casey:sync-reference-data                              # municipalities + companies
php artisan casey:sync-reference-data --include-members             # + members
php artisan casey:sync-reference-data --include-policies            # + policies
php artisan casey:sync-reference-data --only=members                # members only
```

---

## Upload & Premium Batch Workflow

### Phase 1: User Uploads Files

**Route**: `POST /uploads` -> `UploadsController::store`

User selects:
- Municipality
- Company
- Email files (EML/MSG)
- Workings file (spreadsheet)
- Systems import file (CSV/XLSX for CAPS)

The upload is validated against user assignments, saved to `private` disk storage, and an `uploads` record is created.

### Phase 2: Auto-Dispatch to CAPS

Immediately after upload, if a systems import file exists:

1. **Preview** (`POST /v1/premiums/preview`) — validates file headers against CAPS mandatory fields
2. **Import** (`POST /v1/premiums/import`) — CAPS stages all records, creates a `policy_batch`, validates each row against members/companies/affordability
3. **Batch Info** (`GET /v1/premiums/batch/info`) — retrieves categorized counts
4. **Category Records** (`GET /v1/premiums/batch/stage/detailed_info`) — fetches records per category with error reasons

**Important**: `organizationId` is NOT sent to CAPS import. CAPS matches companies from the `Company Name` column in the file data. Sending it forces all rows to match one company and rejects everything else.

### Phase 3: User Reviews Results

User lands on the **Batch Detail** page (`/uploads/{id}/caps-batch-detail`) showing:
- 8 stat cards: New, Updated, Cancelled, Errors, Inactive Members, Inactive Policies, Duplicates, Unaffordable
- Clickable tabs — each shows the actual records from CAPS with details
- Error tab shows the exact error reason per record (e.g., "Policy Code is in scientific notation")
- Total premium amount
- "Save Premiums" button

### Phase 4: User Saves Premiums

**Route**: `POST /uploads/{id}/save-to-caps` -> `UploadsController::saveToCaps`

Sends `POST /v1/premiums/save` with `{ stagingPolicyBatchId: batchId }` to CAPS. CAPS processes staged policies in batches of 50, creates final `policy` and `policy_status` records.

### Phase 5: Notifications

- On CAPS dispatch: User notification "CAPS batch created — X records processed"
- On CAPS save: User notification "Premiums saved to CAPS — Batch #XXXX finalized"
- On upload: User + admin notifications via `UploadCreated` and `NewUploadNotification`

---

## CAPS API Endpoints Used

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/v1/premiums/preview` | Validate file headers |
| POST | `/v1/premiums/import` | Stage records, get batchId |
| GET | `/v1/premiums/batch/info` | Batch counts (new/updated/cancelled/errors) |
| GET | `/v1/premiums/batch/stage/detailed_info` | Records per category with error messages |
| POST | `/v1/premiums/save` | Finalize batch |
| GET | `/v1/admin/organization/municipalities` | Sync municipalities |
| GET | `/v1/admin/organization/companies` | Sync companies |
| GET | `/v1/member/api/members` | Sync members |
| GET | `/v1/premiums/status/fetch` | Sync policies |
| POST | `/v1/user/login` | Service account auth |

### CAPS Criteria Values for Stage Detail

| Tab | Criteria Value |
|-----|---------------|
| New | `newPolicies` |
| Updated | `updatePolicies` |
| Cancelled | `cancelledPolicies` |
| Errors | `errors` |
| Inactive Members | `inactiveMembers` |
| Inactive Policies | `inactivePolicies` |
| Duplicates | `duplicatePolicies` |
| Unaffordable | `affordability` |

---

## Authentication

### SSO Flow

1. User logs into CAPS
2. CAPS stores JWT in SSO microservice (`POST /sessions`)
3. Tracker polls SSO microservice, detects session, auto-redirects to SSO login
4. `CaseySsoController::login` verifies JWT (HS256 shared secret), provisions/updates user
5. Tracker stores JWT in session for CAPS API calls

### Token Usage

- **SSO JWT** (session) — preferred for all CAPS API calls
- **Service Account** — fallback for CLI/scheduler contexts
- **Never hardcoded** — credentials come from `.env`

---

## Key Files Changed

### Backend (Laravel)

| File | What Changed |
|------|-------------|
| `app/Services/CapsSubmissionService.php` | Full 6-phase CAPS dispatch: preview, import, batch info, category records, save, retry |
| `app/Services/CaseyReferenceDataService.php` | Added member + policy sync, fixed nested object extraction |
| `app/Http/Controllers/UploadsController.php` | Auto-dispatch after upload, save-to-caps action, notifications |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | Auto-sync on login with 4-hour cooldown |
| `app/Http/Controllers/Auth/CaseySsoController.php` | Auto-sync on SSO login with background dispatch |
| `app/Http/Controllers/DashboardController.php` | CAPS data visible to all users (members + policies counts) |
| `app/Http/Controllers/WorkAllocationController.php` | New: work allocation page |
| `app/Models/Uploads.php` | CAPS dispatch status constants, new fields, helper methods |
| `app/Models/CapsMember.php` | New: synced CAPS member model |
| `app/Models/CapsPolicy.php` | New: synced CAPS policy model |
| `app/Jobs/ProcessCapsUpload.php` | Background CAPS processing job |
| `bootstrap/app.php` | Registered Spatie permission/role middleware |
| `phpunit.xml` | Disabled SSO in tests |

### Frontend (Vue)

| File | What Changed |
|------|-------------|
| `Pages/Uploads/History.vue` | CAPS-style table (caps-history.png), summary cards, proper theme |
| `Pages/Uploads/CapsBatchDetail.vue` | Full batch review page with 8 clickable tabs, error reasons, search |
| `Pages/Allocations/Index.vue` | New: work allocation page |
| `Pages/Dashboard.vue` | CAPS data for all users, members + policies counts, compact layout |
| `Layouts/AppLayout.vue` | Added Allocation nav link |

### Database Migrations

| Migration | Tables |
|-----------|--------|
| `2026_05_06_100000_add_caps_dispatch_columns` | Added 8 columns to `uploads` for CAPS dispatch tracking |
| `2026_05_06_110000_create_caps_members_and_policies` | Created `caps_members` and `caps_policies` tables |

---

## Configuration

All CAPS integration settings in `.env`:

```
CASEY_API_BASE_URL=http://localhost:9086
CASEY_API_USERNAME=MAK001
CASEY_API_PASSWORD=62616222
CASEY_API_AUTH_ENDPOINT=/v1/user/login
CASEY_SSO_ENABLED=true
CASEY_JWT_SHARED_SECRET=<base64>
CASEY_SSO_SERVICE_URL=http://localhost:4000
CAPS_WEBHOOK_SECRET=<secret>
```

---

## Testing

```bash
php artisan test    # 10 tests, 51 assertions — all passing
npm run build       # Vue build — clean, no warnings
```

Tests run with `CASEY_SSO_ENABLED=false` (phpunit.xml) to prevent the SSO middleware from interfering with test auth.
