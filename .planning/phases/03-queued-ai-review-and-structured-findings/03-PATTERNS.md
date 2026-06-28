# Phase 3: Queued AI Review and Structured Findings - Pattern Map

**Mapped:** 2026-06-28
**Files analyzed:** 30
**Analogs found:** 29 / 30

Assumption: exact class names for new AI execution DTOs and services are planner-controlled. This map uses the minimal file set implied by `03-CONTEXT.md`, `03-RESEARCH.md`, and the Phase 3 roadmap plans so each seam has a concrete local analog.

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `routes/web.php` | route | request-response | `routes/web.php` | exact |
| `app/Http/Controllers/ReviewController.php` | controller | request-response | `app/Http/Controllers/ReviewController.php` | exact |
| `resources/views/reviews/show.blade.php` | component | request-response | `resources/views/reviews/show.blade.php` | exact |
| `app/Services/ReviewExecutionDispatchService.php` | service | request-response | `app/Services/PullRequestIngestionService.php` | exact |
| `app/Jobs/ExecuteReviewRunJob.php` | job | event-driven | none | no analog |
| `app/Services/ReviewExecutionService.php` | service | event-driven | `app/Services/PullRequestIngestionService.php` | role-match |
| `app/Contracts/AI/AIReviewProvider.php` | provider | request-response | `app/Contracts/GitHub/GitHubClient.php` | exact |
| `app/Services/AI/FakeAIReviewProvider.php` | provider | transform | `app/Services/GitHub/HttpGitHubClient.php` | role-match |
| `app/Services/AI/HttpOpenAIReviewProvider.php` | provider | request-response | `app/Services/GitHub/HttpGitHubClient.php` | role-match |
| `app/Services/AI/AIReviewFailureMapper.php` | utility | transform | `app/Services/GitHub/GitHubFailureMapper.php` | exact |
| `app/Data/AI/AIReviewFailure.php` | utility | transform | `app/Data/GitHub/GitHubFailure.php` | exact |
| `app/Data/AI/ReviewExecutionResult.php` | utility | request-response | `app/Data/GitHub/PullRequestIngestionResult.php` | exact |
| `app/Data/AI/ValidatedFindingPayload.php` | utility | transform | `app/Data/GitHub/PullRequestFileSnapshot.php` | role-match |
| `app/Repositories/ReviewRunRepository.php` | repository | CRUD | `app/Repositories/ReviewRunRepository.php` | exact |
| `app/Repositories/ReviewFindingRepository.php` | repository | CRUD | `app/Repositories/ReviewRunRepository.php` | role-match |
| `app/Models/ReviewRun.php` | model | CRUD | `app/Models/ReviewRun.php` | exact |
| `app/Models/ReviewFinding.php` | model | CRUD | `app/Models/ReviewRunFile.php` | role-match |
| `app/Providers/AppServiceProvider.php` | provider | request-response | `app/Providers/AppServiceProvider.php` | exact |
| `config/services.php` | config | request-response | `config/services.php` | exact |
| `database/migrations/*_create_review_findings_table.php` | migration | batch | `database/migrations/2026_06_27_100001_create_review_run_files_table.php` | exact |
| `database/factories/GitHubRepositoryFactory.php` | test | CRUD | `database/factories/UserFactory.php` | role-match |
| `database/factories/PullRequestFactory.php` | test | CRUD | `database/factories/UserFactory.php` | role-match |
| `database/factories/ReviewRunFactory.php` | test | CRUD | `database/factories/UserFactory.php` | role-match |
| `database/factories/ReviewFindingFactory.php` | test | CRUD | `database/factories/UserFactory.php` | role-match |
| `tests/Feature/QueuedReviewDispatchTest.php` | test | request-response | `tests/Feature/GitHubPullRequestIngestionTest.php` | exact |
| `tests/Feature/QueuedReviewExecutionTest.php` | test | event-driven | `tests/Feature/GitHubPullRequestIngestionTest.php` | role-match |
| `tests/Feature/QueuedReviewFailureTest.php` | test | event-driven | `tests/Feature/GitHubPullRequestIngestionFailureTest.php` | exact |
| `tests/Unit/AI/ValidatedFindingPayloadTest.php` | test | transform | `tests/Unit/GitHub/GitHubFailureMapperTest.php` | role-match |
| `tests/Unit/AI/AIReviewFailureMapperTest.php` | test | transform | `tests/Unit/GitHub/GitHubFailureMapperTest.php` | exact |
| `tests/Fixtures/AI/*.json` | test | file-I/O | `tests/Fixtures/GitHub/*.json` | exact |

## Pattern Assignments

### Routing, Controller, and Detail UI

Targets:
- `routes/web.php`
- `app/Http/Controllers/ReviewController.php`
- `resources/views/reviews/show.blade.php`

**Route declaration pattern** from `routes/web.php:3-10`:
```php
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/reviews');

Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
Route::post('/reviews/{reviewRun}/fetch', [ReviewController::class, 'fetch'])->name('reviews.fetch');
Route::get('/reviews/{reviewRun}', [ReviewController::class, 'show'])->name('reviews.show');
```

**Thin controller POST pattern** from `app/Http/Controllers/ReviewController.php:21-39`:
```php
public function store(Request $request, ReviewRunService $reviewRunService): RedirectResponse
{
    $validated = $request->validate([
        'pr_url' => ['required', 'string'],
    ]);

    $result = $reviewRunService->createFromPullRequestUrl($validated['pr_url']);

    if (! $result->successful()) {
        return redirect()
            ->route('reviews.index')
            ->withInput()
            ->with('service_error_code', $result->errorCode())
            ->with('service_error_message', $result->message());
    }

    return redirect()
        ->route('reviews.show', $result->reviewRun())
        ->with('status', $result->message());
}
```

**Service-backed manual action pattern** from `app/Http/Controllers/ReviewController.php:49-62`:
```php
public function fetch(int|string $reviewRun, PullRequestIngestionService $pullRequestIngestionService): RedirectResponse
{
    $result = $pullRequestIngestionService->fetch($reviewRun);

    if (! $result->successful()) {
        return redirect()
            ->route('reviews.show', $result->reviewRun())
            ->with('service_error_code', $result->errorCode())
            ->with('service_error_message', $result->message());
    }

    return redirect()
        ->route('reviews.show', $result->reviewRun())
        ->with('status', $result->message());
}
```

**Detail-page form and conditional rendering pattern** from `resources/views/reviews/show.blade.php:25-35`, `50-55`, `75-113`:
```blade
@if (session('status'))
    <div class="success-block" style="margin-bottom: 24px;">
        <strong>{{ session('status') }}</strong>
    </div>
@endif

@if (session('service_error_message'))
    <div class="error-block" style="margin-bottom: 24px;">
        <strong>{{ session('service_error_message') }}</strong>
    </div>
@endif
```

```blade
<form method="POST" action="{{ route('reviews.fetch', $reviewRun) }}" style="margin-bottom: 20px;">
    @csrf
    <button type="submit">Fetch</button>
</form>
```

```blade
@if ($reviewRun->github_fetched_at)
    <section class="section">
        <h2>GitHub Snapshot</h2>
        <div class="metadata">
            <div class="metadata-row">
                <span class="meta-label">Title</span>
                <span>{{ $reviewRun->github_title }}</span>
            </div>
        </div>
    </section>
@endif
```

**What to copy**
- Add `Run AI Review` as another named POST route beside `reviews.fetch`, not a GET trigger.
- Keep the new controller action HTTP-only: validate/precondition-check input, delegate to a dispatch service, redirect back to `reviews.show`, and flash either `status` or `service_error_message`.
- Extend the existing detail page rather than adding a new shell. Keep the run button, execution state, safe error copy, and findings list on `reviews.show`.
- Reuse the existing `x-review-status` component instead of inventing a second status label style.

---

### Dispatch Service, Execution Service, and Queued Job

Targets:
- `app/Services/ReviewExecutionDispatchService.php`
- `app/Services/ReviewExecutionService.php`
- `app/Jobs/ExecuteReviewRunJob.php`

**Constructor-injected orchestration pattern** from `app/Services/PullRequestIngestionService.php:13-18`:
```php
public function __construct(
    private readonly GitHubClient $githubClient,
    private readonly ReviewRunRepository $reviewRuns,
    private readonly GitHubFailureMapper $failureMapper,
) {
}
```

**Reload-parent-or-fail pattern** from `app/Services/PullRequestIngestionService.php:20-28`:
```php
public function fetch(ReviewRun|int|string $reviewRun): PullRequestIngestionResult
{
    $reviewRun = $reviewRun instanceof ReviewRun
        ? $reviewRun->loadMissing('pullRequest.repository')
        : $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRun);

    $pullRequest = $reviewRun->pullRequest;
    $repository = $pullRequest->repository;
```

**Try/catch plus result-object pattern** from `app/Services/PullRequestIngestionService.php:29-50`:
```php
try {
    $snapshot = $this->githubClient->getPullRequest(
        $repository->owner,
        $repository->name,
        $pullRequest->number,
    );

    $files = $this->githubClient->listPullRequestFiles(
        $repository->owner,
        $repository->name,
        $pullRequest->number,
    );

    $reviewRun = $this->reviewRuns->storeGitHubSnapshot($reviewRun, $snapshot, $files);

    return PullRequestIngestionResult::success($reviewRun);
} catch (\Throwable $throwable) {
    $failure = $this->failureMapper->map($throwable);
    $reviewRun = $this->reviewRuns->markGitHubFetchFailed($reviewRun, $failure->message);

    return PullRequestIngestionResult::failure($reviewRun, $failure);
}
```

**Repository-side reset pattern** from `app/Repositories/ReviewRunRepository.php:47-68`:
```php
return DB::transaction(function () use ($reviewRun, $snapshot, $files): ReviewRun {
    $reviewRun->forceFill([
        'status' => ReviewRunStatus::Pending,
        'github_title' => $snapshot->title,
        'github_state' => $snapshot->state,
        'github_head_sha' => $snapshot->headSha,
        'github_fetched_at' => now(),
        'safe_error_message' => null,
        'failed_at' => null,
    ])->save();

    $reviewRun->files()->delete();
```

**What to copy**
- `ReviewExecutionDispatchService` should mirror `PullRequestIngestionService`: accept `ReviewRun|int|string`, reload the run through the repository, enforce preconditions, transition the run to `queued`, then return a stable result object for the controller.
- Keep queue admission and timestamp resets in a repository transaction. This is where retry cleanup belongs.
- `ReviewExecutionService` should mirror the same orchestration shape, but run from the job path: reload the run, mark it `running`, call the provider seam, validate the payload, replace findings, and mark `completed` or `failed`.
- There is no local job analog. Use the thin-service-call pattern from these services plus the Phase 3 research job skeleton at `03-RESEARCH.md:267-290`: job constructor carries only `reviewRunId`; `handle()` resolves the execution service from the container.

---

### AI Provider Seam, DTOs, and Failure Mapping

Targets:
- `app/Contracts/AI/AIReviewProvider.php`
- `app/Services/AI/FakeAIReviewProvider.php`
- `app/Services/AI/HttpOpenAIReviewProvider.php`
- `app/Services/AI/AIReviewFailureMapper.php`
- `app/Data/AI/AIReviewFailure.php`
- `app/Data/AI/ReviewExecutionResult.php`
- `app/Data/AI/ValidatedFindingPayload.php`

**Provider contract pattern** from `app/Contracts/GitHub/GitHubClient.php:3-14`:
```php
namespace App\Contracts\GitHub;

use App\Data\GitHub\PullRequestSnapshot;

interface GitHubClient
{
    public function getPullRequest(string $owner, string $repository, int $pullRequestNumber): PullRequestSnapshot;

    /**
     * @return array<int, \App\Data\GitHub\PullRequestFileSnapshot>
     */
    public function listPullRequestFiles(string $owner, string $repository, int $pullRequestNumber): array;
}
```

**Concrete provider config and response-guard pattern** from `app/Services/GitHub/HttpGitHubClient.php:11-24`, `60-75`:
```php
class HttpGitHubClient implements GitHubClient
{
    public function getPullRequest(string $owner, string $repository, int $pullRequestNumber): PullRequestSnapshot
    {
        $payload = $this->request()
            ->get($this->repositoryPath($owner, $repository)."/pulls/{$pullRequestNumber}")
            ->throw()
            ->json();

        if (! is_array($payload)) {
            throw new \UnexpectedValueException('GitHub pull request response must be an object.');
        }
```

```php
private function request(): PendingRequest
{
    $request = Http::baseUrl((string) config('services.github.base_url', 'https://api.github.com'))
        ->accept('application/vnd.github+json')
        ->withHeaders([
            'X-GitHub-Api-Version' => (string) config('services.github.api_version', '2022-11-28'),
        ]);

    $token = config('services.github.token');

    if (is_string($token) && $token !== '') {
        $request = $request->withToken($token);
    }

    return $request;
}
```

**Failure mapper pattern** from `app/Services/GitHub/GitHubFailureMapper.php:11-35`, `37-66`:
```php
public function map(\Throwable $throwable): GitHubFailure
{
    if ($throwable instanceof RequestException) {
        return $this->mapRequestException($throwable);
    }

    if ($throwable instanceof ConnectionException) {
        return new GitHubFailure(
            'server_unavailable',
            'GitHub could not be reached. Try fetching this pull request again later.',
        );
    }
```

```php
if ($status === 404) {
    return new GitHubFailure(
        'not_found_or_unreadable',
        'GitHub could not find or read this pull request.',
    );
}
```

**Readonly failure DTO pattern** from `app/Data/GitHub/GitHubFailure.php:5-10`:
```php
readonly class GitHubFailure
{
    public function __construct(
        public string $code,
        public string $message,
    ) {
    }
}
```

**Static result-object pattern** from `app/Data/GitHub/PullRequestIngestionResult.php:7-45`:
```php
readonly class PullRequestIngestionResult
{
    private function __construct(
        private bool $successful,
        private ReviewRun $reviewRun,
        private string $message,
        private ?string $errorCode = null,
    ) {
    }

    public static function success(ReviewRun $reviewRun): self
    {
        return new self(true, $reviewRun, 'GitHub pull request data fetched.');
    }
```

**DTO guard pattern** from `app/Data/GitHub/PullRequestFileSnapshot.php:17-37`:
```php
public static function fromGitHubPayload(array $payload): self
{
    return new self(
        filename: self::requiredString($payload, 'filename'),
        patch: self::requiredString($payload, 'patch'),
        sha: self::requiredString($payload, 'sha'),
    );
}

private static function requiredString(array $payload, string $key): string
{
    $value = $payload[$key] ?? null;

    if (! is_string($value) || $value === '') {
        throw new \UnexpectedValueException("GitHub pull request file payload is missing {$key}.");
    }
```

**What to copy**
- Keep the AI provider interface narrow and app-owned. It should return app DTOs or a JSON string the app validates, never vendor SDK objects.
- `FakeAIReviewProvider` and the optional `HttpOpenAIReviewProvider` should both live under `app/Services/AI/` and follow the same shape-guard discipline as `HttpGitHubClient`.
- `AIReviewFailureMapper` should be a straight parallel to `GitHubFailureMapper`: one place for whitelisted codes and safe user-facing messages.
- `AIReviewFailure` and `ReviewExecutionResult` should follow the same readonly/static-constructor style as the GitHub equivalents so controllers and jobs can assert exact codes and messages.
- `ValidatedFindingPayload` should use the `requiredString` / DTO-guard style locally, but the full nested findings-array validation has no exact codebase analog yet. Pair this local DTO pattern with the Phase 3 research validator rules at `03-RESEARCH.md:303-323`.

---

### Persistence Layer: Status Transitions, Findings Replacement, and Eloquent Shape

Targets:
- `app/Repositories/ReviewRunRepository.php`
- `app/Repositories/ReviewFindingRepository.php`
- `app/Models/ReviewRun.php`
- `app/Models/ReviewFinding.php`
- `database/migrations/*_create_review_findings_table.php`

**Eager-loaded lookup pattern** from `app/Repositories/ReviewRunRepository.php:35-40`:
```php
public function findWithPullRequestRepositoryOrFail(int|string $id): ReviewRun
{
    return ReviewRun::query()
        ->with(['files', 'pullRequest.repository'])
        ->findOrFail($id);
}
```

**Transactional replace-children pattern** from `app/Repositories/ReviewRunRepository.php:45-69`:
```php
public function storeGitHubSnapshot(ReviewRun $reviewRun, PullRequestSnapshot $snapshot, array $files): ReviewRun
{
    return DB::transaction(function () use ($reviewRun, $snapshot, $files): ReviewRun {
        $reviewRun->forceFill([
            'status' => ReviewRunStatus::Pending,
            'github_title' => $snapshot->title,
            'github_state' => $snapshot->state,
            'github_head_sha' => $snapshot->headSha,
            'github_fetched_at' => now(),
            'safe_error_message' => null,
            'failed_at' => null,
        ])->save();

        $reviewRun->files()->delete();

        foreach ($files as $file) {
            $reviewRun->files()->create([
                'filename' => $file->filename,
                'patch' => $file->patch,
                'sha' => $file->sha,
            ]);
        }

        return $reviewRun->refresh()->load(['files', 'pullRequest.repository']);
    });
}
```

**Failure-state write pattern** from `app/Repositories/ReviewRunRepository.php:72-80`:
```php
public function markGitHubFetchFailed(ReviewRun $reviewRun, string $safeErrorMessage): ReviewRun
{
    $reviewRun->forceFill([
        'status' => ReviewRunStatus::Failed,
        'safe_error_message' => $safeErrorMessage,
        'failed_at' => now(),
    ])->save();

    return $reviewRun->refresh()->load(['files', 'pullRequest.repository']);
}
```

**Model fillable/casts pattern** from `app/Models/ReviewRun.php:11-58`:
```php
#[Fillable([
    'pull_request_id',
    'status',
    'safe_error_message',
    'github_title',
    'github_state',
    'github_head_sha',
    'github_fetched_at',
    'queued_at',
    'started_at',
    'completed_at',
    'failed_at',
    'cancelled_at',
])]
class ReviewRun extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ReviewRunStatus::class,
            'github_fetched_at' => 'datetime',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
```

**Simple child model pattern** from `app/Models/ReviewRunFile.php:9-18`:
```php
#[Fillable(['review_run_id', 'filename', 'patch', 'sha'])]
class ReviewRunFile extends Model
{
    public function reviewRun(): BelongsTo
    {
        return $this->belongsTo(ReviewRun::class);
    }
}
```

**Migration foreign-key/index pattern** from `database/migrations/2026_06_27_100001_create_review_run_files_table.php:14-23`:
```php
Schema::create('review_run_files', function (Blueprint $table) {
    $table->id();
    $table->foreignId('review_run_id')->constrained('review_runs')->cascadeOnDelete();
    $table->string('filename');
    $table->text('patch');
    $table->string('sha', 40);
    $table->timestamps();

    $table->index(['review_run_id', 'filename']);
});
```

**What to copy**
- Keep all status writes, timestamp writes, stale-error cleanup, eager loading, and child-row replacement in repository classes.
- `ReviewFindingRepository` should copy `storeGitHubSnapshot()` most closely: delete existing findings for the run, insert the validated replacement set, then return a refreshed/eager-loaded run.
- `ReviewRunRepository` should own `queued`, `running`, `completed`, and `failed` transitions, using `forceFill([...])->save()` with explicit timestamp/reset rules.
- `ReviewRun` should gain only the relations needed by the detail page, most likely `findings(): HasMany`.
- `ReviewFinding` should stay as simple as `ReviewRunFile`: fillable fields, parent relation, and casts only if genuinely useful.
- The findings migration should be small and explicit: `review_run_id`, `severity`, `category`, `file_path`, nullable `line_reference`, `title`, `rationale`, `suggested_comment_text`, timestamps, and an index that helps the detail page load predictably.

---

### Container Binding, Config, Factories, and Test Files

Targets:
- `app/Providers/AppServiceProvider.php`
- `config/services.php`
- `database/factories/GitHubRepositoryFactory.php`
- `database/factories/PullRequestFactory.php`
- `database/factories/ReviewRunFactory.php`
- `database/factories/ReviewFindingFactory.php`
- `tests/Feature/QueuedReviewDispatchTest.php`
- `tests/Feature/QueuedReviewExecutionTest.php`
- `tests/Feature/QueuedReviewFailureTest.php`
- `tests/Unit/AI/ValidatedFindingPayloadTest.php`
- `tests/Unit/AI/AIReviewFailureMapperTest.php`
- `tests/Fixtures/AI/*.json`

**Container binding pattern** from `app/Providers/AppServiceProvider.php:14-17`:
```php
public function register(): void
{
    $this->app->bind(GitHubClient::class, HttpGitHubClient::class);
}
```

**Third-party config pattern** from `config/services.php:25-29`:
```php
'github' => [
    'base_url' => env('GITHUB_API_BASE_URL', 'https://api.github.com'),
    'api_version' => env('GITHUB_API_VERSION', '2022-11-28'),
    'token' => env('GITHUB_TOKEN'),
],
```

**Factory structure pattern** from `database/factories/UserFactory.php:13-44`:
```php
/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ];
    }
}
```

**Feature test structure pattern** from `tests/Feature/GitHubPullRequestIngestionTest.php:16-18`, `86-126`:
```php
class GitHubPullRequestIngestionTest extends TestCase
{
    use RefreshDatabase;
```

```php
$this->post(route('reviews.fetch', $reviewRun))
    ->assertRedirect(route('reviews.show', $reviewRun))
    ->assertSessionHas('status', 'GitHub pull request data fetched.');
```

**Retry/failure assertion pattern** from `tests/Feature/GitHubPullRequestIngestionFailureTest.php:159-179`:
```php
private function assertFailedSafely(ReviewRun $reviewRun, string $expectedMessage): void
{
    $reviewRun = ReviewRun::findOrFail($reviewRun->id);

    $this->assertSame(ReviewRunStatus::Failed, $reviewRun->status);
    $this->assertSame($expectedMessage, $reviewRun->safe_error_message);
    $this->assertNotNull($reviewRun->failed_at);
```

```php
    foreach ($unsafeFragments as $fragment) {
        $this->assertStringNotContainsString($fragment, (string) $reviewRun->safe_error_message);
    }
}
```

**Table-driven service/unit test pattern** from `tests/Feature/ReviewRunCreationServiceTest.php:67-87` and `tests/Unit/GitHub/GitHubFailureMapperTest.php:14-67`:
```php
$cases = [
    'not a url' => 'invalid_url',
    'https://example.com/owner/repo/pull/123' => 'not_github_pr_url',
];

foreach ($cases as $url => $expectedCode) {
    $result = $service->createFromPullRequestUrl($url);

    $this->assertFalse($result->successful(), $url);
    $this->assertSame($expectedCode, $result->errorCode(), $url);
}
```

```php
$failure = app(GitHubFailureMapper::class)->map($this->requestException(404));

$this->assertSame('not_found_or_unreadable', $failure->code);
$this->assertSame('GitHub could not find or read this pull request.', $failure->message);
```

**Fixture directory precedent**
- Existing fixture precedent already lives under `tests/Fixtures/GitHub/`.
- Keep new AI payload fixtures alongside it under `tests/Fixtures/AI/`.
- Reuse the existing fixture helper style from `tests/Feature/GitHubPullRequestIngestionTest.php:165-168` and `tests/Feature/GitHubPullRequestIngestionFailureTest.php:182-185`.

**What to copy**
- Bind `AIReviewProvider` in `AppServiceProvider::register()`. If Phase 3 stays fake-first, bind the fake provider by default and leave the HTTP provider opt-in.
- Reserve all future OpenAI credentials/config in `config/services.php`; do not call `env()` from execution services, repositories, or jobs.
- Keep all new factories in Laravel’s default factory style with typed `Factory<...>` docs and concise `definition()` arrays.
- `QueuedReviewDispatchTest` should follow the existing POST-route + redirect + flashed-status assertions, then add queue-boundary assertions using the research queue example at `03-RESEARCH.md:448-463`.
- `QueuedReviewExecutionTest` should follow the same end-to-end feature style as ingestion tests, but assert `running`, `completed`, timestamp transitions, and replaced findings rows under sync execution.
- `QueuedReviewFailureTest` should copy the existing safe-failure assertions and unsafe-fragment checks exactly.
- `ValidatedFindingPayloadTest` and `AIReviewFailureMapperTest` should stay pure and fast like `GitHubFailureMapperTest`: construct inputs directly, assert codes/messages/validation failures directly, and avoid booting HTTP fakes unless the unit truly needs them.

## Shared Patterns

### Controller / Service / Repository layering
**Sources:** `app/Http/Controllers/ReviewController.php:21-62`, `app/Services/PullRequestIngestionService.php:20-50`, `app/Repositories/ReviewRunRepository.php:35-80`
```php
$result = $pullRequestIngestionService->fetch($reviewRun);
```

```php
$reviewRun = $this->reviewRuns->storeGitHubSnapshot($reviewRun, $snapshot, $files);
```

```php
return ReviewRun::query()
    ->with(['files', 'pullRequest.repository'])
    ->findOrFail($id);
```

**Apply to:** All manual-run, job-execution, findings-persistence, and retry files.

**Rule**
- Controllers stay HTTP-only.
- Services orchestrate workflow and result-shaping.
- Repositories own all Eloquent reads/writes and lifecycle transitions.

### Safe retry cleanup and explicit status writes
**Sources:** `app/Repositories/ReviewRunRepository.php:47-56`, `72-78`
```php
$reviewRun->forceFill([
    'safe_error_message' => null,
    'failed_at' => null,
])->save();
```

```php
$reviewRun->forceFill([
    'status' => ReviewRunStatus::Failed,
    'safe_error_message' => $safeErrorMessage,
    'failed_at' => now(),
])->save();
```

**Apply to:** `ReviewRunRepository`, dispatch service retry path, execution failure path.

**Rule**
- Requeueing a failed run must clear stale failure state before dispatch.
- Completing a run should clear failure state and leave one coherent timestamp set for the latest attempt.

### Provider config and secret redaction
**Sources:** `app/Services/GitHub/HttpGitHubClient.php:60-75`, `app/Services/GitHub/GitHubFailureMapper.php:13-35`, `tests/Feature/GitHubPullRequestIngestionFailureTest.php:169-179`
```php
$token = config('services.github.token');

if (is_string($token) && $token !== '') {
    $request = $request->withToken($token);
}
```

```php
foreach ($unsafeFragments as $fragment) {
    $this->assertStringNotContainsString($fragment, (string) $reviewRun->safe_error_message);
}
```

**Apply to:** AI provider implementations, AI failure mapper, failure feature tests, `config/services.php`.

**Rule**
- Secrets live only in config/env.
- Persist only whitelisted safe summaries.
- Tests should explicitly prove that raw payload fragments and credentials never leak into `safe_error_message`.

### Detail-page SSR integration
**Sources:** `resources/views/reviews/show.blade.php:20-23`, `25-35`, `75-113`; `resources/views/components/review-status.blade.php:4-14`
```blade
<div class="detail-header">
    <h1>Review Run #{{ $reviewRun->id }}</h1>
    <x-review-status :status="$reviewRun->status" />
</div>
```

```blade
@if ($reviewRun->github_fetched_at)
    <section class="section">
        <h2>Fetched Files</h2>
```

**Apply to:** `resources/views/reviews/show.blade.php`, any detail-page findings/status additions.

**Rule**
- Keep the user on the same review-run detail page for fetch, run, failure, retry, and findings display.
- Gate execution UI on existing fetched-snapshot state instead of inventing a second workflow page.

### Model and DTO minimalism
**Sources:** `app/Models/ReviewRunFile.php:9-18`, `app/Data/GitHub/GitHubFailure.php:5-10`, `app/Data/GitHub/PullRequestFileSnapshot.php:17-37`
```php
#[Fillable(['review_run_id', 'filename', 'patch', 'sha'])]
```

```php
readonly class GitHubFailure
{
    public function __construct(
        public string $code,
        public string $message,
    ) {
    }
}
```

**Apply to:** `ReviewFinding`, `AIReviewFailure`, `ValidatedFindingPayload`, related factories.

**Rule**
- Keep persisted shapes narrow and phase-locked.
- Keep DTOs framework-light and app-owned.

### Test style and offline external boundaries
**Sources:** `tests/Feature/GitHubPullRequestIngestionTest.php:28-35`, `58-73`, `165-168`; `tests/Unit/GitHub/GitHubFailureMapperTest.php:14-67`
```php
Http::preventStrayRequests();
Http::fake([
    'https://api.github.com/repos/laravel/framework/pulls/1' => Http::response(
        $this->fixture('GitHub/pull-request.json'),
        200,
        ['Content-Type' => 'application/json'],
    ),
]);
```

```php
private function fixture(string $path): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/'.$path));
}
```

**Apply to:** AI provider feature tests, AI fixture loading, optional HTTP-provider tests.

**Rule**
- External integrations stay fakeable and offline.
- Use feature tests for end-to-end controller/service/repository behavior and unit tests for mappers/validators.

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `app/Jobs/ExecuteReviewRunJob.php` | job | event-driven | The repo has no existing `app/Jobs/` classes or queued workflow objects yet. Use the local service/result/repository patterns plus `03-RESEARCH.md:267-290` for the thin-job skeleton. |

## Metadata

**Analog search scope:** `app/`, `routes/`, `resources/views/`, `resources/views/components/`, `config/`, `database/migrations/`, `database/factories/`, `tests/`, `.planning/phases/03-queued-ai-review-and-structured-findings/`, `.planning/phases/02-github-pr-ingestion/`, `AGENTS.md`
**Files scanned:** 27 code/test files + 5 planning artifacts + `AGENTS.md`
**Pattern extraction date:** 2026-06-28
