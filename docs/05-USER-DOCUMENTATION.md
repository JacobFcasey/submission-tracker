# User Documentation

## Submission Tracker - Casey & Associates

**Document Version:** 1.0
**Date:** April 2026
**Classification:** Internal - Confidential
**Audience:** End Users, Managers, Administrators, Super Administrators

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Logging In](#3-logging-in)
4. [Understanding Your Role](#4-understanding-your-role)
5. [Dashboard Overview](#5-dashboard-overview)
6. [Uploading Submission Files](#6-uploading-submission-files)
7. [Upload Statuses Explained](#7-upload-statuses-explained)
8. [Viewing Upload History](#8-viewing-upload-history)
9. [Verification Results](#9-verification-results)
10. [File Previews](#10-file-previews)
11. [Managing Deadlines](#11-managing-deadlines)
12. [Notifications](#12-notifications)
13. [Managing Users (Admin)](#13-managing-users-admin)
14. [CAPS Data Synchronisation](#14-caps-data-synchronisation)
15. [Reports and Exports](#15-reports-and-exports)
16. [Step-by-Step Workflows](#16-step-by-step-workflows)
17. [Tips and Best Practices](#17-tips-and-best-practices)
18. [Common Errors and Solutions](#18-common-errors-and-solutions)
19. [Frequently Asked Questions (FAQ)](#19-frequently-asked-questions-faq)
20. [Glossary](#20-glossary)
21. [Support and Contact](#21-support-and-contact)

---

## 1. Introduction

### 1.1 What Is the Submission Tracker?

The Submission Tracker is a web-based application used by Casey & Associates to manage the monthly submission lifecycle for municipal payroll deduction files. Every month, municipalities across South Africa deduct premiums from employee salaries on behalf of deduction companies (insurance providers, loan companies, and other financial institutions). Each deduction must be properly submitted, tracked, verified against the CAPS system, and audited for compliance.

The Submission Tracker replaces manual spreadsheet-based tracking with a digital workflow that provides:

- Centralised file upload and storage for all submission documents
- Automated deadline tracking per municipality
- Real-time verification of uploaded data against CAPS records
- Role-based access so each user sees only what they need
- A complete audit trail for regulatory compliance
- Notifications to keep everyone informed of upcoming and overdue deadlines

### 1.2 Who Should Read This Document?

This document is written for all users of the Submission Tracker, regardless of their role or technical expertise:

| Audience | Relevant Sections |
|----------|-------------------|
| **Clerks (User role)** | Sections 1-12, 16-20 |
| **Managers** | All sections except Section 13 (user management details) |
| **Administrators** | All sections |
| **Super Administrators** | All sections |

### 1.3 System Requirements

To use the Submission Tracker, you need:

- A modern web browser (Google Chrome, Microsoft Edge, Mozilla Firefox, or Safari)
- A stable internet/intranet connection
- Your employee number and password (provided by your administrator)
- Screen resolution of 1280x720 or higher recommended

### 1.4 Accessing the Application

| Environment | URL |
|-------------|-----|
| Development | http://localhost:8000 |
| Production | Provided by your IT department |

Open your web browser and navigate to the appropriate URL. You will be presented with the login screen.

---

## 2. Getting Started

### 2.1 First-Time Setup Checklist

Before you begin using the Submission Tracker, confirm the following with your administrator:

- [ ] You have been given an employee number
- [ ] You have received a temporary password (if applicable)
- [ ] Your role has been assigned (User, Manager, Admin, or Super Admin)
- [ ] You have been assigned to the correct municipalities and companies
- [ ] You understand which municipalities and companies you are responsible for

### 2.2 Browser Recommendations

For the best experience, use the latest version of one of these browsers:

| Browser | Minimum Version | Recommended |
|---------|----------------|-------------|
| Google Chrome | 90+ | Yes (preferred) |
| Microsoft Edge | 90+ | Yes |
| Mozilla Firefox | 88+ | Yes |
| Safari | 14+ | Supported |
| Internet Explorer | Not supported | No |

Ensure that JavaScript is enabled and pop-up blockers are configured to allow pop-ups from the application URL.

---

## 3. Logging In

### 3.1 Standard Login (Manual)

If you are logging in manually (SSO is not active or CAPS is not running):

**Step 1:** Open your browser and navigate to the application URL.

**Step 2:** You will see the login screen.

```
+--------------------------------------------------+
|                                                    |
|          Submission Tracker                         |
|          Casey & Associates                         |
|                                                    |
|   +--------------------------------------------+  |
|   | Employee Number                             |  |
|   | [________________________]                  |  |
|   +--------------------------------------------+  |
|                                                    |
|   +--------------------------------------------+  |
|   | Password                                    |  |
|   | [________________________]                  |  |
|   +--------------------------------------------+  |
|                                                    |
|   [ ] Remember Me                                  |
|                                                    |
|   [          Log In          ]                     |
|                                                    |
+--------------------------------------------------+
```

**Step 3:** Enter your **employee number** in the first field (this is not your email address).

**Step 4:** Enter your **password** in the second field.

**Step 5:** Optionally check **Remember Me** to stay logged in on this device.

**Step 6:** Click the **Log In** button.

**Step 7:** If your credentials are correct, you will be redirected to the Dashboard. If not, an error message will appear below the login button.

### 3.2 Single Sign-On (SSO) via CAPS

If CAPS (Casey Application Platform System) is running and SSO is enabled, your login is handled automatically:

- When you open the Submission Tracker URL, the system checks for a valid CAPS session.
- If you are already logged into CAPS, the Submission Tracker will authenticate you automatically within approximately 5 seconds. No manual entry is required.
- Your user profile is synchronised bidirectionally between CAPS and the Submission Tracker. Any changes to your details in either system are reflected in both within 5 seconds.

**Important:** If SSO is temporarily paused or CAPS is undergoing maintenance, the manual login form (described in Section 3.1) is always available as a fallback. You can log in with the same employee number and password regardless of SSO status.

### 3.3 After Logging In

Once authenticated, you will land on the **Dashboard** (see Section 5). The navigation bar at the top of every page shows:

- Your name and role
- A bell icon for notifications (with an unread count badge)
- A dropdown menu for your profile and logout

### 3.4 Logging Out

To log out:

1. Click your name or avatar in the top-right corner of the navigation bar.
2. Select **Logout** from the dropdown menu.
3. You will be redirected to the login page.

**Security tip:** Always log out when leaving a shared or public workstation.

---

## 4. Understanding Your Role

The Submission Tracker uses role-based access control. Your role determines what you can see and do within the application. There are four roles:

### 4.1 User (Clerk)

The **User** role is designed for staff members who are responsible for uploading submission files.

| Capability | Access |
|------------|--------|
| Upload submission files | Yes |
| View own upload history | Yes |
| View verification results for own uploads | Yes |
| Preview uploaded files | Yes |
| Receive notifications | Yes |
| View dashboard (own assignments) | Yes |
| Manage deadlines | No |
| Manage users | No |
| View reports | No |
| Manage companies/municipalities | No |

### 4.2 Manager

The **Manager** role is for team leads and supervisors who oversee the submission process.

| Capability | Access |
|------------|--------|
| All User capabilities | Yes |
| Create and manage deadlines | Yes |
| View uploads from all assigned users | Yes |
| View reports | Yes |
| Export data to CSV/Excel | Yes |
| Manage users | No |
| Manage system settings | No |

### 4.3 Admin (Administrator)

The **Admin** role is for operational administrators who manage the system configuration and users.

| Capability | Access |
|------------|--------|
| All Manager capabilities | Yes |
| Create, edit, and delete users | Yes |
| Assign roles to users | Yes |
| Assign users to municipalities and companies | Yes |
| Manage companies and municipalities | Yes |
| Trigger CAPS data synchronisation | Yes |
| View audit logs | Yes |
| View all reports | Yes |

### 4.4 Super Admin

The **Super Admin** role has unrestricted access to every feature in the system.

| Capability | Access |
|------------|--------|
| All Admin capabilities | Yes |
| View and manage all data across all municipalities | Yes |
| Filter dashboard by any user | Yes |
| System-wide configuration | Yes |
| Access all audit trails | Yes |
| Override any restriction | Yes |

### 4.5 Role Assignment

Your role is assigned by an administrator. If you believe your role is incorrect or you need elevated access, contact your administrator or IT department. Role changes take effect immediately upon the next page load.

---

## 5. Dashboard Overview

The Dashboard is the first page you see after logging in. It provides a comprehensive summary of your submissions, deadlines, and recent activity.

### 5.1 Dashboard Layout

```
+------------------------------------------------------------------+
| NAVIGATION BAR                                    [Bell] [User]  |
+------------------------------------------------------------------+
|                                                                    |
|  [Total Assignments]  [Overdue]  [Due This Week]  [Completion %] |
|       42                 3             8              87%          |
|                                                                    |
|  +-- CAPS Sync Panel (Admin only) ---------------------------+   |
|  | Municipalities: 24 | Companies: 156 | Last sync: 10:32   |   |
|  | [Sync from CAPS]                                           |   |
|  +------------------------------------------------------------+   |
|                                                                    |
|  +-- Left Panel ----------+  +-- Right Panel ----------------+   |
|  |                         |  |                                |   |
|  | UPCOMING DEADLINES      |  | OVERDUE ASSIGNMENTS           |   |
|  | - City of Tshwane (3d)  |  | - Mangaung Metro (2 days)     |   |
|  | - eThekwini (5d)        |  |   Company A, Company B        |   |
|  | - Nelson Mandela (7d)   |  | - Buffalo City (5 days)       |   |
|  |                         |  |   Company C                   |   |
|  | ALL MUNICIPALITY         |  |                                |   |
|  | DEADLINES               |  | ASSIGNMENTS BY MUNICIPALITY   |   |
|  | - Tshwane: 15 Apr       |  | - Tshwane: 12/15 complete     |   |
|  |   Users: J.M., S.K.    |  | - eThekwini: 8/10 complete    |   |
|  |   Companies: 12         |  | - Mangaung: 5/8 complete      |   |
|  |                         |  |                                |   |
|  | RECENT ACTIVITY          |  |                                |   |
|  | - Upload: File A (2h)   |  |                                |   |
|  | - Verified: File B (4h) |  |                                |   |
|  +-------------------------+  +--------------------------------+   |
|                                                                    |
|  Quick Actions: [New Submission]  [View Deadlines]                |
|                                                                    |
+------------------------------------------------------------------+
```

### 5.2 Stats Cards

At the top of the Dashboard, four summary cards provide an at-a-glance view of your submission status:

| Card | Description | What It Means |
|------|-------------|---------------|
| **Total Assignments** | The total number of municipality-company pairs assigned to you for the current period | How many submissions you are responsible for |
| **Overdue** | The number of assignments past their deadline that have not been completed | Items that require immediate attention (displayed in red) |
| **Due This Week** | The number of assignments with deadlines falling within the current week | Items you should prioritise this week |
| **Completion Rate** | The percentage of your assignments that have reached "Completed" status | Your overall progress for the current period |

### 5.3 CAPS Sync Panel (Admin and Super Admin Only)

If you are an Admin or Super Admin, a CAPS Sync panel appears below the stats cards. This panel shows:

- **Municipality count:** The number of municipalities currently synchronised from CAPS
- **Company count:** The number of deduction companies currently synchronised from CAPS
- **Last sync time:** When the last successful synchronisation occurred
- **Sync button:** Click **"Sync from CAPS"** or **"Refresh Data"** to manually trigger a synchronisation

If no CAPS data has been synchronised yet, the panel displays an amber warning with a **"Sync from CAPS Now"** button. See Section 14 for full details on CAPS synchronisation.

### 5.4 Left Panel: Upcoming Deadlines

This section lists your approaching deadlines, sorted by urgency (nearest first). Each entry shows:

- The municipality name
- The number of days remaining until the deadline
- Colour coding: red for overdue, amber for due within 3 days, green for further out

### 5.5 Left Panel: All Municipality Deadlines

A comprehensive list of all active deadlines across all municipalities. Each deadline entry shows:

- Municipality name
- Deadline date
- Assigned users (names or initials)
- Number of companies included in that deadline

### 5.6 Left Panel: Recent Activity

A chronological feed of recent actions, including:

- Files you have uploaded
- Verification results received
- Deadline assignments
- Status changes on your submissions

### 5.7 Right Panel: Overdue Assignments

Displayed with a **red** background for visibility, this section lists all assignments that have passed their deadline without being completed. Each overdue entry shows:

- Municipality name
- Number of days overdue
- Companies still pending

**Action required:** Address overdue items as soon as possible. Contact your manager if you cannot complete a submission.

### 5.8 Right Panel: Assignments by Municipality

A breakdown of your submission progress grouped by municipality. Each entry shows:

- Municipality name
- A progress indicator (e.g., "12/15 complete")
- Visual progress bar

### 5.9 Quick Actions

At the bottom of the Dashboard, two shortcut buttons allow you to jump directly to common tasks:

- **New Submission:** Opens the upload form (see Section 6)
- **View Deadlines:** Opens the deadlines management page (see Section 11)

### 5.10 Super Admin: User Filter Dropdown

Super Admins see an additional dropdown at the top of the Dashboard that allows them to filter the entire Dashboard view by a specific user. This is useful for reviewing individual user progress without needing to switch accounts.

To use:
1. Click the user filter dropdown.
2. Select a user from the list.
3. The Dashboard refreshes to show that user's assignments, deadlines, and activity.
4. Select "All Users" or clear the filter to return to the aggregate view.

---

## 6. Uploading Submission Files

Uploading files is the core activity of the Submission Tracker. Each submission represents a set of files for one municipality-company pair.

### 6.1 Navigating to the Upload Page

1. Click **Uploads** in the main navigation bar.
2. The Uploads page opens, showing the upload form and your recent uploads.

### 6.2 Understanding the Three File Types

Each complete submission can contain up to three types of files:

| File Type | Description | Format | Required? |
|-----------|-------------|--------|-----------|
| **Email file** | The original correspondence from the municipality containing the deduction data or confirmation | `.eml` or `.msg` | **Yes** (mandatory) |
| **Workings file** | A spreadsheet containing the manual workings or calculations for the deductions | `.xlsx` or `.csv` | Optional |
| **Systems import file** | A spreadsheet formatted for import into the processing system | `.xlsx` or `.csv` | Optional |

### 6.3 Step-by-Step: Uploading Files

```
+------------------------------------------------------------------+
| UPLOAD SUBMISSION                                                  |
|                                                                    |
|  Municipality *                                                    |
|  [-- Select Municipality --          v]                           |
|                                                                    |
|  Company *                                                         |
|  [-- Select Company --               v]                           |
|                                                                    |
|  Email File (.eml or .msg) *                                      |
|  [Choose File] No file chosen                                     |
|                                                                    |
|  Workings File (.xlsx or .csv)                                    |
|  [Choose File] No file chosen                                     |
|                                                                    |
|  Systems Import File (.xlsx or .csv)                              |
|  [Choose File] No file chosen                                     |
|                                                                    |
|  [          Submit          ]                                      |
+------------------------------------------------------------------+
```

Follow these steps to upload a submission:

**Step 1: Select Municipality**
- Click the **Municipality** dropdown.
- Select the municipality for which you are submitting files.
- The dropdown only shows municipalities assigned to you.

**Step 2: Select Company**
- Click the **Company** dropdown.
- The company list is filtered to show only companies assigned to you for the selected municipality.
- Every company submits to every municipality, so the list reflects your personal assignments.

**Step 3: Upload Email File (Required)**
- Click **Choose File** next to "Email File."
- Browse your computer and select the `.eml` or `.msg` file.
- This file is mandatory. You cannot submit without it.

**Step 4: Upload Workings File (Optional)**
- Click **Choose File** next to "Workings File."
- Select the `.xlsx` or `.csv` spreadsheet containing your workings.
- This step is optional but recommended for complete submissions.

**Step 5: Upload Systems Import File (Optional)**
- Click **Choose File** next to "Systems Import File."
- Select the `.xlsx` or `.csv` file formatted for system import.
- This step is optional but recommended for complete submissions.

**Step 6: Submit**
- Review your selections to ensure the correct municipality, company, and files are chosen.
- Click the **Submit** button.
- The upload is auto-verified against CAPS immediately upon submission.
- You will be redirected to the uploads list with a success message confirming your submission.

### 6.4 Re-Uploading Files (Within 30 Days)

If you need to re-upload files for a municipality-company pair that already has a submission within the last 30 days, additional fields will appear:

```
+------------------------------------------------------------------+
| RE-UPLOAD DETECTED                                                 |
|                                                                    |
|  A submission for this municipality and company was already        |
|  made on 2026-04-10. Please provide a reason for re-upload.       |
|                                                                    |
|  Reason Type *                                                     |
|  [-- Select Reason --                v]                           |
|                                                                    |
|  Note *                                                            |
|  [____________________________________________]                   |
|  [____________________________________________]                   |
|                                                                    |
+------------------------------------------------------------------+
```

**Step 1:** Select a **Reason Type** from the dropdown (e.g., "Correction," "Updated Data," "Requested by Municipality," etc.).

**Step 2:** Enter a descriptive **Note** explaining why the re-upload is necessary. Be specific - this note is recorded in the audit trail.

**Step 3:** Complete the rest of the upload form as described in Section 6.3.

### 6.5 What Happens After You Submit

1. The system stores your files securely.
2. An automatic verification is triggered against the CAPS system, comparing the uploaded data with known members and policies.
3. The submission is assigned an initial status based on which file types were included (see Section 7).
4. A unique reference number is generated for the submission.
5. You are redirected to the uploads list, where a green success banner confirms the upload.
6. If the verification finds discrepancies, these will be visible in the Verification Results (see Section 9).

---

## 7. Upload Statuses Explained

Every submission has a status that reflects how complete it is. The status is determined automatically based on which file types have been uploaded.

### 7.1 Status Definitions

| Status | Icon/Colour | Meaning | Files Present |
|--------|-------------|---------|---------------|
| **Pending** | Yellow/Amber | Only the email file has been uploaded. The submission is incomplete. | Email only |
| **Processing** | Blue | The email file plus at least one spreadsheet (workings or systems import) has been uploaded. | Email + Workings, or Email + Systems Import |
| **Completed** | Green | All three file types are present. The submission is fully complete. | Email + Workings + Systems Import |

### 7.2 How Status Progresses

```
[Pending] ----> [Processing] ----> [Completed]
  (Email         (Email +           (Email +
   only)          1 spreadsheet)     2 spreadsheets)
```

- You can upload additional files to an existing submission to advance its status.
- Status advances automatically when the required files are detected.
- There is no manual status override; the status always reflects the actual files present.

### 7.3 What Each Status Means for You

- **Pending:** You still need to upload at least one spreadsheet file. The submission is not considered ready for processing.
- **Processing:** You have made progress, but one spreadsheet type is still missing. Check whether the workings file or systems import file still needs to be added.
- **Completed:** All required files are present. No further action needed for this submission.

---

## 8. Viewing Upload History

### 8.1 Navigating to Upload History

1. Click **Uploads** in the main navigation bar.
2. Click the **History** tab.

### 8.2 History Page Layout

```
+------------------------------------------------------------------+
| UPLOAD HISTORY                                                     |
|                                                                    |
|  Search: [_________________________________] [Search]             |
|                                                                    |
|  Filter by Status: [All v]                                        |
|                                                                    |
|  +------+----------+----------+--------+--------+---------+------+|
|  | Ref  | Company  | Munic.   | Status | Date   | Verified| Acts ||
|  +------+----------+----------+--------+--------+---------+------+|
|  | #247 | ABC Ins  | Tshwane  | Done   | 15 Apr | [check] | [eye]||
|  | #246 | XYZ Ltd  | eThekwi  | Proc   | 14 Apr |    -    | [eye]||
|  | #245 | DEF Co   | Mangaung | Pend   | 13 Apr |    -    | [eye]||
|  +------+----------+----------+--------+--------+---------+------+|
|                                                                    |
|  Showing 1-25 of 142       [< 1 2 3 4 5 6 >]                     |
+------------------------------------------------------------------+
```

### 8.3 Searching and Filtering

**Search:** Use the search box to find submissions by:
- Reference number (e.g., "#247" or "247")
- Company name (e.g., "ABC Insurance")
- Municipality name (e.g., "Tshwane")

**Filter by Status:** Use the status dropdown to filter submissions:
- **All:** Shows all submissions
- **Pending:** Shows only pending submissions
- **Processing:** Shows only submissions in processing
- **Completed:** Shows only completed submissions

### 8.4 Understanding the History Table Columns

| Column | Description |
|--------|-------------|
| **Ref** | The unique reference number assigned to the submission |
| **Company** | The deduction company for this submission |
| **Municipality** | The municipality this submission was made for |
| **Status** | Current status (Pending, Processing, or Completed) |
| **Date** | The date the submission was created or last updated |
| **Verified** | Shows a green checkmark button if auto-verification has been completed; a dash if not yet verified |
| **Actions** | Action buttons (eye icon for preview, verify button for manual verification) |

### 8.5 Viewing Verification Results

If the **Verified** column shows a green checkmark button:
1. Click the green checkmark button.
2. A Verification Results modal will open (see Section 9 for full details).

### 8.6 Running Manual Verification

If you need to re-verify a submission against current CAPS data:
1. Find the submission in the history list.
2. Click the **"Verify Members & Policies"** button.
3. The system will run a fresh verification against the latest CAPS data.
4. Results will appear in the Verification Results modal once complete.

---

## 9. Verification Results

When uploaded data is verified against CAPS, the system compares member IDs, policy codes, and premium amounts. The Verification Results modal provides a detailed breakdown.

### 9.1 Opening Verification Results

- From the Upload History page, click the green checkmark button on a verified submission.
- Or, click **"Verify Members & Policies"** to trigger a fresh verification.

### 9.2 Verification Results Modal Layout

```
+------------------------------------------------------------------+
| VERIFICATION RESULTS - Ref #247                                    |
| ABC Insurance | City of Tshwane | 15 April 2026                   |
+------------------------------------------------------------------+
|                                                                    |
|  Verification Score                                                |
|  [============================================------]  87%        |
|                (green >90%  |  amber >70%  |  red <70%)           |
|                                                                    |
|  +----------+----------+-----------+----------+----------+        |
|  | Members  | Policies | Premium   | Members  | Policies |        |
|  | Missing  | Missing  | Mismatch  | OK       | OK       |        |
|  | (3)      | (2)      | (5)       | (42)     | (38)     |        |
|  +----------+----------+-----------+----------+----------+        |
|                                                                    |
|  [Currently viewing: Members Missing]                              |
|                                                                    |
|  +--------+------------------+---------+                          |
|  | ID No. | Member Name      | Status  |                          |
|  +--------+------------------+---------+                          |
|  | 8201.. | Thabo Mokoena    | Missing |                          |
|  | 7905.. | Sipho Nkosi      | Missing |                          |
|  | 8807.. | Lerato Molefe    | Missing |                          |
|  +--------+------------------+---------+                          |
|                                                                    |
|                              [Close]                               |
+------------------------------------------------------------------+
```

### 9.3 Verification Score

At the top of the modal, a percentage bar shows the overall verification score:

| Score Range | Colour | Meaning |
|-------------|--------|---------|
| **Above 90%** | Green | Excellent - very few or no discrepancies |
| **71% - 90%** | Amber/Yellow | Acceptable but some items need attention |
| **70% or below** | Red | Significant issues - investigation required |

The score is calculated based on the proportion of successfully matched members, policies, and premiums versus the total expected.

### 9.4 The Five Tabs

The modal contains five tabs, each displaying different aspects of the verification:

#### Tab 1: Members Missing

Shows members that exist in CAPS but were not found in the uploaded submission files.

| Column | Description |
|--------|-------------|
| ID Number | The member's South African ID number (partially masked for security) |
| Member Name | The full name of the member |
| Status | "Missing" - this member was expected but not found in the upload |

**What to do:** Investigate whether these members should have been included. Contact the municipality if deductions were supposed to be made but were not submitted.

#### Tab 2: Policies Missing

Shows policy numbers that exist in CAPS but were not found in the uploaded data.

| Column | Description |
|--------|-------------|
| Policy Number | The policy reference number |
| Member Name | The member associated with the policy |
| Status | "Missing" - this policy was expected but not found |

**What to do:** Verify with the deduction company whether these policies are still active and should have been included in the submission.

#### Tab 3: Premium Mismatch

Shows entries where the premium amount in the uploaded file does not match the amount recorded in CAPS.

| Column | Description |
|--------|-------------|
| Member Name | The member whose premium is mismatched |
| Policy Number | The associated policy |
| Uploaded Amount | The premium amount found in the submitted file (in ZAR) |
| CAPS Amount | The premium amount recorded in CAPS (in ZAR) |
| Difference | The variance between uploaded and CAPS amounts (in ZAR) |

**What to do:** Investigate the reason for the difference. Common causes include:
- Premium adjustments that have not yet been reflected in CAPS
- Data entry errors in the submission file
- Changes processed by the municipality after the deduction was calculated

#### Tab 4: Members OK

Shows all members that were successfully matched between the uploaded file and CAPS records.

| Column | Description |
|--------|-------------|
| ID Number | The member's ID number |
| Member Name | The full name of the member |
| Status | "Matched" - this member was found in both the upload and CAPS |

This tab confirms which members have been correctly submitted.

#### Tab 5: Policies OK

Shows all policies that were successfully matched.

| Column | Description |
|--------|-------------|
| Policy Number | The policy reference number |
| Member Name | The associated member |
| Status | "Matched" - this policy was found in both the upload and CAPS |

This tab confirms which policies have been correctly submitted.

### 9.5 Using Verification Results

- Use the **Members Missing** and **Policies Missing** tabs to identify gaps in submissions.
- Use the **Premium Mismatch** tab to flag discrepancies that need resolution before processing.
- Use the **Members OK** and **Policies OK** tabs to confirm successful matches.
- Each tab shows a count in parentheses next to the tab name, so you can quickly see how many items fall into each category.
- If the verification score is red (below 70%), escalate to your manager before proceeding.

---

## 10. File Previews

The Submission Tracker allows you to preview any uploaded file directly in the browser without downloading it.

### 10.1 Opening a File Preview

1. Navigate to the Upload History page.
2. Find the submission you want to preview.
3. Click the **eye icon** in the Actions column.
4. A file preview panel opens.

### 10.2 Selecting a File to Preview

If the submission contains multiple files, a file list is displayed at the top of the preview panel. Click on any file name to view it.

```
+------------------------------------------------------------------+
| FILE PREVIEW - Ref #247                                            |
|                                                                    |
|  Files:                                                            |
|  [email_tshwane_april.eml] [workings_april.xlsx] [import.csv]    |
|                                                                    |
+------------------------------------------------------------------+
|                                                                    |
|  (Selected file content displayed here)                            |
|                                                                    |
+------------------------------------------------------------------+
```

### 10.3 Email File Preview (.eml / .msg)

When previewing an email file, the following information is displayed:

**Header Section:**

| Field | Description |
|-------|-------------|
| **From** | The sender's email address |
| **To** | The recipient's email address(es) |
| **Subject** | The email subject line |
| **Date** | The date and time the email was sent |

**Body Section:**

Three tabs allow you to view the email body in different formats:

| Tab | Description |
|-----|-------------|
| **Text** | Plain text version of the email body |
| **HTML** | Rendered HTML version of the email (formatted with styling) |
| **Raw** | The raw email source code (headers and body as plain text) |

**Attachments:**

If the email contains attachments, they are listed below the body section. You can click on attachment names to download them.

### 10.4 Spreadsheet File Preview (.xlsx / .csv)

When previewing a spreadsheet file, the data is rendered as an interactive table:

```
+------------------------------------------------------------------+
| SPREADSHEET PREVIEW                                                |
|                                                                    |
|  Search: [_________________________]                              |
|                                                                    |
|  +--------+------------------+---------+----------+               |
|  | ID No. | Member Name      | Policy  | Amount   |               |
|  +--------+------------------+---------+----------+               |
|  | 8201.. | Thabo Mokoena    | POL-001 | R 450.00 |               |
|  | 7905.. | Sipho Nkosi      | POL-002 | R 325.50 |               |
|  | ...    | ...              | ...     | ...      |               |
|  +--------+------------------+---------+----------+               |
|                                                                    |
|  Showing 1-50 of 234       [< 1 2 3 4 5 >]                       |
+------------------------------------------------------------------+
```

**Features:**
- **Search:** Type in the search box to filter rows by any column value.
- **Pagination:** Use the page controls at the bottom to navigate through large files.
- **Column headers:** The table displays the first row of the spreadsheet as column headers.

---

## 11. Managing Deadlines

Deadlines define when submissions for each municipality are due. Admins and Managers can create and manage deadlines.

### 11.1 Who Can Manage Deadlines?

| Role | Can View Deadlines | Can Create/Edit Deadlines | Can Assign Users |
|------|-------------------|---------------------------|------------------|
| User (Clerk) | Yes (own deadlines) | No | No |
| Manager | Yes (all) | Yes | Yes |
| Admin | Yes (all) | Yes | Yes |
| Super Admin | Yes (all) | Yes | Yes |

### 11.2 Navigating to Deadline Management

1. Click **Deadlines** in the main navigation bar.
2. Click the **Municipalities** tab.
3. Select a municipality from the list.

### 11.3 The Deadline Calendar

```
+------------------------------------------------------------------+
| DEADLINES - City of Tshwane                                        |
|                                                                    |
|  < April 2026 >                                                   |
|  +-----+-----+-----+-----+-----+-----+-----+                    |
|  | Mon | Tue | Wed | Thu | Fri | Sat | Sun |                    |
|  +-----+-----+-----+-----+-----+-----+-----+                    |
|  |     |     |  1  |  2  |  3  |  4  |  5  |                    |
|  |  6  |  7  |  8  |  9  | 10  | 11  | 12  |                    |
|  | 13  | 14  |[15] | 16  | 17  | 18  | 19  |                    |
|  | 20  | 21  | 22  | 23  | 24  | 25  | 26  |                    |
|  | 27  | 28  | 29  | 30  |     |     |     |                    |
|  +-----+-----+-----+-----+-----+-----+-----+                    |
|                                                                    |
|  [15] = Existing deadline (highlighted)                            |
|                                                                    |
|  Deadline Details - 15 April 2026                                  |
|  Assigned Users: J. Makopo, S. Khumalo                            |
|  Companies: 12 assigned                                            |
|  Status: 8 of 12 submitted                                        |
+------------------------------------------------------------------+
```

### 11.4 Creating a New Deadline

**Step 1:** On the Deadline Calendar, click on the date you want to set as the deadline.

**Step 2:** A deadline creation form appears.

**Step 3:** Assign users to companies for this deadline:
- Select users from the user dropdown.
- Select the companies each user is responsible for.
- You can assign multiple users to different companies within the same deadline.

**Step 4:** Click **Save** to create the deadline.

**Step 5:** Notifications are automatically sent to all assigned users informing them of their new deadline and assignments.

### 11.5 Editing an Existing Deadline

1. Click on a highlighted date (one that already has a deadline).
2. The deadline details panel opens.
3. Modify the user assignments or company allocations as needed.
4. Click **Save** to update.
5. Affected users are notified of the changes.

### 11.6 Deadline Best Practices

- Create deadlines at least one week in advance to give users adequate time.
- Assign backup users for critical municipalities to avoid bottlenecks.
- Review the Dashboard regularly to monitor overdue items.
- Communicate any deadline changes directly to affected users in addition to system notifications.

---

## 12. Notifications

The notification system keeps you informed about deadline assignments, upcoming due dates, overdue items, and other important events.

### 12.1 Notification Bell

The bell icon in the top-right corner of the navigation bar shows a badge with the number of unread notifications. If there are no unread notifications, the badge is hidden.

```
Navigation Bar:
[... other items ...]  [Bell (3)]  [User Menu]
                         ^
                    3 unread notifications
```

### 12.2 Notifications Page

To view all notifications:

1. Click the **bell icon** in the navigation bar, or
2. Navigate to the **Notifications** page from the main menu.

### 12.3 Notification Page Layout

```
+------------------------------------------------------------------+
| NOTIFICATIONS                                                      |
|                                                                    |
|  Filter: [All v] [Unread v] [Read v]    [Mark All as Read]       |
|                                                                    |
|  +--------------------------------------------------------------+|
|  | [*] New deadline assigned - City of Tshwane           2h ago  ||
|  |     You have been assigned 3 companies for the 15 Apr deadline||
|  +--------------------------------------------------------------+|
|  | [*] Submission overdue - Mangaung Metro               1d ago  ||
|  |     Your submission for ABC Insurance is 2 days overdue       ||
|  +--------------------------------------------------------------+|
|  | [ ] Verification complete - Ref #245                  3d ago  ||
|  |     Score: 92% - 2 policies missing                           ||
|  +--------------------------------------------------------------+|
|                                                                    |
|  [*] = Unread   [ ] = Read                                        |
+------------------------------------------------------------------+
```

### 12.4 Notification Actions

| Action | How |
|--------|-----|
| **View notification** | Click on any notification to expand its details |
| **Mark as read** | Click on an unread notification (marked with a dot) |
| **Mark all as read** | Click the **"Mark All as Read"** button at the top |
| **Filter notifications** | Use the filter buttons: All, Unread, or Read |
| **Delete a notification** | Click the delete/trash icon on an individual notification |
| **Clear all notifications** | Click the **"Clear All"** button (if available) to remove all notifications |

### 12.5 Types of Notifications

| Notification Type | When It Is Sent | Who Receives It |
|-------------------|-----------------|-----------------|
| **Deadline assigned** | When an admin or manager assigns you to a deadline | Assigned user |
| **Deadline approaching** | When a deadline is within 3 days | Assigned users |
| **Submission overdue** | When a deadline has passed without completion | Assigned user and their manager |
| **Verification complete** | When auto-verification finishes for your upload | Uploader |
| **Assignment change** | When your assignments are modified | Affected user |
| **System sync** | When CAPS data synchronisation completes | Admins |

---

## 13. Managing Users (Admin)

This section is for Administrators and Super Admins who manage user accounts.

### 13.1 Navigating to User Management

1. Click **Admin** in the main navigation bar.
2. Click **Users** from the dropdown menu.

### 13.2 User Management Page Layout

```
+------------------------------------------------------------------+
| USER MANAGEMENT                                                    |
|                                                                    |
|  [+ Create User]                                                   |
|                                                                    |
|  +--------+--------------+----------+---------+------------------+|
|  | Emp #  | Name         | Role     | Status  | Actions          ||
|  +--------+--------------+----------+---------+------------------+|
|  | E001   | Jacob Makopo | Admin    | Active  | [Edit] [Delete]  ||
|  | E002   | Sarah Khumal | Manager  | Active  | [Edit] [Delete]  ||
|  | E003   | Thabo Mokoen | User     | Active  | [Edit] [Delete]  ||
|  | E004   | Lerato Molef | User     | Disabled| [Edit] [Delete]  ||
|  +--------+--------------+----------+---------+------------------+|
+------------------------------------------------------------------+
```

### 13.3 Creating a New User

**Step 1:** Click the **"+ Create User"** button.

**Step 2:** Fill in the user details form:

| Field | Description | Required |
|-------|-------------|----------|
| Employee Number | The user's unique employee number | Yes |
| Full Name | First and last name | Yes |
| Email | The user's email address | Yes |
| Password | Initial password (user should change on first login) | Yes |
| Role | Select from: User, Manager, Admin, Super Admin | Yes |
| Municipalities | Select which municipalities this user can access | Yes |
| Companies | Select which companies this user is responsible for | Yes |

**Step 3:** Click **Save** to create the user.

**Step 4:** Inform the user of their employee number and temporary password.

### 13.4 Editing a User

1. Find the user in the list.
2. Click the **Edit** button.
3. Modify any fields as needed.
4. Click **Save** to apply changes.

Changes take effect immediately. If you change a user's role, their access permissions update on their next page load.

### 13.5 Deleting a User

1. Find the user in the list.
2. Click the **Delete** button.
3. A confirmation dialog will appear: "Are you sure you want to delete this user? This action cannot be undone."
4. Click **Confirm** to delete, or **Cancel** to go back.

**Warning:** Deleting a user is permanent. Consider disabling the user instead if they may need to be reactivated in the future. Deleted users' historical submissions and audit records are retained.

### 13.6 Assigning Roles

When creating or editing a user, select their role from the role dropdown:

| Role | Description | When to Assign |
|------|-------------|----------------|
| **User** | Basic access - upload files and view own history | Clerks and data capturers |
| **Manager** | Supervisory access - manage deadlines and view reports | Team leads and supervisors |
| **Admin** | Administrative access - manage users, companies, municipalities | Operations managers and IT administrators |
| **Super Admin** | Full unrestricted access | System owners and senior IT staff |

**Principle of least privilege:** Always assign the minimum role necessary for the user's job function.

### 13.7 Assigning Municipalities and Companies

Each user must be assigned to at least one municipality and one company. These assignments determine:

- Which municipalities and companies appear in the user's upload dropdowns
- Which deadlines the user can be assigned to
- Which submissions appear in the user's history

To manage assignments:

1. Edit the user (see Section 13.4).
2. In the **Municipalities** field, select or deselect municipalities.
3. In the **Companies** field, select or deselect companies.
4. Click **Save**.

**Note:** Every company submits to every municipality. The assignments control which users are responsible for which municipality-company combinations, not which companies are linked to which municipalities.

---

## 14. CAPS Data Synchronisation

The Submission Tracker integrates with CAPS (Casey Application Platform System) for reference data including municipalities, companies, members, and policies. CAPS data synchronisation keeps the Tracker's reference data up to date.

### 14.1 Who Can Manage CAPS Sync?

Only **Admins** and **Super Admins** can view the CAPS sync panel and trigger synchronisation.

### 14.2 How CAPS Sync Works

- The Submission Tracker uses the logged-in user's CAPS credentials (SSO JWT) to authenticate with the CAPS API. No hardcoded credentials are used.
- Synchronisation pulls the latest municipality and company data from CAPS into the Tracker.
- The sync process runs in the background and typically completes within a few seconds.

### 14.3 Automatic Synchronisation Schedule

| Trigger | When | Description |
|---------|------|-------------|
| **Scheduled daily** | 02:30 (server time) | Automatic nightly sync to keep data fresh |
| **First login (empty data)** | On admin's first login if no CAPS data exists | Ensures the system has baseline data from day one |

### 14.4 Manual Synchronisation

You can trigger a manual sync at any time from the Dashboard:

**If no CAPS data has been synced yet:**

```
+------------------------------------------------------------------+
| CAPS DATA SYNC                                      [Warning]     |
|                                                                    |
|  No CAPS data has been synchronised yet.                          |
|  Municipality and company data is required for the system         |
|  to function correctly.                                            |
|                                                                    |
|  [Sync from CAPS Now]                                              |
+------------------------------------------------------------------+
```

1. Click the **"Sync from CAPS Now"** button.
2. The system connects to the CAPS API using your credentials.
3. A loading indicator appears while the sync is in progress.
4. Once complete, the panel updates to show the number of municipalities and companies imported.

**If CAPS data already exists:**

```
+------------------------------------------------------------------+
| CAPS DATA SYNC                                                     |
|  Municipalities: 24 | Companies: 156 | Last sync: 10:32 today    |
|  [Refresh Data]                                                    |
+------------------------------------------------------------------+
```

1. Click the **"Refresh Data"** button.
2. The system performs a delta sync, updating any changed records.
3. The "Last sync" timestamp updates upon completion.

### 14.5 What Gets Synchronised

| Data Type | Direction | Description |
|-----------|-----------|-------------|
| **Municipalities** | CAPS to Tracker | Names, codes, and details of all municipalities |
| **Companies** | CAPS to Tracker | Names, codes, and details of all deduction companies |
| **Members** | CAPS to Tracker | Member records used for verification |
| **Policies** | CAPS to Tracker | Policy records used for verification |
| **User profiles** | Bidirectional | SSO profile data synchronised within 5 seconds |

### 14.6 Troubleshooting CAPS Sync

| Problem | Possible Cause | Solution |
|---------|---------------|----------|
| Sync button does nothing | CAPS API is unreachable | Check that CAPS is running and accessible from the server |
| "Authentication failed" error | Your CAPS session has expired | Log out and log back in to refresh your SSO token |
| Data counts seem low | Sync was partial | Click "Refresh Data" again to retry |
| Sync takes very long | Large data set or slow connection | Wait up to 2 minutes; if it still does not complete, contact IT |
| "No data" warning persists after sync | CAPS API returned empty results | Verify that CAPS has data for your organisation |

---

## 15. Reports and Exports

The Reports section provides summaries and analytics about submissions, deadlines, and compliance. Available to Admins, Super Admins, and Managers.

### 15.1 Navigating to Reports

1. Click **Reports** in the main navigation bar.
2. Select the report type you wish to view.

### 15.2 Upload Summary Report

This report provides an overview of all uploads across the system.

```
+------------------------------------------------------------------+
| UPLOAD SUMMARY REPORT                                              |
|                                                                    |
|  Period: [April 2026 v]                                           |
|                                                                    |
|  Totals by Status:                                                 |
|  +------------+-------+                                           |
|  | Pending    |    23 |                                           |
|  | Processing |    45 |                                           |
|  | Completed  |   112 |                                           |
|  | Total      |   180 |                                           |
|  +------------+-------+                                           |
|                                                                    |
|  By Municipality:                                                  |
|  +-------------------+----------+------------+-----------+        |
|  | Municipality      | Pending  | Processing | Completed |        |
|  +-------------------+----------+------------+-----------+        |
|  | City of Tshwane   |    5     |     12     |     28    |        |
|  | eThekwini Metro   |    3     |      8     |     22    |        |
|  | Mangaung Metro    |    8     |     10     |     15    |        |
|  +-------------------+----------+------------+-----------+        |
|                                                                    |
|  By Company:                                                       |
|  +-------------------+----------+------------+-----------+        |
|  | Company           | Pending  | Processing | Completed |        |
|  +-------------------+----------+------------+-----------+        |
|  | ABC Insurance     |    2     |      5     |     18    |        |
|  | XYZ Finance       |    3     |      4     |     15    |        |
|  +-------------------+----------+------------+-----------+        |
|                                                                    |
|  [Export to CSV]  [Export to Excel]                                |
+------------------------------------------------------------------+
```

### 15.3 Deadline Summary Report

This report shows deadline compliance across municipalities.

```
+------------------------------------------------------------------+
| DEADLINE SUMMARY REPORT                                            |
|                                                                    |
|  Period: [April 2026 v]                                           |
|                                                                    |
|  Overall Compliance Rate: 87%                                      |
|                                                                    |
|  +-------------------+-----------+----------+--------+-----------+|
|  | Municipality      | Deadline  | Assigned | Done   | Compliance||
|  +-------------------+-----------+----------+--------+-----------+|
|  | City of Tshwane   | 15 Apr    |    15    |   13   |    87%    ||
|  | eThekwini Metro   | 20 Apr    |    10    |   10   |   100%    ||
|  | Mangaung Metro    | 10 Apr    |     8    |    5   |    63%    ||
|  +-------------------+-----------+----------+--------+-----------+|
|                                                                    |
|  Overdue Items: 6                                                  |
|  +-------------------+----------+----------+------------------+   |
|  | Municipality      | Company  | User     | Days Overdue     |   |
|  +-------------------+----------+----------+------------------+   |
|  | Mangaung Metro    | ABC Ins  | T.Mokoen | 13 days          |   |
|  | Mangaung Metro    | DEF Co   | T.Mokoen | 13 days          |   |
|  | Mangaung Metro    | GHI Ltd  | L.Molefe | 13 days          |   |
|  +-------------------+----------+----------+------------------+   |
|                                                                    |
|  [Export to CSV]  [Export to Excel]                                |
+------------------------------------------------------------------+
```

### 15.4 Exporting Reports

All reports can be exported for offline use:

1. Navigate to the desired report.
2. Apply any filters (period, municipality, etc.).
3. Click **"Export to CSV"** for a comma-separated file (opens in any spreadsheet application).
4. Or click **"Export to Excel"** for a formatted `.xlsx` file (opens in Microsoft Excel).
5. The file downloads to your browser's default download location.

### 15.5 Report Best Practices

- Run the Upload Summary report weekly to monitor progress.
- Run the Deadline Summary report at the start of each month to plan.
- Export reports before management meetings for offline reference.
- Use the overdue items section to prioritise follow-ups.

---

## 16. Step-by-Step Workflows

This section provides complete workflows for the most common tasks in the Submission Tracker.

### 16.1 Workflow: Complete Monthly Submission (Clerk)

This workflow describes the end-to-end process for a clerk to complete their monthly submissions.

```
Step 1: Log in
    |
    v
Step 2: Check Dashboard for assignments and deadlines
    |
    v
Step 3: Identify which municipality-company pairs need attention
    |
    v
Step 4: For each assignment:
    |   a. Go to Uploads page
    |   b. Select municipality
    |   c. Select company
    |   d. Upload email file (.eml or .msg)
    |   e. Upload workings file (.xlsx or .csv) if available
    |   f. Upload systems import file (.xlsx or .csv) if available
    |   g. Click Submit
    |   h. Verify success message
    |   i. Check verification results
    |
    v
Step 5: Return to Dashboard and confirm completion rate
    |
    v
Step 6: Address any verification discrepancies (see Section 9)
    |
    v
Step 7: Log out when finished
```

**Estimated time per submission:** 2-5 minutes depending on file availability.

### 16.2 Workflow: Set Up Monthly Deadlines (Manager/Admin)

This workflow describes how to create deadlines for the upcoming month.

```
Step 1: Log in as Manager or Admin
    |
    v
Step 2: Navigate to Deadlines > Municipalities
    |
    v
Step 3: For each municipality:
    |   a. Select the municipality from the list
    |   b. Navigate to the upcoming month on the calendar
    |   c. Click the target deadline date
    |   d. In the deadline form:
    |       - Assign users to companies
    |       - Ensure all companies are covered
    |   e. Save the deadline
    |
    v
Step 4: Verify that notifications have been sent to assigned users
    |
    v
Step 5: Review the Dashboard to confirm all deadlines are created
```

**Tip:** Set up deadlines for the entire month in one session to avoid forgetting any municipality.

### 16.3 Workflow: Onboard a New User (Admin)

This workflow describes how to add a new team member to the system.

```
Step 1: Log in as Admin or Super Admin
    |
    v
Step 2: Navigate to Admin > Users
    |
    v
Step 3: Click "+ Create User"
    |
    v
Step 4: Fill in user details:
    |   - Employee number
    |   - Full name
    |   - Email address
    |   - Temporary password
    |   - Role (User, Manager, Admin, or Super Admin)
    |
    v
Step 5: Assign municipalities
    |   - Select all municipalities the user will work with
    |
    v
Step 6: Assign companies
    |   - Select all companies the user will be responsible for
    |
    v
Step 7: Click Save
    |
    v
Step 8: Communicate the following to the new user:
    |   - Application URL
    |   - Employee number
    |   - Temporary password
    |   - Instructions to change password on first login
    |
    v
Step 9: Add the user to upcoming deadlines (Section 11.4)
```

### 16.4 Workflow: Investigate a Low Verification Score (Clerk/Manager)

When a verification score is below 70% (red), follow this investigation workflow.

```
Step 1: Open the verification results for the submission (Section 9)
    |
    v
Step 2: Check the "Members Missing" tab
    |   - Are these members supposed to be in this submission?
    |   - If yes: contact the municipality to request updated data
    |   - If no: they may have been removed; document the reason
    |
    v
Step 3: Check the "Policies Missing" tab
    |   - Are these policies still active?
    |   - If yes: contact the deduction company for clarification
    |   - If no: they may have lapsed; document the reason
    |
    v
Step 4: Check the "Premium Mismatch" tab
    |   - Compare uploaded amounts vs. CAPS amounts
    |   - Are the differences due to recent changes?
    |   - If yes: wait for CAPS to be updated, then re-verify
    |   - If no: investigate the source file for data entry errors
    |
    v
Step 5: If corrections are needed, re-upload the files
    |   - Follow the re-upload process (Section 6.4)
    |   - Select an appropriate reason type
    |   - Add a detailed note
    |
    v
Step 6: Re-verify the updated submission
    |
    v
Step 7: If the score is still low, escalate to your manager
```

### 16.5 Workflow: Handle Overdue Assignments (Manager)

When the Dashboard shows overdue assignments, follow this workflow.

```
Step 1: Log in and check the Dashboard right panel for overdue items
    |
    v
Step 2: For each overdue item, determine the reason:
    |   a. Contact the assigned user
    |   b. Determine if the files are available
    |   c. Identify any blockers (missing data, municipality delays, etc.)
    |
    v
Step 3: Take corrective action:
    |   Option A: If the user can complete it, set a revised deadline
    |   Option B: If the user is unavailable, reassign the companies
    |             to another user (Section 11.5)
    |   Option C: If files are not yet available from the municipality,
    |             document the delay and notify the Admin
    |
    v
Step 4: Monitor the Dashboard daily until all overdue items are resolved
    |
    v
Step 5: Review processes to prevent recurrence:
        - Were deadlines set too early?
        - Does the user need additional training?
        - Are there systemic delays from certain municipalities?
```

### 16.6 Workflow: Initial CAPS Data Setup (Admin)

When setting up the Submission Tracker for the first time, CAPS data must be imported.

```
Step 1: Log in as Admin or Super Admin
    |
    v
Step 2: On the Dashboard, locate the CAPS Sync panel
    |   - It should show an amber warning: "No CAPS data has been
    |     synchronised yet"
    |
    v
Step 3: Click "Sync from CAPS Now"
    |   - Ensure you have an active CAPS session (SSO must be working)
    |   - Wait for the sync to complete (progress indicator shown)
    |
    v
Step 4: Verify the results
    |   - Check the municipality count (should match your known total)
    |   - Check the company count
    |   - If counts seem low, click "Refresh Data" to retry
    |
    v
Step 5: Navigate to Admin > Users
    |   - Create user accounts (Section 13.3)
    |   - Assign municipalities and companies to each user
    |
    v
Step 6: Navigate to Deadlines
    |   - Create deadlines for the current month (Section 11.4)
    |   - Assign users to companies within each deadline
    |
    v
Step 7: Inform all users that the system is ready for use
```

---

## 17. Tips and Best Practices

### 17.1 For Clerks (User Role)

| Tip | Why It Matters |
|-----|----------------|
| Upload all three file types in one session | Gets your submission to "Completed" status immediately |
| Check the Dashboard first thing every morning | See new assignments and approaching deadlines at a glance |
| Address overdue items before new submissions | Overdue items affect your compliance rate and may trigger escalations |
| Use clear, descriptive filenames | Makes it easier to identify files during preview and audit |
| Check verification results after every upload | Catching discrepancies early saves time later |
| Do not wait until deadline day to upload | Network issues or system maintenance could prevent last-minute uploads |
| Keep your notification inbox tidy | Mark read notifications as read so new ones stand out |

### 17.2 For Managers

| Tip | Why It Matters |
|-----|----------------|
| Set deadlines at least one week in advance | Gives clerks adequate preparation time |
| Assign backup users for critical municipalities | Prevents bottlenecks when primary users are absent |
| Review the Deadline Summary report weekly | Identifies compliance trends before they become problems |
| Follow up on red verification scores promptly | Low scores may indicate systemic data issues |
| Export reports before month-end meetings | Have data ready for management discussions |
| Communicate deadline changes via both the system and email | Ensures no one misses important updates |

### 17.3 For Administrators

| Tip | Why It Matters |
|-----|----------------|
| Run CAPS sync weekly even though it auto-runs daily | Confirms the automatic schedule is working |
| Apply the principle of least privilege for roles | Limits risk of accidental data changes |
| Review audit logs monthly | Detects unusual activity early |
| Disable (rather than delete) departed employees | Preserves their historical data and audit trail |
| Keep user assignments current | Prevents orphaned municipality-company pairs with no responsible user |
| Test SSO connectivity after CAPS updates | Ensures login continues to work after system changes |
| Document any manual overrides or exceptions | Maintains the integrity of the audit trail |

### 17.4 General File Upload Tips

- **Email files:** Save emails directly from Outlook as `.msg` files, or export from webmail as `.eml` files.
- **Spreadsheet files:** Ensure `.xlsx` files are not password-protected before uploading.
- **CSV files:** Use UTF-8 encoding to avoid character issues with South African names and addresses.
- **File size:** If a file is unusually large (over 25 MB), consider splitting it or compressing attachments.
- **Re-uploads:** Always provide a clear, specific reason when re-uploading. "Updated data" is better than "re-upload."

---

## 18. Common Errors and Solutions

### 18.1 Login Errors

| Error | Possible Cause | Solution |
|-------|---------------|----------|
| "Invalid credentials" | Wrong employee number or password | Double-check your employee number (not email). Reset your password if forgotten. |
| "Account is disabled" | Your account has been deactivated | Contact your administrator to reactivate your account. |
| "SSO authentication failed" | CAPS session has expired or CAPS is down | Use the manual login form instead. If the problem persists, contact IT. |
| Login page keeps reloading | Browser cookies are blocked | Enable cookies for the application URL in your browser settings. |
| "Too many login attempts" | Multiple failed login attempts | Wait 15 minutes before trying again, or contact your administrator. |

### 18.2 Upload Errors

| Error | Possible Cause | Solution |
|-------|---------------|----------|
| "Please select a municipality" | No municipality was chosen | Select a municipality from the dropdown before submitting. |
| "Please select a company" | No company was chosen | Select a company from the dropdown. If the dropdown is empty, you may not be assigned to any companies for the selected municipality. Contact your admin. |
| "Email file is required" | No email file was attached | Attach an `.eml` or `.msg` file. This is mandatory for all submissions. |
| "Invalid file type" | The uploaded file has an unsupported extension | Ensure email files are `.eml` or `.msg`, and spreadsheets are `.xlsx` or `.csv`. |
| "File too large" | The file exceeds the maximum upload size | Reduce the file size by compressing attachments or splitting the file. Contact IT for the current size limit. |
| "A submission already exists" (with re-upload fields) | A submission for this municipality-company pair was made within the last 30 days | Select a reason type and add a note explaining the re-upload, then submit. |
| Upload appears to hang | Large file or slow network connection | Wait at least 2 minutes. If still stuck, check your internet connection and try again. Do not click Submit multiple times. |
| "No companies available" dropdown empty | You are not assigned to any companies for the selected municipality | Contact your administrator to update your company assignments. |

### 18.3 Verification Errors

| Error | Possible Cause | Solution |
|-------|---------------|----------|
| "Verification failed" | CAPS API is unreachable | Wait a few minutes and try again. If the issue persists, contact IT. |
| Verification score is 0% | The uploaded file could not be parsed | Ensure the spreadsheet is properly formatted with recognisable column headers. |
| "No members found in file" | The uploaded file does not contain member data | Verify you uploaded the correct file. Check that the spreadsheet has ID numbers and member names. |
| Verification results show all members as "Missing" | The file format does not match expected column layout | Check that your spreadsheet columns match the expected format. Contact your admin for a template. |

### 18.4 Deadline Errors

| Error | Possible Cause | Solution |
|-------|---------------|----------|
| Cannot click on calendar dates | You do not have permission to create deadlines | Only Managers, Admins, and Super Admins can create deadlines. |
| "No users available for assignment" | No users are assigned to the selected municipality | Assign users to the municipality first (Admin > Users). |
| Deadline not appearing on Dashboard | The deadline date may be in a different month | Navigate to the correct month on the calendar. |

### 18.5 CAPS Sync Errors

| Error | Possible Cause | Solution |
|-------|---------------|----------|
| "Sync from CAPS" button not visible | You are not an Admin or Super Admin | Only Admins and Super Admins can trigger CAPS sync. |
| "Authentication failed" during sync | Your CAPS JWT has expired | Log out of the Submission Tracker and log back in to refresh your SSO token. |
| Sync completes but shows 0 municipalities | CAPS returned empty data | Verify that CAPS is operational and has data for your organisation. Contact IT. |
| "Connection timeout" during sync | Network issue between the Tracker and CAPS | Check network connectivity. The CAPS server may be undergoing maintenance. Try again later. |

### 18.6 General Errors

| Error | Possible Cause | Solution |
|-------|---------------|----------|
| "403 Forbidden" | You do not have permission for this page | You may be trying to access a page above your role level. Contact your admin if you believe this is incorrect. |
| "404 Not Found" | The page or resource does not exist | Check the URL. Navigate using the menu instead of typing URLs directly. |
| "500 Internal Server Error" | A server-side issue occurred | Refresh the page. If the error persists, contact IT and note the time and what you were doing. |
| Page loads slowly or is unresponsive | Network latency or server load | Refresh the page. Clear your browser cache if the problem persists. |
| Data appears outdated | Browser cache showing old data | Press Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac) to force a hard refresh. |
| Downloaded export file is empty | No data matches the current filters | Adjust your report filters to include a broader date range or fewer restrictions. |

---

## 19. Frequently Asked Questions (FAQ)

### General

**Q: What is the Submission Tracker?**
A: The Submission Tracker is a web application used by Casey & Associates to manage, track, and verify municipal payroll deduction file submissions. It replaces manual spreadsheet-based tracking with an automated digital workflow.

**Q: Who can use the Submission Tracker?**
A: All Casey & Associates staff who are involved in the payroll deduction submission process. Your administrator will create an account for you and assign the appropriate role.

**Q: Is my data secure?**
A: Yes. The system uses role-based access control, encrypted connections, and a complete audit trail. Member personal information (such as SA ID numbers) is protected in accordance with POPIA requirements.

**Q: Can I access the Submission Tracker from my phone?**
A: The application is designed for desktop browsers. While it may work on tablets, the full experience is optimised for screens of 1280x720 resolution or higher.

### Login and Authentication

**Q: What is my username?**
A: Your username is your employee number (not your email address). If you do not know your employee number, contact your administrator.

**Q: I forgot my password. How do I reset it?**
A: Contact your administrator. They can reset your password from the Admin > Users page.

**Q: What is SSO and how does it work?**
A: SSO (Single Sign-On) allows you to log into the Submission Tracker automatically if you are already logged into CAPS. The two systems share your authentication so you do not need to enter credentials twice. The synchronisation happens within 5 seconds.

**Q: SSO is not working. What should I do?**
A: If SSO is paused or CAPS is down, use the manual login form. Enter your employee number and password as usual. The manual login is always available regardless of SSO status.

**Q: Can I be logged in on multiple devices at the same time?**
A: Contact your IT department for the current policy on concurrent sessions.

### Uploads

**Q: What file types can I upload?**
A: Email files must be `.eml` or `.msg` format. Spreadsheets (workings and systems import files) must be `.xlsx` or `.csv` format.

**Q: Do I have to upload all three files at once?**
A: No. Only the email file is required for initial submission. You can upload the workings and systems import files later. However, the submission status only reaches "Completed" when all three file types are present.

**Q: Can I delete an upload?**
A: No. Uploads cannot be deleted as they form part of the audit trail. If you uploaded the wrong file, re-upload the correct file using the re-upload process (Section 6.4) and explain the error in the re-upload note.

**Q: What happens if I upload the wrong file?**
A: Re-upload the correct file following the re-upload process. Select "Correction" as the reason type and provide a note explaining what was wrong. The original upload remains in the history for audit purposes.

**Q: Why can I not see any companies in the dropdown?**
A: Your company dropdown is filtered to show only companies assigned to you for the selected municipality. If it is empty, contact your administrator to update your company assignments.

**Q: Is there a file size limit?**
A: Yes. Contact your IT department for the current maximum file size. If your file exceeds the limit, consider compressing attachments or splitting the data.

**Q: What is a re-upload and when would I need one?**
A: A re-upload occurs when you submit files for a municipality-company pair that already has a submission within the last 30 days. This might happen when you need to correct errors, submit updated data, or respond to a request from the municipality. You will need to provide a reason type and a descriptive note.

### Verification

**Q: What does the verification score mean?**
A: The verification score is a percentage showing how well your uploaded data matches CAPS records. A higher score means fewer discrepancies. Green (above 90%) is excellent, amber (71-90%) needs attention, and red (70% or below) requires investigation.

**Q: Why does my verification show members as "missing"?**
A: Members appear as "missing" when they exist in CAPS but are not found in your uploaded file. This could mean the municipality did not include them in the deduction run, or there may be a formatting issue in your file.

**Q: Can I re-run verification without re-uploading?**
A: Yes. Click the "Verify Members & Policies" button on any submission in your upload history to run a fresh verification against the latest CAPS data.

**Q: The premium amounts do not match. What should I do?**
A: Check the Premium Mismatch tab for details. The difference could be due to recent premium adjustments, data entry errors, or timing differences between when the municipality processed the deduction and when CAPS was updated. Investigate the cause and escalate to your manager if needed.

### Deadlines

**Q: How do I know when my submissions are due?**
A: Check the Dashboard. The "Upcoming Deadlines" panel on the left shows your approaching deadlines with the number of days remaining. You will also receive a notification when a new deadline is assigned to you.

**Q: Can I change a deadline?**
A: Only Managers, Admins, and Super Admins can create or modify deadlines. If you believe a deadline is incorrect, contact your manager.

**Q: What happens when a deadline passes and I have not submitted?**
A: The assignment moves to the "Overdue" section on the Dashboard (displayed in red). Your manager will be notified of the overdue item. Complete the submission as soon as possible.

### Notifications

**Q: How do I stop receiving notifications?**
A: Notifications are integral to the system's workflow and cannot be turned off. However, you can mark them as read or clear them to keep your inbox manageable.

**Q: I did not receive a notification for a new deadline. Why?**
A: Notifications are sent when a deadline is created and you are assigned to it. If you were assigned to the deadline after it was created, you may not have received the initial notification. Check the Deadlines page directly.

### Reports

**Q: Can I run reports as a Clerk (User role)?**
A: No. Reports are available to Managers, Admins, and Super Admins only. If you need a specific report, ask your manager.

**Q: What export formats are available?**
A: Reports can be exported as CSV (opens in any spreadsheet application) or Excel (.xlsx) format.

### CAPS Synchronisation

**Q: What is CAPS?**
A: CAPS (Casey Application Platform System) is the main business platform used by Casey & Associates. It contains the master records for municipalities, companies, members, and policies. The Submission Tracker synchronises with CAPS to keep its reference data current.

**Q: How often does CAPS data sync?**
A: Automatically every day at 02:30 server time. Admins can also trigger a manual sync at any time from the Dashboard.

**Q: The CAPS sync panel shows old data. What should I do?**
A: Click the "Refresh Data" button to trigger a manual sync. If the data still appears outdated after the sync completes, verify that CAPS itself has the latest information.

**Q: Does the Submission Tracker send data back to CAPS?**
A: User profile data is synchronised bidirectionally (changes in either system are reflected in both). Submission data (uploads, verification results) is stored in the Tracker and does not flow back to CAPS.

---

## 20. Glossary

| Term | Definition |
|------|------------|
| **Assignment** | A specific municipality-company pair that a user is responsible for submitting files for within a given deadline period |
| **Audit trail** | A chronological record of all actions taken in the system, including who did what and when, maintained for compliance purposes |
| **CAPS** | Casey Application Platform System - the main business platform that serves as the source of truth for members, policies, municipalities, and companies |
| **Compliance rate** | The percentage of assignments that have been completed by their deadline |
| **CSV** | Comma-Separated Values - a plain text file format for spreadsheet data, with values separated by commas |
| **Deadline** | The date by which all submissions for a specific municipality must be completed |
| **Deduction company** | An insurance provider, loan company, or other financial institution whose premiums are deducted from municipal employee salaries |
| **Delta sync** | A synchronisation process that only transfers records that have changed since the last sync, rather than all records |
| **EML** | A standard email file format used by many email clients |
| **Employee number** | Your unique staff identifier used to log into the system (not your email address) |
| **JWT** | JSON Web Token - a secure authentication token used for SSO between CAPS and the Submission Tracker |
| **MSG** | A Microsoft Outlook email file format |
| **Municipality** | A local government authority (city or town council) that processes payroll deductions for its employees |
| **Overdue** | An assignment whose deadline has passed without the submission being completed |
| **Payroll deduction** | An amount withheld from an employee's salary and paid to a third party (such as an insurance company) |
| **POPIA** | Protection of Personal Information Act - South African legislation governing the handling of personal data |
| **Premium** | The monthly amount deducted from an employee's salary for an insurance policy or loan repayment |
| **Re-upload** | Submitting new files for a municipality-company pair that already has a submission within the last 30 days |
| **Reference number** | A unique identifier automatically assigned to each submission |
| **Role** | A set of permissions that determines what a user can see and do in the system (User, Manager, Admin, or Super Admin) |
| **SSO** | Single Sign-On - a mechanism that allows you to authenticate once (in CAPS) and gain access to the Submission Tracker without entering credentials again |
| **Submission** | A set of uploaded files (email, workings, systems import) for a specific municipality-company pair |
| **Systems import file** | A spreadsheet file formatted for direct import into the processing system |
| **Verification** | The process of comparing uploaded submission data against CAPS records to identify discrepancies in members, policies, and premium amounts |
| **Verification score** | A percentage indicating how closely the uploaded data matches CAPS records |
| **Workings file** | A spreadsheet containing the manual calculations or reconciliation for a deduction submission |
| **XLSX** | Microsoft Excel file format for spreadsheets |
| **ZAR** | South African Rand - the currency used for all financial amounts in the system |

---

## 21. Support and Contact

### 21.1 Getting Help

If you encounter an issue not covered in this documentation:

1. **Check this document first.** Use the Table of Contents to find the relevant section. The FAQ (Section 19) and Common Errors (Section 18) cover the most frequent issues.

2. **Contact your manager or administrator.** They can resolve most operational issues including role changes, assignment updates, and deadline modifications.

3. **Contact the IT department.** For technical issues such as login failures, SSO problems, CAPS connectivity, or server errors.

### 21.2 Reporting a Bug

When reporting a technical issue, provide the following information:

- **Your employee number** (so the team can check your account and role)
- **Date and time** the issue occurred
- **What you were trying to do** (step by step)
- **What you expected to happen**
- **What actually happened** (include the exact error message if one was displayed)
- **Your browser name and version** (e.g., Chrome 120)
- **Screenshots** if possible (press Print Screen or use the Snipping Tool)

### 21.3 Requesting New Features or Changes

Feature requests and enhancement suggestions can be submitted to your administrator, who will evaluate and prioritise them with the development team.

### 21.4 Training Resources

- This user documentation is your primary reference.
- New users should complete the workflows in Section 16 under supervision during their first week.
- Administrators should familiarise themselves with all sections, particularly Sections 13, 14, and 15.
- Refresher training should be provided whenever significant system updates are released.

---

**Document Control**

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | April 2026 | Casey & Associates | Initial release |

---

*This document is confidential and intended for internal use by Casey & Associates staff only. Unauthorised distribution is prohibited.*
