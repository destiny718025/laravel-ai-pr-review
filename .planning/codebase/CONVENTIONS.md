---
last_mapped: 2026-06-26
focus: quality
---

# Codebase Conventions

## Summary

The codebase currently follows Laravel's default conventions with modern PHP syntax. There is not enough custom application code to infer product-specific patterns yet. Future work should preserve Laravel idioms and introduce domain patterns only when they remove real complexity.

## PHP Style

- Namespace roots follow PSR-4 autoloading from `composer.json`:
  - `App\\` maps to `app/`.
  - `Database\\Factories\\` maps to `database/factories/`.
  - `Database\\Seeders\\` maps to `database/seeders/`.
  - `Tests\\` maps to `tests/`.
- Files use `declare`-less Laravel skeleton style.
- Methods include explicit return types where present.
- Class names use StudlyCase.
- Test method names use descriptive snake_case, e.g. `test_the_application_returns_a_successful_response`.

## Laravel 13 / Modern Laravel Patterns

The app uses newer Laravel skeleton conventions:

- Application configuration lives in `bootstrap/app.php`.
- Providers are listed in `bootstrap/providers.php`.
- Exception JSON behavior is configured through `withExceptions()`.
- The `User` model uses PHP attributes for `Fillable` and `Hidden` instead of traditional protected properties.

Example locations:

- `bootstrap/app.php`
- `app/Models/User.php`

## Model Conventions

`app/Models/User.php` shows these model conventions:

- Eloquent model extends `Illuminate\Foundation\Auth\User`.
- Traits are listed inside the class body.
- Factories are typed with a PHPDoc generic comment.
- Sensitive attributes are hidden.
- Passwords are cast with Laravel's `hashed` cast.

Future models should follow the same clarity:

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

Suggested domain vocabulary to keep code coherent:

- `Repository` or `GitHubRepository` for tracked repos.
- `PullRequest` for PR metadata.
- `ReviewRun` for one AI review execution.
- `ReviewFinding` for structured findings before comment publication.
- `ReviewComment` for GitHub comment payloads or posted comment records.
- `RuleSet` for configurable review rules.

Avoid vague names like `Analyzer` or `Processor` unless the class has a narrowly defined responsibility.
