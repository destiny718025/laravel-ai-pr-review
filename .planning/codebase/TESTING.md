---
last_mapped: 2026-06-26
focus: quality
---

# Codebase Testing

## Summary

Testing is currently the default Laravel PHPUnit setup. There are two example tests and no product-specific test coverage yet. The testing environment is ready for feature tests around webhook ingestion, queued review jobs, GitHub API interactions, AI provider calls, and persistence.

## Test Framework

- PHPUnit is configured by `phpunit.xml`.
- Laravel test runner is invoked through `php artisan test`.
- Composer script `composer run test` clears config before running tests.
- Base test class is `tests/TestCase.php`.

## Test Suites

`phpunit.xml` defines two suites:

- Unit suite: `tests/Unit`
- Feature suite: `tests/Feature`

Current files:

- `tests/Unit/ExampleTest.php`
- `tests/Feature/ExampleTest.php`

## Current Test Coverage

The default feature test in `tests/Feature/ExampleTest.php` verifies that `GET /` returns HTTP 200.

The default unit test is a trivial assertion. There are no tests yet for:

- GitHub webhook signature validation.
- Pull request diff fetching.
- Diff normalization.
- AI review prompt construction.
- AI response parsing.
- Review comment generation.
- GitHub comment publication.
- Queue job behavior.
- Failure handling and retries.

## Testing Environment

`phpunit.xml` configures test-specific environment values:

- `APP_ENV=testing`
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- `CACHE_STORE=array`
- `QUEUE_CONNECTION=sync`
- `SESSION_DRIVER=array`
- `MAIL_MAILER=array`

This is a good fit for fast feature tests.

## Database Testing

Default migrations exist for users, sessions, cache, and jobs. Once product tables are added, feature tests should use Laravel's migration/test database tooling.

Recommended for future tests:

- Use `RefreshDatabase` in feature tests that touch persistence.
- Use model factories for repositories, pull requests, review runs, and findings.
- Keep AI provider responses faked so tests are deterministic.

## External Service Testing

No external clients exist yet. When GitHub and AI provider clients are introduced:

- Wrap provider calls behind application services.
- Use Laravel HTTP client fakes or explicit fake client interfaces.
- Test webhook signature validation with known fixture payloads.
- Store sample GitHub PR file payloads as fixtures, not as live network calls.
- Test AI response parsing against representative valid and invalid payloads.

## Queue Testing

The test environment uses `QUEUE_CONNECTION=sync`, so queued jobs can be asserted through synchronous execution. For review jobs:

- Unit-test pure diff and prompt builders separately.
- Feature-test the webhook-to-job flow.
- Job-test failure paths for rate limits, malformed AI responses, and GitHub API errors.

## Current Gaps

- No CI workflow file was found.
- No product-specific tests exist.
- No static analysis tool is configured.
- No test fixtures exist for GitHub payloads or PR diffs.
- No custom assertions or factories exist beyond `UserFactory`.
