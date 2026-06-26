# Phase 1: Review Run Foundation and Management UI - Context

**Gathered:** 2026-06-26
**Status:** Ready for planning

<domain>
## Phase Boundary

This phase delivers the first usable management UI slice for Laravel AI PR Review. The user can open the app without logging in, land on a reviews dashboard, submit a GitHub pull request URL, have the app validate and parse that URL, create persisted review records, and view review run history/detail shell state.

This phase does not fetch GitHub PR metadata or files, call an AI provider, run queued review execution, generate findings, create comment drafts, or publish GitHub comments. Those capabilities belong to later phases.

</domain>

<decisions>
## Implementation Decisions

### Review Run Status Model

- **D-01:** Use a future-ready review run status set: `pending`, `queued`, `running`, `completed`, `failed`, and `cancelled`.
- **D-02:** Phase 1 primarily uses `pending` for successfully created review runs and validation-related service errors for rejected submissions.
- **D-03:** Reserve `queued`, `running`, `completed`, `failed`, and `cancelled` in the enum/schema so Phase 2 and Phase 3 can add GitHub ingestion and queued AI execution without changing the status vocabulary.

### Management Interface Shape

- **D-04:** Use a hybrid route structure:
  - `GET /` redirects to `/reviews`
  - `GET /reviews` shows the dashboard with PR URL form and review history
  - `POST /reviews` creates a review run from a PR URL
  - `GET /reviews/{id}` shows the review detail shell
- **D-05:** The `/reviews` dashboard should keep the first-use workflow efficient: submit a PR URL at the top, scan recent review runs below, and click into details when needed.
- **D-06:** Phase 1 detail page is a shell for run metadata/status/errors. Findings, comment drafts, and GitHub file data appear in later phases.

### Data Model Naming and Boundaries

- **D-07:** Create separate `repositories`, `pull_requests`, and `review_runs` tables/models in Phase 1.
- **D-08:** `repositories` owns GitHub repository identity such as owner/name and normalized full name.
- **D-09:** `pull_requests` owns GitHub pull request identity such as repository relationship, PR number, source URL, and later metadata fields.
- **D-10:** `review_runs` owns execution status and lifecycle for one review attempt against a pull request.
- **D-11:** Keep database access in repository classes. Services orchestrate creating/finding repository and pull request records, then creating review runs.

### PR URL Validation and Error Presentation

- **D-12:** Use structured validation errors from the service layer rather than only Blade form messages.
- **D-13:** Service-level parse/validation failures should return an error code plus a user-facing message. Candidate codes include `invalid_url`, `not_github_pr_url`, and `missing_pr_number`.
- **D-14:** The UI displays the user-facing message; tests assert the stable error code.
- **D-15:** Invalid PR URLs should not create failed review runs. History should stay focused on actual review attempts, not every malformed input.

### the agent's Discretion

- The exact Blade layout, CSS details, and component extraction are left to the planner/executor, as long as the interface remains clear, practical, and consistent with a work-focused Laravel management tool.
- The exact repository method names are left to the planner/executor, as long as the Controller / Service / Repository boundary is preserved.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project Scope and Requirements

- `.planning/PROJECT.md` — project vision, constraints, architecture decisions, and v1 boundaries
- `.planning/REQUIREMENTS.md` — Phase 1 requirements and traceability
- `.planning/ROADMAP.md` — Phase 1 goal, success criteria, and plan breakdown
- `.planning/STATE.md` — current phase position and session continuity

### Codebase Context

- `.planning/codebase/ARCHITECTURE.md` — current Laravel skeleton architecture and absence of custom domain code
- `.planning/codebase/STRUCTURE.md` — current directory layout and suggested future organization
- `.planning/codebase/CONVENTIONS.md` — Laravel 13 conventions, model style, routing conventions, and naming suggestions

### Research Context

- `.planning/research/SUMMARY.md` — roadmap implications and research-backed phase ordering
- `.planning/research/ARCHITECTURE.md` — recommended Controller / Service / Repository structure and request flows

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets

- `routes/web.php`: currently contains only `GET /`; Phase 1 can replace or redirect this route to `/reviews`.
- `app/Http/Controllers/Controller.php`: base controller exists; add a dedicated review controller rather than growing route closures.
- `app/Models/User.php`: demonstrates the Laravel 13 model style currently in the app, including PHP attributes and typed casts.
- `database/migrations/`: existing default migrations show the project is ready for additional domain tables.
- `resources/views/welcome.blade.php`: default welcome view exists and should be replaced or bypassed by the management UI.
- `phpunit.xml`: already configures in-memory SQLite for fast feature tests.

### Established Patterns

- Laravel 13 app configuration lives in `bootstrap/app.php`.
- Web routes are loaded from `routes/web.php`.
- The project currently has no custom services, repositories, or jobs, so Phase 1 establishes the first domain structure.
- Use Laravel conventions and introduce abstractions only where they support the explicit Controller / Service / Repository decision.

### Integration Points

- New routes connect through `routes/web.php`.
- New controller should live under `app/Http/Controllers/`.
- New service classes should live under `app/Services/`.
- New repository classes should live under `app/Repositories/`.
- New models should live under `app/Models/`.
- Feature tests should live under `tests/Feature/`.

</code_context>

<specifics>
## Specific Ideas

- The dashboard route shape is explicitly chosen: `/` redirects to `/reviews`; `/reviews` contains both the PR URL form and review history; `/reviews/{id}` is the detail shell.
- Review run status should be future-ready even though Phase 1 only creates the foundation.
- Phase 1 data modeling intentionally starts with separate `repositories`, `pull_requests`, and `review_runs`, rather than a single denormalized review run table.
- Invalid PR URLs should produce structured service errors and should not create records.

</specifics>

<deferred>
## Deferred Ideas

- GitHub PR metadata/files fetching belongs to Phase 2.
- Queued AI review execution and AI provider integration belong to Phase 3.
- Findings, comment drafts, and custom instructions belong to Phase 4.
- GitHub comment publishing belongs to Phase 5.
- Webhook automation remains post-v1.

</deferred>

---

*Phase: 1-Review Run Foundation and Management UI*
*Context gathered: 2026-06-26*
