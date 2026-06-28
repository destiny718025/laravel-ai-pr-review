---
phase: 05
slug: github-comment-publishing
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-06-29
---

# Phase 05 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Laravel test runner / PHPUnit 12 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewCommentPublishing|HttpGitHubClientPublication|GitHubFailureMapper|ReviewDraftWorkflow'` |
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
| 05-01-01 | 05-01 | 1 | PUB-02, PUB-04, PUB-05 | T-05-01, T-05-02 | GitHub publication endpoint shapes and safe failure categories are locked before implementation. | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='HttpGitHubClientPublicationTest|GitHubFailureMapperTest'` | ❌ W0 | ⬜ pending |
| 05-01-02 | 05-01 | 1 | PUB-02, PUB-04, PUB-05 | T-05-01, T-05-02 | Publication calls stay behind `GitHubClient` and responses reduce to safe DTO fields. | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='HttpGitHubClientPublicationTest|GitHubFailureMapperTest'` | ❌ W0 | ⬜ pending |
| 05-02-01 | 05-02 | 2 | PUB-02, PUB-03, PUB-04, PUB-05, PUB-06 | T-05-03, T-05-04, T-05-05 | Service semantics publish only approved drafts, retry only failed drafts, and persist safe per-draft outcomes. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=ReviewCommentPublishingServiceTest` | ❌ W0 | ⬜ pending |
| 05-02-02 | 05-02 | 2 | PUB-02, PUB-03, PUB-04, PUB-05, PUB-06 | T-05-03, T-05-04, T-05-05 | Repository and service persist partial success, retry overwrite, and failure safety without raw payload storage. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=ReviewCommentPublishingServiceTest` | ❌ W0 | ⬜ pending |
| 05-03-01 | 05-03 | 3 | PUB-01, PUB-05, PUB-06 | T-05-06, T-05-07, T-05-08 | Detail-page publish/retry actions are explicit, fakeable, and route-level mutation locks are tested. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewCommentPublishingWorkflowTest|ReviewDraftWorkflowTest'` | ❌ W0 | ⬜ pending |
| 05-03-02 | 05-03 | 3 | PUB-01, PUB-05, PUB-06 | T-05-06, T-05-07, T-05-08 | Comment Drafts UI exposes safe publish/retry controls, posted links, and failed safe errors only. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewCommentPublishingWorkflowTest|ReviewDraftWorkflowTest|ReviewCommentPublishingServiceTest'` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/GitHub/HttpGitHubClientPublicationTest.php` — HTTP endpoint shape and response parsing for GitHub publication.
- [ ] `tests/Unit/GitHub/GitHubFailureMapperTest.php` — safe GitHub publication failure categories.
- [ ] `tests/Feature/ReviewCommentPublishingServiceTest.php` — approved-only publish, failed-only retry, fallback, partial success, and safe persistence.
- [ ] `tests/Feature/ReviewCommentPublishingWorkflowTest.php` — route and detail-page publishing workflow.
- [ ] `tests/Feature/ReviewDraftWorkflowTest.php` — posted/failed mutation rejection and no per-draft publish selector.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Detail-page publish controls remain understandable | PUB-01, PUB-06 | Feature tests can assert forms and text exist, but a human scan confirms the Comment Drafts section remains usable with many forms and statuses. | Start the Laravel dev server, open a review run detail page with draft/approved/posted/failed drafts, confirm publish/retry controls are section-local and posted/failed metadata does not overlap other content. |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 180s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
