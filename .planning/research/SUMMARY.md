# Project Research Summary

**Project:** Laravel AI PR Review
**Domain:** AI-assisted GitHub pull request review tool
**Researched:** 2026-06-26
**Confidence:** HIGH

## Executive Summary

Laravel AI PR Review should start as a queue-backed Laravel web app that turns a manually submitted GitHub PR URL into persisted review runs, structured findings, and editable comment drafts. Research strongly supports the user's chosen direction: keep controllers thin, put business workflows in services, isolate database access in repositories, and place slow external work behind queued jobs.

The highest-risk area is not the UI; it is correctness and trust around generated comments. GitHub review comments require precise file, commit, line, and side metadata, and GitHub marks the older `position` field as closing down. AI output also needs schema enforcement and server-side validation because free-form JSON is not enough for reliable persistence or publishing.

The roadmap should validate the manual PR URL workflow first, then add AI provider output and draft review, then publish approved comments, and only later add webhooks/team/SaaS features. This avoids building automation before the review loop is trustworthy.

## Key Findings

### Recommended Stack

Use the current Laravel 13 stack. No extra package is required before the first implementation phase.

**Core technologies:**
- Laravel queues: async review execution so controllers return quickly
- Laravel HTTP client: GitHub and AI API calls with first-class test fakes
- SQLite/database queue: good enough for personal/local MVP
- Blade/Vite/Tailwind: simple management UI without SPA overhead
- AI provider interface: keeps OpenAI/Anthropic/provider choice isolated

### Expected Features

**Must have (table stakes):**
- Manual PR URL submission
- Review run persistence and status tracking
- GitHub PR file/diff ingestion
- Queued AI review execution
- Structured findings
- Comment drafts
- Review history and detail pages
- Manual approval before GitHub posting
- Simple custom instructions textarea

**Should have (competitive):**
- Finding-to-draft traceability
- Provider interface and fake provider
- Clear failure diagnostics
- Laravel/PHP-aware review instructions

**Defer (v2+):**
- GitHub webhook automation
- Auth/team permissions
- Organization rule engine
- Billing/SaaS tenant management
- Multi-provider UI

### Architecture Approach

The architecture should be a Laravel monolith with explicit Controller / Service / Repository boundaries. Controllers validate and return views; services orchestrate workflows; repositories own database access; jobs provide asynchronous boundaries; GitHub and AI providers are accessed through interfaces so tests can fake them.

**Major components:**
1. Review run UI/controllers — submit PR URLs, display history/detail
2. Review services — create, execute, and publish review workflows
3. Repositories — persist runs, findings, drafts, and settings
4. GitHub client — fetch PR files and post approved comments
5. AI provider interface — produce structured findings and drafts
6. Queue job — execute review asynchronously

### Critical Pitfalls

1. **Auto-posting noisy AI comments** — keep draft-first manual approval
2. **Using fragile GitHub diff positions** — store `line`, `side`, path, and commit SHA
3. **Blocking controller requests on AI work** — dispatch queued jobs
4. **Trusting raw AI JSON** — use structured output/schema and validate server-side
5. **Leaking tokens or private code** — redact logs and avoid raw payload persistence by default

## Implications for Roadmap

### Phase 1: Review Run Foundation and Management UI

**Rationale:** The app needs a place to submit PR URLs and track state before external integrations become complex.

**Delivers:** Basic web UI, review run model/repository/service, URL validation, history page, detail page shell.

**Addresses:** Manual PR URL submission, persisted review runs, Controller / Service / Repository pattern.

**Avoids:** Fat controller and invisible failures.

### Phase 2: GitHub PR Ingestion

**Rationale:** AI review quality depends on normalized GitHub PR file data.

**Delivers:** GitHub client interface, HTTP implementation, PR file fetching, diff metadata normalization, faked tests.

**Uses:** GitHub list PR files endpoint.

**Avoids:** Fragile comment mapping and real API calls in tests.

### Phase 3: AI Review Provider and Structured Findings

**Rationale:** Structured findings are needed before the UI can show actionable review output.

**Delivers:** AI provider interface, fake provider, one real provider implementation, review schema, findings persistence.

**Uses:** OpenAI Structured Outputs or equivalent provider schema behavior.

**Avoids:** Raw AI JSON trust and provider lock-in.

### Phase 4: Comment Draft Review and Custom Instructions

**Rationale:** User control and rule tuning are core product decisions.

**Delivers:** Comment draft model/repository/UI, custom instructions textarea, edit/approve flow.

**Addresses:** Draft-first UX and simple rule management.

**Avoids:** Noisy comments and premature rule engine complexity.

### Phase 5: GitHub Comment Publishing

**Rationale:** Publishing should happen only after drafts and line metadata are reliable.

**Delivers:** Approved draft publishing, per-draft status, error handling, GitHub write tests.

**Uses:** GitHub create review comment endpoint.

**Avoids:** Auto-posting and partial failure ambiguity.

### Phase 6: Webhook Automation and Hardening

**Rationale:** Webhooks are useful after the manual workflow is trustworthy.

**Delivers:** Webhook route, signature validation, idempotency, background enqueueing.

**Uses:** GitHub webhook validation guidance.

**Avoids:** Forged webhook triggers and duplicate review runs.

### Phase Ordering Rationale

- The management UI and persistence come before integrations so failures are visible.
- GitHub ingestion comes before AI because line/path metadata shapes the AI output schema.
- AI findings come before comment publishing because drafts must be reviewed and approved.
- Webhooks come after manual review because automation should not lead the product.

### Research Flags

Phases likely needing deeper research during planning:
- **Phase 2:** GitHub diff edge cases, pagination, and line mapping
- **Phase 3:** Provider-specific structured output and failure handling
- **Phase 5:** GitHub review comment validation and rate/secondary rate limits
- **Phase 6:** Webhook idempotency and token model

Phases with standard patterns:
- **Phase 1:** Laravel CRUD/service/repository UI patterns are standard
- **Phase 4:** Draft review UI is product-specific but technically straightforward

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Current Laravel stack is already installed and official docs support queue/HTTP/testing needs |
| Features | MEDIUM-HIGH | Strong alignment between user goals, GitHub API constraints, and AI review research |
| Architecture | HIGH | Controller / Service / Repository fits user preference and Laravel monolith shape |
| Pitfalls | HIGH | GitHub/API/AI failure modes are well documented or backed by empirical research |

**Overall confidence:** HIGH

### Gaps to Address

- **AI provider choice:** Pick concrete first provider during implementation planning, not project initialization.
- **Private repo auth model:** Start with token-based config for MVP, later evaluate GitHub App installation flow.
- **Diff-to-comment mapping:** Needs focused tests with realistic fixtures before publishing comments.
- **Large PR handling:** Define v1 limits and failure messages.

## Sources

### Primary (HIGH confidence)

- GitHub PR files endpoint: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28#list-pull-requests-files
- GitHub review comments endpoint: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28#create-a-review-comment-for-a-pull-request
- GitHub webhook validation: https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries
- Laravel queues: https://laravel.com/docs/13.x/queues
- Laravel HTTP client testing: https://laravel.com/docs/13.x/http-client#testing
- Laravel database testing: https://laravel.com/docs/13.x/database-testing
- OpenAI Structured Outputs: https://developers.openai.com/api/docs/guides/structured-outputs
- Anthropic tool definitions: https://platform.claude.com/docs/en/agents-and-tools/tool-use/define-tools

### Secondary (MEDIUM confidence)

- SWE-PRBench: https://arxiv.org/abs/2603.26130
- AI code review impact case study: https://arxiv.org/abs/2508.18771
- GitHub Copilot security review paper: https://arxiv.org/abs/2509.13650

---
*Research completed: 2026-06-26*
*Ready for roadmap: yes*
