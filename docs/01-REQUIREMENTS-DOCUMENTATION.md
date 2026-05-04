# Requirements Documentation

## Submission Tracker - Casey & Associates

**Document Version:** 1.0
**Date:** April 2026
**Classification:** Internal - Confidential

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Business Context](#2-business-context)
3. [Stakeholders](#3-stakeholders)
4. [Functional Requirements](#4-functional-requirements)
5. [Non-Functional Requirements](#5-non-functional-requirements)
6. [Integration Requirements](#6-integration-requirements)
7. [Data Requirements](#7-data-requirements)
8. [User Stories & Acceptance Criteria](#8-user-stories--acceptance-criteria)
9. [Constraints & Assumptions](#9-constraints--assumptions)
10. [Glossary](#10-glossary)

---

## 1. Executive Summary

The Submission Tracker is an enterprise web application that manages the submission lifecycle for municipal payroll deduction files. Municipalities deduct premiums from employee salaries on behalf of deduction companies (insurance providers, loan companies, etc.), and each deduction must be properly submitted, tracked, verified against the CAPS system, and audited for compliance.

### Purpose
- Digitise the file submission workflow between municipalities and deduction companies
- Provide audit-ready tracking of all uploads and deadlines
- Integrate with CAPS (Casey Application Platform System) for member/policy verification, single sign-on, and reference data synchronisation
- Enforce role-based access control to segregate duties
- Support multi-tenancy for organisational isolation

### Scope
- File upload management (emails, spreadsheets, systems imports)
- Deadline scheduling per municipality with user assignments
- CAPS member and policy verification against uploaded data
- Single sign-on with CAPS via JWT-based bridge
- Notification system for deadline and assignment alerts
- Comprehensive admin panel for user, role, company, and municipality management
- Audit trail for all data changes and authentication events
- Reporting and export capabilities

---

## 2. Business Context

### 2.1 Business Problem

Casey & Associates administers payroll deductions across multiple South African municipalities. Each month, municipalities submit files containing deduction data (member IDs, policy codes, premium amounts) for dozens of deduction companies. The process requires:

1. Collecting three types of files per submission: original correspondence (emails), workings spreadsheets, and systems import files
2. Verifying that member and policy data matches CAPS records
3. Tracking deadlines per municipality and ensuring all companies are submitted
4. Maintaining a complete audit trail for regulatory compliance
5. Coordinating across multiple users with different access levels

### 2.2 Business Objectives

| ID | Objective | Success Metric |
|----|-----------|---------------|
| BO-1 | Eliminate manual file tracking | Zero spreadsheet-based tracking within 3 months of deployment |
| BO-2 | Ensure 100% submission compliance | All municipalities submit before their deadlines |
| BO-3 | Reduce data discrepancies | Automated CAPS verification catches mismatches before processing |
| BO-4 | Maintain audit readiness | Complete digital trail accessible within 60 seconds |
| BO-5 | Enable cross-system authentication | Single login across CAPS and Tracker |

### 2.3 Regulatory Context

- Municipal payroll deductions are governed by South African labour law
- Deduction companies require verifiable proof of submission
- All financial data handling must maintain an audit trail
- Member personal information (SA ID numbers) must be protected under POPIA (Protection of Personal Information Act)

---

## 3. Stakeholders

| Role | Responsibilities | System Interaction |
|------|------------------|--------------------|
| **Super Admin** | System configuration, all operations, user management | Full access to all features |
| **Admin** | User management, municipality/company administration, reporting | Admin panel + operational features |
| **Manager** | Oversee submissions, manage deadlines, view reports | Dashboard, deadlines, uploads, reports |
| **User (Clerk)** | Upload files, track personal submissions | Upload files, view own history, notifications |
| **CAPS System** | Source of truth for members, policies, and reference data | API integration, SSO, webhooks |
| **Municipalities** | Employer organisations that submit deduction files | Represented as data entities (not direct users) |
| **Deduction Companies** | Insurance/loan companies receiving deductions | Represented as data entities |

---

## 4. Functional Requirements

### 4.1 Authentication & Authorisation

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-AUTH-01 | System shall support local username/password authentication | Must |
| FR-AUTH-02 | System shall support CAPS SSO via HS256 JWT verification | Must |
| FR-AUTH-03 | SSO shall auto-provision users from JWT claims if enabled | Should |
| FR-AUTH-04 | SSO login/logout shall sync bidirectionally between CAPS and Tracker | Must |
| FR-AUTH-05 | Session timeout: 120 minutes inactivity, SSO-synced 10 minutes | Must |
| FR-AUTH-06 | Users with no Tracker roles shall be blocked from SSO login | Must |
| FR-AUTH-07 | Deactivated users shall be denied login on all methods | Must |
| FR-AUTH-08 | All authentication events shall be audit-logged | Must |
| FR-AUTH-09 | External password hash fallback for CAPS-synced users | Should |

### 4.2 File Upload Management

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-UPL-01 | Users shall upload three file types: original emails (.eml/.msg), workings spreadsheet (.xlsx/.csv), systems import (.xlsx/.csv) | Must |
| FR-UPL-02 | Uploads shall be scoped to a specific municipality + company combination | Must |
| FR-UPL-03 | Only users assigned to the company/municipality may upload | Must |
| FR-UPL-04 | Re-uploads within 30 days require a reason (type + note) | Must |
| FR-UPL-05 | Upload status progresses: Pending -> Processing -> Completed | Must |
| FR-UPL-06 | Status is determined by which file types are present | Must |
| FR-UPL-07 | MSG files shall be auto-converted to EML format | Should |
| FR-UPL-08 | Email dates shall be extracted from .eml headers | Should |
| FR-UPL-09 | Maximum file size: 10 MB per file | Must |
| FR-UPL-10 | Unique reference code generated per upload (10 chars) | Must |
| FR-UPL-11 | Admin users may upload without deadline or assignment checks | Must |

### 4.3 CAPS Member & Policy Verification

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-VER-01 | System shall verify uploaded member IDs against CAPS using the `?idNumber=` API parameter | Must |
| FR-VER-02 | System shall verify policy codes against CAPS using the `?organizationId=` API parameter | Must |
| FR-VER-03 | System shall detect premium amount mismatches (threshold: R0.01) | Must |
| FR-VER-04 | Verification shall run automatically after file upload | Must |
| FR-VER-05 | Verification shall match rows to the correct deduction company using the Company Name column | Must |
| FR-VER-06 | Company name resolution shall use fuzzy matching (exact, suffix-stripped, LIKE, word-prefix) | Must |
| FR-VER-07 | Manual re-verification shall be available from the History page | Should |
| FR-VER-08 | Results shall persist on the upload record (caps_verification JSON, caps_verified_at timestamp) | Must |
| FR-VER-09 | Results shall show: Members Found/Missing, Policies Found/Missing, Premium Mismatches, Verification Score | Must |

### 4.4 Deadline & Assignment Management

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-DL-01 | Admins shall create deadlines per municipality with a target date | Must |
| FR-DL-02 | Users shall be assigned to company/municipality combinations | Must |
| FR-DL-03 | Bulk assignment creation (deadline + multiple company/user pairs) | Must |
| FR-DL-04 | Dashboard shall show: upcoming, overdue, and all municipality deadlines | Must |
| FR-DL-05 | Calendar view with colour-coded events (overdue=red, today=amber, upcoming=green) | Should |
| FR-DL-06 | Deadline changes shall trigger notifications to affected users | Must |
| FR-DL-07 | Upload page shall show pending companies per municipality | Must |

### 4.5 Notifications

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-NOT-01 | Notifications shall be generated for: upload creation, deadline changes, assignment changes | Must |
| FR-NOT-02 | Users shall view, filter (read/unread/type), and manage notifications | Must |
| FR-NOT-03 | Mark as read (single and bulk), delete (single and clear all) | Must |
| FR-NOT-04 | Admin users may view other users' notifications | Should |

### 4.6 CAPS Reference Data Synchronisation

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-SYNC-01 | Municipalities and companies shall be synced from CAPS API | Must |
| FR-SYNC-02 | Sync shall be keyed by `casey_id` (CAPS record identifier) | Must |
| FR-SYNC-03 | Sync shall run on a daily schedule (02:30) | Must |
| FR-SYNC-04 | Manual sync trigger available from admin dashboard | Must |
| FR-SYNC-05 | Auto-sync on first login if no CAPS data exists | Must |
| FR-SYNC-06 | Local records without `casey_id` shall be excluded from dropdowns (global scope) | Must |
| FR-SYNC-07 | Sync shall upsert: create new, update existing, skip unchanged | Must |

### 4.7 Administration

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-ADM-01 | CRUD for users with role assignment | Must |
| FR-ADM-02 | CRUD for roles with permission management | Must |
| FR-ADM-03 | View/manage companies and municipalities (CAPS-synced) | Must |
| FR-ADM-04 | Audit trail viewer with filters (user, event type, date range) | Must |
| FR-ADM-05 | Reports: upload summary, deadline summary, export to Excel/CSV | Must |

### 4.8 File Preview & Export

| ID | Requirement | Priority |
|----|-------------|----------|
| FR-PRV-01 | Inline spreadsheet preview (table with search and pagination) | Must |
| FR-PRV-02 | Email preview (headers, body tabs: text/HTML/raw, attachments) | Must |
| FR-PRV-03 | MSG to EML conversion for standardised viewing | Should |
| FR-PRV-04 | Download individual files (original, workings, systems import) | Must |
| FR-PRV-05 | Export upload history to Excel | Should |

---

## 5. Non-Functional Requirements

### 5.1 Performance

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-PERF-01 | Page load time | < 2 seconds (p95) |
| NFR-PERF-02 | File upload processing | < 10 seconds for 10 MB file |
| NFR-PERF-03 | CAPS verification (per upload) | < 60 seconds |
| NFR-PERF-04 | Dashboard rendering | < 3 seconds |
| NFR-PERF-05 | API response time | < 500ms (p95) |

### 5.2 Scalability

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-SCL-01 | Concurrent users | 100+ simultaneous |
| NFR-SCL-02 | Data volume | 500,000+ uploads |
| NFR-SCL-03 | Companies | 500+ CAPS-synced companies |
| NFR-SCL-04 | Municipalities | 50+ CAPS-synced municipalities |
| NFR-SCL-05 | CAPS members | 76,000+ (API must handle without OOM) |

### 5.3 Availability

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-AVL-01 | Uptime | 99.5% during business hours (07:00-18:00 SAST) |
| NFR-AVL-02 | SSO graceful degradation | Tracker works independently when SSO microservice is down |
| NFR-AVL-03 | CAPS API degradation | Uploads continue; verification deferred |

### 5.4 Security

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-SEC-01 | Authentication | Multi-method (local + SSO) |
| NFR-SEC-02 | Authorisation | RBAC with 4+ roles and 23+ granular permissions |
| NFR-SEC-03 | Data protection | POPIA-compliant handling of SA ID numbers |
| NFR-SEC-04 | Audit trail | All data changes and auth events logged |
| NFR-SEC-05 | Session security | HTTP-only cookies, SameSite=Lax, CSRF protection |
| NFR-SEC-06 | API security | API key (SHA256 hashed), HMAC-SHA256 webhooks |
| NFR-SEC-07 | Password storage | Bcrypt (12 rounds minimum) |

### 5.5 Usability

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-USE-01 | Browser support | Chrome, Edge, Firefox (latest 2 versions) |
| NFR-USE-02 | Responsive design | Desktop-first with mobile support |
| NFR-USE-03 | Accessibility | WCAG 2.1 Level A minimum |

### 5.6 Maintainability

| ID | Requirement | Target |
|----|-------------|--------|
| NFR-MNT-01 | Code quality | PSR-12 PHP standard, ESLint for Vue |
| NFR-MNT-02 | Documentation | All 7 required document types maintained |
| NFR-MNT-03 | Dependency management | Composer + NPM with lock files |

---

## 6. Integration Requirements

### 6.1 CAPS API Integration

| Endpoint | Method | Purpose | Auth |
|----------|--------|---------|------|
| `/v1/user/login` | POST | Service account authentication | Username/password |
| `/v1/admin/organization/municipalities` | GET | Sync municipality reference data | Bearer token |
| `/v1/admin/organization/companies` | GET | Sync company reference data | Bearer token |
| `/v1/member/api/members?idNumber=X` | GET | Verify member by SA ID number | Bearer token |
| `/v1/premiums/status/fetch?organizationId=X` | GET | Fetch policies for deduction company | Bearer token |

### 6.2 SSO Session Microservice

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `POST /sessions` | POST | Register session after login |
| `GET /sessions/:emp` | GET | Check session + heartbeat |
| `DELETE /sessions/:emp` | DELETE | Remove session on logout |
| `GET /sessions` | GET | List all active sessions |
| `GET /health` | GET | Health check (no auth) |

### 6.3 CAPS Webhooks (Inbound)

| Event | Trigger | Action |
|-------|---------|--------|
| `payment_batch.imported` | CAPS imports a batch | Update upload caps_status |
| `payment_batch.allocated` | CAPS allocates payments | Update upload caps_status |
| `payment_batch.failed` | CAPS batch fails | Update upload with error details |
| `payment_batch.exported` | CAPS exports batch | Update upload caps_status |
| `refund.created/allocated` | Refund processed | Track refund status |

---

## 7. Data Requirements

### 7.1 Data Entities

| Entity | Source | Volume | Sync Frequency |
|--------|--------|--------|----------------|
| Municipalities | CAPS API | ~17-50 | Daily (02:30) + manual |
| Companies | CAPS API | ~190-500 | Daily (02:30) + manual |
| Members | CAPS API | ~76,000 | On-demand (verification) |
| Policies | CAPS API | ~735,000 | On-demand (verification) |
| Uploads | User input | Growing | Real-time |
| Users | Local + SSO | ~50-200 | SSO auto-provision |
| Deadlines | Admin input | ~100-500/year | Real-time |
| Assignments | Admin input | ~500-2000 | Real-time |
| Audit records | Auto-generated | Growing | Real-time |
| Notifications | Auto-generated | Growing | Real-time |

### 7.2 Data Retention

| Data Type | Retention Period | Justification |
|-----------|-----------------|---------------|
| Upload files | 7 years | Financial record keeping |
| Audit trail | 7 years | Regulatory compliance |
| Notifications | 90 days (auto-clear) | Operational relevance |
| Sessions | 120 minutes | Security policy |
| CAPS verification results | Permanent (with upload) | Audit evidence |

### 7.3 Data Classification

| Classification | Examples | Handling |
|----------------|----------|----------|
| **Confidential** | SA ID numbers, passwords, API secrets | Encrypted at rest, masked in logs |
| **Internal** | Upload files, company data, employee numbers | Access-controlled, audit-logged |
| **Public** | Municipality names, deadline dates | No special handling |

---

## 8. User Stories & Acceptance Criteria

### US-01: Upload Deduction Files

**As a** clerk, **I want to** upload deduction files for my assigned company, **so that** the submission is tracked and verified.

**Acceptance Criteria:**
1. I can select my assigned municipality and company from dropdowns
2. I can upload one or more email files (.eml or .msg)
3. I can optionally upload a workings spreadsheet and systems import file
4. If I've uploaded for this company in the last 30 days, I must provide a reason
5. After upload, CAPS verification runs automatically
6. I receive a confirmation with the unique reference code
7. The upload appears in my history with the correct status

### US-02: Verify Upload Against CAPS

**As a** manager, **I want to** see verification results immediately after upload, **so that** I can identify data discrepancies before processing.

**Acceptance Criteria:**
1. After upload, the system parses the spreadsheet and extracts member IDs, policy codes, premiums, and company names
2. Each row is verified against the correct deduction company in CAPS (resolved from the Company Name column)
3. Members are checked using the `?idNumber=` parameter (exact match)
4. Policies are fetched using `?organizationId=` and matched by policy code
5. Premium mismatches flagged when difference > R0.01
6. Results show: verification score, found/missing counts per category, premium differences
7. Results persist and are viewable from History without re-running

### US-03: Manage Deadlines

**As an** admin, **I want to** create deadlines and assign users to companies, **so that** submissions are completed on time.

**Acceptance Criteria:**
1. I can create a deadline for a municipality with a target date
2. I can assign multiple users to specific companies for that deadline
3. Assigned users receive notifications
4. The dashboard shows overdue deadlines in red
5. The calendar view shows all deadlines with colour coding

### US-04: SSO Between CAPS and Tracker

**As a** user, **I want to** log into either CAPS or Tracker and be automatically logged into the other, **so that** I don't need separate credentials.

**Acceptance Criteria:**
1. Logging into CAPS auto-logs me into Tracker within 5 seconds
2. Logging out of either system logs me out of both within 5 seconds
3. If I only have CAPS access (no Tracker roles), SSO is blocked with a message
4. If the SSO microservice is down, both systems work independently
5. Session timeout on either side syncs the logout

### US-05: Sync Reference Data from CAPS

**As an** admin, **I want to** pull the latest municipalities and companies from CAPS, **so that** the system has accurate reference data.

**Acceptance Criteria:**
1. I can click "Sync from CAPS" on the dashboard
2. The sync fetches municipalities and companies from the CAPS API
3. New records are created, existing records are updated, obsolete records remain (soft exclusion via global scope)
4. The dashboard shows the count and last sync timestamp
5. If no data exists, a prominent warning prompts me to sync

---

## 9. Constraints & Assumptions

### 9.1 Constraints

| ID | Constraint | Impact |
|----|-----------|--------|
| C-01 | CAPS API does not support filtering members by company | Must use `?idNumber=` for per-member lookup |
| C-02 | CAPS policy API requires `?organizationId=` (not `?companyId=`) | Must map company casey_id to the correct parameter |
| C-03 | CAPS `?search=` parameter does not filter (returns full dataset) | Cannot use search-based member lookup |
| C-04 | Maximum PHP memory 128 MB | Cannot fetch all 76K members at once |
| C-05 | SSO microservice is in-memory (no persistence) | Session data lost on microservice restart |
| C-06 | File storage is local filesystem | Must configure cloud storage for production scale |

### 9.2 Assumptions

| ID | Assumption | Risk if Invalid |
|----|-----------|-----------------|
| A-01 | CAPS API is available during business hours | Verification and sync fail; manual retry needed |
| A-02 | All deduction companies exist in CAPS with unique names | Company name resolution fails; falls back to upload parent company |
| A-03 | SA ID numbers are the primary member identifier in both systems | Member matching fails if CAPS uses a different primary key |
| A-04 | Users have modern browsers (Chrome/Edge/Firefox) | UI may break on older browsers |
| A-05 | The CAPS JWT shared secret is consistent across environments | SSO fails if secrets mismatch |

---

## 10. Glossary

| Term | Definition |
|------|-----------|
| **CAPS** | Casey Application Platform System - the upstream enterprise platform |
| **Casey ID** | Unique identifier assigned by CAPS to each entity (municipality, company, member) |
| **Deduction Company** | A third-party company (insurer, lender) that receives payroll deductions |
| **Municipality** | A South African local government employer organisation |
| **Member** | An employee of a municipality who has payroll deductions |
| **Policy** | A deduction arrangement between a member and a deduction company |
| **Premium** | The monetary amount deducted per pay period for a policy |
| **SSO** | Single Sign-On - shared authentication between CAPS and Tracker |
| **Workings File** | Spreadsheet containing the working calculations for deductions |
| **Systems Import File** | The file formatted for import into the deduction processing system |
| **SA ID** | South African national identity number (13 digits) |
| **POPIA** | Protection of Personal Information Act (South African data protection law) |
| **Tenant** | An isolated organisational unit within the multi-tenant architecture |
| **Global Scope** | A Laravel query filter that automatically excludes non-CAPS-synced records |
