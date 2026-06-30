---
phase: 04-draft-review-and-custom-instructions
plan: 01
subsystem: database
tags: [drafts, findings, provenance, retry]
requires:
  - phase: 03-05
    provides: Findings persistence, retry flow, and detail-page findings rendering
provides:
  - review_comment_drafts persistence foundation
  - review finding current-versus-superseded provenance semantics
  - repository seams for future draft generation and stale marking
affects: [phase-04, phase-05]
tech-stack:
  added: []
  patterns: [Enum-backed draft workflow state, supersede-not-delete retry persistence]
key-files:
  created:
    - app/Enums/ReviewCommentDraftStatus.php
    - app/Models/ReviewCommentDraft.php
    - app/Repositories/ReviewCommentDraftRepository.php
    - database/factories/ReviewCommentDraftFactory.php
    - database/migrations/2026_06_28_100000_add_superseded_at_to_review_findings_table.php
    - database/migrations/2026_06_28_100100_create_review_comment_drafts_table.php
    - tests/Feature/ReviewDraftPersistenceFoundationTest.php
  modified:
    - app/Models/ReviewFinding.php
    - app/Models/ReviewRun.php
    - app/Repositories/ReviewFindingRepository.php
    - app/Services/ReviewExecutionService.php
    - database/factories/ReviewFindingFactory.php
    - tests/Feature/QueuedReviewExecutionTest.php
key-decisions:
  - "Findings now become superseded rows on retry instead of being physically deleted, so later draft workflows can keep valid source-finding links."
  - "Draft records persist separately from findings and carry copied targeting metadata plus enum-backed status for later approval/publication phases."
patterns-established:
  - "ReviewRun::currentFindings() is the read model for active findings while ReviewRun::findings() preserves full provenance history."
  - "ReviewFindingRepository exposes explicit supersede/store-current seams instead of a destructive replace method."
requirements-completed: [DRAFT-01, DRAFT-06, DRAFT-07]
coverage:
  - id: D1
    description: "Draft persistence foundation exists with enum status, targeting metadata, and source-finding linkage."
    requirement: DRAFT-06
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewDraftPersistenceFoundationTest.php#test_review_run_loads_current_and_superseded_findings_alongside_persisted_drafts"
        status: pass
      - kind: other
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 ./vendor/bin/pint app/Enums/ReviewCommentDraftStatus.php app/Models/ReviewCommentDraft.php app/Models/ReviewFinding.php app/Models/ReviewRun.php app/Repositories/ReviewCommentDraftRepository.php app/Repositories/ReviewFindingRepository.php tests/Feature/ReviewDraftPersistenceFoundationTest.php"
        status: pass
    human_judgment: false
  - id: D2
    description: "Successful retry preserves historical findings as superseded rows while leaving a concrete current-findings read path."
    requirement: DRAFT-01
    verification:
      - kind: integration
        ref: "tests/Feature/QueuedReviewExecutionTest.php#test_successful_retry_supersedes_previous_findings_instead_of_physically_deleting_them"
        status: pass
      - kind: integration
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPersistenceFoundationTest|QueuedReviewExecutionTest'"
        status: pass
    human_judgment: false
duration: 6min
completed: 2026-06-28
status: complete
---

# Phase 04 Plan 01: Draft Persistence Foundation Summary

**Draft rows now persist independently from findings, while retry keeps historical findings as superseded records instead of deleting provenance.**

## Performance

- **Duration:** 6 min
- **Started:** 2026-06-28T11:05:31Z
- **Completed:** 2026-06-28T11:11:03Z
- **Tasks:** 2
- **Files modified:** 13

## Accomplishments

- Added `review_comment_drafts` persistence with enum-backed draft workflow status, copied GitHub targeting metadata, and `stale_at` support.
- Added `superseded_at` semantics plus `currentFindings()` so retries preserve historical findings without breaking future draft provenance.
- Replaced destructive finding replacement with explicit repository seams for superseding current findings and storing a new current set.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lock provenance-safe persistence in RED tests** - `9411a08` (`test`)
2. **Task 2: Add draft schema and superseded-finding repository seams** - `2945c5a` (`feat`)

## Files Created/Modified

- `app/Enums/ReviewCommentDraftStatus.php` - Declares the draft/approved/posted/failed status vocabulary.
- `app/Models/ReviewCommentDraft.php` - Persists draft rows with source finding linkage and targeting metadata.
- `app/Models/ReviewFinding.php` - Adds superseded casting and reverse draft provenance relation.
- `app/Models/ReviewRun.php` - Adds `currentFindings()` and `drafts()` read paths.
- `app/Repositories/ReviewCommentDraftRepository.php` - Provides future draft creation/loading/stale-marking seams.
- `app/Repositories/ReviewFindingRepository.php` - Splits retry persistence into explicit supersede/store-current operations.
- `app/Services/ReviewExecutionService.php` - Uses the new non-destructive retry persistence flow.
- `database/migrations/2026_06_28_100000_add_superseded_at_to_review_findings_table.php` - Adds provenance-safe supersede metadata.
- `database/migrations/2026_06_28_100100_create_review_comment_drafts_table.php` - Creates durable draft storage.
- `tests/Feature/ReviewDraftPersistenceFoundationTest.php` - Covers current/superseded findings and persisted draft provenance.

## Decisions Made

- Keep old findings as historical rows and use `superseded_at` as the retry boundary instead of deleting rows.
- Store comment drafts in their own table so workflow status and copied GitHub targeting metadata can evolve without mutating findings.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `ReviewCommentDraftRepository` and `ReviewCommentDraftStatus` are ready for manual draft generation in `04-02`.
- Retry persistence no longer depends on hard-deleting findings, so `04-03` can mark stale drafts without losing source provenance.

## Self-Check

PASSED

- Verified summary file exists on disk.
- Verified task commits `9411a08` and `2945c5a` exist in git history.
