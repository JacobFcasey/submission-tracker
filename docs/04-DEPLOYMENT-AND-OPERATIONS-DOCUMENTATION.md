# Deployment and Operations Documentation

## Submission Tracker - Casey & Associates

**Document Version:** 1.0
**Date:** April 2026
**Classification:** Internal - Confidential
**Audience:** DevOps Engineers, System Administrators, On-Call Engineers

---

## Table of Contents

1. [Overview](#1-overview)
2. [Architecture Topology](#2-architecture-topology)
3. [Infrastructure Prerequisites](#3-infrastructure-prerequisites)
4. [Software Prerequisites](#4-software-prerequisites)
5. [Repository Structure](#5-repository-structure)
6. [Environment Configuration Reference](#6-environment-configuration-reference)
7. [Installation Procedure](#7-installation-procedure)
8. [Database Setup and Migrations](#8-database-setup-and-migrations)
9. [Frontend Build Pipeline](#9-frontend-build-pipeline)
10. [SSO Microservice Deployment](#10-sso-microservice-deployment)
11. [Startup and Shutdown Procedures](#11-startup-and-shutdown-procedures)
12. [Web Server Configuration (Production)](#12-web-server-configuration-production)
13. [Production Hardening Checklist](#13-production-hardening-checklist)
14. [SSL/TLS and HTTPS Configuration](#14-ssltls-and-https-configuration)
15. [Scheduled Tasks and Queue Workers](#15-scheduled-tasks-and-queue-workers)
16. [Health Checks and Readiness Probes](#16-health-checks-and-readiness-probes)
17. [Logging Architecture](#17-logging-architecture)
18. [Monitoring and Alerting](#18-monitoring-and-alerting)
19. [Backup Strategy](#19-backup-strategy)
20. [Disaster Recovery](#20-disaster-recovery)
21. [Scaling Considerations](#21-scaling-considerations)
22. [Troubleshooting Guide](#22-troubleshooting-guide)
23. [Runbook: Common Operational Tasks](#23-runbook-common-operational-tasks)
24. [Security Operations](#24-security-operations)
25. [Release and Deployment Workflow](#25-release-and-deployment-workflow)
26. [Appendix A: Complete .env Reference](#appendix-a-complete-env-reference)
27. [Appendix B: Port Allocation Map](#appendix-b-port-allocation-map)
28. [Appendix C: Artisan Command Reference](#appendix-c-artisan-command-reference)

---

## 1. Overview

### 1.1 Purpose

This document provides comprehensive deployment, configuration, and operational guidance for the Submission Tracker platform. It is intended to enable any qualified systems administrator to install, configure, run, monitor, back up, and recover the system from scratch.

### 1.2 System Description

The Submission Tracker is an enterprise web application managing the submission lifecycle for municipal payroll deduction files at Casey & Associates. The platform consists of the following components:

| Component | Technology | Default Port | Role |
|-----------|-----------|-------------|------|
| Submission Tracker | Laravel 12 + Vue 3 (Inertia.js) | 8000 | Primary application |
| Casey SSO Session Service | Node.js / Express | 4000 | JWT session management for CAPS SSO |
| MySQL Database | MySQL 8.0+ | 3306 | Persistent data store |
| CAPS Backend API | Spring Boot (external) | 9086 | Member/policy verification, reference data |
| CAPS Frontend | Next.js (external) | 3000 | CAPS user interface and SSO handoff |

### 1.3 Deployment Environments

| Environment | Purpose | APP_ENV | APP_DEBUG |
|------------|---------|---------|-----------|
| Local | Developer workstations | `local` | `true` |
| Staging | Pre-production verification | `staging` | `false` |
| Production | Live system | `production` | `false` |

---

## 2. Architecture Topology

```
                                    +-------------------+
                                    |   Load Balancer   |
                                    |   (HTTPS :443)    |
                                    +---------+---------+
                                              |
                   +--------------------------+-------------------------+
                   |                          |                         |
          +--------v--------+       +---------v---------+    +---------v---------+
          | Submission       |       | Casey SSO Session |    | CAPS Frontend     |
          | Tracker          |       | Service           |    | (Next.js)         |
          | Laravel 12       |       | Node.js/Express   |    | :3000             |
          | :8000            |       | :4000             |    | [EXTERNAL]        |
          +--------+---------+       +---------+---------+    +---------+---------+
                   |                           |                        |
                   |    +----------------------+                        |
                   |    |                                               |
          +--------v----v----+                                +---------v---------+
          |   MySQL 8.0+     |                                | CAPS Backend API  |
          |   :3306          |                                | Spring Boot       |
          +------------------+                                | :9086             |
                                                              | [EXTERNAL]        |
                                                              +-------------------+
```

**Data flow:**

1. Browser requests hit the Submission Tracker (Laravel) via HTTPS.
2. For SSO authentication, Laravel communicates with the Casey SSO Session Service on port 4000 to validate JWT sessions.
3. The SSO Session Service manages session state (active sessions, inactivity timeouts) independently of the Laravel session.
4. Laravel communicates with the CAPS Backend API on port 9086 for reference data sync (companies/municipalities), member verification, and policy verification.
5. The CAPS Frontend on port 3000 handles the SSO handoff - redirecting users with their JWT token to the Submission Tracker.
6. All persistent data (users, submissions, uploads, audit logs) is stored in MySQL.

---

## 3. Infrastructure Prerequisites

### 3.1 Hardware Requirements

#### Minimum (Development / Small Deployment)

| Resource | Specification |
|----------|--------------|
| CPU | 2 cores |
| RAM | 4 GB |
| Disk | 40 GB SSD |
| Network | 100 Mbps |

#### Recommended (Production)

| Resource | Specification |
|----------|--------------|
| CPU | 4+ cores |
| RAM | 8+ GB |
| Disk | 100+ GB SSD (expandable for file uploads) |
| Network | 1 Gbps |
| OS | Ubuntu 22.04 LTS / RHEL 9 / Windows Server 2022 |

### 3.2 Network Requirements

| Source | Destination | Port | Protocol | Purpose |
|--------|------------|------|----------|---------|
| Browser | Load Balancer | 443 | HTTPS | User access |
| Load Balancer | Submission Tracker | 8000 | HTTP | Reverse proxy |
| Load Balancer | SSO Service | 4000 | HTTP | Reverse proxy (if exposed) |
| Submission Tracker | MySQL | 3306 | TCP | Database |
| Submission Tracker | SSO Service | 4000 | HTTP | Session validation |
| Submission Tracker | CAPS API | 9086 | HTTPS | API integration |
| Browser | CAPS Frontend | 3000 | HTTPS | SSO handoff |
| CAPS API | Submission Tracker | 8000 | HTTPS | Webhook callbacks |

### 3.3 DNS Requirements

| Record | Example Value | Purpose |
|--------|--------------|---------|
| `tracker.casey.co.za` | Points to load balancer | Submission Tracker |
| `sso-session.casey.co.za` | Points to load balancer (optional) | SSO service (if publicly exposed) |
| `caps.casey.co.za` | Points to CAPS infrastructure | CAPS Frontend |
| `api.casey.co.za` | Points to CAPS infrastructure | CAPS Backend API |

---

## 4. Software Prerequisites

### 4.1 PHP 8.2+

Required PHP extensions:

| Extension | Purpose | Required |
|-----------|---------|----------|
| `bcmath` | Arbitrary precision mathematics | Yes |
| `ctype` | Character type checking | Yes |
| `fileinfo` | File information (MIME detection) | Yes |
| `json` | JSON encode/decode | Yes |
| `mbstring` | Multibyte string handling | Yes |
| `openssl` | Encryption and SSL/TLS | Yes |
| `pdo` | PHP Data Objects base | Yes |
| `pdo_mysql` | MySQL PDO driver | Yes |
| `tokenizer` | PHP token parsing | Yes |
| `xml` | XML parsing | Yes |
| `gd` | Image processing | Yes |
| `zip` | ZIP archive handling | Yes |
| `imap` | Email parsing (msg/eml uploads) | Yes |
| `curl` | HTTP client (Guzzle) | Yes |

**Verify installation:**

```bash
php -v
# Expected: PHP 8.2.x or higher

php -m | grep -E "bcmath|ctype|fileinfo|json|mbstring|openssl|pdo|pdo_mysql|tokenizer|xml|gd|zip|imap|curl"
```

**Ubuntu installation example:**

```bash
sudo apt update
sudo apt install -y php8.2-cli php8.2-fpm php8.2-mysql php8.2-bcmath \
    php8.2-ctype php8.2-fileinfo php8.2-mbstring php8.2-xml \
    php8.2-gd php8.2-zip php8.2-imap php8.2-curl php8.2-tokenizer
```

### 4.2 Node.js 20+

Required for:
- Building frontend assets (Vite + Vue 3)
- Running the Casey SSO Session Service

```bash
node -v
# Expected: v20.x.x or higher

npm -v
# Expected: 9.x.x or higher
```

**Installation via nvm (recommended):**

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
source ~/.bashrc
nvm install 20
nvm use 20
```

### 4.3 MySQL 8.0+

```bash
mysql --version
# Expected: mysql Ver 8.0.x or higher
```

**Key configuration recommendations for production (`/etc/mysql/mysql.conf.d/mysqld.cnf`):**

```ini
[mysqld]
innodb_buffer_pool_size = 1G          # 50-70% of available RAM
innodb_log_file_size = 256M
max_connections = 200
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
default-time-zone = '+00:00'
sql_mode = STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
```

### 4.4 Composer 2.x

```bash
composer --version
# Expected: Composer version 2.x.x
```

**Installation:**

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 4.5 Additional Tools

| Tool | Purpose | Required |
|------|---------|----------|
| `git` | Version control | Yes |
| `unzip` | Composer dependency extraction | Yes |
| `supervisord` | Process management (production) | Recommended |
| `certbot` | SSL certificate management | Recommended |
| `logrotate` | Log file rotation | Recommended |

---

## 5. Repository Structure

```
file_management/
+-- app/
|   +-- Console/Commands/       # Artisan commands (casey:sync-reference-data, permissions:sync)
|   +-- Http/Controllers/       # Request controllers
|   |   +-- Admin/              # Admin panel controllers
|   |   +-- Auth/               # Authentication controllers (SSO, login)
|   +-- Http/Middleware/        # Request middleware (tenant scoping, SSO)
|   +-- Models/                 # Eloquent models
|   +-- Services/               # Business logic services (CAPS API client, verification)
+-- bootstrap/cache/            # Framework cache (must be writable)
+-- casey-sso-service/          # SSO Session microservice (Node.js)
+-- config/                     # Laravel configuration files
+-- database/
|   +-- migrations/             # Database schema migrations
|   +-- seeders/                # Data seeders (RolePermissionSeeder, etc.)
+-- docs/                       # Project documentation
+-- public/                     # Web root (index.php, compiled assets)
+-- resources/
|   +-- css/                    # Source CSS (Tailwind)
|   +-- js/                     # Vue 3 components and pages
|   |   +-- Pages/              # Inertia page components
+-- routes/
|   +-- web.php                 # Web routes
|   +-- api.php                 # API routes
|   +-- console.php             # Scheduled tasks
+-- storage/
|   +-- app/private/uploads/    # Uploaded submission files
|   +-- logs/                   # Application logs
+-- .env.example                # Environment template
+-- composer.json               # PHP dependencies
+-- package.json                # Node.js dependencies
+-- vite.config.js              # Vite build configuration
```

---

## 6. Environment Configuration Reference

### 6.1 Submission Tracker (.env)

#### Application Core

| Variable | Description | Dev Default | Production Value |
|----------|-----------|-------------|-----------------|
| `APP_NAME` | Application display name | `FileManagement` | `SubmissionTracker` |
| `APP_ENV` | Environment identifier | `local` | `production` |
| `APP_KEY` | Encryption key (base64, 32 bytes) | _(generated)_ | _(unique per env)_ |
| `APP_DEBUG` | Enable debug mode and stack traces | `true` | **`false`** |
| `APP_URL` | Canonical application URL | `http://localhost:8000` | `https://tracker.casey.co.za` |

#### Database

| Variable | Description | Dev Default | Production Value |
|----------|-----------|-------------|-----------------|
| `DB_CONNECTION` | Database driver | `mysql` | `mysql` |
| `DB_HOST` | MySQL host | `127.0.0.1` | _(production host)_ |
| `DB_PORT` | MySQL port | `3306` | `3306` |
| `DB_DATABASE` | Database name | _(empty)_ | `submission_tracker` |
| `DB_USERNAME` | Database user | `root` | _(dedicated user)_ |
| `DB_PASSWORD` | Database password | _(empty)_ | _(strong password)_ |

#### Session and Cache

| Variable | Description | Dev Default | Production Value |
|----------|-----------|-------------|-----------------|
| `SESSION_DRIVER` | Session storage backend | `file` | `file` or `database` |
| `SESSION_LIFETIME` | Session duration (minutes) | `120` | `120` |
| `SESSION_ENCRYPT` | Encrypt session data | `false` | **`true`** |
| `SESSION_SECURE_COOKIE` | HTTPS-only cookies | _(unset)_ | **`true`** |
| `SESSION_PATH` | Cookie path | `/` | `/` |
| `SESSION_DOMAIN` | Cookie domain | `null` | `.casey.co.za` |
| `CACHE_STORE` | Cache backend | `file` | `file` or `redis` |
| `QUEUE_CONNECTION` | Queue backend | `sync` | `database` or `redis` |
| `BCRYPT_ROUNDS` | Password hashing cost | `12` | `12` |

#### CAPS API Integration (Layer 1 - Reference Data)

| Variable | Description | Dev Default | Production Value |
|----------|-----------|-------------|-----------------|
| `CASEY_API_BASE_URL` | CAPS API root URL | _(empty)_ | `https://api.casey.co.za` |
| `CASEY_API_USERNAME` | Service account username | _(dev user)_ | _(service account)_ |
| `CASEY_API_PASSWORD` | Service account password | _(dev pass)_ | _(strong password)_ |
| `CASEY_API_AUTH_ENDPOINT` | Auth endpoint path | `/casey/auth/sign-in` | `/casey/auth/sign-in` |
| `CASEY_API_VERIFY_SSL` | Verify SSL certificates | `true` | **`true`** |
| `CASEY_API_TOKEN_CACHE_TTL` | Token cache duration (minutes) | `50` | `50` |
| `CASEY_API_MUNICIPALITIES_ENDPOINT` | Municipalities sync endpoint | `/v1/admin/organization/municipalities` | `/v1/admin/organization/municipalities` |
| `CASEY_API_COMPANIES_ENDPOINT` | Companies sync endpoint | `/v1/admin/organization/companies` | `/v1/admin/organization/companies` |
| `CASEY_API_SYNC_ONLY_ACTIVE` | Only sync active records | `true` | `true` |
| `CASEY_API_SYNC_DEFAULT_PROVINCE` | Fallback province when CAPS omits it | _(empty)_ | _(optional)_ |

#### CAPS Premium Batch (Layer 2 - Verification)

| Variable | Description | Dev Default | Production Value |
|----------|-----------|-------------|-----------------|
| `CASEY_API_PREMIUM_BATCH_ENDPOINT` | Premium batch details endpoint | `/casey/v1/premiums/batch/detailed_info` | `/casey/v1/premiums/batch/detailed_info` |
| `CASEY_API_PREMIUM_BATCH_ID` | Default premium batch ID | `5239` | _(current batch)_ |

#### CAPS Single Sign-On (Layer 2 - SSO)

| Variable | Description | Dev Default | Production Value |
|----------|-----------|-------------|-----------------|
| `CASEY_SSO_ENABLED` | Enable SSO authentication | `false` | **`true`** |
| `CASEY_JWT_SHARED_SECRET` | HS256 shared secret (must match CAPS) | _(empty)_ | _(64+ char secret)_ |
| `CASEY_JWT_LEEWAY_SECONDS` | Clock skew tolerance | `30` | `30` |
| `CASEY_SSO_AUTO_PROVISION` | Auto-create users from SSO tokens | `true` | `true` |
| `CASEY_SSO_DEFAULT_ROLE` | Default role for provisioned users | `user` | `user` |
| `CASEY_SSO_REDIRECT_ROUTE` | Post-login redirect | `dashboard` | `dashboard` |
| `CASEY_SSO_AUTO_REDIRECT` | Auto-redirect unauthenticated users to CAPS | `true` | `true` |
| `CASEY_SSO_HANDOFF_URL` | CAPS SSO bridge page URL | _(empty)_ | `https://caps.casey.co.za/casey/auth/sso-bridge` |
| `CASEY_SSO_LOGOUT_URL` | CAPS logout bridge URL | _(empty)_ | `https://caps.casey.co.za/casey/auth/sso-logout` |
| `CASEY_SSO_SKIP_SECONDS` | Cooldown after failed SSO attempt | `60` | `60` |

#### Casey SSO Session Service Connection

| Variable | Description | Dev Default | Production Value |
|----------|-----------|-------------|-----------------|
| `CASEY_SSO_SERVICE_URL` | SSO microservice URL | `http://localhost:4000` | `http://localhost:4000` |
| `CASEY_SSO_API_SECRET` | Shared secret for Tracker-to-SSO API calls | _(empty)_ | _(64+ char secret)_ |

#### CAPS Webhook (Layer 3 - Status Echo-back)

| Variable | Description | Dev Default | Production Value |
|----------|-----------|-------------|-----------------|
| `CAPS_WEBHOOK_SECRET` | HMAC-SHA256 secret for webhook payloads | _(empty)_ | _(64+ char secret)_ |

#### Logging

| Variable | Description | Dev Default | Production Value |
|----------|-----------|-------------|-----------------|
| `LOG_CHANNEL` | Log channel | `daily` | `daily` |
| `LOG_STACK` | Stack channel driver | `single` | `single` |
| `LOG_DEPRECATIONS_CHANNEL` | Deprecation log channel | `null` | `null` |
| `LOG_LEVEL` | Minimum log level | `debug` | `warning` or `error` |

#### File Storage

| Variable | Description | Dev Default | Production Value |
|----------|-----------|-------------|-----------------|
| `FILESYSTEM_DISK` | Default filesystem disk | `local` | `local` or `s3` |

### 6.2 Casey SSO Session Service (.env)

The SSO microservice has its own `.env` file located at `casey-sso-service/.env`.

| Variable | Description | Default | Production Value |
|----------|-----------|---------|-----------------|
| `SSO_PORT` | Service listen port | `4000` | `4000` |
| `SSO_API_SECRET` | Shared secret (must match `CASEY_SSO_API_SECRET` in Tracker) | _(empty)_ | _(64+ char secret)_ |
| `SSO_SESSION_TTL_MS` | Maximum session lifetime (ms) | `28800000` (8 hours) | `28800000` |
| `SSO_INACTIVITY_TTL_MS` | Inactivity timeout (ms) | `600000` (10 minutes) | `600000` |

**Critical:** The `SSO_API_SECRET` value must be identical to the `CASEY_SSO_API_SECRET` value in the Submission Tracker `.env`.

---

## 7. Installation Procedure

### 7.1 Step-by-Step Installation

The following procedure assumes a clean Ubuntu 22.04 LTS server. Adapt paths and package management commands for other operating systems.

#### Step 1: Clone the Repository

```bash
cd /var/www
git clone <repository-url> submission-tracker
cd submission-tracker
```

#### Step 2: Install PHP Dependencies

```bash
# Production (no dev dependencies, optimised autoloader)
composer install --no-dev --optimize-autoloader

# Development (includes testing tools, linters)
composer install
```

**Expected output:** All packages install successfully. Verify with:

```bash
php artisan --version
# Expected: Laravel Framework 12.x.x
```

#### Step 3: Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

**Verify key generation:**

```bash
grep APP_KEY .env
# Expected: APP_KEY=base64:... (non-empty value)
```

#### Step 4: Edit Environment Configuration

Open `.env` in your preferred editor and configure all sections. At minimum, you must set:

```bash
# === REQUIRED for any environment ===
APP_URL=https://tracker.casey.co.za        # Your actual URL
DB_DATABASE=submission_tracker
DB_USERNAME=tracker_user
DB_PASSWORD=<strong-database-password>

# === REQUIRED for CAPS integration ===
CASEY_API_BASE_URL=https://api.casey.co.za
CASEY_API_USERNAME=<service-account>
CASEY_API_PASSWORD=<service-password>

# === REQUIRED for SSO ===
CASEY_SSO_ENABLED=true
CASEY_JWT_SHARED_SECRET=<shared-secret-from-caps-team>
CASEY_SSO_HANDOFF_URL=https://caps.casey.co.za/casey/auth/sso-bridge
CASEY_SSO_LOGOUT_URL=https://caps.casey.co.za/casey/auth/sso-logout
CASEY_SSO_SERVICE_URL=http://localhost:4000
CASEY_SSO_API_SECRET=<generate-a-64-char-secret>

# === REQUIRED for webhooks ===
CAPS_WEBHOOK_SECRET=<shared-secret-from-caps-team>
```

#### Step 5: Create the Database

```bash
mysql -u root -p <<'SQL'
CREATE DATABASE submission_tracker
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'tracker_user'@'localhost'
    IDENTIFIED BY '<strong-database-password>';

GRANT ALL PRIVILEGES ON submission_tracker.*
    TO 'tracker_user'@'localhost';

FLUSH PRIVILEGES;
SQL
```

#### Step 6: Run Database Migrations

```bash
php artisan migrate
```

**Expected output:** All migration tables created successfully. The system runs 23+ migrations covering:

- Core Laravel tables (users, cache, jobs)
- Domain tables (municipalities, companies, submissions, uploads)
- Supporting tables (deadlines, assignments, audits, notifications)
- Permission tables (Spatie)
- Integration columns (CAPS IDs, webhook statuses, verification data)
- Multi-tenant tables

#### Step 7: Seed Roles and Permissions

```bash
php artisan db:seed --class=RolePermissionSeeder
```

This creates the role hierarchy and assigns base permissions. Run this only once during initial setup.

#### Step 8: Sync Permission Definitions

```bash
php artisan permissions:sync
```

This ensures all permissions defined in the codebase are registered in the database. Safe to run multiple times (idempotent).

#### Step 9: Initial CAPS Reference Data Sync

```bash
php artisan casey:sync-reference-data
```

This pulls company and municipality master data from CAPS. Verify with:

```bash
php artisan tinker --execute="echo 'Companies: ' . \App\Models\Company::count() . ', Municipalities: ' . \App\Models\Municipality::count();"
```

#### Step 10: Build Frontend Assets

```bash
npm install
npm run build
```

**Expected output:** Vite produces compiled assets in `public/build/`. Verify:

```bash
ls -la public/build/manifest.json
# File should exist and be non-empty
```

#### Step 11: Install SSO Microservice

```bash
cd casey-sso-service
npm install
cp .env.example .env  # If an example exists; otherwise create .env manually
```

Edit `casey-sso-service/.env`:

```ini
SSO_PORT=4000
SSO_API_SECRET=<same-value-as-CASEY_SSO_API_SECRET-in-tracker>
SSO_SESSION_TTL_MS=28800000
SSO_INACTIVITY_TTL_MS=600000
```

#### Step 12: Set File Permissions

```bash
# Return to project root
cd /var/www/submission-tracker

# Storage and cache must be writable by web server
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Uploaded files directory
sudo mkdir -p storage/app/private/uploads
sudo chown -R www-data:www-data storage/app/private/uploads
```

#### Step 13: Verify Installation

```bash
php artisan about
```

This displays a summary of the Laravel installation including environment, cache drivers, database connection, and loaded packages. Confirm all values are correct.

---

## 8. Database Setup and Migrations

### 8.1 Migration Inventory

The system includes the following migrations (in execution order):

| Migration | Purpose |
|-----------|---------|
| `create_users_table` | Core user accounts |
| `create_cache_table` | Cache storage |
| `create_jobs_table` | Queue jobs, batches, failed jobs |
| `create_municipalities_table` | Municipality master data |
| `create_companies_table` | Company (deduction provider) master data |
| `create_submissions_table` | Monthly submission records |
| `create_uploads_table` | Individual file uploads |
| `create_municipality_deadlines_table` | Deadline scheduling |
| `create_permission_tables` | Spatie role/permission tables |
| `create_user_assignments_table` | User-to-municipality assignments |
| `create_audits_table` | Audit trail |
| `create_notifications_table` | User notifications |
| `add_user_id_to_uploads_table` | Upload ownership tracking |
| `add_reupload_reasons_to_uploads_table` | Reupload tracking |
| `create_personal_access_tokens_table` | Sanctum API tokens |
| `add_converted_eml_paths_to_uploads_table` | Email conversion tracking |
| `add_external_password_hash_to_users_table` | SSO user password bridge |
| `add_profile_columns_to_users_table` | Extended user profiles |
| `add_casey_id_to_companies_and_municipalities` | CAPS entity linking |
| `make_companies_municipality_id_nullable` | Multi-municipality company support |
| `add_caps_webhook_columns_to_uploads` | Webhook status tracking |
| `create_multi_tenant_core_tables` | Multi-tenancy infrastructure |
| `add_tenant_id_to_core_tables` | Tenant scoping columns |
| `add_caps_verification_to_uploads_table` | CAPS verification results |

### 8.2 Running Migrations

```bash
# Run all pending migrations
php artisan migrate

# Check migration status
php artisan migrate:status

# Rollback last batch (CAUTION: data loss)
php artisan migrate:rollback

# Reset and re-run all migrations (DANGER: destroys all data)
php artisan migrate:fresh
```

### 8.3 Seeders

| Seeder | Purpose | When to Run |
|--------|---------|-------------|
| `RolePermissionSeeder` | Creates roles and base permissions | Initial setup only |
| `MunicipalitiesSeeder` | Seeds local municipality data (if not using CAPS sync) | Optional, dev only |
| `CompaniesSeeder` | Seeds local company data (if not using CAPS sync) | Optional, dev only |
| `ExternalSystemUsersSeeder` | Creates system integration accounts | As needed |

```bash
# Seed roles and permissions (required)
php artisan db:seed --class=RolePermissionSeeder

# Run all seeders (development only)
php artisan db:seed
```

---

## 9. Frontend Build Pipeline

### 9.1 Technology Stack

- **Vue 3** - Component framework
- **Inertia.js** - Server-driven SPA bridge
- **Vite 7** - Build tool and dev server
- **Tailwind CSS 4** - Utility-first CSS
- **Ziggy** - Laravel route helper for JavaScript

### 9.2 Build Commands

```bash
# Development (with hot module reload on port 5173)
npm run dev

# Production build (generates public/build/)
npm run build

# Preview production build locally
npm run preview
```

### 9.3 Build Artefacts

After `npm run build`, the following are generated:

```
public/build/
+-- manifest.json     # Asset manifest for Laravel
+-- assets/
    +-- app-*.js      # Compiled JavaScript bundle
    +-- app-*.css     # Compiled CSS bundle
    +-- *.js          # Chunked vendor/page bundles
```

**Important:** In production, the `public/build/` directory must exist with compiled assets. Never run `npm run dev` in production -- it starts a dev server that is not suitable for serving traffic.

### 9.4 Key Frontend Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `@inertiajs/vue3` | ^2.1.5 | SPA page transitions |
| `@fullcalendar/vue3` | ^6.1.20 | Calendar view for deadlines |
| `@heroicons/vue` | ^2.2.0 | Icon library |
| `lucide-vue-next` | ^0.544.0 | Additional icons |
| `papaparse` | ^5.5.3 | CSV parsing |
| `read-excel-file` | ^6.0.1 | Excel file reading |
| `date-fns` | ^4.1.0 | Date manipulation |
| `ziggy-js` | ^2.6.0 | Laravel named routes in JS |

---

## 10. SSO Microservice Deployment

### 10.1 Overview

The Casey SSO Session Service is a lightweight Node.js/Express microservice that manages JWT session state. It acts as a session store for SSO tokens, tracking:

- Active sessions (creation, heartbeat, logout)
- Session expiry based on absolute TTL (8 hours default)
- Inactivity timeout (10 minutes default)

### 10.2 Installation

```bash
cd /var/www/submission-tracker/casey-sso-service
npm install --production
```

### 10.3 Configuration

Create `casey-sso-service/.env`:

```ini
SSO_PORT=4000
SSO_API_SECRET=<must-match-CASEY_SSO_API_SECRET>
SSO_SESSION_TTL_MS=28800000
SSO_INACTIVITY_TTL_MS=600000
```

### 10.4 Running

```bash
# Direct
cd /var/www/submission-tracker/casey-sso-service
node index.js
# or
npm start

# With process manager (recommended for production)
pm2 start index.js --name casey-sso-service
pm2 save
pm2 startup
```

### 10.5 Health Check

```bash
curl http://localhost:4000/health
# Expected: 200 OK with JSON status
```

### 10.6 Security Notes

- The SSO microservice should **not** be directly exposed to the internet
- It should only be accessible from the Submission Tracker application server
- All API calls to the SSO service are authenticated with the shared `SSO_API_SECRET`
- Bind to `127.0.0.1` rather than `0.0.0.0` in production if running on the same host

---

## 11. Startup and Shutdown Procedures

### 11.1 Startup Order

Services must be started in the following order to ensure proper dependency resolution:

```
1. MySQL Database
2. Casey SSO Session Service (port 4000)
3. Submission Tracker (port 8000)
4. Laravel Scheduler
5. Laravel Queue Worker (if QUEUE_CONNECTION != sync)
```

#### Development Startup

```bash
# Terminal 1: Start the SSO microservice
cd /var/www/submission-tracker/casey-sso-service
npm start

# Terminal 2: Start Laravel with Vite dev server
cd /var/www/submission-tracker
composer dev
# This runs concurrently: php artisan serve, queue:listen, pail, npm run dev

# Or manually:
# Terminal 2: Laravel dev server
php artisan serve

# Terminal 3: Vite dev server (HMR)
npm run dev

# Terminal 4: Queue worker
php artisan queue:listen --tries=1

# Terminal 5: Scheduler
php artisan schedule:work
```

#### Production Startup

```bash
# 1. Verify MySQL is running
sudo systemctl status mysql

# 2. Start SSO microservice
pm2 start casey-sso-service

# 3. Start/reload web server (Apache or Nginx)
sudo systemctl start nginx    # or apache2
sudo systemctl start php8.2-fpm

# 4. Start queue workers (via Supervisor)
sudo supervisorctl start tracker-worker:*

# 5. Verify scheduler is configured in crontab
crontab -l | grep artisan
# Expected: * * * * * cd /var/www/submission-tracker && php artisan schedule:run >> /dev/null 2>&1
```

### 11.2 Shutdown Order (Graceful)

Reverse of startup:

```bash
# 1. Stop queue workers gracefully
sudo supervisorctl stop tracker-worker:*

# 2. Stop/reload web server
sudo systemctl stop nginx
sudo systemctl stop php8.2-fpm

# 3. Stop SSO microservice
pm2 stop casey-sso-service

# 4. MySQL (only if performing maintenance)
sudo systemctl stop mysql
```

### 11.3 Restart Procedure

For zero-downtime restarts (configuration changes, code deployments):

```bash
# Reload PHP-FPM (picks up new code without dropping connections)
sudo systemctl reload php8.2-fpm

# Restart queue workers (to pick up new code)
php artisan queue:restart

# Restart SSO microservice
pm2 restart casey-sso-service
```

---

## 12. Web Server Configuration (Production)

### 12.1 Nginx Configuration

Create `/etc/nginx/sites-available/submission-tracker`:

```nginx
server {
    listen 80;
    server_name tracker.casey.co.za;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name tracker.casey.co.za;

    # SSL
    ssl_certificate     /etc/letsencrypt/live/tracker.casey.co.za/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tracker.casey.co.za/privkey.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers on;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Document root
    root /var/www/submission-tracker/public;
    index index.php;

    # Max upload size (adjust based on expected file sizes)
    client_max_body_size 64M;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript image/svg+xml;
    gzip_min_length 1000;

    # Static assets (Vite build output)
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Favicon and robots
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Deny access to hidden files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Deny access to sensitive files
    location ~ /\.(env|git|svn) {
        deny all;
    }

    # Access and error logs
    access_log /var/log/nginx/submission-tracker-access.log;
    error_log  /var/log/nginx/submission-tracker-error.log;
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/submission-tracker /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 12.2 Apache Configuration

For Apache with mod_rewrite and mod_ssl:

```apache
<VirtualHost *:80>
    ServerName tracker.casey.co.za
    Redirect permanent / https://tracker.casey.co.za/
</VirtualHost>

<VirtualHost *:443>
    ServerName tracker.casey.co.za
    DocumentRoot /var/www/submission-tracker/public

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/tracker.casey.co.za/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/tracker.casey.co.za/privkey.pem

    <Directory /var/www/submission-tracker/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

    # Max upload size
    LimitRequestBody 67108864

    ErrorLog  ${APACHE_LOG_DIR}/submission-tracker-error.log
    CustomLog ${APACHE_LOG_DIR}/submission-tracker-access.log combined
</VirtualHost>
```

### 12.3 PHP-FPM Pool Configuration

Create `/etc/php/8.2/fpm/pool.d/tracker.conf`:

```ini
[tracker]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; PHP settings
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
php_admin_value[max_execution_time] = 300
php_admin_value[memory_limit] = 256M
php_admin_value[max_input_vars] = 5000

; Log slow requests
request_slowlog_timeout = 10s
slowlog = /var/log/php/tracker-slow.log
```

---

## 13. Production Hardening Checklist

### 13.1 Mandatory Settings

Execute each item before going live:

| # | Item | Setting / Command | Verified |
|---|------|------------------|----------|
| 1 | Debug mode disabled | `APP_DEBUG=false` | [ ] |
| 2 | Production environment | `APP_ENV=production` | [ ] |
| 3 | Secure cookies | `SESSION_SECURE_COOKIE=true` | [ ] |
| 4 | Encrypted sessions | `SESSION_ENCRYPT=true` | [ ] |
| 5 | SSL verification enabled | `CASEY_API_VERIFY_SSL=true` | [ ] |
| 6 | APP_KEY is unique | `grep APP_KEY .env` (non-default) | [ ] |
| 7 | JWT shared secret is strong | `CASEY_JWT_SHARED_SECRET` (64+ chars) | [ ] |
| 8 | SSO API secret is strong | `CASEY_SSO_API_SECRET` (64+ chars) | [ ] |
| 9 | Webhook secret is strong | `CAPS_WEBHOOK_SECRET` (64+ chars) | [ ] |
| 10 | Log level appropriate | `LOG_LEVEL=warning` or `error` | [ ] |
| 11 | File permissions correct | `storage/` and `bootstrap/cache/` writable | [ ] |
| 12 | .env not web-accessible | Verify 403 for `/.env` | [ ] |

### 13.2 Cache Optimisation

Run these commands after every deployment:

```bash
# Cache configuration (must be re-run after .env changes)
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimise autoloader (already done if composer install --optimize-autoloader was used)
composer dump-autoload --optimize
```

**Clear caches** (when debugging or after configuration changes):

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### 13.3 Secret Generation

Generate cryptographically secure secrets:

```bash
# Generate a 64-character hex secret
openssl rand -hex 32

# Or generate a base64 secret
openssl rand -base64 48
```

Apply unique secrets to:
- `APP_KEY` (generated via `php artisan key:generate`)
- `CASEY_JWT_SHARED_SECRET` (must match CAPS configuration)
- `CASEY_SSO_API_SECRET` (must match `SSO_API_SECRET` in SSO microservice)
- `CAPS_WEBHOOK_SECRET` (must match CAPS webhook sender configuration)

### 13.4 PHP Configuration (php.ini)

```ini
; Production php.ini overrides
expose_php = Off
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log
max_execution_time = 300
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_input_vars = 5000
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

---

## 14. SSL/TLS and HTTPS Configuration

### 14.1 Certificate Acquisition (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d tracker.casey.co.za
```

### 14.2 Certificate Auto-Renewal

```bash
# Test renewal
sudo certbot renew --dry-run

# Certbot installs a systemd timer automatically; verify:
sudo systemctl status certbot.timer
```

### 14.3 Internal HTTPS (Between Services)

If the Submission Tracker and SSO microservice run on separate hosts, configure HTTPS between them:

- Use a private CA or internal certificates
- Set `CASEY_SSO_SERVICE_URL=https://sso-internal.casey.co.za:4000`
- Configure the SSO microservice with TLS certificates

For same-host deployments, `http://localhost:4000` is acceptable since traffic does not leave the machine.

---

## 15. Scheduled Tasks and Queue Workers

### 15.1 Laravel Scheduler

The scheduler is defined in `routes/console.php`:

| Task | Schedule | Description |
|------|----------|-------------|
| `casey:sync-reference-data` | Daily at 02:30 | Syncs companies and municipalities from CAPS |

**Configuration options:**
- `withoutOverlapping(30)` - Prevents concurrent runs; lock expires after 30 minutes
- `runInBackground()` - Does not block the scheduler worker
- Output appended to `storage/logs/casey-reference-data-sync.log`

#### Cron Setup (Production)

Add to the web server user's crontab:

```bash
sudo crontab -u www-data -e
```

Add:

```cron
* * * * * cd /var/www/submission-tracker && php artisan schedule:run >> /dev/null 2>&1
```

**Verify:**

```bash
sudo crontab -u www-data -l
```

#### Development

```bash
# Runs the scheduler in the foreground, checking every minute
php artisan schedule:work
```

### 15.2 Queue Workers

If `QUEUE_CONNECTION` is set to anything other than `sync`, you must run queue workers.

#### Supervisor Configuration (Production)

Create `/etc/supervisor/conf.d/tracker-worker.conf`:

```ini
[program:tracker-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/submission-tracker/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/tracker-worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start tracker-worker:*
```

#### Restarting Workers After Deployment

```bash
# Signal workers to finish current job then restart
php artisan queue:restart
```

**Important:** Always restart queue workers after deploying new code. Workers are long-running processes that load code once at startup.

---

## 16. Health Checks and Readiness Probes

### 16.1 Submission Tracker Health

#### Application Health

```bash
# Verify the application responds
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/login
# Expected: 200

# Check API route health
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/integrations/1/health
# Expected: 200 (if integration exists)
```

#### Database Connectivity

```bash
php artisan tinker --execute="DB::connection()->getPdo(); echo 'Database OK';"
# Expected: Database OK
```

#### Storage Writability

```bash
php artisan tinker --execute="file_put_contents(storage_path('health-check.tmp'), 'ok'); echo file_get_contents(storage_path('health-check.tmp')); unlink(storage_path('health-check.tmp'));"
# Expected: ok
```

### 16.2 SSO Microservice Health

```bash
curl -s http://localhost:4000/health
# Expected: 200 OK with JSON status body
```

### 16.3 MySQL Health

```bash
mysqladmin -u tracker_user -p ping
# Expected: mysqld is alive
```

### 16.4 CAPS API Reachability

```bash
curl -s -o /dev/null -w "%{http_code}" https://api.casey.co.za/casey/auth/sign-in
# Expected: 400 or 401 (endpoint reachable, no credentials provided)
# NOT expected: connection timeout or DNS failure
```

### 16.5 Automated Health Check Script

Create `/opt/scripts/health-check.sh`:

```bash
#!/bin/bash
set -e

TRACKER_URL="http://localhost:8000"
SSO_URL="http://localhost:4000"
MYSQL_USER="tracker_user"
MYSQL_PASS="<password>"

ERRORS=0

# Check Tracker
if ! curl -sf -o /dev/null "$TRACKER_URL/login"; then
    echo "FAIL: Submission Tracker not responding"
    ERRORS=$((ERRORS + 1))
else
    echo "OK: Submission Tracker"
fi

# Check SSO
if ! curl -sf -o /dev/null "$SSO_URL/health"; then
    echo "FAIL: SSO microservice not responding"
    ERRORS=$((ERRORS + 1))
else
    echo "OK: SSO microservice"
fi

# Check MySQL
if ! mysqladmin -u "$MYSQL_USER" -p"$MYSQL_PASS" ping &>/dev/null; then
    echo "FAIL: MySQL not responding"
    ERRORS=$((ERRORS + 1))
else
    echo "OK: MySQL"
fi

if [ $ERRORS -gt 0 ]; then
    echo "HEALTH CHECK FAILED: $ERRORS errors"
    exit 1
fi

echo "ALL HEALTH CHECKS PASSED"
exit 0
```

Schedule via cron:

```cron
*/5 * * * * /opt/scripts/health-check.sh >> /var/log/health-check.log 2>&1
```

---

## 17. Logging Architecture

### 17.1 Log Locations

| Log | Location | Rotation | Purpose |
|-----|----------|----------|---------|
| Laravel application | `storage/logs/laravel-YYYY-MM-DD.log` | Daily (auto) | Application errors, warnings, info |
| CAPS reference data sync | `storage/logs/casey-reference-data-sync.log` | Manual / logrotate | Sync operation output |
| Nginx access | `/var/log/nginx/submission-tracker-access.log` | logrotate | HTTP request log |
| Nginx error | `/var/log/nginx/submission-tracker-error.log` | logrotate | Web server errors |
| PHP-FPM | `/var/log/php/error.log` | logrotate | PHP runtime errors |
| PHP-FPM slow | `/var/log/php/tracker-slow.log` | logrotate | Slow request traces |
| Supervisor (queue) | `/var/log/supervisor/tracker-worker.log` | logrotate | Queue worker output |
| MySQL | `/var/log/mysql/error.log` | logrotate | Database errors |
| SSO microservice | stdout/stderr (captured by pm2) | pm2 log rotation | SSO session operations |
| Audit trail | Database (`audits` table) | N/A (queryable) | User action history |

### 17.2 Laravel Log Configuration

The application uses the `daily` log channel by default, producing date-stamped files:

```
storage/logs/
+-- laravel-2026-04-23.log
+-- laravel-2026-04-22.log
+-- casey-reference-data-sync.log
```

Log levels (in order of severity):

| Level | When Used |
|-------|-----------|
| `emergency` | System is unusable |
| `alert` | Immediate action required |
| `critical` | Critical conditions |
| `error` | Error conditions (exceptions, failed API calls) |
| `warning` | Warning conditions (deprecations, slow queries) |
| `notice` | Normal but significant events |
| `info` | Informational (sync progress, login events) |
| `debug` | Debug-level detail (request/response payloads) |

**Production recommendation:** Set `LOG_LEVEL=warning` to reduce noise while still capturing actionable issues.

### 17.3 Log Rotation (logrotate)

Create `/etc/logrotate.d/submission-tracker`:

```
/var/www/submission-tracker/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0664 www-data www-data
    sharedscripts
}
```

### 17.4 Audit Trail

The application maintains a comprehensive audit trail in the `audits` database table, accessible at the admin audits page (`/admin/audits`). Audited events include:

- User authentication (login, logout, SSO authentication)
- Data creation, modification, and deletion
- File uploads and re-uploads
- Role and permission changes
- CAPS verification actions
- Submission status changes

---

## 18. Monitoring and Alerting

### 18.1 Key Metrics to Monitor

#### Application Metrics

| Metric | Source | Alert Threshold |
|--------|--------|----------------|
| HTTP 5xx error rate | Nginx access log | > 1% of requests over 5 minutes |
| Response time (P95) | Nginx access log | > 2 seconds |
| PHP-FPM active processes | php-fpm status page | > 80% of `pm.max_children` |
| Queue job failures | `failed_jobs` table | Any new entries |
| Disk usage (storage) | OS metrics | > 80% capacity |

#### Integration Metrics

| Metric | Source | Alert Threshold |
|--------|--------|----------------|
| CAPS sync success | `casey-reference-data-sync.log` | Failure for 2+ consecutive days |
| SSO service health | `GET /health` response | Non-200 response |
| CAPS API response time | Application logs | > 10 seconds |

#### Infrastructure Metrics

| Metric | Source | Alert Threshold |
|--------|--------|----------------|
| CPU usage | OS metrics | > 80% sustained for 5 minutes |
| Memory usage | OS metrics | > 85% |
| MySQL connections | `SHOW STATUS` | > 80% of `max_connections` |
| MySQL slow queries | Slow query log | > 10 per hour |
| Disk I/O | OS metrics | > 80% utilisation |

### 18.2 Monitoring Stack Options

#### Option A: Prometheus + Grafana (Recommended)

1. Install Prometheus node exporter on the application server
2. Install MySQL exporter for database metrics
3. Configure Prometheus to scrape both exporters
4. Import Grafana dashboards for Laravel, MySQL, and Nginx
5. Configure alerting rules in Prometheus Alertmanager

#### Option B: Simple Script-Based Monitoring

Use the health check script from Section 16.5 combined with email alerts:

```bash
#!/bin/bash
# /opt/scripts/monitor.sh
if ! /opt/scripts/health-check.sh; then
    echo "Submission Tracker health check failed at $(date)" | \
        mail -s "ALERT: Submission Tracker Health Check Failed" ops@casey.co.za
fi
```

```cron
*/5 * * * * /opt/scripts/monitor.sh
```

### 18.3 Queue Monitoring

Check for failed jobs:

```bash
# Count failed jobs
php artisan tinker --execute="echo DB::table('failed_jobs')->count();"

# View failed jobs
php artisan queue:failed

# Retry a specific failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all
```

### 18.4 CAPS Sync Monitoring

Check the last sync status:

```bash
# View recent sync log
tail -50 /var/www/submission-tracker/storage/logs/casey-reference-data-sync.log

# Check last sync timestamp
stat /var/www/submission-tracker/storage/logs/casey-reference-data-sync.log

# Manually trigger a sync
php artisan casey:sync-reference-data
```

---

## 19. Backup Strategy

### 19.1 Backup Components

| Component | Data | RPO | Backup Method |
|-----------|------|-----|--------------|
| MySQL database | All application data | 24 hours | `mysqldump` daily |
| Uploaded files | Submission attachments | 24 hours | rsync / S3 sync |
| `.env` file | Configuration and secrets | On change | Secure vault |
| SSO microservice `.env` | SSO configuration | On change | Secure vault |

### 19.2 Database Backup

#### Daily Automated Backup

Create `/opt/scripts/backup-database.sh`:

```bash
#!/bin/bash
set -euo pipefail

# Configuration
DB_NAME="submission_tracker"
DB_USER="tracker_user"
DB_PASS="<password>"
BACKUP_DIR="/var/backups/submission-tracker/database"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}_${DATE}.sql.gz"

# Ensure backup directory exists
mkdir -p "$BACKUP_DIR"

# Perform backup
mysqldump \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    --single-transaction \
    --routines \
    --triggers \
    --quick \
    --lock-tables=false \
    "$DB_NAME" | gzip > "$BACKUP_FILE"

# Verify backup is non-empty
if [ ! -s "$BACKUP_FILE" ]; then
    echo "ERROR: Backup file is empty!" >&2
    exit 1
fi

# Calculate checksum
sha256sum "$BACKUP_FILE" > "${BACKUP_FILE}.sha256"

# Remove old backups
find "$BACKUP_DIR" -name "*.sql.gz" -mtime +${RETENTION_DAYS} -delete
find "$BACKUP_DIR" -name "*.sha256" -mtime +${RETENTION_DAYS} -delete

echo "Backup completed: $BACKUP_FILE ($(du -h "$BACKUP_FILE" | cut -f1))"
```

```bash
chmod +x /opt/scripts/backup-database.sh
```

Schedule:

```cron
0 1 * * * /opt/scripts/backup-database.sh >> /var/log/backup-database.log 2>&1
```

### 19.3 File Storage Backup

#### Local rsync

```bash
#!/bin/bash
# /opt/scripts/backup-files.sh
set -euo pipefail

SOURCE="/var/www/submission-tracker/storage/app/private/uploads/"
DEST="/var/backups/submission-tracker/uploads/"
DATE=$(date +%Y%m%d)

mkdir -p "$DEST"
rsync -avz --delete "$SOURCE" "$DEST"

echo "File backup completed: $DATE"
```

#### S3 Sync (if using AWS)

```bash
#!/bin/bash
aws s3 sync \
    /var/www/submission-tracker/storage/app/private/uploads/ \
    s3://casey-tracker-backups/uploads/ \
    --delete \
    --storage-class STANDARD_IA
```

### 19.4 Configuration Backup

**Critical files to back up securely:**

```
/var/www/submission-tracker/.env
/var/www/submission-tracker/casey-sso-service/.env
```

**Never store `.env` files in version control.** Use a secrets manager (HashiCorp Vault, AWS Secrets Manager) or encrypted backup:

```bash
# Encrypt and backup
tar czf - .env casey-sso-service/.env | \
    openssl enc -aes-256-cbc -pbkdf2 -out /var/backups/submission-tracker/env-backup.tar.gz.enc

# Decrypt and restore
openssl enc -d -aes-256-cbc -pbkdf2 -in /var/backups/submission-tracker/env-backup.tar.gz.enc | \
    tar xzf -
```

### 19.5 Backup Verification

Perform monthly backup restoration tests:

```bash
# 1. Create a test database
mysql -u root -p -e "CREATE DATABASE tracker_restore_test;"

# 2. Restore the backup
gunzip -c /var/backups/submission-tracker/database/submission_tracker_YYYYMMDD_HHMMSS.sql.gz | \
    mysql -u root -p tracker_restore_test

# 3. Verify table counts
mysql -u root -p tracker_restore_test -e "
    SELECT 'users' AS tbl, COUNT(*) AS cnt FROM users
    UNION ALL SELECT 'submissions', COUNT(*) FROM submissions
    UNION ALL SELECT 'uploads', COUNT(*) FROM uploads
    UNION ALL SELECT 'municipalities', COUNT(*) FROM municipalities
    UNION ALL SELECT 'companies', COUNT(*) FROM companies;
"

# 4. Clean up
mysql -u root -p -e "DROP DATABASE tracker_restore_test;"
```

---

## 20. Disaster Recovery

### 20.1 Recovery Time Objectives

| Scenario | RTO | RPO | Procedure |
|----------|-----|-----|-----------|
| Application server failure | 1 hour | 0 (code in git) | Redeploy to new server |
| Database corruption | 2 hours | 24 hours | Restore from backup |
| File storage loss | 2 hours | 24 hours | Restore from backup |
| Complete data centre loss | 4 hours | 24 hours | Full rebuild on new infrastructure |
| Secret compromise | 30 minutes | 0 | Rotate all secrets |

### 20.2 Full System Recovery Procedure

#### Phase 1: Infrastructure (30 minutes)

1. Provision new server meeting the requirements in Section 3
2. Install OS packages (PHP, Node.js, MySQL, Nginx)
3. Configure firewall rules per Section 3.2

#### Phase 2: Application Restoration (30 minutes)

```bash
# Clone repository
cd /var/www
git clone <repository-url> submission-tracker
cd submission-tracker

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# SSO microservice
cd casey-sso-service && npm install --production && cd ..

# Restore environment files
# (from encrypted backup or secrets manager)
```

#### Phase 3: Database Restoration (30 minutes)

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE submission_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'tracker_user'@'localhost' IDENTIFIED BY '<password>';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON submission_tracker.* TO 'tracker_user'@'localhost'; FLUSH PRIVILEGES;"

# Restore from backup
gunzip -c /path/to/latest-backup.sql.gz | mysql -u root -p submission_tracker

# Or if starting fresh (no backup available)
php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan permissions:sync
php artisan casey:sync-reference-data
```

#### Phase 4: File Storage Restoration (30 minutes)

```bash
# From rsync backup
rsync -avz /var/backups/submission-tracker/uploads/ storage/app/private/uploads/

# Or from S3
aws s3 sync s3://casey-tracker-backups/uploads/ storage/app/private/uploads/

# Fix permissions
chown -R www-data:www-data storage/
```

#### Phase 5: Service Startup and Verification (30 minutes)

```bash
# Set permissions
chown -R www-data:www-data storage bootstrap/cache

# Cache optimisation
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start services (see Section 11.1)
# ...

# Run health checks (see Section 16)
# ...

# Verify CAPS connectivity
php artisan casey:sync-reference-data

# Verify user login
# Manually test SSO flow and local login
```

### 20.3 Secret Rotation Procedure

If any secret is compromised, rotate immediately:

```bash
# 1. Generate new secrets
NEW_APP_KEY=$(php artisan key:generate --show)
NEW_JWT_SECRET=$(openssl rand -hex 32)
NEW_SSO_SECRET=$(openssl rand -hex 32)
NEW_WEBHOOK_SECRET=$(openssl rand -hex 32)

# 2. Update .env
# Edit APP_KEY, CASEY_JWT_SHARED_SECRET, CASEY_SSO_API_SECRET, CAPS_WEBHOOK_SECRET

# 3. Update SSO microservice .env
# Edit SSO_API_SECRET to match CASEY_SSO_API_SECRET

# 4. Coordinate with CAPS team to update their matching secrets
# - CASEY_JWT_SHARED_SECRET must match CAPS JWT signing key
# - CAPS_WEBHOOK_SECRET must match CAPS webhook sender

# 5. Clear caches and restart
php artisan config:cache
php artisan queue:restart
sudo systemctl reload php8.2-fpm
pm2 restart casey-sso-service

# 6. Verify all integrations still work
curl http://localhost:4000/health
php artisan casey:sync-reference-data
```

---

## 21. Scaling Considerations

### 21.1 Vertical Scaling

The simplest scaling approach for moderate growth:

| Bottleneck | Action |
|-----------|--------|
| CPU | Increase PHP-FPM workers, add more cores |
| RAM | Increase MySQL `innodb_buffer_pool_size`, add RAM |
| Disk I/O | Move to NVMe SSD, separate MySQL data directory to dedicated disk |
| Network | Increase bandwidth, enable HTTP/2 |

### 21.2 Horizontal Scaling

For larger deployments:

| Component | Scaling Strategy |
|-----------|-----------------|
| Laravel application | Multiple PHP-FPM servers behind a load balancer (requires shared session store) |
| Queue workers | Multiple worker processes via Supervisor (`numprocs`) |
| MySQL | Read replicas for reporting queries |
| File storage | S3 or shared NFS mount |
| SSO microservice | Multiple instances behind load balancer (stateless design) |

### 21.3 Session Considerations for Multi-Server

If running multiple Laravel application servers:

1. Switch `SESSION_DRIVER` from `file` to `database` or `redis`
2. Switch `CACHE_STORE` from `file` to `redis`
3. Ensure all servers share the same `APP_KEY`
4. Use a shared filesystem or S3 for `FILESYSTEM_DISK`

### 21.4 CAPS API Rate Considerations

The CAPS member/policy verification uses a smart hybrid fetch strategy:

- **Paginated fetching** for datasets under 5,000 records (multiple small API calls)
- **Search-based fetching** for larger datasets (fewer, targeted API calls)

Monitor CAPS API response times and adjust the `CASEY_API_TOKEN_CACHE_TTL` to reduce authentication overhead.

---

## 22. Troubleshooting Guide

### 22.1 HTTP 500 Internal Server Error

**Symptoms:** User sees a generic error page or a JSON error response.

**Diagnosis:**

```bash
# Check the most recent Laravel log
tail -100 /var/www/submission-tracker/storage/logs/laravel-$(date +%Y-%m-%d).log

# Check Nginx error log
tail -50 /var/log/nginx/submission-tracker-error.log

# Check PHP-FPM error log
tail -50 /var/log/php/error.log
```

**Common causes:**

| Cause | Solution |
|-------|----------|
| Missing `.env` file | Copy `.env.example` to `.env` and configure |
| Invalid `APP_KEY` | Run `php artisan key:generate` |
| Database connection failed | Verify `DB_*` settings, test MySQL connectivity |
| Missing PHP extension | Install required extension (see Section 4.1) |
| Permission denied on `storage/` | `chown -R www-data:www-data storage/` |
| Cached stale config | `php artisan config:clear` |

### 22.2 SSO Not Working

**Symptoms:** Users are not redirected to CAPS for login, or SSO login fails silently.

**Diagnosis checklist:**

```bash
# 1. Verify SSO is enabled
grep CASEY_SSO_ENABLED /var/www/submission-tracker/.env
# Must be: CASEY_SSO_ENABLED=true

# 2. Check SSO microservice is running
curl http://localhost:4000/health
# Must return 200

# 3. Verify JWT shared secret matches
grep CASEY_JWT_SHARED_SECRET /var/www/submission-tracker/.env
# Must match the value configured in CAPS (com.casey.supportal.jwt.token.secretkey)

# 4. Verify SSO API secret matches
grep CASEY_SSO_API_SECRET /var/www/submission-tracker/.env
grep SSO_API_SECRET /var/www/submission-tracker/casey-sso-service/.env
# Both values must be identical

# 5. Verify handoff URL is set
grep CASEY_SSO_HANDOFF_URL /var/www/submission-tracker/.env
# Must point to the CAPS SSO bridge page

# 6. Check Laravel logs for SSO errors
grep -i "sso\|jwt\|casey" /var/www/submission-tracker/storage/logs/laravel-$(date +%Y-%m-%d).log | tail -30

# 7. Check for clock skew (JWT expiry)
date
# Compare with CAPS server time; must be within CASEY_JWT_LEEWAY_SECONDS (default 30s)
```

**Common causes:**

| Cause | Solution |
|-------|----------|
| SSO microservice not running | Start with `pm2 start casey-sso-service` |
| JWT shared secret mismatch | Coordinate with CAPS team to align secrets |
| SSO API secret mismatch | Ensure both `.env` files have the same value |
| Handoff URL not configured | Set `CASEY_SSO_HANDOFF_URL` |
| Clock skew > 30 seconds | Sync server clocks with NTP |
| CASEY_SSO_SKIP_SECONDS cooldown | Wait 60 seconds or clear the session cookie |

### 22.3 CAPS Reference Data Sync Failing

**Symptoms:** Companies or municipalities are missing or outdated.

**Diagnosis:**

```bash
# Check sync log
cat /var/www/submission-tracker/storage/logs/casey-reference-data-sync.log

# Test CAPS API connectivity
curl -v https://api.casey.co.za/casey/auth/sign-in

# Run sync manually with verbose output
php artisan casey:sync-reference-data -v
```

**Common causes:**

| Cause | Solution |
|-------|----------|
| CAPS API unreachable | Check `CASEY_API_BASE_URL`, network/firewall rules |
| Authentication failed | Verify `CASEY_API_USERNAME` and `CASEY_API_PASSWORD` |
| SSL certificate error | Check `CASEY_API_VERIFY_SSL`; in production this must be `true` |
| Endpoint path wrong | Verify `CASEY_API_MUNICIPALITIES_ENDPOINT` and `CASEY_API_COMPANIES_ENDPOINT` |
| Token cache stale | Clear cache: `php artisan cache:clear` |

### 22.4 CAPS Verification Inaccurate

**Symptoms:** Member or policy verification returns incorrect results or no matches.

**Diagnosis:**

1. Verify the correct parameters are being sent:
   - **Member verification** uses `idNumber` (South African ID number)
   - **Policy verification** uses `organizationId` (company CAPS ID)
2. Check the CAPS API response in Laravel logs
3. Verify the company has a valid `casey_id` in the local database

```bash
php artisan tinker --execute="
    \$company = \App\Models\Company::where('name', 'LIKE', '%Example%')->first();
    echo 'Company: ' . \$company->name . ', CAPS ID: ' . \$company->casey_id;
"
```

### 22.5 Memory Issues During Verification

**Symptoms:** PHP out-of-memory errors during CAPS member/policy batch fetching.

**Diagnosis:**

The CAPS integration uses a smart hybrid strategy:
- **Paginated fetching** for datasets with fewer than 5,000 records
- **Search-based fetching** for larger datasets

**Solutions:**

```bash
# Increase PHP memory limit temporarily for a specific command
php -d memory_limit=512M artisan casey:sync-reference-data

# Or permanently in php.ini
# memory_limit = 512M

# Check current memory limit
php -i | grep memory_limit
```

### 22.6 File Upload Failures

**Symptoms:** Users cannot upload submission files.

**Diagnosis:**

```bash
# Check directory permissions
ls -la /var/www/submission-tracker/storage/app/private/uploads/

# Check disk space
df -h /var/www/submission-tracker/storage/

# Check PHP upload limits
php -i | grep -E "upload_max_filesize|post_max_size|max_execution_time"

# Check Nginx limits
grep client_max_body_size /etc/nginx/sites-enabled/submission-tracker
```

### 22.7 Blank Page / Assets Not Loading

**Symptoms:** The page loads but is blank or unstyled.

**Diagnosis:**

```bash
# Verify Vite build artefacts exist
ls -la /var/www/submission-tracker/public/build/manifest.json

# If missing, rebuild
cd /var/www/submission-tracker
npm run build

# Check for mixed content (HTTP assets on HTTPS page)
# Ensure APP_URL starts with https:// in production
grep APP_URL .env
```

### 22.8 Permission Errors

**Symptoms:** `Permission denied` errors in logs, or users cannot access certain features.

**Application-level permissions:**

```bash
# Check user's roles and permissions
php artisan tinker --execute="
    \$user = \App\Models\User::where('email', 'user@example.com')->first();
    echo 'Roles: ' . \$user->getRoleNames()->implode(', ');
    echo PHP_EOL . 'Permissions: ' . \$user->getAllPermissions()->pluck('name')->implode(', ');
"
```

**File-system permissions:**

```bash
# Fix ownership
sudo chown -R www-data:www-data /var/www/submission-tracker/storage
sudo chown -R www-data:www-data /var/www/submission-tracker/bootstrap/cache

# Fix permissions
sudo chmod -R 775 /var/www/submission-tracker/storage
sudo chmod -R 775 /var/www/submission-tracker/bootstrap/cache
```

---

## 23. Runbook: Common Operational Tasks

### 23.1 Deploy a New Release

```bash
cd /var/www/submission-tracker

# 1. Pull latest code
git pull origin main

# 2. Install PHP dependencies
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Sync permissions (if new permissions were added)
php artisan permissions:sync

# 5. Install and build frontend
npm install
npm run build

# 6. Update SSO microservice (if changed)
cd casey-sso-service && npm install --production && cd ..

# 7. Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Restart services
php artisan queue:restart
sudo systemctl reload php8.2-fpm
pm2 restart casey-sso-service

# 9. Verify
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/login
curl -s http://localhost:4000/health
```

### 23.2 Add a New User Manually

```bash
php artisan tinker
```

```php
$user = \App\Models\User::create([
    'name' => 'John Doe',
    'email' => 'john.doe@casey.co.za',
    'password' => bcrypt('temporary-password'),
]);
$user->assignRole('user');
```

### 23.3 Force a CAPS Reference Data Sync

```bash
php artisan casey:sync-reference-data
```

### 23.4 Reset a User's Password

```bash
php artisan tinker --execute="
    \$user = \App\Models\User::where('email', 'user@casey.co.za')->first();
    \$user->update(['password' => bcrypt('new-temporary-password')]);
    echo 'Password reset for: ' . \$user->email;
"
```

### 23.5 Clear All Caches

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan event:clear
```

### 23.6 View the Audit Trail

The audit trail is accessible via the admin panel at `/admin/audits`. For command-line access:

```bash
php artisan tinker --execute="
    \App\Models\Audit::latest()->take(20)->get(['id','user_id','event','auditable_type','created_at'])->each(function(\$a) {
        echo \$a->created_at . ' | User ' . \$a->user_id . ' | ' . \$a->event . ' | ' . \$a->auditable_type . PHP_EOL;
    });
"
```

### 23.7 Monitor Queue in Real Time

```bash
# Watch queue processing (development)
php artisan pail --timeout=0

# Check queue status
php artisan queue:monitor database:default

# List failed jobs
php artisan queue:failed
```

### 23.8 Maintenance Mode

```bash
# Enable maintenance mode (returns 503 to all requests)
php artisan down --secret="bypass-token-here"
# Access during maintenance by visiting: https://tracker.casey.co.za/bypass-token-here

# Disable maintenance mode
php artisan up
```

---

## 24. Security Operations

### 24.1 Security Checklist

| Item | Check |
|------|-------|
| APP_DEBUG is `false` in production | `grep APP_DEBUG .env` |
| .env file is not web-accessible | `curl https://tracker.casey.co.za/.env` returns 403 |
| HTTPS enforced (HTTP redirects to HTTPS) | `curl -I http://tracker.casey.co.za` returns 301 |
| Session cookies are secure and HTTP-only | Check response headers |
| CORS is properly configured | Check `config/cors.php` |
| File uploads are stored outside web root | Files in `storage/app/private/uploads/` not in `public/` |
| SQL injection prevention | Eloquent ORM with parameterised queries |
| XSS prevention | Vue 3 auto-escapes output; Laravel Blade uses `{{ }}` |
| CSRF protection | Inertia.js includes CSRF token automatically |
| Rate limiting | Configured in `app/Http/Kernel.php` or route middleware |

### 24.2 Secret Inventory

| Secret | Location | Shared With |
|--------|----------|-------------|
| `APP_KEY` | Tracker `.env` | None (unique per installation) |
| `CASEY_JWT_SHARED_SECRET` | Tracker `.env` | CAPS Backend (Spring Boot `jwt.token.secretkey`) |
| `CASEY_SSO_API_SECRET` | Tracker `.env` | SSO microservice (`SSO_API_SECRET`) |
| `SSO_API_SECRET` | SSO `.env` | Tracker (`CASEY_SSO_API_SECRET`) |
| `CAPS_WEBHOOK_SECRET` | Tracker `.env` | CAPS Backend (webhook sender) |
| `DB_PASSWORD` | Tracker `.env` | MySQL server |
| `CASEY_API_PASSWORD` | Tracker `.env` | CAPS Backend (service account) |

### 24.3 Security Incident Response

1. **Identify** the scope (which secrets, systems, or data are affected)
2. **Contain** by enabling maintenance mode: `php artisan down`
3. **Rotate** all potentially compromised secrets (see Section 20.3)
4. **Investigate** using the audit trail and application logs
5. **Remediate** by patching the vulnerability
6. **Recover** by restarting services and disabling maintenance mode
7. **Document** the incident and update procedures

---

## 25. Release and Deployment Workflow

### 25.1 Pre-Deployment Checklist

| # | Task | Command / Action |
|---|------|-----------------|
| 1 | All tests pass | `php artisan test` |
| 2 | Code linting passes | `./vendor/bin/pint --test` |
| 3 | Frontend builds successfully | `npm run build` |
| 4 | Migrations are reversible | `php artisan migrate:rollback --step=1` then `php artisan migrate` |
| 5 | No sensitive data in commit | Review diff for secrets, credentials |
| 6 | Database backup taken | Run backup script (Section 19.2) |
| 7 | Stakeholders notified | Communication sent |

### 25.2 Deployment Steps

```bash
# 1. Take a database backup
/opt/scripts/backup-database.sh

# 2. Enable maintenance mode
php artisan down --secret="deploy-$(date +%s)"

# 3. Pull code
git pull origin main

# 4. Install dependencies
composer install --no-dev --optimize-autoloader
npm install

# 5. Run migrations
php artisan migrate --force

# 6. Sync permissions
php artisan permissions:sync

# 7. Build frontend
npm run build

# 8. Update SSO microservice
cd casey-sso-service && npm install --production && cd ..

# 9. Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 10. Restart workers
php artisan queue:restart
pm2 restart casey-sso-service

# 11. Reload PHP-FPM
sudo systemctl reload php8.2-fpm

# 12. Disable maintenance mode
php artisan up

# 13. Run health checks
/opt/scripts/health-check.sh

# 14. Monitor logs for errors
tail -f storage/logs/laravel-$(date +%Y-%m-%d).log
```

### 25.3 Rollback Procedure

If issues are discovered after deployment:

```bash
# 1. Enable maintenance mode
php artisan down

# 2. Roll back to previous release
git checkout <previous-tag-or-commit>

# 3. Restore dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# 4. Roll back migrations (if applicable)
php artisan migrate:rollback --step=<number-of-new-migrations>

# 5. Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Restart services
php artisan queue:restart
sudo systemctl reload php8.2-fpm
pm2 restart casey-sso-service

# 7. Disable maintenance mode
php artisan up

# 8. If database rollback is insufficient, restore from backup
# (see Section 20.2 Phase 3)
```

---

## Appendix A: Complete .env Reference

Below is the complete `.env.example` file with all variables and their descriptions:

```ini
# ============================================================
# APPLICATION CORE
# ============================================================
APP_NAME=FileManagement
APP_ENV=local                       # local | staging | production
APP_KEY=                            # Generated by: php artisan key:generate
APP_DEBUG=true                      # MUST be false in production
APP_URL=http://localhost:8000

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

# ============================================================
# LOGGING
# ============================================================
LOG_CHANNEL=daily
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug                     # Production: warning or error

# ============================================================
# DATABASE
# ============================================================
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=root
DB_PASSWORD=

# ============================================================
# SESSION / CACHE / QUEUE
# ============================================================
SESSION_DRIVER=file                 # file | database | redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=false               # Production: true
SESSION_PATH=/
SESSION_DOMAIN=null
# SESSION_SECURE_COOKIE=           # Production: true

CACHE_STORE=file                    # file | redis
QUEUE_CONNECTION=sync               # sync | database | redis

# ============================================================
# FILE STORAGE
# ============================================================
FILESYSTEM_DISK=local               # local | s3

# ============================================================
# VITE
# ============================================================
VITE_APP_NAME="FileManagement"

# ============================================================
# CAPS API (Layer 1 - Reference Data & Authentication)
# ============================================================
CASEY_API_BASE_URL=
CASEY_API_USERNAME=
CASEY_API_PASSWORD=
CASEY_API_AUTH_ENDPOINT=/casey/auth/sign-in
CASEY_API_VERIFY_SSL=true           # MUST be true in production
CASEY_API_TOKEN_CACHE_TTL=50

CASEY_API_PREMIUM_BATCH_ENDPOINT=/casey/v1/premiums/batch/detailed_info
CASEY_API_PREMIUM_BATCH_ID=

# ============================================================
# CAPS REFERENCE DATA SYNC (Layer 1)
# ============================================================
CASEY_API_MUNICIPALITIES_ENDPOINT=/v1/admin/organization/municipalities
CASEY_API_COMPANIES_ENDPOINT=/v1/admin/organization/companies
CASEY_API_SYNC_ONLY_ACTIVE=true
CASEY_API_SYNC_DEFAULT_PROVINCE=

# ============================================================
# CAPS SINGLE SIGN-ON (Layer 2)
# ============================================================
CASEY_SSO_ENABLED=false             # Production: true
CASEY_JWT_SHARED_SECRET=            # HS256 secret (must match CAPS)
CASEY_JWT_LEEWAY_SECONDS=30
CASEY_SSO_AUTO_PROVISION=true
CASEY_SSO_DEFAULT_ROLE=user
CASEY_SSO_REDIRECT_ROUTE=dashboard
CASEY_SSO_AUTO_REDIRECT=true
CASEY_SSO_HANDOFF_URL=              # e.g., https://caps.example.com/casey/auth/sso-bridge
CASEY_SSO_LOGOUT_URL=               # e.g., https://caps.example.com/casey/auth/sso-logout
CASEY_SSO_SKIP_SECONDS=60

# ============================================================
# CASEY SSO SESSION SERVICE
# ============================================================
CASEY_SSO_SERVICE_URL=http://localhost:4000
CASEY_SSO_API_SECRET=               # Must match SSO_API_SECRET in SSO service

# ============================================================
# CAPS WEBHOOK (Layer 3 - Status Echo-back)
# ============================================================
CAPS_WEBHOOK_SECRET=                # HMAC-SHA256 secret (must match CAPS)

# ============================================================
# MAIL (log only by default)
# ============================================================
MAIL_MAILER=log
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="FileManagement"

# ============================================================
# REDIS (optional, for cache/session/queue)
# ============================================================
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

---

## Appendix B: Port Allocation Map

| Port | Service | Protocol | Binding | Notes |
|------|---------|----------|---------|-------|
| 80 | Nginx (HTTP) | TCP | 0.0.0.0 | Redirects to 443 |
| 443 | Nginx (HTTPS) | TCP | 0.0.0.0 | Public-facing |
| 3000 | CAPS Frontend (Next.js) | TCP | External | Not managed by this deployment |
| 3306 | MySQL | TCP | 127.0.0.1 | Bind to localhost only |
| 4000 | Casey SSO Session Service | TCP | 127.0.0.1 | Internal only |
| 5173 | Vite dev server | TCP | 0.0.0.0 | Development only, never in production |
| 8000 | Laravel (php artisan serve) | TCP | 127.0.0.1 | Dev only; production uses PHP-FPM behind Nginx |
| 9086 | CAPS Backend API (Spring Boot) | TCP | External | Not managed by this deployment |

---

## Appendix C: Artisan Command Reference

### Custom Commands

| Command | Description | Usage |
|---------|-------------|-------|
| `casey:sync-reference-data` | Syncs companies and municipalities from CAPS API | `php artisan casey:sync-reference-data` |
| `permissions:sync` | Syncs permission definitions from code to database | `php artisan permissions:sync` |

### Frequently Used Laravel Commands

| Command | Description |
|---------|-------------|
| `php artisan serve` | Start development server on port 8000 |
| `php artisan migrate` | Run pending database migrations |
| `php artisan migrate:status` | Show migration status |
| `php artisan migrate:rollback` | Roll back the last migration batch |
| `php artisan db:seed --class=RolePermissionSeeder` | Seed roles and permissions |
| `php artisan config:cache` | Cache configuration files |
| `php artisan config:clear` | Clear configuration cache |
| `php artisan route:cache` | Cache route definitions |
| `php artisan route:clear` | Clear route cache |
| `php artisan route:list` | List all registered routes |
| `php artisan view:cache` | Compile and cache all Blade views |
| `php artisan view:clear` | Clear compiled view cache |
| `php artisan cache:clear` | Clear application cache |
| `php artisan queue:work` | Start a queue worker |
| `php artisan queue:restart` | Restart all queue workers gracefully |
| `php artisan queue:failed` | List failed queue jobs |
| `php artisan queue:retry all` | Retry all failed jobs |
| `php artisan schedule:work` | Run the scheduler in the foreground |
| `php artisan schedule:list` | List all scheduled tasks |
| `php artisan down` | Enable maintenance mode |
| `php artisan up` | Disable maintenance mode |
| `php artisan about` | Display application information |
| `php artisan tinker` | Interactive REPL |
| `php artisan key:generate` | Generate application encryption key |
| `php artisan pail` | Real-time log viewer |
| `php artisan test` | Run the test suite |

---

**Document Control**

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | April 2026 | Casey & Associates Engineering | Initial release |

---

*This document is classified as Internal - Confidential and should not be distributed outside Casey & Associates without authorisation.*
