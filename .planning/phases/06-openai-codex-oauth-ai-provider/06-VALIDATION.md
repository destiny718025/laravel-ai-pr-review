---
phase: 06
slug: openai-codex-oauth-ai-provider
status: draft
nyquist_compliant: true
wave_0_complete: false
created: 2026-06-29
---

# Phase 06 - Validation Strategy

## Test Infrastructure

| Property | Value |
|----------|-------|
| Framework | PHPUnit `12.5.30` via Laravel test runner |
| Config file | `phpunit.xml` |
| Quick run command | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAIReviewProviderTest|AIReviewFailureMapperTest|QueuedReviewFailureTest'` |
| Full suite command | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` |
| Expected feedback latency | Quick run under 60 seconds; full suite before phase completion |

## Sampling Rate

- **Per task commit:** Run the quick command for provider selection, safe failure mapping, and queued review failure coverage.
- **Per wave merge:** Run `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test`.
- **Phase gate:** Full suite must pass before `$gsd-verify-work 06`.
- **External-call rule:** Tests must fake Codex backend calls and local auth-file reads; no test may call real OpenAI, ChatGPT, GitHub, or Codex endpoints.

## Per-Task Verification Map

| Task ID | Wave | Priority | Requirement / Threat | Behavior Under Test | Test Type | Command | Wave 0 | Status |
|---------|------|----------|----------------------|---------------------|-----------|---------|--------|--------|
| 06-01-01 | 01 | 1 | P06-D-15 | Container resolves `fake`, `openai_api_key`, and `openai_codex_oauth` distinctly with no accidental fallback. | Unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAIReviewProviderTest|OpenAICodexOAuthReviewProviderTest'` | Yes | Pending |
| 06-01-02 | 01 | 1 | P06-D-09 / P06-D-25 / T-06-01 | Missing auth file, malformed JSON, unreadable file, or missing access token fails safely without exposing file contents or token-like values. | Unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='CodexAuthCacheReaderTest|AIReviewFailureMapperTest'` | Yes | Pending |
| 06-02-01 | 02 | 1 | P06-D-19 / P06-D-26 / T-06-02 | Codex backend 401/403, 429, transport failures, malformed success bodies, and unsupported response shapes map to categorized safe errors. | Unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAICodexOAuthReviewProviderTest|AIReviewFailureMapperTest'` | Yes | Pending |
| 06-02-02 | 02 | 1 | AI-04 / AI-08 | Successful Codex responses return structured JSON text compatible with `AIReviewPayloadValidator`; invalid provider text fails the queued review safely. | Unit + Feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAICodexOAuthReviewProviderTest|QueuedReviewExecutionTest|QueuedReviewFailureTest'` | Yes | Pending |
| 06-03-01 | 03 | 1 | EXEC-05 / T-06-03 | Persisted review-run failure data omits bearer tokens, refresh tokens, id tokens, raw auth cache bodies, authorization headers, and raw backend response bodies. | Unit + Feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='AIReviewFailureMapperTest|QueuedReviewFailureTest|OpenAICodexOAuthReviewProviderTest'` | Yes | Pending |

## Wave 0 Requirements

- [ ] Add `tests/Unit/AI/CodexAuthCacheReaderTest.php` for fake auth-file discovery, malformed JSON, missing token, unreadable file, and safe parsing behavior.
- [ ] Add `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php` for HTTP fake coverage of success, 401/403, 429, transport failure, malformed success body, unsupported response shape, and no API-key fallback.
- [ ] Extend `tests/Unit/AI/OpenAIReviewProviderTest.php` with explicit selector coverage for `fake`, `openai_api_key`, and `openai_codex_oauth`.
- [ ] Extend `tests/Feature/QueuedReviewFailureTest.php` with queued safe-failure summaries for Codex auth and Codex backend errors.

## Manual-Only Verifications

| Check | Reason | Required For Phase Pass |
|-------|--------|-------------------------|
| Real local Codex auth smoke test with a user-owned `~/.codex/auth.json` | Requires a live ChatGPT/Codex login and would depend on external account state. Automated tests must use fakes. | No |

## Validation Sign-Off

- [ ] Wave 0 tests exist before implementation tasks are marked complete.
- [ ] Quick command passes after each provider/auth/error-mapping task.
- [ ] Full suite passes before phase verification.
- [ ] No real external Codex, ChatGPT, OpenAI, or GitHub calls are required by the test suite.
- [ ] Failure assertions prove secrets and raw provider payloads are not persisted or shown.
