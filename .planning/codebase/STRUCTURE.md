---
last_mapped: 2026-06-26
focus: arch
---

# Codebase Structure

## Summary

The repository uses the default Laravel project structure. It is small and mostly unmodified, which makes it easy to introduce the AI PR review domain in a deliberate way without fighting existing conventions.

## Top-Level Files

- `artisan` — Laravel command-line entry point.
- `composer.json` — PHP dependencies, autoloading, and Composer scripts.
- `composer.lock` — locked PHP dependencies.
- `package.json` — frontend tooling dependencies and scripts.
- `phpunit.xml` — PHPUnit test suite and testing environment.
- `vite.config.js` — Vite configuration.
- `README.md` — default Laravel README.

## Application Code

- `app/Http/Controllers/Controller.php` — base controller.
- `app/Models/User.php` — default user model.
- `app/Providers/AppServiceProvider.php` — application service provider.

There are no custom service classes, jobs, policies, mailables, notifications, requests, commands, or observers yet.

## Bootstrap

- `bootstrap/app.php` — main application configuration.
- `bootstrap/providers.php` — provider registration list.
- `bootstrap/cache/.gitignore` — keeps cache directory tracked without generated cache files.

## Routes

- `routes/web.php` — currently defines only `GET /`.
- `routes/console.php` — console route definitions.

There is no `routes/api.php` yet. API routes for webhooks or review status will need to be added deliberately.

## Configuration

Config files are in `config/`, including:

- `config/app.php`
- `config/auth.php`
- `config/cache.php`
- `config/database.php`
- `config/filesystems.php`
- `config/logging.php`
- `config/mail.php`
- `config/queue.php`
- `config/services.php`
- `config/session.php`

`config/services.php` is the natural place to add GitHub and AI provider credentials.

## Database

- `database/factories/UserFactory.php` — default user factory.
- `database/seeders/DatabaseSeeder.php` — default database seeder.
- `database/migrations/0001_01_01_000000_create_users_table.php`
- `database/migrations/0001_01_01_000001_create_cache_table.php`
- `database/migrations/0001_01_01_000002_create_jobs_table.php`

The current database schema is Laravel infrastructure only.

## Frontend and Views

- `resources/views/welcome.blade.php` — default Laravel welcome page.
- `resources/js/app.js` — empty/default JS entry.
- `resources/css/app.css` — CSS entry configured for Tailwind/Vite.

No dashboard, review UI, settings UI, or auth pages exist yet.

## Public Assets

- `public/index.php` — front controller.
- `public/favicon.ico`
- `public/robots.txt`

## Storage

Laravel storage directories are present with `.gitignore` placeholders:

- `storage/app/`
- `storage/app/private/`
- `storage/app/public/`
- `storage/framework/cache/`
- `storage/framework/sessions/`
- `storage/framework/testing/`
- `storage/framework/views/`

No application artifacts are stored yet.

## Tests

- `tests/TestCase.php` — base Laravel test case.
- `tests/Feature/ExampleTest.php` — default homepage status test.
- `tests/Unit/ExampleTest.php` — default true assertion test.

## Suggested Future Organization

For the AI PR review product, likely additions:

- `app/Http/Controllers/Api/GitHubWebhookController.php`
- `app/Jobs/ReviewPullRequest.php`
- `app/Services/GitHub/`
- `app/Services/AiReview/`
- `app/Models/Repository.php`
- `app/Models/PullRequest.php`
- `app/Models/ReviewRun.php`
- `app/Models/ReviewFinding.php`
- `routes/api.php`
- `tests/Feature/GitHubWebhookTest.php`
- `tests/Feature/PullRequestReviewTest.php`
