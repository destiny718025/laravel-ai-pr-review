---
phase: 05-github-comment-publishing
plan: 03
subsystem: ui
tags: [github, publishing, blade, controller, testing]
requires:
  - phase: 05-01
    provides: fakeable GitHub publication client methods and safe publication failure mapping
  - phase: 05-02
    provides: review comment publishing service and per-draft posted/failed persistence
provides:
  - review detail page publish-approved and retry-failed actions
  - read-only posted and failed draft row rendering with safe local metadata
  - feature-tested publish and retry route coverage through the GitHub client interface
affects: [phase-05, review-detail-ui, manual-review-workflow]
tech-stack:
  added: []
  patterns: [section-level publish-retry forms, thin controller flash summaries, posted-failed UI locking]
key-files:
  created:
    - tests/Feature/ReviewCommentPublishingWorkflowTest.php
  modified:
    - app/Http/Controllers/ReviewDraftController.php
    - resources/views/reviews/show.blade.php
    - routes/web.php
    - tests/Feature/ReviewDraftWorkflowTest.php
key-decisions:
  - "Publish Approved and Retry Failed stay as section-level POST forms inside Comment Drafts instead of any per-draft publish selector."
  - "ReviewDraftController formats only safe count-based flash summaries while ReviewCommentPublishingService continues to own publication filtering and GitHub interaction."
  - "Posted and failed rows remain locally read-only in the detail view and route-level tests enforce the same lock semantics for update and unapprove actions."
patterns-established:
  - "Comment Drafts UI derives publish and retry button visibility from local draft statuses only."
  - "Publishing workflow feature tests bind a fake GitHubClient so detail-page HTTP behavior stays deterministic without live GitHub calls."
requirements-completed: [PUB-01, PUB-05, PUB-06]
coverage:
  - id: D1
    description: "The review detail page exposes one-click Publish Approved and Retry Failed actions only when the relevant draft statuses exist."
    requirement: PUB-01
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewCommentPublishingWorkflowTest.php#test_review_detail_shows_publish_and_retry_actions_only_when_relevant_drafts_exist"
        status: pass
      - kind: integration
        ref: "tests/Feature/ReviewCommentPublishingWorkflowTest.php#test_review_detail_hides_publish_and_retry_actions_when_no_relevant_drafts_exist"
        status: pass
      - kind: other
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewCommentPublishingWorkflowTest|ReviewDraftWorkflowTest|ReviewCommentPublishingServiceTest'"
        status: pass
    human_judgment: false
  - id: D2
    description: "Publish-approved and retry-failed controller actions redirect back to the review detail page with safe summary flash messages and publish only through the fake GitHub client boundary."
    requirement: PUB-05
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewCommentPublishingWorkflowTest.php#test_publish_approved_route_redirects_back_with_summary_and_publishes_only_approved_drafts"
        status: pass
      - kind: integration
        ref: "tests/Feature/ReviewCommentPublishingWorkflowTest.php#test_retry_failed_route_redirects_back_with_summary_and_retries_only_failed_drafts"
        status: pass
    human_judgment: false
  - id: D3
    description: "Posted and failed drafts render read-only metadata and remain locked from edit and unapprove routes after publication state changes."
    requirement: PUB-06
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewDraftWorkflowTest.php#test_posted_draft_rejects_direct_edits"
        status: pass
      - kind: integration
        ref: "tests/Feature/ReviewDraftWorkflowTest.php#test_failed_draft_rejects_direct_edits"
        status: pass
      - kind: integration
        ref: "tests/Feature/ReviewDraftWorkflowTest.php#test_posted_draft_rejects_cancel_approval"
        status: pass
      - kind: integration
        ref: "tests/Feature/ReviewDraftWorkflowTest.php#test_failed_draft_rejects_cancel_approval"
        status: pass
    human_judgment: false
duration: 2min
completed: 2026-06-29
status: complete
---

# Phase 05 Plan 03: GitHub Comment Publishing Summary

**The review detail page now finishes the manual GitHub publishing workflow with section-level publish and retry actions, safe row metadata, and locked posted/failed drafts.**

## Performance

- **Duration:** 2 min
- **Started:** 2026-06-29T03:06:23Z
- **Completed:** 2026-06-29T03:07:49Z
- **Tasks:** 2
- **Files modified:** 5

## Accomplishments

- Added RED feature coverage for publish/retry detail-page behavior, safe row rendering, and route-level lock semantics for posted and failed drafts.
- Added thin `ReviewDraftController` publish and retry actions plus POST routes that redirect back with safe count-based flash summaries.
- Updated the existing Comment Drafts section to show section-level `Publish Approved` and `Retry Failed` controls and to render posted and failed local metadata without re-enabling mutable controls.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lock publish/retry detail-page behavior with RED feature tests** - `cdef093` (`test`)
2. **Task 2: Add publish/retry routes, controller actions, and Comment Drafts UI** - `b979947` (`feat`)

## Files Created/Modified

- `tests/Feature/ReviewCommentPublishingWorkflowTest.php` - End-to-end feature coverage for detail-page button visibility, publish/retry routes, and safe posted/failed row rendering.
- `tests/Feature/ReviewDraftWorkflowTest.php` - Route-level lock assertions for posted and failed drafts plus no per-draft publish selector coverage.
- `app/Http/Controllers/ReviewDraftController.php` - Thin publish and retry actions that delegate to `ReviewCommentPublishingService` and flash safe summaries.
- `routes/web.php` - Adds `reviews.drafts.publish-approved` and `reviews.drafts.retry-failed` POST routes next to the existing draft workflow routes.
- `resources/views/reviews/show.blade.php` - Renders section-level publish/retry forms and read-only posted/failed draft metadata in the Comment Drafts section.

## Decisions Made

- Kept publish and retry actions in the existing Comment Drafts section to preserve the explicit human-approval flow and avoid any subset-publish UI.
- Used safe count-based controller flash messages so the controller stays HTTP-focused and does not surface GitHub payload details.
- Left posted and failed rows fully read-only in Blade while route-level tests continue to prove those states cannot be mutated through update or unapprove endpoints.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 05 is now functionally complete for the manual GitHub comment publishing MVP slice.
- The detail page can generate, approve, publish, retry, and inspect draft outcomes without introducing automatic posting or per-draft publish selection.

## Self-Check: PASSED

- Verified `.planning/phases/05-github-comment-publishing/05-03-SUMMARY.md` exists on disk.
- Verified task commits `cdef093` and `b979947` exist in git history.
- Verified `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewCommentPublishingWorkflowTest|ReviewDraftWorkflowTest|ReviewCommentPublishingServiceTest'` passes.
- Verified `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 ./vendor/bin/pint app/Http/Controllers/ReviewDraftController.php resources/views/reviews/show.blade.php routes/web.php tests/Feature/ReviewCommentPublishingWorkflowTest.php tests/Feature/ReviewDraftWorkflowTest.php` passes.
