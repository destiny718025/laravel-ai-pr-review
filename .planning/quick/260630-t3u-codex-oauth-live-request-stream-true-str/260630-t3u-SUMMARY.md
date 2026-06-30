---
quick_id: 260630-t3u
slug: codex-oauth-live-request-stream-true-str
status: complete
completed: 2026-06-30
---

# Quick Task 260630-t3u Summary

## Outcome

Codex OAuth provider 現在會在 `/responses` request payload 明確送出 `stream => true`，並可解析 Codex streaming/SSE 回應中的 JSON review text。

## Changes

- `HttpOpenAICodexOAuthReviewProvider` payload 加入 `stream => true`。
- 新增 event stream 偵測與 SSE data parsing。
- 支援從 `response.output_text.delta` 累積輸出文字。
- 支援 `response.output_text.done`、`response.completed`、`output`、`output_text` streaming event fallback。
- 測試鎖住 provider、queued execution、smoke command request 都包含 `store === false` 與 `stream === true`。

## Verification

- Codex targeted tests passed: 12 tests, 52 assertions.
- Full suite passed: 123 tests, 874 assertions.
