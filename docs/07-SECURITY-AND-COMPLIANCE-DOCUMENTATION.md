# Security and Compliance Documentation

## Submission Tracker - Casey & Associates

**Document Version:** 1.0
**Date:** April 2026
**Classification:** Confidential - Restricted Distribution
**Author:** Security Engineering
**Review Cycle:** Quarterly (or after any security incident)
**Applicable Regulation:** Protection of Personal Information Act, 2013 (POPIA)

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Threat Model](#2-threat-model)
3. [Authentication Mechanisms](#3-authentication-mechanisms)
4. [Single Sign-On Security](#4-single-sign-on-security)
5. [Session Security](#5-session-security)
6. [Authorisation and Access Control (RBAC)](#6-authorisation-and-access-control-rbac)
7. [Data Classification Matrix](#7-data-classification-matrix)
8. [Access Control Matrix](#8-access-control-matrix)
9. [Encryption Standards](#9-encryption-standards)
10. [API and Webhook Security](#10-api-and-webhook-security)
11. [CSRF Protection](#11-csrf-protection)
12. [Multi-Tenant Isolation](#12-multi-tenant-isolation)
13. [Audit Trail and Logging](#13-audit-trail-and-logging)
14. [Data Protection (POPIA Compliance)](#14-data-protection-popia-compliance)
15. [Vulnerability Mitigations](#15-vulnerability-mitigations)
16. [Network Security](#16-network-security)
17. [Incident Response Plan](#17-incident-response-plan)
18. [POPIA Compliance Checklist](#18-popia-compliance-checklist)
19. [Penetration Testing Recommendations](#19-penetration-testing-recommendations)
20. [Security Configuration Reference](#20-security-configuration-reference)
21. [Appendices](#21-appendices)

---

## 1. Executive Summary

The Submission Tracker is an enterprise web application that manages the submission lifecycle for municipal payroll deduction files on behalf of Casey & Associates. The system processes personally identifiable information (PII) including South African Identity Numbers (13-digit SA ID numbers), financial data (premium amounts, payment batch details), and employee records. All data handling falls within the scope of the Protection of Personal Information Act (POPIA) and must satisfy financial record-keeping requirements with a minimum 7-year audit trail.

This document describes every security control implemented in the Submission Tracker, the threat model that justifies those controls, the compliance posture relative to POPIA, and the procedures for responding to security incidents. It is intended for security auditors, system administrators, compliance officers, and the development team.

### 1.1 Security Architecture Overview

The Submission Tracker implements a defence-in-depth strategy across the following layers:

| Layer | Control | Implementation |
|-------|---------|----------------|
| **Network** | TLS termination, CORS, rate limiting | HTTPS required, SESSION_SECURE_COOKIE, throttle middleware |
| **Authentication** | Multi-method auth (local, SSO, API key) | Bcrypt 12 rounds, HS256 JWT, SHA256 API key hashing |
| **Authorisation** | Role-Based Access Control (RBAC) | Spatie/Laravel-Permission, 4 roles, 23+ permissions |
| **Application** | CSRF, input validation, parameterised queries | VerifyCsrfToken middleware, Eloquent ORM, Laravel validation |
| **Data** | Encryption at rest, field-level masking | Bcrypt passwords, encrypted integration credentials, sanitised audit logs |
| **Tenant** | Logical data isolation | BelongsToTenant trait, global tenant scope, tenant-scoped API keys |
| **Audit** | Comprehensive event logging | RecordsAuditTrail trait, AuditLogger, AuditTrailMiddleware |
| **Integration** | HMAC-SHA256 webhooks, SSO session sync | Timing-safe signature verification, idempotency tracking |

### 1.2 Key Security Principles

1. **Least Privilege:** Users access only uploads for their assigned company/municipality combinations. API keys carry explicit scope lists.
2. **Defence in Depth:** Multiple overlapping controls at every layer -- authentication, authorisation, tenant isolation, and audit logging all operate independently.
3. **Fail Secure:** SSO failures fall back to local authentication. Microservice unavailability results in independent operation, not open access.
4. **Audit Everything:** All authentication events, data mutations, and state-changing HTTP requests are permanently logged with full provenance.
5. **Minimise Attack Surface:** File storage is on a private disk (not web-accessible). Passwords, tokens, and secrets are never logged or exposed after initial creation.

---

## 2. Threat Model

### 2.1 System Boundaries

```
                    +------------------+
                    |   End Users      |
                    | (Browser/HTTPS)  |
                    +--------+---------+
                             |
                    +--------+---------+
                    |   Load Balancer  |
                    |   (TLS Termination)|
                    +--------+---------+
                             |
              +--------------+--------------+
              |                             |
    +---------+----------+     +------------+-----------+
    | Submission Tracker  |     | SSO Session Microservice|
    | (Laravel/Inertia)   |<--->| (Node.js, in-memory)   |
    +---------+----------+     +------------------------+
              |
    +---------+----------+
    |   CAPS Platform     |
    | (External API)      |
    +---------------------+
```

### 2.2 Trust Boundaries

| Boundary | Description | Controls |
|----------|-------------|----------|
| **TB-1: Browser to Tracker** | All end-user traffic | TLS, CSRF tokens, session cookies (HTTP-only, SameSite=Lax), input validation |
| **TB-2: Tracker to CAPS API** | Outbound API calls for member/policy verification, reference data sync | Bearer token auth (user's SSO JWT from session), TLS |
| **TB-3: CAPS to Tracker Webhooks** | Inbound webhooks from CAPS for payment batch status updates | HMAC-SHA256 signature (X-Caps-Signature), idempotency, IP logging |
| **TB-4: Tracker to SSO Microservice** | Session registration, heartbeat, removal | X-SSO-Key header authentication, timeout limits (3s request, 2s connect) |
| **TB-5: Tenant Isolation** | Logical separation of data between organisational tenants | BelongsToTenant trait, global scope filtering, tenant-scoped API keys |

### 2.3 Threat Catalogue (STRIDE)

| ID | Category | Threat | Likelihood | Impact | Mitigations | Residual Risk |
|----|----------|--------|------------|--------|-------------|---------------|
| T-01 | **Spoofing** | Attacker forges a CAPS JWT to gain SSO access | Medium | Critical | HS256 signature verification with shared secret (CaseyJwtService), claims validation (sub, exp, nbf, iat), 30-second leeway, algorithm pinned to HS256 only | Low -- requires compromise of base64 shared secret |
| T-02 | **Spoofing** | Credential stuffing against local login | Medium | High | Bcrypt 12-round password hashing, audit logging of failed logins (AuditLogger::authEvent), rate limiting via throttle middleware | Low -- failed attempts logged and rate-limited |
| T-03 | **Spoofing** | Stolen API key used for unauthorised API access | Low | High | SHA256 hash stored (plain text returned once on creation), scope-based access restrictions, soft revocation (revoked_at), last_used_at tracking | Low -- key format {prefix}.{secret} enables identification, revocation available |
| T-04 | **Spoofing** | Forged CAPS webhook payload | Medium | Medium | HMAC-SHA256 signature verification (X-Caps-Signature header), timing-safe comparison via hash_equals(), missing/invalid signature returns 401 | Low |
| T-05 | **Tampering** | Modification of audit trail records | Low | Critical | Audit records are append-only (no update/delete UI), database-level constraints, 7-year retention policy, BelongsToTenant scoping | Very Low |
| T-06 | **Tampering** | Request body manipulation on state-changing endpoints | Medium | Medium | CSRF token validation on all web routes, Inertia.js auto-includes CSRF, AuditTrailMiddleware logs POST/PUT/PATCH/DELETE with sanitised request data | Low |
| T-07 | **Repudiation** | User denies performing an action | Medium | High | RecordsAuditTrail trait auto-logs create/update/delete on all core models with old_values/new_values, AuditLogger captures user_id, IP address, user_agent, URL, session_id | Very Low -- comprehensive audit trail makes repudiation infeasible |
| T-08 | **Information Disclosure** | SA ID numbers leaked via logs or API responses | Medium | Critical | AuditLogger sanitises sensitive fields (password, password_confirmation, remember_token, external_password_hash), User model hides password/external_password_hash/remember_token in serialisation, file storage on private disk | Low |
| T-09 | **Information Disclosure** | Session hijacking via cookie theft | Low | Critical | HTTP-only cookies, SameSite=Lax, SESSION_SECURE_COOKIE=true in production, session regeneration on login/logout, database-backed sessions | Low |
| T-10 | **Denial of Service** | Webhook replay flooding | Low | Medium | Idempotency via event_id tracking in caps_webhook_events table, already-processed events return 200 without re-processing | Very Low |
| T-11 | **Denial of Service** | SSO redirect loop trapping users | Medium | Low | casey_sso_skip cookie prevents redirect loops on failed SSO, ?sso=skip query parameter bypass, configurable skip duration | Very Low |
| T-12 | **Elevation of Privilege** | User accessing data from another tenant | Low | Critical | BelongsToTenant trait applies automatic tenant_id on record creation, forTenant() scope on all queries, API keys scoped to tenant_id, TenantResolverService resolves from X-Tenant header > hostname > user.tenant_id > default | Low |
| T-13 | **Elevation of Privilege** | User accessing uploads for unassigned companies/municipalities | Medium | High | Assignment-based access checks (isAssignedToCompanyInMunicipality), admin override only for admin/super-admin roles, permission middleware on routes, controller-level $this->authorize() | Low |
| T-14 | **Spoofing** | SSO session persistence after remote logout | Medium | Medium | Bidirectional session sync (SsoSessionSync middleware), 15-second polling interval, 404 = definitive logout detection, session invalidation and token regeneration | Low |

### 2.4 Asset Inventory

| Asset | Classification | Storage | Protection |
|-------|---------------|---------|------------|
| SA ID Numbers (13-digit) | Confidential | Database (uploads table, verification JSON) | Access-controlled via RBAC, not logged in audit trail values, encrypted in transit (TLS) |
| User Passwords | Confidential | Database (users.password) | Bcrypt 12 rounds, never logged, hidden from serialisation |
| External Password Hashes | Confidential | Database (users.external_password_hash) | Bcrypt hash, never logged, hidden from serialisation |
| API Key Secrets | Confidential | Database (api_keys.key_hash) | SHA256 hash only; plain text returned once on creation, never stored |
| CAPS JWT Shared Secret | Confidential | Environment variable (CASEY_JWT_SHARED_SECRET) | Base64-encoded, never committed to source control, used only in CaseyJwtService |
| CAPS Webhook Secret | Confidential | Environment variable (CAPS_WEBHOOK_SECRET) | Used for HMAC-SHA256 computation, never logged |
| SSO API Secret | Confidential | Environment variable (services.casey.sso_api_secret) | Transmitted via X-SSO-Key header, TLS in transit |
| Integration Credentials | Confidential | Database (credentials_encrypted column) | Encrypted at rest |
| Session Data | Internal | Database (sessions table) | HTTP-only cookies, server-side storage, 120-minute lifetime |
| Upload Files | Internal | Private disk (storage/app/private) | Not web-accessible, downloaded via authenticated controller |
| Audit Records | Internal | Database (audits table) | Append-only, 7-year retention, tenant-scoped |
| Municipality/Company Names | Public | Database | No special protection required |
| Deadline Dates | Public | Database | No special protection required |

---

## 3. Authentication Mechanisms

The Submission Tracker supports four distinct authentication methods to accommodate different access patterns. Each method is independently validated and all authentication events are audit-logged.

### 3.1 Local Authentication (Primary)

| Property | Value |
|----------|-------|
| **Identifier** | `employee_number` (unique, string) |
| **Secret** | `password` (plaintext submitted over TLS) |
| **Hashing** | Bcrypt with cost factor 12 (Laravel `hashed` cast on `password` column) |
| **Session** | Database-backed session, cookie-based session ID |
| **Remember Me** | Optional, extends session via remember_token |

**Flow:**
1. User submits `employee_number` + `password` via POST /login
2. `Auth::attempt()` loads user by `employee_number`, verifies password against bcrypt hash
3. On success: session is regenerated (`$request->session()->regenerate()`), `last_login_at` and `last_login_ip` updated
4. Session registered with SSO microservice (`SsoSessionService::registerSession`)
5. `AuditLogger::authEvent('logged_in')` records the event with employee_number, session_id
6. On failure: `AuditLogger::authEvent('failed_login')` records attempt with employee_number

**Implementation:** `App\Http\Controllers\Auth\AuthenticatedSessionController::store()`

### 3.2 External Password Hash Fallback

| Property | Value |
|----------|-------|
| **Scope** | CAPS-synced users only |
| **Column** | `users.external_password_hash` (nullable string) |
| **Hashing** | Bcrypt (seeded from CAPS user API during sync) |
| **Priority** | Attempted only after primary local auth fails |

**Flow:**
1. Primary `Auth::attempt()` fails
2. User loaded by `employee_number`
3. If `external_password_hash` is non-empty, `Hash::check()` verifies against it
4. On match: `Auth::login()` creates session
5. All subsequent flow identical to primary auth

**Security Rationale:** Enables login for users provisioned from CAPS whose local password has not been set. The external hash is a bcrypt digest -- the plaintext password is never stored or transmitted outside the login request.

**Implementation:** `App\Http\Controllers\Auth\AuthenticatedSessionController::store()` (lines 112-119)

### 3.3 CAPS Single Sign-On (SSO)

| Property | Value |
|----------|-------|
| **Token Format** | JSON Web Token (JWT), 3-part dot-separated |
| **Algorithm** | HS256 (HMAC-SHA256), pinned -- any other algorithm is rejected |
| **Shared Secret** | `CASEY_JWT_SHARED_SECRET` environment variable, base64-encoded |
| **Required Claims** | `sub` (employee number, non-empty), `exp`, `nbf`, `iat` |
| **Clock Leeway** | 30 seconds (configurable via `services.casey.jwt_leeway_seconds`) |
| **Auto-Provision** | Enabled by default (`CASEY_SSO_AUTO_PROVISION=true`); creates user from JWT claims with configurable default role |
| **Transport** | GET /auth/casey-sso?token=<jwt> or POST /auth/casey-sso with token in body or Bearer header |

**Verification Steps (CaseyJwtService::verify):**
1. Validate `CASEY_JWT_SHARED_SECRET` is configured and non-empty
2. Split token into exactly 3 parts (header, payload, signature) -- reject if malformed
3. Decode header, verify `alg` claim is `HS256` -- reject all others
4. Base64-decode the shared secret (strict mode, reject invalid base64)
5. Compute `hash_hmac('sha256', header.payload, decoded_secret, binary)` 
6. Compare expected vs actual signature using `hash_equals()` (timing-safe)
7. Decode payload, validate JSON structure
8. Check `exp`: reject if `(now - leeway) >= exp`
9. Check `nbf`: reject if `(now + leeway) < nbf`
10. Check `iat`: reject if `(now + leeway) < iat` (issued-at in the future)
11. Check `sub`: reject if missing or empty string

**Post-Verification (CaseySsoController::login):**
1. Extract `sub` claim as employee_number
2. Look up user by employee_number; auto-provision if not found and enabled
3. Refresh user display fields (name, email) from JWT claims without overwriting non-empty local values with empty claims
4. Block login if user is inactive (`is_active = false`)
5. Block login if user has no Tracker roles (prevents CAPS-only users from accessing Tracker)
6. Call `Auth::login($user, remember: true)` and regenerate session
7. Store CAPS JWT in session (`session.caps_jwt`) for subsequent CAPS API calls as the authenticated user
8. Register session with SSO microservice
9. Auto-sync CAPS reference data if municipalities/companies tables are empty
10. Audit log the SSO login event

**Implementation:** `App\Services\CaseyJwtService`, `App\Http\Controllers\Auth\CaseySsoController`

### 3.4 API Key Authentication

| Property | Value |
|----------|-------|
| **Header** | `X-API-Key` |
| **Format** | `{prefix}.{secret}` (dot-separated) |
| **Storage** | SHA256 hash of `{secret}` portion stored in `api_keys.key_hash` |
| **Scopes** | JSON array in `api_keys.scopes_json`; wildcard `*` grants all scopes |
| **Revocation** | Soft revocation via `revoked_at` timestamp (nullable) |
| **Tenant Binding** | Each API key is bound to a `tenant_id` |
| **Usage Tracking** | `last_used_at` updated on every successful authentication |

**Verification Steps (AuthenticateApiKey middleware):**
1. Extract `X-API-Key` header; reject if missing or does not contain a dot
2. Split on first dot: discard prefix, extract secret
3. Compute `hash('sha256', secret)`
4. Look up `ApiKey` by `key_hash`; reject if not found
5. Check `isActive()` -- reject if `revoked_at` is not null
6. Check required scope: reject with 403 if key lacks the scope and does not have wildcard `*`
7. Update `last_used_at` timestamp
8. Set tenant context from `api_key.tenant_id`
9. Attach API key to request attributes for downstream access

**Implementation:** `App\Http\Middleware\AuthenticateApiKey`, `App\Models\ApiKey`

### 3.5 Authentication Comparison Matrix

| Feature | Local Auth | External Hash | CAPS SSO | API Key |
|---------|-----------|---------------|----------|---------|
| User-facing | Yes | Yes (transparent) | Yes | No (machine-to-machine) |
| Credential type | Password | Password | JWT | API key string |
| Hash algorithm | Bcrypt 12 | Bcrypt | HMAC-SHA256 | SHA256 |
| Session created | Yes | Yes | Yes | No (stateless) |
| MFA support | Not yet | Not yet | Inherited from CAPS | N/A |
| Rate limited | Yes (throttle) | Yes (throttle) | N/A (CAPS-side) | Yes (throttle) |
| Audit logged | Yes | Yes | Yes | Yes (via middleware) |
| Tenant-aware | Via user | Via user | Via user | Via API key |

---

## 4. Single Sign-On Security

### 4.1 Architecture

The CAPS SSO integration uses a bidirectional session synchronisation model mediated by an external Node.js microservice. The design eliminates the need for iframes or popup windows for the primary login flow, while supporting silent logout via a minimal HTML response for iframe-based CAPS logout sync.

```
+----------+      JWT       +-------------------+    X-SSO-Key     +-----------------------+
|  CAPS UI |  ----------->  | Submission Tracker | <-------------> | SSO Session           |
|          |  <-----------  |                   |                  | Microservice          |
+----------+  redirect back +-------------------+                  | (Node.js, in-memory)  |
                                                                   +-----------------------+
```

### 4.2 Session Lifecycle

| Phase | CAPS Action | Tracker Action | Microservice Role |
|-------|-------------|----------------|-------------------|
| **Login from CAPS** | Redirects user to `/auth/casey-sso?token=<jwt>` | Verifies JWT, creates session, calls `registerSession()` | Stores session record (employeeNumber, token, source=tracker) |
| **Login from Tracker** | N/A | Creates session, calls `registerSession()` with local session token | Stores session record; CAPS detects via polling |
| **Active Session** | N/A | SsoSessionSync middleware checks every 15s via `checkSession()` | Returns session data or 404 |
| **Logout from CAPS** | Removes session from microservice | Detects 404 on next poll (within 15s), invalidates session | Returns 404 for removed session |
| **Logout from Tracker** | N/A | Calls `removeSession()`, invalidates local session | Removes session record; CAPS detects via polling |
| **Silent Logout** | Loads `/auth/casey-sso-logout` in hidden iframe | `CaseySsoController::silentLogout()` destroys session, returns minimal HTML | N/A (already removed by CAPS) |

### 4.3 Graceful Degradation

| Failure Scenario | System Behaviour |
|-----------------|-----------------|
| SSO microservice unreachable (network failure, restart) | `SsoSessionService` catches `\Throwable`, returns `false`/`null`. `SsoSessionSync` receives `null` from `checkSession()` and takes no action. Both CAPS and Tracker operate independently. |
| SSO microservice data loss (in-memory restart) | All sessions lost. Next poll returns 404. Users are logged out within 15 seconds. Re-login required (acceptable trade-off for simplicity). |
| CAPS JWT shared secret mismatch | `CaseyJwtService::verify()` throws `RuntimeException('JWT signature mismatch')`. SSO login fails. User sees error message on local login form. `casey_sso_skip` cookie set to prevent redirect loop. |
| CAPS API unreachable | SSO login still works (JWT verified locally). CAPS verification and reference data sync deferred. Upload workflow continues without verification. |

### 4.4 Anti-Loop Protection

SSO redirect loops can occur when a CAPS-authenticated user is denied Tracker access (no roles, inactive account, or JWT verification failure). The system prevents loops through:

1. **`casey_sso_skip` Cookie:** Set by `CaseySsoController::fail()` with a configurable duration (`CASEY_SSO_SKIP_SECONDS`, default 60). While present, `AuthenticatedSessionController::create()` skips the SSO auto-redirect and renders the local login form.
2. **`?sso=skip` Query Parameter:** Appended to the login redirect URL. `isCaseySsoBlocked()` checks both the cookie and the query parameter.
3. **Cookie Clearance:** On successful local login, `casey_sso_skip` is explicitly forgotten (`Cookie::queue(Cookie::forget('casey_sso_skip'))`), restoring normal SSO behaviour.

### 4.5 Access Control During SSO

| Check | Enforcement Point | Result on Failure |
|-------|-------------------|-------------------|
| User exists in Tracker | `CaseySsoController::login()` | Auto-provision if `CASEY_SSO_AUTO_PROVISION=true`; otherwise redirect to login with error |
| User is active | `CaseySsoController::login()` | Redirect to login with "account is deactivated" message |
| User has Tracker roles | `CaseySsoController::login()` | Redirect to login with "do not have access" message |
| User exists but inactive (auto-login check) | `SsoSessionSync::detectRemoteLogin()` | Skip auto-login silently |
| User exists but no roles (auto-login check) | `SsoSessionSync::detectRemoteLogin()` | Skip auto-login silently |

### 4.6 Token Handling in Session

After successful SSO login, the CAPS JWT is stored in the server-side session (`session.caps_jwt`). This token is used for all subsequent CAPS API calls during the session, ensuring requests authenticate as the logged-in user rather than a shared service account. The session is server-side (database driver), so the JWT is never exposed to the browser.

---

## 5. Session Security

### 5.1 Configuration

| Parameter | Value | Rationale |
|-----------|-------|-----------|
| `SESSION_DRIVER` | `database` | Server-side storage; session data never leaves the server. Survives application restarts. |
| `SESSION_LIFETIME` | `120` (minutes) | 2-hour inactivity timeout balances security with usability for all-day clerical workflows |
| `SESSION_SECURE_COOKIE` | `true` (production) | Cookie only sent over HTTPS; prevents interception on insecure networks |
| `SESSION_HTTP_ONLY` | `true` (default) | Cookie inaccessible to JavaScript; mitigates XSS-based session theft |
| `SESSION_SAME_SITE` | `lax` | Prevents cross-site request forgery via cookie-bearing cross-origin requests; allows top-level navigations (safe for SSO redirects) |
| `SESSION_ENCRYPT` | `true` (production) | Session cookie value encrypted; prevents tampering with session ID |

### 5.2 Session Lifecycle Events

| Event | Action | Implementation |
|-------|--------|----------------|
| **Login (local)** | `$request->session()->regenerate()` | Prevents session fixation by issuing a new session ID |
| **Login (SSO)** | `$request->session()->regenerate()` | Same session fixation prevention |
| **Logout (local)** | `$request->session()->invalidate()` then `$request->session()->regenerateToken()` | Destroys session data and rotates CSRF token |
| **Logout (SSO silent)** | `$request->session()->invalidate()` then `$request->session()->regenerateToken()` | Same as local logout |
| **Logout (SSO remote)** | `$request->session()->invalidate()` then `$request->session()->regenerateToken()` | Triggered by SsoSessionSync when 404 detected |
| **Logout (SSO poll)** | `$request->session()->invalidate()` then `$request->session()->regenerateToken()` | Triggered by client-side polling JS detecting SSO removal |

### 5.3 CSRF Token Rotation

CSRF tokens are rotated whenever the session is regenerated or invalidated. This occurs on every authentication state change (login and logout), ensuring that a CSRF token captured from a previous session cannot be replayed.

---

## 6. Authorisation and Access Control (RBAC)

### 6.1 Framework

The Submission Tracker uses **Spatie/Laravel-Permission** (guard: `web`) for role and permission management. The `User` model includes the `HasRoles` trait, enabling role assignment, permission checking, and role-based query scoping.

### 6.2 Role Hierarchy

| Role | Description | Scope | Implicit Permissions |
|------|-------------|-------|---------------------|
| **super-admin** | System owner with unrestricted access | All tenants, all data | All permissions (gate bypass via Spatie's Super Admin feature) |
| **admin** | Organisational administrator | Own tenant | Full CRUD on users, roles, companies, municipalities, deadlines, uploads, reports, audits |
| **manager** | Operational supervisor | Assigned municipalities/companies | View dashboard, manage deadlines, manage uploads for assigned entities, view reports |
| **user** | Data entry clerk | Assigned municipalities/companies only | Upload files, view own upload history, view own notifications, view assigned deadlines |

### 6.3 Permission Catalogue

The system defines 23+ granular permissions across functional domains:

| Domain | Permissions | Description |
|--------|-------------|-------------|
| **Dashboard** | `view dashboard` | Access to the main dashboard view |
| **Uploads** | `view uploads`, `create uploads`, `edit uploads`, `delete uploads` | Full CRUD on file uploads; create/edit/delete restricted by assignment |
| **Deadlines** | `view deadlines`, `create deadlines`, `edit deadlines`, `delete deadlines` | Deadline management; creation and modification restricted to admin/manager |
| **Submissions** | `view submissions`, `manage submissions` | View and manage the submission lifecycle |
| **Companies** | `view companies`, `manage companies` | View and administer deduction company records |
| **Municipalities** | `view municipalities`, `manage municipalities` | View and administer municipality records |
| **Notifications** | `view notifications`, `manage notifications` | View own and (admin) manage all user notifications |
| **Users** | `view users`, `create users`, `edit users`, `delete users` | User account administration (admin only) |
| **Roles** | `view roles`, `manage roles` | Role definition and assignment (admin only) |
| **Permissions** | `view permissions`, `manage permissions` | Permission assignment (super-admin only) |
| **Reports** | `view reports`, `export reports` | Reporting and data export |
| **Audits** | `view audits` | Access to the audit trail viewer (/admin/audits) |

### 6.4 Enforcement Layers

| Layer | Mechanism | Example |
|-------|-----------|---------|
| **Route** | `middleware('permission:view uploads')` | Applied in `routes/web.php` to route groups |
| **Controller** | `$this->authorize('create uploads')` | Called at the start of controller methods |
| **Frontend** | Permission-based menu rendering | Inertia.js shared data includes user permissions; Vue components conditionally render menu items |
| **Assignment** | `isAssignedToCompanyInMunicipality()` | Upload creation checks if the user has an active assignment for the target company/municipality |
| **Model** | `canAccessUpload()` | User model method checks admin role or assignment to the upload's company/municipality |

### 6.5 Assignment-Based Access

Beyond RBAC permissions, the Submission Tracker enforces **assignment-based access control**. Users are assigned to specific company/municipality combinations via the `user_assignments` table. This creates a fine-grained access matrix:

- **Upload Creation:** Non-admin users can only upload files for their assigned company/municipality pairs
- **Upload Viewing:** Non-admin users see only uploads for their assigned entities
- **Deadline Visibility:** Users see deadlines only for their assigned municipalities
- **Admin Override:** Users with `admin` or `super-admin` roles bypass assignment checks

---

## 7. Data Classification Matrix

### 7.1 Classification Levels

| Level | Definition | Examples | Handling Requirements |
|-------|-----------|----------|----------------------|
| **Confidential** | Data whose disclosure would cause significant harm to individuals or the organisation. Includes all PII regulated by POPIA and all authentication credentials. | SA ID numbers (13-digit), passwords, password hashes, API key secrets, JWT shared secrets, webhook secrets, SSO API secrets, integration credentials | Encrypted at rest. Never logged. Masked in API responses. Access restricted to minimum necessary role. Transmission over TLS only. 7-year retention for financial records. |
| **Internal** | Business data requiring access control but not encryption at rest. Disclosure would cause moderate harm. | Upload files (emails, spreadsheets, import files), employee numbers, company financial data, premium amounts, CAPS verification results, user profile data, audit records, session data | Access-controlled via RBAC and tenant isolation. Audit-logged on mutation. Private file storage (not web-accessible). Transmission over TLS. |
| **Public** | Data that can be disclosed without harm. | Municipality names, deadline dates, company names (as published entities), application version | No special handling required. Available to all authenticated users within tenant. |

### 7.2 Data Flow Classification

| Data Flow | Classification | Transport Security | At-Rest Protection |
|-----------|---------------|-------------------|-------------------|
| User login credentials (browser to server) | Confidential | TLS (HTTPS required) | Bcrypt 12 rounds (password), never stored in plaintext |
| CAPS JWT (CAPS to Tracker) | Confidential | TLS, URL-encoded or POST body | Stored in server-side session (database driver) |
| API key (client to server) | Confidential | TLS, X-API-Key header | SHA256 hash in database |
| CAPS webhook payload | Internal | TLS, HMAC-SHA256 signed | Stored in caps_webhook_events table |
| Upload files (browser to server) | Internal | TLS, multipart/form-data | Private disk storage, not web-accessible |
| SA ID numbers (in uploaded spreadsheets) | Confidential | TLS | Database storage, access-controlled via RBAC |
| Audit records | Internal | N/A (server-side only) | Database, tenant-scoped, 7-year retention |
| SSO session data | Internal | TLS, X-SSO-Key header | In-memory (microservice), database (Tracker) |

### 7.3 Sensitive Field Inventory

| Table | Column | Classification | Protection |
|-------|--------|---------------|------------|
| `users` | `password` | Confidential | Bcrypt hash, `hashed` cast, hidden from serialisation |
| `users` | `external_password_hash` | Confidential | Bcrypt hash, hidden from serialisation, excluded from audit logs |
| `users` | `remember_token` | Confidential | Hidden from serialisation, excluded from audit logs |
| `api_keys` | `key_hash` | Confidential | SHA256 hash only; original secret never stored |
| `integrations` (if applicable) | `credentials_encrypted` | Confidential | Encrypted at rest (Laravel encryption) |
| `uploads` | `caps_verification` (JSON) | Internal (may contain SA IDs) | Access-controlled via RBAC and assignment |
| `audits` | `old_values`, `new_values` | Internal | Sanitised: password, password_confirmation, remember_token, external_password_hash stripped |

---

## 8. Access Control Matrix

### 8.1 Role-Permission Matrix

| Permission | super-admin | admin | manager | user |
|-----------|:-----------:|:-----:|:-------:|:----:|
| `view dashboard` | Y | Y | Y | Y |
| `view uploads` | Y | Y | Y | Y (own assignments) |
| `create uploads` | Y | Y | Y | Y (own assignments) |
| `edit uploads` | Y | Y | Y | -- |
| `delete uploads` | Y | Y | -- | -- |
| `view deadlines` | Y | Y | Y | Y (own assignments) |
| `create deadlines` | Y | Y | Y | -- |
| `edit deadlines` | Y | Y | Y | -- |
| `delete deadlines` | Y | Y | -- | -- |
| `view submissions` | Y | Y | Y | Y (own) |
| `manage submissions` | Y | Y | Y | -- |
| `view companies` | Y | Y | Y | Y |
| `manage companies` | Y | Y | -- | -- |
| `view municipalities` | Y | Y | Y | Y |
| `manage municipalities` | Y | Y | -- | -- |
| `view notifications` | Y | Y | Y | Y (own) |
| `manage notifications` | Y | Y | -- | -- |
| `view users` | Y | Y | -- | -- |
| `create users` | Y | Y | -- | -- |
| `edit users` | Y | Y | -- | -- |
| `delete users` | Y | Y | -- | -- |
| `view roles` | Y | Y | -- | -- |
| `manage roles` | Y | Y | -- | -- |
| `view permissions` | Y | -- | -- | -- |
| `manage permissions` | Y | -- | -- | -- |
| `view reports` | Y | Y | Y | -- |
| `export reports` | Y | Y | Y | -- |
| `view audits` | Y | Y | -- | -- |

### 8.2 API Key Scope Matrix

| Scope | Description | Typical Consumer |
|-------|-------------|-----------------|
| `*` (wildcard) | All API endpoints | Internal service accounts |
| `webhooks:receive` | Receive CAPS webhooks | CAPS platform |
| `data:read` | Read-only access to uploads, companies, municipalities | Reporting integrations |
| `data:write` | Create/update uploads | Automated submission tools |
| `sync:trigger` | Trigger CAPS reference data synchronisation | Scheduled jobs |

### 8.3 Tenant Access Rules

| Resource | Isolation Method | Cross-Tenant Access |
|----------|-----------------|-------------------|
| Users | `users.tenant_id` FK | super-admin only |
| Uploads | `uploads.tenant_id` FK + BelongsToTenant trait | Never |
| Companies | `companies.tenant_id` FK + BelongsToTenant trait | Never |
| Municipalities | `municipalities.tenant_id` FK + BelongsToTenant trait | Never |
| Deadlines | `municipality_deadlines.tenant_id` FK + BelongsToTenant trait | Never |
| Assignments | Derived from user and company/municipality tenant | Never |
| Audit Records | `audits.tenant_id` FK + BelongsToTenant trait | super-admin only |
| API Keys | `api_keys.tenant_id` FK | Never |

---

## 9. Encryption Standards

### 9.1 Encryption at Rest

| Data Type | Algorithm | Key Management | Implementation |
|-----------|-----------|---------------|----------------|
| User passwords | Bcrypt | Cost factor 12, per-password salt (built-in) | Laravel `hashed` cast on User model |
| External password hashes | Bcrypt | As above (synced from CAPS) | Stored in `external_password_hash` column |
| API key secrets | SHA256 (one-way hash) | N/A (hash, not encryption) | `hash('sha256', $secret)` in AuthenticateApiKey |
| Integration credentials | AES-256-CBC (Laravel encryption) | APP_KEY environment variable (base64-encoded) | `credentials_encrypted` column |
| Session data | AES-256-CBC (when SESSION_ENCRYPT=true) | APP_KEY environment variable | Laravel session encryption middleware |

### 9.2 Encryption in Transit

| Channel | Protocol | Minimum Version | Certificate Requirements |
|---------|----------|----------------|------------------------|
| Browser to Tracker | TLS | 1.2 (recommended: 1.3) | Valid CA-signed certificate, HSTS recommended |
| Tracker to CAPS API | TLS | 1.2 | CAPS API certificate validated by PHP CA bundle |
| Tracker to SSO Microservice | TLS (production) | 1.2 | Internal CA or self-signed with pinning acceptable |
| CAPS to Tracker Webhooks | TLS | 1.2 | Valid CA-signed certificate on Tracker endpoint |

### 9.3 Cryptographic Operations

| Operation | Algorithm | Library | Usage |
|-----------|-----------|---------|-------|
| Password hashing | Bcrypt (2y) | PHP `password_hash()` via Laravel | User authentication |
| JWT signature verification | HMAC-SHA256 | PHP `hash_hmac('sha256')` | CAPS SSO token validation |
| JWT signature comparison | Constant-time comparison | PHP `hash_equals()` | Prevents timing attacks on signature verification |
| Webhook signature verification | HMAC-SHA256 | PHP `hash_hmac('sha256')` | CAPS webhook payload integrity |
| Webhook signature comparison | Constant-time comparison | PHP `hash_equals()` | Prevents timing attacks on webhook verification |
| API key hashing | SHA256 | PHP `hash('sha256')` | API key lookup and verification |
| Application encryption | AES-256-CBC | Laravel Encrypter (OpenSSL) | Integration credentials, session encryption |
| CSRF token generation | Random bytes | Laravel `Str::random()` / `random_bytes()` | Cross-site request forgery prevention |

### 9.4 Key Rotation Procedures

| Key/Secret | Rotation Frequency | Rotation Procedure |
|-----------|-------------------|-------------------|
| APP_KEY | Annually or after compromise | Generate new key, re-encrypt all encrypted columns, update environment |
| CASEY_JWT_SHARED_SECRET | Annually or after compromise | Coordinate with CAPS team, update both systems simultaneously, no grace period (immediate switch) |
| CAPS_WEBHOOK_SECRET | Annually or after compromise | Coordinate with CAPS team, update both systems simultaneously |
| SSO API Secret | Annually or after compromise | Update environment variable and microservice configuration |
| API Keys | Per-key revocation | Revoke old key (set revoked_at), issue new key to consumer |
| User Passwords | Per organisational policy | Enforce via password expiry policy (if enabled) |

---

## 10. API and Webhook Security

### 10.1 API Key Authentication

See Section 3.4 for full details. Summary of security controls:

- **Key format:** `{prefix}.{secret}` -- prefix enables identification without revealing the secret
- **Storage:** Only SHA256 hash stored; plain text returned exactly once on creation
- **Scope enforcement:** Each key carries an explicit scope list (`scopes_json`); wildcard `*` grants all
- **Revocation:** Soft revocation via `revoked_at` timestamp; revoked keys immediately rejected
- **Tenant binding:** Key is bound to `tenant_id`; all requests made with the key operate within that tenant's scope
- **Usage tracking:** `last_used_at` updated on every successful authentication, enabling stale key detection

### 10.2 CAPS Webhook Security

| Control | Implementation | Reference |
|---------|----------------|-----------|
| **Signature Verification** | HMAC-SHA256 of raw request body using `CAPS_WEBHOOK_SECRET`. Expected signature compared to `X-Caps-Signature` header. | `CapsWebhookController::handle()` |
| **Timing-Safe Comparison** | `hash_equals($expected, $signature)` prevents timing attacks that could be used to incrementally guess the correct signature | `CapsWebhookController::handle()` |
| **CSRF Exemption** | Webhook endpoint excluded from `VerifyCsrfToken` middleware (uses HMAC instead) | Laravel CSRF exception list |
| **Idempotency** | Each event carries a unique `eventId`. Events are recorded in the `caps_webhook_events` table. Duplicate events (same `event_id`) return 200 without re-processing. | `CapsWebhookEvent` model |
| **Replay Protection** | Idempotency tracking prevents replayed events from causing duplicate state changes. Already-processed events acknowledged with `{ "ok": true, "message": "Event already processed" }` | `CapsWebhookController::handle()` |
| **Event ID Generation** | If `eventId` is missing from the payload, a deterministic ID is generated via `md5(eventType|batchId|reference|occurredAt)` to maintain idempotency | `CapsWebhookController::handle()` |
| **Error Logging** | Invalid signatures logged with IP address. Missing configuration logged as error. Unresolved uploads logged as warning. | Laravel Log facade |

**Accepted Event Types:**

| Event | CAPS Trigger | Tracker Action |
|-------|-------------|----------------|
| `payment_batch.imported` | CAPS imports a batch | Update upload `caps_status` to `imported` |
| `payment_batch.allocated` | CAPS allocates payments | Update upload `caps_status` to `allocated` |
| `payment_batch.failed` | CAPS batch fails | Update upload `caps_status` to `failed`, store error details |
| `payment_batch.exported` | CAPS exports batch | Update upload `caps_status` to `exported` |
| `refund.created` | Refund processed | Update upload `caps_status` to `refund_created` |
| `refund.allocated` | Refund allocated | Update upload `caps_status` to `refund_allocated` |

### 10.3 CAPS API Authentication (Outbound)

All outbound CAPS API calls authenticate using the logged-in user's CAPS JWT (stored in `session.caps_jwt` after SSO login), not a shared service account. This ensures:

1. **Principle of Least Privilege:** API calls carry only the permissions of the authenticated user
2. **Auditability:** CAPS can attribute API calls to specific users
3. **No Shared Credentials in Code:** The .env service account credentials are not used for runtime API calls

If no SSO JWT is available in the session (e.g., user logged in via local auth), the system falls back to service account authentication for essential operations (reference data sync).

### 10.4 SSO Microservice Authentication

| Property | Value |
|----------|-------|
| **Auth Header** | `X-SSO-Key` |
| **Secret** | `services.casey.sso_api_secret` (environment variable) |
| **Timeouts** | Request: 3 seconds, Connect: 2 seconds |
| **Error Handling** | All `\Throwable` caught; failures logged at debug level; graceful degradation (return false/null) |

---

## 11. CSRF Protection

### 11.1 Implementation

The Submission Tracker uses Laravel's `VerifyCsrfToken` middleware, which validates a CSRF token on all POST, PUT, PATCH, and DELETE requests to web routes. Inertia.js automatically includes the CSRF token in all requests via the `X-XSRF-TOKEN` header (read from the `XSRF-TOKEN` cookie).

### 11.2 Token Lifecycle

| Event | Action |
|-------|--------|
| Session creation | CSRF token generated and stored in session |
| Page load (Inertia) | XSRF-TOKEN cookie set; Inertia reads and includes in subsequent requests |
| Login | Session regenerated; CSRF token rotated |
| Logout | Session invalidated; CSRF token regenerated (`regenerateToken()`) |
| SSO login | Session regenerated; CSRF token rotated |
| SSO logout (all types) | Session invalidated; CSRF token regenerated |

### 11.3 CSRF Exemptions

The following routes are excluded from CSRF verification with documented justification:

| Route | Justification | Alternative Protection |
|-------|--------------|----------------------|
| CAPS webhook endpoint (`/api/v1/caps/webhooks`) | Webhooks originate from CAPS server (no browser, no cookie) | HMAC-SHA256 signature verification (X-Caps-Signature) |
| SSO silent logout (`/auth/casey-sso-logout`) | Called from hidden iframe by CAPS; cannot carry Tracker CSRF token | GET request (idempotent); requires active session for meaningful action |
| SSO poll logout (`/sso-logout`) | Called by client-side JS when remote logout detected | GET request (idempotent); requires active session for meaningful action |

---

## 12. Multi-Tenant Isolation

### 12.1 Architecture

The Submission Tracker implements logical multi-tenancy where all tenants share the same database and application instance, isolated by a `tenant_id` foreign key on all data tables.

### 12.2 Tenant Resolution

The `TenantResolverService` resolves the current tenant from the request using a priority chain:

| Priority | Source | Use Case |
|----------|--------|----------|
| 1 (highest) | `X-Tenant` header (matched against `tenants.slug`) | API calls specifying target tenant |
| 2 | Request hostname (matched against `tenant_domains.domain`) | Domain-based tenant routing |
| 3 | Authenticated user's `tenant_id` | Default for logged-in users |
| 4 (lowest) | Default tenant (`tenants.slug = 'default'`) | Fallback when no other resolution succeeds |

Only tenants with `status = 'active'` are resolved.

### 12.3 Data Isolation Controls

| Control | Mechanism | Implementation |
|---------|-----------|----------------|
| **Auto-assignment** | `BelongsToTenant::bootBelongsToTenant()` automatically sets `tenant_id` on model creation from `TenantContext` | `App\Models\Concerns\BelongsToTenant` trait |
| **Query scoping** | `scopeForTenant()` filters queries by `tenant_id` | `BelongsToTenant` trait, applied via middleware |
| **API key binding** | `AuthenticateApiKey` sets `TenantContext` from `api_key.tenant_id` | `App\Http\Middleware\AuthenticateApiKey` |
| **Middleware** | `ResolveTenant` runs on every request, sets `TenantContext` | `App\Http\Middleware\ResolveTenant` |
| **Audit records** | Audit records carry `tenant_id`, scoped via `BelongsToTenant` | `App\Models\Audit` uses `BelongsToTenant` |

### 12.4 Models Using BelongsToTenant

All core data models include the `BelongsToTenant` trait, ensuring tenant isolation at the model layer:

- `Audit`
- `User`
- `Uploads`
- `Company`
- `Municipality`
- `MunicipalityDeadline`
- `UserAssignment`
- `ApiKey` (via tenant_id FK)

### 12.5 Cross-Tenant Access Prevention

- **No cross-tenant queries:** The `forTenant()` scope ensures all queries include a `WHERE tenant_id = ?` clause
- **No tenant_id override:** The `BelongsToTenant` creating hook only sets `tenant_id` if not already set, and the value comes from `TenantContext` (request-scoped, middleware-set)
- **API key isolation:** Each API key is bound to a single tenant; the tenant context is set before any data access
- **super-admin exception:** super-admin users may access data across tenants for system administration purposes

---

## 13. Audit Trail and Logging

### 13.1 Audit Architecture

The Submission Tracker implements a three-layer audit system that captures all data changes, authentication events, and state-changing HTTP requests.

| Layer | Component | Trigger | Scope |
|-------|-----------|---------|-------|
| **Model Events** | `RecordsAuditTrail` trait | Eloquent `created`, `updated`, `deleted` events | All core models |
| **Auth Events** | `AuditLogger::authEvent()` | Login, logout, SSO, provisioning, failed login | Authentication lifecycle |
| **Request Events** | `AuditTrailMiddleware` + `AuditLogger::requestEvent()` | POST, PUT, PATCH, DELETE requests (non-500 responses) | All state-changing web requests |

### 13.2 RecordsAuditTrail Trait

Applied to all core models via `use RecordsAuditTrail`. Automatically logs:

| Event | old_values | new_values |
|-------|------------|------------|
| `created` | `[]` (empty) | All model attributes at creation |
| `updated` | Previous values of changed attributes | New values of changed attributes (excludes `updated_at` to reduce noise) |
| `deleted` | All model attributes at time of deletion | `[]` (empty) |

### 13.3 AuditLogger

Central logging service with three entry points:

| Method | Use Case | Parameters |
|--------|----------|-----------|
| `forModelEvent()` | Model CRUD events | event name, model instance, old values, new values, optional meta |
| `authEvent()` | Authentication events | event name, optional subject (user), optional meta |
| `requestEvent()` | HTTP request events | event name, optional subject, optional meta |

All methods route through `AuditLogger::write()`, which creates an `Audit` record with:

| Field | Source |
|-------|--------|
| `tenant_id` | `TenantContext::tenantId()` or `user.tenant_id` |
| `user_type` | Polymorphic (typically `App\Models\User`) |
| `user_id` | Authenticated user's ID |
| `event` | Event name (e.g., `created`, `logged_in`, `request`) |
| `auditable_type` | Polymorphic model class |
| `auditable_id` | Model primary key |
| `old_values` | JSON (sanitised) |
| `new_values` | JSON (sanitised) |
| `url` | Full request URL |
| `ip_address` | Client IP address |
| `user_agent` | Client user agent (truncated to 1023 chars) |
| `tags` | Comma-separated: event name, route name, HTTP method |
| `created_at` | Timestamp |

### 13.4 Data Sanitisation

The `AuditLogger::sanitize()` method strips sensitive fields before logging:

| Stripped Field | Reason |
|----------------|--------|
| `password` | User passwords must never appear in logs |
| `password_confirmation` | Password confirmation field |
| `remember_token` | Session persistence token |
| `external_password_hash` | CAPS-synced password hash |

The `AuditTrailMiddleware` additionally strips from request data:

| Stripped Field | Reason |
|----------------|--------|
| `password` | User passwords in form submissions |
| `password_confirmation` | Password confirmation in form submissions |
| `_token` | CSRF token (unnecessary noise) |
| `_method` | HTTP method spoofing field (unnecessary noise) |

### 13.5 AuditTrailMiddleware

Runs on all web routes. Logs state-changing requests with the following rules:

| Condition | Action |
|-----------|--------|
| Request method is GET, HEAD, OPTIONS | Skip (read-only, no audit needed) |
| Request method is POST, PUT, PATCH, DELETE | Log if response status < 500 |
| Route is `login.store` or `logout` | Skip (handled by AuditLogger::authEvent directly) |
| Route is `admin.audits.*` | Skip (viewing audits should not itself create audit noise) |
| Response status >= 500 | Skip (server errors do not represent completed actions) |

### 13.6 Audit Events Catalogue

| Event | Category | Trigger |
|-------|----------|---------|
| `logged_in` | Auth | Successful login (local or SSO) |
| `logged_out` | Auth | Explicit logout (local, SSO silent, SSO remote, SSO poll) |
| `failed_login` | Auth | Failed local login attempt |
| `failed_sso` | Auth | Failed SSO login (invalid token, inactive user, no roles) |
| `provisioned_via_sso` | Auth | New user auto-provisioned from CAPS JWT claims |
| `created` | Data | New record created on any model with RecordsAuditTrail |
| `updated` | Data | Record updated on any model with RecordsAuditTrail |
| `deleted` | Data | Record deleted on any model with RecordsAuditTrail |
| `request` | HTTP | State-changing HTTP request (POST/PUT/PATCH/DELETE) |

### 13.7 Retention Policy

| Data Type | Retention Period | Justification |
|-----------|-----------------|---------------|
| Audit records | 7 years | Financial record-keeping regulatory requirement |
| Upload files | 7 years | Financial record-keeping regulatory requirement |
| CAPS verification results | Permanent (with upload) | Audit evidence for submission verification |
| Authentication logs | 7 years (within audit records) | Security incident investigation |
| Notifications | 90 days (auto-clear) | Operational relevance only |
| Sessions | 120 minutes | Security policy |

### 13.8 Audit Access

The audit trail is accessible at `/admin/audits` with the following features:

- **Permission required:** `view audits`
- **Filters:** User, event type, date range, auditable type
- **Display:** Polymorphic labels (User, Company, Municipality, MunicipalityDeadline, Uploads, UserAssignment)
- **Change count:** Computed attribute showing number of changed fields

---

## 14. Data Protection (POPIA Compliance)

### 14.1 POPIA Applicability

The Protection of Personal Information Act (Act 4 of 2013) applies to the Submission Tracker because it processes:

1. **SA Identity Numbers:** 13-digit national identity numbers classified as special personal information under POPIA
2. **Employee Personal Information:** Names, email addresses, employee numbers, department, position
3. **Financial Information:** Premium amounts, deduction details, payment batch data

Casey & Associates acts as the **responsible party** under POPIA, and any data processors (hosting providers, CAPS) must have appropriate processing agreements in place.

### 14.2 POPIA Conditions and Implementation

| POPIA Condition | Condition Name | Implementation in Submission Tracker |
|----------------|----------------|--------------------------------------|
| **Condition 1** | Accountability | Information Officer designated. This security documentation maintained. Audit trail provides accountability evidence. |
| **Condition 2** | Processing Limitation | Data collected only for the specific purpose of managing payroll deduction submissions. Users access only their assigned company/municipality data. SA ID numbers used solely for CAPS member verification. |
| **Condition 3** | Purpose Specification | Upload data retained for 7 years per financial record-keeping requirements. Notifications auto-cleared at 90 days. Purpose documented in this section. |
| **Condition 4** | Further Processing Limitation | Data not shared beyond CAPS verification. No third-party data sharing. API access scope-controlled. |
| **Condition 5** | Information Quality | CAPS reference data synced daily (02:30) and on-demand to maintain accuracy. Verification results persisted with timestamps. |
| **Condition 6** | Openness | Login page identifies the system. Users informed of data collection at registration. POPIA notice recommended (see Section 18). |
| **Condition 7** | Security Safeguards | This entire document. Encryption, access control, audit logging, session security, multi-tenancy, vulnerability mitigations. |
| **Condition 8** | Data Subject Participation | Users can view their own upload history and profile data. Data subject access requests handled via admin audit trail. |

### 14.3 SA ID Number Handling

SA ID numbers (13-digit South African national identity numbers) receive the highest level of protection:

| Control | Implementation |
|---------|----------------|
| **Classification** | Confidential |
| **Storage** | In uploaded spreadsheet files (private disk) and CAPS verification results (JSON column) |
| **Access** | Restricted to users assigned to the relevant company/municipality |
| **Logging** | Never explicitly logged in audit trail `old_values`/`new_values` (present only in file content and verification JSON) |
| **Transit** | TLS-encrypted in all API calls to CAPS (`?idNumber=` parameter) |
| **Verification** | Used solely for member matching against CAPS records |
| **Retention** | 7 years (with upload), then subject to purge policy |
| **Display** | Shown in verification results to authorised users only |

### 14.4 Password and Credential Protection

| Credential | Protection Measures |
|-----------|-------------------|
| **User password** | Bcrypt 12-round hash. Laravel `hashed` cast ensures automatic hashing on write. `$hidden` array prevents serialisation. AuditLogger sanitisation strips from all log entries. |
| **External password hash** | Bcrypt hash synced from CAPS. `$hidden` array prevents serialisation. AuditLogger sanitisation strips from all log entries. |
| **Remember token** | `$hidden` array prevents serialisation. AuditLogger sanitisation strips from all log entries. |
| **API key secret** | SHA256 hash stored. Plain text returned exactly once on creation. Never stored, logged, or retrievable after creation. |
| **CAPS JWT** | Stored in server-side session only. Never exposed to browser. Session database-backed with optional encryption. |
| **Integration credentials** | Encrypted at rest using Laravel encryption (AES-256-CBC). Decrypted only when needed for API calls. |
| **CAPS JWT shared secret** | Environment variable only. Base64-encoded. Never committed to source control. |
| **CAPS webhook secret** | Environment variable only. Never committed to source control. |
| **SSO API secret** | Environment variable only. Never committed to source control. |

### 14.5 Data Breach Notification

Under POPIA Section 22, Casey & Associates must notify the Information Regulator and affected data subjects of any compromise. See Section 17 (Incident Response Plan) for procedures.

---

## 15. Vulnerability Mitigations

### 15.1 OWASP Top 10 Coverage

| OWASP Category | Risk | Mitigation | Status |
|----------------|------|------------|--------|
| **A01:2021 Broken Access Control** | Unauthorised access to data or functions | RBAC with 4 roles and 23+ permissions. Assignment-based access for uploads. Tenant isolation. Route-level and controller-level authorisation. | Implemented |
| **A02:2021 Cryptographic Failures** | Weak or missing encryption | Bcrypt 12 rounds for passwords. HMAC-SHA256 for JWT/webhooks. SHA256 for API keys. AES-256-CBC for encrypted columns. TLS required in production. | Implemented |
| **A03:2021 Injection** | SQL injection, command injection | Eloquent ORM with parameterised queries throughout. No raw SQL with user input. Laravel validation on all form inputs. | Implemented |
| **A04:2021 Insecure Design** | Missing security controls in architecture | Defence in depth: authentication + authorisation + tenant isolation + audit. Threat model documented. Assignment-based access beyond simple RBAC. | Implemented |
| **A05:2021 Security Misconfiguration** | Default credentials, verbose errors | Production: APP_DEBUG=false, SESSION_SECURE_COOKIE=true, SESSION_ENCRYPT=true. Documented configuration reference (Section 20). | Requires verification per environment |
| **A06:2021 Vulnerable and Outdated Components** | Known vulnerabilities in dependencies | Composer and NPM lock files. Regular `composer audit` and `npm audit` recommended. No third-party JWT library (custom HS256 implementation reduces supply chain risk). | Requires ongoing process |
| **A07:2021 Identification and Authentication Failures** | Credential compromise, session attacks | Multi-method auth. Session regeneration on auth state changes. HTTP-only/SameSite cookies. 120-minute timeout. SSO with JWT verification. | Implemented |
| **A08:2021 Software and Data Integrity Failures** | Tampered updates, unsigned data | HMAC-SHA256 webhook signatures. Timing-safe comparison. Idempotency tracking. Audit trail integrity. | Implemented |
| **A09:2021 Security Logging and Monitoring Failures** | Missing audit trail | Three-layer audit: model events, auth events, request events. 23+ event types. 7-year retention. Admin audit viewer. | Implemented |
| **A10:2021 Server-Side Request Forgery** | SSRF via crafted URLs | Outbound requests limited to configured CAPS API endpoints and SSO microservice URL. No user-controlled URLs in server-side HTTP calls. Timeouts enforced (3s request, 2s connect). | Low risk |

### 15.2 Specific Vulnerability Mitigations

#### SQL Injection
- **Risk:** Attacker injects malicious SQL via form inputs or query parameters
- **Mitigation:** All database queries use Eloquent ORM with parameterised bindings. No `DB::raw()` with unsanitised user input. Laravel query builder automatically escapes values.
- **Evidence:** Code review confirms all queries use Eloquent methods (`where()`, `create()`, `update()`, `first()`, etc.)

#### Cross-Site Scripting (XSS)
- **Risk:** Attacker injects malicious JavaScript into pages viewed by other users
- **Mitigation:** Vue.js (used via Inertia.js) auto-escapes all template interpolation (`{{ }}`) by default. Inertia.js server-side rendering does not output raw HTML. No `v-html` usage on user-supplied content.
- **Additional:** Content-Security-Policy headers recommended for production (see Section 16).

#### Cross-Site Request Forgery (CSRF)
- **Risk:** Attacker tricks authenticated user into performing unintended actions
- **Mitigation:** Laravel `VerifyCsrfToken` middleware on all web routes. Inertia.js automatically includes CSRF token. Token rotated on session regeneration. Exemptions documented with alternative protections (Section 11).

#### Session Fixation
- **Risk:** Attacker sets a known session ID before authentication, then hijacks the session post-login
- **Mitigation:** `$request->session()->regenerate()` called on every login (local and SSO). `$request->session()->invalidate()` called on every logout. New session ID issued on authentication state change.

#### Brute Force Attacks
- **Risk:** Attacker systematically tries credentials until finding a valid combination
- **Mitigation:** All failed login attempts logged via `AuditLogger::authEvent('failed_login')` with employee number. Laravel `throttle` middleware available for rate limiting on login routes. IP address logged for correlation.
- **Recommendation:** Implement `ThrottleRequests` middleware on login route with a limit of 5 attempts per minute per IP. See Section 19.

#### Path Traversal
- **Risk:** Attacker manipulates file paths to access files outside intended directories
- **Mitigation:** File downloads served via authenticated controller endpoints, not direct URL access. Files stored on `private` disk (not in public web root). Laravel `Storage` facade handles path sanitisation.

#### Insecure Direct Object References (IDOR)
- **Risk:** Attacker modifies resource IDs in URLs to access other users' data
- **Mitigation:** Assignment-based access checks (`isAssignedToCompanyInMunicipality()`, `canAccessUpload()`). Admin role required for cross-user access. Tenant isolation prevents cross-tenant access.

#### JWT Algorithm Confusion
- **Risk:** Attacker changes JWT `alg` header to `none` or `RS256` to bypass signature verification
- **Mitigation:** `CaseyJwtService::verify()` explicitly checks `strtoupper($header['alg']) !== 'HS256'` and rejects any other algorithm, including `none`, `RS256`, `RS384`, `RS512`, etc.

#### Timing Attacks on Signatures
- **Risk:** Attacker measures response times to gradually determine the correct HMAC signature
- **Mitigation:** Both JWT signature verification and webhook signature verification use `hash_equals()` for constant-time string comparison.

---

## 16. Network Security

### 16.1 Production Requirements

| Control | Configuration | Enforcement |
|---------|---------------|-------------|
| **HTTPS** | TLS 1.2+ required for all connections | Web server configuration (nginx/Apache). `SESSION_SECURE_COOKIE=true` ensures cookies only sent over HTTPS. |
| **HSTS** | `Strict-Transport-Security: max-age=31536000; includeSubDomains` | Web server header. Prevents protocol downgrade attacks. |
| **Secure Cookies** | `SESSION_SECURE_COOKIE=true` | Laravel session configuration. Cookie rejected over HTTP. |
| **Session Encryption** | `SESSION_ENCRYPT=true` | Laravel session configuration. Session cookie value encrypted. |
| **SameSite** | `SESSION_SAME_SITE=lax` | Laravel session configuration. Prevents cross-site cookie transmission except for top-level navigations. |
| **CORS** | Configured for known origins only | Laravel CORS middleware. Restrict `Access-Control-Allow-Origin` to the Tracker and CAPS domains. |
| **Rate Limiting** | `throttle` middleware on auth routes | Laravel `ThrottleRequests` middleware. Recommended: 5/min on login, 60/min on API. |

### 16.2 Recommended HTTP Security Headers

| Header | Value | Purpose |
|--------|-------|---------|
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | Force HTTPS for 1 year |
| `X-Content-Type-Options` | `nosniff` | Prevent MIME-type sniffing |
| `X-Frame-Options` | `SAMEORIGIN` | Prevent clickjacking (allow SSO iframes from same origin) |
| `X-XSS-Protection` | `0` | Disabled (CSP preferred over browser XSS filter) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Limit referrer leakage |
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'` | Restrict resource loading to same origin |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` | Disable unnecessary browser features |

### 16.3 Firewall and Network Segmentation Recommendations

| Zone | Components | Allowed Inbound | Allowed Outbound |
|------|-----------|-----------------|-----------------|
| **DMZ** | Load balancer, reverse proxy | HTTPS (443) from internet | HTTPS to application zone |
| **Application** | Submission Tracker (Laravel) | HTTPS from DMZ only | HTTPS to CAPS API, SSO microservice, database |
| **SSO** | SSO Session Microservice | HTTPS from application zone only | None (in-memory, no external calls) |
| **Database** | MySQL/PostgreSQL | TCP from application zone only (3306/5432) | None |
| **CAPS** | External CAPS platform | HTTPS from application zone (API calls) | HTTPS to application zone (webhooks) |

---

## 17. Incident Response Plan

### 17.1 Incident Classification

| Severity | Definition | Examples | Response Time |
|----------|-----------|----------|---------------|
| **P1 - Critical** | Active data breach, system compromise, or credential exposure affecting production | Unauthorised access to SA ID numbers, JWT shared secret leaked, production database exposed | Immediate (within 30 minutes) |
| **P2 - High** | Security vulnerability discovered that could lead to a P1 if exploited | Authentication bypass, privilege escalation bug, SQL injection found in code review | Within 4 hours |
| **P3 - Medium** | Security weakness identified with limited exploitability | Missing rate limiting on non-critical endpoint, verbose error messages in staging | Within 24 hours |
| **P4 - Low** | Security improvement opportunity, best practice recommendation | Outdated dependency without known exploitable vulnerability, header misconfiguration | Within 1 week |

### 17.2 Incident Response Phases

#### Phase 1: Detection and Identification

| Detection Source | Indicators | Action |
|-----------------|-----------|--------|
| Audit trail (`/admin/audits`) | Unusual `failed_login` patterns, unexpected `failed_sso` events, data access from unfamiliar IPs | Investigate IP addresses, employee numbers, timestamps |
| Application logs | `CAPS webhook rejected: invalid signature`, repeated `RuntimeException` in CaseyJwtService | Check for signature brute-force attempts |
| Infrastructure monitoring | Spike in 401/403 responses, unusual outbound traffic | Correlate with audit trail |
| User reports | Unexpected logout, unfamiliar activity in upload history | Verify audit trail for the reported user |
| Dependency scanning | `composer audit` / `npm audit` reports | Assess exploitability in context of application |

#### Phase 2: Containment

| Action | Procedure |
|--------|----------|
| **Isolate compromised user** | Set `is_active = false` on user record (immediately blocks all auth methods) |
| **Revoke API keys** | Set `revoked_at = now()` on compromised API keys |
| **Rotate secrets** | Update CASEY_JWT_SHARED_SECRET, CAPS_WEBHOOK_SECRET, SSO API secret as needed |
| **Invalidate sessions** | Truncate `sessions` table to force all users to re-authenticate |
| **Block IP addresses** | Add to web server deny list or WAF rules |
| **Disable SSO** | Set `CASEY_SSO_ENABLED=false` if SSO is the attack vector |

#### Phase 3: Eradication

| Action | Procedure |
|--------|----------|
| Identify root cause | Review audit trail, application logs, and infrastructure logs |
| Patch vulnerability | Deploy code fix to production |
| Update dependencies | Run `composer update` / `npm update` if dependency vulnerability is the cause |
| Verify fix | Confirm vulnerability is no longer exploitable |
| Re-enable disabled services | Re-enable SSO, re-issue API keys as appropriate |

#### Phase 4: Recovery

| Action | Procedure |
|--------|----------|
| Restore access | Reactivate legitimate user accounts |
| Monitor closely | Increase audit review frequency for 30 days |
| Validate data integrity | Compare audit trail to detect any unauthorised changes |
| Re-issue credentials | Force password reset for affected users |

#### Phase 5: Post-Incident

| Action | Procedure |
|--------|----------|
| Document incident | Create incident report with timeline, root cause, impact, and remediation |
| Notify Information Regulator | Required under POPIA Section 22 if personal information was compromised |
| Notify affected data subjects | Required under POPIA Section 22 if SA ID numbers or other PII were exposed |
| Update threat model | Add new threat to threat catalogue (Section 2.3) |
| Conduct lessons-learned review | Update security controls, documentation, and monitoring |

### 17.3 POPIA Breach Notification Requirements

Under Section 22 of POPIA:

1. **Notification to Information Regulator:** As soon as reasonably possible after discovery of the compromise
2. **Notification to Data Subjects:** As soon as reasonably possible after discovery, providing:
   - Description of the possible consequences of the security compromise
   - Description of the measures taken or to be taken to address the security compromise
   - Recommendations regarding measures to be taken by the data subjects to mitigate possible adverse effects
   - Identity and contact details of the Information Officer
3. **Documentation:** Maintain records of all breaches, notifications, and remediation actions

### 17.4 Contact Information

| Role | Responsibility | Contact |
|------|---------------|---------|
| Information Officer | POPIA compliance, breach notification | [To be designated] |
| System Administrator | Technical incident response | [To be designated] |
| Development Lead | Code-level investigation and remediation | [To be designated] |
| CAPS Integration Contact | CAPS-side investigation, secret rotation | [To be designated] |

---

## 18. POPIA Compliance Checklist

### 18.1 Responsible Party Obligations

| # | Requirement | Status | Evidence | Action Required |
|---|-----------|--------|----------|----------------|
| 1 | **Information Officer** designated and registered with Information Regulator | Pending | -- | Designate Information Officer and register with the Regulator |
| 2 | **POPIA notice** displayed to users at first login or registration | Pending | -- | Create and display privacy notice during onboarding |
| 3 | **Purpose limitation:** Personal information collected only for documented purposes | Compliant | SA ID numbers used solely for CAPS member verification. Employee data used for authentication and access control. | -- |
| 4 | **Minimality:** Only necessary personal information collected | Compliant | Minimum fields required: employee_number, name, email. SA ID numbers present only in uploaded deduction files (business requirement). | -- |
| 5 | **Consent or legal basis:** Lawful basis for processing documented | Pending | -- | Document lawful basis (likely: compliance with law, legitimate interest, or contractual obligation) |
| 6 | **Retention policy:** Defined and documented | Compliant | 7-year retention for financial records and audit trail. 90-day retention for notifications. Documented in Section 13.7. | Implement automated purge for records exceeding retention period |
| 7 | **Data subject access:** Mechanism for data subjects to request access | Partial | Users can view own uploads and profile. No formal SAR (Subject Access Request) process. | Implement formal SAR handling procedure |
| 8 | **Data subject correction:** Mechanism for correction requests | Partial | Admin users can update user profiles. No formal correction request process. | Implement formal correction request procedure |
| 9 | **Data subject deletion:** Mechanism for deletion requests (right to be forgotten) | Pending | -- | Implement data deletion procedure (subject to 7-year financial retention requirement) |
| 10 | **Breach notification:** Procedure for notifying Regulator and data subjects | Compliant | Incident Response Plan documented in Section 17. | Conduct tabletop exercise to validate procedure |

### 18.2 Security Safeguards (POPIA Condition 7)

| # | Safeguard | Status | Evidence |
|---|----------|--------|----------|
| 1 | Access controlled by identity verification (authentication) | Compliant | Multi-method auth: local, SSO, API key (Section 3) |
| 2 | Access limited to authorised persons (authorisation) | Compliant | RBAC with 4 roles, 23+ permissions, assignment-based access (Section 6) |
| 3 | Technical measures to prevent loss, damage, or unauthorised access | Compliant | Encryption (Section 9), session security (Section 5), tenant isolation (Section 12), vulnerability mitigations (Section 15) |
| 4 | Organisational measures to prevent loss, damage, or unauthorised access | Partial | Incident Response Plan (Section 17). Formal security policies should be established (access management, password policy, acceptable use). |
| 5 | Integrity and confidentiality of personal information maintained | Compliant | Audit trail (Section 13), data classification (Section 7), password/credential protection (Section 14.4) |
| 6 | Regular monitoring and review of security measures | Pending | Penetration testing schedule recommended (Section 19). Quarterly security review to be scheduled. |
| 7 | Protection against reasonably foreseeable risks | Compliant | Threat model (Section 2), OWASP Top 10 coverage (Section 15.1) |

### 18.3 Operator (Processor) Obligations

| # | Obligation | Applicability | Status |
|---|-----------|--------------|--------|
| 1 | Written agreement with all operators processing personal information | CAPS (API integration), hosting provider | Pending -- ensure data processing agreements are in place |
| 2 | Operators must treat personal information as confidential | All operators | Pending -- verify contractual terms |
| 3 | Operators must implement appropriate security measures | All operators | Pending -- verify operator security posture |

### 18.4 Trans-Border Data Transfer

| # | Requirement | Status | Notes |
|---|-----------|--------|-------|
| 1 | Determine if personal information is transferred outside South Africa | To verify | If CAPS API or hosting infrastructure is outside SA, Section 72 applies |
| 2 | Ensure adequate level of protection in recipient jurisdiction | Conditional | Required if cross-border transfer is confirmed |
| 3 | Obtain consent for cross-border transfer if no adequacy finding | Conditional | Required if no adequacy finding exists for recipient jurisdiction |

---

## 19. Penetration Testing Recommendations

### 19.1 Testing Scope

| Area | Priority | Test Focus |
|------|----------|-----------|
| **Authentication** | Critical | Brute force resistance, credential stuffing, session fixation, JWT verification bypass, algorithm confusion attacks, external password hash bypass |
| **Authorisation** | Critical | RBAC bypass, horizontal privilege escalation (accessing other users' uploads), vertical privilege escalation (user to admin), tenant isolation breakout |
| **SSO Integration** | High | JWT forgery, token replay, redirect loop exploitation, session synchronisation race conditions, silent logout bypass |
| **API Key Security** | High | Key brute force, scope bypass, revocation bypass, tenant isolation via API key |
| **Webhook Security** | High | HMAC signature bypass, timing attack on signature comparison, replay attack (idempotency bypass), payload injection |
| **File Upload** | High | Malicious file upload (web shells, polyglots), path traversal via filename, file size bypass, MIME type confusion |
| **CSRF** | Medium | CSRF on exempted routes, token prediction, cross-origin attacks |
| **XSS** | Medium | Stored XSS via upload metadata, reflected XSS via error messages, DOM XSS in Vue components |
| **SQL Injection** | Medium | All user inputs including search, filter, and sort parameters |
| **Tenant Isolation** | Critical | Cross-tenant data access via parameter manipulation, API key tenant bypass, audit record leakage |

### 19.2 Recommended Test Scenarios

#### Authentication Tests

| # | Scenario | Expected Result |
|---|---------|-----------------|
| AT-01 | Submit 100 login attempts with invalid password in 1 minute | Rate-limited after threshold (verify throttle middleware) |
| AT-02 | Forge JWT with `alg: none` and valid claims | Rejected with "Unsupported JWT algorithm" |
| AT-03 | Forge JWT with `alg: RS256` and attacker-controlled public key | Rejected with "Unsupported JWT algorithm" |
| AT-04 | Submit JWT with expired `exp` (beyond 30s leeway) | Rejected with "JWT has expired" |
| AT-05 | Submit JWT with `nbf` in the future (beyond 30s leeway) | Rejected with "JWT is not yet valid" |
| AT-06 | Submit JWT with empty `sub` claim | Rejected with "JWT is missing the subject claim" |
| AT-07 | Submit JWT with valid structure but incorrect HMAC secret | Rejected with "JWT signature mismatch" |
| AT-08 | Login as inactive user (local auth) | Login succeeds at Auth::attempt but [verify if is_active check exists in local flow] |
| AT-09 | Login as inactive user (SSO) | Rejected with "account is deactivated" |
| AT-10 | Login as user with no Tracker roles (SSO) | Rejected with "do not have access" |

#### Authorisation Tests

| # | Scenario | Expected Result |
|---|---------|-----------------|
| AZ-01 | User attempts to access /admin/audits without `view audits` permission | 403 Forbidden |
| AZ-02 | User attempts to upload file for unassigned company/municipality | Rejected (assignment check fails) |
| AZ-03 | User attempts to download file from another user's upload (same tenant) | Rejected unless admin or assigned |
| AZ-04 | User modifies upload_id in URL to access another tenant's upload | No data returned (tenant isolation) |
| AZ-05 | API key with `data:read` scope attempts POST to data:write endpoint | 403 API key lacks required scope |
| AZ-06 | Revoked API key used for authentication | 401 Invalid API key |

#### Webhook Tests

| # | Scenario | Expected Result |
|---|---------|-----------------|
| WH-01 | Submit webhook without X-Caps-Signature header | 401 Invalid signature |
| WH-02 | Submit webhook with incorrect HMAC signature | 401 Invalid signature |
| WH-03 | Replay a previously processed webhook (same eventId) | 200 "Event already processed" (no duplicate state change) |
| WH-04 | Submit webhook with missing event type | 422 Missing event type |
| WH-05 | Submit webhook with large payload (>1MB) | Request rejected by web server body size limit |

#### Tenant Isolation Tests

| # | Scenario | Expected Result |
|---|---------|-----------------|
| TI-01 | User from Tenant A requests data with X-Tenant: B header | Data from Tenant B not accessible (resolved to user's tenant) |
| TI-02 | API key for Tenant A used with X-Tenant: B header | Request uses Tenant A context (API key overrides header) |
| TI-03 | Create record via API, verify tenant_id is automatically set | tenant_id matches resolved tenant |
| TI-04 | Query uploads without tenant scope (attempt bypass) | All queries include tenant_id filter |

### 19.3 Testing Schedule

| Test Type | Frequency | Trigger |
|-----------|-----------|---------|
| Full penetration test | Annually | Scheduled |
| Targeted assessment | After major feature release | Release gate |
| Vulnerability scan (automated) | Monthly | Scheduled |
| Dependency audit (`composer audit`, `npm audit`) | Weekly | CI/CD pipeline |
| Code review (security-focused) | Per pull request | Development workflow |

### 19.4 Penetration Testing Rules of Engagement

1. **Scope:** All Submission Tracker endpoints (web and API). Excludes CAPS production systems (test against staging/mock).
2. **Authorisation:** Written authorisation from Casey & Associates management required before testing.
3. **Environment:** Conduct testing against a dedicated staging environment with representative data.
4. **Data:** Use synthetic SA ID numbers and test data. Never use real PII in testing.
5. **Reporting:** All findings classified by CVSS v3.1 severity. Critical/High findings reported immediately (within 24 hours).
6. **Retesting:** All Critical/High findings retested after remediation to confirm fix.
7. **Tools:** Standard web application testing tools (Burp Suite, OWASP ZAP, custom scripts). No denial-of-service testing without explicit approval.

---

## 20. Security Configuration Reference

### 20.1 Environment Variables

| Variable | Purpose | Classification | Default |
|----------|---------|---------------|---------|
| `APP_KEY` | Laravel application encryption key (AES-256-CBC) | Confidential | Generated via `php artisan key:generate` |
| `APP_DEBUG` | Debug mode (must be `false` in production) | Configuration | `false` |
| `APP_ENV` | Environment identifier | Configuration | `production` |
| `SESSION_DRIVER` | Session storage backend | Configuration | `database` |
| `SESSION_LIFETIME` | Session timeout in minutes | Configuration | `120` |
| `SESSION_SECURE_COOKIE` | Cookie HTTPS-only flag | Configuration | `true` (production) |
| `SESSION_ENCRYPT` | Session data encryption | Configuration | `true` (production) |
| `CASEY_JWT_SHARED_SECRET` | HS256 shared secret for CAPS JWT verification (base64-encoded) | Confidential | None (must be configured) |
| `CASEY_SSO_ENABLED` | Enable/disable CAPS SSO | Configuration | `false` |
| `CASEY_SSO_AUTO_PROVISION` | Auto-create users from SSO claims | Configuration | `true` |
| `CASEY_SSO_DEFAULT_ROLE` | Role assigned to auto-provisioned users | Configuration | `user` |
| `CASEY_SSO_SKIP_SECONDS` | Duration of anti-loop cookie in seconds | Configuration | `60` |
| `CAPS_WEBHOOK_SECRET` | HMAC-SHA256 secret for webhook verification | Confidential | None (must be configured) |
| `SSO_SERVICE_URL` | URL of SSO session microservice | Configuration | `http://localhost:4000` |
| `SSO_API_SECRET` | Authentication secret for SSO microservice | Confidential | `casey-sso-dev-secret` (change in production) |

### 20.2 Production Hardening Checklist

| # | Item | Verification |
|---|------|-------------|
| 1 | `APP_DEBUG=false` | `php artisan env` or check `.env` |
| 2 | `APP_ENV=production` | `php artisan env` |
| 3 | `SESSION_SECURE_COOKIE=true` | Check `config/session.php` |
| 4 | `SESSION_ENCRYPT=true` | Check `config/session.php` |
| 5 | `SESSION_SAME_SITE=lax` | Check `config/session.php` |
| 6 | `SESSION_HTTP_ONLY=true` | Check `config/session.php` |
| 7 | HTTPS enforced at load balancer/web server | Check web server config |
| 8 | HSTS header configured | `curl -I https://tracker.domain.com` |
| 9 | CORS configured for known origins only | Check `config/cors.php` |
| 10 | `CASEY_JWT_SHARED_SECRET` set and matches CAPS | Verify with CAPS team |
| 11 | `CAPS_WEBHOOK_SECRET` set and matches CAPS | Verify with CAPS team |
| 12 | `SSO_API_SECRET` changed from default | Check `.env` |
| 13 | Database credentials rotated from development values | Check `.env` |
| 14 | File storage permissions restricted (no web access to private disk) | Check web server config |
| 15 | Rate limiting enabled on login routes | Check route middleware |
| 16 | Error pages do not reveal stack traces | Browse to 404/500 in production |
| 17 | `.env` file not accessible via web | Browse to `https://tracker.domain.com/.env` |
| 18 | `storage/` directory not accessible via web | Browse to `https://tracker.domain.com/storage/` |
| 19 | Composer and NPM development dependencies excluded | `composer install --no-dev`, `npm run build` |
| 20 | Database migrations applied | `php artisan migrate:status` |

---

## 21. Appendices

### Appendix A: Cryptographic Algorithm Summary

| Algorithm | Standard | Key Length | Usage in System |
|-----------|----------|-----------|----------------|
| Bcrypt (2y) | NIST SP 800-132 | 184-bit salt, variable cost (12 rounds) | Password hashing |
| HMAC-SHA256 | RFC 2104, FIPS 198-1 | 256-bit key (from base64 secret) | JWT signature verification, webhook signature verification |
| SHA256 | FIPS 180-4 | 256-bit output | API key hashing |
| AES-256-CBC | FIPS 197 | 256-bit key | Application encryption (APP_KEY), session encryption, credential encryption |
| Base64 | RFC 4648 | N/A (encoding, not encryption) | JWT segment encoding, shared secret storage |
| Base64url | RFC 4648 Section 5 | N/A (encoding, not encryption) | JWT header, payload, and signature encoding |

### Appendix B: Regulatory Reference

| Regulation | Section | Relevance |
|-----------|---------|-----------|
| POPIA (Act 4 of 2013) | Section 14 | Personal information must be collected for a specific, explicitly defined, and lawful purpose |
| POPIA | Section 15 | Retention must not be longer than necessary (but financial records require 7 years) |
| POPIA | Section 19 | Security safeguards -- integrity and confidentiality of personal information |
| POPIA | Section 22 | Notification of security compromises |
| POPIA | Section 69-72 | Trans-border information flows |
| POPIA | Section 100-107 | Offences and penalties |
| SA Tax Administration Act | Section 29 | Financial record retention (minimum 5 years; 7 years adopted as conservative standard) |
| SA Companies Act (71 of 2008) | Section 24 | Company to maintain accounting records for 7 years |

### Appendix C: Security Contacts and Escalation

| Level | Contact | Responsibility |
|-------|---------|---------------|
| L1 | System Administrator | Initial triage, routine security events |
| L2 | Development Lead | Code-level investigation, vulnerability remediation |
| L3 | Information Officer | POPIA compliance, breach notification, regulatory communication |
| L4 | External Security Consultant | Penetration testing, incident response support |

### Appendix D: Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | April 2026 | Security Engineering | Initial version |

### Appendix E: Related Documentation

| Document | Content |
|----------|---------|
| `01-REQUIREMENTS-DOCUMENTATION.md` | Business context, functional/non-functional requirements, integration requirements, data requirements |
| `Submission_Tracker_CAPS_Integration_Analysis.md` | Detailed CAPS API integration analysis |

---

**End of Security and Compliance Documentation**

*This document must be reviewed quarterly and updated after any security incident, architectural change, or regulatory development. The next scheduled review date is July 2026.*
