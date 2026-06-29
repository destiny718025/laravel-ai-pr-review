---
phase: 05-github-comment-publishing
plan: 02
subsystem: api
tags: [github, comment-publishing, service, database, testing]
requires:
  - phase: 05-01
    provides: fakeable GitHub publication DTOs, client methods, and publication-safe failure mapping
provides:
  - approved-draft publication orchestration through the GitHub client boundary
  - failed-draft retry orchestration with per-draft safe persistence
  - publication result summary data for later controller flash messaging
affects: [phase-05, review-detail-ui, publication-controller]
tech-stack:
  added: []
  patterns: [service-level publish-retry orchestration, per-draft safe publication persistence]
key-files:
  created:
    - app/Data/ReviewCommentPublishingResult.php
    - app/Services/ReviewCommentPublishingService.php
    - database/migrations/2026_06_29_000000_add_publication_columns_to_review_comment_drafts_table.php
  modified:
    - app/Enums/ReviewCommentDraftStatus.php
    - app/Models/ReviewCommentDraft.php
    - app/Repositories/ReviewCommentDraftRepository.php
    - tests/Feature/ReviewCommentPublishingServiceTest.php
key-decisions:
  - "ReviewCommentPublishingService exposes separate publish-approved and retry-failed entry points, but both share one per-draft workflow so status filtering stays explicit."
  - "Line-level GitHub review comments are attempted only when local metadata is sufficient; otherwise the service falls back to one issue comment per draft."
  - "Repository success writes clear prior failure fields, and failure writes clear prior GitHub comment fields so drafts persist only one safe local publication outcome at a time."
patterns-established:
  - "All publication flows through App\\Contracts\\GitHub\\GitHubClient; application services do not call Http:: directly."
  - "Per-draft publication persistence is independent, so one draft failure never rolls back an earlier posted draft in the same batch."
requirements-completed: [PUB-02, PUB-03, PUB-04, PUB-05, PUB-06]
coverage:
  - id: D1
    description: "Approved drafts publish only from approved status, choose line-level versus fallback comment mode, and preserve prior successes when a later draft fails."
    requirement: PUB-06
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewCommentPublishingServiceTest.php#test_publish_approved_processes_only_approved_drafts_and_keeps_prior_success_when_a_later_draft_fails"
        status: pass
      - kind: other
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=ReviewCommentPublishingServiceTest"
        status: pass
    human_judgment: false
  - id: D2
    description: "Failed drafts retry only from failed status and overwrite prior safe failure data with posted metadata on success."
    requirement: PUB-03
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewCommentPublishingServiceTest.php#test_retry_failed_processes_only_failed_drafts_and_overwrites_prior_failure_with_success"
        status: pass
      - kind: other
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=ReviewCommentPublishingServiceTest"
        status: pass
    human_judgment: false
  - id: D3
    description: "Draft publication persists only safe GitHub ids, HTML URLs, timestamps, and categorized failure fields while fallback comments remain fakeable."
    requirement: PUB-04
    verification:
      - kind: integration
        ref: "tests/Feature/ReviewCommentPublishingServiceTest.php#test_publish_approved_uses_issue_comment_fallback_when_target_metadata_is_insufficient"
        status: pass
      - kind: other
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 ./vendor/bin/pint app/Data/ReviewCommentPublishingResult.php app/Enums/ReviewCommentDraftStatus.php app/Models/ReviewCommentDraft.php app/Repositories/ReviewCommentDraftRepository.php app/Services/ReviewCommentPublishingService.php tests/Feature/ReviewCommentPublishingServiceTest.php"
        status: pass
    human_judgment: false
duration: 3min
completed: 2026-06-29
status: complete
---

# Phase 05 Plan 02: GitHub Comment Publishing Summary

**Approved and failed draft publication now runs through a synchronous service that records only safe per-draft GitHub outcomes for later UI actions.**

## Performance

- **Duration:** 3 min
- **Started:** 2026-06-29T02:57:47Z
- **Completed:** 2026-06-29T03:00:18Z
- **Tasks:** 2
- **Files modified:** 7

## Accomplishments

- Added RED-to-GREEN service coverage for approved-only publication, failed-only retry, fallback issue comments, partial success, and safe error persistence.
- Added publication columns and repository mutations so each draft can persist posted GitHub metadata or a safe categorized failure without storing raw GitHub payloads.
- Added `ReviewCommentPublishingService` and `ReviewCommentPublishingResult` so later controllers can publish approved drafts or retry failed drafts through `App\Contracts\GitHub\GitHubClient`.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lock draft publication persistence and service semantics in RED tests** - `73fe02b` (`test`)
2. **Task 2: Implement draft publication schema, repository, and service workflow** - `62e47ab` (`feat`)

## Files Created/Modified

- `app/Data/ReviewCommentPublishingResult.php` - Small summary DTO exposing mode and per-batch attempted/published/failed counts.
- `app/Services/ReviewCommentPublishingService.php` - Loads a review run aggregate, filters drafts by mode, chooses line-level or fallback publication, and persists each draft result independently.
- `database/migrations/2026_06_29_000000_add_publication_columns_to_review_comment_drafts_table.php` - Adds safe GitHub publication result and failure columns to draft persistence.
- `app/Repositories/ReviewCommentDraftRepository.php` - Adds approved/failed draft queries plus posted and failed state mutation methods.
- `app/Models/ReviewCommentDraft.php` - Extends fillable/casts and adds helpers for line-level target sufficiency.
- `app/Enums/ReviewCommentDraftStatus.php` - Adds `isPosted()` and `isFailed()` helpers for later service and UI logic.
- `tests/Feature/ReviewCommentPublishingServiceTest.php` - Exercises publish-approved and retry-failed behavior with a fake `GitHubClient`.

## Decisions Made

- Kept publish and retry as separate service entry points so Phase 05 UI can call explicit approved-only versus failed-only actions without ambiguous filtering.
- Treated insufficient local line metadata as a local fallback case, but left GitHub-rejected line targets as safe `target_invalid` failures instead of silently retrying with a different comment type.
- Persisted publication results directly on `review_comment_drafts` so later controllers and views can render row-level posted/failed state without separate join tables or denormalized GitHub payload storage.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 05-03 can add publish/retry controller actions and detail-page UI on top of `ReviewCommentPublishingService` without touching raw GitHub HTTP calls.
- Posted drafts now have stable local metadata (`github_comment_id`, `github_comment_html_url`, `posted_at`) and failed drafts have safe error fields ready for row-level rendering.

## Self-Check: PASSED

- Verified `.planning/phases/05-github-comment-publishing/05-02-SUMMARY.md` exists on disk.
- Verified task commits `73fe02b` and `62e47ab` exist in git history.
- Verified `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=ReviewCommentPublishingServiceTest` passes.
- Verified `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 ./vendor/bin/pint app/Data/ReviewCommentPublishingResult.php app/Enums/ReviewCommentDraftStatus.php app/Models/ReviewCommentDraft.php app/Repositories/ReviewCommentDraftRepository.php app/Services/ReviewCommentPublishingService.php tests/Feature/ReviewCommentPublishingServiceTest.php` passes.
