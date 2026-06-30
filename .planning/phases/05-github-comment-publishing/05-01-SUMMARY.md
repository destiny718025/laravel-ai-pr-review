---
phase: 05-github-comment-publishing
plan: 01
subsystem: github-api
tags: [github, comment-publishing, http-client, dto, testing]
requires:
  - phase: 04-05
    provides: approved draft metadata and copied GitHub targeting fields
provides:
  - fakeable GitHub review comment publication methods
  - fallback issue comment publication methods
  - publication-safe GitHub failure categorization
affects: [phase-05, publication-service, review-detail-ui]
tech-stack:
  added: []
  patterns: [GitHub publication DTO boundary, context-specific failure mapping]
key-files:
  created:
    - app/Data/GitHub/GitHubCommentPublicationResult.php
    - app/Data/GitHub/GitHubCommentPublicationTarget.php
    - tests/Unit/GitHub/HttpGitHubClientPublicationTest.php
  modified:
    - app/Contracts/GitHub/GitHubClient.php
    - app/Services/GitHub/GitHubFailureMapper.php
    - app/Services/GitHub/HttpGitHubClient.php
    - tests/Unit/GitHub/GitHubFailureMapperTest.php
key-decisions:
  - "GitHubFailureMapper gained a dedicated publication path so publish-safe messages can differ from fetch-safe messages without regressing ingestion."
  - "Publication results are reduced to id, htmlUrl, and postedAt so later phases never depend on raw GitHub payloads."
  - "Line-level review comment payload construction lives in GitHubCommentPublicationTarget and always emits side RIGHT for the MVP."
patterns-established:
  - "All GitHub publication continues to flow through App\\Contracts\\GitHub\\GitHubClient; later services must not call Http:: directly."
  - "Publication response parsing fails closed with UnexpectedValueException when id, html_url, or timestamp are missing."
requirements-completed: [PUB-02, PUB-04, PUB-05]
coverage:
  - id: D1
    description: "GitHubClient publishes line-level pull request review comments through HttpGitHubClient with the expected body, commit_id, path, line, and side payload."
    requirement: PUB-02
    verification:
      - kind: unit
        ref: "tests/Unit/GitHub/HttpGitHubClientPublicationTest.php#test_client_posts_line_level_pull_request_review_comments_without_live_requests"
        status: pass
      - kind: integration
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='HttpGitHubClientPublicationTest|GitHubFailureMapperTest'"
        status: pass
    human_judgment: false
  - id: D2
    description: "Fallback issue comments use the same GitHub client boundary and reduce GitHub responses to id, htmlUrl, and postedAt only."
    requirement: PUB-05
    verification:
      - kind: unit
        ref: "tests/Unit/GitHub/HttpGitHubClientPublicationTest.php#test_client_posts_fallback_issue_comments_without_live_requests"
        status: pass
      - kind: integration
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='HttpGitHubClientPublicationTest|GitHubFailureMapperTest'"
        status: pass
    human_judgment: false
  - id: D3
    description: "Publication failures map to safe categories, including target_invalid, without exposing raw GitHub bodies, headers, or tokens."
    requirement: PUB-04
    verification:
      - kind: unit
        ref: "tests/Unit/GitHub/GitHubFailureMapperTest.php#test_maps_publication_target_validation_to_safe_message"
        status: pass
      - kind: unit
        ref: "tests/Unit/GitHub/GitHubFailureMapperTest.php#test_maps_publication_authenticated_rejection_to_safe_message"
        status: pass
      - kind: unit
        ref: "tests/Unit/GitHub/GitHubFailureMapperTest.php#test_maps_publication_rate_limit_response_to_safe_message"
        status: pass
      - kind: unit
        ref: "tests/Unit/GitHub/GitHubFailureMapperTest.php#test_maps_publication_malformed_payload_to_safe_message"
        status: pass
    human_judgment: false
duration: 3min
completed: 2026-06-29
status: complete
---

# Phase 05 Plan 01: GitHub Publication Client Boundary Summary

**GitHub publication now has fakeable review-comment and fallback issue-comment write paths with minimal DTOs and publication-safe failure mapping.**

## Performance

- **Duration:** 3 min
- **Started:** 2026-06-28T23:32:12Z
- **Completed:** 2026-06-28T23:34:49Z
- **Tasks:** 2
- **Files modified:** 9

## Accomplishments

- Added RED tests and fixtures that lock both GitHub publication endpoints, request payload shapes, and minimal response parsing.
- Added `GitHubCommentPublicationTarget` and `GitHubCommentPublicationResult` so later publication services can work through DTOs instead of raw GitHub JSON.
- Extended `GitHubClient` and `HttpGitHubClient` with line-level review comment and fallback issue comment write methods.
- Split GitHub failure mapping into fetch-safe and publication-safe paths, including `target_invalid` for invalid review comment targets.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lock GitHub publication endpoints and safe failure mapping in RED tests** - `f76f90a` (`test`)
2. **Task 2: Implement GitHub publication DTOs and HTTP client write methods** - `818abf4` (`feat`)

## Files Created/Modified

- `app/Data/GitHub/GitHubCommentPublicationResult.php` - Minimal GitHub publication result DTO with strict payload parsing.
- `app/Data/GitHub/GitHubCommentPublicationTarget.php` - Publication target DTO that builds review-comment and fallback issue-comment payloads.
- `app/Contracts/GitHub/GitHubClient.php` - Adds explicit publication methods to the only GitHub app boundary.
- `app/Services/GitHub/HttpGitHubClient.php` - Implements both GitHub write endpoints using the existing request helper.
- `app/Services/GitHub/GitHubFailureMapper.php` - Adds publication-specific safe error mapping while preserving fetch behavior.
- `tests/Unit/GitHub/HttpGitHubClientPublicationTest.php` - Verifies both POST endpoints, payload shapes, and minimal result parsing under `Http::fake()`.
- `tests/Unit/GitHub/GitHubFailureMapperTest.php` - Verifies publication-safe target-invalid, auth, rate-limit, and malformed-response mapping.

## Decisions Made

- Preserve existing ingestion error messages by adding `mapPublication()` instead of repurposing `map()`.
- Fail closed on malformed publication payloads rather than passing partial GitHub response data downstream.
- Keep fallback issue comment requests limited to the `body` field so later draft services stay decoupled from GitHub-specific extras.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Corrected the immutable Carbon import used by publication result parsing**
- **Found during:** Task 2 (Implement GitHub publication DTOs and HTTP client write methods)
- **Issue:** The initial DTO implementation referenced `Illuminate\\Support\\CarbonImmutable`, which is not available in this Laravel runtime and caused the publication tests to fail before behavior could be verified.
- **Fix:** Switched to `Carbon\\CarbonImmutable` and reran the publication test suite.
- **Files modified:** `app/Data/GitHub/GitHubCommentPublicationResult.php`
- **Verification:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='HttpGitHubClientPublicationTest|GitHubFailureMapperTest'`
- **Committed in:** `818abf4`

---

**Total deviations:** 1 auto-fixed (Rule 3: blocking issue)
**Impact on plan:** The fix was required for correctness and did not change scope or public behavior.

## Issues Encountered

- Carbon timestamp serialization included microseconds in `toISOString()`, so the publication tests were tightened to assert the parsed UTC instant instead of Carbon formatting details.

## User Setup Required

None - no external service configuration was added in this plan.

## Next Phase Readiness

- Phase 05-02 can build the publish/retry service on top of `GitHubClient` without introducing direct `Http::` calls.
- Draft persistence and UI work can safely store only `id`, `htmlUrl`, `postedAt`, and categorized failure messages.

## Self-Check

PASSED

- Verified `.planning/phases/05-github-comment-publishing/05-01-SUMMARY.md` exists on disk.
- Verified task commits `f76f90a` and `818abf4` exist in git history.
- Verified `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='HttpGitHubClientPublicationTest|GitHubFailureMapperTest'` passes.
- Verified `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 ./vendor/bin/pint app/Contracts/GitHub/GitHubClient.php app/Data/GitHub/GitHubCommentPublicationResult.php app/Data/GitHub/GitHubCommentPublicationTarget.php app/Services/GitHub/GitHubFailureMapper.php app/Services/GitHub/HttpGitHubClient.php tests/Unit/GitHub/HttpGitHubClientPublicationTest.php tests/Unit/GitHub/GitHubFailureMapperTest.php` passes.
