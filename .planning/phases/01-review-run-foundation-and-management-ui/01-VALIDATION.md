---
phase: 01
slug: review-run-foundation-and-management-ui
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-06-27
revised: 2026-06-27
---

# Phase 01 - Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Laravel PHPUnit via `php artisan test` |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --testsuite=Feature` |
| **Full suite command** | `composer run test` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --testsuite=Feature`
- **After every plan wave:** Run `composer run test`
- **Before `$gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 01-01-01 | 01-01 | 1 | RUN-04, ARCH-04 | T-01-01-01, T-01-01-02 | Status vocabulary is locked; schema persists only safe review-run lifecycle fields | feature | `php artisan test --testsuite=Feature --filter=ReviewRunSchemaTest` | yes | pending |
| 01-01-02 | 01-01 | 1 | RUN-04, ARCH-04 | T-01-01-01, T-01-01-03 | Tables, models, relationships, casts, and uniqueness constraints support repository-owned persistence | feature | `php artisan test --testsuite=Feature --filter=ReviewRunSchemaTest` | yes | pending |
| 01-01-03 | 01-01 | 1 | RUN-04, ARCH-04 | T-01-01-02 | Schema/model foundation stays narrow with no parser, service, repository, route, controller, or UI work | feature/full | `composer run test` | yes | pending |
| 01-02-01 | 01-02 | 2 | RUN-03, RUN-04, ARCH-01, ARCH-03, ARCH-04, GH-01 | T-01-02-01, T-01-02-02 | Valid GitHub PR URLs create normalized identity and invalid PR URLs do not persist records | feature | `php artisan test --testsuite=Feature --filter=ReviewRunCreationServiceTest` | yes | pending |
| 01-02-02 | 01-02 | 2 | RUN-03, RUN-04, ARCH-01, ARCH-03, ARCH-04, GH-01 | T-01-02-01, T-01-02-02 | Service error codes remain stable and repositories own all database reads/writes | feature | `php artisan test --testsuite=Feature --filter=ReviewRunCreationServiceTest` | yes | pending |
| 01-02-03 | 01-02 | 2 | RUN-03, RUN-04, ARCH-01, ARCH-03, ARCH-04, GH-01 | T-01-02-03 | Service foundation has no HTTP/UI or external GitHub/AI calls | feature/full | `composer run test` | yes | pending |
| 01-03-01 | 01-03 | 3 | RUN-01, RUN-02, RUN-03, RUN-04, ARCH-02 | T-01-03-01, T-01-03-02 | No-login form validates through controller/service boundary without external calls | feature | `php artisan test --testsuite=Feature --filter=ReviewRunSubmissionTest` | yes | pending |
| 01-03-02 | 01-03 | 3 | RUN-01, RUN-02, RUN-03, RUN-04, ARCH-02 | T-01-03-01, T-01-03-02 | GET / redirects to /reviews; valid submissions create pending runs; invalid submissions create no records | feature | `php artisan test --testsuite=Feature --filter=ExampleTest` | yes | pending |
| 01-03-03 | 01-03 | 3 | RUN-01, RUN-02, RUN-03, RUN-04, ARCH-01, ARCH-02, ARCH-03, ARCH-04, GH-01 | T-01-03-03 | Create workflow remains local/private and does not add auth or later-phase capabilities | feature/full | `composer run test` | yes | pending |
| 01-04-01 | 01-04 | 4 | RUN-01, RUN-05, RUN-06, RUN-07 | T-01-04-01 | History/detail show only safe status, safe error text, fallback copy, and required failed next-step copy | feature | `php artisan test --testsuite=Feature --filter=ReviewRunHistoryAndDetailTest` | yes | pending |
| 01-04-02 | 01-04 | 4 | RUN-01, RUN-05, RUN-06, RUN-07, ARCH-02, ARCH-04 | T-01-04-01, T-01-04-02, T-01-04-03 | Dashboard and detail reads are repository-backed; long URLs do not break layout | feature | `php artisan test --testsuite=Feature` | yes | pending |
| 01-04-03 | 01-04 | 4 | RUN-01, RUN-02, RUN-03, RUN-04, RUN-05, RUN-06, RUN-07, ARCH-01, ARCH-02, ARCH-03, ARCH-04, GH-01 | T-01-04-01, T-01-04-02, T-01-04-03 | Full Phase 1 vertical slice is covered with no GitHub fetching, AI, queue controls, findings, drafts, approval, or publishing UI | full | `composer run test` | yes | pending |

*Status: pending, green, red, flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements:

- [x] `phpunit.xml` configures SQLite `:memory:` for tests
- [x] `tests/Feature` exists for web workflow tests
- [x] `tests/Unit` exists for parser/service boundary tests
- [x] `composer run test` is available as the full-suite command

---

## Manual-Only Verifications

All phase behaviors have automated verification.

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-06-27, revised for four-wave replanning 2026-06-27
