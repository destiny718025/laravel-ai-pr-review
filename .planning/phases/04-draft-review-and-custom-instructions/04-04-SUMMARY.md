---
phase: 04-draft-review-and-custom-instructions
plan: 04
subsystem: review-instructions
tags: [custom-instructions, settings, singleton, ui]
requires:
  - phase: 04-03
    provides: Local draft workflow and review-detail management UI surface
provides:
  - global custom review instructions storage
  - custom instructions management UI
  - isolated settings validation error bag
affects: [phase-04, phase-05]
tech-stack:
  added: []
  patterns: [Repository-owned singleton settings row, named validation error bag, settings separate from generated artifacts]
key-files:
  created:
    - app/Http/Controllers/ReviewInstructionSettingController.php
    - app/Models/ReviewInstructionSetting.php
    - app/Repositories/ReviewInstructionSettingRepository.php
    - app/Services/ReviewInstructionSettingService.php
    - database/migrations/2026_06_28_100200_create_review_instruction_settings_table.php
    - tests/Feature/CustomReviewInstructionsPersistenceTest.php
    - tests/Feature/CustomReviewInstructionsTest.php
  modified:
    - app/Http/Controllers/ReviewController.php
    - resources/views/reviews/show.blade.php
    - routes/web.php
key-decisions:
  - "Custom review instructions live in one global singleton settings row keyed by `scope = global`."
  - "Empty or whitespace-only custom instructions normalize to null."
  - "Settings validation uses the `instructions` error bag so draft workflow forms remain isolated."
patterns-established:
  - "ReviewInstructionSettingService handles normalization while ReviewInstructionSettingRepository owns singleton persistence."
  - "Review detail pages receive current global instructions from the service layer instead of reading settings directly in Blade."
requirements-completed: [RULE-01, RULE-02, RULE-04]
coverage:
  - id: R1
    description: "The review detail page renders one global Custom Review Instructions textarea with the current stored value."
    requirement: RULE-01
    verification:
      - kind: integration
        ref: "tests/Feature/CustomReviewInstructionsTest.php#test_review_detail_page_renders_global_custom_review_instructions"
        status: pass
    human_judgment: false
  - id: R2
    description: "Saving custom instructions validates input with an isolated named error bag and normalizes blank input to null."
    requirement: RULE-02
    verification:
      - kind: integration
        ref: "tests/Feature/CustomReviewInstructionsTest.php#test_custom_review_instructions_can_be_saved_with_isolated_validation_errors"
        status: pass
      - kind: integration
        ref: "tests/Feature/CustomReviewInstructionsTest.php#test_blank_custom_review_instructions_are_normalized_to_null"
        status: pass
    human_judgment: false
  - id: R3
    description: "Custom instructions persist separately and do not rewrite existing findings or drafts."
    requirement: RULE-04
    verification:
      - kind: integration
        ref: "tests/Feature/CustomReviewInstructionsPersistenceTest.php#test_custom_instructions_are_stored_separately_from_findings_and_drafts"
        status: pass
      - kind: integration
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='CustomReviewInstructionsTest|CustomReviewInstructionsPersistenceTest|ReviewDraftWorkflowTest|QueuedReviewExecutionTest|QueuedReviewFailureTest'"
        status: pass
    human_judgment: false
duration: 20min
completed: 2026-06-28
status: complete
---

# Phase 04 Plan 04: Custom Instructions Settings Summary

**The review detail page now exposes one global Custom Review Instructions textarea backed by a dedicated singleton settings table.**

## Performance

- **Duration:** 20 min
- **Completed:** 2026-06-28
- **Tasks:** 2
- **Files modified:** 10

## Accomplishments

- Added red tests for settings UI, validation isolation, blank normalization, and persistence boundaries.
- Created `review_instruction_settings` with unique `scope` and nullable `custom_instructions`.
- Added `ReviewInstructionSetting` model, repository, service, and update controller.
- Added `PUT /review-instructions` with named route `review-instructions.update`.
- Updated review detail pages to show and save one global custom-instructions textarea.
- Verified saving settings does not mutate existing findings, drafts, draft status, stale markers, or generated artifacts.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lock custom instructions settings in RED tests** - `3f1815a` (`test`)
2. **Task 2: Add singleton settings persistence and UI** - `6ed9bcb` (`feat`)

## Files Created/Modified

- `database/migrations/2026_06_28_100200_create_review_instruction_settings_table.php` - Creates the dedicated settings table.
- `app/Models/ReviewInstructionSetting.php` - Represents the settings row.
- `app/Repositories/ReviewInstructionSettingRepository.php` - Owns singleton lookup and persistence.
- `app/Services/ReviewInstructionSettingService.php` - Normalizes and updates global instructions.
- `app/Http/Controllers/ReviewInstructionSettingController.php` - Handles settings form submission.
- `app/Http/Controllers/ReviewController.php` - Provides current instructions to the review detail page.
- `resources/views/reviews/show.blade.php` - Adds the custom-instructions section.
- `routes/web.php` - Registers the settings update route.
- `tests/Feature/CustomReviewInstructionsTest.php` - Covers UI, validation, saving, and blank normalization.
- `tests/Feature/CustomReviewInstructionsPersistenceTest.php` - Covers separate persistence and generated-artifact immutability.

## Decisions Made

- Use one global settings record for the MVP, not per-repository or per-team rule sets.
- Normalize whitespace-only input to `null` so future prompt integration can distinguish empty settings deterministically.
- Keep this phase limited to management/storage; prompt integration remains in `04-05`.

## Deviations from Plan

None - plan executed as written.

## Issues Encountered

None.

## User Setup Required

Run migrations in the container before using the new table in a persistent local database.

## Next Phase Readiness

- `04-05` can read the global settings through `ReviewInstructionSettingService` or repository and merge it into AI review instructions.
- The settings form is isolated from draft workflow validation and does not alter generated artifacts.

## Self-Check

PASSED

- Verified red tests failed before implementation due missing route/table.
- Verified target tests pass in Docker after implementation.
- Verified draft workflow regression tests still pass with the new settings section.
- Verified Pint passes on changed PHP files.
- Verified task commits `3f1815a` and `6ed9bcb` exist in git history.
