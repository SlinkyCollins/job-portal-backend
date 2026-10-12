# 🛠 Mission: Building — JobNet Modernization

## 1. Mission

**Goal:** Build real engineering capabilities by shipping software.

JobNet is our flagship engineering laboratory. We are modernizing an existing recruitment platform to learn and practice safe migrations, maintainable architecture, testing, security, performance, and production-minded engineering.

The goal is not simply to rewrite PHP in Laravel or upgrade Angular. The goal is to transform an existing system while preserving its important behavior and progressively improving its engineering quality.

## 2. Modernization Roadmap

AUDIT  
↓  
FREEZE CURRENT SYSTEM  
↓  
TEST / BASELINE — ✅ COMPLETED (65 tests, 759 assertions)  
↓  
ANGULAR MODERNIZATION (Angular 18 → Angular 22) ← CURRENT PHASE  
↓  
DESIGN SYSTEM + TAILWIND  
↓  
FRONTEND REFACTOR  
↓  
LARAVEL MIGRATION (Vanilla PHP → Laravel)  
↓  
API CONTRACT MODERNIZATION  
↓  
BACKEND ARCHITECTURE  
↓  
STABILIZE  
↓  
SECURITY / PERFORMANCE / OBSERVABILITY  
↓  
SCALABILITY  
↓  
ADVANCED FEATURES  
↓  
PRODUCT / SaaS / OPEN SOURCE

**Do not skip ahead or expand the current phase without a clear reason.**

## 3. Current Project Context

Existing stack:
- Frontend: Angular 18
- Backend: Vanilla PHP
- Database: MySQL
- Authentication: Firebase and native authentication flows
- File storage: Cloudinary
- Other existing integrations and services

The current application is an existing, functioning system. We have established a comprehensive automated regression safety net covering all critical and high-risk backend workflows.

Do not assume implementation details from this document are necessarily current. Inspect the actual repository before acting.

## 4. Current Checkpoint

**TEST / BASELINE Phase Complete!**  
All critical and high-priority backend behavior is protected by 65 integration tests and 759 assertions (100% passing).  
**Current Phase: ANGULAR MODERNIZATION (Angular 18 → Angular 22).**

### Completed

1. Native signup — basic coverage complete (`SignupTest`).
2. Native login — basic coverage complete (`LoginTest`).
6. Authentication/session/token handling — basic coverage complete (across all authenticated suites).
7. Role authorization / protected endpoints — basic coverage complete (across all role-guarded suites).
8. Job creation — basic coverage complete (`JobCreationTest`).
9. Job editing — basic coverage complete (`JobEditingTest`).
10. Job listing/search/filtering — basic coverage complete (`JobListingTest`).
11. Job details — basic coverage complete (`JobDetailsTest`).
12. Job application — basic coverage complete (`JobApplicationTest`).
13. Application ownership / employer access control — basic coverage complete (`ApplicationOwnershipTest`).
14. Resume upload — basic coverage complete (`ResumeUploadTest`).
15. Admin authorization — basic coverage complete (`AdminAuthorizationTest`).
16. Admin user management — basic coverage complete (`AdminUserManagementTest`).
17. User deletion / associated-data cleanup — basic coverage complete (`UserDeletionTest`).
18. Saved jobs / wishlist — basic coverage complete (`SavedJobsTest`).
22. Company profile management — basic coverage complete (`CompanyProfileTest`).
23. Profile/account settings — basic coverage complete (`AccountSettingsTest`).
24. Cloudinary uploads/deletions — covered under #14 (`ResumeUploadTest`) & #17 (`UserDeletionTest`).
25. Salary normalization/conversion — basic coverage complete (`SalaryNormalizationTest`).
26. Pagination/sorting/filtering — basic coverage complete (`JobListingTest`).
27. Job application status changes — covered under #13 (`ApplicationOwnershipTest`).

### Deliberately deferred

- **3. Firebase/Google login** — Third-party auth flow; deferred to modern auth refactor.
- **4. Firebase/Facebook login** — Third-party auth flow; deferred to modern auth refactor.
- **5. Social account linking** — Third-party auth flow; deferred to modern auth refactor.
- **19. Employer dashboard data** — Read-only aggregations reliant on covered underlying queries; deferred to UI modernizations.
- **20. Seeker dashboard data** — Read-only aggregations reliant on covered underlying queries; deferred to UI modernizations.
- **21. Admin dashboard statistics** — Underlying metrics covered in #15; UI presentation deferred.
- **28–34. UI behavior (loading, empty, error states, form validations, toast notifications, component interactions, dashboard rendering)** — Deferred to Angular modernization & frontend refactor phases.
- **35–38. Visual appearance (styling, pixel fidelity, animations, responsive layout details)** — Deferred to Design System & Tailwind phase.

These items are deliberately deferred. **Do not reassess, reopen, or implement them as part of the backend testing phase.** They do not block progress.

Authentication and authorization (#6–7) have basic coverage that is sufficient for now. Some security edge cases remain, including expired tokens, malformed tokens, missing Authorization headers, and suspended-user access. These can be addressed later if relevant to migration risk, but they do not block progress.

### Milestone Achieved: Critical Path Baseline Complete (#1–17) + Important Checkpoints (#18, #22, #23, #24, #25, #26, #27)

All non-deferred items in the **🔴 Critical — Core business / non-negotiable** tier, plus all applicable **🟠 Important** business logic behaviors, are now fully protected by automated regression tests (65 tests, 759 assertions, 100% passing across 13 test files).

### Completed Details

10 / 26. **Job Listing / Pagination, Sorting & Filtering** — ✅ Comprehensive coverage (5 tests, 56 assertions). Endpoint: GET `/api/jobs/all_jobs.php`. Tests cover: (1) keyword search across title, overview, and description with field and computed flag assertions, (2) multi-page pagination with `page` and `per_page` returning distinct non-overlapping slices alongside correct envelope metadata (`page`, `per_page`, `total`, `total_pages`), (3) sorting by `datePosted` (`published_at DESC`) and `relevance` (`title ASC`), (4) multi-parameter filtering by `location` (LIKE), `employment_type` (IN clause), and `experience_level` (IN clause), (5) strict exclusion of closed (`status = 'closed'`) and expired (`deadline < NOW()`) jobs from public search results. Test file: `tests/Integration/Jobs/JobListingTest.php`.
11. **Job Details** — ✅ Basic coverage (3 tests, 31 assertions). Endpoint is public (no auth required). Tests cover: successful retrieval with field and company verification, non-existent job 404, missing ID 400. Computed flags (`hasApplied`, `isSaved`, `isRetracted`, `is_closed`) verified at defaults. Note: `salary_amount` returns as `"250000.00"` (MySQL DECIMAL format). Test file: `tests/Integration/Jobs/JobDetailsTest.php`.
12. **Job Application** — ✅ Basic coverage (3 tests, 26 assertions). Endpoint requires `job_seeker` JWT auth. Uses `$_POST` (multipart/form-data), not JSON body. Tests cover: successful application with DB record verification (correct seeker/job association, pending status, cover letter), duplicate application rejection (400 + `hasApplied: true`), unauthenticated access denial (401). Uses default CV from `job_seekers_table` to avoid Cloudinary dependency. Test file: `tests/Integration/Jobs/JobApplicationTest.php`.
13 / 27. **Application Ownership, Employer Access Control & Status Changes** — ✅ Basic coverage (4 tests, 52 assertions). Endpoints: GET `/api/dashboard/employer/get_applications.php` and POST `/api/dashboard/employer/update_application_status.php`. Both require `employer` JWT auth. Tests cover: (1) isolation in viewing applications (Employer 1 only receives applications for their own jobs, Employer 2 only receives applications for their own jobs, retracted applications excluded), (2) unauthorized cross-employer status updates return 403 Forbidden with database state unchanged, (3) authorized status updates by owning employer return 200 OK with database state updated to 'shortlisted', (4) non-employer access denial (seeker gets 403, unauthenticated gets 401). Test file: `tests/Integration/Jobs/ApplicationOwnershipTest.php`.
14 / 24. **Resume Upload & Cloudinary Double** — ✅ Basic coverage (6 tests, 52 assertions). Endpoint: POST `/api/dashboard/seeker/upload_cv.php`. Requires `job_seeker` JWT auth. Supports deterministic Cloudinary test-double via `X-Mock-Cloudinary: true` header or `MOCK_CLOUDINARY=true` flag. Tests cover: (1) successful PDF upload updating `job_seekers_table` with download URL (`fl_attachment`), sanitized filename, and `cv_public_id`, (2) subsequent upload replacing old CV and generating new public_id, (3) rejection of disallowed MIME types (e.g. image/png) with 400 and database untouched, (4) rejection of missing filename with 400, (5) unauthenticated request rejected with 401, (6) non-seeker (employer) access rejected with 403. Test file: `tests/Integration/Seeker/ResumeUploadTest.php`.
15. **Admin Authorization** — ✅ Basic coverage (5 tests, 57 assertions). Endpoints: GET `/api/dashboard/admin/admin_dashboard.php` and GET `/api/dashboard/admin/get_stats.php`. Both require `admin` JWT role. Tests cover: (1) admin can successfully access admin dashboard endpoint with correct user data returned, (2) admin can successfully access admin stats endpoint with platform metrics returned, (3) job seeker is rejected from admin endpoints with 403 Forbidden, (4) employer is rejected from admin endpoints with 403 Forbidden, (5) unauthenticated requests are rejected with 401 Unauthorized. Test file: `tests/Integration/Admin/AdminAuthorizationTest.php`.
16. **Admin User Management** — ✅ Basic coverage (5 tests, 67 assertions). Endpoints: POST `/api/dashboard/admin/suspend_user.php` and GET `/api/dashboard/admin/get_users.php`. Reused #15 authorization baseline. Tests cover: (1) admin suspending a user sets `suspended = 1` in database and prevents subsequent login with 403, (2) admin unsuspending a user restores `suspended = 0` and allows login to succeed, (3) admin cannot suspend another admin (403 Forbidden with target admin unaffected), (4) admin user listing returns seekers and employers while correctly excluding the requesting admin, (5) input validation (404 for non-existent user, 400 for invalid action). Test file: `tests/Integration/Admin/AdminUserManagementTest.php`.
17 / 24. **User Deletion & Cascade Data Cleanup** — ✅ Basic coverage (6 tests, 61 assertions). Endpoints: POST `/api/dashboard/shared/delete_account.php`, POST `/api/dashboard/shared/delete_profile_photo.php`, and POST `/api/dashboard/admin/delete_user.php`. Tests cover: (1) job seeker self-deletion with cascade cleanup of profile (`job_seekers_table`), applications (`applications_table`), and saved jobs (`saved_jobs_table`), (2) employer self-deletion with cascade cleanup of employer profile, company, posted jobs (`jobs_table`), and received applications, (3) confirmation validation requiring exact string `'DELETE'`, (4) profile photo deletion clearing Cloudinary DB references (`profile_pic_url`, `profile_pic_public_id`) while preserving user account, (5) admin user deletion cascading associated records, (6) admin cannot delete another admin (403 Forbidden with target admin intact). Test file: `tests/Integration/Account/UserDeletionTest.php`.
18. **Saved Jobs / Wishlist** — ✅ Basic coverage (5 tests, 88 assertions). Endpoints: POST `/api/jobs/wishlist.php`, DELETE `/api/jobs/wishlist_delete.php`, and GET `/api/dashboard/seeker/saved_jobs.php`. Tests cover: (1) seeker saving a job persists to `saved_jobs_table` and duplicate saves return 400 with `'You have already saved this job.'`, (2) seeker unsaving a job removes it from `saved_jobs_table`, (3) saved jobs retrieval returns full job schema, pagination metadata, and company information, (4) cross-user isolation: users can only see their own saved jobs and cannot delete another user's saved jobs, (5) role authorization and input validation: unauthenticated requests get 401, missing `jobId` gets 400, and non-seeker roles get 403. Test file: `tests/Integration/Seeker/SavedJobsTest.php`.
22. **Company Profile Management** — ✅ Basic coverage (5 tests, 79 assertions). Endpoints: POST `/api/dashboard/employer/save_company_profile.php` and GET `/api/dashboard/employer/get_company_profile.php`. Tests cover: (1) employer successfully updating existing company profile (`name`, `location`, `website`, `description`) with database persistence and matching retrieval via `get_company_profile.php`, (2) strict user-to-company ownership scoping: updates are keyed exclusively to the authenticated employer's JWT ID (`WHERE user_id = ?`), leaving other employers' companies intact, (3) creating a new company profile when none exists and automatically linking `employers_table.company_id`, (4) required fields validation (`name`, `location`, `description`) returning 400 with DB unchanged, (5) role authorization and unauthenticated rejection (401 unauth, 403 seeker). Test file: `tests/Integration/Employer/CompanyProfileTest.php`.
23. **Profile / Account Settings** — ✅ Basic coverage (5 tests, 73 assertions). Endpoints: GET `/api/dashboard/shared/get_settings.php`, POST `/api/dashboard/shared/update_account.php`, POST `/api/dashboard/shared/change_password.php`, and POST `/api/dashboard/shared/verify_old_password.php`. Tests cover: (1) retrieving account settings verifying name, email, registration date, and `has_password: true`, (2) updating account name (`firstName`, `lastName`) with database persistence, reflection in settings, and minimum length validation (`min:2`), (3) current password verification endpoint confirming correct password and rejecting incorrect password with 400, (4) complete password change lifecycle: rejection of incorrect current password (400), rejection of short new password (400 `min:8`), successful password update (200), subsequent login success with new password (200), and login rejection with old password (401), (5) authentication enforcement (401 Unauthorized across all settings endpoints). Test file: `tests/Integration/Account/AccountSettingsTest.php`.
25. **Salary Normalization / Conversion** — ✅ Basic coverage (5 tests, 51 assertions). Endpoints: GET `/api/jobs/all_jobs.php`, GET `/api/dashboard/seeker/saved_jobs.php`, and POST `/api/dashboard/employer/post_job.php`. Tests cover: (1) multi-currency sorting (`salaryHighToLow`) converts diverse currencies (NGN, USD, GBP) to USD-normalized equivalents using cached exchange rates (`j.salary_amount / NULLIF(rate, 0)`), placing $3K GBP and $2K USD above nominal 1.33M NGN, (2) reverse multi-currency sorting (`salaryLowToHigh`) ordering lowest USD-normalized equivalent first, (3) range filtering (`min_salary` and `max_salary` BETWEEN) and specific `currency` filtering in job search, (4) saved jobs salary sorting (`salary-high` and `salary-low`) applying identical currency normalization, (5) salary input boundary validations on job creation (rejecting 0, negative numbers, non-numeric strings, and accepting positive decimals like 0.01). Test file: `tests/Integration/Jobs/SalaryNormalizationTest.php`.

### Next steps

The **TEST / BASELINE** phase is officially complete and signed off with 65 integration tests and 759 assertions (100% passing across 13 test files).
We now transition to the next phase on the roadmap:
- **Current Phase:** **ANGULAR MODERNIZATION (Angular 18 → Angular 22)**
- **Next in Queue:** Design System + Tailwind, Frontend Refactor, then Laravel Backend Migration.

## 5. Core Testing Principle

> If a test doesn't materially protect a risky behavior we're about to change, we probably don't need it.

**The checklist is a map, not a quota.**

We are not trying to maximize test count, achieve exhaustive coverage, or become testing specialists.

We want enough reliable automated coverage to detect important regressions during modernization.

### Momentum rule

If a test becomes a rabbit hole, stop and assess.

If it requires disproportionate setup, new infrastructure, extensive refactoring, or live external services, document the limitation and defer it unless the behavior is too risky to leave unprotected.

Keep moving through the roadmap.

## 6. Working Protocol for AI Agents

### Before implementation

1. Inspect the relevant routes, controllers, models, database queries, integrations, and existing tests.
2. Establish actual behavior, intended business rules, and existing test coverage.
3. Identify the smallest meaningful test set for the current task.
4. Identify any dependencies on external services and whether they can be isolated.

For uncertain or potentially complex tasks, first report the proposed minimal approach and any blockers before making changes.

For straightforward, bounded tasks, proceed directly with implementation after inspection.

### Implementation constraints

- Work only on the current task.
- Follow existing testing conventions and reuse existing infrastructure.
- Do not introduce new dependencies or testing frameworks without approval.
- Do not refactor unrelated code.
- Do not modify production behavior just to make tests pass.
- Do not invent routes, business rules, response formats, or access restrictions.
- Avoid duplicate coverage and brittle assertions tied to implementation details.
- Prefer deterministic fixtures and controlled mocks.
- Do not call live Firebase, Cloudinary, or other external services in ordinary automated tests unless the existing setup explicitly requires it.
- Do not silently fix unrelated bugs. Report relevant findings separately.
- Do not start the next task automatically unless explicitly instructed.

### After implementation

1. Run the focused tests.
2. Run the relevant existing test suite.
3. Report actual results, including failures.
4. Identify changed files.
5. Explain briefly what each test protects.
6. Flag any discovered behavior discrepancies or deferred risks.
7. Confirm that production behavior was not unnecessarily changed.

Never claim a test passed unless it was actually executed.

### Definition of done

The current behavior has sufficient regression protection for its risk level, the changes remain narrowly scoped, and the next task can begin without prolonged testing work.

## 7. Testing Priority Map

The priority labels guide effort. All baseline safety net requirements for critical business behaviors and high-risk backend features are complete and locked down.

### 🔴 Critical — Migration Safety Net

| # | Behavior | Current Status | Suite / Implementation Details |
|---|---|---|---|
| 1 | Native signup | ✅ Basic coverage complete | `SignupTest` (5 tests: seeker & employer signup, validation, duplicate email, role assignment) |
| 2 | Native login | ✅ Basic coverage complete | `LoginTest` (1 test: credential validation, JWT token generation, role verification) |
| 3 | Firebase/Google login | ⏸️ Deliberately deferred | Third-party auth flow; deferred to modern auth refactor |
| 4 | Firebase/Facebook login | ⏸️ Deliberately deferred | Third-party auth flow; deferred to modern auth refactor |
| 5 | Social account linking | ⏸️ Deliberately deferred | Third-party auth flow; deferred to modern auth refactor |
| 6 | Authentication/session/token handling | ✅ Basic coverage complete | Protected endpoints across all suites (401 on missing/invalid JWT tokens) |
| 7 | Role authorization/protected endpoints | ✅ Basic coverage complete | Cross-role enforcement across suites (403 on role mismatch) |
| 8 | Job creation | ✅ Basic coverage complete | `JobCreationTest` (1 test: employer posting, DB persistence, validation) |
| 9 | Job editing | ✅ Basic coverage complete | `JobEditingTest` (1 test: employer updating existing posting, ownership isolation) |
| 10 | Job listing/search/filtering | ✅ Comprehensive coverage | `JobListingTest` (5 tests, 56 assertions: keyword search, pagination, multi-filter, expired/closed exclusion) |
| 11 | Job details | ✅ Basic coverage complete | `JobDetailsTest` (3 tests: public view, computed flags, 404/400 handling) |
| 12 | Job application | ✅ Basic coverage complete | `JobApplicationTest` (3 tests: multipart application, duplicate prevention, default CV) |
| 13 | Application ownership/employer access control | ✅ Basic coverage complete | `ApplicationOwnershipTest` (4 tests: employer isolation, cross-employer status update 403) |
| 14 | Resume upload | ✅ Basic coverage complete | `ResumeUploadTest` (6 tests: PDF upload, CV replacement, MIME validation, Cloudinary double) |
| 15 | Admin authorization | ✅ Basic coverage complete | `AdminAuthorizationTest` (5 tests: admin dashboard & stats access, non-admin 403, unauthenticated 401) |
| 16 | Admin user management | ✅ Basic coverage complete | `AdminUserManagementTest` (5 tests: user suspension/unsuspension, login prevention, self/admin guard) |
| 17 | User deletion/associated-data cleanup | ✅ Basic coverage complete | `UserDeletionTest` (6 tests: cascade deletion of seeker/employer data, confirmation string, admin guard) |

**Additional security gaps already identified:** expired tokens, malformed tokens, missing Authorization headers, and suspended-user access. These are not exhaustive-security-test requirements for this phase. Cover them later if relevant to the migration risk; they do not block progress.

**User deletion note:** the existing flow touches MySQL, Cloudinary, and Firebase and uses a database transaction. The core deletion, cascade cleanup, and data-integrity guarantees are now protected by `UserDeletionTest`.

### 🟠 Important — Core Business Logic & State

| # | Behavior | Current Status | Suite / Implementation Details |
|---|---|---|---|
| 18 | Saved jobs / wishlist | ✅ Basic coverage complete | `SavedJobsTest` (5 tests, 88 assertions: save, unsave, duplicate check, user isolation, auth) |
| 19 | Employer dashboard data | ⏸️ Deliberately deferred | Read-only aggregations reliant on covered underlying queries; no distinct business calculations |
| 20 | Seeker dashboard data | ⏸️ Deliberately deferred | Read-only aggregations reliant on covered underlying queries; no distinct business calculations |
| 21 | Admin dashboard statistics | ⏸️ Deliberately deferred | Underlying metrics covered in #15; count queries deferred to UI phase |
| 22 | Company profile management | ✅ Basic coverage complete | `CompanyProfileTest` (5 tests, 79 assertions: create, update, ownership scoping, validation) |
| 23 | Profile / account settings | ✅ Basic coverage complete | `AccountSettingsTest` (5 tests, 73 assertions: retrieve settings, update name, password lifecycle) |
| 24 | Cloudinary uploads/deletions | ✅ Covered | Protected under #14 `ResumeUploadTest` & #17 `UserDeletionTest` (mock double & photo cleanup) |
| 25 | Salary normalization/conversion | ✅ Basic coverage complete | `SalaryNormalizationTest` (5 tests, 51 assertions: multi-currency USD sorting, currency filter, bounds) |
| 26 | Pagination/sorting/filtering | ✅ Covered | Protected under #10 `JobListingTest` (multi-page pagination, date & relevance sorting) |
| 27 | Job application status changes | ✅ Covered | Protected under #13 `ApplicationOwnershipTest` (authorized update to shortlisted, cross-employer 403) |

### 🟡 Useful — UI & Interaction Behavior

| # | Behavior | Current Status | Target Modernization Phase / Notes |
|---|---|---|---|
| 28 | Loading states | ⏸️ Deliberately deferred | Deferred to Angular modernization & frontend refactor |
| 29 | Empty states | ⏸️ Deliberately deferred | Deferred to Angular modernization & frontend refactor |
| 30 | Error states | ⏸️ Deliberately deferred | Deferred to Angular modernization & frontend refactor |
| 31 | Form validation | ⏸️ Deliberately deferred | Deferred to Angular modernization & frontend refactor |
| 32 | Toast/notification behavior | ⏸️ Deliberately deferred | Deferred to Angular modernization & frontend refactor |
| 33 | UI component behavior | ⏸️ Deliberately deferred | Deferred to Angular modernization & frontend refactor |
| 34 | Dashboard rendering | ⏸️ Deliberately deferred | Deferred to Angular modernization & frontend refactor |

### ⚪ Low Priority — Visual Polish & Aesthetics

| # | Behavior | Current Status | Target Modernization Phase / Notes |
|---|---|---|---|
| 35 | Exact styling | ⏸️ Deliberately deferred | Deferred to Design System & Tailwind phase |
| 36 | Pixel-level visual appearance | ⏸️ Deliberately deferred | Deferred to Design System & Tailwind phase |
| 37 | Animation | ⏸️ Deliberately deferred | Deferred to Design System & Tailwind phase |
| 38 | Responsive layout details | ⏸️ Deliberately deferred | Deferred to Design System & Tailwind phase |

## 8. Summary of Baseline Safety Net

With 65 focused integration tests (759 assertions, 100% passing), all critical business behaviors, data-integrity guarantees, foreign-key cascades, and high-risk operations are locked down:
- Native signup & login (#1, #2)
- JWT token handling & role authorization (#6, #7)
- Job posting, editing, details & listing (#8, #9, #10, #11)
- Job applications & employer ownership access controls (#12, #13, #27)
- Resume & file upload doubles (#14, #24)
- Admin authorization & user suspension (#15, #16)
- User deletion & cascade data cleanup (#17)
- Wishlist / saved jobs isolation (#18)
- Company profile management (#22)
- Account settings & password change security lifecycle (#23)
- Multi-currency salary normalization & range filtering (#25)
- Pagination, sorting & expired/closed job exclusion (#26)

## 9. Current Agent Instruction

**TEST / BASELINE Phase Complete!**

All baseline regression requirements are fulfilled and verified.  
**STATUS: STANDBY FOR NEXT PHASE — ANGULAR MODERNIZATION (Angular 18 → Angular 22).**

Do not make further changes to the backend or tests without explicit user instructions. Await instructions to initiate the Angular modernization roadmap.