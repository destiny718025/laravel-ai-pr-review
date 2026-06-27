# Phase 2: GitHub PR Ingestion - Context

**Gathered:** 2026-06-27T04:21:15Z
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 2 adds GitHub pull request ingestion for the existing review run workflow. It should fetch PR metadata and changed files through a fakeable GitHub client interface, store the changed-file data needed by later AI review phases, and mark review runs failed with safe, user-facing GitHub failure information when ingestion cannot complete.

This phase does not add AI analysis, queue execution, findings, comment drafts, comment publishing, webhooks, login, team workflows, or a full rule engine.

</domain>

<decisions>
## Implementation Decisions

### GitHub Access Scope
- **D-01:** Start with public GitHub pull requests only for Phase 2.
- **D-02:** Do not require a GitHub token for the first ingestion slice. Token/private repository support can be added later without changing the core fakeable client contract.
- **D-03:** Keep GitHub credentials out of the database and logs. If config keys are added, they must live in environment/config only.

### Ingestion Trigger
- **D-04:** Use a manual `Fetch` action for Phase 2 rather than automatically fetching GitHub data when a review run is created.
- **D-05:** The review run detail page is the natural place to expose the fetch action because Phase 1 already redirects successful submissions there.
- **D-06:** Phase 2 should not dispatch queued AI work. Queue-based review execution belongs to Phase 3.

### Stored Diff Data
- **D-07:** Store only the GitHub files API data needed for the next step: filename, patch, and sha.
- **D-08:** Do not parse patches into line/hunk targeting structures in Phase 2. Deeper comment-targeting normalization can be planned later when AI findings/drafts need exact line mapping.
- **D-09:** Preserve the raw patch string returned by GitHub files API in a database-backed model/repository so later phases can normalize or inspect it without calling GitHub again.

### GitHub Failure Behavior
- **D-10:** Distinguish GitHub failure categories with different safe error codes/messages instead of one generic failure.
- **D-11:** At minimum, planning should account for PR not found or unreadable, rate limit, token/auth failure if a token path exists, GitHub server/network failure, and malformed/unexpected GitHub responses.
- **D-12:** Failures should update the review run to `failed`, populate only `safe_error_message`, and avoid storing raw GitHub response bodies, headers, authorization values, or secrets.

### Test Fixture Strategy
- **D-13:** Use JSON fixture files for fake GitHub API responses.
- **D-14:** Fixtures should be reusable by later AI review tests, so keep them under a stable test fixture path and shape them close to GitHub API responses.
- **D-15:** Tests must fake GitHub responses and must not call the real GitHub API.

### the agent's Discretion
- Planner may decide exact class names, fixture directory names, migration/table names, and service method names, as long as Controller / Service / Repository layering is respected.
- Planner may decide whether the concrete public GitHub client uses Laravel HTTP client directly or a small wrapper, as long as application workflow depends on an interface and tests can fake it.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project and Requirements
- `.planning/PROJECT.md` — Product purpose, v1 constraints, no-login personal-use scope, manual approval safety, provider/interface constraints.
- `.planning/REQUIREMENTS.md` — Phase 2 requirements: `ARCH-05`, `GH-02`, `GH-03`, `GH-04`, `GH-05`, `GH-06`.
- `.planning/ROADMAP.md` — Phase 2 scope and success criteria.
- `.planning/STATE.md` — Current project position and accumulated decisions.

### Phase 1 Foundation
- `.planning/phases/01-review-run-foundation-and-management-ui/01-01-SUMMARY.md` — Review run schema, status enum, pull request/repository model foundation.
- `.planning/phases/01-review-run-foundation-and-management-ui/01-02-SUMMARY.md` — PR URL parser, DTOs, service, and repository-layer creation workflow.
- `.planning/phases/01-review-run-foundation-and-management-ui/01-03-SUMMARY.md` — Controller-backed `/reviews` routes and detail page entry point.
- `.planning/phases/01-review-run-foundation-and-management-ui/01-04-SUMMARY.md` — Repository-backed history/detail and safe failed-run display behavior.
- `.planning/phases/01-review-run-foundation-and-management-ui/01-UAT.md` — User acceptance confirmation for the Phase 1 workflow.

### Codebase Maps
- `.planning/codebase/STACK.md` — Laravel 13, PHP runtime, queue/test/development command context.
- `.planning/codebase/INTEGRATIONS.md` — Existing service config and missing GitHub integration notes.
- `.planning/codebase/ARCHITECTURE.md` — Laravel architecture baseline and planned services/jobs/data object locations.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `app/Models/GitHubRepository.php`, `app/Models/PullRequest.php`, `app/Models/ReviewRun.php` — Existing persisted identity and review run foundation for attaching ingested GitHub data.
- `app/Enums/ReviewRunStatus.php` — Existing `failed` status and reserved lifecycle states for ingestion success/failure transitions.
- `app/Repositories/ReviewRunRepository.php` — Existing repository boundary for pending creation, recent history, and detail lookup; should be extended rather than bypassed.
- `app/Services/ReviewRunService.php` — Existing business workflow style: services orchestrate repositories and return stable result objects.
- `resources/views/reviews/show.blade.php` — Existing detail shell where a manual `Fetch` action can appear without introducing a new dashboard concept.

### Established Patterns
- Controllers handle HTTP validation, redirects, flash state, and view responses only.
- Services own business workflow and should not own raw database query details.
- Repositories own Eloquent reads and writes.
- `app/Data` is the accepted home for DTO/value-object style cross-layer data.
- Expected user input failures use stable error codes/messages and avoid exception-driven control flow.

### Integration Points
- `config/services.php` is the right place for future GitHub API configuration, but Phase 2 starts public-only and may not need a token.
- Laravel's HTTP client can be used by a concrete GitHub client, but the review workflow must depend on a fakeable interface.
- Existing feature tests use `RefreshDatabase`; GitHub tests should use fakes/fixtures and no network.
- Docker/Laradock container execution is required for PHP verification in this environment.

</code_context>

<specifics>
## Specific Ideas

- The user explicitly wants Phase 2 public-only first.
- The user explicitly wants a manual `Fetch` action.
- The user explicitly wants filename, patch, and sha from GitHub files API, without deeper hunk/line normalization in this phase.
- The user explicitly wants different GitHub failure categories.
- The user explicitly wants JSON fixture files for fake GitHub responses.

</specifics>

<deferred>
## Deferred Ideas

- Private repository support and required GitHub token setup are deferred.
- Automatic ingestion immediately after review run creation is deferred.
- Patch-to-hunk/line targeting normalization is deferred beyond Phase 2.
- Queue-based review execution remains Phase 3.
- Webhook-triggered review runs remain out of scope for v1 manual workflow validation.

</deferred>

---

*Phase: 2-GitHub PR Ingestion*
*Context gathered: 2026-06-27T04:21:15Z*
