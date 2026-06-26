---
last_mapped: 2026-06-26
focus: arch
---

# Codebase Architecture

## Summary

This is a standard Laravel 13 application skeleton with minimal custom architecture. It currently follows Laravel's default MVC-style layout but has no domain services, jobs, policies, API controllers, or custom database models beyond `App\Models\User`. The architecture is therefore a clean baseline for building an AI PR review product.

## Application Boot

- Front controller: `public/index.php`.
- Artisan console entry point: `artisan`.
- Framework bootstrap: `bootstrap/app.php`.
- Providers list: `bootstrap/providers.php`.
- Custom provider: `app/Providers/AppServiceProvider.php`.

`bootstrap/app.php` configures:

- Web routing through `routes/web.php`.
- Console commands through `routes/console.php`.
- Health check endpoint at `/up`.
- Middleware hook with no custom middleware yet.
- Exception rendering behavior for `api/*` requests.

## Current Request Flow

The only user-facing route is:

- `GET /` in `routes/web.php`, returning `resources/views/welcome.blade.php`.

Current flow:

1. Request enters through `public/index.php`.
2. Laravel boots from `bootstrap/app.php`.
3. The route in `routes/web.php` resolves.
4. The closure returns the default welcome Blade view.

There are no controllers in use yet. `app/Http/Controllers/Controller.php` exists as the base controller only.

## Domain Model

The only application model is:

- `app/Models/User.php`

`User` extends Laravel's `Authenticatable` class and uses:

- `HasFactory`
- `Notifiable`
- Attribute-based `#[Fillable(['name', 'email', 'password'])]`
- Attribute-based `#[Hidden(['password', 'remember_token'])]`
- `casts()` method for `email_verified_at` and hashed `password`

There are no models yet for:

- GitHub repositories.
- Pull requests.
- Review jobs.
- Review findings.
- Review comments.
- Rule sets.
- AI provider runs.
- Installation or webhook delivery records.

## Data Model

Default migrations create infrastructure tables:

- `users`, `password_reset_tokens`, and `sessions`.
- `cache` and `cache_locks`.
- `jobs`, `job_batches`, and `failed_jobs`.

No application-specific AI review schema exists yet.

## Layering

Current layers are Laravel defaults:

- HTTP routes: `routes/web.php`.
- Controllers: `app/Http/Controllers/`.
- Models: `app/Models/`.
- Providers: `app/Providers/`.
- Views: `resources/views/`.
- Database migrations: `database/migrations/`.
- Tests: `tests/Feature/` and `tests/Unit/`.

For the planned product, a good next architecture would likely add:

- HTTP/API controllers for GitHub webhook ingestion and manual PR review triggers.
- Services for GitHub API access, diff normalization, AI prompt orchestration, and comment publishing.
- Jobs for asynchronous review execution.
- Data transfer objects or value objects for normalized PR files, hunks, findings, and comments.
- Eloquent models for repositories, pull requests, review runs, and findings.

## Async Processing

The project is already scaffolded for queue-based work:

- Default queue driver is `database` in `config/queue.php`.
- Jobs table exists in `database/migrations/0001_01_01_000002_create_jobs_table.php`.
- `composer run dev` starts `php artisan queue:listen --tries=1 --timeout=0`.

This matters because AI PR reviews should probably be handled outside the webhook request lifecycle.

## API Readiness

Although no API routes exist yet, `bootstrap/app.php` includes:

```php
$exceptions->shouldRenderJsonWhen(
    fn (Request $request) => $request->is('api/*'),
);
```

This is a useful default for future JSON endpoints.

## Architectural Constraints

- The app is currently monolithic Laravel, not split into packages or services.
- No frontend application shell exists beyond the welcome page.
- No auth scaffolding package is installed.
- No GitHub or AI SDK is installed yet.
- No custom middleware is registered.
