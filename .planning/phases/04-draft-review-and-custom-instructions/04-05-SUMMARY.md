---
phase: 04-draft-review-and-custom-instructions
plan: 05
subsystem: ai-instructions
tags: [custom-instructions, ai-request, retry, builder]
requires:
  - phase: 04-04
    provides: Global custom review instructions settings
provides:
  - deterministic default-plus-custom instruction composition
  - execution-time settings lookup for new runs and retries
  - request-level integration without provider interface changes
affects: [phase-04, phase-05]
tech-stack:
  added: []
  patterns: [Deterministic prompt composition, latest-settings-at-request-build]
key-files:
  created:
    - tests/Unit/AI/ReviewInstructionBuilderTest.php
  modified:
    - app/Services/AI/ReviewInstructionBuilder.php
    - app/Services/ReviewExecutionService.php
    - tests/Feature/QueuedReviewExecutionTest.php
key-decisions:
  - "Default AI review guidance remains first and custom instructions append only when non-empty."
  - "ReviewExecutionService reads global custom instructions at request-build time for every execution and retry."
  - "The provider interface and AIReviewRequest shape remain unchanged; only the composed instructions string changes."
patterns-established:
  - "ReviewInstructionBuilder::buildWithCustomInstructions() is the single deterministic composition point."
  - "Mutable settings are not snapshotted onto review_runs, findings, drafts, or stale markers."
requirements-completed: [RULE-03, RULE-04]
coverage:
  - id: R4
    description: "Blank custom instructions leave default instructions unchanged; non-empty text appends in one stable section."
    requirement: RULE-03
    verification:
      - kind: unit
        ref: "tests/Unit/AI/ReviewInstructionBuilderTest.php#test_blank_custom_instructions_return_default_instructions_only"
        status: pass
      - kind: unit
        ref: "tests/Unit/AI/ReviewInstructionBuilderTest.php#test_custom_instructions_are_appended_after_default_guidance"
        status: pass
    human_judgment: false
  - id: R5
    description: "New executions and retries include the latest saved custom instructions without rewriting generated artifacts."
    requirement: RULE-04
    verification:
      - kind: integration
        ref: "tests/Feature/QueuedReviewExecutionTest.php#test_future_execution_and_retry_requests_include_latest_saved_custom_instructions_without_rewriting_existing_artifacts"
        status: pass
      - kind: integration
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPersistenceFoundationTest|ReviewDraftPresentationTest|ReviewDraftGenerationTest|ReviewDraftMetadataTest|ReviewDraftWorkflowTest|CustomReviewInstructionsTest|CustomReviewInstructionsPersistenceTest|ReviewInstructionBuilderTest|QueuedReviewExecutionTest|QueuedReviewFailureTest'"
        status: pass
    human_judgment: false
duration: 13min
completed: 2026-06-28
status: complete
---

# Phase 04 Plan 05: AI Request Instruction Integration Summary

**Saved custom review instructions are now deterministically appended to future AI review requests and retries while keeping historical artifacts unchanged.**

## Performance

- **Duration:** 13 min
- **Completed:** 2026-06-28
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- Added unit coverage for default-only and default-plus-custom instruction composition.
- Added feature coverage using a captured fake AI provider request to prove new executions and retries include the latest saved instructions.
- Implemented `ReviewInstructionBuilder::buildWithCustomInstructions()` with default guidance first and one stable custom-instructions section.
- Updated `ReviewExecutionService` to read `ReviewInstructionSettingRepository::findGlobal()` at request-build time.
- Preserved the AI provider abstraction and request DTO shape.

## Task Commits

Each task was committed atomically:

1. **Task 1: Lock future request instruction composition in RED tests** - `2f887e9` (`test`)
2. **Task 2: Compose saved instructions into AI review requests** - `7de1ec1` (`feat`)

## Files Created/Modified

- `tests/Unit/AI/ReviewInstructionBuilderTest.php` - Covers deterministic builder composition.
- `tests/Feature/QueuedReviewExecutionTest.php` - Covers latest saved instructions for execution and retry.
- `app/Services/AI/ReviewInstructionBuilder.php` - Adds default-plus-custom composition.
- `app/Services/ReviewExecutionService.php` - Loads latest saved settings when building provider requests.

## Decisions Made

- Append custom text under `Custom Review Instructions:` instead of altering or replacing default safety guidance.
- Read settings at request-build time so retries pick up current user preferences.
- Do not snapshot custom instructions to review runs in this phase.

## Deviations from Plan

None - plan executed as written.

## Issues Encountered

None.

## User Setup Required

Run migrations in the container before using Phase 04 features against a persistent local database.

## Next Phase Readiness

- Phase 04 is complete: generated drafts can be reviewed locally, custom instructions can be managed, and future AI requests use the latest saved settings.
- Phase 05 can build on approved drafts and copied targeting metadata to add safe GitHub publication.

## Self-Check

PASSED

- Verified red tests failed before implementation due missing builder composition and missing settings integration.
- Verified target tests pass after implementation.
- Verified Phase 04 related regression suite passes: 25 tests, 193 assertions.
- Verified Pint passes on changed PHP files.
- Verified task commits `2f887e9` and `7de1ec1` exist in git history.
