---
phase: 03
slug: queued-ai-review-and-structured-findings
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-06-27
---

# Phase 03 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Laravel PHPUnit |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='QueuedReview|FakeAIReviewProvider|ValidatedFindingPayload|AIReviewFailureMapper|OpenAIReviewProvider'` |
| **Full suite command** | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` |
| **Estimated runtime** | ~1-2 seconds locally after container is warm |

---

## Sampling Rate

- **After every task commit:** Run `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='QueuedReview|FakeAIReviewProvider|ValidatedFindingPayload|AIReviewFailureMapper|OpenAIReviewProvider'`
- **After every plan wave:** Run `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test`
- **Before `$gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 03-01-01 | 03-01 | 1 | EXEC-01 | T-03-01 | Manual run action queues work instead of invoking provider logic inline. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=QueuedReviewDispatchTest` | ❌ W0 | ⬜ pending |
| 03-01-02 | 03-01 | 1 | EXEC-01 | T-03-01 | Dispatch service enforces the GitHub snapshot precondition before queue admission. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=QueuedReviewDispatchTest` | ❌ W0 | ⬜ pending |
| 03-01-03 | 03-01 | 1 | EXEC-02 | T-03-02 | Queued job reloads the run and performs lifecycle transitions through the execution service. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=QueuedReviewExecutionTest` | ❌ W0 | ⬜ pending |
| 03-02-01 | 03-02 | 2 | AI-01, AI-02 | T-03-04 | AI review behavior resolves through a fake-first provider contract with deterministic fixtures. | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=FakeAIReviewProviderTest` | ❌ W0 | ⬜ pending |
| 03-02-02 | 03-02 | 2 | AI-06, AI-07 | T-03-06 | Default instructions encode bug/security-first guidance and the shared vocabulary without network access. | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=FakeAIReviewProviderTest` | ❌ W0 | ⬜ pending |
| 03-03-01 | 03-03 | 3 | AI-04, AI-08 | T-03-07 | Provider output is decoded and validated against the shared structured-finding schema before persistence. | unit + feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ValidatedFindingPayloadTest|AIReviewFailureMapperTest|QueuedReviewFailureTest|QueuedReviewExecutionTest'` | ❌ W0 | ⬜ pending |
| 03-03-02 | 03-03 | 3 | EXEC-04, EXEC-05 | T-03-08 | JSON, schema, transport, and runtime failures map to safe summaries with no raw payload leakage. | unit + feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ValidatedFindingPayloadTest|AIReviewFailureMapperTest|QueuedReviewFailureTest|QueuedReviewExecutionTest'` | ❌ W0 | ⬜ pending |
| 03-04-01 | 03-04 | 3 | AI-03 | T-03-10 | Optional OpenAI adapter resolves behind the provider interface only when config enables it. | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=OpenAIReviewProviderTest` | ❌ W0 | ⬜ pending |
| 03-04-02 | 03-04 | 3 | EXEC-05 | T-03-12 | Concrete-adapter tests stay offline and keep all provider secrets in config only. | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=OpenAIReviewProviderTest` | ❌ W0 | ⬜ pending |
| 03-05-01 | 03-05 | 4 | EXEC-03, AI-05 | T-03-13 | Successful queued execution persists the full structured findings set and renders it on the detail page. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='QueuedReviewExecutionTest|QueuedReviewFailureTest'` | ❌ W0 | ⬜ pending |
| 03-05-02 | 03-05 | 4 | EXEC-03, AI-05 | T-03-13 | Findings schema and repository wiring migrate cleanly in the test environment. | migration | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan migrate:fresh --env=testing` | ❌ W0 | ⬜ pending |
| 03-05-03 | 03-05 | 4 | EXEC-03, EXEC-04 | T-03-14 | Failures stay safe and retries replace stale findings with the latest validated result. | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='QueuedReviewExecutionTest|QueuedReviewFailureTest'` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Feature/QueuedReviewDispatchTest.php` — manual run action, queue dispatch, and GitHub snapshot precondition coverage.
- [ ] `tests/Feature/QueuedReviewExecutionTest.php` — running/completed transitions plus validated execution and findings persistence coverage.
- [ ] `tests/Feature/QueuedReviewFailureTest.php` — timeout/transport, invalid JSON, invalid schema, runtime failure, and retry cleanup coverage.
- [ ] `tests/Unit/AI/FakeAIReviewProviderTest.php` — provider interface, fixture seam, and default instruction vocabulary coverage.
- [ ] `tests/Unit/AI/ValidatedFindingPayloadTest.php` — structured output schema validation and vocabulary enforcement coverage.
- [ ] `tests/Unit/AI/AIReviewFailureMapperTest.php` — safe failure classification without raw exception or payload leakage.
- [ ] `tests/Unit/AI/OpenAIReviewProviderTest.php` — opt-in concrete adapter selection and HTTP-fakeable request coverage.

---

## Manual-Only Verifications

All Phase 3 behaviors have automated verification.

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
