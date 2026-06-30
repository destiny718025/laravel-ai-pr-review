# Phase 05: GitHub Comment Publishing - Context

**Gathered:** 2026-06-29T00:32:24+08:00
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 05 publishes already-approved comment drafts to the original GitHub pull request and records the result per draft. The user can publish all approved drafts from the review run detail page, retry failed draft publication later, and inspect posted/failed status on each draft.

This phase does not automate publishing without approval, update already-posted GitHub comments, add webhook automation, introduce authentication/team permissions, support other git providers, or create a broader rule engine. GitHub token handling remains environment/config only and publication must remain behind the fakeable GitHub client boundary.

</domain>

<decisions>
## Implementation Decisions

### Publish Trigger
- **D-01:** Provide a `Publish Approved` action that publishes all drafts currently in `approved` status for the review run.
- **D-02:** Do not support selecting a subset of approved drafts in Phase 05. The MVP publish model is one-click publish-all for approved drafts.
- **D-03:** Provide a separate `Retry Failed` action that retries all drafts currently in `failed` status.
- **D-04:** `Publish Approved` handles only `approved` drafts, while `Retry Failed` handles only `failed` drafts. This keeps publish and retry semantics clear.

### GitHub Comment Type
- **D-05:** Prefer GitHub line-level PR review comments when the draft has enough targeting metadata.
- **D-06:** If a draft cannot be mapped to a line-level review comment target, fall back to a general PR comment instead of failing the draft.
- **D-07:** General PR comment fallback should publish each fallback draft as its own PR comment, not merge multiple drafts into one comment. This preserves one draft to one publication result.

### Posted Draft State
- **D-08:** Once a draft is successfully `posted`, it is locked locally: it cannot be edited and approval cannot be cancelled.
- **D-09:** Updating already-posted GitHub comments is out of scope for Phase 05.
- **D-10:** On successful publication, store GitHub comment id, GitHub comment HTML URL, and `posted_at` on the draft.
- **D-11:** Do not store the full GitHub response JSON on drafts.

### Failure and Retry
- **D-12:** If GitHub publication fails for a draft, mark that draft as `failed` and store a safe categorized error message.
- **D-13:** Failed drafts stay in `failed` status until `Retry Failed` succeeds or fails again.
- **D-14:** Failed publication should not roll back drafts that were already successfully posted to GitHub.
- **D-15:** Safe publication errors should be categorized messages such as rate limit, auth/token rejected, target invalid, GitHub unavailable, or unexpected response.
- **D-16:** Do not display or persist raw GitHub response bodies, authorization headers, tokens, or other secret-bearing payloads in draft errors.

### Detail Page UI
- **D-17:** Publish controls belong in the existing Comment Drafts section rather than a separate page or top-level panel.
- **D-18:** The Comment Drafts section should show `Publish Approved` and `Retry Failed` actions when relevant drafts exist.
- **D-19:** Each draft row should display posted/failed state locally.
- **D-20:** Posted draft rows should show the GitHub comment link when available.
- **D-21:** Failed draft rows should show the safe error message.

### the agent's Discretion
- Planner may choose exact route, controller, service, repository, DTO, migration, and method names as long as Controller / Service / Repository layering is preserved.
- Planner may decide how to model GitHub publication result objects, provided tests can fake both line-level and fallback PR-comment publishing.
- Planner may decide how to detect whether a draft has enough targeting metadata for line-level publication, but fallback to PR comment must be implemented for insufficient targets.
- Planner may decide whether publication runs synchronously in the HTTP request for this MVP or uses a queued job, as long as the user sees per-draft success/failure and tests remain deterministic.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project and Requirements
- `.planning/PROJECT.md` — Product purpose, manual approval safety, GitHub safety constraints, secret handling, testing requirements, and Controller / Service / Repository architecture.
- `.planning/REQUIREMENTS.md` — Phase 05 requirements: `PUB-01` through `PUB-06`; also note Phase 04 draft/rule requirements are implemented even if some checklist entries are stale.
- `.planning/ROADMAP.md` — Phase 05 goal, dependencies, success criteria, and v1 boundary.
- `.planning/STATE.md` — Current project position: Phase 05 ready for planning after Phase 04 completion.

### Prior Phase Context
- `.planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md` — Draft approval, stale draft, custom instruction, and Phase 05 boundary decisions.
- `.planning/phases/04-draft-review-and-custom-instructions/04-05-SUMMARY.md` — Latest implemented custom-instruction integration and Phase 04 completion status.
- `.planning/phases/03-queued-ai-review-and-structured-findings/03-CONTEXT.md` — AI review execution, fake provider, safe failure, and finding/draft boundary decisions.
- `.planning/phases/02-github-pr-ingestion/02-CONTEXT.md` — GitHub client boundary, public-first access scope, manual fetch, safe GitHub failures, and stored snapshot shape.

### Current Code
- `app/Contracts/GitHub/GitHubClient.php` — Existing fakeable GitHub client interface to extend for publication.
- `app/Services/GitHub/HttpGitHubClient.php` — Existing concrete GitHub HTTP client with token/config handling to extend safely.
- `app/Services/GitHub/GitHubFailureMapper.php` — Existing safe GitHub failure categorization pattern to mirror or extend for publication.
- `app/Models/ReviewCommentDraft.php` — Draft model with status/body/target metadata/stale state; Phase 05 will extend posted/failed metadata.
- `app/Enums/ReviewCommentDraftStatus.php` — Existing `draft`, `approved`, `posted`, and `failed` state vocabulary.
- `app/Repositories/ReviewCommentDraftRepository.php` — Existing repository for draft lookup and state mutation.
- `app/Services/ReviewDraftService.php` — Existing draft edit/approve/unapprove workflow and state guards.
- `resources/views/reviews/show.blade.php` — Existing detail page and Comment Drafts section where publish controls and row state should appear.
- `routes/web.php` — Existing review and draft route surface for adding publish/retry actions.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `GitHubClient` already abstracts GitHub PR reads and can be extended with publication methods while keeping tests fakeable.
- `HttpGitHubClient::request()` already centralizes GitHub base URL, API version headers, and optional token handling.
- `GitHubFailureMapper` already maps GitHub exceptions to safe categorized messages for fetch failures.
- `ReviewCommentDraftStatus` already includes `posted` and `failed`, so Phase 05 can add transitions instead of inventing status names.
- `ReviewCommentDraftRepository` already scopes draft lookup by review run and mutates draft status for approval/unapproval.
- `resources/views/reviews/show.blade.php` already renders draft rows and can display publish/retry controls in the Comment Drafts section.

### Established Patterns
- Controllers handle HTTP request validation, redirects, and flash messages only.
- Services own business workflow and state-transition rules.
- Repositories own Eloquent reads and writes.
- External GitHub calls sit behind an interface and are faked in tests.
- Safe errors avoid raw provider/GitHub payloads, authorization headers, and secret fragments.
- PHP/artisan/composer commands must run inside the Docker workspace container in this environment.

### Integration Points
- Add publish/retry routes near the existing draft routes in `routes/web.php`.
- Add a controller for publish/retry HTTP actions or extend the existing draft controller if the planner finds that clearer.
- Extend `GitHubClient` and `HttpGitHubClient` with line-level review comment and fallback PR comment publication methods.
- Add draft columns for GitHub comment id, GitHub comment HTML URL, posted timestamp, and safe publication error message.
- Add repository methods for loading approved/failed drafts and marking drafts posted/failed.
- Update the Comment Drafts section to show `Publish Approved`, `Retry Failed`, GitHub links for posted drafts, and safe errors for failed drafts.

</code_context>

<specifics>
## Specific Ideas

- The user chose one-click publish of all approved drafts.
- The user chose one-click retry of all failed drafts.
- The user chose line-level review comments first, with fallback to general PR comments when line targeting is unavailable.
- The user chose one GitHub comment per draft, including fallback PR comments.
- The user chose to lock posted drafts locally.
- The user chose to store GitHub comment id, HTML URL, and posted timestamp, but not full response JSON.
- The user chose categorized safe publication errors only.
- The user chose to keep publish controls inside the existing Comment Drafts section.

</specifics>

<deferred>
## Deferred Ideas

- Selecting a subset of approved drafts for publication is deferred beyond the MVP.
- Updating/editing already-posted GitHub comments is deferred beyond Phase 05.
- Merging multiple fallback drafts into one PR comment is not part of Phase 05.
- GitHub webhook automation, authentication, team permissions, and multi-provider UI remain outside v1 manual publishing.

</deferred>

---

*Phase: 05-GitHub Comment Publishing*
*Context gathered: 2026-06-29T00:32:24+08:00*
