# ID Card Generation Feature Implementation Plan

## 1. Goal

Add a complete ID card request, review, approval, generation, and download workflow to the existing RTTC admissions portal for:

- Students
- Faculty/Staff

The two request forms will have fixed public URLs, but no link or button will be added to the public website navigation, home page, student dashboard, or footer. Only the admin ID Card page will expose controls to copy each URL or open it in a new tab.

This document is the implementation plan only. No ID card feature code or database change is included in this planning step.

## 2. Confirmed Requirements

- Add an `ID Card` item to the admin sidebar.
- Do not show ID card links anywhere on the public-facing site.
- Provide two unlisted static public form URLs:
  - Student ID card application
  - Faculty/Staff ID card application
- New submissions enter the admin panel with `pending` status.
- The admin page must use the existing table style and server-side pagination.
- Do not add statistics cards to the ID Card admin page.
- Put the form URL controls on the same row as the search/filter controls on desktop.
- Each form must have Copy URL and Open in New Tab controls in the admin panel.
- Pending table records must provide Approve and Delete actions; Approve opens the full review page before any state change.
- Review must show all submitted information and a live front/back card preview before approval.
- The primary review action is `Approve & Download`.
- The issue date is the approval date in the `Asia/Kolkata` timezone.
- The validity date is one year from the issue date.
- One successful download action produces a ZIP containing:
  - A two-page PDF, with front and back as separate pages
  - A high-resolution front PNG
  - A high-resolution back PNG
- After the ZIP has been generated and its browser download has started, mark the record `done`.
- If export fails after approval, retain `approved` status and allow the admin to retry without changing the original issue date.
- Student signature, college stamp, and principal signature areas remain blank for physical completion by the authority.
- Use the RTTC logo as a low-opacity watermark, matching the supplied reference.
- Create the eventual additive production SQL at `database/ID_CARD.SQL` so it can be pasted directly into phpMyAdmin.

## 3. Repository Findings

### 3.1 Current Architecture

- The application is procedural PHP with MySQL/MariaDB through `mysqli`; it is not React or TypeScript.
- Pages combine request handling, SQL, validation, and server-rendered PHP markup.
- Reusable HTML is implemented as PHP components, such as `payment/components/application-form.php`.
- Clean URLs must be added in both `helpers/RouteHelper.php` and `.htaccess`.
- Admin pages use `admin/layouts/admin.php` and the shared styles in `assets/css/admin.css`.
- Admin authentication is available through `SecurityHelper::requireAdminAuth()`.
- The strongest existing table reference is `admin/students/index.php`, which uses prepared filters and server-side pagination.
- The strongest existing review/action reference is `admin/queries/index.php`.
- The strongest existing transactional API reference is `api/admin-delete-payment.php`.
- Browser PDF generation already uses `html2canvas` and `jsPDF` in `payment/confirmation.php`.
- There is no server-side PDF/PNG generator, ZIP library, QR library, or card-generation package.

### 3.2 Data and Security Findings

- Student admissions data exists, but the requested student ID card form is a separate public submission and must not assume the visitor is logged in.
- There is no faculty/staff database model.
- Existing document uploads validate filename extension and size only. That is not sufficient for ID card photos.
- Existing uploads are under the web root and are normally linked directly. ID card photos should not follow that pattern.
- The admin role column exists, but role authorization is not currently enforced. This feature must load the real role at login and prevent `viewer` accounts from approving, downloading, marking done, or deleting.
- The current schema files have drift and are not safe canonical migration sources. `database/ID_CARD.SQL` must therefore be standalone, additive, and non-destructive.
- The app currently declares PHP 7.4 in Composer but uses PHP 8.1 language features. New code should remain compatible with the application's effective PHP 8.1+ runtime.

## 4. Proposed User Experience

### 4.1 Public Student Form

Proposed URL:

```text
/id-card/student
```

Fields:

| Field | Type | Required | Rules |
|---|---|---:|---|
| Name | Text | Yes | 2-150 characters |
| C/O | Text | Yes | 2-150 characters |
| Course | Text/select | Yes | Default `B.Ed.`; store submitted snapshot |
| Session | Text/select | Yes | Format such as `2026-27` |
| Roll No. | Text | Yes | 1-50 characters |
| Date of Birth | Date | Yes | Must be a valid past date |
| Contact No. | Tel | Yes | Valid Indian phone format |
| Blood Group | Select | Yes | Controlled list including positive and negative groups |
| Address | Textarea | Yes | 5-500 characters |
| Photo | File | Yes | JPEG/PNG, real image validation, size and dimension limits |
| Declaration | Checkbox | Yes | Consent that submitted information is correct |

The form will include:

- RTTC branding and a clear `Student ID Card Application` heading.
- Client-side convenience validation plus authoritative server-side validation.
- A photo preview before submission.
- A final confirmation preview before POST.
- CSRF protection even though the route is public.
- A honeypot and submission throttle because an unlisted URL is not access control.
- `noindex, nofollow` metadata to discourage search-engine indexing.
- A success screen containing the generated request reference and submission status.
- No public endpoint for retrieving another person's application by reference number.

### 4.2 Public Faculty/Staff Form

Proposed URL:

```text
/id-card/faculty-staff
```

Fields:

| Field | Type | Required | Rules |
|---|---|---:|---|
| Name | Text | Yes | 2-150 characters |
| C/O | Text | Yes | 2-150 characters |
| Department | Text | Yes | 2-150 characters |
| Designation | Text | Yes | 2-150 characters |
| Blood Group | Select | Yes | Controlled list |
| Contact No. | Tel | Yes | Valid Indian phone format |
| Address | Textarea | Yes | 5-500 characters |
| Photo | File | Yes | Same secure image rules as the student form |
| Declaration | Checkbox | Yes | Consent that submitted information is correct |

The same validation, preview, CSRF, anti-spam, no-index, and success behavior applies to this form.

### 4.3 Admin List Page

Proposed URL:

```text
/admin/id-cards
```

Page layout:

- Page title: `ID Card Applications`.
- No statistics cards.
- A single filter/action row.
- Search by request reference, name, contact number, roll number, department, or designation.
- Filter by application type: All, Student, Faculty/Staff.
- Filter by status: All, Pending, Approved, Done.
- Two split-button groups on the same row at desktop widths:
  - Student Form: Copy URL / Open
  - Faculty/Staff Form: Copy URL / Open
- Controls wrap into a readable vertical/mobile layout on small screens.
- Use server-side SQL filtering and pagination; do not load the entire table into browser DataTables.

Table columns:

| Column | Contents |
|---|---|
| Reference | `IDC-S-000001` or `IDC-F-000001`, derived from type and database ID |
| Type | Student or Faculty/Staff badge |
| Applicant | Name and contact number |
| Academic/Work Detail | Course/session/roll or department/designation |
| Submitted | Date and time |
| Status | Pending, Approved, or Done badge |
| Action | Approve/Delete for pending rows; Review/Download for issued rows |

Behavior by status:

| Status | Meaning | Available actions |
|---|---|---|
| `pending` | Submitted and waiting for admin review | Approve, Delete |
| `approved` | Approved, but first ZIP generation did not complete | Review, Download |
| `done` | ZIP generation started successfully at least once | Review, Download Again |

The pending-row `Approve` button opens the review page; it does not approve directly from the table. Delete is restricted to pending records so an issued-card snapshot cannot be erased.

### 4.4 Admin Review Page

Proposed URL:

```text
/admin/id-cards/review?id={id}
```

A dedicated page is preferable to a modal because the requirement combines complete information, a readable two-sided preview, and high-resolution export controls.

Desktop layout:

- Submitted details panel on the left.
- Front/back card preview on the right.
- Sticky action area where practical.

Mobile layout:

- Details first.
- Horizontally scrollable or scaled preview second.
- Full-width actions at the bottom.

Actions:

- `Approve & Download` for pending applications.
- `Download` for approved applications.
- `Download Again` for done applications.
- `Delete` with a destructive confirmation dialog for pending applications only.
- `Back to ID Cards` without changing state.

The server must reload the record by ID and build the template from database values. It must not trust record data embedded in table `data-*` attributes.

## 5. Card Template Specification

### 5.1 Component Strategy

The repository does not use TSX. Implement the requested reusable template as a PHP component:

```text
views/components/id-card/template.php
```

The component will receive one normalized application array and render both sides. It must not query the database or perform state changes.

Supporting files:

```text
assets/css/id-card.css
assets/js/id-card-export.js
```

The same component must be used for on-screen review and export so the preview and downloaded files cannot drift.

### 5.2 Print Size

- Use portrait CR80 as the proposed default card canvas: 53.98 mm x 85.60 mm per side.
- Export each side at approximately 638 x 1011 pixels for 300 DPI output.
- Keep all critical content inside a print-safe inset.
- Produce a two-page PDF using the same physical page size.
- Complete one physical print proof before calling the template final; browser preview alone is not sufficient for an exact card layout.

The supplied reference is the visual source, while CR80 provides a standard physical ID card size. Final physical dimensions are a Phase 1 approval gate: the authority must approve CR80 or provide the required non-standard dimensions before card-safe field limits are finalized and before either public form is released.

### 5.3 Front Design

Match the supplied reference as closely as possible within the standard card canvas:

- Dark-blue college header.
- RTTC logo and college name.
- NCTE/Gauhati University/Government affiliation line.
- College address and contact line.
- Thin orange divider.
- Orange `IDENTITY CARD` title capsule.
- Passport photo at the left of the primary details block.
- Compact aligned label/value details.
- Address block below the primary details.
- Date of Issue derived only from `approved_at`.
- Validity date derived as approval date plus one year.
- Three intentionally empty authority areas at the bottom:
  - Student: `Student's Signature`, `College Stamp`, `Principal`
  - Faculty/Staff: `Holder's Signature`, `College Stamp`, `Principal`
- Do not use or import the existing admissions signature upload.
- Do not place a digital stamp or principal signature in the generated card.

Student front values:

- Name
- C/O
- Course
- Session
- Roll No.
- Date of Birth
- Contact No.
- Blood Group
- Address
- Date of Issue
- Valid up to

Faculty/Staff front values:

- Name
- C/O
- Department
- Designation
- Blood Group
- Contact No.
- Address
- Date of Issue
- Valid up to

### 5.4 Back Design

- `INSTRUCTIONS` heading in dark blue.
- Point-wise wording and spacing closely matching the reference.
- Large centered RTTC logo watermark at low opacity behind the text.
- Keep watermark contrast low enough that all instructions remain readable in print.

Student instructions:

1. The holder of this card is a student-teacher of this college.
2. The card holder must follow the rules and regulations of the institution.
3. The card may be produced before any concerned authority for identifying the holder on demand.
4. Please keep this card in safe custody. If the card is lost or damaged, the college authority will not be responsible for any misuse. Inform the college immediately if the card is lost or damaged.
5. A charge of Rs. 100/- will be required to issue a duplicate card.
6. The card holder must wear the card on his/her neck while on campus.

Faculty/Staff instructions:

1. The holder of this card is a faculty/staff member of this college.
2. The card holder must follow the rules and regulations of the institution.
3. The card may be produced before any concerned authority for identifying the holder on demand.
4. Please keep this card in safe custody. If the card is lost or damaged, inform the college authority immediately.
5. A charge of Rs. 100/- will be required to issue a duplicate card.
6. The card holder must wear the card while on campus and during official duty where required.

The exact wording, line wrapping, font sizes, and spacing will be visually calibrated against the supplied image during implementation.

## 6. Workflow and State Transitions

```text
Public submit
    |
    v
 pending --delete--> removed
    |
    | admin approves in transaction
    v
 approved --export retry--> approved
    |
    | PDF + PNG ZIP generated and browser save starts
    v
  done --download again--> done
```

Rules:

- A submission always starts as `pending`.
- Authenticated `admin` and `super_admin` accounts can open full review/photo pages, approve, delete, download, or mark an application done. A `viewer` can inspect the list only and receives `403` from review, photo, and action/export endpoints.
- Approval sets `approved_at` only if it is currently null.
- Repeated downloads never change `approved_at`, issue date, or validity date.
- The valid-until value is calculated from the persisted approval timestamp, not from the browser clock.
- Approval uses a database transaction and row lock to prevent double approval.
- Export starts only after the approval API succeeds and returns the authoritative dates.
- The browser captures front and back once each, creates PNG blobs, inserts those same images into a two-page PDF, and adds all three files to one ZIP.
- The browser calls `mark_done` only after ZIP generation succeeds and the save operation is triggered.
- Browsers cannot prove that a person retained a downloaded file. `done` therefore means that the generated ZIP save was initiated successfully, not that the operating system confirmed permanent storage.
- If `mark_done` fails, keep/revert the visible UI to `approved` and allow retry. The downloaded file remains valid because approval data is already persisted.
- Delete is allowed only while pending and requires a confirmation showing the applicant name and reference.
- Delete removes the database row transactionally and removes its stored photo after commit. Photo cleanup failures are logged for manual cleanup.

## 7. Database Plan

Create this file during implementation:

```text
database/ID_CARD.SQL
```

SQL requirements:

- Additive only.
- No `DROP DATABASE`, `DROP TABLE`, or destructive alteration.
- No `CREATE DATABASE` or hard-coded `USE` statement.
- No default accounts or passwords.
- `InnoDB`, `utf8mb4`, and `utf8mb4_unicode_ci`.
- Compatible with the live MySQL/MariaDB version used by the current hosting environment.
- Safe to paste into the already-selected phpMyAdmin database.
- Include verification queries and clear comments.

### 7.1 `id_card_applications`

One table is sufficient because the student and faculty/staff forms share most workflow data and have a small, fixed set of type-specific fields.

| Column | Proposed SQL type | Purpose |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key and request reference source |
| `application_type` | `ENUM('student','faculty_staff')` | Determines fields/template |
| `full_name` | `VARCHAR(150)` | Snapshot submitted name |
| `care_of` | `VARCHAR(150)` | C/O value |
| `course` | `VARCHAR(100) NULL` | Student only |
| `academic_session` | `VARCHAR(20) NULL` | Student only |
| `roll_number` | `VARCHAR(50) NULL` | Student only |
| `date_of_birth` | `DATE NULL` | Student only |
| `department` | `VARCHAR(150) NULL` | Faculty/staff only |
| `designation` | `VARCHAR(150) NULL` | Faculty/staff only |
| `blood_group` | `VARCHAR(10)` | Controlled form value |
| `contact_number` | `VARCHAR(20)` | Contact number |
| `address` | `VARCHAR(500)` | Printed address snapshot |
| `photo_path` | `VARCHAR(500)` | Private server-side relative path |
| `declaration_accepted_at` | `DATETIME` | Consent record |
| `status` | `ENUM('pending','approved','done')` | Workflow state |
| `approved_at` | `DATETIME NULL` | Immutable first approval timestamp |
| `approved_by` | `INT NULL` | `admin_users.id` |
| `first_downloaded_at` | `DATETIME NULL` | First successful export initiation |
| `last_downloaded_at` | `DATETIME NULL` | Most recent export initiation |
| `download_count` | `INT UNSIGNED DEFAULT 0` | Export attempts marked successful |
| `submission_token_hash` | `CHAR(64)` | Unique one-time form submission token for idempotency |
| `submitted_ip_key` | `CHAR(64) NULL` | HMAC IP key retained with successful submission |
| `created_at` | `DATETIME` | Submission timestamp |
| `updated_at` | `DATETIME` | Last database update |

Indexes and constraints:

- Primary key on `id`.
- Index on `(status, created_at)` for the default pending queue.
- Index on `(application_type, status, created_at)` for filtered queues.
- Index on `contact_number` for admin search.
- Unique index on `submission_token_hash` to prevent double-click, retry, and replay duplicates.
- Foreign key from `approved_by` to `admin_users.id` with `ON DELETE SET NULL`.
- Server validation must enforce student-only and faculty/staff-only required fields because portable SQL checks vary across deployed MySQL/MariaDB versions.

Display references are derived without a second sequence table:

- Student: `IDC-S-` plus zero-padded ID.
- Faculty/Staff: `IDC-F-` plus zero-padded ID.

### 7.2 `id_card_action_log`

Maintain a small audit trail for privileged state changes:

| Column | Proposed SQL type | Purpose |
|---|---|---|
| `id` | `BIGINT UNSIGNED AUTO_INCREMENT` | Primary key |
| `application_id` | `BIGINT UNSIGNED NULL` | Application when it still exists |
| `application_reference` | `VARCHAR(32)` | Stable reference retained after delete |
| `action` | `ENUM('submitted','approved','downloaded','deleted')` | Event type |
| `admin_user_id` | `INT NULL` | Acting admin for privileged actions |
| `notes` | `VARCHAR(500) NULL` | Error-safe event context |
| `created_at` | `DATETIME` | Event time |

The application foreign key should use `ON DELETE SET NULL`. The admin foreign key should also use `ON DELETE SET NULL`. Do not expose update/delete controls for audit rows.

Public submission must insert the application and its `submitted` audit event in one database transaction. If either insert fails, roll back both and remove the newly stored photo.

### 7.3 `id_card_submission_attempts`

Use a small dedicated throttle table so rejected attempts are counted before expensive photo decoding. Successful application rows alone are not a sufficient rate-limit source.

| Column | Proposed SQL type | Purpose |
|---|---|---|
| `ip_key` | `CHAR(64)` | HMAC-SHA-256 of the normalized client IP |
| `bucket_start` | `DATETIME` | Start of the fixed throttle time bucket |
| `attempt_count` | `INT UNSIGNED` | Requests observed in this bucket |
| `updated_at` | `DATETIME` | Most recent request in the bucket |

Use `(ip_key, bucket_start)` as the primary or unique key and index `bucket_start` for cleanup. Generate `ip_key` with an environment-provided server secret, never with a plain unsalted IP hash. Before image decoding, atomically insert the current bucket or increment `attempt_count` with `INSERT ... ON DUPLICATE KEY UPDATE`, then reject counts above the configured limit. Opportunistically delete a bounded number of expired buckets so an attack cannot create an unbounded row per request or an expensive cleanup operation.

### 7.4 SQL Verification Block

At the end of `ID_CARD.SQL`, include non-destructive checks such as:

```sql
SHOW TABLES LIKE 'id_card_%';
SHOW CREATE TABLE id_card_applications;
SHOW CREATE TABLE id_card_action_log;
SHOW CREATE TABLE id_card_submission_attempts;
```

The file must state that it should first be tested on a staging copy and that the live database should be backed up before execution.

## 8. Routes and File Changes

### 8.1 New Files

```text
id-card/student.php
id-card/faculty-staff.php
admin/id-cards/index.php
admin/id-cards/review.php
api/admin-id-card-action.php
api/admin-id-card-photo.php
helpers/IdCardHelper.php
views/components/id-card/template.php
assets/css/id-card.css
assets/js/id-card-forms.js
assets/js/id-card-export.js
database/ID_CARD.SQL
tests/id_card_schema_test.php
tests/id_card_form_contract_test.php
tests/id_card_admin_contract_test.php
tests/id_card_export_contract_test.php
```

If shared public form markup becomes meaningfully duplicated, add one small form component under `views/components/id-card/`; otherwise keep each public form self-contained to match the repository's current style.

### 8.2 Existing Files to Update

| File | Change |
|---|---|
| `config/init.php` | Load `IdCardHelper.php` |
| `config/config.php` | Add ID card photo directory, upload size, image type, and rate-limit secret constants |
| `helpers/RouteHelper.php` | Add public, admin, action, and photo route names |
| `.htaccess` | Add clean URL rewrites; block direct SQL and private ID card photo access |
| `admin/layouts/admin.php` | Add the required `ID Card` sidebar item |
| `admin/login.php` | Select the real admin role and store it in the existing admin session |

No public navbar, homepage, footer, or student dashboard file should be changed to expose either form.

### 8.3 Proposed Named Routes

```text
id-card.student                /id-card/student
id-card.faculty-staff          /id-card/faculty-staff
admin.id-cards                /admin/id-cards
admin.id-cards.review         /admin/id-cards/review
api.admin.id-card-action      /api/admin-id-card-action
api.admin.id-card-photo       /api/admin-id-card-photo
```

## 9. Helper Responsibilities

`helpers/IdCardHelper.php` should contain only reusable domain operations:

- Application type constants and labels.
- Status constants and legal transition checks.
- Request reference formatting.
- Form normalization and server-side validation.
- Controlled blood-group options.
- Approval/validity date formatting.
- Secure photo validation and storage.
- Private photo path resolution.
- Application-to-template data normalization.

Do not place SQL page queries, HTML rendering, or JavaScript generation in the helper.

## 10. Secure Photo Handling

The new feature must not reuse the current extension-only upload implementation.

Server checks:

1. Require `UPLOAD_ERR_OK` and `is_uploaded_file()`.
2. Limit file size, initially 2 MB.
3. Inspect actual MIME with `finfo`; allow JPEG and PNG only.
4. Read image metadata with `getimagesize()` without a full GD decode and reject malformed images.
5. Enforce minimum width/height and maximum width, height, and total pixel count before allocating a GD image.
6. Decode with GD only after the dimension and pixel-count checks pass.
7. Correct EXIF orientation for JPEG when support is available.
8. Re-encode to a standard JPEG to strip metadata and normalize content.
9. Generate a random filename; never use the submitted filename.
10. Store outside direct public access under `storage/uploads/id_cards/`.
11. Serve photos to authenticated admins through `api/admin-id-card-photo.php` after checking the application ID.
12. Prevent directory traversal by resolving database paths against the configured base directory.
13. Delete the newly written file if database insertion fails.
14. Delete the stored photo after a committed application deletion.

The admin photo endpoint must return a strict image content type, `X-Content-Type-Options: nosniff`, private cache headers, and no user-controlled filename.

## 11. Public Form Security

Static unlisted links can still be discovered. Apply these controls:

- GET renders the form; POST handles submission.
- CSRF token on both forms.
- Server-side validation for every field.
- Escape at output with `htmlspecialchars`; store normalized raw values rather than pre-encoded HTML.
- Honeypot field hidden from normal users.
- Minimum form-fill duration to reject instant bot submissions.
- Atomically increment the current `id_card_submission_attempts` time bucket before expensive image work, including for rejected requests.
- Per-IP HMAC throttling with an environment-provided secret, fixed time buckets, conservative limits, bounded expired-bucket cleanup, and a generic error message.
- A one-time submission token whose hash is protected by a unique database index.
- POST body and upload size limits.
- No SQL interpolation; use prepared statements.
- Post/Redirect/Get after a successful submission plus database-backed idempotency to prevent refresh, double-click, retry, and replay duplicates.
- `noindex, nofollow` response metadata.
- Do not reveal whether another matching name, roll number, or contact number already exists.
- Log server errors without returning paths, SQL, or stack traces to the visitor.

Do not claim that the forms are private merely because they are not linked. If abuse becomes a problem, the follow-up upgrade is admin-generated signed links or CAPTCHA.

## 12. Admin Action API

`api/admin-id-card-action.php` will accept POST only and return consistent JSON with appropriate HTTP status codes.

Supported actions:

- `approve`
- `mark_done`
- `delete`

Common requirements:

- Use an API-specific authentication check based on `SessionHelper::isAdminLoggedIn()` and return JSON `401` instead of calling the redirecting `requireAdminAuth()` helper.
- Read the actual admin role from the session and return JSON `403` unless it is `admin` or `super_admin`.
- Validate CSRF with `SecurityHelper::validateCsrfToken()` and return JSON `403` instead of calling the redirecting `verifyCsrf()` helper.
- Validate action against a fixed allowlist.
- Validate application ID as a positive integer.
- Start a transaction and select the application `FOR UPDATE`.
- Enforce legal transitions.
- Write the action log inside the transaction.
- Commit before returning success.
- Return generic errors to the browser and detailed errors to the server log.

Action-specific rules:

- `approve`: only `pending -> approved`; set `approved_at` and `approved_by` exactly once.
- `mark_done`: allow `approved -> done` and `done -> done`; set first/last download timestamps and increment count.
- `delete`: allow only `pending` records and only after explicit UI confirmation; record the audit event, delete the row, commit, and then remove the photo.

The endpoint should follow the transaction, row-lock, response, and logging style in `api/admin-delete-payment.php` rather than the less secure student deletion pattern.

## 13. Export Implementation

Use the existing browser-rendering approach because it is already established in the repository and the required output includes PNG files.

Libraries:

- `html2canvas` for rendering the two template sides.
- `jsPDF` for a two-page custom-size PDF.
- `JSZip` for one ZIP containing all output files.

Prefer pinned, locally hosted browser library files for production reliability. If CDN scripts are retained to match current repository conventions, pin exact versions and display a clear load failure rather than partially approving an application.

Export sequence:

1. Disable all approval/export controls and show a spinner.
2. For pending rows, POST `approve` and receive authoritative status, issue date, and validity date.
3. Update/re-render the card template with returned dates.
4. Wait for logo and photo images plus fonts to finish loading.
5. Capture front and back at export scale with a white background.
6. Convert both canvases to PNG blobs.
7. Build a two-page PDF using the exact same PNG blobs.
8. Create a ZIP with deterministic names.
9. Trigger the ZIP save.
10. POST `mark_done`.
11. Update the page status and buttons without changing approval dates.
12. On failure, show an actionable message and leave the record retryable.

Proposed ZIP naming:

```text
RTTC_ID_CARD_IDC-S-000001.zip
  RTTC_ID_CARD_IDC-S-000001_FRONT.png
  RTTC_ID_CARD_IDC-S-000001_BACK.png
  RTTC_ID_CARD_IDC-S-000001.pdf
```

Do not include submitted phone numbers or names in filenames.

## 14. Validation Rules

Shared:

- Trim surrounding whitespace and collapse accidental repeated spaces where appropriate.
- Preserve normal punctuation in names and addresses.
- Full name and C/O: enforce card-safe form limits established by the print prototype; database columns may remain 150 characters.
- Blood group: one value from a server-owned allowlist.
- Contact: digits with optional leading `+91`; normalize before storage.
- Address: enforce a tested card-safe form limit and safe line breaks; the database column may remain 500 characters.
- Declaration: explicitly accepted.
- Photo: required and validated as described above.

Student-specific:

- Course, session, and roll number required.
- Session format must be validated rather than accepted as arbitrary HTML text.
- Date of birth must parse as a real date and be earlier than today.
- Faculty/staff-only fields must be stored as null.

Faculty/staff-specific:

- Department and designation required.
- Student-only fields must be stored as null.

Card layout safety:

- Define maximum printed lengths and wrap rules.
- Establish and print-test those limits before either public form is released.
- Reject values over the tested card-safe limits during public submission, so every accepted record can be printed.
- Never silently cut off a submitted value in the admin details panel.
- Long values may shrink only within a tested lower font-size limit; otherwise they must be rejected by form validation rather than leaving an unresolvable pending record.

## 15. Testing Plan

Follow the repository's current standalone PHP test style unless a broader PHPUnit migration is approved separately.

### 15.1 Database

- `ID_CARD.SQL` imports successfully on the supported live database version.
- Existing admissions tables and data remain unchanged.
- Required tables, indexes, enum values, and foreign keys exist.
- A second execution is either safely idempotent or clearly documented as unnecessary; no partial destructive behavior is permitted.

### 15.2 Public Forms

- Both routes render without authentication.
- Neither route is linked from public navigation, home, footer, or student dashboard.
- Required fields reject missing/invalid data.
- Cross-type fields cannot be injected through a crafted POST.
- CSRF failure is rejected.
- Honeypot/throttle behavior works.
- Rejected attempts count toward the throttle before image decoding.
- Concurrent double-clicks and replayed POSTs create only one application.
- Invalid MIME, corrupt image, oversized image, undersized image, and non-image files are rejected.
- Successful submission creates one `pending` row and one photo.
- Database failure does not leave an orphan photo.
- Refresh after success does not duplicate the row.

### 15.3 Admin List and Review

- Unauthenticated access redirects to admin login.
- The real admin role is loaded at login; `viewer` can inspect the list but receives `403` from review, photo, approval, download, mark-done, and delete routes.
- Search, type filter, status filter, pagination, and reset work together.
- URL copy controls copy the correct absolute URLs.
- Open controls use a safe new tab with `noopener`.
- No statistics cards are rendered.
- Complete submitted data and the correct type-specific preview are shown.
- Admin pages retrieve photos only through the protected endpoint.
- Delete requires confirmation and cleans up the row/photo.

### 15.4 State and Concurrency

- New records are pending.
- Only pending records can receive their first approval.
- Approval date comes from the server in `Asia/Kolkata` and remains unchanged on retry.
- Validity is exactly one year from approval.
- Concurrent approval requests do not produce different issue dates.
- Failed export remains approved.
- Successful first export marks done.
- Repeat export keeps done, increments count, and preserves issue/validity dates.
- Invalid CSRF, action, ID, and state transitions return appropriate errors.

### 15.5 Template and Export

- Student and faculty/staff templates render only their relevant fields.
- Blank student/holder signature, college stamp, and principal areas remain blank in preview and downloads.
- Logo watermark is present and text remains readable.
- Front and back PNG dimensions are correct.
- PDF has exactly two pages at the intended physical size.
- ZIP contains exactly the expected PDF and two PNG files.
- Preview and exported images use the same component content.
- Long but valid names, C/O values, departments, designations, and addresses remain printable.
- Mobile admin review remains usable even though export uses fixed off-screen card dimensions.

### 15.6 Manual Acceptance

- Compare the card side-by-side with the supplied reference.
- Print both sides at 100% scale on the intended printer/card stock.
- Verify colors, text size, safe margins, photo crop, watermark opacity, and blank signature areas.
- Confirm PDF and PNG output with Chrome and one secondary browser.
- Confirm copy/open controls and responsive admin layout on desktop and mobile.

## 16. Implementation Phases

Each phase should be completed and verified before moving to the next.

### Phase 1: Database and Domain Foundation

- Create `database/ID_CARD.SQL`.
- Add `IdCardHelper.php` constants, normalization, validation, references, and state rules.
- Add config constants and bootstrap inclusion.
- Build and print a minimal card layout prototype to lock physical size and card-safe field limits before public validation is released.
- Add schema/helper contract tests.
- Verify SQL on a disposable database copy.

Deliverable: schema and domain behavior are stable before UI work.

### Phase 2: Secure Public Forms

- Add both clean routes and rewrites.
- Build student and faculty/staff forms.
- Implement secure photo processing and private storage.
- Add CSRF, honeypot, dedicated attempt throttling, one-time submission idempotency, PRG success handling, and tests.
- Insert each application and its submitted audit event atomically.
- Verify that no public navigation exposes the routes.

Deliverable: both forms create valid pending records securely.

### Phase 3: Admin Queue

- Add the sidebar item without an additional shared-layout count query.
- Load the real admin role during login and enforce `viewer` restrictions for this feature.
- Build the no-stats admin table with server-side search/filter/pagination.
- Add same-row Copy URL and Open controls.
- Add protected photo endpoint.
- Add pending-only delete with confirmation, transaction, audit row, and photo cleanup.

Deliverable: admins can find, inspect, and remove submissions.

### Phase 4: Template and Review

- Build the shared front/back PHP component.
- Match the supplied student reference.
- Add the adapted faculty/staff content.
- Add responsive review page and overflow warnings.
- Complete browser and print visual calibration.

Deliverable: the authority approves the card appearance before export is enabled.

### Phase 5: Approval and ZIP Export

- Implement transactional approve and mark-done APIs.
- Add high-resolution front/back capture.
- Add two-page physical-size PDF generation.
- Add ZIP generation and deterministic filenames.
- Add retry behavior and download counters.
- Add export and state-transition tests.

Deliverable: one action approves, downloads PDF plus PNGs, and marks the row done.

### Phase 6: Hardening and Deployment

- Run all existing and new standalone tests.
- Verify PHP syntax for every changed/new PHP file.
- Test responsive behavior and browser export.
- Apply `ID_CARD.SQL` to staging after backup.
- Verify private upload directory permissions and direct-access denial.
- Verify the live server has PHP 8.1+, `mysqli`, `fileinfo`, `gd`, and optional EXIF support.
- Document deployment order, rollback limits, and storage backup requirements.
- Apply to production only after staging print approval.

Deliverable: production-ready rollout with database and storage safeguards.

## 17. Acceptance Criteria

The feature is complete when all of the following are true:

- Two working static public form URLs exist and are not exposed in public site UI.
- Student and faculty/staff submissions securely save the specified data and photo as pending.
- The admin sidebar contains `ID Card`.
- The ID Card page has no statistics cards.
- Search/filter controls and both form URL controls share one responsive toolbar.
- Records use the existing admin table visual language with server-side pagination.
- Admin can review every submitted field and both card sides before approval.
- Student and faculty/staff cards match the supplied visual direction and contain the correct type-specific fields.
- The RTTC watermark appears on the back.
- Signature, stamp, and principal areas remain empty.
- Approval date is the issue date; validity is one year later.
- One action downloads a ZIP containing a two-page PDF and front/back PNG files.
- A successful export marks the record done; a failed export remains retryable as approved.
- Repeat downloads do not alter issue or validity dates.
- Delete is confirmed, audited, transactional, and removes the associated photo.
- Delete is unavailable after approval so an issued-card snapshot cannot be erased through the queue.
- Photos are content-validated and are not directly publicly accessible.
- `database/ID_CARD.SQL` can be safely applied to the selected live database without dropping existing data.
- New tests pass and existing application behavior is not regressed.

## 18. Deliberately Out of Scope

These items are not required by the current request and should not be added without separate approval:

- Public ID card status lookup.
- QR code or barcode verification.
- Email/SMS notifications.
- Student login integration or admissions-data prefill.
- Faculty/staff master directory or HR management.
- Bulk approval or batch printing.
- Rejection/resubmission workflow.
- Lost-card reissue, revocation, or version history.
- Digital signatures or prefilled college stamp.
- Public form links in any frontend menu.
- General refactoring of the procedural PHP architecture.
- Full admin role/permission redesign beyond correctly enforcing the existing roles for this feature.

## 19. Decisions to Reconfirm at Visual Review

The functional scope is settled. These visual production details should be confirmed against the first implementation preview and print proof:

- Whether the authority accepts standard portrait CR80 dimensions or requires the exact non-standard aspect ratio visible in the supplied image.
- Final college header wording, affiliation line, website, phone, and email printed on the card.
- Exact font family and blue/orange color values.
- Final line wrapping of the six instructions.
- Whether `College Stamp` or another exact label should appear below the center blank area.
- Whether the faculty/staff bottom-left label should read `Holder's Signature`, `Employee's Signature`, or `Faculty/Staff Signature`.

The physical dimension decision is a Phase 1 gate and blocks release of the Phase 2 forms because it determines safe input lengths. The remaining wording and styling details do not block database/domain work, but all items must be locked before Phase 5 export is accepted.
