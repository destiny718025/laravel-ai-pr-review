---
phase: 01-review-run-foundation-and-management-ui
plan: 01-04
subsystem: ui
tags: [laravel, blade, controller, repository, review-history, review-detail]
requires:
  - phase: 01-03
    provides: Controller-backed review run routes and minimal detail shell
provides:
  - Repository-backed review run history reads
  - Repository-backed review run detail reads
  - Recent review runs dashboard history
  - Completed review run detail shell
  - Safe failed-run display copy
affects: [phase-1-completion, review-history, review-detail]
tech-stack:
  added: []
  patterns:
    - Controller / Repository read boundary for persisted review runs
    - Blade operational history rows
    - Safe failed-state rendering from safe_error_message only
key-files:
  created:
    - tests/Feature/ReviewRunHistoryAndDetailTest.php
  modified:
    - app/Http/Controllers/ReviewController.php
    - app/Repositories/ReviewRunRepository.php
    - resources/views/layouts/app.blade.php
    - resources/views/reviews/index.blade.php
    - resources/views/reviews/show.blade.php
    - tests/Feature/ReviewRunHistoryAndDetailTest.php
key-decisions:
  - "ReviewController@index and ReviewController@show load persisted review run read models through ReviewRunRepository."
  - "The dashboard history remains a scan-friendly row list rather than adding tables, filters, or bulk actions in Phase 1."
  - "Failed runs render only safe_error_message or the required fallback copy, never raw exceptions or provider payloads."
patterns-established:
  - "ReviewRunRepository::recentWithPullRequestRepository() returns eager-loaded recent runs newest first."
  - "ReviewRunRepository::findWithPullRequestRepositoryOrFail() owns eager-loaded detail lookup."
  - "Detail pages omit missing lifecycle timestamps instead of rendering empty placeholders."
requirements-completed: [RUN-01, RUN-05, RUN-06, RUN-07, ARCH-01, ARCH-02, ARCH-04]
coverage:
  - id: D1
    description: "The Review Runs dashboard lists recent review runs newest first with status, repository identity, PR number, source URL, created timestamp, and a detail link."
    requirement: RUN-05
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewRunHistoryAndDetailTest.php#test_reviews_dashboard_lists_recent_review_runs_newest_first"
        status: pass
      - kind: integration
        ref: "composer run test"
        status: pass
    human_judgment: false
  - id: D2
    description: "Review run detail pages show identity, status, source URL, created/updated metadata, and the pending next-step summary."
    requirement: RUN-06
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewRunHistoryAndDetailTest.php#test_review_detail_displays_identity_metadata_and_pending_summary"
        status: pass
    human_judgment: false
  - id: D3
    description: "Failed review runs show only safe error copy, safe fallback copy when absent, and the required next-step sentence."
    requirement: RUN-07
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewRunHistoryAndDetailTest.php#test_failed_review_detail_displays_only_safe_error_copy_and_next_step"
        status: pass
      - kind: integration
        ref: "tests/Feature/ReviewRunHistoryAndDetailTest.php#test_failed_review_detail_uses_safe_fallback_when_no_summary_exists"
        status: pass
    human_judgment: false
  - id: D4
    description: "Reserved review run statuses render stable title-case labels."
    requirement: RUN-06
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewRunHistoryAndDetailTest.php#test_reserved_statuses_render_title_case_labels"
        status: pass
    human_judgment: false
  - id: D5
    description: "Controller reads history and detail data through ReviewRunRepository without direct Eloquent querying in the controller."
    requirement: ARCH-02
    verification:
      - kind: other
        ref: "manual controller/repository inspection during execution"
        status: pass
    human_judgment: false
duration: 13 min
completed: 2026-06-27
status: complete
---

# Phase 01 Plan 01-04: Review Run History and Detail Summary

**Repository-backed review history and safe detail pages for persisted review runs**

## Performance

- **Duration:** 13 min
- **Started:** 2026-06-27T03:31:00Z
- **Completed:** 2026-06-27T03:44:27Z
- **Tasks:** 3
- **Files modified:** 6

## Accomplishments

- Added feature coverage for review run history ordering, detail metadata, failed-run safe copy, fallback safe copy, and reserved status labels.
- Extended `ReviewRunRepository` with eager-loaded history and detail read methods.
- Updated `ReviewController@index` and `ReviewController@show` so persisted read behavior flows through the repository boundary.
- Replaced the dashboard empty-only history area with recent review run rows showing status, repository full name, PR number, source URL, created timestamp, failed safe summary when applicable, and a `View review run` link.
- Expanded the detail shell with source URL, created/updated timestamps, optional lifecycle timestamps, pending summary, and failed-run safe error presentation.
- Added responsive row styles so long repository names and PR URLs wrap without requiring horizontal scrolling.

## Task Commits

Each implementation task was committed atomically:

1. **Task 1: Add history, detail, and failure-state feature tests** - `38b7a5a` (test)
2. **Task 2: Finish repository-backed history and detail views** - `70248ad` (feat)
3. **Task 3: Run full Phase 1 validation and UI contract audit** - no code changes; verification completed against committed task output

**Plan metadata:** pending commit

## Files Created/Modified

- `tests/Feature/ReviewRunHistoryAndDetailTest.php` - Verifies dashboard history, detail identity metadata, failed safe copy, fallback copy, required next-step sentence, and reserved status labels.
- `app/Repositories/ReviewRunRepository.php` - Adds eager-loaded recent and detail read methods.
- `app/Http/Controllers/ReviewController.php` - Loads dashboard/detail data through `ReviewRunRepository`.
- `resources/views/reviews/index.blade.php` - Renders recent review run history rows with safe failed summaries.
- `resources/views/reviews/show.blade.php` - Renders completed review run detail metadata and failed-run safe status section.
- `resources/views/layouts/app.blade.php` - Adds responsive styles for review run history rows and long URL wrapping.

## Decisions Made

- Kept the Phase 1 history list intentionally simple: no search, filters, pagination, retry, cancel, delete, queue controls, findings, drafts, approval, publishing, webhook, token setup, or AI provider setup.
- Used the existing status component unchanged because it already covered pending, queued, running, completed, failed, and cancelled with title-case labels and stable colors.
- Stored failed-run display responsibility in Blade while limiting data access to repository-loaded model relationships.

## Deviations from Plan

None - plan executed exactly as written.

---

**Total deviations:** 0 auto-fixed.
**Impact on plan:** No scope creep; implementation stayed within repository reads, controller wiring, Blade history/detail UI, responsive styles, and feature tests.

## Issues Encountered

- The first GREEN run showed the newest-first test was not actually changing timestamps because `created_at` is not fillable on the model. The test setup was corrected to use `forceFill(...)->save()` so ordering is asserted reliably.
- PHP verification continues to require the Laradock PHP 8.5 workspace because the host does not expose `php`/`composer`, and the PHP 8.3 workspace cannot satisfy the current Composer platform check.

## Verification

- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --testsuite=Feature --filter=ReviewRunHistoryAndDetailTest` - passed, 5 tests / 37 assertions.
- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --testsuite=Feature` - passed, 14 tests / 127 assertions.
- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` - passed, 15 tests / 128 assertions.
- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 ./vendor/bin/pint --dirty` - passed, 3 files.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

Phase 1 is complete as a vertical local MVP slice: submit a GitHub PR URL, create a pending review run, see it in dashboard history, and open a safe detail shell. The project is ready to move toward the next planned phase for GitHub PR diff fetching and normalization.

---
*Phase: 01-review-run-foundation-and-management-ui*
*Completed: 2026-06-27*
