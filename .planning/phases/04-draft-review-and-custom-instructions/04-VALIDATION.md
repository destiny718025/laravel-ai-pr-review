---
phase: 04
slug: draft-review-and-custom-instructions
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-06-28
---

# Phase 04 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Laravel test runner / PHPUnit 12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraft|CustomReviewInstructions|ReviewInstructionBuilder|QueuedReviewExecutionTest'` |
| **Full suite command** | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test` |
| **Estimated runtime** | ~60-180 seconds |

---

## Sampling Rate

- **After every task commit:** Run the task-specific `<automated>` command from the active PLAN task.
- **After every plan wave:** Run `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test`
- **Before `$gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 180 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 04-01-01 | 04-01 | 1 | DRAFT-01 | T-04-01 | Current findings remain queryable after provenance-safe schema changes. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPersistenceFoundationTest|QueuedReviewExecutionTest'` | ❌ W0 | ⬜ pending |
| 04-01-02 | 04-01 | 1 | DRAFT-06, DRAFT-07 | T-04-02 | Draft rows persist explicit status plus targeting metadata independently from findings. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPersistenceFoundationTest|QueuedReviewExecutionTest'` | ❌ W0 | ⬜ pending |
| 04-02-01 | 04-02 | 2 | DRAFT-02, DRAFT-03 | T-04-03 | Manual generation is explicit, local-only, and visible beside read-only findings. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPresentationTest|ReviewDraftGenerationTest|ReviewDraftMetadataTest'` | ❌ W0 | ⬜ pending |
| 04-02-02 | 04-02 | 2 | DRAFT-02, DRAFT-07 | T-04-04 | Generated drafts are missing-only and preserve source plus targeting metadata. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPresentationTest|ReviewDraftGenerationTest|ReviewDraftMetadataTest'` | ❌ W0 | ⬜ pending |
| 04-03-01 | 04-03 | 3 | DRAFT-04, DRAFT-05 | T-04-05 | Draft edits, approval, and cancel-approval obey server-side state guards. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftWorkflowTest|QueuedReviewExecutionTest'` | ❌ W0 | ⬜ pending |
| 04-03-02 | 04-03 | 3 | DRAFT-06 | T-04-06, T-04-07 | Retry preserves drafts, marks them stale, and avoids publication side effects. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftWorkflowTest|QueuedReviewExecutionTest|QueuedReviewFailureTest'` | ❌ W0 | ⬜ pending |
| 04-04-01 | 04-04 | 4 | RULE-01, RULE-02 | T-04-08 | Current custom instructions render and update through an isolated validated form. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='CustomReviewInstructionsTest|CustomReviewInstructionsPersistenceTest'` | ❌ W0 | ⬜ pending |
| 04-04-02 | 04-04 | 4 | RULE-04 | T-04-09 | Custom instructions persist separately from findings and drafts. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='CustomReviewInstructionsTest|CustomReviewInstructionsPersistenceTest'` | ❌ W0 | ⬜ pending |
| 04-05-01 | 04-05 | 5 | RULE-03 | T-04-10 | Instruction builder composes default and saved custom guidance deterministically. | unit + feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewInstructionBuilderTest|QueuedReviewExecutionTest'` | ❌ W0 | ⬜ pending |
| 04-05-02 | 04-05 | 5 | RULE-03, RULE-04 | T-04-11 | Future executions and retries load the latest saved instructions without rewriting historical artifacts. | unit + feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewInstructionBuilderTest|QueuedReviewExecutionTest'` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/ReviewDraftPersistenceFoundationTest.php` — provenance-safe draft and finding persistence foundation.
- [ ] `tests/Feature/ReviewDraftPresentationTest.php` — findings + drafts detail rendering and generate action.
- [ ] `tests/Feature/ReviewDraftGenerationTest.php` — manual/idempotent draft generation from persisted findings.
- [ ] `tests/Feature/ReviewDraftWorkflowTest.php` — edit/approve/cancel-approval rules and stale guards.
- [ ] `tests/Feature/ReviewDraftMetadataTest.php` — source finding, file path, line reference, and targeting metadata retention.
- [ ] `tests/Feature/CustomReviewInstructionsTest.php` — view/update/settings persistence and future execution usage.
- [ ] `tests/Feature/CustomReviewInstructionsPersistenceTest.php` — separate persistence from findings and drafts.
- [ ] `tests/Unit/AI/ReviewInstructionBuilderTest.php` — deterministic default + custom instruction composition.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Visual scan of separate detail page sections | DRAFT-03, RULE-01 | Feature tests can assert text/forms exist, but human scan confirms the management UI remains usable after adding multiple forms. | Start the Laravel dev server, open a completed review run detail page, confirm Structured Findings, Comment Drafts, and Custom Review Instructions are visually distinct and do not overlap. |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 180s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
