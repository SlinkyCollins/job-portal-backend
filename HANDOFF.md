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

### Deliberately deferred

3. Firebase/Google login.
4. Firebase/Facebook login.
5. Social account linking.

These items are deferred. **Do not reassess, reopen, or implement them as part of the current testing phase.** They do not block progress.

Authentication and authorization (#6–7) have basic coverage that is sufficient for now. Some security edge cases remain, including expired tokens, malformed tokens, missing Authorization headers, and suspended-user access. These can be addressed later if relevant to migration risk, but they do not block progress to #8 or beyond.

### Current task: #14 Resume upload

Add focused automated tests for the existing resume upload functionality to establish migration regression protection.

First inspect the existing implementation before writing tests.

### Completed

11. **Job Details** — ✅ Basic coverage (3 tests, 31 assertions). Endpoint is public (no auth required). Tests cover: successful retrieval with field and company verification, non-existent job 404, missing ID 400. Computed flags (`hasApplied`, `isSaved`, `isRetracted`, `is_closed`) verified at defaults. Note: `salary_amount` returns as `"250000.00"` (MySQL DECIMAL format). Test file: `tests/Integration/Jobs/JobDetailsTest.php`.
12. **Job Application** — ✅ Basic coverage (3 tests, 26 assertions). Endpoint requires `job_seeker` JWT auth. Uses `$_POST` (multipart/form-data), not JSON body. Tests cover: successful application with DB record verification (correct seeker/job association, pending status, cover letter), duplicate application rejection (400 + `hasApplied: true`), unauthenticated access denial (401). Uses default CV from `job_seekers_table` to avoid Cloudinary dependency. Test file: `tests/Integration/Jobs/JobApplicationTest.php`.
13. **Application Ownership / Employer Access Control** — ✅ Basic coverage (4 tests, 52 assertions). Endpoints: GET `/api/dashboard/employer/get_applications.php` and POST `/api/dashboard/employer/update_application_status.php`. Both require `employer` JWT auth. Tests cover: (1) isolation in viewing applications (Employer 1 only receives applications for their own jobs, Employer 2 only receives applications for their own jobs, retracted applications excluded), (2) unauthorized cross-employer status updates return 403 Forbidden with database state unchanged, (3) authorized status updates by owning employer return 200 OK with database state updated to 'shortlisted', (4) non-employer access denial (seeker gets 403, unauthenticated gets 401). Test file: `tests/Integration/Jobs/ApplicationOwnershipTest.php`.

### Next steps

Proceed sequentially through the remaining critical checklist:

14. **Resume upload**
15. **Admin authorization**
16. **Admin user management**
17. **User deletion / associated-data cleanup**

For each task, inspect the existing implementation, add only the smallest meaningful set of tests, verify the results, and move forward.

Do not return to completed or deliberately deferred items unless explicitly instructed. The checklist is a map, not a quota.

**Immediate next action:** Complete #14 Resume upload, but wait for user instruction before starting.

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
| 5 | Social account linking | Current task |
| 6 | Authentication/session/token handling | Basic coverage complete |
| 7 | Role authorization/protected endpoints | Basic coverage complete |
| 8 | Job creation | Basic coverage complete |
| 9 | Job editing | Basic coverage complete |
| 10 | Job listing/search/filtering | Basic coverage complete |
| 11 | Job details | Basic coverage complete |
| 12 | Job application | Basic coverage complete |
| 13 | Application ownership/employer access control | Basic coverage complete |
| 14 | Resume upload | Pending |
| 15 | Admin authorization | Pending |
| 16 | Admin user management | Pending |
| 17 | User deletion/associated-data cleanup | Pending |

**Additional security gaps already identified:** expired tokens, malformed tokens, missing Authorization headers, and suspended-user access. These are not exhaustive-security-test requirements for this phase. Cover them later if relevant to the migration risk; they should not automatically block progress.

**User deletion deserves particular attention:** the existing flow touches MySQL, Cloudinary, and Firebase and uses a database transaction. Protect the important deletion and data-integrity guarantees, but avoid building a comprehensive distributed-failure test suite.

### 🟠 Important — Cover when relevant

18. Saved jobs / wishlist  
19. Employer dashboard data  
20. Seeker dashboard data  
21. Admin dashboard statistics  
22. Company profile management  
23. Profile/account settings  
24. Cloudinary uploads/deletions  
25. Salary normalization/conversion  
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

**Execute only the current checkpoint: #14 Resume upload.**

Respect all constraints above. Afterward, report the result and recommend the next checkpoint. Do not begin another feature automatically.

Remember: the objective is to build engineering capabilities through shipping software, not to spend indefinitely perfecting the safety net.