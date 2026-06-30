# Laravel AI PR Review

Laravel AI PR Review 是一個個人使用的 Laravel Web 工具，用來對 GitHub Pull Request 執行 AI 輔助 code review。

目前 v1.0 已完成一條手動審查流程：輸入 GitHub PR URL、抓取 PR metadata 與 changed files、透過佇列執行 AI review、產生結構化 findings 與可編輯的 comment drafts，最後由使用者手動核准後才發布到 GitHub。

## 核心價值

把一個 GitHub PR URL 轉換成可檢查、可編輯、可發布的 AI review findings 與 GitHub comment drafts，協助在 merge 前找出 bug、安全性問題與值得注意的程式碼風險。

## 目前功能

- 無登入的本機管理介面，適合個人或私有環境使用。
- 可從管理介面建立 review run。
- 可解析 GitHub PR URL，並保存 owner、repository、PR number 與 source URL。
- 可透過 GitHub API 取得 PR metadata 與 changed file snapshots。
- changed files 會保存 `filename`、`patch`、`sha`，並在 review run 上保存 PR `head_sha`。
- AI review 透過 Laravel queue 執行，不阻塞 HTTP request。
- AI provider 使用 interface 抽象，測試可使用 fake provider。
- AI findings 會經過 schema validation 後才保存。
- findings 包含 severity、category、file path、line reference、title、rationale、suggested comment text。
- 可從 findings 產生 comment drafts。
- comment drafts 可編輯、核准、取消核准，並在 retry 後標記 stale。
- 支援全域 custom review instructions，後續 AI review 會帶入最新設定。
- 只發布已核准 drafts 到 GitHub。
- 每個 draft 會追蹤 draft、approved、posted、failed 狀態。
- GitHub 發布失敗會保存安全摘要，不保存 raw token、header 或 provider payload。
- 支援 AI provider selector：`fake`、`openai_api_key`、`openai_codex_oauth`。

## 技術架構

專案採用 Laravel 13 與 PHP 8.3，v1.0 以 SQLite-first 的本機 MVP 為主。

主要架構規則：

- Controller 處理 HTTP request、validation、redirect、view response。
- Service 處理業務流程，例如建立 review run、fetch PR、執行 AI review、發布 comments。
- Repository 處理資料庫讀寫。
- GitHub 與 AI provider 都藏在 interface 後面，方便測試 fake 與未來替換 provider。
- 長時間工作使用 Laravel queue，避免在 HTTP request 中直接跑 AI 或外部 API 工作。

常見目錄：

- `app/Http/Controllers/`：Web controllers。
- `app/Services/`：核心業務流程。
- `app/Repositories/`：資料庫存取。
- `app/Contracts/`：GitHub 與 AI interface。
- `app/Data/`：DTO 與跨層資料物件。
- `app/Enums/`：review run 與 draft 狀態。
- `resources/views/reviews/`：管理介面 Blade views。
- `tests/Feature/`、`tests/Unit/`：功能測試與單元測試。

## AI Provider 設定

透過 `.env` 的 `AI_PROVIDER` 選擇使用哪個 AI provider：

```env
AI_PROVIDER=fake
```

可用值：

- `fake`：預設值，使用固定假資料，適合本機開發與測試。
- `openai_api_key`：使用 OpenAI API key 的 provider。
- `openai_codex_oauth`：使用本機 Codex CLI auth cache 的 provider。

相關環境變數：

```env
GITHUB_TOKEN=
OPENAI_API_KEY=
CODEX_AUTH_PATH=
CODEX_HOME=
```

安全注意事項：

- 不要把 GitHub token、OpenAI API key、Codex auth cache 內容 commit 進 repository。
- 不要在 service 中直接使用 `env()`；應透過 Laravel config 讀取。
- AI 與 GitHub 失敗訊息只保存安全摘要，不保存 raw response、authorization header 或 token。

## 安裝與啟動

安裝依賴、建立 `.env`、產生 app key、執行 migration、安裝前端套件並 build assets：

```bash
composer run setup
```

啟動開發環境：

```bash
composer run dev
```

`composer run dev` 會同時啟動 Laravel server、queue listener、log viewer 與 Vite。

## 測試

一般測試指令：

```bash
composer run test
```

在目前這個工作環境中，PHP / Artisan / Composer 指令需要在容器內執行：

```bash
docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test
```

最近一次完整回歸測試結果：

```text
119 passed, 853 assertions
```

## 使用流程

1. 開啟管理介面。
2. 輸入 GitHub PR URL 建立 review run。
3. 在 review detail 頁面 fetch GitHub PR metadata 與 changed files。
4. 點擊 Run AI Review，系統會派送 queued job。
5. AI review 完成後查看 structured findings。
6. 從 findings 產生 comment drafts。
7. 編輯 drafts，核准想發布的 comments。
8. 點擊 Publish Approved，把已核准 drafts 發布到 GitHub。
9. 若發布失敗，可查看安全錯誤摘要並 retry failed drafts。

## v1.0 狀態

v1.0 manual review workflow 已完成：

- 6 / 6 phases complete。
- 23 / 23 plans complete。
- GitHub PR ingestion、queued AI review、draft approval、GitHub publishing、Codex OAuth provider path 都已完成。

更完整的里程碑摘要可查看：

```text
.planning/reports/MILESTONE_SUMMARY-v1.0.md
```

## 後續方向

可能的下一階段：

- 使用真實 public PR 驗證完整流程。
- 支援 GitHub webhook trigger、signature validation 與 idempotency。
- 加入登入、team workflow 與權限管理。
- 擴充 named rule sets、repository-specific rules 與規則版本管理。
- 改善 review history、搜尋與統計。
- 強化 private repository token 管理。
- 評估更多 AI provider 與 provider 管理介面。

## License

此專案目前作為個人使用的 Laravel AI PR review MVP。若要公開或商用，請先補齊授權、資安與部署相關文件。
