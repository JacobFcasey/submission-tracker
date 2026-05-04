# Submission Tracker & CAPS – Technical Analysis and Integration Proposal

## 1. Document Purpose

This document provides a side-by-side technical analysis of two related systems and proposes a concrete way for them to work together:

- **System A – File Management (Submission Tracker)** at `C:\Users\JacobMakopo\PhpstormProjects\file_management`. This is a Laravel application whose business purpose is to track regulatory file submissions made by insurance companies to municipalities. It is referred to throughout the rest of this document as the "Submission Tracker".
- **System B – CAPS (Casey Application – Payment Settlement System)** at `C:\Users\JacobMakopo\CAPS`. This is a multi-module Java/Spring Boot platform that processes member policies, payment imports/exports, allocations and refunds for the same municipal/company ecosystem.

The two systems already share the same business domain (companies, municipalities, payments, members) but currently live independently. The integration proposal in section 4 explains how the Submission Tracker can act as the *front door* for files that ultimately end up being processed inside CAPS, without forcing either system to be rewritten.

## 2. System A – Submission Tracker (file_management)

### 2.1 Purpose

The Submission Tracker is a regulatory-compliance workflow tool. Insurance companies are required to submit monthly batches of files to municipalities by a fixed deadline. The Tracker records *who* submitted *what* for *which* municipality, *when* it was submitted, and *whether* the submission satisfies the deadline. It does not perform the downstream financial processing of those files – that is CAPS' job.

### 2.2 Tech Stack

- PHP 8.2+ on Laravel 12.0
- Vue 3.5 served via Inertia.js 2.0 (server-driven SPA), Tailwind CSS 4.1, Vite 7.1
- MySQL as the primary database, Redis available via Predis 3.3
- Authentication via Laravel Sanctum 4.2 (session for the SPA, bearer tokens for the API)
- Role-based access control via spatie/laravel-permission 6.21
- Excel reporting via Maatwebsite/Excel 3.1 + PhpSpreadsheet 1.30
- Email/MSG parsing via php-mime-mail-parser, zbateson/mail-mime-parser, hfig/mapi and webklex/php-imap, plus a separate Python FastAPI sidecar in `python-msg-parser/`
- Calendar UI via FullCalendar Vue3 6.1

### 2.3 Domain Model

The core tables (under `database/migrations` and `app/Models`) are:

- `Uploads` – the central submission record. Contains `reference`, `company_id`, `municipality_id`, `status` (Pending / Processing / Completed / Rejected), and the three file-path columns: `original_file_path[]`, `workings_file_path`, `systems_import_file_path`. Also stores `extracted_dates[]`, `system_import_date` and `reupload_reason`.
- `Municipality` – name, province, code. Acts as the regulatory body that sets deadlines.
- `Company` – name, registration_number, status, contact_email, municipality_id. The submitting party.
- `MunicipalityDeadline` – periodic deadlines per municipality with notes.
- `User` – internal staff. Carries `external_password_hash` so credentials seeded from Casey can also work.
- `UserAssignment` – joins a User to (municipality, company, deadline) so that the Tracker can show each user only their own work queue.
- `Audit` – generic audit trail captured by the `RecordsAuditTrail` concern, storing `auditable_type`, `event`, `old_values`, `new_values`.

### 2.4 Submission Workflow

A submission is created and progressed in three phases:

1. **Original email files** – the user uploads one or more MSG/EML files. The Tracker streams each file to the FastAPI sidecar (`python-msg-parser/app.py`, port 8000) which extracts subject, sender, recipients, date, body and attachments. Extracted dates are persisted on the `Uploads` row so deadline checks and downstream reconciliation have an authoritative timestamp.
2. **Workings spreadsheet** – the user uploads the calculation/working file used to derive the payment numbers.
3. **Systems-import file** – the final file that downstream payment systems (i.e. CAPS) need to ingest.

Status moves Pending → Processing → Completed (or Rejected, with `reupload_reason`). On completion a `NewUploadNotification` is written to the database for the relevant users.

### 2.5 Authentication & Access

- SPA users sign in with `employee_number` + password. A second password column (`external_password_hash`) is also checked, which is how credentials seeded from Casey continue to work.
- API consumers authenticate with Sanctum bearer tokens against `/api/v1/*`.
- Authorization is permission-based via Spatie. Routes use middleware such as `permission:view uploads` and `permission:manage users`.
- The `Uploads` model exposes `scopeAccessibleToUser` so that non-admin users only see uploads tied to their `UserAssignment` rows.

### 2.6 Existing External Integrations

- **Casey Premium Batch API.** `App\Services\CaseyPremiumBatchService` already authenticates against `{CASEY_API_BASE_URL}/casey/auth/sign-in`, caches the bearer token for 50 minutes, and calls `/casey/v1/premiums/batch/detailed_info`. The result is exposed to internal callers via `GET /api/v1/uploads/premium-batch?policy_batch_id=...`. This is the seed of the integration proposed in section 4.
- **MSG Parsing Sidecar.** A FastAPI service in `python-msg-parser/` exposes `POST /parse-msg` and `GET /health`. Laravel calls it via Guzzle whenever a `.msg` file is uploaded.

### 2.7 HTTP Surface Worth Knowing

Sanctum-protected REST API (under `routes/api.php`):

- `GET /api/v1/user`
- `GET /api/v1/companies`, `GET /api/v1/companies/{id}`
- `GET /api/v1/municipalities`, `GET /api/v1/municipalities/{id}`
- `GET /api/v1/deadlines`, `GET /api/v1/deadlines/{id}`
- `GET /api/v1/uploads`, `GET /api/v1/uploads/{id}` (search + pagination supported)
- `GET /api/v1/uploads/premium-batch`

Inertia/web routes that are useful for deep-linking from CAPS:

- `/uploads/{id}` (detail view)
- `/uploads/{id}/download/{which}/{index}` where `which` is `original|workings|systems`
- `/uploads/{id}/view-email/{index}` and `/uploads/{id}/convert-msg-to-eml/{index}`

## 3. System B – CAPS (Casey Application – Payment Settlement System)

### 3.1 Purpose

CAPS is the payment settlement and member-benefits administration platform. Once a company has submitted its monthly batch, CAPS is what actually imports the payments, allocates them against member policies, exports settlements per municipality/area, and processes refunds. Operationally it is multi-tenant: every record is scoped by Municipality, Area and Company.

### 3.2 Tech Stack

- Java 21, Spring Boot 3.2, Jetty (no Tomcat), Spring Security with JWT, Spring Data JPA, Hibernate
- Spring Cloud Sleuth for tracing, Resilience4j for circuit breakers
- PostgreSQL with Liquibase-managed schema migrations under `casey-web/src/main/resources/changelog/`
- Maven multi-module build:
  - `casey-web` – HTTP entry point: 34 REST controllers, security/JWT filters, Liquibase, profile YAMLs (`dev`, `uat`, `caseyqas`, `caseyprod`)
  - `casey-api` – business services: payment import/export, member processing, lookup services
  - `casey-commons` – shared DTOs and enums (`AppUserType` = MUNICIPALITY_USER / CASEY_USER / COMPANY_USER), `UserContext` ThreadLocal
  - `casey-persistence` – JPA entities and repositories
  - `casey-frontend` – Next.js 16 + React 18 + Material UI + Redux Toolkit + ApexCharts
  - `casey-code-coverage` – aggregates JaCoCo across modules
- JWT signing via `CaseyKeyPair.pem` (RSA private key in repo root)
- Tests use JUnit 5, Mockito, Testcontainers and LocalStack (for the SQS code paths)

### 3.3 Domain Model

Key JPA entities under `casey-persistence`:

- `Member` – idNumber (unique), firstName, surName, dateOfBirth, organizationId, areaId, memStartDate/memEndDate. Inheritance is TABLE_PER_CLASS.
- `Policy` – linked to `Member`, status tracking, monthly/yearly data.
- `PaymentImport` – requestRunId, month, year, municipalityId, paymentsBatchId, companyName.
- `PaymentExport` – policyMonth/Year, areaId, municipalityId.
- `PaymentSubmissions` – paymentSubmissionBatchId, month, year, companyId, employeeNumber, memberId, policyCode.
- `Refund` – allocations with unallocated amount tracking.
- `Organization` hierarchy – Organization → Municipality, Company, Area, Province.
- `AppUser` – username, email, userRoleId, userOrgId, userAreaIds (used for area-scoped queries).

### 3.4 Key Workflows

1. Payment Import – upload file → preview/validate headers → import to batch → allocate.
2. Payment Export – select municipalities/areas → preview against policy status → generate export batch.
3. Member Management – batch upload → staging → validation → final import + payslips.
4. Refund Processing – fetch eligible policies (unallocated amount) → allocate → track "last reference" per area/month.
5. Multi-tenancy – every query is filtered through `UserContext` so users only see their organization/area scope.

### 3.5 HTTP Surface

JWT-protected REST API under `/v1/*`. Selected endpoints (full list in `Casey.postman_collection.json`):

- Auth: `POST /v1/user/login`, `POST /v1/user/validate-token`, `POST /v1/user/create`, `POST /v1/user/{username}/clear-cache`
- Members: `/v1/member/*` (CRUD, batch import, flags, statements, payslips)
- Payments: `/v1/import-payments/*`, `/v1/export-payments/*`, `/v1/payments-submissions/summary` (filter by company / areaCode / month / year)
- Refunds: `/v1/refund/*` (eligible policies, line items, last reference by areaCode + yearMonth)
- Admin: `/v1/admin/*`, `/v1/api/documents/*`, `/v1/notes/*`, `/v1/file-config/*`
- Swagger UI at `/v1/api-docs/swagger-ui`

### 3.6 Authentication & Security

- JWT bearer tokens issued at login, validated by `JwtAuthFilter`. Tokens are signed with the RSA private key in `CaseyKeyPair.pem` and cached per username for performance.
- Default token expiry: 480 minutes (8 hours), configurable per profile.
- Passwords are BCrypt hashed (`AppUserService` / `AppUserValidator.isBCryptEncoded`).
- A superadmin path exists (config-driven username/password) whose cache is cleared per request so elevated changes are picked up immediately.
- CORS is profile-specific – e.g. `application-dev.yml` allows `http://localhost:3000` and `:3001`.

### 3.7 Existing External Integrations

- SMTP – three mail accounts (support, deductions, reports) for notifications, password reset and report delivery.
- AWS SQS – wired into tests via LocalStack; production usage is environment-dependent.
- No webhook subscribers are documented today, which is the main gap we propose to close in section 4.

## 4. Integration Proposal

### 4.1 Where the Two Systems Naturally Meet

The Submission Tracker and CAPS describe the same world – companies, municipalities, payments, members – but at different points in the lifecycle.

| Concern | Source of Truth Today | Notes |
| --- | --- | --- |
| Companies, Municipalities, Areas, Provinces | CAPS | Full org hierarchy already modelled |
| Members & Policies | CAPS | Submission Tracker has no concept of these |
| Submission events (who submitted what, when) | Submission Tracker | CAPS only sees the file *after* it has been imported |
| Original email / workings / systems-import files | Submission Tracker | Stored on the Tracker's filesystem |
| Payment batches, allocations, refunds | CAPS | The downstream processing of an accepted submission |
| Deadlines per municipality | Submission Tracker | CAPS does not track the SLA, only the data once it arrives |

The Submission Tracker therefore sits *upstream* of CAPS. A submission becomes a CAPS payment-import once it is marked Completed.

### 4.2 Recommended Integration Architecture

A three-layer integration is proposed. Each layer is independently useful and can be delivered in order without reworking the previous one.

#### Layer 1 – Reference Data Sync (CAPS → Submission Tracker)

CAPS already owns the canonical Company / Municipality / Area data. The Submission Tracker should stop maintaining this data manually and instead pull it from CAPS:

- The Submission Tracker calls `GET /v1/admin/...` (or dedicated lookup endpoints) on a scheduled job (Laravel Scheduler) to refresh `companies` and `municipalities` tables.
- Conflicts are resolved by treating CAPS as the authority. A `casey_id` column is added to the Tracker's `companies` and `municipalities` tables to hold the CAPS primary key, used for all subsequent calls.

This eliminates double-entry and guarantees that a submission references entities CAPS will recognise.

#### Layer 2 – Submission Hand-off (Submission Tracker → CAPS)

When a submission transitions to status `Completed`, the Tracker hands the systems-import file to CAPS:

1. Tracker obtains a JWT via `POST /v1/user/login` using a service account, caching the token like it already does for the Casey Premium Batch service.
2. Tracker uploads `systems_import_file_path` to `POST /v1/import-payments/upload` with the same `companyId`, `municipalityId`, month and year captured against the `Uploads` row.
3. CAPS responds with a `paymentsBatchId`. The Tracker persists this on the `Uploads` row (new column `caps_payment_batch_id`).
4. Failure modes (validation errors from CAPS) are written back to `reupload_reason` and the upload status reverts to `Processing`, prompting the user to fix and resubmit.

This makes the Tracker the single point where files enter CAPS, while preserving the audit trail (who submitted, when, against which deadline).

#### Layer 3 – Status Echo-back (CAPS → Submission Tracker)

CAPS publishes processing-lifecycle events back to the Tracker so the user-facing status reflects what actually happened downstream:

- Preferred transport: a small set of webhooks. CAPS adds a `WebhookPublisher` that fires on key events (`payment_batch.imported`, `payment_batch.allocated`, `payment_batch.failed`, `refund.created`).
- Tracker exposes a Sanctum-protected endpoint such as `POST /api/v1/webhooks/caps` that verifies an HMAC signature and updates the matching `Uploads` row by `caps_payment_batch_id`.
- If AWS SQS is preferred operationally (CAPS already has the dependency wired up via LocalStack tests), the same payloads can be published to a topic that Laravel consumes via a queued listener.

Either way, the Tracker's UI can show real CAPS processing status – not just "file delivered".

### 4.3 Authentication Strategy

The Tracker already stores `external_password_hash` and already authenticates against the Casey Premium Batch API. Two improvements are proposed:

1. **Service-to-service auth.** A dedicated `casey-bridge` user is provisioned in CAPS with a role limited to the integration endpoints (login, import-payments/upload, payments-submissions/summary, lookup endpoints). The Tracker uses this account to obtain JWTs, never user JWTs.
2. **End-user SSO (optional, later).** Because both systems already share usernames (employee_number / Casey username), CAPS can be promoted to identity provider by issuing a JWT that the Tracker validates with the public half of `CaseyKeyPair.pem`. The Tracker continues to issue its own Sanctum tokens for its SPA, bridged at login.

### 4.4 UI Cross-Linking

To make the integration visible to users without a frontend rewrite:

- On the Tracker's upload detail page, render a "View in CAPS" link when `caps_payment_batch_id` is populated, deep-linking to the CAPS payment-batch screen.
- On CAPS' payment-import detail screen, render a "View submission" link that opens `/uploads/{id}` in the Tracker, using the Tracker `reference` carried through in the upload payload.

### 4.5 Data Contract Summary

The minimum payload exchanged on hand-off (Layer 2) and echo-back (Layer 3):

Tracker → CAPS (`POST /v1/import-payments/upload`):

- `companyId` (CAPS id, resolved via `casey_id`)
- `municipalityId` (CAPS id)
- `month`, `year`
- `submissionReference` (Tracker `Uploads.reference`)
- `submittedAt` (ISO timestamp, derived from the parsed email date when available)
- file – the systems-import file as multipart upload

CAPS → Tracker (webhook body):

- `event` (`payment_batch.imported` etc.)
- `paymentsBatchId`
- `submissionReference`
- `status`
- `errors[]` (optional, populated for `*.failed` events)
- `occurredAt`

### 4.6 Delivery Phasing

1. Phase 1 – Add `casey_id` columns and a daily reference-data sync job. No user-visible change yet.
2. Phase 2 – Wire the Completed-status hand-off to CAPS. Add `caps_payment_batch_id` column and surface it in the Tracker UI.
3. Phase 3 – Implement webhook receiver and CAPS publisher; replace polling for batch status with event-driven updates.
4. Phase 4 – Optional SSO promotion using CAPS as the JWT issuer, retiring the duplicate `external_password_hash` column.

### 4.7 Risks and Mitigations

- **Reference-data drift.** Mitigated by treating CAPS as authoritative and never letting the Tracker create Companies/Municipalities locally.
- **Duplicate submissions to CAPS.** Mitigated by an idempotency key on the hand-off call (use `submissionReference` plus a hash of the file).
- **Webhook reliability.** Mitigated by storing every inbound webhook in an `integration_events` table with a unique key, and retrying with exponential backoff on the CAPS side.
- **JWT key rotation.** Mitigated by exposing the CAPS JWKS or distributing the public key via configuration management; the Tracker should refresh on a known interval.

## 5. Summary

The Submission Tracker captures the regulatory event of a company submitting files to a municipality by a deadline. CAPS turns those files into payment imports, allocations and refunds. They already share data and there is already a working integration point (Casey Premium Batch) inside the Tracker. The proposed three-layer architecture – reference-data sync, submission hand-off, status echo-back – formalises that relationship without forcing either system off its current stack, and gives end users a single coherent view from "file submitted" through to "payment allocated".
