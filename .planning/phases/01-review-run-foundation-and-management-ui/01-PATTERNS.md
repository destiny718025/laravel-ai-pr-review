# Phase 01 Pattern Map: Review Run Foundation and Management UI

## Existing Baseline

The repository is a fresh Laravel 13 skeleton with the default MVC directory layout and no custom review domain code yet. [VERIFIED: codebase]

Current request flow is a single closure route in `routes/web.php`: `GET /` returns the default `welcome` Blade view. [VERIFIED: codebase]

`app/Http/Controllers/Controller.php` exists only as the base controller; no controller-backed web routes are in use yet. [VERIFIED: codebase]

`app/Models/User.php` is the only application model and demonstrates the current model style: Laravel 13 PHP attributes for `Fillable` and `Hidden`, traits inside the class body, typed `casts()`, and no `declare(strict_types=1)`. [VERIFIED: codebase]

Existing migrations are anonymous migration classes that create Laravel infrastructure tables only: users/password reset tokens/sessions, cache/cache locks, jobs/job batches/failed jobs. [VERIFIED: codebase]

Tests are default PHPUnit/Laravel tests. The only feature test asserts `GET /` returns HTTP 200 and currently does not use `RefreshDatabase`. [VERIFIED: codebase]

Composer autoload maps `App\\` to `app/`, `Database\\Factories\\` to `database/factories/`, `Database\\Seeders\\` to `database/seeders/`, and `Tests\\` to `tests/`. [VERIFIED: codebase]

`phpunit.xml` runs Unit and Feature suites with SQLite `:memory:`, array cache/session/mail, and sync queues. [VERIFIED: codebase]

## File Pattern Map

| Planned file/class | Role | Closest existing analog | Pattern to follow | Notes |
| --- | --- | --- | --- | --- |
| `routes/web.php` | Web route entry points for dashboard, creation, and detail | Current `GET /` closure route | Keep route definitions in `routes/web.php`; replace homepage behavior with redirect to `/reviews`; use controller-backed routes for non-trivial behavior | Planned routes from phase context: `GET /`, `GET /reviews`, `POST /reviews`, `GET /reviews/{id}`. [VERIFIED: codebase] Controller-backed shape is [ASSUMED]. |
| `app/Http/Controllers/ReviewController.php` | HTTP adapter for review dashboard/create/detail | `app/Http/Controllers/Controller.php` base controller; no concrete analog exists | Extend base controller; keep HTTP validation, redirects, view selection, and flash/session messages here | Do not parse GitHub URLs or call Eloquent directly in controller. This is a new pattern for the codebase. [ASSUMED] |
| `app/Services/ReviewRunService.php` | Business workflow for creating a review run from a PR URL | No existing service analog | Constructor-inject parser/repositories; orchestrate parse -> find/create repository -> find/create pull request -> create review run | Service owns decisions and structured service errors; repositories own persistence. [ASSUMED] |
| `app/Services/GitHub/GitHubPullRequestUrlParser.php` or similar | Pure parser for GitHub PR URLs | No existing parser/value-object analog | Keep pure and unit-testable; use URL parsing plus path segment checks; return a DTO/result rather than throwing for expected invalid input | Accept `https://github.com/{owner}/{repo}/pull/{number}`; reject with stable codes `invalid_url`, `not_github_pr_url`, `missing_pr_number`. [ASSUMED] |
| `app/Data/GitHubPullRequestReference.php` or similar | Parsed PR URL value object | No existing DTO analog | Immutable/simple object with owner, repository name, PR number, and normalized source URL | Introduce only if it keeps service/controller code clearer; namespace is planner discretion. [ASSUMED] |
| `app/Data/ReviewRunCreationResult.php` or similar | Service result for success/failure | No existing DTO analog | Expose success/failure, optional `ReviewRun`, optional stable error code, and user-facing message | Enables UI message display while tests assert stable code. [ASSUMED] |
| `app/Enums/ReviewRunStatus.php` or model constants | Status vocabulary | No enum analog; `User::casts()` is the closest cast pattern | Define exact future-ready statuses: `pending`, `queued`, `running`, `completed`, `failed`, `cancelled` | Enum is useful if migrations/models/tests use it consistently; a string column with constants is also viable. [ASSUMED] |
| `app/Models/Repository.php` or `app/Models/GitHubRepository.php` | Eloquent model for GitHub repo identity | `app/Models/User.php` | Use Laravel model conventions from `User`; define fillable attributes, relationships, and explicit casts only where useful | Naming risk: `Repository` collides conceptually with repository-layer classes. Planner should choose clear class names/imports. [ASSUMED] |
| `app/Models/PullRequest.php` | Eloquent model for PR identity | `app/Models/User.php` | Follow model style; define `repository()` and `reviewRuns()` relationships | Owns repository relationship, PR number, source URL, and later metadata fields. [ASSUMED] |
| `app/Models/ReviewRun.php` | Eloquent model for one review attempt | `app/Models/User.php` | Follow model style; define `pullRequest()` relationship and status/lifecycle casts | Owns execution status and safe error display fields; list/detail should eager-load `pullRequest.repository`. [ASSUMED] |
| `app/Repositories/*Repository.php` | Persistence boundary for domain models | No existing repository-layer analog | Encapsulate Eloquent queries/writes; services call these instead of `Model::query()`/`create()` | Likely classes: repository identity persistence, pull request persistence, review run persistence. Exact names are planner discretion. [ASSUMED] |
| `database/migrations/*_create_repositories_table.php` | Domain table for repo identity | Default anonymous migrations | Use anonymous migration class; `Schema::create`; `id`, string columns, indexes, timestamps; `down()` drops table | Include owner/name/full_name or equivalent and a uniqueness strategy. [ASSUMED] |
| `database/migrations/*_create_pull_requests_table.php` | Domain table for PR identity | Default anonymous migrations | Use `foreignId(...)->constrained()` style if planner chooses it; add timestamps and indexes | Needs repository foreign key, numeric PR number, source URL, unique repository + number. [ASSUMED] |
| `database/migrations/*_create_review_runs_table.php` | Domain table for review attempts | Default anonymous migrations; jobs migration for future queue context | Store `pull_request_id`, `status`, nullable safe error/lifecycle fields, timestamps | Phase 1 creates `pending` runs only for valid submissions; invalid URLs create no records. [VERIFIED: codebase] |
| `resources/views/reviews/index.blade.php` | Dashboard with PR URL form and history | `resources/views/welcome.blade.php` | Blade-first UI; practical management-tool layout; form at top, recent runs below | No existing app layout/components; planner must decide layout extraction. [ASSUMED] |
| `resources/views/reviews/show.blade.php` | Detail shell for run metadata/status/errors | `resources/views/welcome.blade.php` | Blade view receives eager-loaded run; display identity, status, timestamps, source URL, safe failure message | Findings/comments/GitHub files are out of scope. [VERIFIED: codebase] |
| `tests/Feature/ExampleTest.php` | Existing homepage feature test | Itself | Update or replace because `/` should redirect to `/reviews`, not return 200 | Default assertion will become wrong once routes change. [VERIFIED: codebase] |
| `tests/Feature/ReviewRunManagementTest.php` or similar | End-to-end web/persistence behavior | `tests/Feature/ExampleTest.php` | Extend `Tests\TestCase`; add `RefreshDatabase` for persistence tests; use Laravel HTTP assertions | Cover no-login dashboard, valid submission, invalid errors/no records, history, detail shell. [ASSUMED] |
| `tests/Unit/GitHubPullRequestUrlParserTest.php` | Pure parser/error-code behavior | `tests/Unit/ExampleTest.php` | Extend `PHPUnit\Framework\TestCase` or Laravel `TestCase` as needed; no database | Assert accepted URL shapes and stable error codes. [ASSUMED] |
| `database/factories/*Factory.php` | Test data creation for domain models | `database/factories/UserFactory.php` | Use Laravel factory namespace/pattern if tests need repeated persisted runs | Helpful for history/detail failure-state tests; optional if tests create records through repositories/service. [ASSUMED] |

## Data Flow Pattern

Intended Phase 1 flow:

1. `GET /` resolves in `routes/web.php` and redirects to `/reviews`. [VERIFIED: codebase]
2. `GET /reviews` resolves to `ReviewController@index`, which asks a read boundary such as `ReviewRunRepository` for recent runs, then returns `resources/views/reviews/index.blade.php`. [ASSUMED]
3. `POST /reviews` resolves to `ReviewController@store`, which performs minimal HTTP shape validation for `pr_url`, then calls `ReviewRunService::createFromPullRequestUrl($url)`. [ASSUMED]
4. `ReviewRunService` calls the PR URL parser. Parser failures return stable service errors such as `invalid_url`, `not_github_pr_url`, or `missing_pr_number`; the service does not create database records for these failures. [ASSUMED]
5. For a valid PR URL, the service uses repository-layer classes to find/create the GitHub repository record, find/create the pull request record, and create a new `pending` review run. [ASSUMED]
6. On success, the controller redirects to `/reviews/{id}`. On service validation failure, it redirects back to `/reviews` with a user-facing message and stable code available to tests. [ASSUMED]
7. `GET /reviews/{id}` resolves to `ReviewController@show`, which loads the run with `pullRequest.repository` and returns the detail shell. [ASSUMED]

Layer responsibility pattern:

- Routes map URLs to controller actions only. [ASSUMED]
- Controllers handle HTTP input, redirects, views, and flash/session presentation. [ASSUMED]
- Services own workflow decisions, semantic validation, and status selection. [ASSUMED]
- Repositories own Eloquent query/write details. [ASSUMED]
- Models own relationships, casts, and table-backed domain state. [ASSUMED]

## Test Pattern Map

Feature tests:

- Existing feature tests live under `tests/Feature` and extend `Tests\TestCase`. [VERIFIED: codebase]
- Persistence-touching Phase 1 feature tests should import and use `Illuminate\Foundation\Testing\RefreshDatabase`; the default example currently comments this import out. [VERIFIED: codebase]
- `phpunit.xml` already configures SQLite `:memory:` and sync queues, so feature tests can migrate and assert database state quickly. [VERIFIED: codebase]
- Replace/update the default homepage test to assert `GET /` redirects to `/reviews`. [ASSUMED]
- Add dashboard test: `GET /reviews` returns 200 without auth and shows the PR URL form plus empty/history state. [ASSUMED]
- Add valid submission test: `POST /reviews` with a GitHub PR URL creates one repository, one pull request, one `pending` review run, and redirects to the detail route. [ASSUMED]
- Add duplicate submission test: same PR reuses repository/pull request records but creates a fresh review run. [ASSUMED]
- Add invalid submission tests for `invalid_url`, `not_github_pr_url`, and `missing_pr_number`; assert no records exist in `repositories`, `pull_requests`, or `review_runs`. [ASSUMED]
- Add history/detail tests: persisted runs show status, PR identity, source URL, timestamps, and safe failure message for a failed run prepared in the test. [ASSUMED]

Unit tests:

- Existing unit test location is `tests/Unit`; current default unit test is a trivial true assertion. [VERIFIED: codebase]
- Add parser tests that do not touch the database and assert stable accepted/rejected URL behavior. [ASSUMED]
- Add service tests with fake repositories only if repository interfaces or simple fakes are introduced; otherwise focused feature tests plus parser unit tests are likely enough for Phase 1. [ASSUMED]

Expected commands:

- Main verification command: `composer run test`, which clears config and runs `php artisan test`. [VERIFIED: codebase]
- If UI asset files are changed beyond Blade-only work, also run `npm run build`. [ASSUMED]

## Pattern Risks

- There is no existing service or repository-layer code, so the planner must explicitly define constructor injection, method names, and where service result/DTO classes live. [VERIFIED: codebase]
- `Repository` is both a domain concept and a persistence-pattern term; class naming can become confusing if the model is `Repository` and the persistence class is `RepositoryRepository`. [ASSUMED]
- There is no existing enum pattern. If `ReviewRunStatus` is an enum, the planner should specify casting and migration storage clearly; if constants are used, tests should protect the exact vocabulary. [ASSUMED]
- The skeleton only has route closures. Moving to controller-backed routes is correct for this phase, but it is a new local pattern and should not be left implicit. [VERIFIED: codebase]
- No app layout, Blade component system, or design convention exists beyond the default welcome page; UI work should stay practical and avoid overbuilding a design system. [VERIFIED: codebase]
- Semantic PR URL validation belongs in the service/parser, not only in Laravel form validation; otherwise stable error codes and no-record invalid submissions become brittle. [ASSUMED]
- Invalid URL submissions must not create `failed` review runs; failed runs represent real review attempts after creation. [VERIFIED: codebase]
- Phase 1 must not drift into GitHub API fetching, queue dispatch, AI provider interfaces, findings, comment drafts, posting comments, auth, teams, or webhooks. [VERIFIED: codebase]
- Foreign key cascade/restrict behavior has no local precedent in product tables; migrations should state the intended deletion behavior rather than accepting accidental defaults. [ASSUMED]
- Default `tests/Feature/ExampleTest.php` will fail after the homepage redirect unless updated or replaced. [VERIFIED: codebase]

## Provenance

- `.planning/phases/01-review-run-foundation-and-management-ui/01-CONTEXT.md` read for Phase 1 decisions D-01 through D-15, route shape, data-model boundaries, validation/error decisions, and out-of-scope items. Facts from this file are phase decisions; implementation guidance derived from them is [ASSUMED].
- `.planning/phases/01-review-run-foundation-and-management-ui/01-RESEARCH.md` read for planned files/classes, data model guidance, test strategy, and pitfalls. Research guidance not directly visible in code is [ASSUMED].
- `.planning/codebase/ARCHITECTURE.md` read for current boot/request flow, absent domain code, default layers, queue readiness, and API JSON readiness. [VERIFIED: codebase]
- `.planning/codebase/STRUCTURE.md` read for directory layout, existing app files, routes, config, database, views, assets, and tests. [VERIFIED: codebase]
- `.planning/codebase/CONVENTIONS.md` read for PHP style, Laravel 13 model conventions, routing/config/error conventions, Pint availability, and domain naming suggestions. [VERIFIED: codebase]
- `.planning/codebase/TESTING.md` read for PHPUnit setup, suites, current coverage, testing environment, and recommended `RefreshDatabase` usage. [VERIFIED: codebase]
- `routes/web.php` read; it currently defines only `GET /` returning `view('welcome')`. [VERIFIED: codebase]
- `app/Models/User.php` read; it uses `Fillable`/`Hidden` attributes, `HasFactory`, `Notifiable`, and typed `casts()`. [VERIFIED: codebase]
- `database/migrations/0001_01_01_000000_create_users_table.php` read for anonymous migration/table style. [VERIFIED: codebase]
- `database/migrations/0001_01_01_000001_create_cache_table.php` read for anonymous migration/table style. [VERIFIED: codebase]
- `database/migrations/0001_01_01_000002_create_jobs_table.php` read for queue table availability and anonymous migration/table style. [VERIFIED: codebase]
- `tests/Feature/ExampleTest.php` read; it asserts `GET /` returns HTTP 200 and does not use `RefreshDatabase`. [VERIFIED: codebase]
- `composer.json` read for PHP/Laravel versions, PSR-4 autoload roots, dev tools, and `composer run test` script. [VERIFIED: codebase]
- `phpunit.xml` read for Unit/Feature suites and test env values including SQLite `:memory:`, array cache/session/mail, and sync queue. [VERIFIED: codebase]
