# Phase 06: OpenAI Codex OAuth AI Provider - Context

**Gathered:** 2026-06-29T16:08:20+08:00
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 06 adds an OpenAI Codex OAuth AI provider path to the existing AI review workflow. The provider should let the app run AI review through a locally authenticated Codex/ChatGPT session by reading the existing Codex CLI cache at `~/.codex/auth.json`, while keeping the current fake provider and OpenAI Platform API-key provider available.

This phase does not build a full OAuth login UI, device-code UI, browser callback flow, token storage system, team auth model, multi-provider management UI, or automatic fallback between paid API-key and Codex subscription routes. It should keep AI execution behind the existing `AIReviewProvider` contract and preserve Controller / Service / Repository layering where persistence or workflow code is needed.

</domain>

<decisions>
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

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project and Requirements
- `.planning/PROJECT.md` - Product purpose, personal-use scope, AI provider abstraction, no-secret-storage rule, queueing, fake external calls in tests, and Controller / Service / Repository architecture.
- `.planning/REQUIREMENTS.md` - Existing `AI-01` through `AI-08`, `EXEC-01` through `EXEC-05`, and architecture constraints. Phase 06 extends the AI provider choice rather than changing review execution behavior.
- `.planning/ROADMAP.md` - Phase 06 entry, dependency on Phase 5, and current milestone context.
- `.planning/STATE.md` - Current project state after Phase 06 was added to the roadmap.

### Prior Phase Context
- `.planning/phases/03-queued-ai-review-and-structured-findings/03-CONTEXT.md` - AI provider interface, fake-first provider strategy, safe failure, retry, and structured output decisions.
- `.planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md` - Custom instructions are included in future AI review requests and should continue to flow through the provider contract.
- `.planning/phases/05-github-comment-publishing/05-CONTEXT.md` - Safe external integration patterns, no raw provider payload persistence, and explicit user-control philosophy.

### Current Code
- `app/Contracts/AI/AIReviewProvider.php` - Existing provider contract that Phase 06 should preserve.
- `app/Data/AI/AIReviewRequest.php` - Existing review input DTO that the Codex provider should consume.
- `app/Services/AI/FakeAIReviewProvider.php` - Existing deterministic fake provider path.
- `app/Services/AI/HttpOpenAIReviewProvider.php` - Existing OpenAI Platform API-key provider to keep as a separate selectable route.
- `app/Services/AI/ReviewInstructionBuilder.php` - Existing instruction composition path that must continue to feed provider requests.
- `app/Services/ReviewExecutionService.php` - Existing queued review execution workflow that should remain provider-agnostic.
- `app/Providers/AppServiceProvider.php` - Current AI provider binding location.
- `config/services.php` - Current OpenAI config; Phase 06 should add provider selection/config without direct `env()` reads in services.
- `tests/Unit/AI/OpenAIReviewProviderTest.php` - Existing provider resolution and HTTP fake tests to extend or mirror.

### External References
- `https://developers.openai.com/codex/auth` - Official Codex authentication page: Codex supports ChatGPT sign-in and API-key sign-in; CLI/IDE cache login details.
- `https://github.com/openai/codex#using-codex-with-your-chatgpt-plan` - Official Codex README describing ChatGPT plan sign-in and API-key alternative.
- `https://github.com/openclaw/openclaw/blob/main/docs/providers/openai.md` - OpenClaw provider docs describing OpenAI API-key and Codex subscription OAuth routes.
- `https://github.com/openclaw/openclaw/blob/main/extensions/openai/openai-chatgpt-oauth-flow.runtime.ts` - OpenClaw browser OAuth / token refresh implementation reference.
- `https://github.com/openclaw/openclaw/blob/main/extensions/openai/openai-chatgpt-device-code.ts` - OpenClaw device-code implementation reference, useful for future phases but out of scope for Phase 06.
- `https://github.com/nousresearch/hermes-agent/blob/main/hermes_cli/auth.py` - Hermes `openai-codex` auth handling, Codex cache import, device-code flow, token refresh, and safe failure patterns.
- `https://github.com/nousresearch/hermes-agent/blob/main/hermes_cli/auth_commands.py` - Hermes command wiring for adding `openai-codex` credentials.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `AIReviewProvider` already isolates review generation from the rest of the workflow.
- `AIReviewRequest` already carries all review input needed by a new provider.
- `ReviewExecutionService` already treats the provider as a dependency and validates provider output after `review()`.
- `FakeAIReviewProvider` already proves deterministic provider behavior for local tests.
- `HttpOpenAIReviewProvider` already shows the current HTTP client pattern and response-text extraction behavior for OpenAI-style responses.
- `AppServiceProvider` already owns provider binding and can be evolved into config-based provider selection.

### Established Patterns
- External calls sit behind interfaces and are faked in tests.
- Controllers should not own provider or credential logic.
- Services own workflow and safety behavior.
- Database access belongs in repositories; Phase 06 should avoid new persistence unless planning finds a strong need.
- Secrets and raw provider payloads must not be persisted or logged.
- PHP/artisan/composer commands must run inside the Docker workspace container in this environment.

### Integration Points
- Add provider-selection config, likely under `config/services.php` or a small dedicated config entry.
- Add a Codex auth-cache reader that can be faked in tests.
- Add a Codex HTTP provider implementation that uses the current AI provider contract.
- Update `AppServiceProvider` to resolve `fake`, `openai_api_key`, and `openai_codex_oauth` modes.
- Extend AI provider tests to cover selection, missing cache, malformed cache, safe failures, and fake Codex backend responses.

</code_context>

<specifics>
## Specific Ideas

- The user wants the app to work more like OpenClaw and Hermes for OpenAI/Codex auth.
- The first Phase 06 slice should be small: use the existing Codex CLI login cache rather than implementing OAuth UI.
- The app should not store Codex OAuth tokens in Laravel.
- The active provider should be selected by `AI_PROVIDER=openai_codex_oauth`.
- Codex backend failure should be explicit and safe, not hidden by automatic OpenAI API-key fallback.

</specifics>

<deferred>
## Deferred Ideas

- Full browser localhost callback OAuth belongs to a future phase.
- Device-code OAuth UI belongs to a future phase.
- Database-backed encrypted token storage belongs to a future phase if this becomes multi-user or team-oriented.
- A full provider management UI belongs to a future phase.
- Automatic fallback between Codex OAuth and OpenAI API-key providers is intentionally deferred and should not happen silently.
- Webhook automation, team permissions, named rule sets, and SaaS operations remain outside Phase 06.

</deferred>

---

*Phase: 06-OpenAI Codex OAuth AI Provider*
*Context gathered: 2026-06-29T16:08:20+08:00*
