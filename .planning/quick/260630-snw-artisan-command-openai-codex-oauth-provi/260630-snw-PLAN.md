---
quick_id: 260630-snw
slug: artisan-command-openai-codex-oauth-provi
status: complete
created: 2026-06-30
---

# Quick Task 260630-snw: Codex OAuth Smoke Test Command

## Task

新增小型 Artisan command，用來測試 OpenAI Codex OAuth provider 設定、顯示 `auth.json` 候選路徑，並可選擇打一次 live smoke request。

## Plan

1. 讓 `CodexAuthCacheReader` 可安全提供候選 auth cache 路徑。
2. 新增 `ai:codex-oauth-test` console command。
3. 預設 dry-run 檢查 provider、model、base URL、auth cache 路徑與 token 存在性，不輸出 token。
4. 加上 `--live` 選項，透過 `AIReviewProvider` 發送最小 review request 並驗證回傳 JSON schema。
5. 加 feature tests 覆蓋 dry-run、missing auth safe failure、live mode fake HTTP success。

## Verification

- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=CodexOAuthSmokeCommandTest`
- `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test`
