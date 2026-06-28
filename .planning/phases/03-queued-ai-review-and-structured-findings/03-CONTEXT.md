# Phase 3: Queued AI Review and Structured Findings - Context

**Gathered:** 2026-06-27T23:43:12Z
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 3 turns an existing review run with fetched GitHub PR snapshot data into an asynchronously executed AI review. It should enqueue review work through Laravel queues, run the review through a fakeable AI provider interface, validate structured AI output, persist review findings, and transition the review run through safe lifecycle states.

This phase does not create editable comment drafts, approve comments, publish comments to GitHub, add custom instruction management UI, add team workflows, add authentication, or add webhook automation. Draft editing belongs to Phase 4, and GitHub comment publishing belongs to Phase 5.

</domain>

<decisions>
## Implementation Decisions

### Review Trigger
- **D-01:** Use a manual `Run AI Review` action for Phase 3 instead of automatically starting AI review immediately after GitHub `Fetch`.
- **D-02:** The review run detail page is the natural place for the manual `Run AI Review` action because it already shows review run identity, GitHub fetch status, safe failure state, and fetched file snapshot data.
- **D-03:** Planning should prevent AI review execution before the review run has GitHub snapshot data. The planner may decide whether the UI blocks the action, the service returns a safe validation error, or both.
- **D-04:** Phase 3 review execution must use Laravel queues. The HTTP request should enqueue work and return quickly instead of calling the AI provider inline.

### AI Provider Strategy
- **D-05:** Build the AI review provider interface and fake provider first. Tests should rely on the fake provider for deterministic local behavior.
- **D-06:** Reserve configuration for a future OpenAI adapter in `config/services.php` / environment variables, but do not make the Phase 3 MVP depend on live OpenAI calls.
- **D-07:** The concrete OpenAI adapter can be planned as a seam or stub if useful, but provider selection must remain behind an interface so the core review workflow does not depend directly on one vendor.
- **D-08:** External AI calls and provider payloads must be fakeable in tests and must not require network access.

### Structured Findings
- **D-09:** Persist structured review findings in Phase 3.
- **D-10:** Findings should include at least severity, category, file path, line reference when available, title, rationale, and suggested comment text.
- **D-11:** Phase 3 should include `suggested_comment_text` on findings because it is useful review output and prepares Phase 4, but it must not create editable comment draft records yet.
- **D-12:** Comment drafts, draft approval, draft status, and draft editing remain Phase 4 responsibilities.
- **D-13:** Default review instructions should prioritize bugs and security issues first, while allowing Laravel/PHP style feedback when useful and not noisy.

### Failure, Retry, and Safety
- **D-14:** Support safe AI review failure states for timeout/transport errors, invalid provider output, schema validation failure, and unexpected runtime failures.
- **D-15:** Failure messages shown or persisted on `review_runs.safe_error_message` must be safe summaries and must not include API keys, authorization headers, raw provider payloads, or unredacted secrets.
- **D-16:** Allow the same review run to be retried manually after an AI review failure.
- **D-17:** A successful retry should clear prior safe failure state and persist fresh findings for the current GitHub snapshot.
- **D-18:** Planning should define what happens to prior findings on retry. Preferred MVP behavior is replacing findings for the review run so the detail page reflects the latest execution attempt.

### the agent's Discretion
- Planner may decide exact class names, migration names, service method names, job names, and route names as long as Controller / Service / Repository layering is respected.
- Planner may choose whether review execution state transitions live in `ReviewRunRepository` or a dedicated execution repository, but database writes must stay in repository classes.
- Planner may choose the exact validation mechanism for AI output, provided invalid or incomplete output fails safely without malformed findings.
- Planner may decide whether to include a minimal OpenAI adapter stub in Phase 3 or leave it for a later AI integration pass, as long as config is reserved and the fake provider path is complete.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project and Requirements
- `.planning/PROJECT.md` — Product purpose, v1 constraints, queueing requirement, AI provider interface requirement, no-login personal-use scope, manual approval safety, and Controller / Service / Repository architecture.
- `.planning/REQUIREMENTS.md` — Phase 3 requirements: `EXEC-01`, `EXEC-02`, `EXEC-03`, `EXEC-04`, `EXEC-05`, `AI-01`, `AI-02`, `AI-03`, `AI-04`, `AI-05`, `AI-06`, `AI-07`, `AI-08`.
- `.planning/ROADMAP.md` — Phase 3 goal, dependencies, success criteria, and boundary from later draft/publishing phases.
- `.planning/STATE.md` — Current project position and accumulated decisions.

### Prior Phase Context
- `.planning/phases/02-github-pr-ingestion/02-CONTEXT.md` — Phase 2 decisions that GitHub fetch is manual, queue-based review execution belongs to Phase 3, and raw `filename` / `patch` / `sha` snapshots plus PR head SHA are available for later review work.
- `.planning/phases/02-github-pr-ingestion/02-VERIFICATION.md` — Verified Phase 2 behavior and accepted GH-04 scope: raw file snapshots only, line/side derivation deferred.
- `.planning/phases/02-github-pr-ingestion/02-01-SUMMARY.md` — GitHub client boundary and fixture strategy.
- `.planning/phases/02-github-pr-ingestion/02-02-SUMMARY.md` — Manual fetch workflow and persisted PR/file snapshot shape.
- `.planning/phases/02-github-pr-ingestion/02-03-SUMMARY.md` — Safe GitHub failure handling behavior.

### Codebase Maps
- `.planning/codebase/ARCHITECTURE.md` — Laravel architecture baseline, planned jobs/services/data object locations, and queue readiness.
- `.planning/codebase/INTEGRATIONS.md` — Queue configuration, missing AI provider integration, and service config notes.
- `.planning/codebase/TESTING.md` — PHPUnit setup, synchronous queue test environment, and fake external service guidance.
- `.planning/codebase/STACK.md` — Laravel 13, PHP runtime, queue/test/development command context.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Models/ReviewRun.php` — Existing review run model already has lifecycle timestamps (`queued_at`, `started_at`, `completed_at`, `failed_at`) and `safe_error_message`.
- `app/Enums/ReviewRunStatus.php` — Existing lifecycle statuses include `pending`, `queued`, `running`, `completed`, `failed`, and `cancelled`; Phase 3 should use these instead of inventing new status strings.
- `app/Models/ReviewRunFile.php` — Existing fetched file snapshots provide `filename`, `patch`, and `sha` for AI review input.
- `app/Repositories/ReviewRunRepository.php` — Existing repository boundary for review-run reads/writes and GitHub snapshot/failure updates; should be extended or complemented for execution transitions.
- `app/Services/PullRequestIngestionService.php` — Existing service style for orchestration and safe result objects.
- `app/Contracts/GitHub/GitHubClient.php` and `app/Services/GitHub/HttpGitHubClient.php` — Existing provider-boundary pattern to mirror for AI provider design.
- `resources/views/reviews/show.blade.php` — Existing review detail page where `Run AI Review`, execution status, findings, and safe failure messages can appear.
- `tests/Fixtures/GitHub/` — Existing fixture strategy; Phase 3 can add fake AI provider payload fixtures if useful.

### Established Patterns
- Controllers should handle HTTP concerns only, then delegate to services.
- Services should own business workflow orchestration.
- Repositories should own Eloquent reads/writes and status transitions.
- `app/Data` is the accepted home for DTO/value-object style cross-layer data.
- External service calls should sit behind contracts and be fakeable in tests.
- Safe errors use stable codes/messages and avoid raw provider or secret leakage.
- PHP/artisan/composer verification commands must run inside the Docker workspace container in this environment.

### Integration Points
- Laravel database queues are already configured and the jobs table migration exists.
- Test configuration uses `QUEUE_CONNECTION=sync`, so queued review jobs can be exercised deterministically in feature tests.
- `config/services.php` is the correct home for AI provider keys/model/config; application services should not call `env()` directly.
- Phase 3 should connect to the existing review detail page and existing review run status vocabulary rather than introducing a separate workflow shell.

</code_context>

<specifics>
## Specific Ideas

- The user explicitly wants manual `Run AI Review` for Phase 3.
- The user explicitly wants fake provider first.
- The user explicitly wants OpenAI adapter configuration reserved.
- The user explicitly wants findings to include `suggested_comment_text`.
- The user explicitly does not want Phase 3 to create comment drafts.
- The user explicitly wants safe failure handling and retry support.

</specifics>

<deferred>
## Deferred Ideas

- Editable comment draft records and draft approval remain Phase 4.
- Posting comments back to GitHub remains Phase 5.
- Custom instructions management UI remains Phase 4.
- Real provider live-call verification can wait until after the fake provider workflow is stable.
- GitHub webhook automation remains out of scope for v1 manual workflow validation.

</deferred>

---

*Phase: 3-Queued AI Review and Structured Findings*
*Context gathered: 2026-06-27T23:43:12Z*
