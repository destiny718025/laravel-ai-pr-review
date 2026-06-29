# Phase 06: OpenAI Codex OAuth AI Provider - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md - this log preserves the alternatives considered.

**Date:** 2026-06-29T16:08:20+08:00
**Phase:** 06-openai-codex-oauth-ai-provider
**Areas discussed:** OAuth source and login flow, Token storage and safety, Provider switching, Codex backend failure behavior

---

## OAuth Source and Login Flow

| Option | Description | Selected |
|--------|-------------|----------|
| Import `~/.codex/auth.json` | Use the locally signed-in Codex CLI token first. Fastest path for Phase 06 and avoids building OAuth UI now. | yes |
| Device-code flow | Laravel displays a code and the user signs in through OpenAI. Closer to Hermes/OpenClaw headless mode but more implementation. | |
| Browser localhost callback OAuth | Full OAuth callback flow. Better UX but more callback and network error handling. | |
| Import plus device-code | Most complete first slice, but makes Phase 06 larger. | |

**User's choice:** Import `~/.codex/auth.json`.
**Notes:** The first implementation should depend on the user signing in through Codex CLI outside Laravel.

---

## Token Storage and Safety

| Option | Description | Selected |
|--------|-------------|----------|
| Read only from Codex CLI cache | Laravel reads `~/.codex/auth.json` at runtime and does not store tokens. Lowest storage risk. | yes |
| Store encrypted tokens in DB | Laravel owns imported tokens and refresh. More complete, but larger security surface. | |
| Store encrypted tokens in Laravel storage | Avoids DB storage but still creates a Laravel-owned secret store. | |
| Let the agent decide | The agent chooses based on MVP scope and safety. | |

**User's choice:** Read only from Codex CLI cache.
**Notes:** Laravel should not persist access tokens, refresh tokens, id tokens, or copied auth cache contents.

---

## Provider Switching

| Option | Description | Selected |
|--------|-------------|----------|
| Add `AI_PROVIDER=openai_codex_oauth` | Keep `fake`, `openai_api_key`, and `openai_codex_oauth` as distinct provider modes. | yes |
| Add `OPENAI_AUTH_MODE=codex_oauth` | Reuse `OPENAI_ENABLED=true`, but mixes API-key and Codex OAuth semantics. | |
| Replace current OpenAI provider | Simpler, but removes the API-key path and makes fallback/testing less clear. | |
| Let the agent decide | The agent chooses based on maintainability and test clarity. | |

**User's choice:** Add `AI_PROVIDER=openai_codex_oauth`.
**Notes:** The existing fake provider and OpenAI API-key provider should remain available.

---

## Codex Backend Failure Behavior

| Option | Description | Selected |
|--------|-------------|----------|
| Safe fail, no fallback | Codex backend failures fail explicitly and safely. Avoids unexpected API-key costs or model behavior changes. | yes |
| Automatic fallback to API key | Smoother in some failures, but can create cost and behavior surprises. | |
| Auth failures safe fail, transient errors retry | More nuanced, but requires more error classification in Phase 06. | |
| Let the agent decide | The agent chooses based on predictability and safety. | |

**User's choice:** Safe fail, no fallback.
**Notes:** Users can manually switch provider config if they intentionally want API-key behavior.

---

## the agent's Discretion

- The planner may choose exact class names, DTO names, config keys beyond `AI_PROVIDER`, and error mapper structure.
- The planner may decide the exact Codex backend request and response shape after researching OpenClaw, Hermes, and official Codex docs.
- The planner may decide whether a minimal provider status indicator is necessary for safe operation.

## Deferred Ideas

- Browser localhost callback OAuth.
- OpenAI device-code OAuth UI.
- Laravel-owned encrypted token storage.
- Full provider management UI.
- Automatic fallback from Codex OAuth to OpenAI API-key provider.
