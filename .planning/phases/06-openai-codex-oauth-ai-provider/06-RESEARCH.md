# Phase 06: OpenAI Codex OAuth AI Provider - Research

**Researched:** 2026-06-29
**Domain:** Laravel AI provider integration with local Codex CLI OAuth cache
**Confidence:** MEDIUM

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
## Implementation Decisions

### OAuth Source and Login Flow
- **D-01:** Phase 06 should use the local Codex CLI auth cache as the first token source.
- **D-02:** The app should read `~/.codex/auth.json` or a configurable Codex auth path and extract the current Codex access token from that file.
- **D-03:** The user must sign in through Codex CLI outside Laravel before using this provider.
- **D-04:** Do not build browser localhost callback OAuth in Phase 06.
- **D-05:** Do not build OpenAI device-code OAuth UI in Phase 06.

### Token Storage and Safety
- **D-06:** Laravel should not persist imported Codex tokens in the database.
- **D-07:** Laravel should not copy imported Codex tokens into `storage/`, config files, logs, review records, findings, drafts, or failure messages.
- **D-08:** The provider should read the Codex CLI cache at runtime when it needs credentials.
- **D-09:** If the Codex auth cache is missing, unreadable, malformed, or lacks a usable access token, the review run should fail safely with a clear non-secret message.
- **D-10:** Refresh-token management is out of scope for Phase 06 unless it can be handled by re-reading a refreshed Codex CLI cache. Laravel should not own refresh-token rotation in this phase.

### Provider Selection
- **D-11:** Introduce an explicit provider selector such as `AI_PROVIDER=openai_codex_oauth`.
- **D-12:** Keep the fake provider available as the default deterministic local/test path.
- **D-13:** Keep the current OpenAI Platform API-key provider available as a separate provider option.
- **D-14:** Do not overload `OPENAI_ENABLED=true` to mean both API-key OpenAI and Codex OAuth. Provider selection should make the active route obvious.
- **D-15:** Tests should prove the service container resolves fake, OpenAI API-key, and Codex OAuth provider modes distinctly.

### Codex Backend and Failure Behavior
- **D-16:** The Codex OAuth provider may use the Codex backend route studied from OpenClaw and Hermes, such as `https://chatgpt.com/backend-api/codex`, if planning confirms the request shape.
- **D-17:** Codex backend failures should fail safely and should not automatically fall back to the OpenAI API-key provider.
- **D-18:** Do not silently switch to `OPENAI_API_KEY`, because that could create unexpected cost and different model behavior.
- **D-19:** Failure messages should categorize common cases, including missing Codex auth, invalid or expired token, unauthorized Codex backend response, rate limit, transport failure, malformed response, and unsupported response shape.
- **D-20:** Raw Codex backend responses, authorization headers, bearer tokens, refresh tokens, id tokens, and full local auth cache contents must not be persisted or shown.

### Request and Response Compatibility
- **D-21:** Phase 06 should reuse the existing `AIReviewProvider::review(AIReviewRequest $request): string` contract so `ReviewExecutionService` and output validation do not need provider-specific branches.
- **D-22:** The Codex provider should return the same structured JSON text expected by the existing AI output validator.
- **D-23:** The provider may adapt request payload shape internally, but it must preserve current review input: default/custom instructions, repository identity, PR number, source URL, head SHA, title, and changed files.
- **D-24:** If Codex response parsing cannot find review JSON text, the review should fail safely as invalid provider output.

### Testing Scope
- **D-25:** Tests must fake Codex backend calls and local auth file reads; no test should call real OpenAI, ChatGPT, or Codex endpoints.
- **D-26:** Tests should cover missing auth file, malformed auth JSON, missing access token, backend 401/403, backend 429, transport failure, successful structured JSON response, and no automatic API-key fallback.
- **D-27:** External credentials must not be required to run the test suite.

### the agent's Discretion
- Planner may choose exact class, config, DTO, and error-mapping names, provided provider selection remains explicit and the existing provider contract stays stable.
- Planner may decide whether auth-cache reading belongs in a dedicated service or repository-like credential reader. If it touches filesystem state, keep business decisions in services and keep file access small, fakeable, and testable.
- Planner may decide the exact Codex backend request shape after researching OpenClaw, Hermes, and official Codex docs.
- Planner may decide whether a small management-page status indicator is needed in Phase 06. A full provider management UI is not required unless planning finds it necessary for safe operation.

### Deferred Ideas (OUT OF SCOPE)
## Deferred Ideas

- Full browser localhost callback OAuth belongs to a future phase.
- Device-code OAuth UI belongs to a future phase.
- Database-backed encrypted token storage belongs to a future phase if this becomes multi-user or team-oriented.
- A full provider management UI belongs to a future phase.
- Automatic fallback between Codex OAuth and OpenAI API-key providers is intentionally deferred and should not happen silently.
- Webhook automation, team permissions, named rule sets, and SaaS operations remain outside Phase 06.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ARCH-01 | Review run workflows use Controller / Service / Repository layering. [VERIFIED: .planning/REQUIREMENTS.md] | Keep provider selection in `AppServiceProvider`, filesystem/HTTP work in AI services, and leave `ReviewExecutionService` provider-agnostic. [VERIFIED: app/Providers/AppServiceProvider.php] [VERIFIED: app/Services/ReviewExecutionService.php] |
| ARCH-03 | Services own business workflows for creating, executing, and publishing review runs. [VERIFIED: .planning/REQUIREMENTS.md] | Add a small auth-cache reader plus a Codex HTTP provider service instead of branching in controllers or jobs. [VERIFIED: app/Services/ReviewExecutionService.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| ARCH-05 | External GitHub and AI provider calls are hidden behind interfaces that can be faked in tests. [VERIFIED: .planning/REQUIREMENTS.md] | Preserve `AIReviewProvider`, keep HTTP fakeable, and inject file-reading behind a seam or fakeable path config. [VERIFIED: app/Contracts/AI/AIReviewProvider.php] [VERIFIED: tests/Unit/AI/OpenAIReviewProviderTest.php] |
| AI-01 | System defines an AI review provider interface for generating structured review output. [VERIFIED: .planning/REQUIREMENTS.md] | Phase 06 extends the existing interface with a third implementation, not a new execution path. [VERIFIED: app/Contracts/AI/AIReviewProvider.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| AI-03 | System can use one concrete AI provider implementation behind the provider interface. [VERIFIED: .planning/REQUIREMENTS.md] | Replace the boolean selector with explicit provider resolution for `fake`, `openai_api_key`, and `openai_codex_oauth`. [VERIFIED: app/Providers/AppServiceProvider.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| AI-04 | AI review output is validated against a structured finding schema before persistence. [VERIFIED: .planning/REQUIREMENTS.md] | The Codex provider should still return review JSON text only; `AIReviewPayloadValidator` remains the schema gate. [VERIFIED: app/Services/AI/AIReviewPayloadValidator.php] [VERIFIED: app/Services/ReviewExecutionService.php] |
| AI-08 | Invalid or incomplete AI output fails the review run safely without creating malformed findings. [VERIFIED: .planning/REQUIREMENTS.md] | Unsupported Codex response shapes must map to safe invalid-output failures instead of provider-specific branches. [VERIFIED: app/Services/AI/AIReviewFailureMapper.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| EXEC-04 | Review execution job marks the review run failed with a safe summarized error when GitHub, AI, or parsing work fails. [VERIFIED: .planning/REQUIREMENTS.md] | Extend failure mapping with Codex-auth, unauthorized, rate-limit, transport, and malformed-response categories while preserving safe summaries only. [VERIFIED: app/Services/AI/AIReviewFailureMapper.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| EXEC-05 | Review execution avoids logging raw API credentials, authorization headers, or unredacted provider payloads. [VERIFIED: .planning/REQUIREMENTS.md] | Never persist or log `auth.json` contents, bearer tokens, `id_token`, `refresh_token`, or full backend responses. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [VERIFIED: AGENTS.md] |
| P06-D-15 | Service container must resolve fake, API-key, and Codex OAuth modes distinctly. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | Extend the existing provider resolution test and add Codex-specific binding assertions. [VERIFIED: tests/Unit/AI/OpenAIReviewProviderTest.php] |
| P06-D-25/P06-D-26 | Tests must fake auth files and HTTP, cover missing/malformed auth, 401/403, 429, transport failure, success, and no fallback. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | Add focused unit tests for auth parsing and the Codex provider plus failure-path feature coverage around queued execution. [VERIFIED: tests/Unit/AI/OpenAIReviewProviderTest.php] [VERIFIED: tests/Feature/QueuedReviewFailureTest.php] |
</phase_requirements>

## Project Constraints (from AGENTS.md)

- Use Laravel 13 and PHP 8.3-compatible code only. [VERIFIED: AGENTS.md]
- Keep SQLite-first local MVP assumptions intact; Phase 06 should not require new persistence tables. [VERIFIED: AGENTS.md] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]
- Keep AI review work behind Laravel queues; Phase 06 should not move provider calls back into the HTTP request. [VERIFIED: AGENTS.md] [VERIFIED: app/Services/ReviewExecutionService.php]
- Do not store or log raw API secrets; GitHub tokens, API keys, and imported Codex OAuth tokens must stay out of logs, records, and generated artifacts. [VERIFIED: AGENTS.md]
- Preserve the AI provider abstraction behind `AIReviewProvider`. [VERIFIED: AGENTS.md] [VERIFIED: app/Contracts/AI/AIReviewProvider.php]
- Preserve Controller / Service / Repository layering. [VERIFIED: AGENTS.md]
- Fake all external GitHub and AI calls in tests. [VERIFIED: AGENTS.md]
- Centralize environment access in config; application services should not call `env()` directly after config is loaded. [VERIFIED: AGENTS.md]
- In this environment, PHP/artisan/composer verification commands run inside the Docker workspace container, not on the host. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [VERIFIED: docker exec]

## Summary

The current Laravel seam is already appropriate for Phase 06: `AIReviewProvider` returns raw JSON text, `ReviewExecutionService` builds a single `AIReviewRequest`, validates decoded findings centrally, and marks the run failed through one mapper when provider work breaks. [VERIFIED: app/Contracts/AI/AIReviewProvider.php] [VERIFIED: app/Data/AI/AIReviewRequest.php] [VERIFIED: app/Services/ReviewExecutionService.php] [VERIFIED: app/Services/AI/AIReviewPayloadValidator.php] The smallest safe plan is therefore to add a third provider implementation plus explicit config-based provider selection, not to branch the queued review workflow. [VERIFIED: app/Providers/AppServiceProvider.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

The local Codex CLI cache on this machine is a JSON file with top-level `auth_mode`, `tokens`, and `last_refresh`, and `tokens` currently contains `id_token`, `access_token`, `refresh_token`, and `account_id`. [VERIFIED: local Codex auth cache] Official Codex docs say auth artifacts live under `CODEX_HOME`, and `auth.json` is present when credential storage is configured as `file` or when the OS keyring path is unavailable. [CITED: https://developers.openai.com/codex/auth] That means Phase 06 should use a path strategy of explicit override first, then `CODEX_HOME/auth.json`, then `~/.codex/auth.json`, and it must safe-fail when no readable file exists instead of assuming every logged-in Codex installation uses file-backed credentials. [VERIFIED: local Codex auth cache] [CITED: https://developers.openai.com/codex/auth] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

OpenClaw and Hermes both treat Codex OAuth as a Codex-specific transport, not as a generic OpenAI API-key path. [VERIFIED: /private/tmp/openclaw-openclaw/extensions/openai/openai-provider.ts] [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py] OpenClaw normalizes the backend to `https://chatgpt.com/backend-api/codex`, uses an `openai-chatgpt-responses` transport, and posts Codex-backed work to a `/responses` endpoint; Hermes documents that refresh-token ownership is risky when multiple clients share the same session and explicitly calls out `refresh_token_reused` as a terminal refresh error. [VERIFIED: /private/tmp/openclaw-openclaw/extensions/openai/base-url.ts] [VERIFIED: /private/tmp/openclaw-openclaw/extensions/openai/image-generation-provider.ts] [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py] Because Phase 06 does not own login or refresh rotation, the safest planning stance is read-only cache reuse with no automatic API-key fallback and no Laravel-side refresh-token writes. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py]

**Primary recommendation:** Use explicit `AI_PROVIDER` resolution plus a read-only `CodexAuthCacheReader` and `HttpOpenAICodexOAuthReviewProvider` that targets the Codex `/responses` transport, returns raw review JSON text, never persists imported tokens, and never falls back to the API-key provider. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [VERIFIED: app/Contracts/AI/AIReviewProvider.php] [VERIFIED: /private/tmp/openclaw-openclaw/extensions/openai/image-generation-provider.ts]

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| AI provider selection and container binding | API / Backend | — | Provider choice is application workflow configuration resolved by Laravel's service container, not a browser concern. [VERIFIED: app/Providers/AppServiceProvider.php] [CITED: https://laravel.com/docs/13.x/container] |
| Reading the local Codex auth cache | API / Backend | Database / Storage | The app process reads a local filesystem artifact at runtime, but it must not persist any imported credential state. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [CITED: https://developers.openai.com/codex/auth] |
| Sending Codex-backed review requests | API / Backend | — | The provider adapter owns outbound HTTP and response extraction behind `AIReviewProvider`. [VERIFIED: app/Contracts/AI/AIReviewProvider.php] [VERIFIED: app/Services/AI/HttpOpenAIReviewProvider.php] |
| Validating provider output and mapping safe failures | API / Backend | Database / Storage | Validation and failure summarization are service-layer responsibilities, while only safe summaries are persisted to `review_runs`. [VERIFIED: app/Services/AI/AIReviewPayloadValidator.php] [VERIFIED: app/Services/AI/AIReviewFailureMapper.php] [VERIFIED: app/Services/ReviewExecutionService.php] |
| Optional provider-status messaging in the UI | Frontend Server (SSR) | API / Backend | If Phase 06 adds a status indicator, Blade should only render safe availability state derived from backend checks, never raw token details. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [ASSUMED] |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` [VERIFIED: docker exec] | `v13.17.0` (released 2026-06-23) [VERIFIED: docker exec] | Provides the service container, config system, HTTP client, validation, and queue integration Phase 06 should reuse. [VERIFIED: docker exec] | Already installed; no new package is needed to add a third AI provider path. [VERIFIED: codebase] |
| `Illuminate\Http\Client` via `laravel/framework` [VERIFIED: codebase] | `v13.17.0` [VERIFIED: docker exec] | Handles Codex backend calls and `Http::fake()`-based tests. [VERIFIED: app/Services/AI/HttpOpenAIReviewProvider.php] | Matches the existing API-key provider pattern and keeps tests deterministic. [VERIFIED: tests/Unit/AI/OpenAIReviewProviderTest.php] |
| Existing AI seam: `AIReviewProvider` + `AIReviewRequest` + `AIReviewPayloadValidator` [VERIFIED: codebase] | current repo state [VERIFIED: codebase] | Preserves provider-agnostic execution and centralized schema validation. [VERIFIED: app/Contracts/AI/AIReviewProvider.php] [VERIFIED: app/Data/AI/AIReviewRequest.php] [VERIFIED: app/Services/AI/AIReviewPayloadValidator.php] | Prevents provider-specific branching in `ReviewExecutionService`. [VERIFIED: app/Services/ReviewExecutionService.php] |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `phpunit/phpunit` [VERIFIED: docker exec] | `12.5.30` (released 2026-06-15) [VERIFIED: docker exec] | Unit and feature tests for auth-file parsing, provider selection, and safe failure behavior. [VERIFIED: docker exec] | Use for all Phase 06 coverage; no real Codex network calls should be made. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| `laravel/pint` [VERIFIED: docker exec] | `v1.29.3` (released 2026-06-16) [VERIFIED: docker exec] | Keeps new AI provider classes and tests aligned with existing project style. [VERIFIED: docker exec] | Run on touched PHP files during implementation. [VERIFIED: AGENTS.md] |
| Docker workspace container `laradock-workspace-85-1` [VERIFIED: docker exec] | PHP `8.5.7`, Composer `2.10.1` [VERIFIED: docker exec] | Real execution target for `php`, `composer`, and `artisan test` in this environment. [VERIFIED: docker exec] | Use for all planner verification commands because host `php` and `composer` are unavailable. [VERIFIED: docker exec] |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Explicit `AI_PROVIDER` selector [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | Reuse boolean `services.openai.enabled` [VERIFIED: config/services.php] | The boolean path is ambiguous once two OpenAI-backed providers exist and makes accidental fallback much easier. [VERIFIED: app/Providers/AppServiceProvider.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| Read-only CLI cache reuse [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | Laravel-owned OAuth login or refresh-token rotation [CITED: https://developers.openai.com/codex/auth] | Login/refresh ownership is larger scope, and Hermes documents cross-client `refresh_token_reused` failures when multiple clients rotate the same session. [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py] |
| No new package; use existing Laravel HTTP/config/testing stack [VERIFIED: codebase] | Add a third-party OAuth or OpenAI SDK [ASSUMED] | Phase 06 is a transport and credential-source adaptation, not a new login flow; extra packages add attack surface without solving the key problem. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [ASSUMED] |

**Installation:**
```bash
# No new Composer or npm packages are recommended for Phase 06.
```

**Version verification:** `laravel/framework` `v13.17.0`, `phpunit/phpunit` `12.5.30`, `laravel/pint` `v1.29.3`, PHP `8.5.7`, and Composer `2.10.1` were verified in the workspace container on 2026-06-29. [VERIFIED: docker exec]

## Package Legitimacy Audit

No new external packages are recommended for Phase 06. [VERIFIED: codebase] The phase fits the existing Laravel, PHPUnit, and Pint stack. [VERIFIED: docker exec]

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

This phase should keep the current execution pipeline and swap only the provider-selection and credential-source seam. [VERIFIED: app/Services/ReviewExecutionService.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

```text
ReviewExecutionService
    -> build AIReviewRequest
    -> resolve AIReviewProvider from container
        -> fake
        -> openai_api_key
        -> openai_codex_oauth
              -> CodexAuthCacheReader
                    -> explicit auth path override?
                    -> else CODEX_HOME/auth.json?
                    -> else ~/.codex/auth.json?
                    -> parse safe credential DTO
              -> HttpOpenAICodexOAuthReviewProvider
                    -> POST {baseUrl}/responses
                    -> Bearer access token
                    -> optional ChatGPT-Account-ID
                    -> extract JSON review text
    -> json_decode()
    -> AIReviewPayloadValidator
    -> ReviewRunRepository / ReviewFindingRepository / ReviewCommentDraftRepository
    -> safe success or safe failure only
```

### Recommended Project Structure

```text
app/
├── Contracts/AI/
│   └── AIReviewProvider.php                  # unchanged contract
├── Data/AI/
│   └── CodexAuthCredentials.php             # optional DTO for parsed auth cache
├── Providers/
│   └── AppServiceProvider.php               # explicit provider selector
├── Services/AI/
│   ├── CodexAuthCacheReader.php             # runtime auth-file reader
│   ├── HttpOpenAICodexOAuthReviewProvider.php
│   └── AIReviewFailureMapper.php            # extended safe categories
config/
└── services.php                             # explicit AI provider + Codex path/base URL config
tests/
├── Unit/AI/
│   ├── CodexAuthCacheReaderTest.php
│   ├── OpenAICodexOAuthReviewProviderTest.php
│   └── OpenAIReviewProviderTest.php         # extend selector assertions
└── Feature/
    └── QueuedReviewFailureTest.php          # extend queued safe-failure coverage
```

### Pattern 1: Config-Driven Provider Resolution

**What:** Replace the current boolean `services.openai.enabled` switch with explicit provider resolution keyed by `AI_PROVIDER`, while keeping the existing fake provider default and the API-key provider intact. [VERIFIED: config/services.php] [VERIFIED: app/Providers/AppServiceProvider.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

**When to use:** Any time the app resolves `AIReviewProvider`; there should be one authoritative selector and no hidden fallback path. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

**Example:**
```php
<?php

// Source: https://laravel.com/docs/13.x/container
// Adapted to the current project seam.
$this->app->bind(AIReviewProvider::class, function () {
    return match (config('services.ai.provider')) {
        'openai_api_key' => app(HttpOpenAIReviewProvider::class),
        'openai_codex_oauth' => app(HttpOpenAICodexOAuthReviewProvider::class),
        'fake', null => app(FakeAIReviewProvider::class),
        default => throw new InvalidArgumentException('Unsupported AI provider.'),
    };
});
```

### Pattern 2: Read-Only Auth Cache Reader

**What:** Isolate auth-file discovery and parsing in a small reader that returns only the fields Phase 06 needs, such as `access_token`, optional `account_id`, `auth_mode`, and `last_refresh`. [VERIFIED: local Codex auth cache] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

**When to use:** Only inside the Codex OAuth provider path; all tests should fake this reader or its configured path so no real user credentials are needed. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

**Example:**
```php
<?php

// Source: https://developers.openai.com/codex/auth
final readonly class CodexAuthCredentials
{
    public function __construct(
        public string $accessToken,
        public ?string $accountId,
        public ?string $authMode,
        public ?string $lastRefresh,
    ) {}
}
```

### Pattern 3: Provider-Local Extraction, Central Validation

**What:** Keep response-shape adaptation inside the Codex provider, but keep JSON decoding, finding validation, and persistence exactly where they already live today. [VERIFIED: app/Services/ReviewExecutionService.php] [VERIFIED: app/Services/AI/AIReviewPayloadValidator.php]

**When to use:** For any Codex-specific response parsing ambiguity, throw a provider-local exception and let the existing failure path mark the run failed safely. [VERIFIED: app/Services/AI/AIReviewFailureMapper.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

**Example:**
```php
<?php

// Source: Phase 06 target pattern, adapted from the existing provider-local extraction boundary.
$text = null;

foreach ((array) data_get($response, 'output', []) as $item) {
    foreach ((array) data_get($item, 'content', []) as $part) {
        if (in_array(data_get($part, 'type'), ['output_text', 'text'], true) && is_string(data_get($part, 'text'))) {
            $text = data_get($part, 'text');
            break 2;
        }
    }
}

$text ??= is_string(data_get($response, 'output_text')) ? data_get($response, 'output_text') : null;

if (! is_string($text) || $text === '') {
    throw new UnexpectedValueException('Provider response did not include review JSON text.');
}
```

### Anti-Patterns to Avoid

- **Silent API-key fallback:** Never retry Codex auth/backend failures by switching to `OPENAI_API_KEY`; the user explicitly rejected that behavior because it changes cost and model semantics. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]
- **Laravel-owned token rotation:** Do not POST refresh-token grants or write refreshed tokens back into `~/.codex/auth.json`; Hermes treats shared-session refresh rotation as a real concurrency hazard. [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py]
- **Controller/job-local file reads:** Do not read `~/.codex/auth.json` directly from controllers, jobs, or Blade; keep that concern in one fakeable backend service. [VERIFIED: AGENTS.md] [VERIFIED: app/Services/ReviewExecutionService.php]
- **Persisting auth or backend payloads:** Never store raw auth-file content, bearer tokens, full exception bodies, or full backend responses in the database or flash messages. [VERIFIED: AGENTS.md] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OAuth browser/device login | A Laravel login UI or local callback server in Phase 06. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | Existing Codex CLI login, documented by OpenAI. [CITED: https://developers.openai.com/codex/auth] | The CLI already owns user interaction, and Phase 06 only needs runtime credential reuse. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| Refresh-token lifecycle | Shared-session refresh-token rotation in Laravel. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | Re-read the current file-backed cache or require the user to re-login through Codex CLI. [CITED: https://developers.openai.com/codex/auth] [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py] | Hermes explicitly classifies `refresh_token_reused` as terminal; rotating shared tokens inside Laravel is a trap. [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py] |
| Provider selection | Boolean `OPENAI_ENABLED` branches or controller-level conditionals. [VERIFIED: config/services.php] | One container binding keyed by explicit provider config. [CITED: https://laravel.com/docs/13.x/container] | It keeps fake, API-key, and Codex OAuth modes testable and distinct. [VERIFIED: tests/Unit/AI/OpenAIReviewProviderTest.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| Safe-failure summarization | Ad hoc string concatenation from raw exceptions. [VERIFIED: app/Services/AI/AIReviewFailureMapper.php] | Typed provider exceptions plus centralized safe message mapping. [VERIFIED: app/Services/AI/AIReviewFailureMapper.php] | Existing execution flow already expects one safe mapper path and should stay that way. [VERIFIED: app/Services/ReviewExecutionService.php] |

**Key insight:** Phase 06 is a credential-source and transport adaptation phase, not an authentication-ownership phase. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

## Common Pitfalls

### Pitfall 1: Assuming `auth.json` Always Exists

**What goes wrong:** The provider hardcodes `~/.codex/auth.json`, but some Codex installs use keychain-backed storage and therefore never produce a readable file. [CITED: https://developers.openai.com/codex/auth]

**Why it happens:** Official Codex auth uses `CODEX_HOME`, and file storage is conditional rather than universal. [CITED: https://developers.openai.com/codex/auth]

**How to avoid:** Support explicit auth-path override, then `CODEX_HOME/auth.json`, then `~/.codex/auth.json`, and fail with a clear prerequisite message when no readable file exists. [CITED: https://developers.openai.com/codex/auth] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

**Warning signs:** Tests only pass when the developer machine is already logged in locally, or production-like machines report "file not found" even though Codex login status is healthy. [ASSUMED]

### Pitfall 2: Sharing Refresh-Token Ownership

**What goes wrong:** One client refreshes the Codex session and another client immediately starts failing with `invalid_grant` or `refresh_token_reused`. [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py]

**Why it happens:** Hermes documents the refresh token as effectively single-use across clients sharing the same OAuth session. [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py]

**How to avoid:** Do not implement refresh-token rotation in Laravel for Phase 06; keep Phase 06 read-only and re-read the cache on each call. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py]

**Warning signs:** A provider works once after login but later fails without code changes, especially after the Codex CLI or another tool has been used. [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py]

### Pitfall 3: Hiding Codex Failure Behind API-Key Fallback

**What goes wrong:** The app silently switches to the API-key provider, creating unexpected cost and making debugging impossible. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

**Why it happens:** The current codebase already has one boolean OpenAI switch, so a naive extension can conflate "OpenAI" with "any OpenAI-backed path." [VERIFIED: app/Providers/AppServiceProvider.php] [VERIFIED: config/services.php]

**How to avoid:** Use an explicit provider enum/string and let Codex-auth/backend failures surface as safe Codex failures only. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

**Warning signs:** A Codex-auth failure unexpectedly succeeds after `OPENAI_API_KEY` is set, or tests cannot prove which provider path executed. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

### Pitfall 4: Treating the Unofficial Codex Response Shape as Guaranteed

**What goes wrong:** The provider assumes the same body shape as the current OpenAI Responses API and crashes on a transport-specific variation. [VERIFIED: app/Services/AI/HttpOpenAIReviewProvider.php] [ASSUMED]

**Why it happens:** `chatgpt.com/backend-api/codex` is not documented as a public Laravel integration surface, so response fields are less stable than the public API. [VERIFIED: /private/tmp/openclaw-openclaw/extensions/openai/base-url.ts] [ASSUMED]

**How to avoid:** Keep extraction tolerant, support the minimal known text paths, and map any unsupported success body to a safe invalid-output error. [VERIFIED: app/Services/AI/HttpOpenAIReviewProvider.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

**Warning signs:** HTTP 200 responses still cause schema failures, or manual log inspection shows text in a field the provider never checks. [ASSUMED]

### Pitfall 5: Leaking Local Credential Material in Errors

**What goes wrong:** Exception text or debug logging includes path contents, bearer tokens, `id_token`, or raw backend payloads. [VERIFIED: AGENTS.md] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

**Why it happens:** File-read and HTTP exceptions often include raw payload fragments by default. [ASSUMED]

**How to avoid:** Throw typed safe exceptions from the reader/provider and let one mapper translate them to fixed messages. [VERIFIED: app/Services/AI/AIReviewFailureMapper.php] [VERIFIED: app/Services/ReviewExecutionService.php]

**Warning signs:** Failure messages differ run to run or include path snippets, JSON fragments, or token-looking strings. [ASSUMED]

## Code Examples

Verified patterns from official sources and the current codebase:

### Container-Bound Provider Selection
```php
<?php

// Source: https://laravel.com/docs/13.x/container
$this->app->bind(AIReviewProvider::class, function () {
    return match (config('services.ai.provider')) {
        'fake' => app(FakeAIReviewProvider::class),
        'openai_api_key' => app(HttpOpenAIReviewProvider::class),
        'openai_codex_oauth' => app(HttpOpenAICodexOAuthReviewProvider::class),
        default => throw new InvalidArgumentException('Unsupported AI provider.'),
    };
});
```

### Runtime-Only Auth Path Resolution
```php
<?php

// Source: https://developers.openai.com/codex/auth
private function resolveCodexAuthPath(): string
{
    return (string) (
        config('services.codex.auth_path')
        ?: config('services.codex.default_auth_path')
    );
}
```

### Safe Text Extraction Before Central Validation
```php
<?php

// Source: Phase 06 target pattern, adapted from OpenAI Responses object text parts.
$text = null;

foreach ((array) data_get($response, 'output', []) as $item) {
    foreach ((array) data_get($item, 'content', []) as $part) {
        if (in_array(data_get($part, 'type'), ['output_text', 'text'], true) && is_string(data_get($part, 'text'))) {
            $text = data_get($part, 'text');
            break 2;
        }
    }
}

$text ??= is_string(data_get($response, 'output_text')) ? data_get($response, 'output_text') : null;

if (! is_string($text) || $text === '') {
    throw new UnexpectedValueException('Codex response did not include review JSON text.');
}

return $text;
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Boolean `OPENAI_ENABLED` chooses between fake and one HTTP provider. [VERIFIED: config/services.php] | Explicit provider selection such as `fake`, `openai_api_key`, and `openai_codex_oauth`. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | Phase 06 planning target. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | Makes the active route obvious and prevents unsafe fallback coupling. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| Laravel owns all OpenAI credentials through env/config only. [VERIFIED: config/services.php] | Laravel may borrow a runtime access token from the local Codex CLI cache without persisting it. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [VERIFIED: local Codex auth cache] | Phase 06 planning target. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | Adds a personal-use Codex subscription route while preserving the no-persistence rule. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| Public OpenAI API transport only. [VERIFIED: app/Services/AI/HttpOpenAIReviewProvider.php] | Codex transport inferred from OpenClaw and Hermes: `https://chatgpt.com/backend-api/codex` with `/responses`. [VERIFIED: /private/tmp/openclaw-openclaw/extensions/openai/base-url.ts] [VERIFIED: /private/tmp/openclaw-openclaw/extensions/openai/image-generation-provider.ts] | Current reference-code behavior as of 2026-06-29. [VERIFIED: /private/tmp/openclaw-openclaw/extensions/openai/base-url.ts] | Requires defensive parsing and explicit failure handling because the endpoint is not the current app's public OpenAI API integration path. [VERIFIED: app/Services/AI/HttpOpenAIReviewProvider.php] [ASSUMED] |

**Deprecated/outdated:**

- `OPENAI_ENABLED` as the only provider selector is now insufficient once both API-key OpenAI and Codex OAuth must coexist. [VERIFIED: config/services.php] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]
- Automatic provider fallback is intentionally out of scope for this phase. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | RESOLVED: Phase 06 will treat OpenAI Responses-style `output[].content[]` parts with `type` of `output_text` or `text` as the primary success body contract, with `output_text` as a compatibility fallback. [VERIFIED: https://platform.openai.com/docs/api-reference/responses/object] [VERIFIED: /private/tmp/hermes-agent/agent/codex_responses_adapter.py] | Summary, Pattern 3, Code Examples | If the unofficial Codex backend drifts from the Responses object shape, provider tests should fail closed as unsupported response shape rather than persisting malformed findings. |
| A2 | RESOLVED: Phase 06 will send `ChatGPT-Account-ID` when an account id is available from the Codex auth cache/credential DTO; if unavailable, the provider will omit it without crashing and let the backend return a mapped auth failure. [VERIFIED: /private/tmp/hermes-agent/agent/auxiliary_client.py] [VERIFIED: local Codex auth cache] | Open Questions (RESOLVED), Common Pitfalls | If a future backend requires the header in all cases, missing account id will produce a safe 401/403 failure instead of fallback or secret leakage. |
| A3 | A small management-page availability indicator is optional rather than required for safe operation in Phase 06. [ASSUMED] | Architectural Responsibility Map | If the user expects UI visibility, the plan may miss a small but important UX task. |

## Open Questions (RESOLVED)

1. **What is the exact successful response body shape for `POST https://chatgpt.com/backend-api/codex/responses` in this PR-review use case?**
   - Resolution: Phase 06 will use the standard OpenAI Responses object shape as the contract: read assistant message content from `output[].content[]` entries whose `type` is `output_text` or `text`, then return the first non-empty `text` value. [VERIFIED: https://platform.openai.com/docs/api-reference/responses/object]
   - Compatibility fallback: If `output[].content[]` has no text, check top-level `output_text`, because Hermes normalizes final text from that field when Responses content parts are empty. [VERIFIED: /private/tmp/hermes-agent/agent/codex_responses_adapter.py]
   - Explicit non-goal: Do not scatter Codex response parsing through queued execution. The Codex provider owns extraction and must throw a safe unsupported-response exception when neither contract yields review JSON text. [VERIFIED: app/Services/AI/HttpOpenAIReviewProvider.php] [VERIFIED: app/Services/ReviewExecutionService.php]
   - Planning impact: Update provider tests to fake a Responses object with `output: [{ type: "message", content: [{ type: "output_text", text: "{...}" }] }]`, plus a fallback `output_text` case and unsupported-shape failure case.

2. **Is `tokens.account_id` or `ChatGPT-Account-ID` required for inference requests, or only for model discovery/catalog calls?**
   - Resolution: Phase 06 will treat `ChatGPT-Account-ID` as part of the best-effort Codex request header contract when account id is available. Hermes extracts `chatgpt_account_id` from the OAuth JWT and sends the canonical `ChatGPT-Account-ID` header for Codex backend requests; the local cache also exposes an account id field. [VERIFIED: /private/tmp/hermes-agent/agent/auxiliary_client.py] [VERIFIED: local Codex auth cache]
   - Failure behavior: Missing or unparsable account id must not crash, must not trigger API-key fallback, and must not expose token contents. The provider may omit the header, and 401/403 responses are mapped to a safe unauthorized Codex failure. [VERIFIED: /private/tmp/hermes-agent/agent/auxiliary_client.py] [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]
   - Planning impact: Keep `accountId` optional on `CodexAuthCredentials`, assert `ChatGPT-Account-ID` is sent when present, and assert no header/body/token leakage when authorization fails.

3. **Should Phase 06 expose a safe UI status indicator for Codex auth availability?**
   - What we know: The context leaves this as planner discretion, and a full provider-management UI is explicitly out of scope. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]
   - What's unclear: Whether the user wants a tiny Blade hint like "Codex auth file not available" before running a review. [ASSUMED]
   - Recommendation: Keep it optional and only plan it if it materially reduces confusing failures. [ASSUMED]

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Docker Engine [VERIFIED: docker exec] | All PHP/composer/artisan verification in this environment. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | ✓ [VERIFIED: docker exec] | `29.4.0` [VERIFIED: docker exec] | — |
| Laravel workspace container `laradock-workspace-85-1` [VERIFIED: docker exec] | Running tests and any Phase 06 PHP commands. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | ✓ [VERIFIED: docker exec] | PHP `8.5.7`, Composer `2.10.1` [VERIFIED: docker exec] | — |
| Host `php` CLI [VERIFIED: exec_command] | Direct local `artisan` / PHPUnit execution. [VERIFIED: codebase] | ✗ [VERIFIED: exec_command] | — | Use `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php ...`. [VERIFIED: docker exec] |
| Host `composer` CLI [VERIFIED: exec_command] | Direct local `composer run test`. [VERIFIED: codebase] | ✗ [VERIFIED: exec_command] | — | Use `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer ...`. [VERIFIED: docker exec] |
| Host `node` / `npm` [VERIFIED: exec_command] | Non-PHP tooling and existing frontend stack. [VERIFIED: codebase] | ✓ [VERIFIED: exec_command] | Node `v24.1.0`, npm `11.3.0` [VERIFIED: exec_command] | — |
| Local Codex auth cache [VERIFIED: local Codex auth cache] | Runtime token source for the Codex OAuth provider. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | ✓ on this machine [VERIFIED: local Codex auth cache] | schema observed on 2026-06-29 [VERIFIED: local Codex auth cache] | When absent on other machines, require Codex CLI login plus file-backed storage or explicit auth-path override. [CITED: https://developers.openai.com/codex/auth] |

**Missing dependencies with no fallback:**

- Host `php` and `composer` are missing; all planner verification commands must target the Docker workspace container. [VERIFIED: exec_command] [VERIFIED: docker exec]

**Missing dependencies with fallback:**

- None beyond the documented host-to-container PHP/composer fallback. [VERIFIED: docker exec]

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit `12.5.30` via Laravel test runner. [VERIFIED: docker exec] |
| Config file | `phpunit.xml`. [VERIFIED: phpunit.xml] |
| Quick run command | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAIReviewProviderTest|AIReviewFailureMapperTest|QueuedReviewFailureTest'` [VERIFIED: docker exec] |
| Full suite command | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` [VERIFIED: docker exec] |

### Phase Requirements -> Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| P06-D-15 | Container resolves `fake`, `openai_api_key`, and `openai_codex_oauth` distinctly. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAIReviewProviderTest|OpenAICodexOAuthReviewProviderTest'` [VERIFIED: docker exec] | Partial: `tests/Unit/AI/OpenAIReviewProviderTest.php` exists; Codex-specific test file is Wave 0. [VERIFIED: tests/Unit/AI/OpenAIReviewProviderTest.php] |
| P06-D-09 / P06-D-25 | Missing auth file, malformed JSON, or missing access token safe-fails without secret leakage. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='CodexAuthCacheReaderTest|AIReviewFailureMapperTest'` [VERIFIED: docker exec] | ❌ Wave 0 |
| P06-D-19 / P06-D-26 | Backend 401/403, 429, and transport failures map to safe categorized Codex errors. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAICodexOAuthReviewProviderTest|AIReviewFailureMapperTest'` [VERIFIED: docker exec] | ❌ Wave 0 |
| AI-04 / AI-08 | Successful Codex response still returns JSON text compatible with `AIReviewPayloadValidator`, and invalid text fails safely. [VERIFIED: .planning/REQUIREMENTS.md] | unit + feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAICodexOAuthReviewProviderTest|QueuedReviewExecutionTest|QueuedReviewFailureTest'` [VERIFIED: docker exec] | Partial: feature files exist; Codex unit file is Wave 0. [VERIFIED: tests/Feature/QueuedReviewExecutionTest.php] [VERIFIED: tests/Feature/QueuedReviewFailureTest.php] |
| EXEC-05 | No raw token, auth-file body, or backend body leaks into persisted safe errors. [VERIFIED: .planning/REQUIREMENTS.md] | feature + unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='AIReviewFailureMapperTest|QueuedReviewFailureTest|OpenAICodexOAuthReviewProviderTest'` [VERIFIED: docker exec] | Partial: mapper and feature files exist; Codex provider test is Wave 0. [VERIFIED: tests/Unit/AI/AIReviewFailureMapperTest.php] [VERIFIED: tests/Feature/QueuedReviewFailureTest.php] |

### Sampling Rate

- **Per task commit:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='OpenAIReviewProviderTest|AIReviewFailureMapperTest|QueuedReviewFailureTest'` [VERIFIED: docker exec]
- **Per wave merge:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` [VERIFIED: docker exec]
- **Phase gate:** Full suite green before `$gsd-verify-work`. [VERIFIED: .planning/config.json]

### Wave 0 Gaps

- [ ] `tests/Unit/AI/CodexAuthCacheReaderTest.php` - fake auth-file discovery, malformed JSON, missing access token, and safe parsing coverage. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]
- [ ] `tests/Unit/AI/OpenAICodexOAuthReviewProviderTest.php` - HTTP fake coverage for success, 401/403, 429, transport failure, malformed success body, and no API-key fallback. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md]
- [ ] Extend `tests/Unit/AI/OpenAIReviewProviderTest.php` - explicit selector coverage for all three provider modes. [VERIFIED: tests/Unit/AI/OpenAIReviewProviderTest.php]
- [ ] Extend `tests/Feature/QueuedReviewFailureTest.php` - queued safe-failure summaries for Codex auth and Codex backend errors. [VERIFIED: tests/Feature/QueuedReviewFailureTest.php]

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | yes [VERIFIED: .planning/config.json] | Trust only a runtime-read Codex access token from the configured local auth file, with explicit provider selection and no silent fallback. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| V3 Session Management | yes [VERIFIED: .planning/config.json] | Treat the imported Codex session as external state owned by Codex CLI; Laravel reads it but does not rotate or persist it. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py] |
| V4 Access Control | no [VERIFIED: codebase] | Phase 06 does not add new end-user authorization flows; no-login personal-use scope remains unchanged. [VERIFIED: AGENTS.md] |
| V5 Input Validation | yes [VERIFIED: .planning/config.json] | Validate auth-file JSON structure before use, and validate provider output with `AIReviewPayloadValidator`. [VERIFIED: local Codex auth cache] [VERIFIED: app/Services/AI/AIReviewPayloadValidator.php] |
| V6 Cryptography | yes [VERIFIED: .planning/config.json] | Rely on OpenAI-issued OAuth tokens and HTTPS/TLS; do not hand-roll token signing, encryption, or refresh logic. [CITED: https://developers.openai.com/codex/auth] [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py] |

### Known Threat Patterns for Laravel + local Codex OAuth reuse

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Credential leakage through logs or persisted safe errors. [VERIFIED: AGENTS.md] | Information Disclosure | Keep auth-file parsing and backend failures on typed safe exceptions; never persist tokens or raw payloads. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [VERIFIED: app/Services/AI/AIReviewFailureMapper.php] |
| Path poisoning or wrong credential source selection. [ASSUMED] | Tampering | Resolve auth path from trusted config only, prefer explicit override, and do not fetch remote credential locations. [CITED: https://developers.openai.com/codex/auth] [ASSUMED] |
| Shared refresh-token invalidation across tools. [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py] | Denial of Service | Avoid Laravel-owned refresh rotation and safe-fail on unusable sessions. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] [VERIFIED: /private/tmp/hermes-agent/hermes_cli/auth.py] |
| Hidden switch from subscription auth to API-key billing. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] | Repudiation / Elevation of Privilege | Make provider choice explicit and keep fallback disabled. [VERIFIED: .planning/phases/06-openai-codex-oauth-ai-provider/06-CONTEXT.md] |
| Malformed or unsupported Codex backend response still reaching persistence. [VERIFIED: app/Services/AI/AIReviewPayloadValidator.php] [ASSUMED] | Tampering | Extract only text, decode centrally, reject unknown top-level/finding keys, and fail the run safely. [VERIFIED: app/Services/AI/AIReviewPayloadValidator.php] [VERIFIED: app/Services/ReviewExecutionService.php] |

## Sources

### Primary (HIGH confidence)

- Current project codebase: `app/Contracts/AI/AIReviewProvider.php`, `app/Data/AI/AIReviewRequest.php`, `app/Services/AI/HttpOpenAIReviewProvider.php`, `app/Services/AI/FakeAIReviewProvider.php`, `app/Services/AI/AIReviewFailureMapper.php`, `app/Services/AI/AIReviewPayloadValidator.php`, `app/Services/ReviewExecutionService.php`, `app/Providers/AppServiceProvider.php`, `config/services.php`, `tests/Unit/AI/OpenAIReviewProviderTest.php`. [VERIFIED: codebase]
- Local Codex auth cache schema probe for `~/.codex/auth.json`, run without printing secret values. [VERIFIED: local Codex auth cache]
- OpenClaw local reference code: `docs/providers/openai.md`, `extensions/openai/base-url.ts`, `extensions/openai/openai-provider.ts`, `extensions/openai/openai-chatgpt-provider.ts`, `extensions/openai/image-generation-provider.ts`, `extensions/openai/openai-chatgpt-auth-identity.ts`. [VERIFIED: /private/tmp/openclaw-openclaw]
- Hermes local reference code: `hermes_cli/auth.py`, `hermes_cli/auth_commands.py`, `agent/credential_pool.py`. [VERIFIED: /private/tmp/hermes-agent]
- Workspace container environment checks: `docker ps`, `docker exec ... php --version`, `docker exec ... composer --version`, and `docker exec ... php artisan test --filter=OpenAIReviewProviderTest --stop-on-failure`. [VERIFIED: docker exec]

### Secondary (MEDIUM confidence)

- OpenAI Codex authentication docs: https://developers.openai.com/codex/auth
- OpenAI Codex README: https://github.com/openai/codex#using-codex-with-your-chatgpt-plan

### Tertiary (LOW confidence)

- None beyond the explicit assumptions listed in `## Assumptions Log`. [VERIFIED: this document]

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH - The repo versions, container runtime, and existing Laravel seams were verified locally. [VERIFIED: docker exec] [VERIFIED: codebase]
- Architecture: MEDIUM - The Laravel integration points are clear and the Phase 06 success parser now targets the official Responses object text-part shape, while the Codex backend itself remains an unofficial transport surface that should fail closed on shape drift. [VERIFIED: codebase] [VERIFIED: https://platform.openai.com/docs/api-reference/responses/object] [VERIFIED: /private/tmp/openclaw-openclaw] [VERIFIED: /private/tmp/hermes-agent]
- Pitfalls: HIGH - The key risks were cross-checked against official Codex docs, local cache shape, and two separate reference implementations. [CITED: https://developers.openai.com/codex/auth] [VERIFIED: local Codex auth cache] [VERIFIED: /private/tmp/openclaw-openclaw] [VERIFIED: /private/tmp/hermes-agent]

**Research date:** 2026-06-29
**Valid until:** 2026-07-06 because `chatgpt.com/backend-api/codex` behavior is an unofficial transport surface and may shift faster than the Laravel codebase or official CLI docs. [VERIFIED: /private/tmp/openclaw-openclaw/extensions/openai/base-url.ts] [ASSUMED]
