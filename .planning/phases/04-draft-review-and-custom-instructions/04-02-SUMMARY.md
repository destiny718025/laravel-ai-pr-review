---
phase: 04-draft-review-and-custom-instructions
plan: 02
subsystem: review-drafts
tags: [drafts, ui, manual-generation, repositories]
requires:
  - phase: 04-01
    provides: Draft persistence schema, source finding links, and current finding semantics
provides:
  - manual comment draft generation action
  - split findings-versus-drafts review detail presentation
  - service-layer draft generation workflow
affects: [phase-04, phase-05]
tech-stack:
  added: []
  patterns: [Controller-Service-Repository workflow, generated-but-not-approved drafts]
key-files:
  created:
    - app/Http/Controllers/ReviewDraftController.php
    - app/Services/ReviewDraftService.php
    - tests/Feature/ReviewDraftGenerationTest.php
    - tests/Feature/ReviewDraftMetadataTest.php
    - tests/Feature/ReviewDraftPresentationTest.php
  modified:
    - app/Repositories/ReviewFindingRepository.php
    - app/Repositories/ReviewRunRepository.php
    - resources/views/reviews/show.blade.php
    - routes/web.php
    - tests/Feature/QueuedReviewExecutionTest.php
key-decisions:
  - "Draft generation is an explicit manual POST action on the review-run detail page."
  - "Findings and comment drafts are rendered as separate sections so the user can inspect analysis before draft workflow actions."
  - "Generated drafts copy GitHub targeting metadata from source findings and review files, but do not expose approval, editing, or publication yet."
patterns-established:
  - "ReviewDraftService owns draft workflow business logic while ReviewFindingRepository and ReviewCommentDraftRepository own database access."
  - "ReviewRunRepository loads the review detail aggregate with current findings, drafts, files, and repository metadata."
requirements-completed: [DRAFT-02, DRAFT-03, DRAFT-06]
coverage:
  - id: D3
    description: "The review detail page separates structured findings from generated comment drafts."
    requirement: DRAFT-02
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewDraftPresentationTest.php#test_review_detail_page_shows_findings_and_comment_drafts_as_separate_sections"
        status: pass
      - kind: integration
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPresentationTest|ReviewDraftGenerationTest|ReviewDraftMetadataTest|QueuedReviewExecutionTest'"
        status: pass
    human_judgment: false
  - id: D4
    description: "A manual Generate Drafts action creates one draft per current finding missing a draft."
    requirement: DRAFT-03
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewDraftGenerationTest.php#test_manual_generate_drafts_creates_comment_drafts_for_current_findings_without_existing_drafts"
        status: pass
      - kind: integration
        ref: "tests/Feature/ReviewDraftGenerationTest.php#test_manual_generate_drafts_does_not_duplicate_existing_drafts"
        status: pass
    human_judgment: false
  - id: D5
    description: "Generated drafts preserve copied targeting metadata for future GitHub-ready review comments."
    requirement: DRAFT-06
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewDraftMetadataTest.php#test_generated_draft_copies_targeting_metadata_from_source_finding_and_review_file"
        status: pass
    human_judgment: false
duration: 18min
completed: 2026-06-28
status: complete
---

# Phase 04 Plan 02: Manual Draft Generation Summary

**Review runs can now manually generate stored comment drafts from current findings, with findings and drafts displayed as separate review-detail sections.**

## Performance

- **Duration:** 18 min
- **Completed:** 2026-06-28
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments

- Added red tests for separate findings/drafts presentation, manual draft generation, idempotency, and copied targeting metadata.
- Added `ReviewDraftController` and `ReviewDraftService` for a manual `Generate Drafts` action using the existing Controller / Service / Repository layering.
- Added repository support for finding current findings without source drafts.
- Updated the review detail page to show Structured Findings and Comment Drafts separately without approval, edit, publish, or GitHub posting behavior.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lock manual draft generation in RED tests** - `3527ad6` (`test`)
2. **Task 2: Add manual draft generation workflow and split presentation** - `1fee674` (`feat`)

## Files Created/Modified

- `app/Http/Controllers/ReviewDraftController.php` - Handles manual draft generation POST requests.
- `app/Services/ReviewDraftService.php` - Creates missing comment drafts from current findings.
- `app/Repositories/ReviewFindingRepository.php` - Adds a query for current findings that do not yet have source drafts.
- `app/Repositories/ReviewRunRepository.php` - Loads the review detail aggregate required by the draft page.
- `resources/views/reviews/show.blade.php` - Separates Structured Findings from Comment Drafts and adds the manual generation action.
- `routes/web.php` - Registers `reviews.drafts.generate`.
- `tests/Feature/ReviewDraftGenerationTest.php` - Covers manual generation and idempotency.
- `tests/Feature/ReviewDraftMetadataTest.php` - Covers copied draft metadata.
- `tests/Feature/ReviewDraftPresentationTest.php` - Covers split presentation.
- `tests/Feature/QueuedReviewExecutionTest.php` - Updates the prior detail-page assertion for the new draft section.

## Decisions Made

- Generate draft text from the finding title/body currently persisted by Phase 03 instead of invoking AI again in this phase.
- Keep draft generation manual and local to the review-run detail page.
- Do not expose approval, editing, publication, or retry-stale behavior yet.

## Deviations from Plan

- Updated `QueuedReviewExecutionTest` to reflect the new Comment Drafts section introduced by this plan.

## Issues Encountered

- The external executor hit its usage limit, so this plan was completed inline while preserving the same red/green/commit workflow.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `04-03` can build on stored drafts by marking drafts stale when source findings are superseded during retry.
- Later approval/publication phases have a persisted draft list and copied GitHub metadata to work from.

## Self-Check

PASSED

- Verified red tests failed before implementation due the missing route and draft section.
- Verified target tests pass in Docker after implementation.
- Verified Pint passes on changed PHP files.
- Verified task commits `3527ad6` and `1fee674` exist in git history.
