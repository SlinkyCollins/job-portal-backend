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
TEST / BASELINE ← CURRENT PHASE  
↓  
ANGULAR MODERNIZATION (Angular 18 → Angular 22)  
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

The current application is an existing, functioning system. We are establishing a focused regression safety net before modernization.

Do not assume implementation details from this document are necessarily current. Inspect the actual repository before acting.

## 4. Current Checkpoint

We are in **TEST / BASELINE**, working through the critical-behavior checklist.

### Completed

1. Native signup — basic coverage complete.
2. Native login — basic coverage complete.
6. Authentication/session/token handling — basic coverage complete.
7. Role authorization / protected endpoints — basic coverage complete.
8. Job creation — basic coverage complete.
9. Job editing — basic coverage complete.
10. Job listing/search/filtering — basic coverage complete.
11. Job details — basic coverage complete.
12. Job application — basic coverage complete.
13. Application ownership / employer access control — basic coverage complete.
14. Resume upload — basic coverage complete.
15. Admin authorization — basic coverage complete.
16. Admin user management — basic coverage complete.
17. User deletion / associated-data cleanup — basic coverage complete.
18. Saved jobs / wishlist — basic coverage complete.
22. Company profile management — basic coverage complete.
25. Salary normalization/conversion — basic coverage complete.

### Deliberately deferred

3. Firebase/Google login.
4. Firebase/Facebook login.
5. Social account linking.

These items are deferred. **Do not reassess, reopen, or implement them as part of the current testing phase.** They do not block progress.

Authentication and authorization (#6–7) have basic coverage that is sufficient for now. Some security edge cases remain, including expired tokens, malformed tokens, missing Authorization headers, and suspended-user access. These can be addressed later if relevant to migration risk, but they do not block progress to #8 or beyond.

### Milestone Achieved: Critical Path Baseline Complete (#1–17) + Important Checkpoints (#18, #22, #25)

All non-deferred items in the **🔴 Critical — Core business / non-negotiable** tier, plus **#18 Saved jobs / wishlist**, **#22 Company profile management**, and **#25 Salary normalization/conversion**, are now fully covered by automated regression tests (56 tests, 644 assertions, 100% passing).

### Completed Details

11. **Job Details** — ✅ Basic coverage (3 tests, 31 assertions). Endpoint is public (no auth required). Tests cover: successful retrieval with field and company verification, non-existent job 404, missing ID 400. Computed flags (`hasApplied`, `isSaved`, `isRetracted`, `is_closed`) verified at defaults. Note: `salary_amount` returns as `"250000.00"` (MySQL DECIMAL format). Test file: `tests/Integration/Jobs/JobDetailsTest.php`.
12. **Job Application** — ✅ Basic coverage (3 tests, 26 assertions). Endpoint requires `job_seeker` JWT auth. Uses `$_POST` (multipart/form-data), not JSON body. Tests cover: successful application with DB record verification (correct seeker/job association, pending status, cover letter), duplicate application rejection (400 + `hasApplied: true`), unauthenticated access denial (401). Uses default CV from `job_seekers_table` to avoid Cloudinary dependency. Test file: `tests/Integration/Jobs/JobApplicationTest.php`.
13. **Application Ownership / Employer Access Control** — ✅ Basic coverage (4 tests, 52 assertions). Endpoints: GET `/api/dashboard/employer/get_applications.php` and POST `/api/dashboard/employer/update_application_status.php`. Both require `employer` JWT auth. Tests cover: (1) isolation in viewing applications (Employer 1 only receives applications for their own jobs, Employer 2 only receives applications for their own jobs, retracted applications excluded), (2) unauthorized cross-employer status updates return 403 Forbidden with database state unchanged, (3) authorized status updates by owning employer return 200 OK with database state updated to 'shortlisted', (4) non-employer access denial (seeker gets 403, unauthenticated gets 401). Test file: `tests/Integration/Jobs/ApplicationOwnershipTest.php`.
14. **Resume Upload** — ✅ Basic coverage (6 tests, 52 assertions). Endpoint: POST `/api/dashboard/seeker/upload_cv.php`. Requires `job_seeker` JWT auth. Supports deterministic Cloudinary test-double via `X-Mock-Cloudinary: true` header or `MOCK_CLOUDINARY=true` flag. Tests cover: (1) successful PDF upload updating `job_seekers_table` with download URL (`fl_attachment`), sanitized filename, and `cv_public_id`, (2) subsequent upload replacing old CV and generating new public_id, (3) rejection of disallowed MIME types (e.g. image/png) with 400 and database untouched, (4) rejection of missing filename with 400, (5) unauthenticated request rejected with 401, (6) non-seeker (employer) access rejected with 403. Test file: `tests/Integration/Seeker/ResumeUploadTest.php`.
15. **Admin Authorization** — ✅ Basic coverage (5 tests, 57 assertions). Endpoints: GET `/api/dashboard/admin/admin_dashboard.php` and GET `/api/dashboard/admin/get_stats.php`. Both require `admin` JWT role. Tests cover: (1) admin can successfully access admin dashboard endpoint with correct user data returned, (2) admin can successfully access admin stats endpoint with platform metrics returned, (3) job seeker is rejected from admin endpoints with 403 Forbidden, (4) employer is rejected from admin endpoints with 403 Forbidden, (5) unauthenticated requests are rejected with 401 Unauthorized. Test file: `tests/Integration/Admin/AdminAuthorizationTest.php`.
16. **Admin User Management** — ✅ Basic coverage (5 tests, 67 assertions). Endpoints: POST `/api/dashboard/admin/suspend_user.php` and GET `/api/dashboard/admin/get_users.php`. Reused #15 authorization baseline. Tests cover: (1) admin suspending a user sets `suspended = 1` in database and prevents subsequent login with 403, (2) admin unsuspending a user restores `suspended = 0` and allows login to succeed, (3) admin cannot suspend another admin (403 Forbidden with target admin unaffected), (4) admin user listing returns seekers and employers while correctly excluding the requesting admin, (5) input validation (404 for non-existent user, 400 for invalid action). Test file: `tests/Integration/Admin/AdminUserManagementTest.php`.
17. **User Deletion / Associated-Data Cleanup** — ✅ Basic coverage (6 tests, 61 assertions). Endpoints: POST `/api/dashboard/shared/delete_account.php`, POST `/api/dashboard/shared/delete_profile_photo.php`, and POST `/api/dashboard/admin/delete_user.php`. Tests cover: (1) job seeker self-deletion with cascade cleanup of profile (`job_seekers_table`), applications (`applications_table`), and saved jobs (`saved_jobs_table`), (2) employer self-deletion with cascade cleanup of employer profile, company, posted jobs (`jobs_table`), and received applications, (3) confirmation validation requiring exact string `'DELETE'`, (4) profile photo deletion clearing Cloudinary DB references (`profile_pic_url`, `profile_pic_public_id`) while preserving user account, (5) admin user deletion cascading associated records, (6) admin cannot delete another admin (403 Forbidden with target admin intact). Test file: `tests/Integration/Account/UserDeletionTest.php`.
18. **Saved Jobs / Wishlist** — ✅ Basic coverage (5 tests, 88 assertions). Endpoints: POST `/api/jobs/wishlist.php`, DELETE `/api/jobs/wishlist_delete.php`, and GET `/api/dashboard/seeker/saved_jobs.php`. Tests cover: (1) seeker saving a job persists to `saved_jobs_table` and duplicate saves return 400 with `'You have already saved this job.'`, (2) seeker unsaving a job removes it from `saved_jobs_table`, (3) saved jobs retrieval returns full job schema, pagination metadata, and company information, (4) cross-user isolation: users can only see their own saved jobs and cannot delete another user's saved jobs, (5) role authorization and input validation: unauthenticated requests get 401, missing `jobId` gets 400, and non-seeker roles get 403. Test file: `tests/Integration/Seeker/SavedJobsTest.php`.
22. **Company Profile Management** — ✅ Basic coverage (5 tests, 79 assertions). Endpoints: POST `/api/dashboard/employer/save_company_profile.php` and GET `/api/dashboard/employer/get_company_profile.php`. Tests cover: (1) employer successfully updating existing company profile (`name`, `location`, `website`, `description`) with database persistence and matching retrieval via `get_company_profile.php`, (2) strict user-to-company ownership scoping: updates are keyed exclusively to the authenticated employer's JWT ID (`WHERE user_id = ?`), leaving other employers' companies intact, (3) creating a new company profile when none exists and automatically linking `employers_table.company_id`, (4) required fields validation (`name`, `location`, `description`) returning 400 with DB unchanged, (5) role authorization and unauthenticated rejection (401 unauth, 403 seeker). Test file: `tests/Integration/Employer/CompanyProfileTest.php`.
25. **Salary Normalization / Conversion** — ✅ Basic coverage (5 tests, 51 assertions). Endpoints: GET `/api/jobs/all_jobs.php`, GET `/api/dashboard/seeker/saved_jobs.php`, and POST `/api/dashboard/employer/post_job.php`. Tests cover: (1) multi-currency sorting (`salaryHighToLow`) converts diverse currencies (NGN, USD, GBP) to USD-normalized equivalents using cached exchange rates (`j.salary_amount / NULLIF(rate, 0)`), placing $3K GBP and $2K USD above nominal 1.33M NGN, (2) reverse multi-currency sorting (`salaryLowToHigh`) ordering lowest USD-normalized equivalent first, (3) range filtering (`min_salary` and `max_salary` BETWEEN) and specific `currency` filtering in job search, (4) saved jobs salary sorting (`salary-high` and `salary-low`) applying identical currency normalization, (5) salary input boundary validations on job creation (rejecting 0, negative numbers, non-numeric strings, and accepting positive decimals like 0.01). Test file: `tests/Integration/Jobs/SalaryNormalizationTest.php`.

### Next steps

With #18, #22, and #25 covered, proceed according to project priorities:
- **Option A:** Proceed with other **🟠 Important** items (e.g. #19 Employer dashboard data, #20 Seeker dashboard data, #21 Admin dashboard stats).
- **Option B:** Transition to the modernization phase (Laravel backend migration / Angular frontend modernization).

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

The priority labels guide effort. They are not a requirement to implement every test.

### 🔴 Critical — Migration safety net

| # | Behavior | Current status |
|---|---|---|
| 1 | Native signup | Basic coverage complete |
| 2 | Native login | Basic coverage complete |
| 3 | Firebase/Google login | Deferred |
| 4 | Firebase/Facebook login | Deferred |
| 5 | Social account linking | Deferred |
| 6 | Authentication/session/token handling | Basic coverage complete |
| 7 | Role authorization/protected endpoints | Basic coverage complete |
| 8 | Job creation | Basic coverage complete |
| 9 | Job editing | Basic coverage complete |
| 10 | Job listing/search/filtering | Basic coverage complete |
| 11 | Job details | Basic coverage complete |
| 12 | Job application | Basic coverage complete |
| 13 | Application ownership/employer access control | Basic coverage complete |
| 14 | Resume upload | Basic coverage complete |
| 15 | Admin authorization | Basic coverage complete |
| 16 | Admin user management | Basic coverage complete |
| 17 | User deletion/associated-data cleanup | Basic coverage complete |

**Additional security gaps already identified:** expired tokens, malformed tokens, missing Authorization headers, and suspended-user access. These are not exhaustive-security-test requirements for this phase. Cover them later if relevant to the migration risk; they should not automatically block progress.

**User deletion deserves particular attention:** the existing flow touches MySQL, Cloudinary, and Firebase and uses a database transaction. Protect the important deletion and data-integrity guarantees, but avoid building a comprehensive distributed-failure test suite.

### 🟠 Important — Cover when relevant

18. Saved jobs / wishlist — ✅ Basic coverage complete
19. Employer dashboard data  
20. Seeker dashboard data  
21. Admin dashboard statistics  
22. Company profile management — ✅ Basic coverage complete
23. Profile/account settings  
24. Cloudinary uploads/deletions  
25. Salary normalization/conversion — ✅ Basic coverage complete
26. Pagination/sorting/filtering  
27. Job application status changes  

Salary normalization is business logic and should not be dismissed as merely a UI concern.

Avoid duplicating coverage between related categories. For example, #14 may cover essential upload behavior without needing a second exhaustive suite under #24.

### 🟡 Useful — Not a current blocker

28. Loading states  
29. Empty states  
30. Error states  
31. Form validation  
32. Toast/notification behavior  
33. UI component behavior  
34. Dashboard rendering  

### ⚪ Low priority for the initial safety net

35. Exact styling  
36. Pixel-level visual appearance  
37. Animation  
38. Responsive layout details  

Responsiveness still matters in the broader modernization roadmap; it simply does not belong at the top of this initial backend regression-testing phase.

## 8. How to Execute Each Task

For each item:

1. Read the relevant code and existing tests.
2. Define the smallest meaningful acceptance criteria.
3. Implement focused tests.
4. Run tests and review the diff.
5. Record the result and any deliberate deferrals.
6. Move to the next item.

Do not turn one feature into a comprehensive audit of the entire application.

## 9. Current Agent Instruction

**Baseline Safety Net Complete: #1–18, #22, #25 (56 tests, 644 assertions, 100% passing).**

Awaiting user direction on whether to:
1. Cover additional items from **🟠 Important** (e.g. #19 Employer dashboard data, #20 Seeker dashboard data, #21 Admin dashboard stats), OR
2. Transition directly to framework modernization (Laravel backend migration / Angular frontend upgrade).

Remember: the objective is to build engineering capabilities through shipping software, not to spend indefinitely perfecting the safety net.