---
quick_id: 260630-t0k
slug: codex-oauth-live-request-store-false-bac
status: complete
completed: 2026-06-30
---

# Quick Task 260630-t0k Summary

## Outcome

修正 Codex backend live request 需要 `store: false` 的問題。`openai_codex_oauth` provider 現在會在 `/responses` request payload 明確送出 `store => false`。

## Changes

- `HttpOpenAICodexOAuthReviewProvider` 的 Codex `/responses` payload 加入 `store => false`。
- `OpenAICodexOAuthReviewProviderTest` 鎖定 provider request payload 必須包含 `store === false`。
- `QueuedReviewExecutionTest` 鎖定 queued Codex path 也會送出 `store === false`。
- `CodexOAuthSmokeCommandTest` 鎖定 `ai:codex-oauth-test --live` request 也會送出 `store === false`。

## Verification

- Codex targeted tests passed: 11 tests, 49 assertions.
- Full suite passed: 122 tests, 871 assertions.
