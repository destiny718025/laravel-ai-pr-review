---
quick_id: 260630-snw
slug: artisan-command-openai-codex-oauth-provi
status: complete
completed: 2026-06-30
---

# Quick Task 260630-snw Summary

## Outcome

新增 `ai:codex-oauth-test` Artisan command，方便檢查 OpenAI Codex OAuth provider 是否能讀到 auth cache，並可用 `--live` 發送一次真實 provider smoke request。

## Changes

- `CodexAuthCacheReader::candidatePaths()` 改為 public，讓 command 可以安全顯示候選路徑。
- `routes/console.php` 新增 `ai:codex-oauth-test`。
- command 預設 dry-run，不打外部 API。
- command 的 `--live` 模式會要求 `AI_PROVIDER=openai_codex_oauth`，並驗證 provider 回傳符合 findings schema。
- command 不輸出 access token，account id 也會遮罩。
- 新增 `CodexOAuthSmokeCommandTest` 覆蓋 dry-run、missing auth、live fake HTTP success。

## Verification

- Targeted test passed: 3 tests, 18 assertions.
- Full suite passed: 122 tests, 871 assertions.
