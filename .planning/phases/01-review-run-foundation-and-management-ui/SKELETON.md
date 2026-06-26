# Walking Skeleton — Laravel AI PR Review

**Phase:** 1
**Generated:** 2026-06-27

## Capability Proven End-to-End

User can submit a GitHub PR URL and see a persisted pending review run through the no-login management UI.

## Architectural Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Framework | Laravel 13 / PHP 8.3 | The project already exists on this stack, and it fits a personal-use web management interface with conventional routing, Blade views, Eloquent persistence, queues, and PHPUnit tests. |
| Data layer | SQLite-first with Eloquent migrations/models and a repository layer | The local MVP needs simple durable persistence, while repository classes keep database reads/writes out of controllers and services. |
| Auth | None in Phase 1; local/private no-login app | The first slice validates the manual review-run workflow before auth, teams, roles, or SaaS concerns are introduced. |
| Deployment target | Laravel local development via `composer run dev` | Phase 1 is a local/private walking skeleton; the full stack can be exercised with the Laravel dev server, queue listener, logs, and Vite through the existing Composer script. |
| Directory layout | Controller in `app/Http/Controllers`, service in `app/Services`, repositories in `app/Repositories`, models in `app/Models`, views in `resources/views/reviews`, tests in `tests/Feature` and `tests/Unit` | This establishes the Phase 1 Controller / Service / Repository layering contract without introducing packages or module boundaries too early. |

## Stack Touched in Phase 1

- [x] Project scaffold (framework, build, lint, test runner)
- [x] Routing — at least one real route
- [x] Database — at least one real read AND one real write
- [x] UI — at least one interactive element wired to the API
- [x] Deployment — running on dev environment OR documented local full-stack run command

## Out of Scope (Deferred to Later Slices)

- GitHub PR metadata, changed files, patches, commits, and diff mapping.
- External GitHub API clients, credentials, tokens, and HTTP calls.
- Queued AI review execution jobs and lifecycle automation beyond persisted Phase 1 status fields.
- AI provider interfaces, prompts, model configuration, schema validation, and AI calls.
- Findings, comment drafts, custom instructions, draft approval, and GitHub comment publishing.
- Login, users, teams, permissions, webhooks, billing, and SaaS operations.
- Delete, retry, cancel, bulk actions, queue controls, and provider selection UI.

## Subsequent Slice Plan

Each later phase adds one vertical slice on top of this skeleton without altering its architectural decisions:

- Phase 2: Fetch GitHub pull request metadata and changed files through a fakeable GitHub client.
- Phase 3: Execute queued AI review through a provider interface and persist validated findings.
- Phase 4: Convert findings into editable comment drafts and simple custom instructions.
- Phase 5: Publish only approved comment drafts to GitHub and track publication status.
