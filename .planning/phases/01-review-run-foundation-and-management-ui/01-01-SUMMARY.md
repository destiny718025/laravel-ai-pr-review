---
phase: 01-review-run-foundation-and-management-ui
plan: 01-01
subsystem: database
tags: [laravel, sqlite, eloquent, schema, enum]
requires: []
provides:
  - Review run status vocabulary
  - GitHub repository, pull request, and review run persistence schema
  - Eloquent relationships and casts for review run foundation
affects: [01-02, review-run-service, repository-layer]
tech-stack:
  added: []
  patterns:
    - Laravel anonymous migrations for review domain tables
    - Laravel 13 attribute-based model fillable declarations
    - Backed enum cast for review run status
key-files:
  created:
    - app/Enums/ReviewRunStatus.php
    - app/Models/GitHubRepository.php
    - app/Models/PullRequest.php
    - app/Models/ReviewRun.php
    - database/migrations/2026_06_27_000001_create_repositories_table.php
    - database/migrations/2026_06_27_000002_create_pull_requests_table.php
    - database/migrations/2026_06_27_000003_create_review_runs_table.php
    - tests/Feature/ReviewRunSchemaTest.php
  modified: []
key-decisions:
  - "Use GitHubRepository as the model name to avoid colliding with repository-layer class names."
  - "Persist repository identity in repositories.full_name as a normalized lower-case owner/name value."
  - "Store review run status as a string column cast to the ReviewRunStatus backed enum."
patterns-established:
  - "Review domain models follow the existing Laravel 13 attribute-based Fillable style."
  - "ReviewRun owns lifecycle timestamps and casts them to datetime values."
requirements-completed: [RUN-04, ARCH-04]
coverage:
  - id: D1
    description: "Exact review run status vocabulary is represented by a backed enum."
    requirement: RUN-04
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewRunSchemaTest.php#test_review_run_status_vocabulary_is_exact"
        status: pass
    human_judgment: false
  - id: D2
    description: "Repositories, pull requests, and review runs persist identity, status, safe error text, and timestamps."
    requirement: RUN-04
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewRunSchemaTest.php#test_review_run_foundation_persists_identity_status_and_lifecycle_fields"
        status: pass
      - kind: integration
        ref: "composer run test"
        status: pass
    human_judgment: false
  - id: D3
    description: "Schema and model foundation stays below service, repository, controller, route, and UI scope."
    requirement: ARCH-04
    verification:
      - kind: other
        ref: "manual file-scope inspection during execution"
        status: pass
    human_judgment: false
duration: 25 min
completed: 2026-06-27
status: complete
---

# Phase 01 Plan 01-01: Review Run Foundation Summary

**Laravel review run persistence with exact status enum, normalized PR identity tables, and Eloquent relationships**

## Performance

- **Duration:** 25 min
- **Started:** 2026-06-27T02:12:00Z
- **Completed:** 2026-06-27T02:37:38Z
- **Tasks:** 3
- **Files modified:** 8

## Accomplishments

- Added `ReviewRunStatus` with the exact future-ready status vocabulary: `pending`, `queued`, `running`, `completed`, `failed`, and `cancelled`.
- Added `repositories`, `pull_requests`, and `review_runs` migrations with uniqueness constraints for repository identity and pull request identity.
- Added `GitHubRepository`, `PullRequest`, and `ReviewRun` models with relationships and enum/datetime casts.
- Added focused schema/model feature tests that prove persistence, relationships, status casting, safe error text, lifecycle timestamps, and regular timestamps.

## Task Commits

Each implementation task was committed atomically:

1. **Task 1: Prove schema, relationships, and status vocabulary with failing tests** - `d63fb79` (test)
2. **Task 2: Implement migrations, models, and status enum** - `ffcbe37` (feat)
3. **Task 3: Verify the foundation remains narrow** - no code changes; verification completed against committed task output

**Plan metadata:** pending commit

## Files Created/Modified

- `app/Enums/ReviewRunStatus.php` - Defines the exact review run status vocabulary as a backed enum.
- `app/Models/GitHubRepository.php` - Represents tracked GitHub repository identity and owns pull request relationships.
- `app/Models/PullRequest.php` - Represents GitHub pull request identity and owns review run relationships.
- `app/Models/ReviewRun.php` - Represents one review attempt with status and lifecycle casts.
- `database/migrations/2026_06_27_000001_create_repositories_table.php` - Creates normalized repository identity storage.
- `database/migrations/2026_06_27_000002_create_pull_requests_table.php` - Creates pull request identity storage tied to repositories.
- `database/migrations/2026_06_27_000003_create_review_runs_table.php` - Creates review run status, safe error, and lifecycle storage.
- `tests/Feature/ReviewRunSchemaTest.php` - Verifies schema, relationships, enum values, and casts.

## Decisions Made

- Chose `GitHubRepository` instead of `Repository` for the model name so later repository-layer classes remain readable.
- Used `repositories.full_name` as the canonical unique normalized owner/name key while keeping submitted owner and name fields available separately.
- Kept this plan below service/repository/controller/UI scope; repository classes and creation workflows remain in `01-02`.

## Deviations from Plan

None - plan executed exactly as written.

---

**Total deviations:** 0 auto-fixed.
**Impact on plan:** No scope creep; implementation stayed within schema, model, enum, and test boundaries.

## Issues Encountered

- Local host `php` and `composer` commands are not available; all PHP verification was run inside the Laradock PHP 8.5 workspace container.
- The PHP 8.3 Laradock workspace could not run the current `vendor` tree because Composer platform checks require PHP `>= 8.4.1`. Verification used `laradock-workspace-85-1`.

## Verification

- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --testsuite=Feature --filter=ReviewRunSchemaTest` - passed, 2 tests / 16 assertions.
- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --testsuite=Feature` - passed, 3 tests / 17 assertions.
- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` - passed, 4 tests / 18 assertions.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Plan `01-02` can now build the PR URL parser, DTOs, repository-layer classes, and `ReviewRunService` on top of the committed schema and Eloquent relationships.

---
*Phase: 01-review-run-foundation-and-management-ui*
*Completed: 2026-06-27*
