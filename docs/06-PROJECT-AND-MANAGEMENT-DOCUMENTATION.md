# Project and Management Documentation

## Submission Tracker - Casey & Associates

**Document Version:** 1.0
**Date:** April 2026
**Classification:** Internal - Confidential
**Document Owner:** Development Team Lead
**Review Cycle:** Quarterly (next review: July 2026)
**Approval Authority:** Head of Technology, Casey & Associates

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Project Charter](#2-project-charter)
3. [Organisational Context](#3-organisational-context)
4. [Team Structure and Responsibilities](#4-team-structure-and-responsibilities)
5. [Project Timeline and Milestones](#5-project-timeline-and-milestones)
6. [Module Ownership and Decomposition](#6-module-ownership-and-decomposition)
7. [Technology Decision Register](#7-technology-decision-register)
8. [Risk Register](#8-risk-register)
9. [Quality Assurance and Metrics](#9-quality-assurance-and-metrics)
10. [Change Management Process](#10-change-management-process)
11. [Release Management](#11-release-management)
12. [Communication Plan](#12-communication-plan)
13. [Configuration Management](#13-configuration-management)
14. [Dependency Management](#14-dependency-management)
15. [Environment Strategy](#15-environment-strategy)
16. [Budget and Resource Tracking](#16-budget-and-resource-tracking)
17. [Stakeholder Register](#17-stakeholder-register)
18. [Lessons Learned](#18-lessons-learned)
19. [Future Roadmap](#19-future-roadmap)
20. [Document Control](#20-document-control)

---

## 1. Executive Summary

The Submission Tracker is a bespoke enterprise web application developed by Casey & Associates to manage the end-to-end lifecycle of municipal payroll deduction file submissions. The project was initiated in September 2025 to replace manual, spreadsheet-based tracking of file submissions between South African municipalities and deduction companies (insurance providers, lenders, and other financial services entities).

Over the course of seven months (September 2025 to April 2026), the project has progressed from initial core table design through to a production-ready system with full CAPS integration, single sign-on, multi-tenancy support, CAPS member/policy verification, and a comprehensive audit trail. The system now serves as the authoritative front door for all file submissions entering the Casey ecosystem, sitting upstream of the CAPS (Casey Application Platform System) payment processing platform.

### Key Achievements

| Achievement | Date | Impact |
|-------------|------|--------|
| Core platform delivered | October 2025 | Replaced manual file tracking for all municipalities |
| API layer and file conversion | January 2026 | Enabled third-party integrations and standardised email formats |
| CAPS user authentication bridge | February 2026 | Unified credential management across platforms |
| Full CAPS integration | April 2026 | Real-time verification, SSO, webhooks, and reference data sync |
| Multi-tenancy architecture | April 2026 | Organisational isolation for future scaling |

### Current Status

**Project Phase:** Production-ready
**Current Version:** 1.0 (April 2026)
**System Health:** Operational
**Outstanding Critical Issues:** None
**Next Major Milestone:** Workflow automation activation (see Section 19)

---

## 2. Project Charter

### 2.1 Project Identity

| Field | Value |
|-------|-------|
| **Project Name** | Submission Tracker |
| **Project Code** | ST-2025 |
| **Organisation** | Casey & Associates (Pty) Ltd |
| **Industry** | Financial Services (Payroll Deduction Administration) |
| **Jurisdiction** | South Africa |
| **Regulatory Framework** | POPIA (Protection of Personal Information Act), Municipal Finance Management Act |
| **Start Date** | 11 September 2025 |
| **Target Completion** | March 2026 (core); April 2026 (CAPS integration) |
| **Actual Completion** | April 2026 (production-ready) |
| **Project Sponsor** | Casey & Associates Management |
| **Project Manager** | Development Team Lead |

### 2.2 Project Objectives

| ID | Objective | Success Criteria | Status |
|----|-----------|-----------------|--------|
| PO-01 | Digitise the file submission workflow | Zero spreadsheet-based tracking within 3 months of deployment | Achieved |
| PO-02 | Ensure 100% submission compliance | All municipalities submit before their deadlines; system enforces tracking | Achieved |
| PO-03 | Reduce data discrepancies | Automated CAPS verification catches member/policy/premium mismatches before processing | Achieved |
| PO-04 | Maintain audit readiness | Complete digital trail accessible within 60 seconds | Achieved |
| PO-05 | Enable cross-system authentication | Single login across CAPS and Submission Tracker | Achieved |
| PO-06 | Integrate with CAPS for data verification | Member, policy, and premium verification against live CAPS data | Achieved |
| PO-07 | Support multi-tenancy for future growth | Tenant-scoped data isolation and configuration | Achieved (infrastructure) |

### 2.3 Project Scope

**In Scope:**
- File upload management (email correspondence, workings spreadsheets, systems import files)
- Deadline scheduling per municipality with user assignments
- CAPS member and policy verification against uploaded spreadsheet data
- Single sign-on between CAPS and the Submission Tracker via JWT-based bridge
- CAPS reference data synchronisation (municipalities, companies)
- Database-channel notification system with read/unread tracking
- Comprehensive admin panel (users, roles, permissions, companies, municipalities, reports, audits)
- RESTful API layer with API key authentication
- CAPS webhook receiver for payment batch status updates
- Multi-tenant data isolation architecture
- Audit trail for all model changes, authentication events, and request logging
- Excel/CSV export capabilities

**Out of Scope (current release):**
- Workflow automation engine (infrastructure built; activation deferred)
- Microsoft 365 email integration (adapter exists; not connected)
- S3 cloud file storage (adapter exists; local filesystem in use)
- Real-time WebSocket/Echo notifications (database channel only)
- Mobile application (responsive web only)
- Direct municipality self-service portal
- Automated report scheduling and distribution

### 2.4 Constraints

| ID | Constraint | Impact | Mitigation |
|----|-----------|--------|------------|
| C-01 | CAPS API does not support filtering members by company | Per-member lookup via `?idNumber=` parameter required | Smart pagination: < 5,000 records use paginated fetch; > 5,000 use per-ID lookup |
| C-02 | CAPS policy API requires `?organizationId=` (not `?companyId=`) | Must map company `casey_id` to correct API parameter | Documented in integration analysis; tested extensively |
| C-03 | CAPS `?search=` parameter does not filter server-side | Cannot use search-based bulk member lookup | Use `?idNumber=` for exact per-member lookups only |
| C-04 | PHP memory limit of 128 MB | Cannot fetch all 76,000+ CAPS members in a single request | Smart pagination strategy in `CaseyMemberPolicyService` |
| C-05 | SSO microservice stores sessions in memory (no persistence) | Session data lost on microservice restart | Graceful degradation; both applications work independently |
| C-06 | File storage is local filesystem | Must configure cloud storage for production scaling | S3Adapter infrastructure exists; migration path clear |

### 2.5 Assumptions

| ID | Assumption | Risk if Invalid |
|----|-----------|-----------------|
| A-01 | CAPS API available during South African business hours (07:00-18:00 SAST) | Verification and sync fail; manual retry required |
| A-02 | All deduction companies exist in CAPS with unique names | Company name resolution fails; fallback to upload parent company |
| A-03 | SA ID numbers are the primary member identifier in both systems | Member matching fails |
| A-04 | Users have modern browsers (Chrome, Edge, Firefox - latest 2 versions) | UI may degrade on older browsers |
| A-05 | CAPS JWT shared secret is consistent across environments | SSO authentication fails |
| A-06 | Every company submits to every municipality (not scoped 1:1) | Data model allows cross-municipality submissions |
| A-07 | CAPS API calls should use the logged-in user's SSO JWT credentials | Ensures audit traceability and user-scoped data access |

---

## 3. Organisational Context

### 3.1 About Casey & Associates

Casey & Associates (Pty) Ltd is a South African financial services company that administers payroll deductions across multiple municipalities. Each month, municipalities submit deduction files containing member IDs, policy codes, and premium amounts for dozens of deduction companies (insurance providers, loan companies, and other financial services entities). Casey & Associates acts as the intermediary, processing these submissions through their CAPS platform to ensure accurate payment settlement, allocation, and reconciliation.

### 3.2 Business Process Overview

```
Municipality HR Department
        |
        | Prepares monthly deduction files
        |   (member IDs, policy codes, premium amounts)
        v
+-------------------+
| Submission Tracker |  <-- THIS SYSTEM
| (File Management)  |
+-------------------+
        |
        | 1. Upload & track files (emails, workings, imports)
        | 2. Verify against CAPS (members, policies, premiums)
        | 3. Enforce deadlines per municipality
        | 4. Maintain audit trail
        |
        v
+-------------------+
| CAPS               |
| (Payment Settlement)|
+-------------------+
        |
        | 1. Import payment batches
        | 2. Allocate to member policies
        | 3. Export settlements per area
        | 4. Process refunds
        |
        v
  Deduction Companies
  (Insurance, Lenders)
```

### 3.3 Regulatory Environment

| Regulation | Applicability | Compliance Measure |
|-----------|--------------|-------------------|
| **POPIA** (Protection of Personal Information Act) | SA ID numbers, member personal data | Encrypted storage, access control, audit logging, data classification |
| **Municipal Finance Management Act** | Public sector financial data handling | Complete audit trail, deadline enforcement, submission tracking |
| **Labour Relations Act** | Payroll deduction authorisation | Verification against CAPS member/policy records |
| **Financial Advisory and Intermediary Services Act (FAIS)** | Financial services compliance | Audit-ready record keeping, role-based access segregation |

### 3.4 System Landscape

The Submission Tracker exists within the following system ecosystem:

| System | Technology | Relationship | Data Flow |
|--------|-----------|-------------|-----------|
| **Submission Tracker** | Laravel 12, Vue 3, MySQL | Primary (this system) | N/A |
| **CAPS** | Java 21, Spring Boot 3.2, PostgreSQL | Upstream system of truth | Bidirectional: reference data sync, verification, webhooks |
| **SSO Microservice** | Node.js, Express, in-memory store | Session bridge | Bidirectional: session registration, heartbeat, logout |
| **Python MSG Parser** | Python, FastAPI | File conversion sidecar | Unidirectional: MSG file parsing requests |
| **CAPS Frontend** | Next.js 16, React 18, Material UI | Sister application (shared users) | Cross-linked via SSO and deep links |

---

## 4. Team Structure and Responsibilities

### 4.1 Project Organisation

```
Project Sponsor (Casey & Associates Management)
        |
        +-- Project Manager / Development Team Lead
            |
            +-- Backend Development (Laravel / PHP)
            |       - Core platform development
            |       - CAPS API integration services
            |       - Database design and migrations
            |       - Authentication and authorisation
            |       - Audit trail and notification systems
            |
            +-- Frontend Development (Vue.js / Inertia)
            |       - Dashboard and upload interfaces
            |       - Calendar and deadline views
            |       - Admin panel UI components
            |       - File preview (spreadsheet and email)
            |       - Notification centre
            |
            +-- Integration Development
            |       - CAPS API client services
            |       - SSO microservice (Node.js)
            |       - Webhook receiver and event handling
            |       - Reference data synchronisation
            |
            +-- Quality Assurance
            |       - PHPUnit test framework
            |       - Manual testing and verification
            |       - Audit trail regression monitoring
            |
            +-- DevOps / Infrastructure
                    - Environment configuration
                    - Deployment and release management
                    - Database administration
```

### 4.2 RACI Matrix

| Activity | Dev Lead | Backend Dev | Frontend Dev | Integration Dev | QA | Sponsor |
|----------|---------|------------|-------------|----------------|-----|---------|
| Requirements definition | A | C | C | C | C | R |
| Architecture decisions | R/A | C | C | C | I | I |
| Database design | A | R | I | C | I | I |
| Backend implementation | A | R | I | C | C | I |
| Frontend implementation | A | I | R | I | C | I |
| CAPS integration | A | C | I | R | C | I |
| SSO implementation | A | C | I | R | C | I |
| Testing | A | C | C | C | R | I |
| Release approval | R | C | C | C | C | A |
| Production deployment | R/A | C | I | C | I | I |
| Incident management | R/A | C | C | C | C | I |

Legend: **R** = Responsible, **A** = Accountable, **C** = Consulted, **I** = Informed

### 4.3 Skill Matrix

| Skill Area | Required Level | Team Capacity | Gap |
|-----------|---------------|--------------|-----|
| PHP 8.2+ / Laravel 12 | Expert | Covered | None |
| Vue 3 / Inertia.js 2 | Advanced | Covered | None |
| MySQL database design | Advanced | Covered | None |
| REST API design | Advanced | Covered | None |
| Java / Spring Boot (CAPS) | Advanced | Covered (Integration team) | None |
| Node.js (SSO service) | Intermediate | Covered | None |
| Python / FastAPI (MSG parser) | Intermediate | Covered | None |
| DevOps / CI/CD | Intermediate | Covered | Enhancement opportunity |
| Automated testing | Intermediate | Covered | Enhancement opportunity |
| Security / POPIA compliance | Intermediate | Covered | External audit recommended |

---

## 5. Project Timeline and Milestones

### 5.1 Phase 1: Foundation (September 2025)

**Duration:** 4 weeks (11 September - 8 October 2025)
**Objective:** Establish core domain model and basic CRUD operations

| Date | Migration / Deliverable | Description |
|------|------------------------|-------------|
| 11 Sep 2025 | `create_municipalities_table` | Core municipality entity with name, province, code |
| 12 Sep 2025 | `create_companies_table` | Deduction company entity with registration, status, contact info |
| 12 Sep 2025 | `create_submissions_table` | Submission tracking entity |
| 15 Sep 2025 | `create_uploads_table` | Central upload record: reference, 3 file types, status workflow |
| 16 Sep 2025 | `create_municipality_deadlines_table` | Per-municipality deadline scheduling |
| 17 Sep 2025 | `create_permission_tables` | Spatie RBAC: roles, permissions, model_has_roles, etc. |
| 22 Sep 2025 | `create_user_assignments_table` | User-to-company/municipality/deadline assignments |
| 23 Sep 2025 | `create_audits_table` | Generic audit trail (auditable_type, event, old/new values) |
| 23 Sep 2025 | `create_notifications_table` | Database-channel notification system |

**Key Decisions Made:**
- Laravel 12 selected as the application framework
- Vue 3 + Inertia.js chosen for SPA-like experience without separate API
- Spatie/Permission selected for RBAC (industry standard)
- MySQL selected as primary database
- Three-file upload model established (original email, workings, systems import)
- Status progression defined: Pending -> Processing -> Completed

**Milestone:** Core platform operational with upload, deadline, and user management

### 5.2 Phase 2: Enhancement (October 2025)

**Duration:** 2 weeks (mid-October 2025)
**Objective:** Enhance upload workflow with user tracking and re-upload controls

| Date | Migration / Deliverable | Description |
|------|------------------------|-------------|
| 17 Oct 2025 | `add_user_id_to_uploads_table` | Track which user created each upload |
| 20 Oct 2025 | `add_reupload_reasons_to_uploads_table` | Re-upload reason type and notes (within 30-day window) |

**Key Features Delivered:**
- Upload ownership tracking
- Re-upload workflow with mandatory reason capture
- File preview system (spreadsheet table with search/pagination, email preview with tabs)
- Dashboard with upcoming/overdue deadline widgets
- Notification generation for upload creation, deadline changes, assignment changes

**Milestone:** Upload workflow fully operational with user tracking and re-upload controls

### 5.3 Phase 3: API and File Conversion (January 2026)

**Duration:** 2 weeks (mid-January 2026)
**Objective:** Enable external integrations and standardise email file formats

| Date | Migration / Deliverable | Description |
|------|------------------------|-------------|
| 13 Jan 2026 | `create_personal_access_tokens_table` | Laravel Sanctum API token support |
| 16 Jan 2026 | `add_converted_eml_paths_to_uploads_table` | Track MSG-to-EML conversion output paths |

**Key Features Delivered:**
- RESTful API layer (v1) with Sanctum bearer token authentication
- API endpoints: users, companies, municipalities, deadlines, uploads, premium batch
- MSG-to-EML email file conversion (via Python FastAPI sidecar)
- Converted EML path tracking on upload records
- API documentation and endpoint catalogue

**Milestone:** API layer operational; third-party integration capability established

### 5.4 Phase 4: CAPS Authentication Bridge (February 2026)

**Duration:** 1 week (mid-February 2026)
**Objective:** Unify user credentials between CAPS and Submission Tracker

| Date | Migration / Deliverable | Description |
|------|------------------------|-------------|
| 16 Feb 2026 | `add_external_password_hash_to_users_table` | Store CAPS password hash for dual-auth fallback |

**Key Features Delivered:**
- External password hash column for CAPS-provisioned users
- Dual authentication: local password OR CAPS-seeded password hash
- Password verification via bcrypt (12+ rounds)
- Foundation for SSO bridge (user identity linkage)

**Milestone:** CAPS users can authenticate with their existing credentials

### 5.5 Phase 5: Full CAPS Integration (April 2026)

**Duration:** 3 weeks (14 April - 23 April 2026)
**Objective:** Complete CAPS integration with SSO, verification, sync, webhooks, and multi-tenancy

| Date | Migration / Deliverable | Description |
|------|------------------------|-------------|
| 14 Apr 2026 | `add_profile_columns_to_users_table` | Extended user profile for CAPS-synced data |
| 15 Apr 2026 | `add_casey_id_to_companies_and_municipalities` | CAPS entity identifier for reference data keying |
| 15 Apr 2026 | `make_companies_municipality_id_nullable` | Support companies that submit to all municipalities (not 1:1 scoped) |
| 16 Apr 2026 | `add_caps_webhook_columns_to_uploads` | CAPS status, batch ID, error details, webhook event tracking |
| 23 Apr 2026 | `create_multi_tenant_core_tables` | Tenant, TenantDomain, TenantSetting, ApiKey, IntegrationConnection, WorkflowDefinition, WorkflowInstance, EventLog, WebhookDelivery |
| 23 Apr 2026 | `add_tenant_id_to_core_tables` | Tenant foreign key on all core entities |
| 23 Apr 2026 | `add_caps_verification_to_uploads_table` | CAPS verification JSON results, verified_at timestamp |

**Key Features Delivered:**

*SSO (Single Sign-On):*
- JWT-based SSO bridge between CAPS and Submission Tracker
- HS256 JWT verification via `CaseyJwtService`
- Auto-provisioning of users from JWT claims
- Bidirectional login/logout synchronisation
- Silent SSO login via popup window, silent logout via hidden iframe
- SSO session sync middleware (`SsoSessionSync`)
- Session heartbeat via `SsoSessionService`
- Graceful degradation when SSO microservice is unavailable
- Deactivated users and users without Tracker roles are blocked

*CAPS Verification:*
- Member verification against CAPS using `?idNumber=` API parameter
- Policy verification using `?organizationId=` API parameter
- Premium amount mismatch detection (threshold: R0.01)
- Company name resolution using fuzzy matching (exact, suffix-stripped, LIKE, word-prefix)
- Smart pagination: < 5,000 records paginated fetch, > 5,000 per-ID lookup
- Verification results stored as JSON on upload record with timestamp
- Verification score with found/missing counts per category
- Manual re-verification from History page

*Reference Data Synchronisation:*
- Daily scheduled sync (02:30 SAST) via Laravel Scheduler
- Manual sync trigger from admin dashboard (`CapsDataSyncController`)
- Auto-sync on first login if no CAPS data exists
- Upsert logic: create new, update existing, skip unchanged
- `casey_id` keyed synchronisation for municipalities and companies
- Global scope excludes non-CAPS-synced records from dropdowns

*Webhook Receiver:*
- Inbound CAPS webhook endpoint (`POST /api/v1/webhooks/caps`)
- HMAC-SHA256 signature verification
- Event types: `payment_batch.imported`, `payment_batch.allocated`, `payment_batch.failed`, `payment_batch.exported`, `refund.created`, `refund.allocated`
- CAPS status and error tracking on upload records
- `WebhookDelivery` model for event logging and replay

*Multi-Tenancy:*
- `Tenant`, `TenantDomain`, `TenantSetting` models
- `BelongsToTenant` trait for automatic tenant scoping
- `TenantContext` service for current tenant resolution
- `TenantResolverService` for domain-based tenant identification
- `ResolveTenant` middleware on all requests
- Tenant ID foreign key on all core tables
- API key scoping per tenant

*Platform Infrastructure:*
- `ApiKey` model with SHA-256 hashed keys
- `IntegrationConnection` model with adapter pattern
- `WorkflowDefinition` and `WorkflowInstance` models (infrastructure ready)
- `EventLog` model for cross-cutting event tracking
- `WebhookDelivery` model with replay capability
- Partner-facing API with API key authentication and rate limiting

**Milestone:** Full CAPS integration achieved; system is production-ready

### 5.6 Timeline Summary (Gantt View)

```
2025 Sep |============================| Phase 1: Foundation
         |  Core tables, RBAC, audit  |
         |  Upload workflow, deadlines |
         |  Notifications, dashboard  |

2025 Oct |============|                 Phase 2: Enhancement
         | User track |
         | Re-uploads |
         | Previews   |

2025 Nov - Dec |                        (Stabilisation, bug fixes, UX refinement)

2026 Jan |============|                 Phase 3: API & File Conversion
         | Sanctum API|
         | MSG->EML   |

2026 Feb |======|                       Phase 4: CAPS Auth Bridge
         | Ext. |
         | Hash |

2026 Mar |                              (Integration planning, CAPS API analysis)

2026 Apr |============================| Phase 5: Full CAPS Integration
         | SSO, Verification, Sync    |
         | Webhooks, Multi-tenancy    |
         | Platform infrastructure    |
```

---

## 6. Module Ownership and Decomposition

### 6.1 Module Catalogue

The Submission Tracker is decomposed into ten functional modules, each with defined boundaries, data ownership, and interface contracts.

#### Module 1: Authentication and Authorisation

| Attribute | Value |
|-----------|-------|
| **Module ID** | MOD-AUTH |
| **Owner** | Backend Development |
| **Status** | Production |
| **Priority** | Critical |

**Components:**
- `AuthenticatedSessionController` - Local login/logout with employee_number + password
- `CaseySsoController` - CAPS SSO bridge (JWT verification, auto-provisioning, silent login/logout)
- `CaseyJwtService` - HS256 JWT token verification and claims extraction
- `SsoSessionService` - SSO microservice client (register, check, delete sessions)
- `SsoSessionSync` middleware - Periodic SSO session heartbeat on authenticated requests
- `AuthenticateApiKey` middleware - SHA-256 API key verification for partner routes
- `User` model - Dual password hash (local + external), role/permission assignment
- Login page (`Auth/Login.vue`) - Employee number + password form with SSO redirect support

**Authentication Methods:**
1. **Local Login** - Employee number + bcrypt password via Laravel session
2. **CAPS SSO** - HS256 JWT token via `/auth/casey-sso` endpoint
3. **External Password** - CAPS-seeded bcrypt hash fallback on local login
4. **API Token** - Laravel Sanctum bearer tokens for API consumers
5. **API Key** - SHA-256 hashed keys for partner integrations

**Permissions (23+ granular):**
- `view dashboard`, `view uploads`, `create upload`, `export uploads`
- `view deadlines`, `create deadline`, `edit deadline`, `delete deadline`
- `view submissions`, `create submissions`
- `manage users`, `manage roles`, `manage permissions`
- `view companies`, `manage companies`
- `view municipalities`, `manage municipalities`
- `view reports`, `view audits`

#### Module 2: Upload Management

| Attribute | Value |
|-----------|-------|
| **Module ID** | MOD-UPL |
| **Owner** | Backend + Frontend Development |
| **Status** | Production |
| **Priority** | Critical |

**Components:**
- `UploadsController` - Full CRUD: index, store, complete, history, export, download, preview
- `Uploads` model - Central record: reference code, 3 file paths, status, CAPS verification
- `Submission` model - Grouping entity for related uploads
- Upload page (`Uploads/Index.vue`) - Municipality/company selection, file upload form
- Complete page (`Uploads/Complete.vue`) - Multi-step completion with all three file types
- History page (`Uploads/History.vue`) - Filterable upload history with CAPS verification results
- Spreadsheet preview (`Uploads/ViewSpreadsheet.vue`) - Inline table with search and pagination
- Email preview (`Uploads/ViewEmail.vue`) - Headers, body tabs (text/HTML/raw), attachments

**File Types:**
| Type | Extensions | Storage | Processing |
|------|-----------|---------|-----------|
| Original Email | `.eml`, `.msg` | `original_file_path[]` (JSON array) | MSG auto-converted to EML; date extraction from headers |
| Workings Spreadsheet | `.xlsx`, `.csv` | `workings_file_path` | Parsed for CAPS verification (member IDs, policy codes, premiums) |
| Systems Import | `.xlsx`, `.csv` | `systems_import_file_path` | File for downstream CAPS import |

**Status Workflow:**
```
Pending  -->  Processing  -->  Completed
   |              |               |
   |              |               +--> [CAPS Verification runs automatically]
   |              |
   +--------------+--> Rejected (with reupload_reason)
```

**Business Rules:**
- Maximum file size: 10 MB per file
- Unique 10-character reference code generated per upload
- Re-uploads within 30 days require reason (type + note)
- Non-admin users restricted to assigned company/municipality combinations
- Admin users may upload without deadline or assignment checks
- `scopeAccessibleToUser` ensures non-admin users see only their uploads

#### Module 3: CAPS Verification

| Attribute | Value |
|-----------|-------|
| **Module ID** | MOD-VER |
| **Owner** | Integration Development |
| **Status** | Production |
| **Priority** | High |

**Components:**
- `CaseyMemberPolicyService` - Core verification logic: member lookup, policy fetch, premium comparison
- `CaseyPremiumBatchService` - CAPS API client for authentication and batch queries
- `CaseyReferenceDataService` - Municipality and company sync from CAPS
- Upload `compareWithCaps` action - Triggered automatically post-upload and manually from History

**Verification Process:**
1. Parse uploaded workings spreadsheet to extract member IDs, policy codes, premium amounts, and company names
2. Resolve each row's deduction company from the Company Name column using fuzzy matching:
   - Exact name match
   - Suffix-stripped match (e.g., remove "Ltd", "Pty")
   - SQL LIKE match
   - Word-prefix match
3. Look up each member in CAPS via `GET /v1/member/api/members?idNumber={SA_ID}`
4. Fetch policies for the resolved company via `GET /v1/premiums/status/fetch?organizationId={casey_id}`
5. Match policy codes and compare premium amounts (threshold: R0.01)
6. Store results as JSON on the upload record: `caps_verification` field + `caps_verified_at` timestamp

**Smart Pagination Strategy:**
| Dataset Size | Strategy | Rationale |
|-------------|---------|-----------|
| < 5,000 records | Paginated bulk fetch | Efficient for moderate datasets |
| > 5,000 records | Per-ID individual lookup | Prevents PHP memory exhaustion (128 MB limit) |

**Verification Output:**
- Members Found / Members Missing counts
- Policies Found / Policies Missing counts
- Premium Mismatches with difference amounts
- Overall Verification Score (percentage)
- Per-row detail (matched, unmatched, mismatch reason)

#### Module 4: Deadline and Assignment Management

| Attribute | Value |
|-----------|-------|
| **Module ID** | MOD-DL |
| **Owner** | Backend + Frontend Development |
| **Status** | Production |
| **Priority** | High |

**Components:**
- `MunicipalityDeadlineController` - Full CRUD for deadlines, assignments, bulk creation
- `MunicipalityDeadline` model - Per-municipality deadline with target date and notes
- `UserAssignment` model - User-to-(municipality, company, deadline) mapping
- Municipality deadlines page (`Deadlines/Municipalities.vue`) - Deadline list with CRUD
- Company deadlines page (`Deadlines/Companies.vue`) - Company-centric deadline view
- Calendar view - FullCalendar integration with colour-coded events
- `ShareCalendarEvents` middleware - Injects calendar data into Inertia shared props

**Colour Coding:**
| State | Colour | Condition |
|-------|--------|-----------|
| Overdue | Red | Deadline date has passed |
| Today | Amber | Deadline date is today |
| Upcoming | Green | Deadline date is in the future |

**Assignment Model:**
- Every company submits to every municipality (not scoped 1:1)
- Bulk assignment creation: deadline + multiple company/user pairs in a single operation
- Deadline changes trigger notifications to all affected assigned users

#### Module 5: Notification System

| Attribute | Value |
|-----------|-------|
| **Module ID** | MOD-NOT |
| **Owner** | Backend + Frontend Development |
| **Status** | Production |
| **Priority** | Medium |

**Components:**
- `NotificationController` - Index, mark as read (single/bulk), delete, clear all
- Laravel database notification channel - Persistent storage in `notifications` table
- Notification centre (`Notifications/Index.vue`) - Filterable list (read/unread/type)
- Assignment notification (`Notifications/AssignNotification.vue`) - Assignment-specific display

**Notification Triggers:**
| Event | Recipients | Content |
|-------|-----------|---------|
| Upload created | Assigned users + admins | Reference code, municipality, company |
| Deadline created/changed | Assigned users | Municipality, new/changed date |
| Assignment created | Assigned user | Municipality, company, deadline |
| Assignment removed | Previously assigned user | Municipality, company |
| CAPS verification complete | Upload creator | Verification score, summary |

#### Module 6: Administration Panel

| Attribute | Value |
|-----------|-------|
| **Module ID** | MOD-ADM |
| **Owner** | Backend + Frontend Development |
| **Status** | Production |
| **Priority** | High |

**Components:**

*User Management:*
- `Admin\UserController` - CRUD with role assignment, municipality/company assignments
- User list page (`Admin/Users/Index.vue`) - Searchable user table with role badges
- User edit page (`Admin/Users/Edit.vue`) - Profile editing, role assignment, assignment management

*Role Management:*
- `Admin\RoleController` - CRUD with permission matrix
- Role list page (`Admin/Roles/Index.vue`) - Role table with permission counts
- Role create/edit page (`Admin/Roles/Create.vue`) - Permission checkbox matrix

*Company Management:*
- `Admin\CompanyController` - CRUD (CAPS-synced), view assignments
- Company list page (`Admin/Companies/Index.vue`) - Company table with CAPS sync status

*Municipality Management:*
- `Admin\MunicipalityController` - CRUD (CAPS-synced), view companies/deadlines/assignments
- Municipality list page (`Admin/Municipalities/Index.vue`) - Municipality table with CAPS sync status

*Reports:*
- `Admin\ReportController` - Upload summary, deadline summary, Excel/CSV export
- Reports page (`Admin/Reports/Index.vue`) - Dashboard-style report views with export buttons

*Audit Trail:*
- `Admin\AuditController` - Index with filters (user, event type, date range), show detail
- Audit list page (`Admin/Audits/Index.vue`) - Filterable audit table
- Audit detail page (`Admin/Audits/Show.vue`) - Old/new value comparison

*CAPS Data Sync:*
- `Admin\CapsDataSyncController` - Manual sync trigger, sync status display
- Sync button on admin dashboard - Prominent sync control with count and last-sync timestamp

#### Module 7: CAPS Reference Data Synchronisation

| Attribute | Value |
|-----------|-------|
| **Module ID** | MOD-SYNC |
| **Owner** | Integration Development |
| **Status** | Production |
| **Priority** | High |

**Components:**
- `CaseyReferenceDataService` - Sync logic: fetch, upsert, timestamp tracking
- `CapsDataSyncController` - Manual trigger endpoint and status API
- Laravel Scheduler - Daily automated sync at 02:30 SAST

**Sync Behaviour:**
| Scenario | Action |
|---------|--------|
| Record exists in CAPS, not in Tracker | Create new record with `casey_id` |
| Record exists in both systems | Update if changed, skip if unchanged |
| Record exists in Tracker, not in CAPS | Retain record; exclude from dropdowns via global scope |
| No CAPS data exists on first login | Prominent warning banner; auto-sync on admin first login |

**Data Volumes:**
| Entity | Expected Volume | Sync Frequency |
|--------|----------------|---------------|
| Municipalities | ~17-50 | Daily (02:30) + manual |
| Companies | ~190-500 | Daily (02:30) + manual |

#### Module 8: Multi-Tenancy

| Attribute | Value |
|-----------|-------|
| **Module ID** | MOD-TNT |
| **Owner** | Backend Development |
| **Status** | Infrastructure Ready |
| **Priority** | Medium |

**Components:**
- `Tenant` model - Organisation entity with domain mapping
- `TenantDomain` model - Domain-to-tenant resolution
- `TenantSetting` model - Per-tenant configuration key-value store
- `BelongsToTenant` trait - Automatic tenant scoping on all queries and creates
- `TenantContext` service - ThreadLocal-style current tenant holder
- `TenantResolverService` - Domain-based tenant identification
- `ResolveTenant` middleware - Request-level tenant resolution
- `TenantController` API - Current tenant info and settings management

**Architecture:**
- Single database with `tenant_id` foreign key on all core tables
- Query-level isolation via global scope (not schema-level)
- Tenant resolved from request domain or API key
- Settings stored as JSON key-value pairs per tenant

#### Module 9: Audit Trail

| Attribute | Value |
|-----------|-------|
| **Module ID** | MOD-AUD |
| **Owner** | Backend Development |
| **Status** | Production |
| **Priority** | Critical |

**Components:**
- `Audit` model - Generic audit record (auditable_type, event, old_values, new_values)
- `RecordsAuditTrail` concern (trait) - Automatic model change capture on create/update/delete
- `AuditTrailMiddleware` - Request-level audit logging (IP, user agent, route)
- `EventLog` model - Cross-cutting event tracking for integrations and workflows
- `EventTimelineService` - Timeline reconstruction from audit and event records

**Captured Events:**
| Category | Events | Data Captured |
|----------|--------|--------------|
| Model Changes | Create, Update, Delete | Old values, new values, changed fields, user ID, timestamp |
| Authentication | Login, Logout, SSO Login, SSO Logout, Failed Login | User ID, IP address, user agent, method |
| Upload Lifecycle | Upload created, Status changed, File added, Verification complete | Upload reference, status transition, user ID |
| CAPS Integration | Sync started, Sync completed, Verification run, Webhook received | Records affected, duration, errors |
| Admin Actions | User created, Role changed, Permission updated | Actor, target, before/after state |

#### Module 10: API Layer

| Attribute | Value |
|-----------|-------|
| **Module ID** | MOD-API |
| **Owner** | Backend Development |
| **Status** | Production |
| **Priority** | High |

**Components:**

*Sanctum-Authenticated Internal API (`/api/v1/`):*
- `Api\V1\UploadController` - Upload listing, detail, premium batch query
- `Api\V1\CompanyController` - Company listing and detail
- `Api\V1\MunicipalityController` - Municipality listing and detail
- `Api\V1\DeadlineController` - Deadline listing and detail
- `Api\V1\RoleController` - Role listing and detail
- `Api\V1\TenantController` - Current tenant info, settings management
- `Api\V1\ApiKeyController` - API key CRUD
- `Api\V1\WorkflowController` - Workflow definition CRUD, publish, instance creation
- `Api\V1\IntegrationController` - Integration connection, sync, health check
- `Api\V1\EventLogController` - Event log listing
- `Api\V1\WebhookReplayController` - Webhook event replay
- `Api\V1\OpsController` - Failed jobs listing and retry

*Webhook Receiver (No Auth - HMAC Verified):*
- `Api\V1\CapsWebhookController` - Inbound CAPS webhook processing

*Partner API (API Key Authenticated):*
- `GET /api/v1/partner/events` - Event log access for partners
- `POST /api/v1/partner/integrations/{id}/sync` - Partner-triggered sync
- `POST /api/v1/partner/webhooks/replay/{id}` - Partner webhook replay

### 6.2 Module Dependency Map

```
                            +-------------+
                            |  MOD-AUTH   |
                            | (Auth/RBAC) |
                            +------+------+
                                   |
                   +---------------+---------------+
                   |               |               |
            +------+------+ +-----+------+ +------+------+
            |  MOD-UPL   | |  MOD-DL   | |  MOD-ADM   |
            | (Uploads)  | | (Deadlines)| | (Admin)    |
            +------+------+ +-----+------+ +------+------+
                   |               |               |
                   +-------+-------+       +-------+-------+
                           |               |               |
                    +------+------+ +------+------+ +------+------+
                    |  MOD-VER   | |  MOD-NOT   | |  MOD-SYNC  |
                    | (CAPS Verif)| | (Notifs)   | | (Data Sync)|
                    +------+------+ +------------+ +------+------+
                           |                              |
                    +------+------------------------------+------+
                    |              |               |              |
             +------+------+ +----+-------+ +-----+-----+ +-----+-----+
             |  MOD-AUD   | |  MOD-TNT  | | MOD-API   | | CAPS API  |
             | (Audit)    | | (Tenancy) | | (REST)    | | (External)|
             +------------+ +------------+ +-----------+ +-----------+
```

---

## 7. Technology Decision Register

Each technology decision is documented with the rationale, alternatives considered, and trade-offs accepted.

### TDR-001: Application Framework - Laravel 12

| Field | Value |
|-------|-------|
| **Decision Date** | September 2025 |
| **Status** | Accepted |
| **Decision** | Use Laravel 12 (PHP 8.2+) as the primary application framework |

**Rationale:**
- Rapid development with Eloquent ORM, built-in authentication, and comprehensive ecosystem
- Mature RBAC support via Spatie/Permission (industry standard for Laravel)
- Built-in scheduler for automated tasks (CAPS sync)
- Laravel Sanctum provides session-based SPA auth and bearer token API auth in one package
- Strong community support and extensive documentation
- Team expertise in PHP/Laravel

**Alternatives Considered:**
| Alternative | Reason Rejected |
|------------|----------------|
| Spring Boot (Java) | Heavier for file management; team stronger in PHP; CAPS already uses Spring Boot |
| Django (Python) | Smaller team ecosystem in ZA; less mature SPA integration |
| Express.js (Node) | Less structured for enterprise RBAC; PHP team preference |

**Trade-offs Accepted:**
- PHP single-threaded model limits concurrent processing (mitigated by queue workers)
- 128 MB memory limit requires careful large dataset handling (mitigated by smart pagination)

### TDR-002: Frontend Framework - Vue 3 + Inertia.js

| Field | Value |
|-------|-------|
| **Decision Date** | September 2025 |
| **Status** | Accepted |
| **Decision** | Use Vue 3 with Inertia.js 2.0 for the frontend, served via server-driven SPA pattern |

**Rationale:**
- SPA-like user experience without building and maintaining a separate API layer
- Server-side routing with client-side rendering (best of both worlds)
- Vue 3 Composition API for clean component logic
- Tailwind CSS 4 for rapid, consistent styling
- Vite 7 for fast build times and hot module replacement
- Ziggy for named route generation in JavaScript

**Alternatives Considered:**
| Alternative | Reason Rejected |
|------------|----------------|
| React + Next.js | CAPS frontend already uses React; wanted differentiation and team preference |
| Livewire | Less rich interactivity for file preview and calendar features |
| Separate Vue SPA + API | Additional complexity maintaining two codebases; Inertia eliminates this |

**Key Frontend Dependencies:**
| Package | Version | Purpose |
|---------|---------|---------|
| `vue` | ^3.5.21 | Core UI framework |
| `@inertiajs/vue3` | ^2.1.5 | Server-driven SPA bridge |
| `tailwindcss` | ^4.1.13 | Utility-first CSS framework |
| `vite` | ^7.1.5 | Build tool and dev server |
| `@fullcalendar/vue3` | ^6.1.20 | Calendar UI for deadline visualisation |
| `@heroicons/vue` | ^2.2.0 | Icon library |
| `lucide-vue-next` | ^0.544.0 | Additional icon library |
| `read-excel-file` | ^6.0.1 | Client-side spreadsheet reading |
| `papaparse` | ^5.5.3 | CSV parsing |
| `msgreader` | ^1.0.1 | MSG file parsing in browser |
| `date-fns` | ^4.1.0 | Date manipulation utilities |
| `ziggy-js` | ^2.6.0 | Laravel named routes in JavaScript |

### TDR-003: Role-Based Access Control - Spatie/Permission

| Field | Value |
|-------|-------|
| **Decision Date** | September 2025 |
| **Status** | Accepted |
| **Decision** | Use `spatie/laravel-permission` v6.21 for RBAC |

**Rationale:**
- Industry standard for Laravel RBAC with 15,000+ GitHub stars
- Supports roles, permissions, and role-permission assignment
- Built-in middleware (`permission:xxx`) for route protection
- Model-level permission checks via `$user->can()` and `$user->hasRole()`
- Database-backed (auditable) rather than config-file-based

**Role Hierarchy:**
| Role | Permissions | Purpose |
|------|-----------|---------|
| Super Admin | All (wildcard) | System configuration, all operations |
| Admin | Most (excluding system config) | User management, municipality/company admin, reporting |
| Manager | Operational | Oversee submissions, manage deadlines, view reports |
| User (Clerk) | Basic | Upload files, view own history, notifications |

### TDR-004: Calendar Visualisation - FullCalendar

| Field | Value |
|-------|-------|
| **Decision Date** | September 2025 |
| **Status** | Accepted |
| **Decision** | Use FullCalendar Vue3 v6.1 for deadline calendar visualisation |

**Rationale:**
- Feature-rich calendar component with day/week/month views
- Vue 3 native integration
- Supports event colour coding (critical for deadline status)
- Interactive drag-and-drop capability for future deadline management
- Well-documented API

### TDR-005: SSO Microservice - Node.js Express

| Field | Value |
|-------|-------|
| **Decision Date** | April 2026 |
| **Status** | Accepted |
| **Decision** | Use a lightweight Node.js Express microservice for SSO session management |

**Rationale:**
- Minimal footprint for session store/lookup/delete operations
- In-memory storage for fast session checks (sub-millisecond latency)
- Language-agnostic HTTP API consumable by both Laravel (PHP) and CAPS (Java)
- Simple deployment and operation (single process, no database dependency)
- Graceful degradation: both applications work independently if microservice is down

**Trade-offs Accepted:**
- In-memory storage means session data lost on restart (mitigated by graceful degradation)
- No clustering support without external session store (acceptable for current scale)
- No persistence means no session analytics (acceptable trade-off for simplicity)

### TDR-006: CAPS API Authentication - User SSO JWT

| Field | Value |
|-------|-------|
| **Decision Date** | April 2026 |
| **Status** | Accepted |
| **Decision** | CAPS API calls use the logged-in user's SSO JWT credentials, not hardcoded service account credentials from `.env` |

**Rationale:**
- Ensures audit traceability: CAPS logs show the actual user making the request
- Respects CAPS RBAC: user-scoped data access enforced by CAPS itself
- No shared service account credentials to manage or rotate
- Aligns with principle of least privilege

**Implementation:**
- User's CAPS JWT is obtained during SSO authentication
- JWT is cached for the session duration (refreshed on heartbeat)
- All `CaseyMemberPolicyService` and `CaseyReferenceDataService` calls use the user's JWT
- Fallback: if no user JWT available (e.g., scheduled sync), uses service account from configuration

### TDR-007: Email File Processing - Multi-Library Approach

| Field | Value |
|-------|-------|
| **Decision Date** | January 2026 |
| **Status** | Accepted |
| **Decision** | Use multiple libraries for email processing: PHP libraries for EML, Python sidecar for MSG |

**PHP Libraries:**
| Library | Version | Purpose |
|---------|---------|---------|
| `php-mime-mail-parser` | ^9.0 | Primary EML parsing |
| `zbateson/mail-mime-parser` | ^3.0 | Secondary EML parsing (fallback/validation) |
| `hfig/mapi` | ^1.4 | MAPI/MSG format support in PHP |
| `webklex/php-imap` | ^6.2 | IMAP protocol support |

**Python Sidecar:**
- FastAPI service (`python-msg-parser/`) on port 8000
- `POST /parse-msg` - Extract subject, sender, recipients, date, body, attachments from MSG files
- `GET /health` - Health check endpoint
- Called via Guzzle HTTP client from Laravel

**Rationale:**
- MSG format is complex (Microsoft proprietary MAPI); Python libraries handle it more reliably
- EML format is standard MIME; PHP libraries handle it natively
- Sidecar pattern keeps the main application lean while leveraging best-in-class parsers

### TDR-008: Multi-Tenancy - Single Database with Tenant Scoping

| Field | Value |
|-------|-------|
| **Decision Date** | April 2026 |
| **Status** | Accepted |
| **Decision** | Implement multi-tenancy using a single database with `tenant_id` columns and query-level scoping |

**Rationale:**
- Simplest deployment model (single database, single application instance)
- `BelongsToTenant` trait provides automatic scoping with minimal code changes
- Global scopes ensure tenant isolation at the query level
- Easier migration path from single-tenant to multi-tenant
- Lower operational cost than database-per-tenant

**Trade-offs Accepted:**
- Performance may degrade with very large tenant data (mitigated by indexing on `tenant_id`)
- No physical data isolation (mitigated by query-level enforcement and testing)
- Schema changes affect all tenants simultaneously

---

## 8. Risk Register

### 8.1 Active Risks

| ID | Risk | Probability | Impact | Severity | Mitigation | Owner | Status |
|----|------|------------|--------|----------|-----------|-------|--------|
| R-001 | **CAPS API Unavailability** - CAPS API goes down during business hours, blocking verification and sync | Medium | High | High | Graceful degradation: uploads continue without verification; sync retries on next schedule; verification can be triggered manually later | Integration Dev | Mitigated |
| R-002 | **Memory Exhaustion on Large Datasets** - Fetching 76,000+ CAPS members causes PHP OOM | High | High | Critical | Smart pagination in `CaseyMemberPolicyService`: < 5,000 records use paginated fetch; > 5,000 use per-ID lookup; PHP memory limit enforced at 128 MB | Integration Dev | Mitigated |
| R-003 | **SSO Microservice Data Loss** - In-memory session store lost on service restart | Medium | Medium | Medium | Graceful degradation: both CAPS and Tracker work independently; users re-authenticate on next request; SSO is convenience, not requirement | Integration Dev | Mitigated |
| R-004 | **CAPS API Parameter Confusion** - Using wrong parameter (`?search=` vs `?idNumber=` vs `?companyId=` vs `?organizationId=`) returns incorrect or full datasets | High | High | Critical | Documented in integration analysis; each parameter's behaviour is tested and verified; code uses correct parameters exclusively (`?idNumber=` for members, `?organizationId=` for policies) | Integration Dev | Mitigated |
| R-005 | **Reference Data Drift** - CAPS and Tracker entity data diverges over time | Medium | Medium | Medium | CAPS treated as authoritative source; daily sync at 02:30; manual sync available; `casey_id` keying ensures reliable matching; global scope excludes non-synced records | Integration Dev | Mitigated |
| R-006 | **Duplicate Submissions to CAPS** - Same file submitted multiple times due to webhook failure or user error | Low | High | Medium | Idempotency via upload reference code + file hash; re-upload within 30 days requires reason; CAPS batch ID tracking prevents duplicate processing | Backend Dev | Mitigated |
| R-007 | **JWT Key/Secret Mismatch** - SSO JWT shared secret differs between CAPS and Tracker environments | Low | High | Medium | HS256 secret configured via environment variable; documented in deployment guide; health check endpoint validates SSO connectivity | Integration Dev | Mitigated |
| R-008 | **File Storage Capacity** - Local filesystem storage fills up over time | Medium | High | High | S3Adapter infrastructure exists for cloud storage migration; monitoring for disk usage; 7-year retention policy governs archival | DevOps | Monitoring |
| R-009 | **Webhook Reliability** - CAPS webhook delivery fails (network issues, service restart) | Medium | Medium | Medium | `WebhookDelivery` model logs all inbound webhooks; replay capability via `WebhookReplayController`; CAPS implements retry with exponential backoff | Integration Dev | Mitigated |
| R-010 | **POPIA Compliance Violation** - SA ID numbers exposed or improperly handled | Low | Critical | High | SA ID numbers classified as Confidential; access-controlled via RBAC; audit-logged on all access; no ID numbers in application logs; encrypted at rest | Backend Dev | Monitoring |
| R-011 | **Single Point of Failure - Python MSG Sidecar** - MSG parser sidecar goes down, blocking email uploads | Medium | Medium | Medium | Health check endpoint (`/health`) monitored; EML files processed natively without sidecar; MSG conversion is best-effort (upload proceeds without conversion) | Backend Dev | Mitigated |
| R-012 | **Database Performance Degradation** - Growing audit/notification/upload tables slow queries | Low | Medium | Medium | Indexed on `tenant_id`, `created_at`, and foreign keys; notification retention of 90 days with auto-clear; audit records archived after 7 years | Backend Dev | Monitoring |

### 8.2 Closed Risks

| ID | Risk | Resolution | Closed Date |
|----|------|-----------|------------|
| R-C01 | CAPS `?search=` parameter returns unfiltered full dataset | Documented and verified; code never uses `?search=`; uses `?idNumber=` exclusively | April 2026 |
| R-C02 | Companies scoped 1:1 to municipalities would block cross-municipality submissions | `municipality_id` on companies made nullable (migration `2026_04_15_130000`); every company submits to every municipality | April 2026 |
| R-C03 | No CAPS entity linkage | `casey_id` columns added to companies and municipalities; sync fully operational | April 2026 |

### 8.3 Risk Assessment Matrix

```
                  Impact
                  Low    Medium    High    Critical
Probability  +--------+---------+--------+-----------+
High         |        |         | R-002  |           |
             |        |         | R-004  |           |
             +--------+---------+--------+-----------+
Medium       |        | R-003   | R-001  | R-010     |
             |        | R-005   | R-008  |           |
             |        | R-009   |        |           |
             |        | R-011   |        |           |
             +--------+---------+--------+-----------+
Low          |        | R-012   | R-006  |           |
             |        |         | R-007  |           |
             +--------+---------+--------+-----------+
```

---

## 9. Quality Assurance and Metrics

### 9.1 Quality Strategy

The Submission Tracker employs a multi-layered quality assurance strategy combining automated testing, audit-based regression detection, manual verification, and code quality standards.

### 9.2 Testing Framework

| Layer | Tool | Coverage | Status |
|-------|------|---------|--------|
| Unit Tests | PHPUnit 11.5 | Service classes, model logic, validation | Configured |
| Feature Tests | PHPUnit 11.5 | Controller actions, middleware, authorisation | Configured |
| Integration Tests | PHPUnit 11.5 | CAPS API client, SSO flow, webhook handling | Configured |
| Browser Tests | Manual | Upload workflow, calendar interaction, file preview | Ongoing |
| API Tests | Sanctum test helpers | All v1 API endpoints | Configured |
| Code Style | PHP-CS-Fixer 3.92, Laravel Pint 1.24 | PSR-12 compliance | Enforced |
| Frontend Linting | ESLint 9.37, Stylelint 16.25 | Vue component quality, CSS standards | Enforced |

### 9.3 Code Quality Standards

| Standard | Tool | Configuration |
|----------|------|--------------|
| PHP Coding Style | PSR-12 | Enforced via `friendsofphp/php-cs-fixer` ^3.92 |
| PHP Static Analysis | Laravel Pint | Enforced via `laravel/pint` ^1.24 |
| JavaScript Style | ESLint | Vue plugin + Prettier integration |
| CSS Style | Stylelint | Standard + Tailwind + Vue recommended configs |
| Vue Component Style | eslint-plugin-vue | Standard recommended rules |
| Dependency Security | Composer audit, npm audit | Run before each release |

### 9.4 Quality Metrics

| Metric | Target | Current | Measurement Method |
|--------|--------|---------|-------------------|
| **Uptime** | 99.5% during business hours (07:00-18:00 SAST) | Monitoring in place | Server monitoring tools |
| **Page Load Time** | < 2 seconds (p95) | Meeting target | Laravel Debugbar in development; browser metrics |
| **File Upload Processing** | < 10 seconds for 10 MB file | Meeting target | Application-level timing |
| **CAPS Verification Time** | < 60 seconds per upload | Meeting target | Stored in audit trail |
| **API Response Time** | < 500ms (p95) | Meeting target | API logging middleware |
| **Audit Trail Completeness** | 100% of model changes captured | 100% | `RecordsAuditTrail` concern on all models |
| **RBAC Coverage** | 100% of routes protected | 100% | All routes use `permission:xxx` middleware |
| **CAPS Sync Success Rate** | > 99% daily syncs succeed | Monitoring | Scheduled job logging |
| **SSO Bridge Latency** | < 5 seconds login/logout propagation | Meeting target | SSO session service timing |
| **Zero Known Critical Bugs** | 0 | 0 | Issue tracker |

### 9.5 Audit-Based Regression Detection

The comprehensive audit trail serves as a secondary regression detection mechanism:

1. **Model Change Auditing** - Every create, update, and delete operation on all models is captured via the `RecordsAuditTrail` concern, storing old and new values. Unexpected changes (e.g., status reversions, permission escalations) are detectable through audit queries.

2. **Authentication Event Auditing** - All login, logout, SSO, and failed authentication events are logged. Anomalous patterns (e.g., repeated failures, unusual login times) are detectable.

3. **CAPS Verification History** - Verification results are stored permanently on upload records. Comparison over time detects drift in CAPS data quality or verification logic changes.

4. **Request-Level Logging** - `AuditTrailMiddleware` captures IP address, user agent, and route for every authenticated request. Traffic patterns and error rates are analysable.

### 9.6 Acceptance Testing Approach

Each module has defined acceptance criteria documented in the Requirements Documentation (document 01). Key test scenarios include:

| Scenario | Module | Verification Method |
|---------|--------|-------------------|
| Upload three file types for assigned company | MOD-UPL | Manual walkthrough |
| Re-upload within 30 days prompts for reason | MOD-UPL | Manual + unit test |
| CAPS verification runs automatically post-upload | MOD-VER | Manual + integration test |
| Verification detects premium mismatch > R0.01 | MOD-VER | Unit test with fixture data |
| SSO login from CAPS auto-creates Tracker session | MOD-AUTH | Manual cross-system test |
| SSO logout from CAPS destroys Tracker session | MOD-AUTH | Manual cross-system test |
| Daily sync creates new CAPS companies | MOD-SYNC | Scheduled job log review |
| Admin cannot see other tenant's data | MOD-TNT | Manual + feature test |
| Audit trail captures all model changes | MOD-AUD | Automated via trait; spot-checked manually |
| Webhook updates upload CAPS status | MOD-API | Integration test with mock CAPS payload |

---

## 10. Change Management Process

### 10.1 Change Request Workflow

```
+------------------+     +------------------+     +------------------+
|  Change Request  |---->|  Impact Analysis |---->|  Approval        |
|  Submitted       |     |  & Estimation    |     |  Decision        |
+------------------+     +------------------+     +--------+---------+
                                                           |
                                               +-----------+-----------+
                                               |                       |
                                        +------+------+         +-----+------+
                                        |  Approved   |         |  Rejected  |
                                        +------+------+         +------------+
                                               |
                                        +------+------+
                                        | Development |
                                        | & Testing   |
                                        +------+------+
                                               |
                                        +------+------+
                                        |  Code Review|
                                        +------+------+
                                               |
                                        +------+------+
                                        |  Staging    |
                                        |  Deployment |
                                        +------+------+
                                               |
                                        +------+------+
                                        |  UAT Sign-  |
                                        |  off        |
                                        +------+------+
                                               |
                                        +------+------+
                                        | Production  |
                                        | Release     |
                                        +------+------+
                                               |
                                        +------+------+
                                        | Post-Release|
                                        | Verification|
                                        +------+------+
```

### 10.2 Change Categories

| Category | Description | Approval Authority | Lead Time |
|----------|-----------|-------------------|-----------|
| **Emergency** | Production-down fix, security vulnerability, data loss | Dev Lead (verbal) | Immediate |
| **Standard** | Bug fix, minor enhancement, configuration change | Dev Lead | 1-3 days |
| **Significant** | New feature, module change, integration modification | Dev Lead + Sponsor | 1-2 weeks |
| **Major** | Architecture change, new integration, data model change | Dev Lead + Sponsor + Stakeholders | 2-4 weeks |

### 10.3 Change Request Template

| Field | Description |
|-------|-----------|
| **CR Number** | Auto-generated (CR-YYYY-NNN) |
| **Title** | Brief description of the change |
| **Requester** | Name and role |
| **Date Submitted** | Date |
| **Category** | Emergency / Standard / Significant / Major |
| **Affected Modules** | List of MOD-xxx identifiers |
| **Business Justification** | Why is this change needed? |
| **Technical Description** | What will change? (database, code, configuration) |
| **Impact Assessment** | Which users, systems, and data are affected? |
| **Rollback Plan** | How to revert if the change fails |
| **Testing Requirements** | What must be tested before release? |
| **Estimated Effort** | Hours or days |
| **Target Release** | Version number or date |

### 10.4 Database Change Management

Database changes follow a strict migration-based process:

1. **All schema changes** are implemented as Laravel migrations (never manual DDL)
2. **Migration naming convention:** `YYYY_MM_DD_HHMMSS_description.php`
3. **Migrations are version-controlled** in `database/migrations/`
4. **Rollback methods** must be implemented in every migration's `down()` method
5. **Data migrations** (backfills, transformations) are separate from schema migrations
6. **Migration review** is mandatory before merging to main branch

**Current Migration Count:** 23 migrations spanning September 2025 to April 2026

### 10.5 Configuration Change Management

| Configuration Type | Location | Change Process |
|-------------------|----------|---------------|
| Environment variables | `.env` | Manual update, documented in deployment guide |
| Application config | `config/*.php` | Code change, standard CR process |
| RBAC permissions | Database (Spatie) | Seeder update + migration |
| Tenant settings | `tenant_settings` table | Admin UI or API |
| CAPS API endpoints | `.env` / `config/services.php` | Environment variable update |
| SSO shared secret | `.env` | Coordinated update with CAPS team |

---

## 11. Release Management

### 11.1 Release Strategy

The Submission Tracker follows a **trunk-based development** model with release branches for production deployments.

### 11.2 Versioning Scheme

**Semantic Versioning (SemVer):** `MAJOR.MINOR.PATCH`

| Component | Increment When |
|-----------|---------------|
| MAJOR | Breaking changes to API contracts, data model restructuring, major feature overhaul |
| MINOR | New features, new modules, significant enhancements |
| PATCH | Bug fixes, minor improvements, dependency updates |

**Current Version:** 1.0.0 (April 2026 - Production-ready release)

### 11.3 Release Process

#### Pre-Release Checklist

| Step | Action | Owner | Verification |
|------|--------|-------|-------------|
| 1 | All CRs for release are approved and merged | Dev Lead | CR tracker review |
| 2 | All migrations are tested (up and down) | Backend Dev | Local + staging |
| 3 | PHPUnit tests pass | QA | `php artisan test` |
| 4 | ESLint and Stylelint pass | Frontend Dev | `npm run lint` |
| 5 | PHP-CS-Fixer passes | Backend Dev | `vendor/bin/php-cs-fixer fix --dry-run` |
| 6 | Composer and npm audit clean | DevOps | `composer audit`, `npm audit` |
| 7 | Frontend build succeeds | Frontend Dev | `npm run build` |
| 8 | Staging deployment successful | DevOps | Manual verification |
| 9 | UAT sign-off obtained | QA + Sponsor | Sign-off email/ticket |
| 10 | Release notes drafted | Dev Lead | Document review |
| 11 | Rollback plan documented | DevOps | Plan review |
| 12 | CAPS team notified (if integration changes) | Integration Dev | Email confirmation |

#### Deployment Steps

```
1. Create release branch: release/vX.Y.Z
2. Final testing on release branch
3. Tag release: vX.Y.Z
4. Deploy to staging environment
5. Run migrations: php artisan migrate
6. Clear caches: php artisan config:clear && php artisan cache:clear
7. Build frontend: npm run build
8. Smoke test on staging
9. Deploy to production
10. Run production migrations
11. Clear production caches
12. Verify CAPS sync connectivity
13. Verify SSO bridge connectivity
14. Smoke test on production
15. Monitor error logs for 30 minutes
16. Announce release completion
```

#### Post-Release Verification

| Check | Expected Result | Action if Failed |
|-------|----------------|-----------------|
| Application loads | Dashboard renders | Rollback |
| Login works (local) | Session created | Rollback |
| Login works (SSO) | CAPS bridge functional | Check SSO service; degrade gracefully |
| Upload works | File stored, reference generated | Rollback |
| CAPS verification | Results returned | Check CAPS API; degrade gracefully |
| CAPS sync | Data fetched | Check CAPS API; degrade gracefully |
| Audit trail | Events logged | Investigate; non-blocking |
| Notifications | Generated on actions | Investigate; non-blocking |

### 11.4 Rollback Procedure

| Scenario | Action | Recovery Time |
|---------|--------|--------------|
| Application error after deploy | Revert to previous release tag; `php artisan migrate:rollback` | < 15 minutes |
| Database migration failure | Run migration `down()` method; restore from backup if needed | < 30 minutes |
| CAPS integration failure | Disable CAPS features via environment flags; system continues with local data | < 5 minutes |
| SSO failure | Disable SSO via `CASEY_SSO_ENABLED=false`; users log in locally | < 2 minutes |

### 11.5 Release History

| Version | Date | Type | Key Changes |
|---------|------|------|------------|
| 0.1.0 | October 2025 | Initial | Core platform: uploads, deadlines, RBAC, audit trail |
| 0.2.0 | October 2025 | Minor | User tracking on uploads, re-upload workflow, file preview |
| 0.3.0 | January 2026 | Minor | API layer (Sanctum), MSG-to-EML conversion |
| 0.4.0 | February 2026 | Minor | CAPS external password hash authentication bridge |
| 1.0.0 | April 2026 | Major | Full CAPS integration: SSO, verification, sync, webhooks, multi-tenancy |

---

## 12. Communication Plan

### 12.1 Communication Matrix

| Audience | Content | Frequency | Channel | Owner |
|---------|---------|-----------|---------|-------|
| **Project Sponsor** | Project status, risks, decisions needed | Weekly | Status meeting + email summary | Dev Lead |
| **Development Team** | Sprint planning, technical decisions, code reviews | Daily | Stand-up + team chat | Dev Lead |
| **CAPS Team** | Integration changes, API contract updates, SSO coordination | As needed (minimum fortnightly) | Email + joint meeting | Integration Dev |
| **End Users** | Release announcements, training, feature guides | Per release | Email + in-app notification | Dev Lead |
| **IT Operations** | Deployment schedules, infrastructure changes, monitoring alerts | Per release + as needed | Email + ops channel | DevOps |
| **Management** | Monthly summary, budget status, milestone achievement | Monthly | Written report | Dev Lead |
| **External Auditors** | Compliance documentation, audit trail access | As requested | Formal documentation package | Dev Lead + Sponsor |

### 12.2 Meeting Schedule

| Meeting | Frequency | Duration | Attendees | Purpose |
|---------|-----------|----------|-----------|---------|
| Daily Stand-up | Daily | 15 min | Dev team | Progress, blockers, plan |
| Sprint Planning | Fortnightly | 1 hour | Dev team + Dev Lead | Sprint scope and priorities |
| Sprint Review | Fortnightly | 30 min | Dev team + Sponsor | Demo completed work |
| Architecture Review | Monthly | 1 hour | Dev team | Technical debt, design decisions |
| CAPS Integration Sync | Fortnightly | 30 min | Integration Dev + CAPS team | API changes, SSO coordination |
| Stakeholder Update | Monthly | 30 min | Dev Lead + Sponsor | Project health, risks, budget |

### 12.3 Escalation Path

```
Level 1: Development Team
    |-- Resolve within: 4 hours (standard), 1 hour (emergency)
    |
Level 2: Development Lead
    |-- Resolve within: 1 business day (standard), 4 hours (emergency)
    |
Level 3: Project Sponsor
    |-- Resolve within: 2 business days (standard), 1 business day (emergency)
    |
Level 4: Casey & Associates Management
    |-- Final escalation for business-critical decisions
```

### 12.4 Incident Communication Protocol

| Severity | Definition | Notification | Update Frequency |
|----------|-----------|-------------|-----------------|
| **P1 - Critical** | Production down, data loss, security breach | Immediate phone + email to Dev Lead, Sponsor, Ops | Every 30 minutes until resolved |
| **P2 - High** | Major feature broken, CAPS integration down | Email within 1 hour to Dev Lead, Sponsor | Every 2 hours until resolved |
| **P3 - Medium** | Minor feature broken, performance degradation | Email within 4 hours to Dev Lead | Daily until resolved |
| **P4 - Low** | Cosmetic issue, minor inconvenience | Logged in issue tracker | Per sprint review |

---

## 13. Configuration Management

### 13.1 Source Code Management

| Aspect | Detail |
|--------|--------|
| **Repository** | Git (local + remote) |
| **Branching Strategy** | Trunk-based with feature branches |
| **Main Branch** | `main` (protected) |
| **Feature Branch Naming** | `feature/MOD-xxx-description` |
| **Bugfix Branch Naming** | `fix/MOD-xxx-description` |
| **Release Branch Naming** | `release/vX.Y.Z` |
| **Commit Message Format** | Conventional: `type(scope): description` |
| **Code Review** | Required before merge to main |

### 13.2 Environment Configuration

| Variable | Purpose | Environment-Specific |
|----------|---------|---------------------|
| `APP_ENV` | Application environment (local, staging, production) | Yes |
| `APP_DEBUG` | Debug mode toggle | Yes (false in production) |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MySQL connection | Yes |
| `CASEY_API_BASE_URL` | CAPS API base URL | Yes |
| `CASEY_SSO_ENABLED` | Toggle SSO bridge | Yes |
| `CASEY_SSO_SECRET` | HS256 shared secret for JWT verification | Yes (coordinated with CAPS) |
| `SSO_SERVICE_URL` | Node.js SSO microservice URL | Yes |
| `CAPS_WEBHOOK_SECRET` | HMAC-SHA256 shared secret for webhook verification | Yes (coordinated with CAPS) |
| `MAIL_*` | Email configuration | Yes |
| `REDIS_*` | Redis connection for caching/sessions | Yes |

### 13.3 Dependency Management

| Package Manager | Lock File | Update Policy |
|----------------|-----------|--------------|
| Composer (PHP) | `composer.lock` | Monthly review; security patches immediately |
| npm (Node.js) | `package-lock.json` | Monthly review; security patches immediately |

**Key Dependency Versions (as of April 2026):**

| Dependency | Version | Purpose |
|-----------|---------|---------|
| `laravel/framework` | ^12.0 | Application framework |
| `laravel/sanctum` | ^4.2 | API authentication |
| `spatie/laravel-permission` | ^6.21 | RBAC |
| `maatwebsite/excel` | ^3.1 | Excel export |
| `phpoffice/phpspreadsheet` | ^1.30 | Spreadsheet parsing |
| `guzzlehttp/guzzle` | ^7.10 | HTTP client (CAPS API) |
| `predis/predis` | ^3.3 | Redis client |
| `vue` | ^3.5.21 | Frontend framework |
| `@inertiajs/vue3` | ^2.1.5 | SPA bridge |
| `tailwindcss` | ^4.1.13 | CSS framework |
| `vite` | ^7.1.5 | Build tool |
| `@fullcalendar/vue3` | ^6.1.20 | Calendar component |

---

## 14. Dependency Management

### 14.1 External System Dependencies

| System | Dependency Type | Criticality | Fallback |
|--------|---------------|------------|----------|
| **CAPS API** | Runtime (verification, sync) | High | Graceful degradation: uploads proceed without verification; sync retries next cycle |
| **SSO Microservice** | Runtime (cross-platform auth) | Medium | Users log in locally; both systems work independently |
| **Python MSG Parser** | Runtime (file conversion) | Medium | MSG files uploaded as-is; conversion deferred or skipped |
| **MySQL Database** | Runtime (all data) | Critical | No fallback; system non-functional without database |
| **Redis** | Runtime (caching, sessions) | Medium | Laravel falls back to file-based caching/sessions |

### 14.2 Internal Module Dependencies

| Module | Depends On | Is Depended On By |
|--------|-----------|-------------------|
| MOD-AUTH | None (foundation) | All other modules |
| MOD-UPL | MOD-AUTH, MOD-AUD | MOD-VER, MOD-NOT |
| MOD-VER | MOD-UPL, MOD-AUTH, CAPS API | MOD-NOT |
| MOD-DL | MOD-AUTH, MOD-AUD | MOD-NOT, MOD-UPL |
| MOD-NOT | MOD-AUTH | None (leaf) |
| MOD-ADM | MOD-AUTH, MOD-AUD | None (leaf) |
| MOD-SYNC | MOD-AUTH, CAPS API | MOD-UPL, MOD-VER |
| MOD-TNT | None (infrastructure) | All modules (via trait) |
| MOD-AUD | None (cross-cutting) | All modules (via trait) |
| MOD-API | MOD-AUTH, All modules | External consumers |

### 14.3 Third-Party Service Agreements

| Service | Provider | SLA | Contact Protocol |
|---------|---------|-----|-----------------|
| CAPS API | Casey & Associates (internal) | Business hours availability (07:00-18:00 SAST) | Internal escalation |
| MySQL Hosting | Infrastructure team | 99.9% uptime | Ops ticket |
| Redis Hosting | Infrastructure team | 99.5% uptime | Ops ticket |

---

## 15. Environment Strategy

### 15.1 Environment Catalogue

| Environment | Purpose | Data | CAPS Connection | SSO |
|------------|---------|------|----------------|-----|
| **Local (Development)** | Developer workstations | Seeded test data | CAPS dev/QAS or mock | Optional |
| **Staging** | Pre-production testing, UAT | Anonymised production-like data | CAPS UAT | Enabled |
| **Production** | Live system | Real data | CAPS Production | Enabled |

### 15.2 Environment Parity

| Aspect | Local | Staging | Production |
|--------|-------|---------|-----------|
| PHP version | 8.2+ | 8.2+ | 8.2+ |
| MySQL version | 8.0+ | 8.0+ | 8.0+ |
| Node.js version | 20+ | 20+ | 20+ |
| Laravel version | 12.x | 12.x | 12.x |
| Redis | Optional | Required | Required |
| SSO Microservice | Optional | Running | Running |
| Python MSG Parser | Running | Running | Running |
| CAPS API | Dev/Mock | UAT | Production |

### 15.3 Data Management per Environment

| Environment | Data Source | Refresh Frequency | Anonymisation |
|------------|-----------|-------------------|---------------|
| Local | Seeders + manual | On demand | N/A (test data) |
| Staging | Production snapshot | Monthly | SA ID numbers masked, passwords reset |
| Production | Live data | N/A | N/A |

---

## 16. Budget and Resource Tracking

### 16.1 Resource Allocation

| Resource | Allocation | Duration | Phase |
|----------|-----------|----------|-------|
| Backend Development | Full-time | Sep 2025 - Apr 2026 | All phases |
| Frontend Development | Full-time | Sep 2025 - Apr 2026 | All phases |
| Integration Development | Part-time (Sep-Feb), Full-time (Apr) | Sep 2025 - Apr 2026 | Phase 1, 3, 4, 5 |
| Quality Assurance | Part-time | Sep 2025 - Apr 2026 | All phases |
| DevOps | Part-time | Sep 2025 - Apr 2026 | All phases |

### 16.2 Effort Distribution by Phase

| Phase | Duration | Estimated Effort | Actual Effort | Variance |
|-------|----------|-----------------|--------------|---------|
| Phase 1: Foundation | 4 weeks | Baseline | On track | None |
| Phase 2: Enhancement | 2 weeks | Baseline | On track | None |
| Phase 3: API & Conversion | 2 weeks | Baseline | On track | None |
| Phase 4: Auth Bridge | 1 week | Baseline | On track | None |
| Phase 5: CAPS Integration | 3 weeks | 150% of baseline (complex integration) | On track | Within tolerance |

### 16.3 Effort Distribution by Module

| Module | Estimated % | Actual % | Notes |
|--------|-----------|---------|-------|
| MOD-AUTH | 15% | 18% | SSO complexity higher than estimated |
| MOD-UPL | 25% | 23% | File preview reused existing libraries |
| MOD-VER | 15% | 17% | CAPS API parameter research required extra effort |
| MOD-DL | 10% | 9% | Calendar library accelerated delivery |
| MOD-NOT | 5% | 5% | Laravel notifications simplified implementation |
| MOD-ADM | 10% | 10% | Standard CRUD operations |
| MOD-SYNC | 5% | 6% | Upsert logic required careful testing |
| MOD-TNT | 5% | 5% | Trait-based approach reduced implementation effort |
| MOD-AUD | 5% | 4% | Trait-based approach reduced implementation effort |
| MOD-API | 5% | 3% | API resource controllers simplified implementation |

---

## 17. Stakeholder Register

### 17.1 Internal Stakeholders

| Stakeholder | Role | Interest | Influence | Engagement Level |
|------------|------|---------|-----------|-----------------|
| Casey & Associates Management | Project Sponsor | Business value, compliance, ROI | High | Manage Closely |
| Development Team Lead | Project Manager / Technical Lead | Delivery, quality, architecture | High | Manage Closely |
| Backend Developers | Implementers | Code quality, technical growth | Medium | Keep Satisfied |
| Frontend Developers | Implementers | UX quality, technical growth | Medium | Keep Satisfied |
| Integration Developers | CAPS bridge implementers | System interoperability | Medium | Keep Satisfied |
| QA Team | Quality gatekeepers | Test coverage, defect rates | Medium | Keep Informed |
| IT Operations | Infrastructure, deployment | System stability, monitoring | Medium | Keep Informed |
| Finance Team | Budget oversight | Cost control, value delivery | Low | Keep Informed |

### 17.2 System Stakeholders (Users)

| Stakeholder | System Role | Key Needs | Engagement |
|------------|-----------|-----------|-----------|
| Super Admins | Full system access | System configuration, user management | Training + feedback sessions |
| Admins | Administrative access | Municipality/company management, reporting | Training + feedback sessions |
| Managers | Operational oversight | Deadline management, upload monitoring | Training + user guide |
| Clerks | Operational users | File upload, submission tracking | Training + user guide |

### 17.3 External Stakeholders

| Stakeholder | Relationship | Interest | Communication |
|------------|-------------|---------|--------------|
| CAPS Development Team | Sister system | API stability, SSO coordination | Fortnightly sync meetings |
| Municipalities | Data providers (indirect) | Timely submission, data accuracy | Via Casey & Associates operations |
| Deduction Companies | Data consumers (indirect) | Accurate deduction processing | Via Casey & Associates operations |
| External Auditors | Compliance reviewers | Audit trail completeness, data protection | On-request documentation access |
| POPIA Regulator | Regulatory oversight | Personal data protection compliance | Annual compliance review |

---

## 18. Lessons Learned

### 18.1 What Went Well

| Lesson | Context | Recommendation |
|--------|---------|---------------|
| **Inertia.js eliminated API duplication** | Choosing Vue 3 + Inertia.js avoided building and maintaining a separate API layer for the frontend; server-side controllers directly serve page props | Continue using Inertia for internal-facing features; reserve REST API for external consumers |
| **Spatie/Permission accelerated RBAC** | Off-the-shelf RBAC with 23+ permissions and 4 roles was operational in days, not weeks | Use established packages for cross-cutting concerns rather than building from scratch |
| **`RecordsAuditTrail` trait provided universal auditing** | A single trait applied to all models gave 100% audit coverage with minimal code | Apply the trait pattern for other cross-cutting concerns (e.g., `BelongsToTenant`) |
| **Migration-based schema management** | 23 migrations provide a complete, reproducible history of database evolution from September 2025 to April 2026 | Never apply manual DDL; all schema changes must be migrations |
| **Graceful degradation for external dependencies** | SSO microservice being in-memory and CAPS API being intermittent were handled by designing for independence | Always design external integrations with fallback behaviour |
| **Smart pagination solved memory constraints** | The < 5,000 / > 5,000 threshold strategy prevented OOM while maintaining reasonable performance | Profile memory usage early; establish data volume thresholds before coding |

### 18.2 What Could Be Improved

| Lesson | Context | Recommendation |
|--------|---------|---------------|
| **CAPS API parameter behaviour was underdocumented** | The `?search=` parameter returns unfiltered data (unlike what the name suggests); `?organizationId=` is required (not `?companyId=`). This caused debugging effort. | Demand API documentation and test endpoints before integration coding begins |
| **SSO microservice lacks persistence** | In-memory session store is a known limitation; data lost on restart | Evaluate Redis-backed session store for the SSO microservice in a future iteration |
| **Automated test coverage could be higher** | PHPUnit is configured but not all modules have comprehensive test suites | Mandate minimum 80% code coverage for new modules before release |
| **No CI/CD pipeline yet** | Builds and deployments are manual | Establish automated CI/CD pipeline (GitHub Actions or similar) as a priority |
| **Local file storage limits scalability** | S3Adapter exists but is not active; local disk will fill up with 7-year retention | Activate S3 storage before production data volume becomes critical |
| **Company-municipality relationship assumption changed mid-project** | Originally assumed 1:1; changed to many-to-many (every company submits to every municipality) in April 2026 | Validate domain model assumptions with business stakeholders before initial schema design |

### 18.3 Technical Debt Register

| ID | Description | Module | Impact | Priority | Target Resolution |
|----|-----------|--------|--------|----------|-----------------|
| TD-001 | S3 storage adapter exists but is not connected | MOD-UPL | Local disk fills over time | High | Q3 2026 |
| TD-002 | SSO microservice lacks persistent session store | MOD-AUTH | Sessions lost on restart | Medium | Q3 2026 |
| TD-003 | Workflow engine infrastructure built but not activated | MOD-API | Unused code in codebase | Low | Q4 2026 |
| TD-004 | M365 mail adapter exists but is not connected | MOD-API | Unused code in codebase | Low | Q4 2026 |
| TD-005 | No CI/CD pipeline for automated testing and deployment | All | Manual processes, risk of human error | High | Q2 2026 |
| TD-006 | No WebSocket/Echo for real-time notifications | MOD-NOT | Users must refresh to see new notifications | Medium | Q3 2026 |
| TD-007 | Automated test coverage below 80% target | All | Regression risk | Medium | Q2 2026 |

---

## 19. Future Roadmap

### 19.1 Short-Term (Q2-Q3 2026)

| Initiative | Description | Priority | Dependencies | Effort |
|-----------|-----------|----------|-------------|--------|
| **CI/CD Pipeline** | Automated testing and deployment via GitHub Actions or similar | High | DevOps capacity | 2 weeks |
| **Automated Test Coverage** | Achieve 80% PHPUnit coverage for all modules | High | Dev team capacity | 4 weeks (ongoing) |
| **S3 File Storage Migration** | Activate `S3Adapter` for cloud file storage | High | AWS account, S3 bucket configuration | 1 week |
| **Real-Time Notifications** | WebSocket/Echo integration for push notifications | Medium | Redis, Laravel Echo Server | 2 weeks |
| **Mobile-Responsive Enhancements** | Optimise all views for tablet and mobile screen sizes | Medium | Frontend capacity | 3 weeks |
| **Redis-Backed SSO Sessions** | Replace in-memory store with Redis persistence in SSO microservice | Medium | Redis infrastructure | 1 week |

### 19.2 Medium-Term (Q4 2026 - Q1 2027)

| Initiative | Description | Priority | Dependencies | Effort |
|-----------|-----------|----------|-------------|--------|
| **Workflow Automation Engine** | Activate `WorkflowDefinition` and `WorkflowInstance` models for automated submission workflows | Medium | Business process definition | 4 weeks |
| **M365 Email Integration** | Connect `M365MailAdapter` for automated email ingestion from shared mailboxes | Medium | Microsoft 365 licence, Graph API credentials | 3 weeks |
| **Automated Report Generation** | Scheduled reports (upload summary, deadline compliance) distributed via email | Medium | Report template design, email infrastructure | 2 weeks |
| **Enhanced Analytics Dashboard** | Advanced analytics: trend analysis, compliance scores, user productivity metrics | Low | Analytics page already exists (`Analytics/Index.vue`) | 3 weeks |
| **Bulk Upload Processing** | Batch upload multiple municipality/company file sets in a single operation | Medium | UX design, backend processing queue | 3 weeks |

### 19.3 Long-Term (2027+)

| Initiative | Description | Priority | Dependencies |
|-----------|-----------|----------|-------------|
| **Municipality Self-Service Portal** | Direct portal for municipalities to upload files and check status | Low | Business decision, security review |
| **AI-Assisted Verification** | Machine learning for anomaly detection in premium data | Low | Data science capability, training data |
| **Multi-Region Deployment** | Deploy to multiple Azure/AWS regions for DR | Low | Budget, infrastructure capacity |
| **API Marketplace** | Open API for third-party deduction companies to query submission status | Low | Business model, security review |

### 19.4 Roadmap Dependencies

```
Q2 2026:  CI/CD Pipeline ----+
          Test Coverage ------+----> Foundation for all future releases
          S3 Migration -------+

Q3 2026:  Real-Time Notifs --+
          Mobile Responsive --+----> UX Modernisation
          Redis SSO ----------+

Q4 2026:  Workflow Engine ----+
          M365 Integration ---+----> Automation Suite
          Auto Reports -------+

Q1 2027:  Analytics ----------+
          Bulk Upload --------+----> Efficiency Package

2027+:    Self-Service Portal -+
          AI Verification -----+----> Platform Evolution
          Multi-Region ---------+
```

---

## 20. Document Control

### 20.1 Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | April 2026 | Development Team | Initial release - comprehensive project and management documentation |

### 20.2 Related Documents

| Document | ID | Location | Purpose |
|---------|-----|---------|---------|
| Requirements Documentation | 01 | `docs/01-REQUIREMENTS-DOCUMENTATION.md` | Functional and non-functional requirements, user stories, data requirements |
| CAPS Integration Analysis | - | `docs/Submission_Tracker_CAPS_Integration_Analysis.md` | Detailed technical analysis of CAPS integration architecture |
| CAPS Integration Proposal | - | `docs/Formal_CAPS_Integration_Proposal.docx` | Formal proposal for CAPS integration (business audience) |

### 20.3 Document Review Schedule

| Review | Frequency | Reviewer | Purpose |
|--------|-----------|---------|---------|
| Quarterly Review | Every 3 months | Dev Lead | Update timeline, risks, metrics |
| Post-Release Update | After each MINOR/MAJOR release | Dev Lead | Update release history, lessons learned |
| Annual Review | Annually | Dev Lead + Sponsor | Strategic alignment, roadmap refresh |
| Audit Review | On request | Dev Lead + External Auditor | Compliance verification |

### 20.4 Approval Signatures

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Development Team Lead | _________________ | _________________ | _________________ |
| Project Sponsor | _________________ | _________________ | _________________ |
| Head of Technology | _________________ | _________________ | _________________ |

---

*This document is classified as Internal - Confidential and is the property of Casey & Associates (Pty) Ltd. Unauthorised distribution is prohibited.*

*Document generated: April 2026 | Next review: July 2026*
