# Laravel AI PR Review

## What This Is

Laravel AI PR Review is a personal-use Laravel web application for running AI-assisted code reviews on GitHub pull requests. The first version lets the user enter a GitHub PR URL in a management interface, fetch and analyze the PR diff, generate structured findings and GitHub-ready comment drafts, and manually approve comments before posting them back to GitHub.

The project is currently a fresh Laravel 13 skeleton with codebase mapping completed in `.planning/codebase/`. The product direction is to validate the AI review workflow first, then grow toward webhook automation, configurable rules, history, and team workflows.

## Core Value

Turn a GitHub PR URL into useful, reviewable AI findings and comment drafts that help catch bugs and security issues before code is merged.

## Requirements

### Validated

- Laravel 13 application skeleton exists and is mapped — existing
- Database-backed queue, cache, session, and default user infrastructure are scaffolded — existing
- PHPUnit test setup is available — existing
- Vite and Tailwind frontend tooling are available — existing

### Active

- [ ] User can open a web management interface without login for local/private personal use
- [ ] User can submit a GitHub PR URL to start an AI review run
- [ ] System can fetch or receive the PR diff data needed for review
- [ ] System can persist review runs, findings, and comment drafts in the database
- [ ] System can show a review history page with run status
- [ ] User can open a review run detail page and inspect findings and comment drafts
- [ ] System can generate review output focused first on bugs and security issues
- [ ] System can also include Laravel/PHP style feedback when useful
- [ ] User can edit simple custom review instructions in a textarea
- [ ] AI provider access is isolated behind a provider interface rather than hardcoded into application flow
- [ ] User can manually approve generated comment drafts before posting them to GitHub
- [ ] System can post approved comments back to the relevant GitHub PR

### Out of Scope

- Multi-user authentication in v1 — this starts as a local/private personal workflow
- Organization/team permissions in v1 — team workflow comes after the single-user flow proves useful
- Automatic GitHub webhook review triggering in the first slice — manual PR URL input comes first, webhook support is a later phase
- Full rule engine in v1 — a simple custom instructions textarea is enough to validate rule usefulness
- Billing, subscriptions, and SaaS tenant management — the first goal is workflow validation, not monetization
- Supporting every git provider — GitHub is the initial integration target
- Fully automated comment posting without human approval — v1 keeps the user in control before writing to GitHub

## Context

The current repository is a new Laravel app at `/Users/tang/Project/laravel-ai-pr-review`. Codebase mapping exists in `.planning/codebase/` and shows:

- PHP `^8.3` and Laravel `^13.8` in `composer.json`
- Default web route in `routes/web.php`
- Default `User` model in `app/Models/User.php`
- Default migrations for users, sessions, cache, and jobs in `database/migrations/`
- Database queue support already scaffolded through `config/queue.php`
- PHPUnit configured in `phpunit.xml`
- Vite and Tailwind configured through `package.json` and `vite.config.js`

The AI PR review product has not been implemented yet. There are no GitHub clients, AI provider clients, review models, review jobs, webhook routes, dashboard pages, or product-specific tests.

Key workflow ideas discovered during initialization:

- Start with a web interface, not CLI-only operation
- First user is the project owner, not a team
- Review trigger should start with manual GitHub PR URL input
- GitHub webhook automation should follow after the manual workflow is stable
- Findings and comment drafts should be stored so review history is visible
- Comment drafts should be manually reviewed before posting to GitHub
- Provider-specific AI calls should sit behind an interface so the implementation can swap OpenAI, Anthropic, or other providers later
- v1 should prioritize bug and security review quality over breadth

## Constraints

- **Tech stack**: Laravel 13 and PHP 8.3 — the project is already created on this stack
- **Database**: SQLite-first for local MVP — default Laravel config already supports this and keeps early setup simple
- **Queueing**: Use Laravel queues for AI review work — PR diff analysis and AI calls should not block HTTP requests
- **Security**: Do not store or log raw API secrets — GitHub tokens and AI provider keys must stay in environment/config
- **GitHub safety**: Human approval is required before posting comments — avoids noisy or incorrect automated review comments
- **Architecture**: AI provider must be abstracted behind an interface — prevents the core workflow from depending on one vendor
- **Scope**: Personal-use MVP first — auth, team roles, billing, and SaaS operations are deferred
- **Testing**: External GitHub and AI calls should be faked in tests — avoids slow, brittle, or costly tests

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Build a Laravel web management interface for v1 | The user wants an interface to manage review runs, history, findings, and drafts | - Pending |
| Skip login for first version | The first user is the project owner using a local/private app | - Pending |
| Start with manual GitHub PR URL submission | Easier to validate the full review flow before webhook complexity | - Pending |
| Store review runs, findings, and comment drafts | History and detail views are part of the management workflow | - Pending |
| Generate comment drafts before posting | Keeps human control before writing to GitHub | - Pending |
| Use simple custom instructions textarea for v1 rules | Enough configurability to validate rule usefulness without building a full rules engine | - Pending |
| Use an AI provider interface | Keeps provider choice flexible and easier to test | - Pending |
| Prioritize bug/security findings first | This is the highest-value AI review output for the MVP | - Pending |
| Defer GitHub webhook automation | Webhook support depends on a stable manual review pipeline | - Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `$gsd-transition`):
1. Requirements invalidated? -> Move to Out of Scope with reason
2. Requirements validated? -> Move to Validated with phase reference
3. New requirements emerged? -> Add to Active
4. Decisions to log? -> Add to Key Decisions
5. "What This Is" still accurate? -> Update if drifted

**After each milestone** (via `$gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-06-26 after initialization*
