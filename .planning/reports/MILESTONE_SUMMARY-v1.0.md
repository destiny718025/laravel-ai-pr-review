# Milestone v1.0 - Project Summary

**Generated:** 2026-06-30
**Purpose:** Team onboarding and project review
**Project:** Laravel AI PR Review

---

## 1. Project Overview

Laravel AI PR Review is a personal-use Laravel web application for running AI-assisted code review on GitHub pull requests. The v1 milestone turns a GitHub PR URL into a persisted review run, fetches GitHub PR metadata and changed-file snapshots, runs queued AI review, stores structured findings, generates editable comment drafts, and publishes only explicitly approved drafts back to GitHub.

The core value of v1 is a safe manual review workflow: the AI can help identify bug/security issues and prepare GitHub-ready review comments, but the user stays in control before anything is posted.

The completed MVP supports:

- No-login local management interface for personal use.
- Manual GitHub PR URL submission.
- GitHub PR metadata and changed-file snapshot ingestion.
- Queued AI review through a provider interface.
- Structured finding validation and safe failure handling.
- Editable comment drafts, local approval, and stale-draft handling on retry.
- Global custom review instructions.
- GitHub publication for approved drafts only, with per-draft posted/failed status.
- Explicit AI provider selection, including fake, OpenAI API-key, and OpenAI Codex OAuth provider paths.

Active post-v1 product questions are real-PR validation, webhook automation, richer rules, review history improvements, authentication/team workflow, and private repository token handling.

## 2. Architecture & Technical Decisions

- **Decision:** Use Laravel 13, PHP 8.3, SQLite-first persistence, and Laravel queues.
  - **Why:** The project is a local/private MVP, and Laravel already provides database, queue, test, and Blade primitives with low setup cost.
  - **Phase:** Foundation / Phase 1

- **Decision:** Use Controller / Service / Repository layering.
  - **Why:** Controllers stay focused on HTTP validation, redirects, sessions, and views; services own business workflow; repositories own database reads and writes.
  - **Phase:** All phases

- **Decision:** Start with manual PR URL submission instead of webhooks.
  - **Why:** Manual review validates the core workflow before adding signature validation, delivery deduplication, and automation complexity.
  - **Phase:** Phase 1

- **Decision:** Store review runs, PR snapshots, findings, drafts, and publication outcomes.
  - **Why:** The user needs history, retry behavior, review detail pages, and auditability instead of one-shot transient output.
  - **Phase:** Phases 1-5

- **Decision:** Keep GitHub and AI integrations behind interfaces.
  - **Why:** Tests must fake external calls, and the app should not be tightly coupled to one provider implementation.
  - **Phase:** Phases 2, 3, 5, 6

- **Decision:** Store only GitHub files API fields needed for the first diff snapshot: `filename`, `patch`, `sha`, plus PR `head_sha` on the review run.
  - **Why:** Phase 2 intentionally deferred full hunk/line normalization until draft targeting and publication needed it.
  - **Phase:** Phase 2

- **Decision:** Run AI review through Laravel jobs.
  - **Why:** AI and GitHub work should not block the HTTP request, and failures need persisted lifecycle state.
  - **Phase:** Phase 3

- **Decision:** Validate structured AI output before persisting findings.
  - **Why:** Invalid JSON, incomplete schema, or unsupported values should fail safely without malformed findings.
  - **Phase:** Phase 3

- **Decision:** Generate drafts before publication and require manual approval.
  - **Why:** AI-generated comments should not be posted automatically; trust comes from human review.
  - **Phase:** Phases 4-5

- **Decision:** Preserve finding/draft provenance on retry.
  - **Why:** Retry should supersede current findings and mark existing drafts stale instead of deleting useful history.
  - **Phase:** Phase 4

- **Decision:** Keep custom instructions simple and global in v1.
  - **Why:** A textarea validates rule usefulness without building a full named rule engine.
  - **Phase:** Phase 4

- **Decision:** Publish line-level comments only when targeting metadata is sufficient, otherwise fall back to issue comments.
  - **Why:** GitHub write safety matters more than forcing a fragile line target.
  - **Phase:** Phase 5

- **Decision:** Add explicit `AI_PROVIDER` selection and isolate Codex OAuth auth-cache reuse behind a read-only reader.
  - **Why:** Provider behavior should be deliberate, fake remains deterministic by default, and local Codex credentials should not be stored in Laravel.
  - **Phase:** Phase 6

## 3. Phases Delivered

| Phase | Name | Status | One-Liner |
| --- | --- | --- | --- |
| 1 | Review Run Foundation and Management UI | Complete in roadmap; GSD progress metadata says executed/no verification file | User can submit PR URLs and see persisted review run status/history. |
| 2 | GitHub PR Ingestion | Complete / verified passed | System fetches PR metadata/files and stores replayable diff snapshots. |
| 3 | Queued AI Review and Structured Findings | Complete / verified passed | System runs queued AI review through a provider interface and persists validated findings. |
| 4 | Draft Review and Custom Instructions | Complete in roadmap; GSD progress metadata says executed/no verification file | User can inspect findings, edit drafts, approve drafts, and tune simple instructions. |
| 5 | GitHub Comment Publishing | Complete / verified passed | User can publish approved drafts to GitHub with per-draft status and safe error handling. |
| 6 | OpenAI Codex OAuth AI Provider | Complete / verified passed | Queued AI review can use a Codex OAuth provider path backed by local Codex CLI auth cache. |

Phase artifact counts:

- Plans completed: 23 / 23
- Phase summaries found: 23
- Verification reports found: Phase 2, Phase 3, Phase 5, Phase 6
- Research/context artifacts found across all phases

## 4. Requirements Coverage

Roadmap and implementation artifacts show the v1 manual workflow is complete. `STATE.md` records v1.0 at 100% with 6 / 6 phases and 23 / 23 plans complete.

Satisfied requirement groups:

- **RUN:** Review run creation, URL validation, history, detail pages, statuses, and safe failure messages are implemented.
- **ARCH:** Controller / Service / Repository layering and fakeable external boundaries are implemented.
- **GH:** GitHub PR URL parsing, metadata fetch, changed-file snapshot persistence, safe GitHub failure handling, and fake HTTP tests are implemented.
- **EXEC:** Review work is queued, status transitions are persisted, failures are safe, and credentials/provider payloads are not persisted as raw failure text.
- **AI:** AI provider interface, fake provider, OpenAI API-key adapter seam, Codex OAuth provider, structured validation, default instructions, and safe invalid-output handling are implemented.
- **DRAFT:** Findings and drafts are persisted; drafts can be generated, viewed, edited, approved/unapproved, marked stale, and tracked through draft/approved/posted/failed states.
- **RULE:** Global custom instructions can be viewed, saved, stored separately, and included in future AI review requests.
- **PUB:** Approved drafts can be published to GitHub through a fakeable client, with posted/failed status and safe failure messages.

Known documentation drift:

- `.planning/REQUIREMENTS.md` still marks `DRAFT-02` through `DRAFT-05` and `RULE-01` through `RULE-04` as pending in its checklist/traceability table.
- Phase 4 summaries and `PROJECT.md` indicate those behaviors were implemented.
- `init.progress` reports Phase 1 and Phase 4 as `executed` instead of `complete` because no dedicated verification report file exists for those phases, while `ROADMAP.md` and `STATE.md` mark them complete.

This is a planning metadata cleanup item, not a currently observed product behavior gap.

## 5. Key Decisions Log

| ID | Decision | Phase | Rationale |
| --- | --- | --- | --- |
| D-01 | No-login personal management UI first | Phase 1 | First user is the project owner; authentication is deferred until the workflow proves useful. |
| D-02 | Manual PR URL trigger first | Phase 1 | Avoid webhook complexity while validating the end-to-end review experience. |
| D-03 | Persist review runs with repository and pull request identity | Phase 1 | Enables history, status pages, retries, and future workflow automation. |
| D-04 | Keep HTTP controllers thin | Phase 1+ | HTTP code should not own business logic or direct database writes. |
| D-05 | GitHub client interface with HTTP-faked tests | Phase 2 | Prevents brittle or costly tests and keeps external integration replaceable. |
| D-06 | Store replayable changed-file snapshots | Phase 2 | Later phases need stable diff input for AI review and comment targeting. |
| D-07 | Defer full line/side normalization | Phase 2 | Raw file API data was enough for the ingestion phase; targeting could be refined near publishing. |
| D-08 | Queued review execution | Phase 3 | External review work should be asynchronous and lifecycle-aware. |
| D-09 | Fake provider default | Phase 3 | Local and CI tests remain deterministic without AI cost or network dependency. |
| D-10 | Structured finding schema | Phase 3 | Findings need stable fields for UI, drafts, and GitHub comment text. |
| D-11 | Drafts before publication | Phase 4 | User approval is required before writing AI-generated comments to GitHub. |
| D-12 | Retry supersedes findings and marks drafts stale | Phase 4 | Historical provenance survives without confusing current and old review output. |
| D-13 | Global custom instructions | Phase 4 | Simple rule customization is enough for v1 and avoids a premature rule engine. |
| D-14 | Separate publish-approved and retry-failed workflows | Phase 5 | Status filtering remains explicit and safer than mixed mutation behavior. |
| D-15 | Fallback issue comments when line target is insufficient | Phase 5 | Safer GitHub publication beats fragile line-level targeting. |
| D-16 | Explicit `AI_PROVIDER` selector | Phase 6 | Avoids hidden fallback behavior and makes provider choice auditable. |
| D-17 | Codex auth-cache read-only reuse | Phase 6 | The app can use local Codex OAuth credentials without storing tokens in Laravel. |
| D-18 | Provider-agnostic execution service | Phase 6 | Codex should pass through the existing provider/decode/validate boundary without special workflow branches. |

## 6. Tech Debt & Deferred Items

Deferred product scope:

- GitHub webhook trigger, signature validation, delivery deduplication, and automatic enqueueing.
- Authentication, team permissions, and shared team history.
- Full named rule sets, repository-specific rules, category toggles, and rule versioning.
- Production operations such as queue monitoring, cost/token usage tracking, tenant-aware settings, and billing.
- Multi-provider management UI.
- GitLab/Bitbucket support.

Technical follow-up items:

- Clean up `.planning/REQUIREMENTS.md` traceability for Phase 4 draft/rule items.
- Decide whether to add retroactive verification reports for Phase 1 and Phase 4 so `init.progress` aligns with `STATE.md` and `ROADMAP.md`.
- Validate the full workflow against real public PRs before expanding automation.
- Revisit private repository token handling before moving beyond local/private personal use.
- Confirm line-level GitHub comment targeting quality on real-world diffs.

## 7. Getting Started

Run project setup:

```bash
composer run setup
```

Run the local dev stack:

```bash
composer run dev
```

Run tests in this workspace's Docker environment:

```bash
docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test
```

Core code entry points:

- `routes/web.php` - Review, fetch, run, draft, settings, and publication routes.
- `app/Http/Controllers/` - Thin web controllers.
- `app/Services/` - Business workflow orchestration.
- `app/Repositories/` - Eloquent/database persistence boundaries.
- `app/Contracts/` - GitHub and AI integration interfaces.
- `app/Data/` - DTOs and normalized cross-layer payloads.
- `app/Enums/` - Review run and draft status vocabularies.
- `resources/views/reviews/` - Management UI.
- `tests/Feature/` and `tests/Unit/` - Offline behavior coverage.

Important environment/config values:

- `AI_PROVIDER=fake` - deterministic default provider.
- `AI_PROVIDER=openai_api_key` - OpenAI API-key provider path.
- `AI_PROVIDER=openai_codex_oauth` - Codex OAuth provider path using local Codex auth cache.
- `GITHUB_TOKEN` - GitHub API access for fetch/publish flows.
- `OPENAI_API_KEY` - OpenAI API-key provider credential.
- `CODEX_AUTH_PATH` / `CODEX_HOME` - Optional Codex auth-cache path controls.

Recommended first read for a new contributor:

1. `.planning/PROJECT.md`
2. `.planning/ROADMAP.md`
3. `app/Services/ReviewExecutionService.php`
4. `app/Services/ReviewCommentPublishingService.php`
5. `resources/views/reviews/show.blade.php`

---

## Stats

- **Timeline:** 2026-06-26 -> 2026-06-30
- **Phases:** 6 / 6 roadmap phases complete
- **Plans:** 23 / 23 plans complete
- **Commits:** 88 since 2026-06-26
- **Files changed:** 262 files
- **Insertions:** 37,429
- **Deletions:** 1,085
- **Contributors:** Tang
- **Latest full regression gate:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` - 119 passed, 853 assertions
