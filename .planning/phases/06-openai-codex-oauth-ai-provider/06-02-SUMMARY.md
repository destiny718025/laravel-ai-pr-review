---
phase: 06-openai-codex-oauth-ai-provider
plan: 02
subsystem: api
tags: [laravel, codex, oauth, openai, ai-provider]
requires:
  - phase: 05-github-comment-publishing
    provides: AIReviewProvider-based queued review flow and safe external-integration patterns
provides:
  - Dedicated `openai_codex_oauth` HTTP provider bound behind `AIReviewProvider`
  - Responses-style review JSON extraction from Codex `/responses` output parts with safe fallback rules
  - Categorized safe failure mapping for Codex auth-cache, HTTP status, malformed response, and unsupported shape cases
affects: [06-03-queued-execution-hardening, ai-provider, queued-review]
tech-stack:
  added: []
  patterns: [config-driven provider selection, read-only Codex auth reuse, provider-local response adaptation with central validation]
key-files:
  created:
    - app/Services/AI/HttpOpenAICodexOAuthReviewProvider.php
    - tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php
  modified:
    - app/Providers/AppServiceProvider.php
    - app/Services/AI/AIReviewFailureMapper.php
    - tests/Unit/AI/AIReviewFailureMapperTest.php
    - tests/Unit/AI/OpenAIReviewProviderTest.php
key-decisions:
  - "Bind `openai_codex_oauth` directly in `AppServiceProvider` so queued execution remains provider-agnostic."
  - "Accept only `output[].content[]` parts with `type` `output_text` or `text`, then fall back to top-level `output_text`."
  - "Keep request/status/shape failure messaging provider-agnostic in `AIReviewFailureMapper`, while Codex auth-cache failures stay explicitly actionable."
patterns-established:
  - "Codex-specific transport adapts upstream response shapes inside the provider and returns raw review JSON text only."
  - "Shared AI failure mapping can add upstream-specific branches without introducing provider branches into `ReviewExecutionService`."
requirements-completed: [AI-03, AI-04, AI-08, EXEC-04, EXEC-05]
coverage:
  - id: D1
    description: "The `openai_codex_oauth` selector now resolves to a dedicated provider that posts the existing review context to the Codex `/responses` backend."
    requirement: AI-03
    verification:
      - kind: unit
        ref: "tests/Unit/AI/OpenAIReviewProviderTest.php#test_openai_codex_oauth_provider_resolves_when_selector_requests_it"
        status: pass
      - kind: unit
        ref: "tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php#test_provider_posts_review_context_to_codex_responses_endpoint_and_returns_output_text_parts"
        status: pass
      - kind: other
        ref: "docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAIReviewProviderTest|OpenAICodexOAuthReviewProviderTest|AIReviewFailureMapperTest'"
        status: pass
    human_judgment: false
  - id: D2
    description: "Codex success parsing now extracts raw review JSON only from supported Responses-style text parts or the `output_text` fallback, and unsupported shapes fail safely without API-key fallback."
    requirement: AI-04
    verification:
      - kind: unit
        ref: "tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php#test_provider_accepts_text_parts_and_omits_account_header_when_not_available"
        status: pass
      - kind: unit
        ref: "tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php#test_provider_falls_back_to_top_level_output_text_when_message_parts_are_missing"
        status: pass
      - kind: unit
        ref: "tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php#test_provider_rejects_unsupported_success_shape_without_falling_back_to_api_key_transport"
        status: pass
    human_judgment: false
  - id: D3
    description: "Codex auth-cache and backend failure cases now collapse into categorized, sanitized summaries with no token or raw-body leakage."
    requirement: EXEC-04
    verification:
      - kind: unit
        ref: "tests/Unit/AI/AIReviewFailureMapperTest.php#test_missing_codex_auth_maps_to_safe_summary"
        status: pass
      - kind: unit
        ref: "tests/Unit/AI/AIReviewFailureMapperTest.php#test_unauthorized_request_failure_maps_to_safe_summary"
        status: pass
      - kind: unit
        ref: "tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php#test_provider_throws_transport_errors_without_retrying_openai_api_key_transport"
        status: pass
    human_judgment: false
duration: 2min
completed: 2026-06-30
status: complete
---

# Phase 06 Plan 02: Codex OAuth Provider Summary

**Codex OAuth `/responses` transport with safe review-text extraction and categorized failure mapping behind the existing AI provider contract**

## Performance

- **Duration:** 2 min
- **Started:** 2026-06-29T23:25:06Z
- **Completed:** 2026-06-29T23:27:14Z
- **Tasks:** 2
- **Files modified:** 6

## Accomplishments

- Replaced the temporary fail-closed `openai_codex_oauth` selector with a real `HttpOpenAICodexOAuthReviewProvider` binding behind `AIReviewProvider`.
- Added Codex `/responses` request coverage for bearer auth, optional `ChatGPT-Account-ID`, review-context preservation, supported text extraction, and no API-key fallback.
- Expanded `AIReviewFailureMapper` to classify Codex auth-cache failures, HTTP auth/rate-limit failures, malformed responses, unsupported response shapes, and generic transport/runtime failures safely.

## Task Commits

Each task was committed atomically:

1. **Task 06-02-01: Add Wave 0 RED tests for Codex transport semantics and categorized safe failures** - `7094fb7` (`test`)
2. **Task 06-02-02: Implement the Codex OAuth provider and extend failure mapping without fallback** - `3f1a89c` (`feat`)

## Files Created/Modified

- `app/Services/AI/HttpOpenAICodexOAuthReviewProvider.php` - Codex backend adapter that reads local auth credentials, posts `/responses`, and extracts raw review JSON text safely.
- `app/Providers/AppServiceProvider.php` - Explicitly resolves `openai_codex_oauth` to the new provider without touching queued execution workflow code.
- `app/Services/AI/AIReviewFailureMapper.php` - Adds auth-cache, request-status, malformed-response, and unsupported-shape branches while preserving sanitized output only.
- `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php` - Offline request/response contract coverage for the Codex provider and no-fallback behavior.
- `tests/Unit/AI/OpenAIReviewProviderTest.php` - Locks the container-resolution contract for the Codex provider selector.
- `tests/Unit/AI/AIReviewFailureMapperTest.php` - Covers categorized safe summaries for Codex auth and backend failure paths.

## Decisions Made

- Kept `ReviewExecutionService` unchanged and provider-agnostic by confining all Codex transport details to a dedicated provider class.
- Reused the existing review request payload shape and only adapted the outer HTTP transport to Codex `/responses`.
- Used provider-agnostic HTTP/response failure messages in the shared mapper so the API-key provider does not inherit misleading Codex-only wording.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required in this plan.

## Next Phase Readiness

Plan 06-03 can now wire the Codex provider through the queued execution path end-to-end and verify persisted failure summaries against the full review workflow.

No blockers remain for the next plan.

## Self-Check: PASSED

- Verified `.planning/phases/06-openai-codex-oauth-ai-provider/06-02-SUMMARY.md` exists on disk.
- Verified commits `7094fb7` and `3f1a89c` exist in git history.
