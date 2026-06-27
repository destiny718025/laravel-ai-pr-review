---
phase: 01-review-run-foundation-and-management-ui
plan: 01-02
subsystem: service
tags: [laravel, dto, service, repository, github-url-parser]
requires:
  - phase: 01-01
    provides: Review run schema, models, relationships, and status enum
provides:
  - GitHub pull request URL parsing
  - Review run creation service
  - Repository-layer persistence boundaries for review run creation
  - Structured creation result data for later controller/UI use
affects: [01-03, review-routes, management-ui]
tech-stack:
  added: []
  patterns:
    - app/Data objects for cross-layer DTO/value object transfer
    - Controller-ready service result object with success/error accessors
    - Repository classes owning Eloquent reads and writes
key-files:
  created:
    - app/Data/GitHubPullRequestReference.php
    - app/Data/ReviewRunCreationResult.php
    - app/Services/GitHub/GitHubPullRequestUrlParser.php
    - app/Services/ReviewRunService.php
    - app/Repositories/GitHubRepositoryRepository.php
    - app/Repositories/PullRequestRepository.php
    - app/Repositories/ReviewRunRepository.php
    - tests/Feature/ReviewRunCreationServiceTest.php
  modified: []
key-decisions:
  - "Keep app/Data as the home for DTO/value object classes that carry structured data between layers."
  - "ReviewRunService returns ReviewRunCreationResult rather than throwing for expected user input failures."
  - "Invalid PR URL submissions return stable error codes and create no domain records."
patterns-established:
  - "Services orchestrate workflow and delegate all Eloquent reads/writes to repository classes."
  - "Parser failures are represented as stable service error codes for controller/UI use."
requirements-completed: [RUN-03, RUN-04, ARCH-01, ARCH-03, ARCH-04, GH-01]
coverage:
  - id: D1
    description: "Valid GitHub pull request URLs parse into normalized identity and create pending review runs."
    requirement: GH-01
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewRunCreationServiceTest.php#test_it_creates_a_pending_review_run_from_a_valid_github_pull_request_url"
        status: pass
      - kind: integration
        ref: "composer run test"
        status: pass
    human_judgment: false
  - id: D2
    description: "Duplicate submissions reuse repository and pull request identity while creating a new review run."
    requirement: RUN-04
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewRunCreationServiceTest.php#test_duplicate_submissions_reuse_repository_and_pull_request_but_create_new_review_runs"
        status: pass
    human_judgment: false
  - id: D3
    description: "Invalid GitHub PR URL categories return stable error codes without persisting records."
    requirement: RUN-03
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewRunCreationServiceTest.php#test_invalid_pull_request_urls_return_stable_error_codes_without_creating_records"
        status: pass
    human_judgment: false
  - id: D4
    description: "Service/repository layering keeps business workflow in service and database access in repositories."
    requirement: ARCH-01
    verification:
      - kind: other
        ref: "manual file-scope inspection during execution"
        status: pass
    human_judgment: false
duration: 24 min
completed: 2026-06-27
status: complete
---

# Phase 01 Plan 01-02: Review Run Creation Service Summary

**GitHub PR URL parsing and repository-backed review run creation service with stable validation errors**

## Performance

- **Duration:** 24 min
- **Started:** 2026-06-27T02:42:00Z
- **Completed:** 2026-06-27T03:06:02Z
- **Tasks:** 3
- **Files modified:** 8

## Accomplishments

- Added a GitHub pull request URL parser that accepts `https://github.com/{owner}/{repo}/pull/{number}` and normalizes persisted source URLs by dropping query strings and fragments.
- Added `GitHubPullRequestReference` and `ReviewRunCreationResult` data objects for structured cross-layer data transfer.
- Added repository classes for GitHub repository identity, pull request identity, and pending review run creation.
- Added `ReviewRunService::createFromPullRequestUrl()` to orchestrate parser output and repository persistence without direct Eloquent writes in the service.
- Added service tests for valid creation, duplicate identity reuse, and invalid URL categories that persist no records.

## Task Commits

Each implementation task was committed atomically:

1. **Task 1: Prove parser, repository, and service creation behavior with failing tests** - `b9b2ed9` (test)
2. **Task 2: Implement parser, DTOs, repositories, and ReviewRunService** - `803d885` (feat)
3. **Task 3: Verify service foundation and layer boundaries** - no code changes; verification completed against committed task output

**Plan metadata:** pending commit

## Files Created/Modified

- `app/Data/GitHubPullRequestReference.php` - Carries parsed PR owner, repository name, PR number, normalized source URL, and normalized full name.
- `app/Data/ReviewRunCreationResult.php` - Carries successful review run creation or stable service error code/message.
- `app/Services/GitHub/GitHubPullRequestUrlParser.php` - Validates GitHub PR URL shape and returns either a reference object or stable error code.
- `app/Services/ReviewRunService.php` - Orchestrates parser and repository classes to create pending review runs.
- `app/Repositories/GitHubRepositoryRepository.php` - Finds or creates normalized GitHub repository identity records.
- `app/Repositories/PullRequestRepository.php` - Finds or creates pull request identity records for a repository and number.
- `app/Repositories/ReviewRunRepository.php` - Creates pending review runs for pull requests.
- `tests/Feature/ReviewRunCreationServiceTest.php` - Verifies valid creation, duplicate reuse, and invalid no-record behavior.

## Decisions Made

- Kept `app/Data` as the namespace for DTO/value object style classes used between parser, service, repositories, and later controllers.
- Used `ReviewRunCreationResult` for expected user input failures so controllers can branch on `successful()`, `errorCode()`, and `message()` without exception flow.
- Kept parser invalid cases stable as `invalid_url`, `not_github_pr_url`, and `missing_pr_number`.

## Deviations from Plan

None - plan executed exactly as written.

---

**Total deviations:** 0 auto-fixed.
**Impact on plan:** No scope creep; implementation stayed within parser, data object, repository, service, and test boundaries.

## Issues Encountered

- PHP verification continues to require the Laradock PHP 8.5 workspace because the host does not expose `php`/`composer`, and the PHP 8.3 workspace cannot satisfy the current Composer platform check.

## Verification

- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --testsuite=Feature --filter=ReviewRunCreationServiceTest` - passed, 3 tests / 41 assertions.
- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --testsuite=Feature` - passed, 6 tests / 58 assertions.
- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` - passed, 7 tests / 59 assertions.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Plan `01-03` can now wire `/reviews` routes and controller actions to `ReviewRunService`, using `ReviewRunCreationResult` for success/error branching and the repository-backed persistence created here.

---
*Phase: 01-review-run-foundation-and-management-ui*
*Completed: 2026-06-27*
