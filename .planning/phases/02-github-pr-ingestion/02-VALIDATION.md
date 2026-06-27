---
phase: 02
slug: github-pr-ingestion
status: ready
nyquist_compliant: true
wave_0_complete: false
created: 2026-06-27
---

# Phase 02 - Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 12.5 via Laravel test runner |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHub` |
| **Full suite command** | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` |
| **Estimated runtime** | ~30-90 seconds |

---

## Sampling Rate

- **After every task commit:** Run `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHub`
- **After every plan wave:** Run `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test`
- **Before `$gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 90 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 02-01-01 | 01 | 1 | ARCH-05 | T-02-01 | GitHub calls resolve through an interface and can be faked | unit/feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHubClient` | missing W0 | pending |
| 02-01-02 | 01 | 1 | GH-06 | T-02-02 | Tests use JSON fixtures and prevent stray GitHub requests | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHubFixture` | missing W0 | pending |
| 02-02-01 | 02 | 2 | GH-02 | T-02-03 | Manual fetch obtains PR metadata through the client boundary | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=PullRequestMetadata` | missing W0 | pending |
| 02-02-02 | 02 | 2 | GH-03 | T-02-04 | Manual fetch stores changed-file filename, patch, and sha from GitHub files API | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=PullRequestFiles` | missing W0 | pending |
| 02-02-03 | 02 | 2 | GH-04 | T-02-05 | Persisted data keeps commit SHA prerequisites while deferring line parsing | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=DiffMetadata` | missing W0 | pending |
| 02-03-01 | 03 | 3 | GH-05 | T-02-06 | GitHub failures mark the run failed with safe summarized messages only | feature/unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHubFailure` | missing W0 | pending |
| 02-03-02 | 03 | 3 | GH-06 | T-02-07 | Real GitHub API calls are blocked in tests | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHub` | missing W0 | pending |

*Status: pending / green / red / flaky*

---

## Wave 0 Requirements

`wave_0_complete: false` means the Wave 0 test artifacts are planned and named, but they do not exist yet because execution has not started.

- [ ] `tests/Feature/GitHubPullRequestIngestionTest.php` - happy-path metadata and files ingestion coverage.
- [ ] `tests/Feature/GitHubPullRequestIngestionFailureTest.php` - unreadable, rate-limit, malformed-response, and upstream-failure coverage.
- [ ] `tests/Fixtures/GitHub/pull-request.json` - PR metadata fixture.
- [ ] `tests/Fixtures/GitHub/pull-request-files-page-1.json` - changed files fixture with `filename`, `patch`, and `sha`.
- [ ] `tests/Unit/GitHub/GitHubFailureMapperTest.php` or equivalent pure-class coverage - safe failure categorization.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Manual review detail fetch button is reachable and shows success/failure flash state | GH-02, GH-05 | UI click flow may be covered by a feature test but should be manually sanity-checked during execution | Open a review run detail page, trigger Fetch, confirm the user sees a non-secret success or safe failure message |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all missing references
- [ ] No watch-mode flags
- [ ] Feedback latency < 90s
- [x] `nyquist_compliant: true` set in frontmatter because the current plan set includes automated verification coverage and named Wave 0 artifacts

**Approval:** pending
