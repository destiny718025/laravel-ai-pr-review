---
phase: 06-openai-codex-oauth-ai-provider
verified: 2026-06-29T23:42:56Z
status: passed
score: 9/9 must-haves verified
behavior_unverified: 0
overrides_applied: 0
---

# Phase 6: OpenAI Codex OAuth AI Provider Verification Report

**Phase Goal:** Let queued AI review use an explicit Codex OAuth provider path backed by local Codex CLI auth cache without storing tokens or silently falling back to the API-key route.
**Verified:** 2026-06-29T23:42:56Z
**Status:** passed
**Re-verification:** No - initial verification

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
| --- | --- | --- | --- |
| 1 | AI provider selection is explicit and config-driven, `fake` remains the default, `openai_api_key` stays distinct, and unsupported selectors fail closed. | ✓ VERIFIED | `config/services.php:31`, `app/Providers/AppServiceProvider.php:21`, `tests/Unit/AI/OpenAIReviewProviderTest.php:16`, `tests/Unit/AI/OpenAIReviewProviderTest.php:29`, `tests/Unit/AI/OpenAIReviewProviderTest.php:36`, `tests/Unit/AI/OpenAIReviewProviderTest.php:46` |
| 2 | Codex auth-cache reading is isolated behind one runtime reader that resolves override -> `CODEX_HOME/auth.json` -> `~/.codex/auth.json`, exposes only the needed fields, and safe-fails missing/unreadable/malformed/missing-token cases. | ✓ VERIFIED | `app/Data/AI/CodexAuthCredentials.php:5`, `app/Exceptions/AI/CodexAuthException.php:7`, `app/Services/AI/CodexAuthCacheReader.php:11`, `tests/Unit/AI/CodexAuthCacheReaderTest.php:30`, `tests/Unit/AI/CodexAuthCacheReaderTest.php:77`, `tests/Unit/AI/CodexAuthCacheReaderTest.php:94`, `tests/Unit/AI/CodexAuthCacheReaderTest.php:113`, `tests/Unit/AI/CodexAuthCacheReaderTest.php:134` |
| 3 | Phase 06 reuses Codex CLI auth only and does not add browser callback OAuth, device-code flow, Laravel token persistence, or schema work. | ✓ VERIFIED | No new controller/job/UI auth flow files in phase artifacts, `config/services.php:42` holds env-only config, `database/migrations` contains no Codex/OpenAI/token-persistence additions, and `app/Repositories/ReviewRunRepository.php:123` persists only safe error text. |
| 4 | `openai_codex_oauth` resolves to a dedicated HTTP provider class that reuses `AIReviewRequest`, posts review context to Codex `/responses`, and extracts raw review JSON text from supported Responses output shapes. | ✓ VERIFIED | `app/Providers/AppServiceProvider.php:26`, `app/Services/AI/HttpOpenAICodexOAuthReviewProvider.php:18`, `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php:16`, `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php:62`, `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php:95` |
| 5 | Missing auth, malformed auth, auth rejection, rate limiting, transport failures, malformed responses, unsupported shapes, invalid JSON, and invalid schema all map to categorized safe failures with no API-key fallback and no secret leakage. | ✓ VERIFIED | `app/Services/AI/AIReviewFailureMapper.php:14`, `tests/Unit/AI/AIReviewFailureMapperTest.php:17`, `tests/Unit/AI/AIReviewFailureMapperTest.php:41`, `tests/Unit/AI/AIReviewFailureMapperTest.php:57`, `tests/Unit/AI/AIReviewFailureMapperTest.php:84`, `tests/Unit/AI/AIReviewFailureMapperTest.php:95`, `tests/Unit/AI/AIReviewFailureMapperTest.php:105`, `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php:114`, `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php:150`, `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php:176`, `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php:202` |
| 6 | Tests fake auth reads and Codex/OpenAI HTTP; no real external calls are required for the provider path. | ✓ VERIFIED | `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php:18`, `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php:21`, `tests/Feature/QueuedReviewExecutionTest.php:86`, `tests/Feature/QueuedReviewExecutionTest.php:88`, `tests/Feature/QueuedReviewFailureTest.php:115`, `tests/Feature/QueuedReviewFailureTest.php:352`, plus current Docker runs passed offline. |
| 7 | Queued execution remains provider-agnostic: `ReviewExecutionService` still builds one `AIReviewRequest`, calls `AIReviewProvider::review()`, decodes the returned JSON, validates findings, and routes failures through the shared mapper without Codex-specific branches. | ✓ VERIFIED | `app/Services/ReviewExecutionService.php:30` through `app/Services/ReviewExecutionService.php:85`; there are no `codex` or `openai_codex_oauth` branches in this service. |
| 8 | The real `openai_codex_oauth` selector works through the queued execution path and persists validated findings through the existing validator/persistence pipeline. | ✓ VERIFIED | `tests/Feature/QueuedReviewExecutionTest.php:73`, `tests/Feature/QueuedReviewExecutionTest.php:118`, current targeted Docker run `test_openai_codex_oauth_selector_runs_through_queued_execution_and_persists_validated_findings` passed, and current full Docker suite passed. |
| 9 | Failed queued Codex runs persist only safe summarized text and do not store bearer/access/refresh/id token fragments, raw auth-cache JSON, raw backend JSON, `Authorization`, or `ChatGPT-Account-ID`. | ✓ VERIFIED | `app/Services/ReviewExecutionService.php:55`, `app/Repositories/ReviewRunRepository.php:123`, `tests/Feature/QueuedReviewFailureTest.php:86`, `tests/Feature/QueuedReviewFailureTest.php:110`, `tests/Feature/QueuedReviewFailureTest.php:131`, `tests/Feature/QueuedReviewFailureTest.php:153`, `tests/Feature/QueuedReviewFailureTest.php:175`, `tests/Feature/QueuedReviewFailureTest.php:194`, `tests/Feature/QueuedReviewFailureTest.php:217`, `tests/Feature/QueuedReviewFailureTest.php:313` |

**Score:** 9/9 truths verified (0 present, behavior-unverified)

### Required Artifacts

| Artifact | Expected | Status | Details |
| --- | --- | --- | --- |
| `app/Data/AI/CodexAuthCredentials.php` | Minimal in-memory credential DTO only | ✓ VERIFIED | Readonly DTO with `accessToken`, `accountId`, `authMode`, `lastRefresh`; no persistence logic. |
| `app/Exceptions/AI/CodexAuthException.php` | Safe typed auth-cache failure wrapper | ✓ VERIFIED | Stores only a reason code plus message. |
| `app/Providers/AppServiceProvider.php` | Single container binding seam for provider selection | ✓ VERIFIED | `AIReviewProvider` resolves `fake`, `openai_api_key`, `openai_codex_oauth`, else throws. |
| `config/services.php` | Config-only selector and Codex settings | ✓ VERIFIED | Adds `services.ai.provider` and `services.codex.*`; no direct `env()` usage in services. |
| `app/Services/AI/CodexAuthCacheReader.php` | Only filesystem seam for Codex auth cache | ✓ VERIFIED | Resolves path precedence, parses minimal fields, throws safe failures. |
| `app/Services/AI/HttpOpenAICodexOAuthReviewProvider.php` | Dedicated Codex `/responses` adapter | ✓ VERIFIED | Reads auth cache, posts one request, extracts only supported text output. |
| `app/Services/AI/AIReviewFailureMapper.php` | Shared sanitized failure classification | ✓ VERIFIED | Maps auth/request/JSON/schema/runtime failures to stable safe summaries. |
| `app/Services/ReviewExecutionService.php` | Provider-agnostic queued execution workflow | ✓ VERIFIED | Builds one request, validates results, persists success/failure through repositories. |
| `tests/Unit/AI/CodexAuthCacheReaderTest.php` | Offline auth-cache precedence and failure coverage | ✓ VERIFIED | Temp-file and config-override coverage only. |
| `tests/Unit/AI/OpenAIReviewProviderTest.php` | Selector resolution coverage | ✓ VERIFIED | Covers `fake`, `openai_api_key`, `openai_codex_oauth`, and unsupported selector. |
| `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php` | Offline Codex transport contract coverage | ✓ VERIFIED | Fakes auth reader and HTTP; proves no fallback to OpenAI API-key transport. |
| `tests/Unit/AI/AIReviewFailureMapperTest.php` | Safe failure classification coverage | ✓ VERIFIED | Covers Codex auth, HTTP status, malformed response, and runtime cases. |
| `tests/Feature/QueuedReviewExecutionTest.php` | End-to-end queued Codex success coverage | ✓ VERIFIED | Exercises real selector + provider + validator + persistence path. |
| `tests/Feature/QueuedReviewFailureTest.php` | End-to-end queued Codex safe-failure coverage | ✓ VERIFIED | Exercises queued failure matrix and rejects secret fragments in persisted messages. |

### Key Link Verification

| From | To | Via | Status | Details |
| --- | --- | --- | --- | --- |
| `config/services.php` | `AppServiceProvider` | `config('services.ai.provider')` match | ✓ VERIFIED | Selector config drives container binding directly. |
| `AppServiceProvider` | `HttpOpenAICodexOAuthReviewProvider` | `openai_codex_oauth => app(...)` | ✓ VERIFIED | Dedicated provider is resolved through the interface, not by ad hoc call sites. |
| `CodexAuthCacheReader` | `config/services.php` | `config('services.codex.*')` | ✓ VERIFIED | Reader consumes config only; no direct `env()` lookups. |
| `HttpOpenAICodexOAuthReviewProvider` | `CodexAuthCacheReader` | constructor injection + `read()` | ✓ VERIFIED | Auth credentials are acquired only through the reader seam. |
| `HttpOpenAICodexOAuthReviewProvider` | Codex backend | `POST /responses` + `extractReviewJson()` | ✓ VERIFIED | Provider sends one Codex request and extracts review JSON from supported response parts or `output_text`. |
| `ReviewExecutionService` | `AIReviewProvider` / validator / failure mapper | `review()` -> `json_decode()` -> `validate()` -> `markExecutionFailed()` | ✓ VERIFIED | Service remains the single decode/validate/fail orchestration point. |
| `ReviewExecutionService` | `ReviewRunRepository` | `markExecutionFailed($failure->message)` | ✓ VERIFIED | Only mapped safe summary text is persisted on failure. |
| Feature tests | Real selector/provider path | config override + fake reader + `Http::fake()` | ✓ VERIFIED | Tests exercise the real binding instead of bypassing the container for Codex scenarios. |

### Data-Flow Trace (Level 4)

| Artifact | Data Variable | Source | Produces Real Data | Status |
| --- | --- | --- | --- | --- |
| `app/Services/AI/CodexAuthCacheReader.php` | `accessToken`, `accountId`, `authMode`, `lastRefresh` | Configured auth path -> JSON file -> `tokens` / top-level fields | Yes | ✓ FLOWING |
| `app/Services/AI/HttpOpenAICodexOAuthReviewProvider.php` | `$response` / extracted review JSON text | Codex `/responses` HTTP POST using live request payload | Yes | ✓ FLOWING |
| `app/Services/ReviewExecutionService.php` | `$validatedFindings` | `AIReviewProvider::review()` -> `json_decode()` -> validator -> repositories | Yes | ✓ FLOWING |

### Behavioral Spot-Checks

| Behavior | Command | Result | Status |
| --- | --- | --- | --- |
| Codex provider posts review context and extracts supported response text | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='test_provider_posts_review_context_to_codex_responses_endpoint_and_returns_output_text_parts\|test_openai_codex_oauth_selector_runs_through_queued_execution_and_persists_validated_findings\|test_codex_unauthorized_failure_persists_safe_summary_without_account_header_fragments'` | 3 passed, 30 assertions | ✓ PASS |
| Auth-cache precedence and malformed-cache safe failure | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='test_reader_prefers_explicit_override_path_before_codex_home_and_home_directory\|test_reader_fails_safely_when_auth_cache_json_is_malformed\|test_malformed_codex_auth_maps_to_safe_summary'` | 3 passed, 11 assertions | ✓ PASS |
| Full offline regression gate | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` | 119 passed, 853 assertions | ✓ PASS |

### Probe Execution

| Probe | Command | Result | Status |
| --- | --- | --- | --- |
| n/a | n/a | Step 7c skipped: no phase-declared or conventional probe scripts for Phase 06 | ? SKIP |

### Requirements Coverage

All requirement IDs declared across `06-01-PLAN.md`, `06-02-PLAN.md`, and `06-03-PLAN.md` are present in `REQUIREMENTS.md`. The traceability table still attributes these IDs to their original completion phases, but every Phase 06 frontmatter requirement is accounted for and backed by current-code evidence below.

| Requirement | Source Plan | Description | Status | Evidence |
| --- | --- | --- | --- | --- |
| `ARCH-01` | `06-01` | Review run workflows use Controller / Service / Repository layering | ✓ SATISFIED | `AppServiceProvider` remains the container seam for `AIReviewProvider` (`app/Providers/AppServiceProvider.php:21`); Phase 06 added provider/auth services without moving workflow logic into controllers or repositories. |
| `ARCH-03` | `06-03` | Services own business workflows for creating, executing, and publishing review runs | ✓ SATISFIED | `ReviewExecutionService` still owns execution workflow (`app/Services/ReviewExecutionService.php:30`), and Plan 06-03 added only feature coverage rather than controller/job branching. |
| `ARCH-05` | `06-01` | External GitHub and AI provider calls are hidden behind interfaces that can be faked in tests | ✓ SATISFIED | `AIReviewProvider` is still the seam selected in `AppServiceProvider`; unit and feature tests fake `CodexAuthCacheReader` and `Http` instead of making live calls. |
| `AI-03` | `06-01`, `06-02` | System can use one concrete AI provider implementation behind the provider interface | ✓ SATISFIED | `openai_codex_oauth` resolves to `HttpOpenAICodexOAuthReviewProvider` (`app/Providers/AppServiceProvider.php:26`) and is exercised in unit and feature tests. |
| `AI-04` | `06-02`, `06-03` | AI review output is validated against a structured finding schema before persistence | ✓ SATISFIED | `ReviewExecutionService` decodes provider output and calls the validator before persistence (`app/Services/ReviewExecutionService.php:35` through `:53`); queued Codex success test persists validated findings only (`tests/Feature/QueuedReviewExecutionTest.php:73`). |
| `AI-08` | `06-02`, `06-03` | Invalid or incomplete AI output fails the review run safely without creating malformed findings | ✓ SATISFIED | `AIReviewFailureMapper` classifies invalid JSON, invalid schema, malformed response, and unsupported shape (`app/Services/AI/AIReviewFailureMapper.php:24` through `:118`); failure tests assert safe summaries and zero malformed findings. |
| `EXEC-04` | `06-02`, `06-03` | Review execution job marks the review run failed with a safe summarized error when GitHub, AI, or parsing work fails | ✓ SATISFIED | `ReviewExecutionService` catches all throwables and persists the mapped safe message (`app/Services/ReviewExecutionService.php:55` through `:58`); `QueuedReviewFailureTest` exercises Codex failure cases end to end. |
| `EXEC-05` | `06-01`, `06-02`, `06-03` | Review execution avoids logging raw API credentials, authorization headers, or unredacted provider payloads | ✓ SATISFIED | No Codex/OpenAI token migration or persistence artifacts exist; `QueuedReviewFailureTest::assertFailedSafely()` rejects `Authorization`, `Bearer`, access/refresh/id tokens, raw payload/body fragments, `ChatGPT-Account-ID`, and account IDs (`tests/Feature/QueuedReviewFailureTest.php:313`). |

### Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
| --- | --- | --- | --- | --- |
| n/a | n/a | None | - | No `TBD`/`FIXME`/`XXX` debt markers, placeholders, stub returns, or orphaned phase artifacts were found in the Phase 06 files. |

### Gaps Summary

No blocking gaps found. Phase 06’s explicit Codex OAuth provider path is present, wired into queued execution, backed by the local auth-cache reader, protected against silent API-key fallback, and covered by current offline Docker tests.

---

_Verified: 2026-06-29T23:42:56Z_
_Verifier: the agent (gsd-verifier)_
