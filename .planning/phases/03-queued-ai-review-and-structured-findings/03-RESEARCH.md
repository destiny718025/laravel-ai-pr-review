# Phase 3: Queued AI Review and Structured Findings - Research

**Researched:** 2026-06-28
**Domain:** Laravel 13 queued review execution, AI provider abstraction, structured output validation, and findings persistence
**Confidence:** MEDIUM

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

### Review Trigger
- **D-01:** Use a manual `Run AI Review` action for Phase 3 instead of automatically starting AI review immediately after GitHub `Fetch`.
- **D-02:** The review run detail page is the natural place for the manual `Run AI Review` action because it already shows review run identity, GitHub fetch status, safe failure state, and fetched file snapshot data.
- **D-03:** Planning should prevent AI review execution before the review run has GitHub snapshot data. The planner may decide whether the UI blocks the action, the service returns a safe validation error, or both.
- **D-04:** Phase 3 review execution must use Laravel queues. The HTTP request should enqueue work and return quickly instead of calling the AI provider inline.

### AI Provider Strategy
- **D-05:** Build the AI review provider interface and fake provider first. Tests should rely on the fake provider for deterministic local behavior.
- **D-06:** Reserve configuration for a future OpenAI adapter in `config/services.php` / environment variables, but do not make the Phase 3 MVP depend on live OpenAI calls.
- **D-07:** The concrete OpenAI adapter can be planned as a seam or stub if useful, but provider selection must remain behind an interface so the core review workflow does not depend directly on one vendor.
- **D-08:** External AI calls and provider payloads must be fakeable in tests and must not require network access.

### Structured Findings
- **D-09:** Persist structured review findings in Phase 3.
- **D-10:** Findings should include at least severity, category, file path, line reference when available, title, rationale, and suggested comment text.
- **D-11:** Phase 3 should include `suggested_comment_text` on findings because it is useful review output and prepares Phase 4, but it must not create editable comment draft records yet.
- **D-12:** Comment drafts, draft approval, draft status, and draft editing remain Phase 4 responsibilities.
- **D-13:** Default review instructions should prioritize bugs and security issues first, while allowing Laravel/PHP style feedback when useful and not noisy.

### Failure, Retry, and Safety
- **D-14:** Support safe AI review failure states for timeout/transport errors, invalid provider output, schema validation failure, and unexpected runtime failures.
- **D-15:** Failure messages shown or persisted on `review_runs.safe_error_message` must be safe summaries and must not include API keys, authorization headers, raw provider payloads, or unredacted secrets.
- **D-16:** Allow the same review run to be retried manually after an AI review failure.
- **D-17:** A successful retry should clear prior safe failure state and persist fresh findings for the current GitHub snapshot.
- **D-18:** Planning should define what happens to prior findings on retry. Preferred MVP behavior is replacing findings for the review run so the detail page reflects the latest execution attempt.

### the agent's Discretion
- Planner may decide exact class names, migration names, service method names, job names, and route names as long as Controller / Service / Repository layering is respected.
- Planner may choose whether review execution state transitions live in `ReviewRunRepository` or a dedicated execution repository, but database writes must stay in repository classes.
- Planner may choose the exact validation mechanism for AI output, provided invalid or incomplete output fails safely without malformed findings.
- Planner may decide whether to include a minimal OpenAI adapter stub in Phase 3 or leave it for a later AI integration pass, as long as config is reserved and the fake provider path is complete.

### Deferred Ideas (OUT OF SCOPE)
- Editable comment draft records and draft approval remain Phase 4.
- Posting comments back to GitHub remains Phase 5.
- Custom instructions management UI remains Phase 4.
- Real provider live-call verification can wait until after the fake provider workflow is stable.
- GitHub webhook automation remains out of scope for v1 manual workflow validation.
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| EXEC-01 | System dispatches review execution to a Laravel queued job instead of running AI review work inside the HTTP request. [VERIFIED: codebase] | Use a controller POST that delegates to a service, atomically marks the run `queued`, and dispatches the job with `->afterCommit()` so HTTP returns immediately and workers only see committed state. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/queues] |
| EXEC-02 | Review execution job loads the review run and marks it in progress before external work begins. [VERIFIED: codebase] | The job should take only `reviewRunId`, reload the model fresh, and set `running` plus `started_at` before calling the provider. Inject the execution service or provider into `handle()`, not into serialized constructor state. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/queues] [CITED: https://laravel.com/docs/13.x/container] |
| EXEC-03 | Review execution job marks the review run completed when findings and drafts are persisted. [VERIFIED: codebase] | For Phase 3 planning, interpret this as “completed when validated findings are persisted”; locked decisions D-11 and D-12 explicitly defer draft records to Phase 4. Completion and findings replacement should happen in one transaction. [VERIFIED: codebase] |
| EXEC-04 | Review execution job marks the review run failed with a safe summarized error when GitHub, AI, or parsing work fails. [VERIFIED: codebase] | Keep the existing safe-failure pattern: return stable error codes/messages from the service result, persist only the whitelisted `safe_error_message`, and distinguish provider transport, invalid JSON, schema failure, and unexpected runtime failures. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/queues] [CITED: https://laravel.com/docs/13.x/validation] |
| EXEC-05 | Review execution avoids logging raw API credentials, authorization headers, or unredacted provider payloads. [VERIFIED: codebase] | Reserve provider secrets in `config/services.php`, never persist raw provider bodies, and map exceptions to safe summaries only. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client] |
| AI-01 | System defines an AI review provider interface for generating structured review output. [VERIFIED: codebase] | Mirror the existing `GitHubClient` pattern with an `AIReviewProvider` contract bound in `AppServiceProvider`. The contract should expose generated structured review content behind the interface, not vendor-specific SDK types. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/container] |
| AI-02 | System includes a fake AI review provider for deterministic local tests. [VERIFIED: codebase] | Make the fake provider the default test path and drive it with fixed JSON payloads or fixed decoded arrays so feature tests stay deterministic and offline. [VERIFIED: codebase] |
| AI-03 | System can use one concrete AI provider implementation behind the provider interface. [VERIFIED: codebase] | Reserve `services.openai` config now and implement an opt-in `HttpOpenAIReviewProvider` in Plan `03-04`; Phase 3 stays fake-provider-first and must not require live OpenAI access. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client] [CITED: https://platform.openai.com/docs/guides/structured-outputs] |
| AI-04 | AI review output is validated against a structured finding schema before persistence. [VERIFIED: codebase] | Decode provider JSON with a hard failure on invalid JSON, then validate the decoded array with Laravel validation rules for allowed keys, required keys, and nested finding fields before any database write occurs. [CITED: https://laravel.com/docs/13.x/validation] |
| AI-05 | Structured findings include severity, category, file path, line reference when available, title, rationale, and suggested comment text. [VERIFIED: codebase] | Persist one findings table keyed to `review_run_id` with those exact fields; keep `line_reference` nullable and separate from future GitHub draft-targeting metadata because Phase 2 intentionally stopped at raw snapshot storage. [VERIFIED: codebase] |
| AI-06 | Default review instructions prioritize bug and security issues. [VERIFIED: codebase] | Put a default review-instructions builder in the service/provider seam and make “bugs and security first” the system instruction baseline. [VERIFIED: codebase] |
| AI-07 | Default review instructions allow Laravel/PHP style feedback when it is useful and not noisy. [VERIFIED: codebase] | Keep style feedback explicitly secondary in the default instructions so findings stay useful rather than verbose. [VERIFIED: codebase] |
| AI-08 | Invalid or incomplete AI output fails the review run safely without creating malformed findings. [VERIFIED: codebase] | Treat invalid JSON and schema validation failures as terminal safe failures, and do not insert or update findings unless the entire payload has passed decode plus validation. [CITED: https://laravel.com/docs/13.x/validation] [CITED: https://platform.openai.com/docs/guides/structured-outputs] |
</phase_requirements>

## Project Constraints (from AGENTS.md)

- Laravel `^13.8` on PHP `^8.3` is locked by the project stack. [VERIFIED: codebase]
- SQLite-first persistence remains the MVP default. [VERIFIED: codebase]
- AI review work must use Laravel queues rather than blocking HTTP requests. [VERIFIED: codebase]
- GitHub tokens and AI credentials must stay in environment/config and out of persisted review data and logs. [VERIFIED: codebase]
- Human approval before posting GitHub comments is a product constraint; Phase 3 therefore stops at findings and must not create publication behavior. [VERIFIED: codebase]
- Controller / Service / Repository layering is mandatory: controllers own HTTP concerns, services own workflow logic, repositories own persistence. [VERIFIED: codebase]
- `config/services.php` is the approved place for third-party credentials, and application services should not call `env()` directly. [VERIFIED: codebase]
- Tests must fake external GitHub and AI calls. [VERIFIED: codebase]
- The repo has no project-defined skills under `.codex/skills/` or `.agents/skills/`. [VERIFIED: codebase]
- In this environment, host `php` and `composer` are unavailable, while Docker plus the Laravel workspace container are available; PHP/artisan/composer verification commands should therefore run through `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 ...`. [VERIFIED: docker exec] [VERIFIED: codebase]

## Summary

Phase 3 should be planned as a queued execution pipeline layered on top of the existing Phase 1 and Phase 2 seams, not as a one-off controller action. The repo already has the right foundation for that shape: `ReviewRunStatus` includes `pending`, `queued`, `running`, `completed`, and `failed`; `ReviewRun` already tracks lifecycle timestamps plus `safe_error_message`; `ReviewRunRepository` already owns review-run writes; the review detail page already exists as the manual action surface; and the default queue driver is the database queue while tests already run with `QUEUE_CONNECTION=sync`. [VERIFIED: codebase]

The safest plan is a two-stage state machine. The manual `Run AI Review` POST should stay thin and delegate to a service that first enforces preconditions, then atomically marks the run `queued`, clears stale failure state, and dispatches a job with `->afterCommit()`. The queued job should reload the run by ID, mark it `running`, call the provider seam, decode provider JSON, validate the structured findings payload, and only then replace findings and mark the run `completed` inside one transaction. That keeps queue dispatch, execution, validation, and persistence coherent under the project’s Controller / Service / Repository rules. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/queues] [CITED: https://laravel.com/docs/13.x/container] [CITED: https://laravel.com/docs/13.x/validation]

The critical planning decisions are around failure taxonomy and retry behavior. Phase 3 should not depend on automatic worker retries for user-visible recovery; the repo’s local queue listener is already configured with `--tries=1`, and the locked decision is manual retry after failure. Plan explicit safe-failure paths for provider timeout/transport errors, invalid JSON, schema validation failure, and unexpected runtime errors, with only whitelisted summaries persisted. A successful rerun should clear stale failure state and replace any prior findings for that review run so the detail page always reflects the latest validated execution. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/queues] [CITED: https://platform.openai.com/docs/guides/structured-outputs]

**Primary recommendation:** Keep Phase 3 fake-first and queue-first: dispatch `Run AI Review` after commit, inject dependencies into the job `handle()`, validate provider JSON before any write, persist findings without draft records, and treat retry as a fresh queued execution that replaces the review run’s findings only after full validation succeeds. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/queues] [CITED: https://laravel.com/docs/13.x/validation]

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Manual `Run AI Review` action and readiness gating on the review detail page | Frontend Server (SSR) | Browser / Client | The current app uses Blade detail pages and normal POST forms, so the user interaction starts in SSR while the browser simply submits the action. [VERIFIED: codebase] |
| Queue dispatch, state preconditions, and retry admission rules | API / Backend | Database / Storage | The service layer should decide when a run may move to `queued`, and repository classes should persist those transitions atomically. [VERIFIED: codebase] |
| Provider invocation, JSON decode, schema validation, and failure classification | API / Backend | — | These are business workflow concerns behind an interface and belong in services plus provider adapters, not in controllers or Blade views. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/container] [CITED: https://laravel.com/docs/13.x/validation] |
| Findings replacement and lifecycle timestamp persistence | Database / Storage | API / Backend | The durable output of the phase is stored findings plus review-run state, and the project explicitly assigns database writes to repository classes. [VERIFIED: codebase] |
| Safe error display and retry affordance on failure | Frontend Server (SSR) | API / Backend | The view should reflect whitelisted safe messages and manual retry controls, but the message generation itself stays in the service layer. [VERIFIED: codebase] |
| Dispatch-boundary tests and sync execution tests | API / Backend | — | PHPUnit plus Laravel test helpers own verification of queue dispatch, execution, validation, and failure behavior. [VERIFIED: codebase] |

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel framework | `v13.17.0` [VERIFIED: codebase] | Supplies queued jobs, container bindings, validation, Eloquent transactions, and HTTP fakes used across this phase. [CITED: https://laravel.com/docs/13.x/queues] [CITED: https://laravel.com/docs/13.x/container] [CITED: https://laravel.com/docs/13.x/validation] | Already installed and sufficient; no new package is required to build the queued AI review seam. [VERIFIED: codebase] |
| Laravel database queue | Built into `laravel/framework v13.17.0`. [VERIFIED: codebase] | Runs review execution asynchronously with the repo’s existing `jobs` table and default `database` queue connection. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/queues] | Matches the project constraint that AI work must not block HTTP requests. [VERIFIED: codebase] |
| Eloquent + SQLite | Current app defaults. [VERIFIED: codebase] | Persist review-run status, lifecycle timestamps, and structured findings for the local MVP. [VERIFIED: codebase] | Fits the SQLite-first project constraint and existing repository pattern. [VERIFIED: codebase] |
| PHP runtime | Composer requires `^8.3`; workspace container currently runs `PHP 8.5.7`. [VERIFIED: codebase] [VERIFIED: docker exec] | Executes the Laravel app and test suite. [VERIFIED: docker exec] | The container runtime satisfies the project floor and is the actual execution target in this environment. [VERIFIED: docker exec] |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHPUnit | `12.5.30` [VERIFIED: codebase] | Feature and unit coverage for dispatch, execution, validation, failure mapping, and retry behavior. [VERIFIED: codebase] | Use for all Nyquist verification in this phase. [VERIFIED: codebase] |
| Laravel HTTP client | Built into `laravel/framework v13.17.0`. [VERIFIED: codebase] | Supports the opt-in `HttpOpenAIReviewProvider` seam and enables offline adapter tests through fakes. [CITED: https://laravel.com/docs/13.x/http-client] | Use in Plan `03-04` for the optional concrete adapter while keeping Phase 3 fake-first. [VERIFIED: codebase] |
| Laravel queue test helpers | Built into `laravel/framework v13.17.0`. [VERIFIED: codebase] | Support `Queue::fake`, dispatch assertions, and selective execution boundaries. [VERIFIED: codebase] | Use for dispatch-boundary tests; use sync execution for end-to-end job behavior tests. [VERIFIED: codebase] |
| Laravel validator | Built into `laravel/framework v13.17.0`. [VERIFIED: codebase] | Validates decoded provider payloads with allowed keys, required keys, and nested finding rules. [CITED: https://laravel.com/docs/13.x/validation] | Use immediately after JSON decode and before any persistence. [CITED: https://laravel.com/docs/13.x/validation] |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Service-driven queue dispatch plus queued job orchestration. [VERIFIED: codebase] | Inline controller-to-provider execution. | Violates the queueing requirement and makes status transitions, retries, and safe failure handling harder to test. [VERIFIED: codebase] |
| Provider returns raw JSON string, then the app decodes and validates it centrally. [CITED: https://laravel.com/docs/13.x/validation] | Provider returns already-persistable Eloquent-ready arrays or models. | Central decode+validate keeps invalid JSON and schema failure distinct, while pushing ready-to-persist data through the provider hides a critical safety boundary. [CITED: https://platform.openai.com/docs/guides/structured-outputs] |
| String columns with validator-owned vocabularies for `severity` and `category`. [VERIFIED: codebase] | Database enum columns. | String columns keep SQLite-friendly migrations and easier vocabulary evolution, while validation still enforces allowed values. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/validation] |
| `line_reference` as a nullable display-oriented field on findings. [VERIFIED: codebase] | Draft-ready GitHub `line` / `side` / `commit_id` targeting columns in Phase 3. | Phase 2 intentionally stopped at raw GitHub file snapshots, so Phase 3 should not pretend it already has exact publishable line-target metadata. [VERIFIED: codebase] |

**Installation:**

```bash
# No new Composer or npm packages are recommended for Phase 3.
```

**Version verification:** `laravel/framework` `v13.17.0`, `phpunit/phpunit` `12.5.30`, and `laravel/pint` `v1.29.3` were verified from `composer.lock`; the workspace container provides `PHP 8.5.7` and `Composer 2.10.1`. [VERIFIED: codebase] [VERIFIED: docker exec]

## Package Legitimacy Audit

No new external package is recommended for this phase; the standard stack uses framework capabilities already present in the repository. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/queues] [CITED: https://laravel.com/docs/13.x/validation]

| Package | Registry | Age | Downloads | Source Repo | Verdict | Disposition |
|---------|----------|-----|-----------|-------------|---------|-------------|
| None | — | — | — | — | N/A | Approved to proceed with built-in Laravel capabilities only. [VERIFIED: codebase] |

**Packages removed due to [SLOP] verdict:** none
**Packages flagged as suspicious [SUS]:** none

## Architecture Patterns

### System Architecture Diagram

```text
Browser on review detail page
    |
    v
POST /reviews/{reviewRun}/run
    |
    v
ReviewController@run
    |
    v
ReviewExecutionDispatchService
    |
    +--> Precondition gate:
    |        - GitHub snapshot exists
    |        - status is retryable/dispatchable
    |        - not already queued/running
    |
    +--> ReviewRunRepository
    |        - mark queued
    |        - clear stale failure state
    |
    +--> dispatch(new ExecuteReviewRunJob($reviewRunId))->afterCommit()
    |
    v
Queue worker
    |
    v
ExecuteReviewRunJob
    |
    +--> mark running
    |
    +--> AIReviewProvider interface
    |        |
    |        +--> FakeAIReviewProvider
    |        |
    |        +--> optional HttpOpenAIReviewProvider seam
    |
    +--> decode provider JSON
    +--> validate findings schema
    |
    +--> Success transaction:
    |        - replace findings for review run
    |        - mark completed
    |
    +--> Failure transaction:
             - preserve/replace no findings
             - mark failed
             - persist safe summary only
    |
    v
SQLite-backed repositories and review detail page refresh
```

The diagram follows the locked manual review action, the existing SSR detail page, and the repo’s Controller / Service / Repository split. [VERIFIED: codebase]

### Recommended Project Structure

```text
app/
├── Contracts/AI/               # AI review provider interface
├── Data/AI/                    # provider result, failure, and findings payload DTOs
├── Http/Controllers/           # manual Run AI Review entrypoint
├── Jobs/                       # queued review execution job
├── Models/                     # review finding model plus existing review run models
├── Repositories/               # review-run state transitions and findings persistence
├── Services/AI/                # fake provider, optional HTTP adapter, decode/validation helpers
└── Services/                   # dispatch service and execution orchestration
database/
├── factories/                  # review run / finding factories for tests
└── migrations/                 # findings table + any review-run adjustments
tests/
├── Feature/                    # dispatch, execution, retry, and failure flows
├── Fixtures/AI/                # fake provider JSON fixtures if used
└── Unit/AI/                    # validation and failure-classification tests
```

This structure extends the repo’s existing `Contracts`, `Data`, `Repositories`, `Services`, and feature-test patterns without introducing a new package or architectural style. [VERIFIED: codebase]

### Recommended Findings Persistence Shape

| Field | Recommended Type | Notes |
|-------|------------------|-------|
| `review_run_id` | `foreignId` | Required owner of all findings; cascade on delete with the run. [VERIFIED: codebase] |
| `severity` | `string` | Validate against the app-owned vocabulary `critical`, `high`, `medium`, `low` in Laravel rules rather than a database enum. [CITED: https://laravel.com/docs/13.x/validation] |
| `category` | `string` | Validate against the app-owned vocabulary `bug`, `security`, `performance`, `maintainability`, `style`, with bug/security prioritized in default instructions. [VERIFIED: codebase] |
| `file_path` | `string` | Store the GitHub snapshot filename that the finding refers to. [VERIFIED: codebase] |
| `line_reference` | `string` nullable | Keep this human-facing and optional; Phase 3 is not yet the phase for GitHub `line` / `side` targeting metadata. [VERIFIED: codebase] |
| `title` | `string` | Short user-visible summary for the finding. [VERIFIED: codebase] |
| `rationale` | `text` | Explanation of why the issue matters. [VERIFIED: codebase] |
| `suggested_comment_text` | `text` | Future draft seed only; do not create draft rows in Phase 3. [VERIFIED: codebase] |
| `timestamps` | Laravel defaults | Useful for auditability and later UI display. [VERIFIED: codebase] |

**Recommendation:** Replace all findings for a review run in one transaction on successful execution rather than attempting per-finding patch updates. That keeps retry behavior simple and matches D-18. [VERIFIED: codebase]

### Pattern 1: After-Commit Dispatch Boundary
**What:** Mark the review run `queued` in the repository, then dispatch the queued job with `->afterCommit()` so workers never observe stale status or half-written state. [CITED: https://laravel.com/docs/13.x/queues]

**When to use:** Use for the manual `Run AI Review` action and for any later retry entrypoint. [VERIFIED: codebase]

**Example:**

```php
<?php

// Source: https://laravel.com/docs/13.x/queues

ExecuteReviewRunJob::dispatch($reviewRun->id)->afterCommit();
```

### Pattern 2: Thin Job Payload, Rich `handle()` Injection
**What:** Serialize only the scalar review-run identifier in the job constructor and let Laravel resolve services and providers inside `handle()`. [CITED: https://laravel.com/docs/13.x/queues] [CITED: https://laravel.com/docs/13.x/container]

**When to use:** Use for every queued execution job so service dependencies are fresh and non-serializable objects never enter the queue payload. [CITED: https://laravel.com/docs/13.x/queues]

**Example:**

```php
<?php

// Source: https://laravel.com/docs/13.x/queues

use App\Services\ReviewExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;

class ExecuteReviewRunJob implements ShouldQueue
{
    public function __construct(public int $reviewRunId) {}

    public function handle(ReviewExecutionService $service): void
    {
        $service->execute($this->reviewRunId);
    }
}
```

### Pattern 3: Decode, Validate, Then Persist
**What:** Treat provider output as untrusted until JSON decode and Laravel validation succeed; only after that may the repository replace findings and mark the run completed. [CITED: https://laravel.com/docs/13.x/validation] [CITED: https://platform.openai.com/docs/guides/structured-outputs]

**When to use:** Use for both fake and future concrete providers so malformed output fails the same way regardless of transport. [VERIFIED: codebase]

**Example:**

```php
<?php

// Source: https://laravel.com/docs/13.x/validation

use Illuminate\Support\Facades\Validator;

$payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

Validator::make($payload, [
    'findings' => ['required', 'array'],
    'findings.*' => [
        'required',
        'array:severity,category,file_path,line_reference,title,rationale,suggested_comment_text',
        'required_array_keys:severity,category,file_path,title,rationale,suggested_comment_text',
    ],
    'findings.*.severity' => ['required', 'string'],
    'findings.*.category' => ['required', 'string'],
    'findings.*.file_path' => ['required', 'string'],
    'findings.*.line_reference' => ['nullable', 'string'],
    'findings.*.title' => ['required', 'string'],
    'findings.*.rationale' => ['required', 'string'],
    'findings.*.suggested_comment_text' => ['required', 'string'],
])->stopOnFirstFailure()->validate();
```

### Pattern 4: Result Object + Failure Mapper
**What:** Return a stable result object from dispatch/execution services and keep whitelisted safe error strings in one mapper rather than spreading error text across controllers, jobs, and repositories. [VERIFIED: codebase]

**When to use:** Use for both the manual dispatch service and the queued execution workflow so feature tests can assert exact user-facing behavior. [VERIFIED: codebase]

**Example:**

```php
<?php

// Source: local codebase pattern in App\Data\GitHub\PullRequestIngestionResult

return ReviewExecutionResult::failure(
    reviewRun: $reviewRun,
    code: 'invalid_schema',
    message: 'AI review returned incomplete findings. Try running the review again.',
);
```

### Anti-Patterns to Avoid
- **Dispatching inside an uncommitted transaction without `afterCommit()`:** A worker can see stale state or race ahead of persisted queue-admission changes. [CITED: https://laravel.com/docs/13.x/queues]
- **Serializing providers, repositories, or fully-hydrated models into the job payload:** Queue payloads should stay small and reload fresh state at execution time. [CITED: https://laravel.com/docs/13.x/queues]
- **Creating draft records in Phase 3:** Locked decisions D-11 and D-12 reserve draft rows and editing workflows for Phase 4. [VERIFIED: codebase]
- **Persisting findings before the full payload has validated:** Partial writes would make retry semantics and UI behavior inconsistent. [CITED: https://laravel.com/docs/13.x/validation]
- **Treating `Queue::fake` tests as sufficient execution coverage:** Dispatch assertion tests and sync execution tests cover different failure classes and both are needed. [VERIFIED: codebase]

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Background execution | Custom async runner or controller-owned sleep/poll loop. | Laravel queued jobs with the existing database queue. [CITED: https://laravel.com/docs/13.x/queues] | The framework already provides dispatch, lifecycle hooks, retries, and failure handling. [CITED: https://laravel.com/docs/13.x/queues] |
| Dependency selection | Controller-local switch statements or service locators for provider choice. | Container bindings in `AppServiceProvider`. [CITED: https://laravel.com/docs/13.x/container] | The existing GitHub seam already uses this pattern and it keeps the provider boundary fakeable. [VERIFIED: codebase] |
| Structured payload validation | Manual nested `isset()` / `is_string()` loops scattered across services. | Laravel validator with nested array rules, allowed keys, and required keys. [CITED: https://laravel.com/docs/13.x/validation] | Central validation is easier to test, easier to evolve, and produces a single safe failure boundary. [CITED: https://laravel.com/docs/13.x/validation] |
| Dispatch assertions | Homegrown spies around the queue manager. | Laravel queue fakes and assertions, plus sync-queue feature tests. [VERIFIED: codebase] | The framework already exposes `Queue::fake`, `assertPushed`, and selective `except()` behavior. [VERIFIED: codebase] |
| Provider transport | A new SDK dependency just to reserve an OpenAI seam. | Built-in Laravel HTTP client for any optional adapter stub, or no adapter at all in Phase 3 if the planner keeps it deferred. [CITED: https://laravel.com/docs/13.x/http-client] | This phase does not need live provider traffic to prove the core workflow. [VERIFIED: codebase] |
| Retry state cleanup | Ad hoc per-field resets spread across controllers, jobs, and views. | Repository methods that atomically reset stale failure/execution fields on `queued` transition. [VERIFIED: codebase] | Retry safety is a persistence concern and should be implemented once in the repository layer. [VERIFIED: codebase] |

**Key insight:** The hard part of Phase 3 is not “calling AI”; it is guaranteeing that queued execution, schema validation, retry, and safe failure behavior remain deterministic even when the provider returns bad data or no data at all. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/validation]

## Common Pitfalls

### Pitfall 1: Dispatching the Job Before the `queued` Transition Commits
**What goes wrong:** The worker starts with a stale `pending` or `failed` record, or sees old timestamps/error state. [VERIFIED: codebase]

**Why it happens:** Queue dispatch and status persistence are not tied together with an after-commit boundary. [CITED: https://laravel.com/docs/13.x/queues]

**How to avoid:** Have the service persist `queued` first and dispatch with `->afterCommit()`. [CITED: https://laravel.com/docs/13.x/queues]

**Warning signs:** Tests pass with `QUEUE_CONNECTION=sync` but race conditions appear under the real database worker. [VERIFIED: codebase]

### Pitfall 2: Letting Job Constructors Capture Too Much State
**What goes wrong:** The queue payload contains stale models, unserializable services, or provider state that should have been resolved fresh. [CITED: https://laravel.com/docs/13.x/queues]

**Why it happens:** It is tempting to inject the provider, repository, or loaded review run into the constructor instead of using `handle()` injection. [CITED: https://laravel.com/docs/13.x/container]

**How to avoid:** Store only the review-run ID in the job and resolve services inside `handle()`. [CITED: https://laravel.com/docs/13.x/queues]

**Warning signs:** Queue payloads become large, or tests fail with serialization errors. [CITED: https://laravel.com/docs/13.x/queues]

### Pitfall 3: Validating Findings Incrementally Instead of as One Payload
**What goes wrong:** Some malformed findings may be skipped while others are persisted, leaving the review run in a misleading “completed” state. [CITED: https://laravel.com/docs/13.x/validation]

**Why it happens:** Validation is attempted inside per-finding loops instead of against the decoded provider payload before any write. [CITED: https://laravel.com/docs/13.x/validation]

**How to avoid:** Decode once, validate the entire payload once, then replace findings in one transaction. [CITED: https://laravel.com/docs/13.x/validation]

**Warning signs:** Code deletes/inserts findings inside a loop or marks the run completed before the full payload is validated. [VERIFIED: codebase]

### Pitfall 4: Using Queue Fakes for Tests That Should Execute the Job
**What goes wrong:** Dispatch wiring is covered, but the actual `running` / `completed` / `failed` lifecycle, JSON decode path, and findings replacement logic remain untested. [VERIFIED: codebase]

**Why it happens:** `Queue::fake` is excellent for boundary assertions, but it intentionally prevents real job execution unless selectively bypassed. [VERIFIED: codebase]

**How to avoid:** Split tests into two layers: dispatch-boundary tests with queue fakes, and sync-backed feature tests that actually run the job. [VERIFIED: codebase]

**Warning signs:** The suite asserts “job was pushed” but never asserts `started_at`, `completed_at`, `failed_at`, or database findings rows. [VERIFIED: codebase]

### Pitfall 5: Persisting Unsafe Provider Failures
**What goes wrong:** API keys, authorization headers, raw provider JSON, or exception text leak into `safe_error_message` or logs. [VERIFIED: codebase]

**Why it happens:** Exception messages are dumped directly instead of being mapped to fixed safe summaries. [VERIFIED: codebase]

**How to avoid:** Centralize provider failure mapping and whitelist every persisted safe message. [VERIFIED: codebase]

**Warning signs:** `safe_error_message` is built from `$throwable->getMessage()` or raw response bodies. [VERIFIED: codebase]

### Pitfall 6: Retrying Without Clearing Stale Failure State
**What goes wrong:** A successful rerun still displays the old failure summary, or old timestamps make lifecycle order ambiguous. [VERIFIED: codebase]

**Why it happens:** Retry only dispatches a new job without resetting `safe_error_message`, `failed_at`, or stale execution timestamps. [VERIFIED: codebase]

**How to avoid:** The `queued` transition should clear stale failure state and reset execution timestamps for the new attempt. [VERIFIED: codebase]

**Warning signs:** The run is `queued` or `running` while `failed_at` or an old safe error message remains populated. [VERIFIED: codebase]

## Code Examples

Verified patterns from official sources:

### After-Commit Queue Dispatch

```php
<?php

// Source: https://laravel.com/docs/13.x/queues

ExecuteReviewRunJob::dispatch($reviewRunId)->afterCommit();
```

### Service-Container Interface Binding

```php
<?php

// Source: https://laravel.com/docs/13.x/container

$this->app->bind(
    \App\Contracts\AI\AIReviewProvider::class,
    \App\Services\AI\FakeAIReviewProvider::class,
);
```

### Queue Dispatch Assertion

```php
<?php

// Source: local Laravel framework Queue facade / QueueFake

use Illuminate\Support\Facades\Queue;

Queue::fake();

$this->post(route('reviews.run', $reviewRun))
    ->assertRedirect(route('reviews.show', $reviewRun));

Queue::assertPushed(ExecuteReviewRunJob::class);
```

### Nested Findings Validation

```php
<?php

// Source: https://laravel.com/docs/13.x/validation

Validator::make($payload, [
    'findings' => ['required', 'array'],
    'findings.*' => ['required', 'array', 'required_array_keys:severity,category,file_path,title,rationale,suggested_comment_text'],
    'findings.*.line_reference' => ['nullable', 'string'],
])->stopOnFirstFailure()->validate();
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Long-running provider work inside the HTTP request. | Queued job dispatch with explicit execution lifecycle state. [CITED: https://laravel.com/docs/13.x/queues] | Current Laravel queue guidance. [CITED: https://laravel.com/docs/13.x/queues] | Phase 3 should treat review execution as asynchronous workflow state, not a controller subroutine. [CITED: https://laravel.com/docs/13.x/queues] |
| Free-form “JSON mode” trust without local schema validation. | Strict structured output generation plus local decode and schema validation before persistence. [CITED: https://platform.openai.com/docs/guides/structured-outputs] [CITED: https://laravel.com/docs/13.x/validation] | Current OpenAI structured-output guidance and current Laravel validation primitives. [CITED: https://platform.openai.com/docs/guides/structured-outputs] [CITED: https://laravel.com/docs/13.x/validation] | Even if a provider claims JSON structure, the app should still reject invalid JSON or incomplete findings locally. [CITED: https://platform.openai.com/docs/guides/structured-outputs] [CITED: https://laravel.com/docs/13.x/validation] |
| Immediate draft creation as soon as AI returns comments. | Findings-only persistence first, with drafts deferred to a later phase. [VERIFIED: codebase] | Locked Phase 3 boundary. [VERIFIED: codebase] | Prevents this phase from mixing execution concerns with editing/approval concerns. [VERIFIED: codebase] |

**Deprecated/outdated:**
- Inline AI execution in controller/request flow is outdated for this phase’s requirements. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/queues]
- Trusting provider output without local decode plus validation is outdated for safe structured-review persistence. [CITED: https://platform.openai.com/docs/guides/structured-outputs] [CITED: https://laravel.com/docs/13.x/validation]

## Assumptions Log

All claims in this research were verified from the codebase or cited from official documentation. No `[ASSUMED]` claims remain.

## Open Questions (RESOLVED)

1. **OpenAI adapter scope**
   - Resolution: Phase 3 includes an opt-in `HttpOpenAIReviewProvider` behind `AIReviewProvider`, with reserved `services.openai.*` config and HTTP-fakeable tests in Plan `03-04`. The default execution path remains `FakeAIReviewProvider`, and Phase 3 verification must not require live OpenAI calls. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/http-client]

2. **Severity/category vocabulary**
   - Resolution: Structured findings use severity values `critical`, `high`, `medium`, `low` and category values `bug`, `security`, `performance`, `maintainability`, `style`. Default instructions prioritize bug/security findings; style findings are allowed only when useful and not noisy. The validator, fixtures, tests, and UI should share these exact tokens. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/validation]

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Docker Engine | Containerized PHP/artisan execution in this environment. [VERIFIED: docker exec] | ✓ [VERIFIED: docker exec] | `29.4.0` [VERIFIED: docker exec] | — |
| Laravel workspace container | Running `php`, `composer`, and `artisan test` for this repo. [VERIFIED: docker exec] | ✓ [VERIFIED: docker exec] | Container names include `laradock-workspace-85-1`. [VERIFIED: docker exec] | — |
| PHP CLI on host | Direct host execution of `artisan` / PHPUnit. [VERIFIED: codebase] | ✗ [VERIFIED: codebase] | — | Use `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php ...`. [VERIFIED: docker exec] |
| Composer CLI on host | Direct host execution of `composer run test`. [VERIFIED: codebase] | ✗ [VERIFIED: codebase] | — | Use `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer ...`. [VERIFIED: docker exec] |
| PHP in workspace container | Running the Laravel app and tests. [VERIFIED: docker exec] | ✓ [VERIFIED: docker exec] | `8.5.7` [VERIFIED: docker exec] | — |
| Composer in workspace container | Dependency and test command execution. [VERIFIED: docker exec] | ✓ [VERIFIED: docker exec] | `2.10.1` [VERIFIED: docker exec] | — |
| Node.js | Existing frontend/build tooling and any fixture helpers. [VERIFIED: codebase] | ✓ [VERIFIED: codebase] | `v24.1.0` [VERIFIED: codebase] | — |
| npm | Existing frontend/build tooling. [VERIFIED: codebase] | ✓ [VERIFIED: codebase] | `11.3.0` [VERIFIED: codebase] | — |
| Laravel database queue configuration | Async review execution. [VERIFIED: codebase] | ✓ [VERIFIED: codebase] | Default queue connection is `database`. [VERIFIED: codebase] | Tests already fall back to `QUEUE_CONNECTION=sync`. [VERIFIED: codebase] |

**Missing dependencies with no fallback:**
- None confirmed.

**Missing dependencies with fallback:**
- Host `php` and `composer` are missing; use the workspace container commands for all verification and execution steps. [VERIFIED: docker exec]

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | PHPUnit `12.5.30` via Laravel test runner. [VERIFIED: codebase] |
| Config file | `phpunit.xml`. [VERIFIED: codebase] |
| Quick run command | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=QueuedReview` [VERIFIED: docker exec] |
| Full suite command | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` [VERIFIED: docker exec] |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| EXEC-01 | Manual run action marks `queued` and dispatches a job instead of running provider work inline. [VERIFIED: codebase] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=QueuedReviewDispatch` [VERIFIED: docker exec] | ❌ Wave 0 |
| EXEC-02 | Job reloads the run and marks it `running` before external work. [VERIFIED: codebase] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=QueuedReviewExecution` [VERIFIED: docker exec] | ❌ Wave 0 |
| EXEC-03 | Successful execution persists validated findings and marks the run `completed`. [VERIFIED: codebase] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=QueuedReviewExecution` [VERIFIED: docker exec] | ❌ Wave 0 |
| EXEC-04 | Provider, decode, validation, and runtime failures mark the run `failed` with safe summaries. [VERIFIED: codebase] | feature + unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=QueuedReviewFailure` [VERIFIED: docker exec] | ❌ Wave 0 |
| EXEC-05 | No unsafe provider secrets or payload fragments leak to persisted safe errors. [VERIFIED: codebase] | feature + unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ValidatedFindingPayloadTest|AIReviewFailureMapperTest|QueuedReviewFailureTest|OpenAIReviewProviderTest'` [VERIFIED: docker exec] | ❌ Wave 0 |
| AI-01 | AI provider contract resolves through the container. [VERIFIED: codebase] | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=FakeAIReviewProviderTest` [VERIFIED: docker exec] | ❌ Wave 0 |
| AI-02 | Fake provider supplies deterministic findings without network access. [VERIFIED: codebase] | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=FakeAIReviewProviderTest` [VERIFIED: docker exec] | ❌ Wave 0 |
| AI-03 | Optional concrete provider seam stays behind the interface and is HTTP-fakeable. [VERIFIED: codebase] | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=OpenAIReviewProviderTest` [VERIFIED: docker exec] | ❌ Wave 0 |
| AI-04 | Decoded provider payload is validated against the structured findings schema. [VERIFIED: codebase] | unit + feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ValidatedFindingPayloadTest|QueuedReviewExecutionTest'` [VERIFIED: docker exec] | ❌ Wave 0 |
| AI-05 | Persisted findings include severity, category, file path, optional line reference, title, rationale, and suggested comment text. [VERIFIED: codebase] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='QueuedReviewExecutionTest|QueuedReviewFailureTest'` [VERIFIED: docker exec] | ❌ Wave 0 |
| AI-06 | Default instructions prioritize bugs and security issues. [VERIFIED: codebase] | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=FakeAIReviewProviderTest` [VERIFIED: docker exec] | ❌ Wave 0 |
| AI-07 | Default instructions allow useful, non-noisy Laravel/PHP style feedback. [VERIFIED: codebase] | unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=FakeAIReviewProviderTest` [VERIFIED: docker exec] | ❌ Wave 0 |
| AI-08 | Invalid or incomplete AI output fails safely without malformed findings. [VERIFIED: codebase] | feature + unit | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ValidatedFindingPayloadTest|AIReviewFailureMapperTest|QueuedReviewFailureTest'` [VERIFIED: docker exec] | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=QueuedReview` [VERIFIED: docker exec]
- **Per wave merge:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 composer run test` [VERIFIED: docker exec]
- **Phase gate:** Full PHPUnit suite green before `$gsd-verify-work`. [VERIFIED: codebase]

### Wave 0 Gaps
- [ ] `tests/Feature/QueuedReviewDispatchTest.php` — manual run action, queue dispatch, precondition gate, and retry-admission coverage. [VERIFIED: codebase]
- [ ] `tests/Feature/QueuedReviewExecutionTest.php` — `running` / `completed` transitions plus findings replacement coverage under sync queue execution. [VERIFIED: codebase]
- [ ] `tests/Feature/QueuedReviewFailureTest.php` — timeout/transport, invalid JSON, invalid schema, unexpected runtime, and retry cleanup coverage. [VERIFIED: codebase]
- [ ] `tests/Unit/AI/FakeAIReviewProviderTest.php` — provider interface resolution, fixture seam, and default instruction vocabulary. [VERIFIED: codebase]
- [ ] `tests/Unit/AI/ValidatedFindingPayloadTest.php` — nested schema validation and vocabulary enforcement. [VERIFIED: codebase]
- [ ] `tests/Unit/AI/AIReviewFailureMapperTest.php` — safe code/message mapping without raw exception leakage. [VERIFIED: codebase]
- [ ] `tests/Unit/AI/OpenAIReviewProviderTest.php` — opt-in OpenAI adapter seam and HTTP-fakeable request coverage. [VERIFIED: codebase]
- [ ] `database/factories/GitHubRepositoryFactory.php`, `PullRequestFactory.php`, `ReviewRunFactory.php`, and `ReviewFindingFactory.php` — the repo currently only has `UserFactory`. [VERIFIED: codebase]

## Security Domain

Security enforcement is enabled in `.planning/config.json`. [VERIFIED: codebase]

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no | The product remains a personal-use, no-login MVP in this phase. [VERIFIED: codebase] |
| V3 Session Management | no | The run action stays on the existing web stack and does not introduce a new session model. [VERIFIED: codebase] |
| V4 Access Control | no | Team roles and authz are deferred; the Phase 3 focus is safe workflow state, not user authorization. [VERIFIED: codebase] |
| V5 Input Validation | yes | Validate controller input, queue-dispatch preconditions, decoded provider JSON, and finding fields before persistence. [VERIFIED: codebase] [CITED: https://laravel.com/docs/13.x/validation] |
| V6 Cryptography | no | No new cryptographic flow is introduced; the main control is keeping provider secrets in env/config only. [VERIFIED: codebase] |

### Known Threat Patterns for Laravel Queued AI Review

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| User triggers AI review before GitHub snapshot data exists. [VERIFIED: codebase] | Tampering | Enforce the precondition in the service and optionally disable or hide the button in the SSR view until `github_fetched_at` exists. [VERIFIED: codebase] |
| Duplicate manual submissions enqueue concurrent jobs for the same run. [VERIFIED: codebase] | Denial of Service | Make the repository’s `queued` transition atomic and reject dispatch while status is already `queued` or `running`. [VERIFIED: codebase] |
| Provider exception text or payload leaks secrets into persisted state. [VERIFIED: codebase] | Information Disclosure | Keep secrets in `config/services.php`, map failures to whitelisted safe messages, and never persist raw provider payloads. [VERIFIED: codebase] |
| Malformed or incomplete provider output is treated as trusted data. [VERIFIED: codebase] | Tampering | Decode with hard JSON errors, validate the full payload, and refuse all writes on any validation failure. [CITED: https://laravel.com/docs/13.x/validation] |
| PR snapshot text is untrusted input to the AI prompt. [VERIFIED: codebase] | Tampering | Keep default instructions fixed, require structured output, and preserve later human approval before any GitHub publication workflow. [VERIFIED: codebase] [CITED: https://platform.openai.com/docs/guides/structured-outputs] |

## Sources

### Primary (HIGH confidence)
- Local planning artifacts and codebase: `AGENTS.md`, `.planning/phases/03-queued-ai-review-and-structured-findings/03-CONTEXT.md`, `.planning/REQUIREMENTS.md`, `.planning/ROADMAP.md`, `.planning/STATE.md`, `.planning/phases/02-github-pr-ingestion/02-VERIFICATION.md`, `.planning/codebase/ARCHITECTURE.md`, `.planning/codebase/INTEGRATIONS.md`, `.planning/codebase/TESTING.md`, `.planning/config.json`, `app/Enums/ReviewRunStatus.php`, `app/Models/ReviewRun.php`, `app/Models/ReviewRunFile.php`, `app/Repositories/ReviewRunRepository.php`, `app/Services/PullRequestIngestionService.php`, `app/Http/Controllers/ReviewController.php`, `resources/views/reviews/show.blade.php`, `config/services.php`, `routes/web.php`, `composer.json`, `composer.lock`, `phpunit.xml`, `vendor/laravel/framework/src/Illuminate/Support/Facades/Queue.php`, and `vendor/laravel/framework/src/Illuminate/Support/Testing/Fakes/QueueFake.php`. [VERIFIED: codebase]
- Environment verification: `docker --version`, `docker ps`, `docker exec ... php --version`, `docker exec ... composer --version`, and `docker exec ... php artisan test --filter=ExampleTest`. [VERIFIED: docker exec]

### Secondary (MEDIUM confidence)
- Laravel queue docs: https://laravel.com/docs/13.x/queues
- Laravel service container docs: https://laravel.com/docs/13.x/container
- Laravel validation docs: https://laravel.com/docs/13.x/validation
- Laravel HTTP client docs: https://laravel.com/docs/13.x/http-client
- OpenAI structured outputs guide: https://platform.openai.com/docs/guides/structured-outputs

### Tertiary (LOW confidence)
- None.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - The repo versions and runtime path are verified locally, and the phase can rely on installed Laravel capabilities rather than speculative package choices. [VERIFIED: codebase] [VERIFIED: docker exec]
- Architecture: HIGH - The controller/service/repository split, current models, status vocabulary, and detail-page workflow already exist in the codebase and directly constrain the plan. [VERIFIED: codebase]
- Pitfalls: MEDIUM - The queue/validation failure classes are well supported by official docs, but some sequencing recommendations remain implementation design choices for this specific phase. [CITED: https://laravel.com/docs/13.x/queues] [CITED: https://laravel.com/docs/13.x/validation]

**Research date:** 2026-06-28
**Valid until:** 2026-07-28
