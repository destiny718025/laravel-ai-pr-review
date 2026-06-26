# Phase 01 Research: Review Run Foundation and Management UI

## User Constraints

Locked decisions copied from `01-CONTEXT.md`:

- **D-01:** Use a future-ready review run status set: `pending`, `queued`, `running`, `completed`, `failed`, and `cancelled`. [VERIFIED: codebase]
- **D-02:** Phase 1 primarily uses `pending` for successfully created review runs and validation-related service errors for rejected submissions. [VERIFIED: codebase]
- **D-03:** Reserve `queued`, `running`, `completed`, `failed`, and `cancelled` in the enum/schema so Phase 2 and Phase 3 can add GitHub ingestion and queued AI execution without changing the status vocabulary. [VERIFIED: codebase]
- **D-04:** Use a hybrid route structure: `GET /` redirects to `/reviews`; `GET /reviews` shows the dashboard with PR URL form and review history; `POST /reviews` creates a review run from a PR URL; `GET /reviews/{id}` shows the review detail shell. [VERIFIED: codebase]
- **D-05:** The `/reviews` dashboard should keep the first-use workflow efficient: submit a PR URL at the top, scan recent review runs below, and click into details when needed. [VERIFIED: codebase]
- **D-06:** Phase 1 detail page is a shell for run metadata/status/errors. Findings, comment drafts, and GitHub file data appear in later phases. [VERIFIED: codebase]
- **D-07:** Create separate `repositories`, `pull_requests`, and `review_runs` tables/models in Phase 1. [VERIFIED: codebase]
- **D-08:** `repositories` owns GitHub repository identity such as owner/name and normalized full name. [VERIFIED: codebase]
- **D-09:** `pull_requests` owns GitHub pull request identity such as repository relationship, PR number, source URL, and later metadata fields. [VERIFIED: codebase]
- **D-10:** `review_runs` owns execution status and lifecycle for one review attempt against a pull request. [VERIFIED: codebase]
- **D-11:** Keep database access in repository classes. Services orchestrate creating/finding repository and pull request records, then creating review runs. [VERIFIED: codebase]
- **D-12:** Use structured validation errors from the service layer rather than only Blade form messages. [VERIFIED: codebase]
- **D-13:** Service-level parse/validation failures should return an error code plus a user-facing message. Candidate codes include `invalid_url`, `not_github_pr_url`, and `missing_pr_number`. [VERIFIED: codebase]
- **D-14:** The UI displays the user-facing message; tests assert the stable error code. [VERIFIED: codebase]
- **D-15:** Invalid PR URLs should not create failed review runs. History should stay focused on actual review attempts, not every malformed input. [VERIFIED: codebase]

Discretion areas copied from `01-CONTEXT.md`:

- The exact Blade layout, CSS details, and component extraction are left to the planner/executor, as long as the interface remains clear, practical, and consistent with a work-focused Laravel management tool. [VERIFIED: codebase]
- The exact repository method names are left to the planner/executor, as long as the Controller / Service / Repository boundary is preserved. [VERIFIED: codebase]

Deferred ideas copied from `01-CONTEXT.md`:

- GitHub PR metadata/files fetching belongs to Phase 2. [VERIFIED: codebase]
- Queued AI review execution and AI provider integration belong to Phase 3. [VERIFIED: codebase]
- Findings, comment drafts, and custom instructions belong to Phase 4. [VERIFIED: codebase]
- GitHub comment publishing belongs to Phase 5. [VERIFIED: codebase]
- Webhook automation remains post-v1. [VERIFIED: codebase]

## Project Constraints (from AGENTS.md)

- The project is Laravel AI PR Review, a personal-use Laravel web app for AI-assisted GitHub pull request reviews. [VERIFIED: codebase]
- Use Laravel 13 and PHP 8.3; do not plan a stack migration. [VERIFIED: codebase]
- Use SQLite-first persistence for the local MVP. [VERIFIED: codebase]
- Use Laravel queues for long-running AI review work; Phase 1 must leave room for this, but not implement queued execution. [VERIFIED: codebase]
- Do not store or log raw API secrets; future GitHub tokens and AI keys belong in environment/config. [VERIFIED: codebase]
- Require human approval before posting comments to GitHub; Phase 1 must not add any posting path. [VERIFIED: codebase]
- Keep AI provider access abstracted behind an interface in later phases; Phase 1 should avoid coupling the review run model to one provider. [VERIFIED: codebase]
- Use Controller / Service / Repository layering: controllers handle HTTP concerns, services own business workflows, repositories own database access. [VERIFIED: codebase]
- Personal-use MVP comes first; auth, teams, billing, and SaaS operations are deferred. [VERIFIED: codebase]
- Fake external GitHub and AI calls in tests; Phase 1 should have no external calls at all. [VERIFIED: codebase]
- Before file-changing work, use a GSD workflow entry point or the active phase workflow context. [VERIFIED: codebase]
- Ask before creating commits; use Chinese commit messages when possible. This was required by the phase prompt, but was not present in the checked-in `AGENTS.md` found by `rg`. [ASSUMED]

## Standard Stack

- Runtime is PHP `^8.3` and Laravel framework `^13.8` from `composer.json`. [VERIFIED: codebase]
- Composer autoload maps `App\` to `app/`, so new domain classes should use normal `App\Models`, `App\Services`, and `App\Repositories` namespaces. [VERIFIED: codebase]
- Existing route surface is only `GET /`, returning `resources/views/welcome.blade.php`. Phase 1 should replace that homepage behavior with a redirect to `/reviews`. [VERIFIED: codebase]
- The only current application model is `App\Models\User`, using Laravel 13 attribute-based `#[Fillable]` and `#[Hidden]` plus typed `casts()`. New models should use explicit casts and clear relationships. [VERIFIED: codebase]
- Current migrations are Laravel infrastructure only: users/password resets/sessions, cache/cache locks, jobs/job batches/failed jobs. No review domain schema exists yet. [VERIFIED: codebase]
- Default queue connection is database in project docs and jobs tables are already scaffolded, but Phase 1 does not dispatch jobs. [VERIFIED: codebase]
- PHPUnit is configured with `APP_ENV=testing`, SQLite `:memory:`, array cache/session/mail, and sync queue. [VERIFIED: codebase]
- `composer run test` clears config and runs `php artisan test`; use that as the verification command. [VERIFIED: codebase]
- Vite and Tailwind CSS are installed, but Phase 1 can use Blade-first UI and only lean on existing CSS tooling if needed. [VERIFIED: codebase]

## Architecture Patterns

- Plan one controller, probably `ReviewController`, with `index`, `store`, and `show` actions. The controller should validate request shape, call the service, translate service results into redirects/views, and avoid parsing PR URLs or touching Eloquent directly. [ASSUMED]
- Plan one workflow service, probably `ReviewRunService`, responsible for `createFromPullRequestUrl(string $url)`. It should orchestrate parsing, repository lookup/create, pull request lookup/create, and review run creation. [ASSUMED]
- Plan a small parser/value object boundary for GitHub PR URLs, such as `GitHubPullRequestUrlParser` returning a DTO with `owner`, `repositoryName`, `pullRequestNumber`, and normalized source URL, or a structured failure. [ASSUMED]
- Plan repository classes for persistence, such as `GitHubRepositoryRepository`, `PullRequestRepository`, and `ReviewRunRepository`, but avoid naming that becomes unreadable. If the model is named `Repository`, be explicit with imports because that name collides conceptually with repository-layer classes. [ASSUMED]
- Eloquent models should own relationships and casts, while repository classes own query/write methods. Services may receive model instances from repositories but should not call `Model::query()` or `Model::create()` directly. [ASSUMED]
- Keep service-result classes or DTOs small and stable. A creation result should expose success/failure, optional `ReviewRun`, optional error code, and user-facing message. [ASSUMED]
- Bind interfaces only if they create immediate test or boundary value. For Phase 1, concrete repository classes injected by Laravel's container may be enough unless the planner wants explicit contracts for future mocking. [ASSUMED]

## Data Model Guidance

- `repositories` table: `id`, `owner`, `name`, `full_name`, timestamps. Add a unique index on `full_name`, or a composite unique index on `owner` + `name`; using both is acceptable if one is canonical. [ASSUMED]
- Normalize `owner` and `name` consistently. GitHub owner/repo names are case-insensitive for routing in practice, but display casing may matter. Store the submitted/display casing plus a normalized `full_name` lowercased for uniqueness if the plan wants robust deduplication. [ASSUMED]
- `pull_requests` table: `id`, `repository_id` foreign key, `number` as unsigned integer, `source_url`, nullable future metadata fields only if clearly needed, timestamps. Add unique index on `repository_id` + `number`. [ASSUMED]
- `review_runs` table: `id`, `pull_request_id` foreign key, `status`, nullable `safe_error_message`, nullable lifecycle timestamps such as `queued_at`, `started_at`, `completed_at`, `failed_at`, `cancelled_at`, plus regular timestamps. [ASSUMED]
- To satisfy RUN-04 directly from a review run, detail/list queries can eager-load `pullRequest.repository` rather than duplicating owner/name/number on `review_runs`. If the UI needs denormalized snapshots later, add them deliberately in a later phase. [ASSUMED]
- Status values must reserve exactly `pending`, `queued`, `running`, `completed`, `failed`, and `cancelled`; initial successful submissions create `pending` runs. [VERIFIED: codebase]
- Safe failure display is required by RUN-07 even though Phase 1 does not create failed runs from malformed input. Seed/test support or repository helpers should allow a failed `ReviewRun` with `safe_error_message` so the UI can prove the failure state. [ASSUMED]
- Use foreign key constraints with cascade behavior carefully: deleting repositories is not a Phase 1 workflow, so default restrictive or cascade choices should be explicit in the plan. [ASSUMED]

## PR URL Parsing and Validation

- Parsing must accept GitHub pull request URLs shaped like `https://github.com/{owner}/{repo}/pull/{number}` and return owner, repo, and positive integer PR number for GH-01. [ASSUMED]
- Treat malformed strings or URLs without host/path as `invalid_url`. [VERIFIED: codebase]
- Treat valid URLs that are not GitHub PR URLs as `not_github_pr_url`. This includes non-GitHub hosts and GitHub paths that are not `/owner/repo/pull/...`. [VERIFIED: codebase]
- Treat GitHub PR-looking URLs without a usable numeric PR number as `missing_pr_number`. [VERIFIED: codebase]
- Parser/service failures should return stable error codes and user-facing safe messages. Tests should assert codes, not exact copy, except where UI visibility matters. [VERIFIED: codebase]
- Do not create a `failed` review run for invalid submitted URLs. Invalid input is rejected before persistence. [VERIFIED: codebase]
- Recommended boundary: HTTP form validation checks `pr_url` is present and string-like; service/parser validates semantic GitHub PR shape. This keeps controller validation separate from business validation. [ASSUMED]
- Normalize by stripping query strings/fragments for persisted `source_url` if the planner wants canonicalization, but be explicit and test it. [ASSUMED]

## UI and Route Flow

- `GET /`: redirect to `/reviews` so RUN-01 lands the no-login user in the management interface. [VERIFIED: codebase]
- `GET /reviews`: dashboard with PR URL form at top and recent review history below. It should call repository/service read methods, not query models from the route closure. [VERIFIED: codebase]
- `POST /reviews`: accepts `pr_url`, calls review creation service, redirects to the created review detail on success, and redirects back to `/reviews` with service error message on failure. [ASSUMED]
- `GET /reviews/{id}`: detail shell showing status, source URL, repository owner/name, PR number, timestamps, and safe error message when status is `failed`. [VERIFIED: codebase]
- Use controller-backed routes because behavior is beyond trivial route closures. [VERIFIED: codebase]
- The UI should be work-focused: clear form, compact status labels, useful empty state, recent runs table/list, and obvious detail navigation. [ASSUMED]
- No login middleware should be applied in Phase 1. [VERIFIED: codebase]

## Test Strategy

- Add feature tests with `RefreshDatabase` for route behavior and persistence because `phpunit.xml` already uses in-memory SQLite. [VERIFIED: codebase]
- Feature-test `GET /` redirects to `/reviews`. [VERIFIED: codebase]
- Feature-test `GET /reviews` returns 200 without authentication and shows the submission form and empty/history state. [VERIFIED: codebase]
- Feature-test valid `POST /reviews` creates one repository, one pull request, and one pending review run, then redirects to `/reviews/{id}`. [ASSUMED]
- Feature-test duplicate valid submissions for the same PR reuse the repository and pull request records but create a new review run, because a run represents one review attempt. [ASSUMED]
- Feature-test invalid URL cases for `invalid_url`, `not_github_pr_url`, and `missing_pr_number`; assert no records are created in `repositories`, `pull_requests`, or `review_runs`. [VERIFIED: codebase]
- Feature-test review history displays status and basic PR identity for persisted runs. [VERIFIED: codebase]
- Feature-test detail page displays run metadata and safe failure message for a manually created failed run. [VERIFIED: codebase]
- Unit-test the PR URL parser/service boundary independently for accepted and rejected URL shapes. [ASSUMED]
- Unit-test service orchestration with fake repositories only if the planner introduces repository interfaces or simple fakes; otherwise feature tests may provide enough confidence for Phase 1. [ASSUMED]
- Update or remove the default Laravel example homepage test because `GET /` should redirect, not return 200. [VERIFIED: codebase]

## Common Pitfalls

- Do not put GitHub fetching, AI calls, jobs, findings, drafts, publishing, or webhook logic into Phase 1. [VERIFIED: codebase]
- Do not let controllers parse PR URLs, decide statuses, or call Eloquent directly for domain writes. [VERIFIED: codebase]
- Do not let services own raw query construction or persistence details; keep reads/writes in repositories. [VERIFIED: codebase]
- Do not store invalid URL attempts as failed review runs; failed runs are for real review attempts that fail after creation. [VERIFIED: codebase]
- Do not use only Blade validation errors for service-level semantic failures; keep stable service error codes. [VERIFIED: codebase]
- Do not choose a narrow status enum that omits future states already decided in D-01. [VERIFIED: codebase]
- Do not overbuild authentication, settings, queue dashboards, or provider selection. [VERIFIED: codebase]
- Do not log raw submitted secrets or future provider payloads; even though Phase 1 handles only URLs, this security habit should be established now. [VERIFIED: codebase]
- Avoid brittle PR URL parsing with one giant regex if a URL parser plus path segment checks is clearer and easier to test. [ASSUMED]
- Avoid denormalizing repository owner/name/PR number onto every table unless the plan states why; relationships already model the ownership. [ASSUMED]

## Validation Architecture

- Feature tests should prove user-visible workflows: no-login access, redirects, form submission, persistence, history, detail shell, failure display, and no records on invalid PR URL submissions. [ASSUMED]
- Unit tests should prove pure parsing and stable error-code behavior because those are easy to regress without hitting HTTP or the database. [ASSUMED]
- Repository behavior can be covered by feature tests unless methods become complex; keep direct repository unit tests optional for Phase 1. [ASSUMED]
- Service behavior should be verified either through feature tests plus focused unit tests, or through service unit tests with fake repositories if interfaces are introduced. The planner should pick one clear approach and avoid duplicative tests. [ASSUMED]
- Run `composer run test` as the main verification command. If UI assets are changed, also run `npm run build`. [VERIFIED: codebase]
- Because no external calls are in scope, test suite execution should be deterministic and require no GitHub token or AI key. [VERIFIED: codebase]

## Out of Scope

- Fetching GitHub PR metadata, commits, files, patches, or diff data. [VERIFIED: codebase]
- GitHub client interfaces and HTTP implementations, except for a pure URL parser if the planner places it under a GitHub namespace. [VERIFIED: codebase]
- Dispatching or running queued review execution jobs. [VERIFIED: codebase]
- AI provider interfaces, fake AI providers, prompts, model configuration, schema validation, and provider calls. [VERIFIED: codebase]
- Review findings, comment drafts, custom instruction settings, draft editing/approval, and GitHub publication. [VERIFIED: codebase]
- Webhook automation, webhook signature validation, and delivery deduplication. [VERIFIED: codebase]
- Login, teams, authorization policies, billing, tenant management, and SaaS operations. [VERIFIED: codebase]
- Supporting GitLab, Bitbucket, or non-GitHub PR URLs. [VERIFIED: codebase]

## Planning Implications

- **01-01: Create review run data model, repository, and service boundaries** should deliver migrations/models/relationships, status handling, repository classes, parser/service result types, and tests around parser/service or persistence boundaries. It must establish Controller / Service / Repository rules before UI work depends on them. [ASSUMED]
- **01-02: Build PR URL submission, validation, and review run creation UI** should add controller routes/views for `/` and `/reviews`, wire the form to `ReviewRunService::createFromPullRequestUrl`, display service error messages, redirect to detail on success, and assert invalid submissions create no records. [ASSUMED]
- **01-03: Build review history/detail pages and status/failure display** should complete dashboard history, detail shell, eager-loaded identity display, status labels, failed status safe message rendering, and feature tests for listing/detail behavior. [ASSUMED]
- Plan order matters: create schema and service contracts first, then creation UI, then richer history/detail display. Otherwise controllers will be tempted to own logic that belongs in services/repositories. [ASSUMED]
- Each plan should include tests that map directly to the required IDs: RUN-01 through RUN-07, ARCH-01 through ARCH-04, and GH-01. [VERIFIED: codebase]

## Research Provenance

- Read `.planning/phases/01-review-run-foundation-and-management-ui/01-CONTEXT.md` for locked phase decisions D-01 through D-15, discretion areas, and deferred ideas. [VERIFIED: codebase]
- Read `.planning/REQUIREMENTS.md` for Phase 1 requirement IDs RUN-01 through RUN-07, ARCH-01 through ARCH-04, and GH-01. [VERIFIED: codebase]
- Read `.planning/ROADMAP.md` for Phase 1 goal, success criteria, and three-plan breakdown. [VERIFIED: codebase]
- Read `.planning/STATE.md` for current phase position and planning status. [VERIFIED: codebase]
- Read `.planning/PROJECT.md` and `AGENTS.md` for project scope, stack constraints, no-login MVP, architecture constraints, and GSD workflow guidance. [VERIFIED: codebase]
- Read `.planning/codebase/ARCHITECTURE.md`, `.planning/codebase/STRUCTURE.md`, `.planning/codebase/CONVENTIONS.md`, and `.planning/codebase/TESTING.md` for current skeleton architecture, structure, conventions, and test setup. [VERIFIED: codebase]
- Read `composer.json`, `routes/web.php`, `app/Models/User.php`, `database/migrations/*`, and `phpunit.xml` for verified stack, route, model, migration, and test-environment facts. [VERIFIED: codebase]
- Laravel/PHP design guidance in this document is prescriptive planning advice based on common Laravel practice and the local project constraints, not an implemented code fact. [ASSUMED]
