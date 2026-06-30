---
phase: 06-openai-codex-oauth-ai-provider
plan: 01
subsystem: auth
tags: [laravel, openai, codex, oauth, ai-provider]
requires:
  - phase: 05-github-comment-publishing
    provides: AIReviewProvider-based queued review flow and safe external-integration patterns
provides:
  - Explicit `AI_PROVIDER` selector wiring with fake/default and API-key branches
  - Read-only Codex auth cache reader with minimal credential DTO and safe typed failures
  - Offline unit coverage for selector behavior and auth-cache precedence/failure cases
affects: [06-02-openai-codex-provider, 06-03-queued-execution-hardening]
tech-stack:
  added: []
  patterns: [config-driven AI provider selection, read-only local auth-cache seam]
key-files:
  created:
    - app/Data/AI/CodexAuthCredentials.php
    - app/Exceptions/AI/CodexAuthException.php
    - app/Services/AI/CodexAuthCacheReader.php
    - tests/Unit/AI/CodexAuthCacheReaderTest.php
  modified:
    - app/Providers/AppServiceProvider.php
    - config/services.php
    - tests/Unit/AI/OpenAIReviewProviderTest.php
    - tests/Unit/AI/FakeAIReviewProviderTest.php
key-decisions:
  - "Use `services.ai.provider` / `AI_PROVIDER` as the only authoritative selector and keep `fake` as the deterministic default."
  - "Keep Codex CLI auth reuse behind a small runtime reader that exposes only access token, account id, auth mode, and last refresh."
  - "Reserve `openai_codex_oauth` in 06-01 as an explicit fail-closed selector until the real transport lands in 06-02."
patterns-established:
  - "Container binding remains the single provider-selection seam in `AppServiceProvider`."
  - "Filesystem credential access stays in a fakeable AI service instead of controllers, repositories, or persistence."
requirements-completed: [ARCH-01, ARCH-05, AI-03, EXEC-05]
coverage:
  - id: D1
    description: "Explicit AI provider selection now uses `AI_PROVIDER` with fake/default, OpenAI API-key, and reserved Codex OAuth branches."
    requirement: AI-03
    verification:
      - kind: unit
        ref: "tests/Unit/AI/OpenAIReviewProviderTest.php#test_openai_api_key_provider_resolves_when_selector_requests_it"
        status: pass
      - kind: unit
        ref: "tests/Unit/AI/OpenAIReviewProviderTest.php#test_openai_codex_oauth_selector_fails_closed_until_the_transport_is_installed"
        status: pass
      - kind: unit
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAIReviewProviderTest|CodexAuthCacheReaderTest|FakeAIReviewProviderTest'"
        status: pass
    human_judgment: false
  - id: D2
    description: "Codex auth cache reading is isolated behind a minimal DTO plus safe typed failures with override -> CODEX_HOME -> ~/.codex path precedence."
    requirement: EXEC-05
    verification:
      - kind: unit
        ref: "tests/Unit/AI/CodexAuthCacheReaderTest.php#test_reader_prefers_explicit_override_path_before_codex_home_and_home_directory"
        status: pass
      - kind: unit
        ref: "tests/Unit/AI/CodexAuthCacheReaderTest.php#test_reader_fails_safely_when_auth_cache_json_is_malformed"
        status: pass
      - kind: unit
        ref: "tests/Unit/AI/CodexAuthCacheReaderTest.php#test_reader_fails_safely_when_access_token_is_missing"
        status: pass
    human_judgment: false
  - id: D3
    description: "Selector and auth-cache behavior is covered fully offline with temp files, config overrides, and no live Codex/OpenAI/GitHub calls."
    requirement: ARCH-05
    verification:
      - kind: unit
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAIReviewProviderTest|CodexAuthCacheReaderTest|FakeAIReviewProviderTest'"
        status: pass
      - kind: other
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 ./vendor/bin/pint app/Data/AI/CodexAuthCredentials.php app/Exceptions/AI/CodexAuthException.php app/Providers/AppServiceProvider.php app/Services/AI/CodexAuthCacheReader.php config/services.php tests/Unit/AI/CodexAuthCacheReaderTest.php tests/Unit/AI/OpenAIReviewProviderTest.php tests/Unit/AI/FakeAIReviewProviderTest.php"
        status: pass
    human_judgment: false
duration: 4min
completed: 2026-06-29
status: complete
---

# Phase 06 Plan 01: Explicit Provider Selection Summary

**Explicit `AI_PROVIDER` selection, a read-only Codex auth-cache reader, and safe fail-closed foundations for the future Codex OAuth transport**

## Performance

- **Duration:** 4 min
- **Started:** 2026-06-29T13:27:49Z
- **Completed:** 2026-06-29T13:31:52Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments

- Replaced the old boolean OpenAI toggle with explicit `services.ai.provider` / `AI_PROVIDER` selection while preserving the fake provider as the default path.
- Added `CodexAuthCredentials`, `CodexAuthException`, and `CodexAuthCacheReader` so Codex CLI auth reuse is runtime-only, fakeable, and safe on missing/unreadable/malformed cache states.
- Locked selector and auth-cache behavior into offline unit coverage that uses config overrides and temp files only.

## Task Commits

Each task was committed atomically:

1. **Task 06-01-01: Add Wave 0 RED tests for explicit selector behavior and safe Codex auth-cache reading** - `1ce9efe` (`test`)
2. **Task 06-01-02: Implement explicit provider config, safe auth reader, and fail-closed binding** - `ba3f984` (`feat`)
3. **Follow-up fix: keep the reserved Codex selector fail-closed before 06-02 transport lands** - `187dc7a` (`fix`)

## Files Created/Modified

- `app/Data/AI/CodexAuthCredentials.php` - Minimal in-memory credential DTO for the Codex auth cache.
- `app/Exceptions/AI/CodexAuthException.php` - Typed safe failure with reason metadata only.
- `app/Services/AI/CodexAuthCacheReader.php` - Runtime auth-file discovery and parsing seam.
- `app/Providers/AppServiceProvider.php` - Explicit provider selector binding and reserved Codex fail-closed branch.
- `config/services.php` - `services.ai.provider` and `services.codex.*` configuration.
- `tests/Unit/AI/CodexAuthCacheReaderTest.php` - Offline precedence and safe-failure matrix for auth-cache reading.
- `tests/Unit/AI/OpenAIReviewProviderTest.php` - Selector-contract tests for API-key, fake, unsupported, and reserved Codex branches.
- `tests/Unit/AI/FakeAIReviewProviderTest.php` - Explicit fake-selector pinning so unit tests are not polluted by environment provider choice.

## Decisions Made

- `AI_PROVIDER` is now the only authoritative selector for provider resolution; the old boolean OpenAI flag is no longer consulted for container binding.
- Codex auth reuse stays outside Laravel login flows and is reduced to the smallest runtime DTO the future transport will need.
- Until `HttpOpenAICodexOAuthReviewProvider` exists in Plan 06-02, selecting `openai_codex_oauth` fails closed with a stable `InvalidArgumentException` rather than a container resolution crash.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Pinned the fake-provider unit test to an explicit selector**
- **Found during:** Task 06-01-02 verification
- **Issue:** `FakeAIReviewProviderTest` depended on ambient config and could accidentally resolve the reserved Codex selector when `AI_PROVIDER` was set in the environment.
- **Fix:** Forced the affected fake-provider assertions to set `services.ai.provider=fake` explicitly.
- **Files modified:** `tests/Unit/AI/FakeAIReviewProviderTest.php`
- **Verification:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='FakeAIReviewProviderTest'`
- **Committed in:** `ba3f984`

**2. [Rule 2 - Missing Critical] Reserved the Codex selector as an explicit fail-closed branch**
- **Found during:** Close-out verification after Task 06-01-02
- **Issue:** `openai_codex_oauth` would otherwise try to resolve a provider class that is intentionally deferred to Plan 06-02.
- **Fix:** Converted the reserved selector into a stable `InvalidArgumentException` path and added a unit test for the temporary behavior.
- **Files modified:** `app/Providers/AppServiceProvider.php`, `tests/Unit/AI/OpenAIReviewProviderTest.php`
- **Verification:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAIReviewProviderTest|CodexAuthCacheReaderTest|FakeAIReviewProviderTest'`
- **Committed in:** `187dc7a`

---

**Total deviations:** 2 auto-fixed (1 Rule 2, 1 Rule 3)
**Impact on plan:** Both changes were required to keep the new selector foundation deterministic and safely non-runnable until the Codex transport exists. No scope creep beyond the provider/auth seam.

## Issues Encountered

None beyond the auto-fixed deviations above.

## User Setup Required

None - no external service configuration is required in this plan.

## Next Phase Readiness

Plan 06-02 can now introduce `HttpOpenAICodexOAuthReviewProvider` on top of the existing auth reader and replace the temporary fail-closed Codex selector assertion with a concrete provider-resolution assertion.

No blockers remain for the next plan.

## Self-Check: PASSED

- Verified created files exist on disk.
- Verified code commits `1ce9efe`, `ba3f984`, and `187dc7a` exist in git history.
