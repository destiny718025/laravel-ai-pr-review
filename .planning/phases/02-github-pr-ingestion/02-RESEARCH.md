# Phase 2: GitHub PR Ingestion - Research

**Researched:** 2026-06-27
**Domain:** Laravel 13 GitHub pull request ingestion, snapshot persistence, and fakeable HTTP integration
**Confidence:** MEDIUM

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

### GitHub Access Scope
- **D-01:** Start with public GitHub pull requests only for Phase 2.
- **D-02:** Do not require a GitHub token for the first ingestion slice. Token/private repository support can be added later without changing the core fakeable client contract.
- **D-03:** Keep GitHub credentials out of the database and logs. If config keys are added, they must live in environment/config only.

### Ingestion Trigger
- **D-04:** Use a manual `Fetch` action for Phase 2 rather than automatically fetching GitHub data when a review run is created.
- **D-05:** The review run detail page is the natural place to expose the fetch action because Phase 1 already redirects successful submissions there.
- **D-06:** Phase 2 should not dispatch queued AI work. Queue-based review execution belongs to Phase 3.

### Stored Diff Data
- **D-07:** Store only the GitHub files API data needed for the next step: filename, patch, and sha.
- **D-08:** Do not parse patches into line/hunk targeting structures in Phase 2. Deeper comment-targeting normalization can be planned later when AI findings/drafts need exact line mapping.
- **D-09:** Preserve the raw patch string returned by GitHub files API in a database-backed model/repository so later phases can normalize or inspect it without calling GitHub again.

### GitHub Failure Behavior
- **D-10:** Distinguish GitHub failure categories with different safe error codes/messages instead of one generic failure.
- **D-11:** At minimum, planning should account for PR not found or unreadable, rate limit, token/auth failure if a token path exists, GitHub server/network failure, and malformed/unexpected GitHub responses.
- **D-12:** Failures should update the review run to `failed`, populate only `safe_error_message`, and avoid storing raw GitHub response bodies, headers, authorization values, or secrets.

### Test Fixture Strategy
- **D-13:** Use JSON fixture files for fake GitHub API responses.
- **D-14:** Fixtures should be reusable by later AI review tests, so keep them under a stable test fixture path and shape them close to GitHub API responses.
- **D-15:** Tests must fake GitHub responses and must not call the real GitHub API.

### the agent's Discretion
- Planner may decide exact class names, fixture directory names, migration/table names, and service method names, as long as Controller / Service / Repository layering is respected.
- Planner may decide whether the concrete public GitHub client uses Laravel HTTP client directly or a small wrapper, as long as application workflow depends on an interface and tests can fake it.

### Deferred Ideas (OUT OF SCOPE)
- Private repository support and required GitHub token setup are deferred.
- Automatic ingestion immediately after review run creation is deferred.
- Patch-to-hunk/line targeting normalization is deferred beyond Phase 2.
- Queue-based review execution remains Phase 3.
- Webhook-triggered review runs remain out of scope for v1 manual workflow validation.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| ARCH-05 | External GitHub and AI provider calls are hidden behind interfaces that can be faked in tests. [VERIFIED: codebase] | Bind a `GitHubClient` interface in `AppServiceProvider`, inject it into the ingestion service, and cover it with fixture-driven fakes. [CITED: https://laravel.com/docs/13.x/container] [CITED: https://laravel.com/docs/13.x/http-client] |
| GH-02 | System can fetch pull request metadata from GitHub through a GitHub client interface. [VERIFIED: codebase] | Use `GET /repos/{owner}/{repo}/pulls/{pull_number}` behind the interface and persist the snapshot needed by later phases. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28] |
| GH-03 | System can fetch pull request changed files and patch data through a GitHub client interface. [VERIFIED: codebase] | Use `GET /repos/{owner}/{repo}/pulls/{pull_number}/files` with pagination and persist `filename`, `patch`, and `sha` per locked scope. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28] |
| GH-04 | System stores enough diff metadata to later publish line-level comments, including file path, line, side, and commit SHA when available. [VERIFIED: codebase] | GitHub’s current review-comment API is line/side based and requires `commit_id` plus `path`; Phase 2 satisfies the preparatory part of this requirement by persisting raw `filename`, `patch`, and `sha` file snapshots plus `review_runs.github_head_sha`, while line/side normalization remains deferred by locked decisions D-07 and D-08. [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28] |
| GH-05 | System records a clear failure state when GitHub API calls fail or the PR cannot be read. [VERIFIED: codebase] | Map transport, rate-limit, unreadable/not-found, auth, and malformed-response failures to stable safe error codes/messages and mark the run `failed`. [CITED: https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api] [CITED: https://docs.github.com/en/rest/using-the-rest-api/troubleshooting-the-rest-api] |
| GH-06 | Tests can fake GitHub API responses without calling the real GitHub API. [VERIFIED: codebase] | Use JSON fixtures with `Http::fake`, `Http::response`, and `Http::preventStrayRequests` so no real GitHub network call can escape. [CITED: https://laravel.com/docs/13.x/http-client] |
</phase_requirements>

## Project Constraints (from AGENTS.md)

- Laravel `^13.8` on PHP `^8.3` is locked by the project stack. [VERIFIED: codebase]
- SQLite-first persistence remains the MVP default. [VERIFIED: codebase]
- AI review work should use queues later, but Phase 2 itself must not dispatch queued AI work. [VERIFIED: codebase]
- GitHub tokens and future AI credentials must stay in environment/config and out of database rows and logs. [VERIFIED: codebase]
- Human approval before posting GitHub comments is a product constraint, even though comment posting is out of scope here. [VERIFIED: codebase]
- Controller / Service / Repository layering is mandatory: controllers own HTTP concerns, services own workflow logic, repositories own persistence. [VERIFIED: codebase]
- `config/services.php` is the approved place for third-party credentials; application services should not call `env()` directly. [VERIFIED: codebase]
- Tests must fake external GitHub and AI calls. [VERIFIED: codebase]
- The repo has no project-defined skills under `.codex/skills/` or `.agents/skills/`. [VERIFIED: codebase]
- In this environment, host `php` and `composer` are unavailable, while Docker is installed; execution-time PHP/artisan commands therefore use `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test ...` and `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test`. [VERIFIED: codebase]

## Summary

Phase 2 should be planned as a snapshotting phase, not a diff-intelligence phase. The current codebase already has the Phase 1 primitives needed to support that shape: `ReviewController`, `ReviewRunService`, repository classes, `ReviewRunStatus`, and a review detail Blade page that can host a manual fetch action. No new Composer package is needed; Laravel 13.17.0 already provides the service container, HTTP client, and HTTP fake tooling required for a fakeable GitHub integration. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/container] [CITED: https://laravel.com/docs/13.x/http-client]

The strongest planning move is to add an explicit `GitHubClient` interface plus one concrete HTTP implementation that talks to GitHub’s REST pull-request endpoints. Fetch PR metadata first, then fetch paginated file data, then persist a review-run-owned snapshot of the upstream state. GitHub’s current review-comment API requires `commit_id`, `path`, `line`, and `side`, and is moving away from `position`, so the plan should store raw patch text, per-file SHA, and the latest PR head SHA now, while deferring hunk/line parsing to a later phase exactly as the locked decisions require. The mutable snapshot belongs to the review run via `review_runs` snapshot columns plus `review_run_files` rows, rather than overwriting shared pull-request identity records. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28] [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28]

Failure handling deserves its own explicit design. GitHub documents separate status/failure modes for unreadable resources, rate limits, and upstream failures, and Laravel’s HTTP client gives enough structure to convert those into stable safe error codes/messages without logging raw upstream bodies or credentials. Tests should use JSON fixture files plus `Http::preventStrayRequests()` so the phase proves fakeability end to end. [CITED: https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api] [CITED: https://docs.github.com/en/rest/using-the-rest-api/troubleshooting-the-rest-api] [CITED: https://laravel.com/docs/13.x/http-client]

**Primary recommendation:** Use Laravel’s built-in HTTP client behind a `GitHubClient` interface, trigger ingestion from a manual fetch action on the existing review detail page, persist review-run snapshots of PR metadata plus file `filename` / `patch` / `sha`, and map GitHub failures to stable safe run-failure states without adding third-party packages. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client] [CITED: https://laravel.com/docs/13.x/container] [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28]

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Manual `Fetch` action on the review detail page | Frontend Server (SSR) [ASSUMED] | Browser / Client [ASSUMED] | The existing Blade review detail page is already the user’s entry point, and the action is a normal server-rendered form submission rather than a client app concern. [VERIFIED: codebase] |
| GitHub request composition, pagination, and response validation | API / Backend [ASSUMED] | — | Outbound API behavior belongs in services and interface-backed clients, not in Blade or controllers. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/container] |
| Review run state transitions and safe error mapping | API / Backend [ASSUMED] | Database / Storage [ASSUMED] | The service layer should decide success/failure semantics; repositories should persist those decisions. [VERIFIED: codebase] |
| Persisting PR snapshots and changed-file records | Database / Storage [ASSUMED] | API / Backend [ASSUMED] | The durable artifact of this phase is stored snapshot data that later phases consume without re-calling GitHub. [VERIFIED: codebase] [ASSUMED] |
| Fixture-driven no-network tests | API / Backend [ASSUMED] | — | GitHub faking is a backend integration contract concern enforced by PHPUnit and Laravel’s HTTP fake features. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client] |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel framework | `v13.17.0` [VERIFIED: codebase] | Provides the service container, HTTP client, config system, Eloquent, Blade, and testing hooks used in this phase. [CITED: https://laravel.com/docs/13.x/container] [CITED: https://laravel.com/docs/13.x/http-client] | Already installed and sufficient; no extra GitHub package is required for public PR ingestion. [VERIFIED: codebase] [ASSUMED] |
| GitHub REST API | `2022-11-28` header version in current docs examples. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28] | Upstream contract for PR metadata, file lists, and later review comments. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28] [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28] | It is the canonical public API surface for pull requests and supports unauthenticated public-resource access. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28] |
| Eloquent + SQLite | Current app defaults. [VERIFIED: codebase] | Persist review-run snapshots, file rows, and failure state for local MVP execution. [VERIFIED: codebase] | Matches the project’s locked SQLite-first constraint and existing repository pattern. [VERIFIED: codebase] |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHPUnit | `12.5.30` [VERIFIED: codebase] | Feature and unit coverage for ingestion flows, failure mapping, and no-network guarantees. [VERIFIED: codebase] | Use for the phase’s main verification layer via Laravel test cases. [VERIFIED: codebase] |
| Laravel HTTP fake tooling | Built into `v13.17.0`. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client] | Fake GitHub responses from fixture files, assert request shape, and block stray network calls. [CITED: https://laravel.com/docs/13.x/http-client] | Use in every GitHub client and ingestion test. [CITED: https://laravel.com/docs/13.x/http-client] |
| Blade detail page + POST form | Existing app pattern. [VERIFIED: codebase] | Host the manual fetch action without introducing a JavaScript-heavy workflow. [VERIFIED: codebase] | Use because the Phase 1 detail page already exists and the user explicitly chose manual fetch there. [VERIFIED: codebase] |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Laravel HTTP client + interface [VERIFIED: codebase] | A third-party GitHub SDK package. [ASSUMED] | Extra dependency surface and new fake strategy without clear Phase 2 benefit for public-only REST calls. [ASSUMED] |
| Persisting raw patch/file snapshots now [VERIFIED: codebase] | Immediate hunk/line normalization tables. [ASSUMED] | Conflicts with locked scope D-07 and D-08, and risks designing around GitHub’s older `position` model too early. [VERIFIED: codebase] [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28] |
| Review-run-owned snapshot data via `review_runs` columns and `review_run_files` rows. [VERIFIED: codebase] | Mutating only the shared `pull_requests` row with every fetch. [ASSUMED] | Global mutation loses historical accuracy when a PR’s head SHA or file list changes between runs. [VERIFIED: codebase] |

**Installation:**

```bash
# No new Composer or npm packages are recommended for Phase 2.
```

**Version verification:** `laravel/framework` `v13.17.0` and `phpunit/phpunit` `12.5.30` were verified from `composer.lock`; the GitHub REST examples are documented against API version `2022-11-28`. [VERIFIED: codebase] [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28]

## Package Legitimacy Audit

No new external package is recommended for this phase; the standard stack uses framework capabilities already present in the repository. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client] [CITED: https://laravel.com/docs/13.x/container]

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| None | — | — | — | — | N/A | Approved to proceed with built-in Laravel capabilities only. [VERIFIED: codebase] |

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

```text
Browser (review detail page)
    |
    v
POST /reviews/{reviewRun}/fetch   [ASSUMED]
    |
    v
ReviewController@fetch            [ASSUMED]
    |
    v
PullRequestIngestionService
    |
    +--> GitHubClient interface
    |        |
    |        v
    |    HttpGitHubClient
    |        |
    |        +--> GET /repos/{owner}/{repo}/pulls/{number}
    |        |
    |        +--> GET /repos/{owner}/{repo}/pulls/{number}/files?page=n
    |
    +--> Success branch:
    |        persist PR snapshot + file snapshots
    |        update review run with fetched metadata state
    |
    +--> Failure branch:
             classify GitHub failure
             mark review run failed
             store safe error message only
    |
    v
SQLite-backed repositories
    |
    v
Redirect back to review detail with success or safe failure state
```

The diagram assumes a POST fetch endpoint on the existing review detail flow because that is the user’s locked interaction model, but the exact method/class names remain planner discretion. [VERIFIED: codebase] [ASSUMED]

### Recommended Project Structure

```text
app/
├── Contracts/GitHub/            # GitHub client interface boundary
├── Data/GitHub/                 # PR metadata / file snapshot DTOs
├── Http/Controllers/            # Existing review controller gains fetch action or delegates to dedicated action
├── Models/                      # Existing review models plus file snapshot model(s)
├── Repositories/                # Existing repositories extended for fetch-time persistence
├── Services/GitHub/             # Concrete HTTP client + failure mapper
└── Services/                    # Review-run ingestion orchestration service
tests/
├── Feature/                     # Manual fetch workflow, persistence, and safe failure tests
├── Fixtures/GitHub/             # JSON fixture payloads close to GitHub API shape
└── Unit/                        # Failure mapper / DTO / pure client behavior tests
```

This structure follows the repo’s existing `app/Repositories`, `app/Services`, `app/Data`, and Blade-first patterns while isolating GitHub concerns under a stable namespace. [VERIFIED: codebase] [ASSUMED]

### Pattern 1: Interface-Backed GitHub Client
**What:** Define an application-facing `GitHubClient` interface and bind it to one HTTP implementation in `AppServiceProvider`. [CITED: https://laravel.com/docs/13.x/container] [ASSUMED]

**When to use:** Use for every outbound GitHub call so tests can fake the boundary and services never depend directly on `Http` or raw response arrays. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client]

**Example:**

```php
<?php

// Source: https://laravel.com/docs/13.x/container

namespace App\Providers;

use App\Contracts\GitHub\GitHubClient;
use App\Services\GitHub\HttpGitHubClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GitHubClient::class, HttpGitHubClient::class);
    }
}
```

### Pattern 2: Two-Step Ingestion with Snapshot Persistence
**What:** Fetch the PR metadata document first, then page through the files endpoint, then persist both results as a single review-run snapshot. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28] [ASSUMED]

**When to use:** Use on the manual fetch action for a review run so later phases consume stored GitHub state instead of making fresh API calls during AI analysis. [VERIFIED: codebase] [ASSUMED]

**Example:**

```php
<?php

// Source: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28

$metadata = $gitHubClient->getPullRequest($owner, $repo, $number);
$files = $gitHubClient->listAllPullRequestFiles($owner, $repo, $number);

$reviewRunRepository->storeGitHubSnapshot(
    reviewRun: $reviewRun,
    metadata: $metadata,
    files: $files,
);
```

### Pattern 3: Fixture-Driven HTTP Fakes with Stray-Request Blocking
**What:** Load JSON fixtures from disk, wire them into `Http::fake`, and call `Http::preventStrayRequests()` before the application code runs. [CITED: https://laravel.com/docs/13.x/http-client]

**When to use:** Use in every Phase 2 test that reaches the concrete HTTP client or the full ingestion workflow. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client]

**Example:**

```php
<?php

// Source: https://laravel.com/docs/13.x/http-client

use Illuminate\Support\Facades\Http;

Http::preventStrayRequests();

Http::fake([
    'api.github.com/repos/*/pulls/*' => Http::response(
        json_decode(file_get_contents(base_path('tests/Fixtures/GitHub/pull-request.json')), true),
        200
    ),
    'api.github.com/repos/*/pulls/*/files*' => Http::response(
        json_decode(file_get_contents(base_path('tests/Fixtures/GitHub/pull-request-files-page-1.json')), true),
        200
    ),
]);
```

### Anti-Patterns to Avoid
- **Controller-owned GitHub calls:** Calling `Http::get()` directly from a controller would violate the project’s layering rule and make tests brittle. [VERIFIED: codebase]
- **Raw upstream error storage:** Saving GitHub response bodies or headers to `safe_error_message` would violate the project’s security constraint. [VERIFIED: codebase]
- **Position-first diff modeling:** GitHub’s review-comment docs are moving away from `position`; do not lock the schema around it. [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28]
- **Live API tests:** Any test that allows an actual GitHub HTTP request is out of bounds for Phase 2. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client]
- **Skipping pagination:** The files endpoint is paginated and documented to include up to 3000 files total; a single-page implementation is incomplete. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28]

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Outbound GitHub transport | Raw cURL wrappers. [ASSUMED] | Laravel HTTP client with configured base URL, headers, timeout, and retry policy. [CITED: https://laravel.com/docs/13.x/http-client] | Built-in request composition, fakes, assertions, and exceptions are already available. [CITED: https://laravel.com/docs/13.x/http-client] |
| Interface resolution | Manual service locators. [ASSUMED] | Laravel container bindings in `AppServiceProvider`. [CITED: https://laravel.com/docs/13.x/container] | Keeps constructor injection clean and test seams explicit. [CITED: https://laravel.com/docs/13.x/container] |
| Integration test doubles | Ad hoc mock servers or live GitHub sandboxes. [ASSUMED] | JSON fixtures + `Http::fake` + `Http::preventStrayRequests`. [CITED: https://laravel.com/docs/13.x/http-client] | Faster, deterministic, and aligned with the project’s fake-external-calls constraint. [VERIFIED: codebase] |
| Line-level diff mapping in Phase 2 | Custom patch parser and position tables now. [ASSUMED] | Store raw patch text and defer normalization to the later comment-generation phase. [VERIFIED: codebase] | The locked scope forbids deep diff parsing now, and GitHub’s current API expects line/side semantics later anyway. [VERIFIED: codebase] [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28] |
| Failure summarization | Free-text exception dumping. [ASSUMED] | Stable failure classifier that maps status/headers/exceptions to safe messages. [ASSUMED] | Prevents secret leakage and gives the UI/test suite exact behavior to assert. [VERIFIED: codebase] |

**Key insight:** Phase 2 should build a reliable, replayable GitHub snapshot boundary. It should not try to solve future comment-targeting math yet. [VERIFIED: codebase] [ASSUMED]

## Common Pitfalls

### Pitfall 1: Treating `404` as Only “PR Not Found”
**What goes wrong:** The app reports a missing PR when the real issue is unreadable/private access or bad authentication. [CITED: https://docs.github.com/en/rest/using-the-rest-api/troubleshooting-the-rest-api]

**Why it happens:** GitHub may return `404` for private resources when the request is not properly authenticated. [CITED: https://docs.github.com/en/rest/using-the-rest-api/troubleshooting-the-rest-api]

**How to avoid:** Name the failure category “not found or unreadable” for the public-only slice, and keep future auth-specific branching isolated behind the same client contract. [VERIFIED: codebase] [ASSUMED]

**Warning signs:** Tests only cover a happy-path `404` copy and ignore the locked future token/auth category. [VERIFIED: codebase] [ASSUMED]

### Pitfall 2: Forgetting Files Pagination
**What goes wrong:** Large pull requests appear partially ingested because only the first page of file results is stored. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28]

**Why it happens:** The files endpoint is paginated with `per_page` and `page`, and GitHub documents a maximum of 3000 files in the response set. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28]

**How to avoid:** Plan the client around an iterator or page loop from the start, even if most MVP PRs are small. [ASSUMED]

**Warning signs:** A client method named `getFiles()` that returns one raw response page without loop coverage. [ASSUMED]

### Pitfall 3: Letting Real Network Calls Escape the Test Suite
**What goes wrong:** Tests become slow, flaky, rate-limited, or unexpectedly hit the public GitHub API. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client]

**Why it happens:** The concrete client is exercised without `Http::fake`, or the fake rules do not match the actual request URLs. [CITED: https://laravel.com/docs/13.x/http-client]

**How to avoid:** Call `Http::preventStrayRequests()`, fake every expected GitHub URL pattern, and keep fixtures under a stable shared path. [CITED: https://laravel.com/docs/13.x/http-client] [VERIFIED: codebase]

**Warning signs:** Tests rely on environment tokens, or fail intermittently with GitHub rate-limit or DNS errors. [ASSUMED]

### Pitfall 4: Modeling for `position` Instead of `line` / `side`
**What goes wrong:** The schema or service API locks the app into an outdated comment-targeting shape that will need refactoring before comment publishing. [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28]

**Why it happens:** Older GitHub examples used diff `position`, but the current docs mark it as closing down and favor `line` plus `side`. [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28]

**How to avoid:** Store raw patch inputs and the latest head SHA now, and postpone line/side derivation until the phase that actually constructs comment drafts. [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28] [ASSUMED]

**Warning signs:** New columns or DTOs are named around “position” rather than “line/side” or “raw patch”. [ASSUMED]

### Pitfall 5: Writing Unsafe Upstream Failure Details into Review Runs
**What goes wrong:** Tokens, request headers, or raw GitHub error bodies leak into the database or UI. [VERIFIED: codebase]

**Why it happens:** Exception messages or raw response payloads are surfaced directly instead of being translated into safe summaries. [ASSUMED]

**How to avoid:** Centralize failure mapping, whitelist safe message text, and keep full raw responses out of persisted review-run fields. [VERIFIED: codebase] [ASSUMED]

**Warning signs:** `safe_error_message` is built from a thrown exception string or raw JSON body. [ASSUMED]

## Code Examples

Verified patterns from official sources:

### GitHub Request Baseline

```php
<?php

// Source: https://laravel.com/docs/13.x/http-client
// Source: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28

use Illuminate\Support\Facades\Http;

$response = Http::baseUrl('https://api.github.com')
    ->accept('application/vnd.github+json')
    ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
    ->timeout(10)
    ->get("/repos/{$owner}/{$repo}/pulls/{$number}");
```

### Interface Binding

```php
<?php

// Source: https://laravel.com/docs/13.x/container

$this->app->bind(
    \App\Contracts\GitHub\GitHubClient::class,
    \App\Services\GitHub\HttpGitHubClient::class,
);
```

### Fixture-Backed Fake with Assertions

```php
<?php

// Source: https://laravel.com/docs/13.x/http-client

use Illuminate\Support\Facades\Http;

Http::preventStrayRequests();

Http::fake([
    'api.github.com/repos/*/pulls/*' => Http::response($pullFixture, 200),
]);

$service->fetch($reviewRun);

Http::assertSent(fn ($request) =>
    $request->url() === 'https://api.github.com/repos/owner/repo/pulls/123'
);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Diff `position` as the primary review-comment locator. [ASSUMED] | `line` / `side` (and multi-line `start_line` / `start_side`) with `commit_id` and `path`. [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28] | Current GitHub REST docs mark `position` as closing down. [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28] | Do not design Phase 2 persistence around `position`-only targeting. [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28] |
| Live integration tests for upstream HTTP. [ASSUMED] | `Http::fake`, response sequences, request assertions, and stray-request prevention. [CITED: https://laravel.com/docs/13.x/http-client] | Current Laravel HTTP client docs. [CITED: https://laravel.com/docs/13.x/http-client] | Phase 2 can prove the GitHub contract without real network access. [CITED: https://laravel.com/docs/13.x/http-client] |

**Deprecated/outdated:**
- `position`-first comment targeting is outdated for new planning work. [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28]

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | GH-04 is satisfied in Phase 2 by persisting raw patch inputs plus `review_runs.github_head_sha`, while line/side normalization stays deferred by locked decisions D-07 and D-08. [VERIFIED: codebase] | Phase Requirements, Summary, Common Pitfalls | If this were wrong, later comment-targeting work would lack the required commit-sha prerequisite. |
| A2 | Snapshot data that can change across review runs is owned by the review run via `review_runs` snapshot columns and `review_run_files` rows, not only the shared `pull_requests` row. [VERIFIED: codebase] | Alternatives Considered, Architecture Patterns | If this were wrong, later fetches could overwrite the historical snapshot a review run actually used. |
| A3 | The execution-time PHP verification wrapper for this environment is `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test ...`, and the full suite wrapper is `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test`. [VERIFIED: codebase] | Environment Availability, Validation Architecture | If this were wrong, downstream verification steps would fail even when the plan was otherwise correct. |

## Open Questions (RESOLVED)

1. **GH-04 reconciliation with D-07 and D-08**
   - Resolution: Phase 2 fulfills GH-04 by storing raw `filename`, `patch`, and `sha` file snapshots plus `review_runs.github_head_sha`; line and side normalization is intentionally deferred by locked decisions D-07 and D-08. [VERIFIED: codebase] [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28]

2. **Mutable snapshot ownership**
   - Resolution: Mutable GitHub snapshot data is review-run-owned: review-run metadata lives on `review_runs` snapshot columns and per-file snapshots live in `review_run_files` rows, while durable repository and pull-request identity stay on the existing shared models. [VERIFIED: codebase]

3. **Containerized PHP test wrapper**
   - Resolution: The selected verification commands are `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test ...` for targeted runs and `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` for the full suite. [VERIFIED: codebase]

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Docker Engine | Containerized PHP/artisan execution in this environment. [VERIFIED: codebase] | ✓ [VERIFIED: codebase] | `29.4.0` [VERIFIED: codebase] | — |
| PHP CLI on host | Direct host execution of `artisan` / PHPUnit. [VERIFIED: codebase] | ✗ [VERIFIED: codebase] | — | Use `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test ...` instead. [VERIFIED: codebase] |
| Composer CLI on host | Direct host execution of `composer run test`. [VERIFIED: codebase] | ✗ [VERIFIED: codebase] | — | Use `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` instead. [VERIFIED: codebase] |
| Node.js | Existing asset tooling and any fixture-generation helpers. [VERIFIED: codebase] | ✓ [VERIFIED: codebase] | `v24.1.0` [VERIFIED: codebase] | — |
| npm | Existing frontend build tooling. [VERIFIED: codebase] | ✓ [VERIFIED: codebase] | `11.3.0` [VERIFIED: codebase] | — |
| GitHub REST API | Real manual fetches outside tests. [ASSUMED] | External dependency. [ASSUMED] | `2022-11-28` header version in docs. [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28] | Tests should use JSON fixtures and HTTP fakes. [VERIFIED: codebase] |

**Missing dependencies with no fallback:**
- None confirmed.

**Missing dependencies with fallback:**
- Host `php` and `composer` are missing; use the selected containerized PHP workflow instead. [VERIFIED: codebase]

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit `12.5.30` via Laravel test runner. [VERIFIED: codebase] |
| Config file | `phpunit.xml`. [VERIFIED: codebase] |
| Quick run command | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHub` [VERIFIED: codebase] |
| Full suite command | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` [VERIFIED: codebase] |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| ARCH-05 | GitHub calls resolve through an interface and can be replaced by a fake. [VERIFIED: codebase] | feature + unit [ASSUMED] | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHubClient` [VERIFIED: codebase] | ❌ Wave 0 |
| GH-02 | Manual fetch obtains PR metadata through the client boundary. [VERIFIED: codebase] | feature [ASSUMED] | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=PullRequestMetadata` [VERIFIED: codebase] | ❌ Wave 0 |
| GH-03 | Manual fetch paginates and stores changed-file data. [VERIFIED: codebase] | feature [ASSUMED] | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=PullRequestFiles` [VERIFIED: codebase] | ❌ Wave 0 |
| GH-04 | Persisted snapshot includes raw patch/file data and commit SHA prerequisites for later comments. [VERIFIED: codebase] | feature [ASSUMED] | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=DiffMetadata` [VERIFIED: codebase] | ❌ Wave 0 |
| GH-05 | GitHub failures mark the run `failed` with a safe summary only. [VERIFIED: codebase] | feature [ASSUMED] | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHubFailure` [VERIFIED: codebase] | ❌ Wave 0 |
| GH-06 | Tests use JSON fixtures and do not call real GitHub. [VERIFIED: codebase] | feature + unit [ASSUMED] | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHubFixture` [VERIFIED: codebase] | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=GitHub` [VERIFIED: codebase]
- **Per wave merge:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` [VERIFIED: codebase]
- **Phase gate:** Full PHPUnit suite green before `$gsd-verify-work`. [VERIFIED: codebase] [ASSUMED]

### Wave 0 Gaps
- [ ] `tests/Feature/GitHubPullRequestIngestionTest.php` — happy-path metadata + files ingestion. [ASSUMED]
- [ ] `tests/Feature/GitHubPullRequestIngestionFailureTest.php` — unreadable, rate-limit, malformed-response, and upstream-failure states. [ASSUMED]
- [ ] `tests/Fixtures/GitHub/pull-request.json` and paginated file fixtures — shared fake payloads for this and later phases. [VERIFIED: codebase] [ASSUMED]
- [ ] `tests/Unit/GitHub/GitHubFailureMapperTest.php` or equivalent pure-class coverage — keeps failure categorization cheap to test. [ASSUMED]

## Security Domain

Security enforcement is enabled in `.planning/config.json`. [VERIFIED: codebase]

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no [ASSUMED] | Public-only PR ingestion in Phase 2 does not add user authentication. [VERIFIED: codebase] |
| V3 Session Management | no [ASSUMED] | The fetch action reuses Laravel’s existing web session/CSRF stack; no new session model is introduced. [VERIFIED: codebase] [ASSUMED] |
| V4 Access Control | no [ASSUMED] | Personal-use MVP scope defers authz roles and policies. [VERIFIED: codebase] |
| V5 Input Validation | yes [ASSUMED] | Validate the review-run action input, normalize owner/repo/number, and validate GitHub response shape before persistence. [VERIFIED: codebase] [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28] |
| V6 Cryptography | no [ASSUMED] | No new cryptographic workflow is introduced; the security focus is secret handling and safe logging, not custom crypto. [VERIFIED: codebase] |

### Known Threat Patterns for Laravel + GitHub REST Ingestion

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| User-supplied PR URL abused as arbitrary outbound fetch target. [ASSUMED] | Tampering | Parse and normalize owner/repo/number, then call fixed GitHub API routes; never fetch the user-submitted URL directly. [VERIFIED: codebase] [ASSUMED] |
| GitHub token or raw error body leakage into logs or persisted review runs. [VERIFIED: codebase] | Information Disclosure | Keep credentials in config/env only and map failures to whitelisted safe messages. [VERIFIED: codebase] |
| Rate-limit exhaustion or retry storms. [CITED: https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api] | Denial of Service | Use bounded retries, inspect rate-limit signals, and fail safely when limits are reached. [CITED: https://laravel.com/docs/13.x/http-client] [ASSUMED] |
| Stored raw patch content later rendered unsafely. [ASSUMED] | Injection | Continue using escaped Blade output and avoid raw HTML rendering of patch text. [VERIFIED: codebase] [ASSUMED] |
| Malformed upstream response persisted as trusted data. [ASSUMED] | Tampering | Validate required keys before persistence and treat schema mismatch as a classified GitHub failure. [ASSUMED] |

## Sources

### Primary (HIGH confidence)
- Local codebase and planning artifacts: `AGENTS.md`, `.planning/phases/02-github-pr-ingestion/02-CONTEXT.md`, `.planning/REQUIREMENTS.md`, `.planning/ROADMAP.md`, `.planning/STATE.md`, `.planning/config.json`, `app/Http/Controllers/ReviewController.php`, `app/Services/ReviewRunService.php`, `app/Repositories/*.php`, `app/Models/*.php`, `resources/views/reviews/show.blade.php`, `routes/web.php`, `tests/Feature/*.php`, `composer.json`, `composer.lock`, and `phpunit.xml`. [VERIFIED: codebase]

### Secondary (MEDIUM confidence)
- Laravel HTTP client docs: https://laravel.com/docs/13.x/http-client
- Laravel service container docs: https://laravel.com/docs/13.x/container
- GitHub REST pull requests docs: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28
- GitHub REST pull request comments docs: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28
- GitHub REST rate limits docs: https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api
- GitHub REST troubleshooting docs: https://docs.github.com/en/rest/using-the-rest-api/troubleshooting-the-rest-api

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: MEDIUM - The repo state is verified locally, and the external GitHub/Laravel guidance comes from official docs fetched via web search rather than Context7. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client] [CITED: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28]
- Architecture: HIGH - The current controller/service/repository structure, routes, models, and views are directly present in the codebase. [VERIFIED: codebase]
- Pitfalls: MEDIUM - The risks are well supported by official GitHub/Laravel docs, but a few planning implications remain inference-driven because the locked scope intentionally defers line normalization. [CITED: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28] [CITED: https://laravel.com/docs/13.x/http-client] [ASSUMED]

**Research date:** 2026-06-27
**Valid until:** 2026-07-27
