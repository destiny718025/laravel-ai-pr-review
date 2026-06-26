---
last_mapped: 2026-06-26
focus: tech
---

# Codebase Stack

## Summary

This repository is a fresh Laravel application skeleton intended to become an AI PR review tool. The current codebase has the default Laravel web entry point, default user/auth persistence schema, default queue/cache/session infrastructure, Vite assets, and PHPUnit tests. No GitHub integration, AI provider integration, review domain model, or API surface has been implemented yet.

## Runtime

- PHP: `^8.3`, declared in `composer.json`.
- Framework: `laravel/framework` `^13.8`, declared in `composer.json`.
- Package manager: Composer, with lockfile at `composer.lock`.
- CLI entry point: `artisan`.
- Application bootstrap: `bootstrap/app.php`.

## PHP Dependencies

- Production dependencies:
  - `laravel/framework` `^13.8`
  - `laravel/tinker` `^3.0`
- Development dependencies:
  - `fakerphp/faker` for test factories.
  - `laravel/pail` for local log tailing.
  - `laravel/pao` for Laravel development tooling.
  - `laravel/pint` for PHP style formatting.
  - `mockery/mockery` for test doubles.
  - `nunomaduro/collision` for console error output.
  - `phpunit/phpunit` `^12.5.12` for tests.

## Frontend Tooling

- JavaScript package manager: npm, via `package.json`.
- Module type: ESM, via `"type": "module"` in `package.json`.
- Build tool: Vite `^8.0.0`.
- Laravel Vite plugin: `laravel-vite-plugin` `^3.1`.
- CSS framework: Tailwind CSS `^4.0.0` using `@tailwindcss/vite`.
- Asset entry points:
  - `resources/js/app.js`
  - `resources/css/app.css`
  - `vite.config.js`

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
  - users, password reset tokens, and sessions in `database/migrations/0001_01_01_000000_create_users_table.php`
  - cache and cache locks in `database/migrations/0001_01_01_000001_create_cache_table.php`
  - jobs, job batches, and failed jobs in `database/migrations/0001_01_01_000002_create_jobs_table.php`

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
