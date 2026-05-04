# 02 - System Design Documentation

## Submission Tracker - Municipal Payroll Deduction File Management System

| Attribute            | Value                                                     |
|----------------------|-----------------------------------------------------------|
| Document ID          | ST-SDD-002                                                |
| Version              | 1.0.0                                                     |
| Classification       | Internal - Confidential                                   |
| Status               | Approved                                                  |
| Last Updated         | 2026-04-23                                                |
| Author               | Engineering Team                                          |
| Review Cycle         | Quarterly                                                 |

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [System Overview](#2-system-overview)
3. [Architecture Overview](#3-architecture-overview)
4. [Component Architecture](#4-component-architecture)
5. [Data Architecture](#5-data-architecture)
6. [Integration Architecture](#6-integration-architecture)
7. [Security Architecture](#7-security-architecture)
8. [Multi-Tenancy Architecture](#8-multi-tenancy-architecture)
9. [File Storage Architecture](#9-file-storage-architecture)
10. [API Design](#10-api-design)
11. [Workflow and Business Logic](#11-workflow-and-business-logic)
12. [Deployment Architecture](#12-deployment-architecture)
13. [Observability and Audit](#13-observability-and-audit)
14. [Error Handling and Resilience](#14-error-handling-and-resilience)
15. [Performance Considerations](#15-performance-considerations)
16. [Appendices](#16-appendices)

---

## 1. Introduction

### 1.1 Purpose

This document provides the comprehensive system design specification for the Submission Tracker application. It describes the architecture, component interactions, data models, integration patterns, security mechanisms, and deployment topology that collectively form the platform for managing municipal payroll deduction file submissions.

This document is intended for software engineers, system architects, DevOps engineers, security reviewers, and technical stakeholders who need to understand, maintain, extend, or audit the system.

### 1.2 Scope

The Submission Tracker is a multi-tenant web application that enables payroll administrators to:

- Upload and manage deduction file submissions (original emails, workings spreadsheets, system import files) organised by municipality and company.
- Track submission deadlines per municipality with user assignments.
- Verify uploaded data against the CAPS (Casey Administration and Premium System) API for member existence, policy validity, and premium accuracy.
- Receive real-time processing status updates from CAPS via webhooks.
- Authenticate via bidirectional Single Sign-On (SSO) with the CAPS frontend.
- Produce audit trails, reports, and notifications for compliance and operational visibility.

### 1.3 Design Principles

| Principle                  | Description                                                                                                    |
|----------------------------|----------------------------------------------------------------------------------------------------------------|
| CAPS is System of Record   | Companies and municipalities are mastered in CAPS. The Tracker syncs from CAPS and treats local data as a cache. |
| Global Company Scope       | Every company submits to every municipality. Companies are not scoped 1:1 to a single municipality.            |
| User-Context API Calls     | CAPS API calls use the logged-in user's SSO JWT when available, falling back to service-account credentials.   |
| Tenant Isolation            | All data is scoped to a tenant. Global scopes and middleware enforce isolation transparently.                   |
| Graceful Degradation        | When external services (SSO microservice, CAPS API) are unreachable, the system continues operating locally.   |
| Auditability                | Every state mutation is recorded via audit trails. Webhook events are stored for replay and forensic analysis.  |

### 1.4 Technology Stack Summary

| Layer               | Technology                                              | Version / Notes                          |
|----------------------|---------------------------------------------------------|------------------------------------------|
| Backend Framework    | Laravel                                                 | 12 (PHP 8.2+)                            |
| ORM                  | Eloquent ORM                                            | MySQL driver                             |
| Database             | MySQL                                                   | 8.x                                      |
| Frontend Framework   | Vue 3 + Inertia.js                                      | SPA-like with server-side routing        |
| CSS Framework        | Tailwind CSS                                            | Utility-first styling                    |
| SSO Microservice     | Node.js / Express                                       | In-memory session store, port 4000       |
| External API         | CAPS (Spring Boot / Java)                               | Port 9086                                |
| CAPS Frontend        | Next.js / React                                         | Port 3000                                |
| Authentication       | Laravel Sanctum + Spatie Permissions + HS256 JWT (SSO)  |                                          |
| Spreadsheet Parsing  | PhpSpreadsheet                                          | .xlsx / .csv parsing                     |
| Email Parsing        | php-mime-mail-parser                                    | .eml / .msg parsing                      |
| Export               | Maatwebsite Excel                                       | Report and upload exports                |
| RBAC                 | Spatie laravel-permission                               | Role and permission-based access control |

### 1.5 Conventions

- All timestamps are stored and displayed in UTC unless otherwise noted.
- JSON columns use MySQL's native JSON type.
- All IDs are unsigned 64-bit auto-incrementing integers unless specified as UUIDs.
- `casey_id` columns store the CAPS-side identifier as a string (UUIDs in CAPS).

---

## 2. System Overview

### 2.1 System Context Diagram (ASCII)

```
+---------------------------------------------------------------------+
|                        EXTERNAL ACTORS                               |
+---------------------------------------------------------------------+
|                                                                      |
|   +------------------+        +------------------+                   |
|   |  Payroll Admin   |        |  System Admin    |                   |
|   |  (End User)      |        |  (IT Admin)      |                   |
|   +--------+---------+        +--------+---------+                   |
|            |                           |                             |
+---------------------------------------------------------------------+
             |                           |
             v                           v
+---------------------------------------------------------------------+
|                    SUBMISSION TRACKER SYSTEM                          |
|                                                                      |
|  +-------------------------------+  +----------------------------+   |
|  | Submission Tracker (Laravel)  |  | Casey SSO Microservice     |   |
|  | - Web UI (Vue 3 / Inertia)   |  | (Node.js / Express)        |   |
|  | - REST API (Sanctum)         |  | Port 4000                  |   |
|  | - Webhook Receiver           |  +----------------------------+   |
|  | Port 8000                    |                                    |
|  +-------------------------------+                                   |
|                                                                      |
+---------------------------------------------------------------------+
             |                           |
             v                           v
+---------------------------------------------------------------------+
|                      EXTERNAL SYSTEMS                                |
|                                                                      |
|  +-------------------------------+  +----------------------------+   |
|  | CAPS Backend API              |  | CAPS Frontend              |   |
|  | (Spring Boot / Java)          |  | (Next.js / React)          |   |
|  | Port 9086                     |  | Port 3000                  |   |
|  +-------------------------------+  +----------------------------+   |
|                                                                      |
|  +-------------------------------+                                   |
|  | MySQL Database                |                                   |
|  | (Shared or Dedicated)         |                                   |
|  +-------------------------------+                                   |
+---------------------------------------------------------------------+
```

### 2.2 System Boundaries

The Submission Tracker system boundary encompasses:

- **Inside boundary:** Laravel application, Vue 3 frontend, Casey SSO Microservice, MySQL database, local file storage.
- **Outside boundary:** CAPS Backend API, CAPS Frontend, email systems, end-user browsers.

---

## 3. Architecture Overview

### 3.1 High-Level Architecture Diagram

```
+------------------------------------------------------------------+
|                        USER'S BROWSER                             |
|  +------------------------------------------------------------+  |
|  |  Vue 3 SPA (Inertia.js)                                    |  |
|  |  - Pages: Dashboard, Uploads, Deadlines, Admin, Auth       |  |
|  |  - Tailwind CSS styling                                     |  |
|  |  - Inertia Link / Form for navigation (no full page reload)|  |
|  +------------------------------------------------------------+  |
+-------------------+-----------------------------------+-----------+
                    |                                   |
          Inertia HTTP                          Direct API
          (HTML + JSON props)                   (Sanctum / API Key)
                    |                                   |
+-------------------v-----------------------------------v-----------+
|                     LARAVEL APPLICATION (Port 8000)                |
|                                                                    |
|  +-------------------+  +------------------+  +-----------------+  |
|  | Web Routes        |  | API v1 Routes    |  | Webhook Routes  |  |
|  | (Inertia)         |  | (Sanctum/APIKey) |  | (HMAC-SHA256)   |  |
|  +--------+----------+  +--------+---------+  +--------+--------+  |
|           |                      |                      |          |
|  +--------v----------------------v----------------------v--------+ |
|  |                     MIDDLEWARE PIPELINE                        | |
|  |  ResolveTenant -> SsoSessionSync -> Auth -> Permission        | |
|  |  AuditTrailMiddleware -> ShareCalendarEvents                  | |
|  |  AuthenticateApiKey (API routes)                              | |
|  +---------------------------------------------------------------+ |
|           |                      |                      |          |
|  +--------v----------+  +-------v--------+  +----------v-------+  |
|  | Controllers       |  | Services       |  | Models (Eloquent)|  |
|  | - Uploads         |  | - CaseyMember  |  | - User           |  |
|  | - Dashboard       |  |   PolicyService|  | - Uploads        |  |
|  | - Submissions     |  | - CaseyRef     |  | - Company        |  |
|  | - Deadlines       |  |   DataService  |  | - Municipality   |  |
|  | - Admin/*         |  | - CaseyJwt     |  | - Audit          |  |
|  | - Auth/SSO        |  |   Service      |  | - UserAssignment |  |
|  | - CapsWebhook     |  | - SsoSession   |  | - Tenant         |  |
|  | - Notifications   |  |   Service      |  | - ApiKey         |  |
|  +-------------------+  | - TenantContext|  | - EventLog       |  |
|                          | - TenantResolv|  | - CapsWebhook    |  |
|                          |   erService   |  |   Event          |  |
|                          +-------+-------+  +--------+---------+  |
|                                  |                    |            |
+----------------------------------+--------------------+------------+
                                   |                    |
                    +--------------+----+        +------v------+
                    |   MySQL Database  |        | Local File  |
                    |   (Multi-tenant)  |        | Storage     |
                    +-------------------+        | (private)   |
                                                 +-------------+
```

### 3.2 Architectural Style

The Submission Tracker follows a **monolithic server-side application architecture** with the following characteristics:

| Aspect                | Pattern                                                                                |
|-----------------------|----------------------------------------------------------------------------------------|
| Server-side rendering | Inertia.js (server decides what to render; Vue handles the DOM)                        |
| Routing               | Server-side (Laravel routes), client-side navigation via Inertia Link                  |
| API layer             | RESTful JSON API (Sanctum-authenticated + API-key-authenticated partner endpoints)     |
| State management      | Server-authoritative (Inertia props); client-side reactivity via Vue Composition API   |
| External integration  | Service classes with HTTP clients (Laravel Http facade) calling CAPS REST endpoints    |
| Background processing | Laravel queue (sync driver in development, database/Redis in production)               |
| Multi-tenancy         | Shared database with `tenant_id` column and global Eloquent scopes                     |

### 3.3 Communication Patterns

```
+-------------------+         +-------------------+         +-------------------+
|   CAPS Frontend   |  SSO    | Casey SSO Service |  SSO    | Submission        |
|   (Next.js)       +-------->| (Node.js:4000)    |<--------+ Tracker (Laravel) |
|   Port 3000       |         |  In-memory store  |         | Port 8000         |
+--------+----------+         +-------------------+         +--------+----------+
         |                                                           |
         | JWT                                                       | HTTP GET/POST
         |                                                           | (Bearer JWT)
         v                                                           v
+--------+----------------------------------------------------------+-----------+
|                        CAPS Backend API (Spring Boot)                          |
|                        Port 9086                                               |
|                                                                                |
|  Endpoints:                                                                    |
|    /casey/auth/sign-in          (POST) - Authenticate, returns JWT            |
|    /v1/member/api/members       (GET)  - Member lookup by idNumber            |
|    /v1/premiums/status/fetch    (GET)  - Policy lookup by organizationId      |
|    /v1/organizations            (GET)  - Company/Municipality master data     |
|    /v1/webhooks/caps            (POST) - Webhook: CAPS -> Tracker             |
+-------------------------------------------------------------------------------+
```

---

## 4. Component Architecture

### 4.1 Component Catalogue

#### 4.1.1 Submission Tracker (Laravel Application)

| Property     | Detail                                                                         |
|-------------|---------------------------------------------------------------------------------|
| Runtime     | PHP 8.2+ on Apache/Nginx                                                       |
| Framework   | Laravel 12                                                                      |
| Port        | 8000 (development) / 80/443 (production)                                        |
| Entrypoints | `routes/web.php` (Inertia), `routes/api.php` (REST), `routes/console.php` (CLI)|

**Responsibilities:**
- Serve the Vue 3 SPA via Inertia.js server-side rendering.
- Handle file uploads, storage, and retrieval.
- Manage municipalities, companies, deadlines, and user assignments.
- Verify uploaded spreadsheet data against CAPS (members, policies, premiums).
- Receive and process CAPS webhook events.
- Enforce RBAC via Spatie permissions on every route.
- Maintain multi-tenant isolation at the data layer.
- Generate notifications, audit logs, and reports.

#### 4.1.2 Casey SSO Session Service (Node.js Microservice)

| Property     | Detail                                                |
|-------------|-------------------------------------------------------|
| Runtime     | Node.js / Express                                     |
| Port        | 4000                                                  |
| Storage     | In-memory session store (Map/Object)                  |
| Auth        | `X-SSO-Key` header (shared secret)                    |

**Responsibilities:**
- Act as a session registry shared between CAPS Frontend and Submission Tracker.
- Allow one system to detect when the other has logged in or out.
- Provide endpoints: `POST /sessions` (register), `GET /sessions/:employeeNumber` (check), `DELETE /sessions/:employeeNumber` (remove), `GET /sessions` (list all).

**API Contract:**

| Method   | Endpoint                          | Purpose                                  |
|----------|-----------------------------------|------------------------------------------|
| `POST`   | `/sessions`                       | Register a new SSO session               |
| `GET`    | `/sessions/:employeeNumber`       | Check if a session exists (200/404)      |
| `GET`    | `/sessions`                       | List all active sessions                 |
| `DELETE` | `/sessions/:employeeNumber`       | Remove a session on logout               |

#### 4.1.3 CAPS Backend API (Spring Boot - External)

| Property     | Detail                                                      |
|-------------|--------------------------------------------------------------|
| Runtime     | Java (Spring Boot)                                           |
| Port        | 9086                                                         |
| Auth        | JWT Bearer token (HS256) / Basic Auth fallback               |

**Consumed Endpoints:**

| Endpoint                              | Method | Parameters                              | Purpose                                       |
|---------------------------------------|--------|-----------------------------------------|-----------------------------------------------|
| `/casey/auth/sign-in`                 | POST   | `username`, `password`                  | Obtain a bearer token for API calls            |
| `/v1/member/api/members`              | GET    | `idNumber`, `page`, `size`              | Look up a member by SA ID number (exact match) |
| `/v1/premiums/status/fetch`           | GET    | `organizationId`, `page`, `size`        | Fetch policies for a deduction company         |
| `/v1/organizations` (municipalities)  | GET    | `active`                                | Fetch all municipality master data             |
| `/v1/organizations` (companies)       | GET    | `active`                                | Fetch all company master data                  |

#### 4.1.4 CAPS Frontend (Next.js - External)

| Property | Detail                                                                       |
|----------|------------------------------------------------------------------------------|
| Runtime  | Next.js / React                                                              |
| Port     | 3000                                                                         |
| Role     | Initiates SSO login by redirecting to Tracker's `/auth/casey-sso` endpoint   |

### 4.2 Controller Inventory

#### 4.2.1 Web Controllers (Inertia)

| Controller                        | Prefix          | Key Responsibilities                                          |
|-----------------------------------|-----------------|---------------------------------------------------------------|
| `DashboardController`             | `/dashboard`    | Render dashboard, recent uploads, stats, search               |
| `UploadsController`               | `/uploads`      | CRUD for uploads, file download/preview, CAPS comparison      |
| `SubmissionController`            | `/submissions`  | List and create submissions                                   |
| `MunicipalityDeadlineController`  | `/deadlines`    | Deadline CRUD, assignments, calendar events                   |
| `NotificationController`          | `/notifications`| List, mark-read, delete notifications                         |
| `CaseySsoController`             | `/auth/casey-sso`| SSO login, silent logout, auto-provisioning                   |
| `AuthenticatedSessionController`  | `/login`        | Local login form, credential authentication                   |

#### 4.2.2 Admin Controllers (Web)

| Controller                | Prefix                | Permission Guard          |
|---------------------------|-----------------------|---------------------------|
| `UserController`          | `/admin/users`        | `manage users`            |
| `RoleController`          | `/admin/roles`        | `manage roles`            |
| `PermissionController`    | `/admin/permissions`  | `manage permissions`      |
| `CompanyController`       | `/admin/companies`    | `view/manage companies`   |
| `MunicipalityController`  | `/admin/municipalities`| `view/manage municipalities`|
| `ReportController`        | `/admin/reports`      | `view reports`            |
| `AuditController`         | `/admin/audits`       | `view audits`             |
| `CapsDataSyncController`  | `/admin/caps-sync`    | `manage companies`        |

#### 4.2.3 API v1 Controllers

| Controller               | Prefix               | Auth                     |
|--------------------------|-----------------------|--------------------------|
| `RoleController`         | `/api/v1/roles`       | Sanctum                  |
| `CompanyController`      | `/api/v1/companies`   | Sanctum                  |
| `MunicipalityController` | `/api/v1/municipalities`| Sanctum                |
| `DeadlineController`     | `/api/v1/deadlines`   | Sanctum                  |
| `UploadController`       | `/api/v1/uploads`     | Sanctum                  |
| `TenantController`       | `/api/v1/tenants`     | Sanctum                  |
| `ApiKeyController`       | `/api/v1/api-keys`    | Sanctum                  |
| `WorkflowController`     | `/api/v1/workflows`   | Sanctum                  |
| `IntegrationController`  | `/api/v1/integrations`| Sanctum                  |
| `EventLogController`     | `/api/v1/events`      | Sanctum                  |
| `WebhookReplayController`| `/api/v1/webhooks/replay`| Sanctum               |
| `OpsController`          | `/api/v1/ops`         | Sanctum                  |
| `CapsWebhookController`  | `/api/v1/webhooks/caps`| HMAC-SHA256 (no Sanctum)|

#### 4.2.4 Partner API (API-Key Auth)

| Endpoint                                  | Auth Method       |
|-------------------------------------------|-------------------|
| `GET  /api/v1/partner/events`             | `X-API-Key` header|
| `POST /api/v1/partner/integrations/{id}/sync` | `X-API-Key` header|
| `POST /api/v1/partner/webhooks/replay/{id}`   | `X-API-Key` header|

### 4.3 Service Layer

| Service                        | Responsibility                                                                         |
|--------------------------------|----------------------------------------------------------------------------------------|
| `CaseyMemberPolicyService`    | Fetches members (by idNumber) and policies (by organizationId) from CAPS. Compares uploaded spreadsheet rows against CAPS data. Performs fuzzy company name resolution. |
| `CaseyReferenceDataService`   | Syncs municipality and company master data from CAPS into the local database. Upserts by `casey_id`, resolves area-to-municipality mappings. |
| `CaseyPremiumBatchService`    | Manages premium batch operations with CAPS.                                            |
| `CaseyJwtService`             | Verifies HS256 JWTs issued by CAPS. Zero-dependency implementation (no JWT library).   |
| `SsoSessionService`           | HTTP client for the Casey SSO Session microservice. Registers, checks, lists, and removes sessions. |
| `TenantContext`               | Singleton holding the current tenant for the request lifecycle.                         |
| `TenantResolverService`       | Resolves tenant from request: `X-Tenant` header > hostname > `user.tenant_id` > `default`. |
| `EventTimelineService`        | Aggregates events into a unified timeline view.                                        |
| `WorkflowEngineService`       | Executes workflow definitions against entity instances.                                 |
| `IntegrationManager`          | Orchestrates integration adapters (M365 Mail, S3, etc.).                               |

### 4.4 Middleware Pipeline

The request lifecycle passes through the following middleware (in order):

```
Incoming Request
       |
       v
+------------------+
| ResolveTenant    |   Resolves tenant from X-Tenant header / hostname /
|                  |   user.tenant_id / default slug
+--------+---------+
         |
         v
+------------------+
| SsoSessionSync   |   Bidirectional SSO: detects remote login/logout
|                  |   via the SSO microservice (15-second throttle)
+--------+---------+
         |
         v
+------------------+
| Auth (Laravel)   |   Standard authentication guard
+--------+---------+
         |
         v
+------------------+
| Permission       |   Spatie permission middleware (per-route)
| (Spatie)         |   e.g. 'permission:view uploads'
+--------+---------+
         |
         v
+------------------+
| AuditTrail       |   Records audit entries for mutations
| Middleware       |
+--------+---------+
         |
         v
+------------------+
| ShareCalendar    |   Shares upcoming calendar events with
| Events           |   every Inertia response
+--------+---------+
         |
         v
     Controller
```

For API routes, `AuthenticateApiKey` middleware replaces the standard Auth middleware and sets the tenant context from the API key's `tenant_id`.

---

## 5. Data Architecture

### 5.1 Entity-Relationship Overview

```
+-------------------+       +-------------------+       +-------------------+
|     tenants       |       |  tenant_domains   |       | tenant_settings   |
|-------------------|       |-------------------|       |-------------------|
| id (PK)           |<------| tenant_id (FK)    |       | tenant_id (FK,UQ) |
| name              |       | domain (UQ)       |       | branding_json     |
| slug (UQ)         |       | is_primary        |       | security_json     |
| status            |       +-------------------+       | workflow_json     |
| plan              |                                   +-------------------+
| billing_customer_id|
+--------+----------+
         |
         | tenant_id (FK, nullable) on all core tables
         |
+--------v-----------------------------------------------------------+
|                                                                     |
|  +----------------+     +------------------+     +----------------+ |
|  | users          |     | municipalities   |     | companies      | |
|  |----------------|     |------------------|     |----------------| |
|  | id (PK)        |     | id (PK)          |     | id (PK)        | |
|  | tenant_id (FK) |     | tenant_id (FK)   |     | tenant_id (FK) | |
|  | name           |     | name             |     | name           | |
|  | email (UQ)     |     | province         |     | registration_no| |
|  | employee_number|     | code             |     | status         | |
|  | password       |     | casey_id         |     | contact_email  | |
|  | external_pwd_  |     | casey_synced_at  |     | municipality_id| |
|  |   hash         |     +--------+---------+     | casey_id       | |
|  | phone          |              |                | casey_synced_at| |
|  | department     |              |                +-------+--------+ |
|  | position       |              |                        |          |
|  | is_active      |              |                        |          |
|  | last_login_at  |     +--------v---------+              |          |
|  | last_login_ip  |     | municipality_    |              |          |
|  +-------+--------+     |   deadlines      |              |          |
|          |               |------------------|              |          |
|          |               | id (PK)          |              |          |
|          |               | tenant_id (FK)   |              |          |
|          |               | municipality_id  |              |          |
|          |               | deadline_date    |              |          |
|          |               | notes            |              |          |
|          |               +------------------+              |          |
|          |                                                 |          |
|  +-------v-------------------------------------------------v--------+|
|  | user_assignments                                                  ||
|  |-------------------------------------------------------------------|
|  | id (PK)                                                           ||
|  | tenant_id (FK)                                                    ||
|  | user_id (FK) -----> users.id                                      ||
|  | municipality_id (FK) -----> municipalities.id                     ||
|  | company_id (FK, nullable) -----> companies.id                     ||
|  | deadline_date                                                     ||
|  | notes                                                             ||
|  +-------------------------------------------------------------------+|
|                                                                      |
|  +-------------------------------------------------------------------+|
|  | uploads                                                           ||
|  |-------------------------------------------------------------------|
|  | id (PK)                                                           ||
|  | tenant_id (FK)                                                    ||
|  | reference (UQ)                                                    ||
|  | company_id (FK) -----> companies.id                               ||
|  | municipality_id (FK) -----> municipalities.id                     ||
|  | user_id (FK) -----> users.id                                      ||
|  | status ENUM('Pending','Processing','Completed','Rejected')        ||
|  | original_file_path (JSON array)                                   ||
|  | original_file_names (JSON array)                                  ||
|  | workings_file_path                                                ||
|  | workings_file_name                                                ||
|  | systems_import_file_path                                          ||
|  | systems_import_file_name                                          ||
|  | converted_eml_paths (JSON array)                                  ||
|  | extracted_dates (JSON array)                                      ||
|  | system_import_date                                                ||
|  | submitted_at                                                      ||
|  | reupload_reason_type                                              ||
|  | reupload_reason_note                                              ||
|  | caps_payment_batch_id (indexed)                                   ||
|  | caps_status                                                       ||
|  | caps_status_detail                                                ||
|  | caps_last_webhook_at                                              ||
|  | caps_verification (JSON)                                          ||
|  | caps_verified_at                                                  ||
|  +-------------------------------------------------------------------+|
|                                                                      |
|  +-------------------------------------------------------------------+|
|  | audits                                                            ||
|  |-------------------------------------------------------------------|
|  | id (PK)                                                           ||
|  | tenant_id (FK)                                                    ||
|  | user_type (morph)                                                 ||
|  | user_id (morph)                                                   ||
|  | event (string: created/updated/deleted)                           ||
|  | auditable_type (morph)                                            ||
|  | auditable_id (morph)                                              ||
|  | old_values (JSON)                                                 ||
|  | new_values (JSON)                                                 ||
|  | url                                                               ||
|  | ip_address                                                        ||
|  | user_agent                                                        ||
|  | tags                                                              ||
|  +-------------------------------------------------------------------+|
+----------------------------------------------------------------------+
```

### 5.2 Database Table Specifications

#### 5.2.1 `tenants`

The root table for multi-tenancy. Every core table references this via a nullable `tenant_id` foreign key.

| Column                | Type         | Constraints        | Description                          |
|-----------------------|--------------|--------------------|--------------------------------------|
| `id`                  | BIGINT       | PK, AUTO_INCREMENT | Surrogate key                        |
| `name`                | VARCHAR(255) | NOT NULL           | Display name                         |
| `slug`                | VARCHAR(255) | UNIQUE, NOT NULL   | URL-safe identifier                  |
| `status`              | VARCHAR(255) | DEFAULT 'active'   | `active`, `suspended`, `archived`    |
| `plan`                | VARCHAR(255) | DEFAULT 'starter'  | Billing plan tier                    |
| `billing_customer_id` | VARCHAR(255) | NULLABLE           | External billing system reference    |
| `created_at`          | TIMESTAMP    |                    |                                      |
| `updated_at`          | TIMESTAMP    |                    |                                      |

#### 5.2.2 `tenant_domains`

Maps hostnames to tenants for hostname-based tenant resolution.

| Column       | Type         | Constraints                  | Description                    |
|-------------|--------------|------------------------------|--------------------------------|
| `id`        | BIGINT       | PK, AUTO_INCREMENT           |                                |
| `tenant_id` | BIGINT       | FK -> tenants.id, CASCADE    |                                |
| `domain`    | VARCHAR(255) | UNIQUE                       | Fully qualified domain name    |
| `is_primary`| BOOLEAN      | DEFAULT false                | Primary domain flag            |

#### 5.2.3 `tenant_settings`

Per-tenant configuration stored as JSON columns for flexibility.

| Column           | Type   | Constraints                  | Description                              |
|-----------------|--------|------------------------------|------------------------------------------|
| `id`            | BIGINT | PK                           |                                          |
| `tenant_id`     | BIGINT | FK -> tenants.id, UNIQUE     | One-to-one with tenant                   |
| `branding_json` | JSON   | NULLABLE                     | Logo, colours, labels                    |
| `security_json` | JSON   | NULLABLE                     | Password policy, MFA settings            |
| `workflow_json`  | JSON   | NULLABLE                     | Default workflow configuration           |

#### 5.2.4 `users`

| Column                   | Type         | Constraints            | Description                                    |
|--------------------------|--------------|------------------------|------------------------------------------------|
| `id`                     | BIGINT       | PK, AUTO_INCREMENT     |                                                |
| `tenant_id`              | BIGINT       | FK -> tenants.id, NULL | Tenant scope                                   |
| `name`                   | VARCHAR(255) | NOT NULL               | Display name                                   |
| `email`                  | VARCHAR(255) | UNIQUE                 | Email address                                  |
| `employee_number`        | VARCHAR(255) | NULLABLE               | Canonical identifier for SSO (`sub` claim)     |
| `password`               | VARCHAR(255) | NOT NULL               | Bcrypt hash (random for SSO-provisioned users) |
| `external_password_hash` | VARCHAR(255) | NULLABLE               | Hash from external system                      |
| `phone`                  | VARCHAR(255) | NULLABLE               | Contact phone                                  |
| `department`             | VARCHAR(255) | NULLABLE               | Organisational department                      |
| `position`               | VARCHAR(255) | NULLABLE               | Job title                                      |
| `is_active`              | BOOLEAN      | DEFAULT true           | Account activation status                      |
| `last_login_at`          | TIMESTAMP    | NULLABLE               | Last successful login timestamp                |
| `last_login_ip`          | VARCHAR(45)  | NULLABLE               | Last login IP address                          |
| `email_verified_at`      | TIMESTAMP    | NULLABLE               |                                                |
| `remember_token`         | VARCHAR(100) | NULLABLE               |                                                |

**Roles and Permissions:** Managed via Spatie `model_has_roles`, `model_has_permissions`, `role_has_permissions` pivot tables (migration: `create_permission_tables`).

#### 5.2.5 `companies`

| Column                | Type         | Constraints                           | Description                              |
|-----------------------|--------------|---------------------------------------|------------------------------------------|
| `id`                  | BIGINT       | PK, AUTO_INCREMENT                    |                                          |
| `tenant_id`           | BIGINT       | FK -> tenants.id, NULLABLE            |                                          |
| `name`                | VARCHAR(255) | NOT NULL                              | Company display name                     |
| `registration_number` | VARCHAR(255) | NULLABLE                              | Business registration number             |
| `status`              | VARCHAR(255) | DEFAULT 'active'                      | `active` or `inactive`                   |
| `contact_email`       | VARCHAR(255) | NULLABLE                              |                                          |
| `municipality_id`     | BIGINT       | FK -> municipalities.id, NULLABLE     | Nullable; populated via CAPS area mapping|
| `casey_id`            | VARCHAR(255) | NULLABLE                              | CAPS-side organisation UUID              |
| `casey_synced_at`     | TIMESTAMP    | NULLABLE                              | Last successful sync timestamp           |

**Global Scope (`capsOnly`):** By default, only companies with a non-empty `casey_id` are returned by Eloquent queries. This excludes legacy seeded duplicates. Use `Company::withoutGlobalScope('capsOnly')` to bypass.

#### 5.2.6 `municipalities`

| Column            | Type         | Constraints                  | Description                          |
|-------------------|--------------|------------------------------|--------------------------------------|
| `id`              | BIGINT       | PK, AUTO_INCREMENT           |                                      |
| `tenant_id`       | BIGINT       | FK -> tenants.id, NULLABLE   |                                      |
| `name`            | VARCHAR(255) | NOT NULL                     | Municipality display name            |
| `province`        | VARCHAR(255) | NULLABLE                     | South African province               |
| `code`            | VARCHAR(255) | NULLABLE                     | Municipality code                    |
| `casey_id`        | VARCHAR(255) | NULLABLE                     | CAPS-side organisation UUID          |
| `casey_synced_at` | TIMESTAMP    | NULLABLE                     | Last successful sync timestamp       |

**Global Scope (`capsOnly`):** Same as companies -- only CAPS-synced rows by default.

#### 5.2.7 `uploads`

The central entity. Stores a single submission comprising up to three file categories.

| Column                       | Type                                             | Description                                                 |
|------------------------------|--------------------------------------------------|-------------------------------------------------------------|
| `id`                         | BIGINT PK                                        |                                                             |
| `tenant_id`                  | BIGINT FK NULLABLE                               |                                                             |
| `reference`                  | VARCHAR UNIQUE                                   | Human-readable submission reference (auto-generated)        |
| `company_id`                 | BIGINT FK                                        | Which company this submission is for                        |
| `municipality_id`            | BIGINT FK                                        | Which municipality this submission targets                  |
| `user_id`                    | BIGINT FK                                        | Who uploaded this submission                                |
| `status`                     | ENUM: Pending, Processing, Completed, Rejected   | Workflow status                                             |
| `original_file_path`         | JSON array                                       | Paths to original email files (.eml/.msg)                   |
| `original_file_names`        | JSON array                                       | Original filenames as uploaded                              |
| `workings_file_path`         | VARCHAR                                          | Path to workings spreadsheet                                |
| `workings_file_name`         | VARCHAR                                          | Original filename of workings file                          |
| `systems_import_file_path`   | VARCHAR                                          | Path to systems import spreadsheet                          |
| `systems_import_file_name`   | VARCHAR                                          | Original filename of systems import file                    |
| `converted_eml_paths`        | JSON array                                       | Paths to MSG files converted to EML format                  |
| `extracted_dates`            | JSON array                                       | Dates extracted from uploaded files                         |
| `system_import_date`         | TIMESTAMP                                        | Date from the systems import file                           |
| `submitted_at`               | TIMESTAMP                                        | When the upload was submitted                               |
| `reupload_reason_type`       | VARCHAR NULLABLE                                 | Categorised reason for re-upload                            |
| `reupload_reason_note`       | TEXT NULLABLE                                    | Free-text re-upload reason                                  |
| `caps_payment_batch_id`      | VARCHAR NULLABLE, INDEXED                        | CAPS payment batch reference (populated via webhook)        |
| `caps_status`                | VARCHAR NULLABLE                                 | CAPS processing status (imported/allocated/failed/etc.)     |
| `caps_status_detail`         | TEXT NULLABLE                                    | CAPS error messages or status description                   |
| `caps_last_webhook_at`       | TIMESTAMP NULLABLE                               | Timestamp of last CAPS webhook for this upload              |
| `caps_verification`          | JSON NULLABLE                                    | Full CAPS comparison results (members, policies, premiums)  |
| `caps_verified_at`           | TIMESTAMP NULLABLE                               | When the CAPS verification was performed                    |

#### 5.2.8 `municipality_deadlines`

| Column            | Type   | Constraints               | Description                    |
|-------------------|--------|---------------------------|--------------------------------|
| `id`              | BIGINT | PK                        |                                |
| `tenant_id`       | BIGINT | FK NULLABLE               |                                |
| `municipality_id` | BIGINT | FK -> municipalities.id   | Which municipality             |
| `deadline_date`   | DATE   | NOT NULL                  | The submission deadline date   |
| `notes`           | TEXT   | NULLABLE                  | Admin notes                    |

#### 5.2.9 `user_assignments`

The pivot table that links users to municipality + company combinations with a deadline context.

| Column            | Type   | Constraints                       | Description                                |
|-------------------|--------|-----------------------------------|--------------------------------------------|
| `id`              | BIGINT | PK                                |                                            |
| `tenant_id`       | BIGINT | FK NULLABLE                       |                                            |
| `user_id`         | BIGINT | FK -> users.id                    | Assigned user                              |
| `municipality_id` | BIGINT | FK -> municipalities.id           | Assigned municipality                      |
| `company_id`      | BIGINT | FK -> companies.id, NULLABLE      | Assigned company (nullable for muni-level) |
| `deadline_date`   | DATE   | NULLABLE                          | Deadline context for this assignment       |
| `notes`           | TEXT   | NULLABLE                          |                                            |

#### 5.2.10 `audits`

Polymorphic audit trail for all auditable models.

| Column            | Type         | Description                                       |
|-------------------|--------------|---------------------------------------------------|
| `id`              | BIGINT PK    |                                                   |
| `tenant_id`       | BIGINT FK    |                                                   |
| `user_type`       | VARCHAR      | Polymorphic: the actor class                      |
| `user_id`         | BIGINT       | Polymorphic: the actor ID                         |
| `event`           | VARCHAR      | `created`, `updated`, `deleted`, auth events      |
| `auditable_type`  | VARCHAR      | Polymorphic: the entity class being audited       |
| `auditable_id`    | BIGINT       | Polymorphic: the entity ID                        |
| `old_values`      | JSON         | Previous attribute values                         |
| `new_values`      | JSON         | New attribute values                              |
| `url`             | TEXT         | Request URL that triggered the change             |
| `ip_address`      | VARCHAR(45)  | Client IP address                                 |
| `user_agent`      | VARCHAR(1023)| Browser user agent string                         |
| `tags`            | VARCHAR      | Optional categorisation tags                      |

#### 5.2.11 `caps_webhook_events`

Stores every inbound CAPS webhook for auditability and idempotency.

| Column                  | Type         | Description                                           |
|-------------------------|--------------|-------------------------------------------------------|
| `id`                    | BIGINT PK    |                                                       |
| `event_id`              | VARCHAR UQ   | CAPS event ID (or computed hash) for idempotency      |
| `event_type`            | VARCHAR      | e.g. `payment_batch.imported`, `refund.created`       |
| `payments_batch_id`     | VARCHAR IDX  | CAPS batch reference                                  |
| `submission_reference`  | VARCHAR IDX  | Tracker submission reference                          |
| `status`                | VARCHAR      | Mapped status value                                   |
| `payload`               | JSON         | Full webhook payload (raw)                            |
| `upload_id`             | BIGINT FK    | Resolved upload (nullable if unmatched)               |

#### 5.2.12 `api_keys`

Tenant-scoped API keys for partner access.

| Column         | Type          | Description                                  |
|----------------|---------------|----------------------------------------------|
| `id`           | BIGINT PK     |                                              |
| `tenant_id`    | BIGINT FK     |                                              |
| `name`         | VARCHAR       | Human-readable key name                      |
| `key_hash`     | VARCHAR(128)  | SHA-256 hash of the secret portion           |
| `scopes_json`  | JSON          | Array of allowed scopes (or `["*"]` for all) |
| `last_used_at` | TIMESTAMP     | Last API call timestamp                      |
| `revoked_at`   | TIMESTAMP     | Revocation timestamp (null = active)         |

#### 5.2.13 `event_log`

General-purpose event sourcing table for timeline reconstruction.

| Column         | Type     | Description                              |
|----------------|----------|------------------------------------------|
| `id`           | BIGINT PK|                                          |
| `tenant_id`    | BIGINT FK|                                          |
| `entity_type`  | VARCHAR  | e.g. `upload`, `deadline`, `user`        |
| `entity_id`    | BIGINT   | The entity this event relates to         |
| `event_type`   | VARCHAR  | Event classification                     |
| `payload_json` | JSON     | Event-specific data                      |
| `occurred_at`  | TIMESTAMP| When the event occurred                  |

#### 5.2.14 Additional Platform Tables

| Table                       | Purpose                                                    |
|-----------------------------|------------------------------------------------------------|
| `integration_connections`   | Third-party integration credentials and sync status        |
| `workflow_definitions`      | Configurable workflow step definitions (JSON)              |
| `workflow_instances`        | Active workflow instances tied to entities                  |
| `webhook_deliveries`        | Generic inbound webhook log with retry tracking            |
| `submissions`               | Legacy/alternate submission tracking                       |
| `personal_access_tokens`    | Laravel Sanctum token storage                              |
| `notifications`             | Laravel notification storage (database channel)            |

### 5.3 Key Relationships

```
User  --< UserAssignment >-- Municipality
User  --< UserAssignment >-- Company
User  --< Uploads
Municipality --< MunicipalityDeadline
Municipality --< Company (via municipality_id, nullable)
Municipality --< Uploads
Company --< Uploads
Uploads --< CapsWebhookEvent
Tenant --< User, Company, Municipality, Uploads, Audit, UserAssignment, ...
```

### 5.4 Data Integrity Rules

| Rule                                           | Enforcement                                          |
|------------------------------------------------|------------------------------------------------------|
| Upload reference uniqueness                    | Database UNIQUE constraint on `uploads.reference`    |
| User email uniqueness                          | Database UNIQUE constraint on `users.email`          |
| Tenant domain uniqueness                       | Database UNIQUE constraint on `tenant_domains.domain`|
| Webhook idempotency                            | UNIQUE constraint on `caps_webhook_events.event_id`  |
| API key uniqueness                             | UNIQUE constraint on `api_keys.key_hash`             |
| Tenant isolation                               | `BelongsToTenant` trait + global Eloquent scopes     |
| CAPS-synced-only scope                         | `capsOnly` global scope on Company and Municipality  |
| Foreign key cascades                           | `ON DELETE CASCADE` on tenant, company, municipality |

---

## 6. Integration Architecture

### 6.1 CAPS Reference Data Sync

**Service:** `CaseyReferenceDataService`

**Purpose:** Synchronise the canonical list of municipalities and companies from CAPS into the Tracker's local database. CAPS is treated as the system of record.

**Trigger:** Manual (admin button at `/admin/caps-sync`) or CLI command (`SyncCaseyReferenceData`).

**Flow:**

```
+--------------------+         +------------------+         +------------------+
| Admin / CLI        |  POST   | CapsDataSync     |  call   | CaseyReference   |
| triggers sync      +-------->| Controller       +-------->| DataService      |
+--------------------+         +------------------+         +--------+---------+
                                                                     |
                                                            1. Authenticate
                                                            2. GET municipalities
                                                            3. GET companies
                                                                     |
                                                                     v
                                                            +--------+---------+
                                                            | CAPS API         |
                                                            | (Spring Boot)    |
                                                            +--------+---------+
                                                                     |
                                                            Response: JSON array
                                                                     |
                                                                     v
                                                            +--------+---------+
                                                            | Upsert Logic     |
                                                            | - Match by       |
                                                            |   casey_id       |
                                                            | - Create or      |
                                                            |   update         |
                                                            | - Bump           |
                                                            |   casey_synced_at|
                                                            +--------+---------+
                                                                     |
                                                                     v
                                                            +--------+---------+
                                                            | MySQL            |
                                                            | municipalities   |
                                                            | companies        |
                                                            +------------------+
```

**Sync Algorithm:**

1. Fetch the CAPS municipalities endpoint. Normalise the JSON response (unwrap `data`/`content`/`items` envelopes).
2. For each row, extract `casey_id` from `id`/`orgId`/`organizationId`.
3. If a local municipality with that `casey_id` exists, update its attributes. Otherwise, create a new row.
4. Always update `casey_synced_at` to the current timestamp.
5. Repeat for companies. For each company, resolve the municipality link via the area-to-municipality map (`deductionCodes[i].areaId` -> `municipality.areaId` -> local `municipalities.id`).
6. Companies without a resolvable area are imported with `municipality_id = NULL`.

**Authentication Resolution Order:**

1. Logged-in user's SSO JWT (stored in `session('caps_jwt')`) -- so API calls act as the real user.
2. Cached service-account token (cached for 50 minutes).
3. Fresh service-account token (POST to `/casey/auth/sign-in` with configured credentials).
4. Basic Auth fallback (if token acquisition fails).

### 6.2 CAPS Member and Policy Verification

**Service:** `CaseyMemberPolicyService`

**Purpose:** Verify uploaded spreadsheet data against CAPS by checking member existence, policy existence, and premium accuracy.

**Trigger:** User action via `POST /uploads/{upload}/compare-caps`.

**Flow:**

```
Step 1: Parse Spreadsheet (PhpSpreadsheet)
        Extract per row: memberId (idNumber), personelNumber (payNumber),
        companyName, policyCode, premiumAmount

Step 2: Group Rows by Company Name
        Rows with companyName -> grouped by that name
        Rows without companyName -> "__fallback__" group

Step 3: Resolve Company Name -> casey_id
        Fuzzy matching strategy:
          a) Exact name match in companies table
          b) Base name match (strip trailing " - Suffix")
          c) LIKE '%name%' contains match
          d) First 3 significant words prefix match
          e) Fall back to the upload's company casey_id

Step 4: Fetch CAPS Data (per unique casey_id)
        Members: GET /v1/member/api/members?idNumber=X
                 One API call per unique SA ID number
                 Exact match, returns 0 or 1 result
        Policies: GET /v1/premiums/status/fetch?organizationId=X
                  Paginated (page=0, size=500, up to 60 pages / 30K policies)
                  All pages fetched for each company

Step 5: Index CAPS Data
        Members indexed by: idNumber, payNumber, personnelNumber (lowercase)
        Policies indexed by: policyCode/policyNumber/policyNo (lowercase)
        On duplicate policy codes, keep the entry with highest premium

Step 6: Compare Each Row
        Member check: Does idNumber or personnelNumber exist in CAPS index?
        Policy check: Does policyCode exist in CAPS index?
        Premium check: |CAPS premium - uploaded premium| > R0.01 = mismatch

Step 7: Store Results
        JSON stored on uploads.caps_verification
        Timestamp stored on uploads.caps_verified_at
```

**Comparison Result Schema (`caps_verification` JSON):**

```json
{
  "member_found": [ { "memberId": "...", "companyName": "...", ... } ],
  "member_not_found": [ { "memberId": "...", "companyName": "...", ... } ],
  "policy_found": [ { "policyCode": "...", ... } ],
  "policy_not_found": [ { "policyCode": "...", ... } ],
  "premium_mismatch": [
    {
      "policyCode": "...",
      "caps_premium": 150.00,
      "uploaded_premium": 175.50,
      ...
    }
  ],
  "caps_members_total": 42,
  "caps_policies_total": 1250,
  "uploaded_rows_total": 85,
  "caps_members_error": null,
  "caps_policies_error": null,
  "companies_resolved": 3
}
```

### 6.3 CAPS Webhook Integration (Status Echo-back)

**Controller:** `CapsWebhookController`

**Endpoint:** `POST /api/v1/webhooks/caps`

**Authentication:** HMAC-SHA256 signature verification (no Sanctum token required).

**Purpose:** Receive real-time status updates from CAPS when a payment batch changes state.

**Security Protocol:**

```
CAPS Server:
  signature = HMAC-SHA256(request_body, shared_secret)
  Header: X-Caps-Signature: <signature>

Tracker (verification):
  expected = HMAC-SHA256(raw_body, CAPS_WEBHOOK_SECRET)
  hash_equals(expected, X-Caps-Signature) must be true
```

**Event Type Mapping:**

| CAPS Event Type              | Mapped `caps_status` Value |
|------------------------------|----------------------------|
| `payment_batch.imported`     | `imported`                 |
| `payment_batch.allocated`    | `allocated`                |
| `payment_batch.failed`       | `failed`                   |
| `payment_batch.exported`     | `exported`                 |
| `refund.created`             | `refund_created`           |
| `refund.allocated`           | `refund_allocated`         |

**Processing Flow:**

1. Verify HMAC-SHA256 signature. Reject with 401 if invalid.
2. Parse payload: extract `eventId`, `event`/`eventType`, `paymentsBatchId`, `submissionReference`, `status`, `errors`.
3. Generate `eventId` if missing (MD5 hash of event type + batch ID + reference + occurred_at).
4. Check idempotency: if `caps_webhook_events` already has this `event_id`, return 200 without re-processing.
5. Resolve the matching upload: first by `caps_payment_batch_id`, then by `reference`.
6. Persist the event to `caps_webhook_events`.
7. Update the upload's `caps_status`, `caps_status_detail`, `caps_payment_batch_id`, and `caps_last_webhook_at`.
8. Return 200 with the event ID and resolved upload ID.

### 6.4 SSO Integration

**Flow: CAPS -> Tracker (Primary SSO Login)**

```
+----------------+    1. User clicks     +----------------+
| CAPS Frontend  |    "Open Tracker"     | User's Browser |
| (Next.js:3000) +-----------------------+                |
+-------+--------+                       +-------+--------+
        |                                        |
        | 2. Redirect with JWT                   |
        |    GET /auth/casey-sso?token=<jwt>      |
        |                                        |
        +------->---+----->---+----->---+--------+
                              |
                     +--------v--------+
                     | CaseySsoController|
                     |                  |
                     | 3. Verify JWT    |
                     |    (HS256,       |
                     |     shared       |
                     |     secret)      |
                     |                  |
                     | 4. Extract sub   |
                     |    (employee_no) |
                     |                  |
                     | 5. Find or       |
                     |    provision     |
                     |    user          |
                     |                  |
                     | 6. Auth::login() |
                     |                  |
                     | 7. Store JWT in  |
                     |    session       |
                     |    ('caps_jwt')  |
                     |                  |
                     | 8. Register in   |
                     |    SSO micro-    |
                     |    service       |
                     |                  |
                     | 9. Auto-sync     |
                     |    ref data if   |
                     |    empty         |
                     |                  |
                     | 10. Redirect to  |
                     |     dashboard    |
                     +--------+--------+
                              |
                     +--------v--------+
                     | SSO Microservice |
                     | (Node.js:4000)   |
                     | POST /sessions   |
                     +-----------------+
```

**Flow: Bidirectional Session Sync (Middleware)**

The `SsoSessionSync` middleware runs on every authenticated request (throttled to every 15 seconds):

- **Logged-in user, remote session gone (404):** Log the user out of the Tracker (remote logout detected).
- **Not logged in, active CAPS session found:** Redirect to `/auth/casey-sso` with the token (auto-login).
- **SSO microservice unreachable:** Do nothing (graceful degradation).

**Flow: Silent Logout**

When a user logs out of CAPS, the CAPS frontend loads a hidden iframe pointing to `/auth/casey-sso-logout`. This endpoint:

1. Destroys the Tracker session (`Auth::logout`, `session()->invalidate()`).
2. Returns a minimal HTML response with `window.close()`.

**JWT Verification (CaseyJwtService):**

- Algorithm: HS256 only (reject all others).
- Shared secret: Base64-encoded, configured as `CASEY_JWT_SHARED_SECRET` (mirrors `com.casey.supportal.jwt.token.secretkey` in CAPS).
- Claims validated: `exp` (with 30-second leeway), `nbf`, `iat`, `sub` (required, treated as employee number).
- Zero external dependencies (no JWT library).

**Auto-Provisioning:**

When `CASEY_SSO_AUTO_PROVISION=true` (default), users authenticating via SSO who have no Tracker account are automatically created with:
- Employee number from `sub` claim.
- Name from `name` / `given_name` + `family_name` claims.
- Email from `email` / `preferred_username` claims.
- Random 48-character password (never exposed).
- Default role from `CASEY_SSO_DEFAULT_ROLE` (default: `user`).

---

## 7. Security Architecture

### 7.1 Authentication Methods

| Method                    | Scope            | Mechanism                                                |
|---------------------------|------------------|----------------------------------------------------------|
| Local Login               | Web UI           | Employee number + password (bcrypt), Laravel session      |
| Casey SSO                 | Web UI           | CAPS JWT (HS256 verified), auto-login with session        |
| Laravel Sanctum           | API v1           | Bearer token (personal access tokens)                    |
| API Key                   | Partner API      | `X-API-Key` header, SHA-256 hash lookup in `api_keys`    |
| HMAC-SHA256               | CAPS Webhooks    | `X-Caps-Signature` header, shared secret verification    |

### 7.2 Authorization Model

Authorization is enforced via Spatie `laravel-permission` with per-route middleware:

```php
Route::get('/uploads', [UploadsController::class, 'index'])
    ->middleware('permission:view uploads');
```

**Permission Categories:**

| Category       | Permissions                                                               |
|----------------|---------------------------------------------------------------------------|
| Dashboard      | `view dashboard`                                                          |
| Uploads        | `view uploads`, `create upload`, `export uploads`                         |
| Submissions    | `view submissions`, `create submissions`                                  |
| Deadlines      | `view deadlines`, `create deadline`, `edit deadline`, `delete deadline`    |
| Companies      | `view companies`, `manage companies`                                      |
| Municipalities | `view municipalities`, `manage municipalities`                            |
| Users          | `manage users`                                                            |
| Roles          | `manage roles`                                                            |
| Permissions    | `manage permissions`                                                      |
| Reports        | `view reports`                                                            |
| Audits         | `view audits`                                                             |

**Data-Level Authorization:**

Beyond route-level permission checks, data access is scoped:

1. **Uploads:** `scopeAccessibleToUser` ensures users see only uploads linked to their assigned companies or municipalities. Admins bypass this.
2. **Municipalities:** `scopeAccessibleToUser` filters to municipalities the user is assigned to.
3. **Tenant isolation:** `BelongsToTenant` trait ensures all queries are scoped to the current tenant.

### 7.3 Credential Storage

| Credential Type         | Storage Method                                          |
|-------------------------|---------------------------------------------------------|
| User passwords          | Bcrypt hash (`password` cast in User model)             |
| External password hash  | Stored in `external_password_hash` (hidden attribute)   |
| API key secrets         | SHA-256 hash in `key_hash` (plaintext never stored)     |
| CAPS JWT shared secret  | Environment variable `CASEY_JWT_SHARED_SECRET` (base64) |
| CAPS webhook secret     | Environment variable `CAPS_WEBHOOK_SECRET`              |
| SSO API secret          | Environment variable `CASEY_SSO_API_SECRET`             |
| CAPS service credentials| Environment variables `CASEY_USERNAME`, `CASEY_PASSWORD` |
| Integration credentials | Encrypted JSON in `integration_connections.credentials_encrypted` |

### 7.4 Session Security

- Sessions are regenerated on login (`$request->session()->regenerate()`).
- Sessions are invalidated on logout (`$request->session()->invalidate()`).
- CSRF tokens are regenerated on logout (`$request->session()->regenerateToken()`).
- SSO JWT is stored in the server-side session (never exposed to the client).
- Silent SSO logout prevents session fixation from the CAPS side.

### 7.5 API Security

- **Sanctum-protected routes:** Require a valid bearer token in the `Authorization` header.
- **API-key-protected routes:** Require `X-API-Key` header. The key format is `prefix.secret`; only the secret portion is hashed and compared. Scope validation ensures the key has the required scope.
- **CAPS webhooks:** No bearer token required. Security is enforced via HMAC-SHA256 of the raw request body. The computed hash must match the `X-Caps-Signature` header (timing-safe comparison via `hash_equals`).
- **Rate limiting:** Partner API routes use the `throttle:tenant-api` middleware.

---

## 8. Multi-Tenancy Architecture

### 8.1 Strategy

The Submission Tracker uses a **shared database, shared schema** multi-tenancy model with row-level isolation via a `tenant_id` column on all core tables.

### 8.2 Tenant Resolution

The `TenantResolverService` resolves the active tenant for each request using the following priority chain:

```
Priority 1: X-Tenant HTTP Header (slug match)
    |
    | not found
    v
Priority 2: Hostname (via tenant_domains table)
    |
    | not found
    v
Priority 3: Authenticated User's tenant_id
    |
    | not found
    v
Priority 4: Default Tenant (slug = 'default')
```

**Implementation:** The `ResolveTenant` middleware runs early in the pipeline. It calls `TenantResolverService::resolveFromRequest()` and stores the result in the `TenantContext` singleton.

### 8.3 Tenant Data Isolation

**Trait: `BelongsToTenant`**

Applied to: `User`, `Company`, `Municipality`, `Uploads`, `UserAssignment`, `MunicipalityDeadline`, `Audit`.

Behaviour:
- **On create:** Automatically sets `tenant_id` from `TenantContext::tenantId()` if not already set.
- **Query scope:** Provides `scopeForTenant($query, $tenantId)` for explicit tenant filtering.

**Note:** The current implementation uses an explicit scope rather than a global scope for tenant isolation. This allows cross-tenant queries when needed (e.g., for platform-level admin operations).

### 8.4 Tenant-Scoped Tables

| Table                    | Has `tenant_id` | Notes                                        |
|--------------------------|-----------------|----------------------------------------------|
| `users`                  | Yes             |                                              |
| `companies`              | Yes             |                                              |
| `municipalities`         | Yes             |                                              |
| `uploads`                | Yes             |                                              |
| `audits`                 | Yes             |                                              |
| `user_assignments`       | Yes             |                                              |
| `municipality_deadlines` | Yes             |                                              |
| `submissions`            | Yes             |                                              |
| `notifications`          | Yes             |                                              |
| `api_keys`               | Yes             | FK to tenants                                |
| `event_log`              | Yes             | FK to tenants                                |
| `workflow_definitions`   | Yes             | FK to tenants                                |
| `workflow_instances`     | Yes             | FK to tenants                                |
| `integration_connections`| Yes             | FK to tenants                                |
| `webhook_deliveries`     | Yes             | FK to tenants                                |
| `tenant_settings`        | Yes             | One-to-one with tenants                      |
| `caps_webhook_events`    | No              | Cross-tenant (CAPS does not know tenant)     |

---

## 9. File Storage Architecture

### 9.1 Storage Configuration

| Property           | Value                                                    |
|--------------------|----------------------------------------------------------|
| Disk               | `private` (Laravel filesystem disk)                      |
| Driver             | `local`                                                  |
| Root directory     | `storage/app/private/` (outside web root)                |
| Maximum file size  | 10 MB per file                                           |
| Access control     | Files served via controller routes (not publicly linked) |

### 9.2 Directory Structure

```
storage/app/private/
  uploads/
    user-{userId}/
      municipality-{municipalityId}/
        company-{companyId}/
          {YYYY-MM-DD-HHMMSS}/
            original/
              email-001.eml
              email-002.msg
            workings/
              workings-spreadsheet.xlsx
            systems/
              systems-import.csv
            converted/
              email-002.eml          (MSG converted to EML)
```

### 9.3 File Categories

| Category          | Extensions           | Purpose                                        | Storage Column              |
|-------------------|----------------------|------------------------------------------------|-----------------------------|
| Original Emails   | `.eml`, `.msg`       | Original email correspondence from payroll      | `original_file_path` (JSON) |
| Workings          | `.xlsx`, `.csv`      | Working spreadsheet with calculations           | `workings_file_path`        |
| Systems Import    | `.xlsx`, `.csv`      | File ready for import into payroll system       | `systems_import_file_path`  |
| Converted EMLs    | `.eml`               | MSG files auto-converted to EML for previewing  | `converted_eml_paths` (JSON)|

### 9.4 File Access Control

Files are never served directly via a public URL. All file access goes through authenticated controller routes:

| Route Pattern                                  | Purpose                              |
|------------------------------------------------|--------------------------------------|
| `GET /uploads/{upload}/download/{which}/{index?}` | Download original/workings/systems file |
| `GET /uploads/{upload}/preview/{index?}`       | Inline preview (PDF-like view)       |
| `GET /uploads/{upload}/preview-data/{index?}`  | JSON spreadsheet data for modal      |
| `GET /uploads/{upload}/view-email/{index?}`    | Render parsed email view             |
| `GET /uploads/{upload}/view-email-data/{index?}`| JSON email data for rendering       |
| `GET /uploads/{upload}/convert-msg-to-eml/{index}`| Convert MSG to EML on demand      |

All routes require `permission:view uploads` and the user must be authenticated.

### 9.5 Upload Completion Flow

An upload goes through a multi-step completion process:

```
Step 1: Create Upload (POST /uploads)
        -> Status: 'Pending'
        -> Upload original email files (.eml/.msg)

Step 2: Complete Upload (POST /uploads/{id}/complete)
        -> Upload workings file (.xlsx/.csv)
        -> Upload systems import file (.xlsx/.csv)
        -> Parse and extract dates from spreadsheets
        -> Status: 'Completed' (if all files present)

Required files check (hasAllRequiredFiles):
  - original_file_path is not empty
  - workings_file_path is not empty
  - systems_import_file_path is not empty
```

---

## 10. API Design

### 10.1 Web API (Inertia Routes)

All web routes are served via Inertia.js. The server returns HTML on first load and JSON props on subsequent navigation. CSRF protection is enforced on all state-mutating requests.

**Route Groups:**

| Group          | Prefix           | Middleware                          | Routes |
|----------------|------------------|-------------------------------------|--------|
| Auth (guest)   | `/login`         | `guest`                             | 2      |
| SSO            | `/auth/casey-*`  | None (handles both guest and auth)  | 3      |
| Dashboard      | `/dashboard`     | `auth`, `permission:view dashboard` | 4      |
| Uploads        | `/uploads`       | `auth`, various permissions         | 15     |
| Submissions    | `/submissions`   | `auth`, `permission:view/create submissions` | 2 |
| Deadlines      | `/deadlines`     | `auth`, various permissions         | 18     |
| Notifications  | `/notifications` | `auth`                              | 5      |
| Admin          | `/admin`         | `auth`, various admin permissions   | 25+    |
| Logout         | `/logout`        | `auth`                              | 2      |

### 10.2 REST API v1

**Base URL:** `/api/v1`

**Authentication:** Bearer token (Sanctum) for internal routes; `X-API-Key` for partner routes; HMAC-SHA256 for webhook routes.

**Endpoint Summary:**

| Endpoint                                  | Method | Auth        | Description                                  |
|-------------------------------------------|--------|-------------|----------------------------------------------|
| `/api/v1/user`                            | GET    | Sanctum     | Current authenticated user                   |
| `/api/v1/roles`                           | GET    | Sanctum     | List roles                                   |
| `/api/v1/roles/{id}`                      | GET    | Sanctum     | Show role                                    |
| `/api/v1/companies`                       | GET    | Sanctum     | List companies                               |
| `/api/v1/companies/{id}`                  | GET    | Sanctum     | Show company                                 |
| `/api/v1/municipalities`                  | GET    | Sanctum     | List municipalities                          |
| `/api/v1/municipalities/{id}`             | GET    | Sanctum     | Show municipality                            |
| `/api/v1/deadlines`                       | GET    | Sanctum     | List deadlines                               |
| `/api/v1/deadlines/{id}`                  | GET    | Sanctum     | Show deadline                                |
| `/api/v1/uploads`                         | GET    | Sanctum     | List uploads                                 |
| `/api/v1/uploads/{id}`                    | GET    | Sanctum     | Show upload                                  |
| `/api/v1/uploads/premium-batch`           | GET    | Sanctum     | Premium batch detailed info                  |
| `/api/v1/tenants/current`                 | GET    | Sanctum     | Current tenant info                          |
| `/api/v1/tenants/current/settings`        | PATCH  | Sanctum     | Update tenant settings                       |
| `/api/v1/api-keys`                        | GET    | Sanctum     | List API keys                                |
| `/api/v1/api-keys`                        | POST   | Sanctum     | Create API key                               |
| `/api/v1/api-keys/{id}`                   | DELETE | Sanctum     | Revoke API key                               |
| `/api/v1/workflows`                       | GET    | Sanctum     | List workflows                               |
| `/api/v1/workflows`                       | POST   | Sanctum     | Create workflow                              |
| `/api/v1/workflows/{id}/publish`          | POST   | Sanctum     | Publish workflow                             |
| `/api/v1/workflows/{id}/instances`        | POST   | Sanctum     | Create workflow instance                     |
| `/api/v1/integrations`                    | GET    | Sanctum     | List integrations                            |
| `/api/v1/integrations/{provider}/connect` | POST   | Sanctum     | Connect integration                          |
| `/api/v1/integrations/{id}/sync`          | POST   | Sanctum     | Trigger integration sync                     |
| `/api/v1/integrations/{id}/health`        | GET    | Sanctum     | Integration health check                     |
| `/api/v1/events`                          | GET    | Sanctum     | List events                                  |
| `/api/v1/webhooks/replay/{id}`            | POST   | Sanctum     | Replay webhook                               |
| `/api/v1/ops/failed-jobs`                 | GET    | Sanctum     | List failed jobs                             |
| `/api/v1/ops/failed-jobs/{uuid}/retry`    | POST   | Sanctum     | Retry failed job                             |
| `/api/v1/webhooks/caps`                   | POST   | HMAC-SHA256 | CAPS webhook receiver                        |
| `/api/v1/partner/events`                  | GET    | API Key     | Partner: list events                         |
| `/api/v1/partner/integrations/{id}/sync`  | POST   | API Key     | Partner: trigger sync                        |
| `/api/v1/partner/webhooks/replay/{id}`    | POST   | API Key     | Partner: replay webhook                      |

### 10.3 Webhook Payload Schema (Inbound from CAPS)

```json
{
  "eventId": "uuid-string",
  "event": "payment_batch.imported",
  "paymentsBatchId": "uuid-string",
  "submissionReference": "SUB-2026-0001",
  "status": "imported",
  "errors": [],
  "message": "Batch imported successfully",
  "occurredAt": "2026-04-23T10:30:00Z"
}
```

**Required headers:**

| Header              | Value                                          |
|---------------------|------------------------------------------------|
| `Content-Type`      | `application/json`                             |
| `X-Caps-Signature`  | HMAC-SHA256 hex digest of the raw request body |

---

## 11. Workflow and Business Logic

### 11.1 Upload Lifecycle

```
                    +------------+
                    |  Created   |
                    |  (Draft)   |
                    +-----+------+
                          |
                   Upload original
                   email files
                          |
                    +-----v------+
                    |  Pending   |
                    +-----+------+
                          |
                   Upload workings +
                   systems import
                          |
                    +-----v------+
                    | Processing |
                    +-----+------+
                          |
              +-----------+-----------+
              |                       |
        All files OK            Rejected by
        CAPS verified           admin / CAPS
              |                       |
        +-----v------+        +------v-----+
        | Completed  |        | Rejected   |
        +-----+------+        +------+-----+
              |                       |
        CAPS webhook             Re-upload
        updates status           with reason
              |                       |
        +-----v------+        +------v-----+
        | imported / |        |  Pending   |
        | allocated /|        | (new cycle)|
        | failed ... |        +------------+
        +------------+
```

### 11.2 CAPS Verification Decision Matrix

| Condition                              | Result Category     | Action                                          |
|----------------------------------------|--------------------|-------------------------------------------------|
| Member ID found in CAPS                | `member_found`     | Row marked as verified                          |
| Member ID NOT found in CAPS            | `member_not_found` | Row flagged for review                          |
| Policy code found in CAPS              | `policy_found`     | Row marked as verified                          |
| Policy code NOT found in CAPS          | `policy_not_found` | Row flagged for review                          |
| Premium difference <= R0.01            | (no flag)          | Amounts match within tolerance                  |
| Premium difference > R0.01             | `premium_mismatch` | Row flagged with both CAPS and uploaded amounts |

### 11.3 Deadline and Assignment Model

```
Municipality
  |
  +--< MunicipalityDeadline (deadline_date, notes)
  |
  +--< UserAssignment
        |
        +-- user_id -> User
        +-- municipality_id -> Municipality
        +-- company_id -> Company (nullable, for company-specific assignments)
        +-- deadline_date (assignment-level deadline context)
```

Every company submits to every municipality. The `user_assignments` table determines which user is responsible for which municipality/company combination for a given deadline period.

### 11.4 Notification System

The application uses Laravel's database notification channel. Notification types:

| Notification Class           | Trigger                                        | Recipients                   |
|------------------------------|------------------------------------------------|------------------------------|
| `UploadCreated`              | New upload submitted                           | Assigned users and admins    |
| `NewUploadNotification`      | New upload (alternate notification)            | Related users                |
| `AssignmentCreated` (Admin)  | New user assignment created                    | Assigned user                |
| `AssignmentRemoved`          | User unassigned from municipality/company      | Previously assigned user     |
| `DeadlineCreated`            | New deadline created                           | Assigned users               |
| `DeadlineUpdated`            | Deadline date changed                          | Assigned users               |
| `DeadlineDeleted`            | Deadline removed                               | Previously assigned users    |
| `DeadlineAssigned`           | User assigned to a deadline                    | Assigned user                |
| `UserEditAction` (Admin)     | Admin modifies a user account                  | Admins                       |

---

## 12. Deployment Architecture

### 12.1 Deployment Topology

```
+------------------------------------------------------------------+
|                    PRODUCTION ENVIRONMENT                          |
|                                                                    |
|  +-----------------------------+  +-----------------------------+  |
|  |  Web Server (Nginx/Apache)  |  | Node.js Process (PM2)      |  |
|  |  Port 80/443 (TLS)         |  | Casey SSO Service           |  |
|  |                             |  | Port 4000                   |  |
|  |  Proxies to Laravel via     |  | In-memory session store     |  |
|  |  PHP-FPM (port 9000)       |  +-----------------------------+  |
|  +-------------+---------------+                                   |
|                |                                                   |
|  +-------------v---------------+  +-----------------------------+  |
|  |  PHP-FPM                    |  | Queue Worker (Optional)     |  |
|  |  Laravel Application        |  | php artisan queue:work      |  |
|  |  Port 9000 (FastCGI)       |  | Processes background jobs   |  |
|  +-------------+---------------+  +-----------------------------+  |
|                |                                                   |
|  +-------------v---------------+  +-----------------------------+  |
|  |  MySQL Database             |  | Local File Storage          |  |
|  |  Port 3306                  |  | /var/www/app/storage/app/   |  |
|  |  (Dedicated or RDS)         |  |   private/uploads/          |  |
|  +-----------------------------+  +-----------------------------+  |
|                                                                    |
+------------------------------------------------------------------+
                    |
                    | HTTPS (outbound)
                    v
+------------------------------------------------------------------+
|                    CAPS ENVIRONMENT                                |
|                                                                    |
|  +-----------------------------+  +-----------------------------+  |
|  | CAPS Backend API            |  | CAPS Frontend               |  |
|  | (Spring Boot)               |  | (Next.js)                   |  |
|  | Port 9086                   |  | Port 3000                   |  |
|  +-----------------------------+  +-----------------------------+  |
+------------------------------------------------------------------+
```

### 12.2 Service Port Map

| Service                     | Port  | Protocol | Notes                                       |
|-----------------------------|-------|----------|----------------------------------------------|
| Submission Tracker (Laravel)| 8000  | HTTP     | Development; 80/443 in production            |
| Casey SSO Microservice      | 4000  | HTTP     | Internal only; not exposed to public internet|
| CAPS Backend API            | 9086  | HTTPS    | External; TLS required                       |
| CAPS Frontend               | 3000  | HTTPS    | External; user-facing                        |
| MySQL                       | 3306  | TCP      | Internal only                                |
| PHP-FPM                     | 9000  | FastCGI  | Internal only                                |

### 12.3 Environment Variables

| Variable                    | Description                                              | Required |
|-----------------------------|----------------------------------------------------------|----------|
| `APP_KEY`                   | Laravel application encryption key                       | Yes      |
| `DB_HOST`, `DB_DATABASE`    | MySQL connection parameters                              | Yes      |
| `DB_USERNAME`, `DB_PASSWORD`| MySQL credentials                                        | Yes      |
| `CASEY_API_BASE_URL`        | CAPS API base URL (e.g., `https://caps.example.com:9086`)| Yes      |
| `CASEY_USERNAME`            | CAPS service account username                            | Yes      |
| `CASEY_PASSWORD`            | CAPS service account password                            | Yes      |
| `CASEY_JWT_SHARED_SECRET`   | Base64-encoded HS256 shared secret for JWT verification  | Yes      |
| `CASEY_SSO_ENABLED`         | Enable/disable SSO (`true`/`false`)                      | Yes      |
| `CASEY_SSO_AUTO_PROVISION`  | Auto-create user accounts on SSO login                   | No       |
| `CASEY_SSO_DEFAULT_ROLE`    | Default role for auto-provisioned users                  | No       |
| `CASEY_SSO_SERVICE_URL`     | SSO microservice URL (e.g., `http://localhost:4000`)     | Yes*     |
| `CASEY_SSO_API_SECRET`      | Shared secret for SSO microservice authentication        | Yes*     |
| `CAPS_WEBHOOK_SECRET`       | Shared secret for HMAC-SHA256 webhook verification       | Yes*     |
| `CASEY_VERIFY_SSL`          | Whether to verify CAPS API TLS certificates              | No       |
| `CASEY_TOKEN_CACHE_TTL`     | Minutes to cache CAPS auth tokens (default: 50)          | No       |

*Required when the respective feature is enabled.

### 12.4 CLI Commands

| Command                      | Purpose                                         |
|------------------------------|------------------------------------------------|
| `php artisan sync:casey`     | Sync companies and municipalities from CAPS     |
| `php artisan sync:permissions`| Synchronise permission definitions              |

### 12.5 Scheduled Tasks

| Schedule   | Task                        | Description                                   |
|------------|-----------------------------|-----------------------------------------------|
| Daily      | `sync:casey`                | Re-sync CAPS reference data                   |
| As needed  | `queue:work`                | Process queued notifications and jobs          |

---

## 13. Observability and Audit

### 13.1 Audit Trail

The `RecordsAuditTrail` trait is applied to the following models:

- `User`
- `Company`
- `Municipality`
- `MunicipalityDeadline`
- `Uploads`
- `UserAssignment`

Every `created`, `updated`, and `deleted` event on these models records:
- The acting user (polymorphic `user_type` + `user_id`).
- The affected entity (polymorphic `auditable_type` + `auditable_id`).
- Before and after values as JSON (`old_values`, `new_values`).
- Request context: URL, IP address, user agent.

The `AuditTrailMiddleware` provides request-level context injection.

### 13.2 Authentication Audit Events

The `AuditLogger::authEvent()` helper records:

| Event                     | When                                              |
|---------------------------|---------------------------------------------------|
| `logged_in`               | Successful local or SSO login                     |
| `logged_out`              | Manual logout, SSO silent logout, remote logout   |
| `failed_sso`              | SSO token verification failure                    |
| `provisioned_via_sso`     | New user auto-created during SSO                  |

### 13.3 CAPS Webhook Audit

Every inbound CAPS webhook is persisted to `caps_webhook_events` with the full raw payload. This provides:
- **Forensic auditability:** Every event CAPS has ever sent is stored.
- **Idempotency:** Duplicate events (same `event_id`) are detected and skipped.
- **Replay capability:** Events can be replayed via `POST /api/v1/webhooks/replay/{id}`.

### 13.4 Event Log

The `event_log` table provides a general-purpose event sourcing mechanism for timeline reconstruction and analytics. Events are tenant-scoped and indexed by entity type, entity ID, and event type.

### 13.5 Logging

Laravel's built-in logging (Monolog) is used throughout the application:

| Log Channel | Context                                                        |
|-------------|----------------------------------------------------------------|
| `default`   | Application-level events, errors, warnings                     |
| `info`      | CAPS sync results, member/policy fetch counts, SSO events      |
| `warning`   | CAPS API failures, SSO rejections, unresolved company names    |
| `error`     | CAPS API exceptions, webhook secret misconfiguration           |

---

## 14. Error Handling and Resilience

### 14.1 External Service Failure Modes

| Service                | Failure Mode          | Behaviour                                                |
|------------------------|-----------------------|----------------------------------------------------------|
| CAPS API               | HTTP 4xx/5xx          | Logged; operation fails with user-facing error message   |
| CAPS API               | Network timeout       | 30s timeout, 2 retries with 500ms delay                  |
| CAPS API               | Auth failure           | Falls back to Basic Auth; logged as warning              |
| SSO Microservice       | Unreachable           | Graceful degradation: no auto-login/logout detection     |
| SSO Microservice       | HTTP error (non-404)  | Treated as unreachable (no action taken)                 |
| MySQL                  | Connection failure    | Standard Laravel exception handling                      |

### 14.2 Retry Policies

| Operation                     | Retries | Delay   | Condition                      |
|-------------------------------|---------|---------|--------------------------------|
| CAPS API data fetch           | 2       | 500ms   | Any failure (non-throwing)     |
| CAPS Auth token acquisition   | 1       | 300ms   | Auth endpoint failure          |
| SSO Microservice calls        | 0       | N/A     | Fail fast (3s timeout)         |

### 14.3 Idempotency

- **Webhook processing:** `caps_webhook_events.event_id` UNIQUE constraint prevents duplicate processing.
- **Reference data sync:** Upsert by `casey_id` ensures repeated syncs are safe.
- **File uploads:** Unique directory paths (`YYYY-MM-DD-HHMMSS`) prevent filename collisions.

### 14.4 Data Consistency

- **Database transactions:** Reference data sync (`syncMunicipalities`, `syncCompanies`) wraps all upserts in a single `DB::transaction`.
- **Atomic upload completion:** The completion step updates multiple columns on the upload record in a single `save()`.
- **Cascading deletes:** Foreign key constraints with `CASCADE` ensure referential integrity when tenants, companies, or municipalities are removed.

---

## 15. Performance Considerations

### 15.1 Caching Strategy

| Cache Key Pattern                      | TTL     | Purpose                                    |
|----------------------------------------|---------|--------------------------------------------|
| `casey_api_token_{hash}`               | 50 min  | CAPS service-account bearer token          |

### 15.2 Query Optimization

- **Eager loading:** Controllers use `with()` to prevent N+1 queries (e.g., `with(['company:id,name', 'municipality:id,name'])`).
- **Global scopes:** `capsOnly` scope on Company and Municipality ensures non-CAPS rows are excluded at the database level, reducing result sets.
- **Indexed columns:** `caps_payment_batch_id`, `tenant_id`, `casey_id`, `event_id`, `key_hash`, and all foreign keys.
- **Pagination:** CAPS policy fetch uses `page`/`size` pagination (500 per page, up to 60 pages).
- **Throttled SSO checks:** The `SsoSessionSync` middleware throttles microservice calls to once every 15 seconds per session.

### 15.3 File Upload Limits

| Constraint         | Value    |
|--------------------|----------|
| Max file size      | 10 MB    |
| Allowed originals  | `.eml`, `.msg` |
| Allowed workings   | `.xlsx`, `.csv` |
| Allowed systems    | `.xlsx`, `.csv` |

### 15.4 CAPS API Call Volume

| Operation                         | Calls per Invocation                               |
|-----------------------------------|----------------------------------------------------|
| Member verification               | 1 call per unique SA ID number in the spreadsheet  |
| Policy verification               | N pages per unique company (500 policies/page)     |
| Reference data sync               | 2 calls (1 for municipalities, 1 for companies)   |
| Auth token acquisition            | 1 call (cached for 50 minutes)                     |

---

## 16. Appendices

### Appendix A: Vue Page Inventory

| Page Path                                    | Route Name                        | Description                         |
|----------------------------------------------|-----------------------------------|-------------------------------------|
| `Pages/Auth/Login.vue`                       | `login`                           | Local login form                    |
| `Pages/Dashboard.vue`                        | `dashboard`                       | Main dashboard with stats/calendar  |
| `Pages/Uploads/Index.vue`                    | `uploads.index`                   | Upload list and creation            |
| `Pages/Uploads/Complete.vue`                 | `uploads.complete`                | Upload completion form              |
| `Pages/Uploads/History.vue`                  | `uploads.history`                 | Upload history view                 |
| `Pages/Uploads/ViewSpreadsheet.vue`          | `uploads.preview`                 | Inline spreadsheet viewer           |
| `Pages/Uploads/ViewEmail.vue`                | `uploads.view-email`              | Parsed email viewer                 |
| `Pages/Submissions/Index.vue`                | `submissions.index`               | Submission list                     |
| `Pages/Deadlines/Municipalities.vue`         | `deadlines.municipalities.index`  | Municipality deadline management    |
| `Pages/Deadlines/Companies.vue`              | `deadlines.companies.index`       | Company deadline view               |
| `Pages/Deadlines/Municipalities/Index.vue`   | `deadlines.municipalities.index`  | Municipality deadline (alternate)   |
| `Pages/Notifications/Index.vue`              | `notifications.index`             | Notification centre                 |
| `Pages/Notifications/AssignNotification.vue` | -                                 | Assignment notification component   |
| `Pages/Settings/Index.vue`                   | -                                 | User settings                       |
| `Pages/Analytics/Index.vue`                  | -                                 | Analytics dashboard                 |
| `Pages/Admin/Users/Index.vue`                | `admin.users.index`               | User management                     |
| `Pages/Admin/Users/Edit.vue`                 | `admin.users.edit`                | Edit user                           |
| `Pages/Admin/Roles/Index.vue`                | `admin.roles.index`               | Role management                     |
| `Pages/Admin/Roles/Create.vue`               | `admin.roles.create`              | Create role                         |
| `Pages/Admin/Companies/Index.vue`            | `admin.companies.index`           | Company management                  |
| `Pages/Admin/Municipalities/Index.vue`       | `admin.municipalities.index`      | Municipality management              |
| `Pages/Admin/Reports/Index.vue`              | `admin.reports.index`             | Reports dashboard                   |
| `Pages/Admin/Audits/Index.vue`               | `admin.audits.index`              | Audit trail browser                 |
| `Pages/Admin/Audits/Show.vue`                | `admin.audits.show`               | Audit detail view                   |

### Appendix B: Eloquent Model Trait Usage

| Model                  | BelongsToTenant | RecordsAuditTrail | HasRoles | HasApiTokens | Notifiable |
|------------------------|:---------------:|:-----------------:|:--------:|:------------:|:----------:|
| `User`                 | Yes             | Yes               | Yes      | Yes          | Yes        |
| `Company`              | Yes             | Yes               | --       | --           | --         |
| `Municipality`         | Yes             | Yes               | --       | --           | --         |
| `Uploads`              | Yes             | Yes               | --       | --           | --         |
| `UserAssignment`       | Yes             | Yes               | --       | --           | --         |
| `MunicipalityDeadline` | Yes             | Yes               | --       | --           | --         |
| `Audit`                | Yes             | --                | --       | --           | --         |
| `Tenant`               | --              | --                | --       | --           | --         |
| `ApiKey`               | --              | --                | --       | --           | --         |
| `EventLog`             | --              | --                | --       | --           | --         |
| `CapsWebhookEvent`     | --              | --                | --       | --           | --         |

### Appendix C: CAPS Integration Configuration Reference

```dotenv
# CAPS API Connection
CASEY_API_BASE_URL=https://caps-api.example.com:9086
CASEY_USERNAME=tracker-service-account
CASEY_PASSWORD=<service-account-password>
CASEY_AUTH_ENDPOINT=/casey/auth/sign-in
CASEY_VERIFY_SSL=true
CASEY_TOKEN_CACHE_TTL=50

# CAPS Endpoints
CASEY_MEMBERS_ENDPOINT=/v1/member/api/members
CASEY_POLICIES_ENDPOINT=/v1/premiums/status/fetch
CASEY_MUNICIPALITIES_ENDPOINT=/v1/organizations/municipalities
CASEY_COMPANIES_ENDPOINT=/v1/organizations/companies

# CAPS SSO
CASEY_SSO_ENABLED=true
CASEY_SSO_AUTO_PROVISION=true
CASEY_SSO_DEFAULT_ROLE=user
CASEY_SSO_REDIRECT_ROUTE=dashboard
CASEY_JWT_SHARED_SECRET=<base64-encoded-hs256-secret>
CASEY_SSO_SERVICE_URL=http://localhost:4000
CASEY_SSO_API_SECRET=<sso-microservice-shared-secret>

# CAPS Webhooks
CAPS_WEBHOOK_SECRET=<hmac-sha256-shared-secret>

# Sync Configuration
CASEY_SYNC_ONLY_ACTIVE=true
CASEY_SYNC_DEFAULT_PROVINCE=Gauteng
```

### Appendix D: Glossary

| Term                   | Definition                                                                                          |
|------------------------|-----------------------------------------------------------------------------------------------------|
| CAPS                   | Casey Administration and Premium System -- the upstream system of record for members and policies.   |
| Casey                  | The product name for the CAPS ecosystem.                                                            |
| casey_id               | The unique identifier assigned to an entity (company/municipality) in CAPS.                         |
| Deduction File         | A payroll deduction file submitted by an employer to a municipality.                                |
| EML                    | Standard email message format (RFC 822).                                                            |
| MSG                    | Microsoft Outlook proprietary email format.                                                         |
| Municipality           | A South African local government entity that collects payroll deductions.                           |
| Organisation           | In CAPS terminology, either a municipality or a deduction company.                                  |
| Premium                | The monthly deduction amount for a member's insurance policy.                                       |
| SA ID Number           | South African national identity number (13 digits), used as the primary member identifier.          |
| SSO                    | Single Sign-On -- the mechanism allowing a user authenticated in CAPS to access the Tracker.        |
| Tenant                 | An isolated organisational unit within the multi-tenant platform.                                   |
| Tracker                | Short name for the Submission Tracker application.                                                  |
| Workings File          | A spreadsheet containing calculations and working data for a deduction submission.                  |
| Systems Import File    | A spreadsheet formatted for direct import into the municipality's payroll system.                   |

---

*End of Document*
