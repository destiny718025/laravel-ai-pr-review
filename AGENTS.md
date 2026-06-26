<!-- GSD:project-start source:PROJECT.md -->

## Project

**Laravel AI PR Review**

Laravel AI PR Review is a personal-use Laravel web application for running AI-assisted code reviews on GitHub pull requests. The first version lets the user enter a GitHub PR URL in a management interface, fetch and analyze the PR diff, generate structured findings and GitHub-ready comment drafts, and manually approve comments before posting them back to GitHub.

The project is currently a fresh Laravel 13 skeleton with codebase mapping completed in `.planning/codebase/`. The product direction is to validate the AI review workflow first, then grow toward webhook automation, configurable rules, history, and team workflows.

**Core Value:** Turn a GitHub PR URL into useful, reviewable AI findings and comment drafts that help catch bugs and security issues before code is merged.

### Constraints

- **Tech stack**: Laravel 13 and PHP 8.3 — the project is already created on this stack
- **Database**: SQLite-first for local MVP — default Laravel config already supports this and keeps early setup simple
- **Queueing**: Use Laravel queues for AI review work — PR diff analysis and AI calls should not block HTTP requests
- **Security**: Do not store or log raw API secrets — GitHub tokens and AI provider keys must stay in environment/config
- **GitHub safety**: Human approval is required before posting comments — avoids noisy or incorrect automated review comments
- **Architecture**: AI provider must be abstracted behind an interface — prevents the core workflow from depending on one vendor
- **Architecture**: Use Controller / Service / Repository layering — controllers handle HTTP concerns, services own business workflows, repositories own database access
- **Scope**: Personal-use MVP first — auth, team roles, billing, and SaaS operations are deferred
- **Testing**: External GitHub and AI calls should be faked in tests — avoids slow, brittle, or costly tests

<!-- GSD:project-end -->

<!-- GSD:stack-start source:codebase/STACK.md -->

## Technology Stack

## Summary

## Runtime

- PHP: `^8.3`, declared in `composer.json`.
- Framework: `laravel/framework` `^13.8`, declared in `composer.json`.
- Package manager: Composer, with lockfile at `composer.lock`.
- CLI entry point: `artisan`.
- Application bootstrap: `bootstrap/app.php`.

## PHP Dependencies

- Production dependencies:
- Development dependencies:

## Frontend Tooling

- JavaScript package manager: npm, via `package.json`.
- Module type: ESM, via `"type": "module"` in `package.json`.
- Build tool: Vite `^8.0.0`.
- Laravel Vite plugin: `laravel-vite-plugin` `^3.1`.
- CSS framework: Tailwind CSS `^4.0.0` using `@tailwindcss/vite`.
- Asset entry points:

## Laravel Configuration

- Routing is configured in `bootstrap/app.php`.
- Web routes are loaded from `routes/web.php`.
- Console routes are loaded from `routes/console.php`.
- Health endpoint is configured as `/up` in `bootstrap/app.php`.
- JSON exception rendering is enabled for `api/*` requests in `bootstrap/app.php`.
- Service provider list is in `bootstrap/providers.php`.
- Application service provider is `app/Providers/AppServiceProvider.php`.

## Persistence Defaults

- Default database connection is SQLite via `config/database.php`.
- The setup script creates `database/database.sqlite` after project creation.
- Default migrations exist for:

## Queue, Cache, Session

- Default queue connection is `database` in `config/queue.php`.
- Queue tables are already scaffolded by the default jobs migration.
- Default cache store is `database` in `config/cache.php`.
- Default session driver is `database` in `config/session.php`.
- Tests override queue/session/cache to in-memory or synchronous drivers in `phpunit.xml`.

## Development Commands

- `composer run setup` installs dependencies, prepares `.env`, generates the app key, migrates, installs npm packages, and builds assets.
- `composer run dev` runs server, queue listener, pail logs, and Vite concurrently.
- `composer run test` clears config and runs `php artisan test`.
- `npm run dev` starts Vite.
- `npm run build` builds frontend assets.

## AI PR Review Implications

- The Laravel skeleton is ready for a conventional service-oriented implementation.
- Planned GitHub and AI credentials should be added through `config/services.php` and environment variables.
- Long-running review jobs should use the existing queue setup rather than blocking webhook requests.
- Review persistence can start with SQLite locally and move to MySQL/PostgreSQL later without changing the domain model much.

<!-- GSD:stack-end -->

<!-- GSD:conventions-start source:CONVENTIONS.md -->

## Conventions

## Summary

## PHP Style

- Namespace roots follow PSR-4 autoloading from `composer.json`:
- Files use `declare`-less Laravel skeleton style.
- Methods include explicit return types where present.
- Class names use StudlyCase.
- Test method names use descriptive snake_case, e.g. `test_the_application_returns_a_successful_response`.

## Laravel 13 / Modern Laravel Patterns

- Application configuration lives in `bootstrap/app.php`.
- Providers are listed in `bootstrap/providers.php`.
- Exception JSON behavior is configured through `withExceptions()`.
- The `User` model uses PHP attributes for `Fillable` and `Hidden` instead of traditional protected properties.
- `bootstrap/app.php`
- `app/Models/User.php`

## Model Conventions

- Eloquent model extends `Illuminate\Foundation\Auth\User`.
- Traits are listed inside the class body.
- Factories are typed with a PHPDoc generic comment.
- Sensitive attributes are hidden.
- Passwords are cast with Laravel's `hashed` cast.
- Put domain relationships on models only when they are real and used.
- Prefer explicit casts for JSON columns, timestamps, enums, and booleans.
- Keep AI provider payloads out of fillable mass assignment unless necessary.

## Routing Conventions

- Web route closures are currently used only for the default homepage in `routes/web.php`.
- No route groups, controllers, or API routes exist yet.
- Future webhook/API routes should be controller-backed once behavior moves beyond trivial routing.

## Configuration Conventions

- Environment access is centralized in config files, as Laravel recommends.
- `config/services.php` is available for third-party API credentials.
- Avoid calling `env()` directly in application services after configuration is cached.

## Error Handling Conventions

- There is no custom exception handling yet.
- `bootstrap/app.php` ensures `api/*` requests get JSON responses.
- Future GitHub webhook and AI review code should produce structured failure records rather than relying only on logs.

## Formatting and Static Quality

- Laravel Pint is available through `laravel/pint` in `composer.json`.
- No custom Pint config exists yet.
- The project can use Laravel defaults until a specific team style requirement appears.

## Naming Suggestions for Planned Domain

- `Repository` or `GitHubRepository` for tracked repos.
- `PullRequest` for PR metadata.
- `ReviewRun` for one AI review execution.
- `ReviewFinding` for structured findings before comment publication.
- `ReviewComment` for GitHub comment payloads or posted comment records.
- `RuleSet` for configurable review rules.

<!-- GSD:conventions-end -->

<!-- GSD:architecture-start source:ARCHITECTURE.md -->

## Architecture

## Summary

## Application Boot

- Front controller: `public/index.php`.
- Artisan console entry point: `artisan`.
- Framework bootstrap: `bootstrap/app.php`.
- Providers list: `bootstrap/providers.php`.
- Custom provider: `app/Providers/AppServiceProvider.php`.
- Web routing through `routes/web.php`.
- Console commands through `routes/console.php`.
- Health check endpoint at `/up`.
- Middleware hook with no custom middleware yet.
- Exception rendering behavior for `api/*` requests.

## Current Request Flow

- `GET /` in `routes/web.php`, returning `resources/views/welcome.blade.php`.

## Domain Model

- `app/Models/User.php`
- `HasFactory`
- `Notifiable`
- Attribute-based `#[Fillable(['name', 'email', 'password'])]`
- Attribute-based `#[Hidden(['password', 'remember_token'])]`
- `casts()` method for `email_verified_at` and hashed `password`
- GitHub repositories.
- Pull requests.
- Review jobs.
- Review findings.
- Review comments.
- Rule sets.
- AI provider runs.
- Installation or webhook delivery records.

## Data Model

- `users`, `password_reset_tokens`, and `sessions`.
- `cache` and `cache_locks`.
- `jobs`, `job_batches`, and `failed_jobs`.

## Layering

- HTTP routes: `routes/web.php`.
- Controllers: `app/Http/Controllers/`.
- Models: `app/Models/`.
- Providers: `app/Providers/`.
- Views: `resources/views/`.
- Database migrations: `database/migrations/`.
- Tests: `tests/Feature/` and `tests/Unit/`.
- HTTP/API controllers for GitHub webhook ingestion and manual PR review triggers.
- Services for GitHub API access, diff normalization, AI prompt orchestration, and comment publishing.
- Jobs for asynchronous review execution.
- Data transfer objects or value objects for normalized PR files, hunks, findings, and comments.
- Eloquent models for repositories, pull requests, review runs, and findings.

## Async Processing

- Default queue driver is `database` in `config/queue.php`.
- Jobs table exists in `database/migrations/0001_01_01_000002_create_jobs_table.php`.
- `composer run dev` starts `php artisan queue:listen --tries=1 --timeout=0`.

## API Readiness

```php

```

## Architectural Constraints

- The app is currently monolithic Laravel, not split into packages or services.
- No frontend application shell exists beyond the welcome page.
- No auth scaffolding package is installed.
- No GitHub or AI SDK is installed yet.
- No custom middleware is registered.

<!-- GSD:architecture-end -->

<!-- GSD:skills-start source:skills/ -->

## Project Skills

No project skills found. Add skills to any of: `.claude/skills/`, `.agents/skills/`, `.cursor/skills/`, `.github/skills/`, or `.codex/skills/` with a `SKILL.md` index file.
<!-- GSD:skills-end -->

<!-- GSD:workflow-start source:GSD defaults -->

## GSD Workflow Enforcement

Before using Edit, Write, or other file-changing tools, start work through a GSD command so planning artifacts and execution context stay in sync.

Use these entry points:

- `/gsd-quick` for small fixes, doc updates, and ad-hoc tasks
- `/gsd-debug` for investigation and bug fixing
- `/gsd-execute-phase` for planned phase work

Do not make direct repo edits outside a GSD workflow unless the user explicitly asks to bypass it.
<!-- GSD:workflow-end -->

<!-- GSD:profile-start -->

## Developer Profile

> Profile not yet configured. Run `/gsd-profile-user` to generate your developer profile.
> This section is managed by `generate-claude-profile` -- do not edit manually.
<!-- GSD:profile-end -->
