---
phase: 04-draft-review-and-custom-instructions
plan: 03
subsystem: review-draft-workflow
tags: [drafts, approval, stale-drafts, retry]
requires:
  - phase: 04-02
    provides: Manual draft generation and split findings/drafts presentation
provides:
  - local draft editing
  - local draft approval and unapproval
  - stale draft marking during successful retry
affects: [phase-04, phase-05]
tech-stack:
  added: []
  patterns: [Service-layer state guards, local approval without publication, retry stale marking]
key-files:
  created:
    - tests/Feature/ReviewDraftWorkflowTest.php
  modified:
    - app/Enums/ReviewCommentDraftStatus.php
    - app/Http/Controllers/ReviewDraftController.php
    - app/Repositories/ReviewCommentDraftRepository.php
    - app/Services/ReviewDraftService.php
    - app/Services/ReviewExecutionService.php
    - resources/views/reviews/show.blade.php
    - routes/web.php
    - tests/Feature/QueuedReviewExecutionTest.php
    - tests/Feature/QueuedReviewFailureTest.php
key-decisions:
  - "Draft body edits are allowed only while a draft is in local `draft` status."
  - "Approval remains a local `approved` status transition only and still does not create any GitHub write path."
  - "Successful retry preserves existing drafts, marks them stale, supersedes current findings, and stores fresh current findings in one transaction."
patterns-established:
  - "ReviewDraftService centralizes edit, approve, and unapprove guards instead of relying on Blade visibility."
  - "ReviewCommentDraftRepository owns draft lookup and mutation helpers scoped by review run."
requirements-completed: [DRAFT-04, DRAFT-05, DRAFT-06]
coverage:
  - id: D6
    description: "Draft body updates are accepted only while the draft is in `draft` status."
    requirement: DRAFT-06
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewDraftWorkflowTest.php#test_draft_body_can_be_edited_while_status_is_draft"
        status: pass
      - kind: integration
        ref: "tests/Feature/ReviewDraftWorkflowTest.php#test_approved_draft_rejects_direct_edits_until_unapproved"
        status: pass
    human_judgment: false
  - id: D7
    description: "Selected drafts can move to local approved state and approved drafts can return to draft."
    requirement: DRAFT-04
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewDraftWorkflowTest.php#test_selected_drafts_can_be_approved_locally_without_posting_to_github"
        status: pass
      - kind: integration
        ref: "tests/Feature/ReviewDraftWorkflowTest.php#test_cancel_approval_returns_an_approved_draft_to_draft"
        status: pass
    human_judgment: false
  - id: D8
    description: "Successful retry marks existing drafts stale and still allows fresh draft generation for new current findings."
    requirement: DRAFT-05
    verification:
      - kind: integration
        ref: "tests/Feature/QueuedReviewExecutionTest.php#test_successful_retry_preserves_and_marks_existing_drafts_stale_before_new_drafts_are_generated"
        status: pass
      - kind: integration
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftWorkflowTest|QueuedReviewExecutionTest|QueuedReviewFailureTest'"
        status: pass
    human_judgment: false
duration: 27min
completed: 2026-06-28
status: complete
---

# Phase 04 Plan 03: Local Draft Workflow Summary

**Comment drafts now support local editing, approval, approval cancellation, and retry-safe stale marking without any GitHub publication path.**

## Performance

- **Duration:** 27 min
- **Completed:** 2026-06-28
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments

- Added feature coverage for draft editing, approved-edit rejection, bulk local approval, approval cancellation, stale UI cues, and retry stale generation safety.
- Extended `ReviewDraftController` with update, approve, and unapprove actions.
- Added `ReviewDraftService` guards so invalid state transitions fail server-side.
- Added repository methods for scoped draft lookup and mutation.
- Updated successful review retry to mark existing drafts stale in the same transaction as superseding findings and storing fresh current findings.
- Updated the review detail page with draft text editing, bulk approval, cancel approval, and stale draft warnings.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lock local draft workflow in RED tests** - `4ae1360` (`test`)
2. **Task 2: Implement draft state transitions and stale retry handling** - `b8e0c71` (`feat`)

## Files Created/Modified

- `tests/Feature/ReviewDraftWorkflowTest.php` - Covers local edit, approve, unapprove, stale warning, and no-publish behavior.
- `tests/Feature/QueuedReviewExecutionTest.php` - Covers stale draft marking and fresh draft generation after retry.
- `tests/Feature/QueuedReviewFailureTest.php` - Aligns retry assertions with historical finding preservation.
- `app/Enums/ReviewCommentDraftStatus.php` - Adds readable status helpers for service guards.
- `app/Http/Controllers/ReviewDraftController.php` - Adds workflow endpoints.
- `app/Repositories/ReviewCommentDraftRepository.php` - Adds scoped draft lookup and mutation operations.
- `app/Services/ReviewDraftService.php` - Owns draft workflow business rules.
- `app/Services/ReviewExecutionService.php` - Marks drafts stale during successful retry.
- `resources/views/reviews/show.blade.php` - Adds draft workflow controls and stale warnings.
- `routes/web.php` - Registers update, approve, and unapprove routes.

## Decisions Made

- Invalid state transitions return authorization failures instead of silently mutating drafts.
- Stale drafts remain visible and editable as draft rows, but are clearly labeled so the user can decide whether to reuse or replace them.
- Approval stays local; no posted status transition or GitHub client call was introduced.

## Deviations from Plan

- Updated `QueuedReviewFailureTest` because Phase 04 now preserves historical findings; the test now asserts four historical findings and two current findings after successful retry.
- Added `ReviewCommentDraftStatus` helper methods to keep service guards expressive.

## Issues Encountered

- Initial GREEN run exposed the old retry assertion in `QueuedReviewFailureTest`; it was updated to the new provenance-preserving behavior.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `04-04` can add custom instruction persistence without touching the local draft approval workflow.
- Phase 05 publication can build on approved draft state, copied targeting metadata, and stale markings.

## Self-Check

PASSED

- Verified red tests failed before implementation due missing routes and stale retry behavior.
- Verified target tests pass in Docker after implementation and after Pint.
- Verified Pint passes on changed PHP files.
- Verified task commits `4ae1360` and `b8e0c71` exist in git history.
