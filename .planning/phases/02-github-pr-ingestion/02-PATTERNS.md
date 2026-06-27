# Phase 2: GitHub PR Ingestion - Pattern Map

**Mapped:** 2026-06-27
**Files analyzed:** 21
**Analogs found:** 20 / 21

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `routes/web.php` | route | request-response | `routes/web.php` | exact |
| `app/Http/Controllers/ReviewController.php` | controller | request-response | `app/Http/Controllers/ReviewController.php` | exact |
| `resources/views/reviews/show.blade.php` | component | request-response | `resources/views/reviews/show.blade.php` | exact |
| `app/Services/PullRequestIngestionService.php` | service | request-response | `app/Services/ReviewRunService.php` | exact |
| `app/Contracts/GitHub/GitHubClient.php` | service | request-response | `app/Services/GitHub/GitHubPullRequestUrlParser.php` | role-match |
| `app/Services/GitHub/HttpGitHubClient.php` | service | request-response | `app/Services/GitHub/GitHubPullRequestUrlParser.php` | role-match |
| `app/Services/GitHub/GitHubFailureMapper.php` | utility | transform | `app/Services/ReviewRunService.php` | partial |
| `app/Data/GitHub/PullRequestSnapshot.php` | utility | transform | `app/Data/GitHubPullRequestReference.php` | exact |
| `app/Data/GitHub/PullRequestFileSnapshot.php` | utility | transform | `app/Data/GitHubPullRequestReference.php` | exact |
| `app/Data/GitHub/PullRequestIngestionResult.php` | utility | request-response | `app/Data/ReviewRunCreationResult.php` | exact |
| `app/Repositories/ReviewRunRepository.php` | repository | CRUD | `app/Repositories/ReviewRunRepository.php` | exact |
| `app/Models/ReviewRun.php` | model | CRUD | `app/Models/ReviewRun.php` | exact |
| `app/Models/ReviewRunFile.php` | model | CRUD | `app/Models/PullRequest.php` | role-match |
| `database/migrations/*_create_review_run_files_table.php` | migration | batch | `database/migrations/2026_06_27_000002_create_pull_requests_table.php` | exact |
| `database/migrations/*_add_github_snapshot_columns_to_review_runs_table.php` | migration | batch | `database/migrations/2026_06_27_000003_create_review_runs_table.php` | exact |
| `app/Providers/AppServiceProvider.php` | provider | request-response | `app/Providers/AppServiceProvider.php` | partial |
| `config/services.php` | config | request-response | `config/services.php` | exact |
| `tests/Feature/GitHubPullRequestIngestionTest.php` | test | request-response | `tests/Feature/ReviewRunSubmissionTest.php` | exact |
| `tests/Feature/GitHubPullRequestIngestionFailureTest.php` | test | request-response | `tests/Feature/ReviewRunHistoryAndDetailTest.php` | exact |
| `tests/Unit/GitHub/GitHubFailureMapperTest.php` | test | transform | `tests/Feature/ReviewRunCreationServiceTest.php` | partial |
| `tests/Fixtures/GitHub/*.json` | test | file-I/O | none | no analog |

## Pattern Assignments

### `routes/web.php` (route, request-response)

**Analog:** `routes/web.php`

**Route declaration pattern** (`routes/web.php:3-10`):
```php
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/reviews');

Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
Route::get('/reviews/{reviewRun}', [ReviewController::class, 'show'])->name('reviews.show');
```

**What to copy**
- Keep the flat route style with named routes, not route groups.
- Add the manual fetch endpoint as another controller-backed route beside the existing review routes.

---

### `app/Http/Controllers/ReviewController.php` (controller, request-response)

**Analog:** `app/Http/Controllers/ReviewController.php`

**Imports pattern** (`app/Http/Controllers/ReviewController.php:5-9`):
```php
use App\Repositories\ReviewRunRepository;
use App\Services\ReviewRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
```

**POST action pattern** (`app/Http/Controllers/ReviewController.php:20-39`):
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

**Detail view pattern** (`app/Http/Controllers/ReviewController.php:41-45`):
```php
public function show(int|string $reviewRun, ReviewRunRepository $reviewRunRepository): View
{
    return view('reviews.show', [
        'reviewRun' => $reviewRunRepository->findWithPullRequestRepositoryOrFail($reviewRun),
    ]);
}
```

**What to copy**
- Keep validation, redirects, flash messaging, and view-return logic in the controller.
- Inject the new ingestion service the same way `ReviewRunService` is injected.
- Do not call `Http` or Eloquent directly from the controller.

---

### `resources/views/reviews/show.blade.php` (component, request-response)

**Analog:** `resources/views/reviews/show.blade.php`

**Flash/status block pattern** (`resources/views/reviews/show.blade.php:25-29`):
```blade
@if (session('status'))
    <div class="success-block" style="margin-bottom: 24px;">
        <strong>{{ session('status') }}</strong>
    </div>
@endif
```

**Safe failure copy pattern** (`resources/views/reviews/show.blade.php:31-41`):
```blade
<section class="section">
    <h2>Status</h2>
    @if ($reviewRun->status === \App\Enums\ReviewRunStatus::Failed)
        <div class="error-block" style="margin-top: 0;">
            <strong>Review run failed</strong>
            <div>{{ $reviewRun->safe_error_message ?: 'The run failed, but no safe error summary was recorded.' }}</div>
            <div>Review the safe error summary, then create a new run after fixing the source issue.</div>
        </div>
    @else
        <p class="muted">This review run is ready for the next processing step.</p>
    @endif
</section>
```

**Metadata section pattern** (`resources/views/reviews/show.blade.php:44-85`):
```blade
<section class="section">
    <h2>Pull Request</h2>
    <div class="metadata">
        <div class="metadata-row">
            <span class="meta-label">Repository</span>
            <span>{{ $reviewRun->pullRequest->repository->full_name }}</span>
        </div>
        <div class="metadata-row">
            <span class="meta-label">Pull Request</span>
            <span>PR #{{ $reviewRun->pullRequest->number }}</span>
        </div>
    </div>
</section>
```

**What to copy**
- Put the manual `Fetch` form on the existing detail page.
- Reuse flash-success and safe-failure rendering, and keep patch-derived data escaped with normal Blade output.

---

### `app/Services/PullRequestIngestionService.php` (service, request-response)

**Analog:** `app/Services/ReviewRunService.php`

**Constructor injection pattern** (`app/Services/ReviewRunService.php:14-19`):
```php
public function __construct(
    private readonly GitHubPullRequestUrlParser $parser,
    private readonly GitHubRepositoryRepository $repositories,
    private readonly PullRequestRepository $pullRequests,
    private readonly ReviewRunRepository $reviewRuns,
) {
}
```

**Workflow orchestration pattern** (`app/Services/ReviewRunService.php:22-38`):
```php
public function createFromPullRequestUrl(string $url): ReviewRunCreationResult
{
    $reference = $this->parser->parse($url);

    if (! $reference instanceof GitHubPullRequestReference) {
        return ReviewRunCreationResult::failure(
            $reference,
            $this->messageForErrorCode($reference),
        );
    }

    $repository = $this->repositories->findOrCreateFromReference($reference);
    $pullRequest = $this->pullRequests->findOrCreateForRepository($repository, $reference);
    $reviewRun = $this->reviewRuns->createPendingForPullRequest($pullRequest);

    return ReviewRunCreationResult::success($reviewRun->load('pullRequest.repository'));
}
```

**Stable message mapping pattern** (`app/Services/ReviewRunService.php:40-47`):
```php
private function messageForErrorCode(string $errorCode): string
{
    return match ($errorCode) {
        'invalid_url' => 'Enter a valid HTTPS GitHub pull request URL.',
        'not_github_pr_url' => 'Enter a GitHub pull request URL from github.com.',
        'missing_pr_number' => 'Enter a GitHub pull request URL with a valid pull request number.',
        default => 'The pull request URL could not be reviewed.',
    };
}
```

**What to copy**
- Keep the ingestion workflow in one service that orchestrates collaborators.
- Return a stable result object instead of leaking raw exceptions into the controller.
- Put GitHub failure classification behind a dedicated mapper or a private helper, but keep the public service API result-shaped.

---

### `app/Contracts/GitHub/GitHubClient.php` (service, request-response)

**Analog:** `app/Services/GitHub/GitHubPullRequestUrlParser.php`

**Namespace and single-purpose class pattern** (`app/Services/GitHub/GitHubPullRequestUrlParser.php:3-9`):
```php
namespace App\Services\GitHub;

use App\Data\GitHubPullRequestReference;

class GitHubPullRequestUrlParser
{
    public function parse(string $url): GitHubPullRequestReference|string
```

**What to copy**
- Keep GitHub-specific types under a dedicated `GitHub` namespace.
- Make the interface narrow: one method for PR metadata and one for paginated PR files is enough for this phase.

**Gap**
- There is no existing interface file in the repo. Use `AppServiceProvider` binding plus constructor injection as the real pattern source.

---

### `app/Services/GitHub/HttpGitHubClient.php` (service, request-response)

**Analog:** `app/Services/GitHub/GitHubPullRequestUrlParser.php`

**Guard/return-shape pattern** (`app/Services/GitHub/GitHubPullRequestUrlParser.php:9-45`):
```php
public function parse(string $url): GitHubPullRequestReference|string
{
    $parts = parse_url($url);

    if ($parts === false || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
        return 'invalid_url';
    }

    if ($parts['scheme'] !== 'https') {
        return 'invalid_url';
    }

    if (strtolower($parts['host']) !== 'github.com') {
        return 'not_github_pr_url';
    }
```

**What to copy**
- Follow the same explicit guard style: validate response shape before returning DTOs.
- Keep the concrete HTTP client isolated under `app/Services/GitHub/`.
- Use `config/services.php` for base URL/token inputs; do not call `env()` here.

**Gap**
- No current outbound HTTP service exists. Use this namespace/guard pattern from the parser and the result-object pattern from `ReviewRunService.php`.

---

### `app/Services/GitHub/GitHubFailureMapper.php` (utility, transform)

**Analog:** `app/Services/ReviewRunService.php`

**Error-code to safe-message mapping pattern** (`app/Services/ReviewRunService.php:40-47`):
```php
return match ($errorCode) {
    'invalid_url' => 'Enter a valid HTTPS GitHub pull request URL.',
    'not_github_pr_url' => 'Enter a GitHub pull request URL from github.com.',
    'missing_pr_number' => 'Enter a GitHub pull request URL with a valid pull request number.',
    default => 'The pull request URL could not be reviewed.',
};
```

**What to copy**
- Centralize whitelisted safe error strings in one mapper.
- Return stable codes/messages for `not_found_or_unreadable`, `rate_limited`, `auth_failed`, `server_unavailable`, and `malformed_response`.
- Do not persist raw GitHub bodies, headers, or thrown exception strings.

---

### `app/Data/GitHub/PullRequestSnapshot.php` (utility, transform)

**Analog:** `app/Data/GitHubPullRequestReference.php`

**Readonly DTO pattern** (`app/Data/GitHubPullRequestReference.php:5-18`):
```php
class GitHubPullRequestReference
{
    public function __construct(
        public readonly string $owner,
        public readonly string $repositoryName,
        public readonly int $pullRequestNumber,
        public readonly string $sourceUrl,
    ) {
    }
}
```

**What to copy**
- Use a minimal constructor-promoted readonly DTO.
- Keep this class pure and framework-free.

---

### `app/Data/GitHub/PullRequestFileSnapshot.php` (utility, transform)

**Analog:** `app/Data/GitHubPullRequestReference.php`

**DTO pattern** (`app/Data/GitHubPullRequestReference.php:7-12`):
```php
public function __construct(
    public readonly string $owner,
    public readonly string $repositoryName,
    public readonly int $pullRequestNumber,
    public readonly string $sourceUrl,
) {
}
```

**What to copy**
- Keep only the phase-locked fields in the DTO: `filename`, `patch`, and `sha`.
- Avoid premature line/hunk parsing fields in this phase.

---

### `app/Data/GitHub/PullRequestIngestionResult.php` (utility, request-response)

**Analog:** `app/Data/ReviewRunCreationResult.php`

**Static constructor result pattern** (`app/Data/ReviewRunCreationResult.php:7-45`):
```php
class ReviewRunCreationResult
{
    private function __construct(
        private readonly bool $successful,
        private readonly ?ReviewRun $reviewRun,
        private readonly ?string $errorCode,
        private readonly string $message,
    ) {
    }

    public static function success(ReviewRun $reviewRun): self
    {
        return new self(true, $reviewRun, null, 'Review run created.');
    }

    public static function failure(string $errorCode, string $message): self
    {
        return new self(false, null, $errorCode, $message);
    }
}
```

**What to copy**
- Keep explicit `success()` and `failure()` named constructors.
- Expose controller-friendly accessors like `successful()`, `message()`, and `errorCode()`.

---

### `app/Repositories/ReviewRunRepository.php` (repository, CRUD)

**Analog:** `app/Repositories/ReviewRunRepository.php`

**Create/update repository pattern** (`app/Repositories/ReviewRunRepository.php:12-17`):
```php
public function createPendingForPullRequest(PullRequest $pullRequest): ReviewRun
{
    return ReviewRun::create([
        'pull_request_id' => $pullRequest->id,
        'status' => ReviewRunStatus::Pending,
    ]);
}
```

**Query with eager loading pattern** (`app/Repositories/ReviewRunRepository.php:23-37`):
```php
public function recentWithPullRequestRepository(int $limit = 25): Collection
{
    return ReviewRun::query()
        ->with('pullRequest.repository')
        ->latest()
        ->limit($limit)
        ->get();
}

public function findWithPullRequestRepositoryOrFail(int|string $id): ReviewRun
{
    return ReviewRun::query()
        ->with('pullRequest.repository')
        ->findOrFail($id);
}
```

**What to copy**
- Extend this repository rather than bypassing it.
- Put snapshot persistence and failure-state updates here, including eager-loading any new `reviewRunFiles` relation needed by the detail page.

---

### `app/Models/ReviewRun.php` (model, CRUD)

**Analog:** `app/Models/ReviewRun.php`

**Fillable/casts pattern** (`app/Models/ReviewRun.php:10-45`):
```php
#[Fillable([
    'pull_request_id',
    'status',
    'safe_error_message',
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
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
```

**Relation pattern** (`app/Models/ReviewRun.php:22-28`):
```php
public function pullRequest(): BelongsTo
{
    return $this->belongsTo(PullRequest::class);
}
```

**What to copy**
- Add only genuinely needed snapshot fields and casts.
- Put the new `hasMany` relation for stored PR files here if files are owned by the review run.

---

### `app/Models/ReviewRunFile.php` (model, CRUD)

**Analog:** `app/Models/PullRequest.php`

**Fillable + relations pattern** (`app/Models/PullRequest.php:10-27`):
```php
#[Fillable(['repository_id', 'number', 'source_url'])]
class PullRequest extends Model
{
    public function repository(): BelongsTo
    {
        return $this->belongsTo(GitHubRepository::class, 'repository_id');
    }

    public function reviewRuns(): HasMany
    {
        return $this->hasMany(ReviewRun::class);
    }
}
```

**What to copy**
- Use attribute-based `#[Fillable([...])]`.
- Model the snapshot file as a simple relational record with one parent relation and explicit casts only if needed.

---

### `database/migrations/*_create_review_run_files_table.php` (migration, batch)

**Analog:** `database/migrations/2026_06_27_000002_create_pull_requests_table.php`

**Foreign key + unique/index pattern** (`database/migrations/2026_06_27_000002_create_pull_requests_table.php:12-22`):
```php
Schema::create('pull_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('repository_id')->constrained('repositories')->cascadeOnDelete();
    $table->unsignedInteger('number');
    $table->string('source_url');
    $table->timestamps();

    $table->unique(['repository_id', 'number']);
});
```

**What to copy**
- Use the same anonymous migration class style.
- Prefer foreign keys with `cascadeOnDelete()`.
- Add only schema needed for `filename`, `patch`, `sha`, and the owning review-run key.

---

### `database/migrations/*_add_github_snapshot_columns_to_review_runs_table.php` (migration, batch)

**Analog:** `database/migrations/2026_06_27_000003_create_review_runs_table.php`

**Lifecycle/timestamp column pattern** (`database/migrations/2026_06_27_000003_create_review_runs_table.php:14-24`):
```php
Schema::create('review_runs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('pull_request_id')->constrained('pull_requests')->cascadeOnDelete();
    $table->string('status');
    $table->string('safe_error_message')->nullable();
    $table->timestamp('queued_at')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('failed_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->timestamps();
});
```

**What to copy**
- Match existing nullable timestamp/string column style.
- Any fetch-time metadata added to `review_runs` should stay small and explicit, not a raw upstream JSON dump.

---

### `app/Providers/AppServiceProvider.php` (provider, request-response)

**Analog:** `app/Providers/AppServiceProvider.php`

**Provider skeleton** (`app/Providers/AppServiceProvider.php:7-23`):
```php
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
```

**What to copy**
- Put the `GitHubClient` interface binding in `register()`.
- Keep the binding here unless the planner decides the GitHub integration deserves its own provider later.

---

### `config/services.php` (config, request-response)

**Analog:** `config/services.php`

**Third-party config pattern** (`config/services.php:3-38`):
```php
return [
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],
```

**What to copy**
- Add a `github` config entry here if the concrete client needs base URL, API version, or future token keys.
- Read config in the concrete client or provider; do not call `env()` in application services.

---

### `tests/Feature/GitHubPullRequestIngestionTest.php` (test, request-response)

**Analog:** `tests/Feature/ReviewRunSubmissionTest.php`

**Feature test structure** (`tests/Feature/ReviewRunSubmissionTest.php:10-67`):
```php
class ReviewRunSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_pull_request_url_creates_pending_review_run_and_redirects_to_detail(): void
    {
        $response = $this->post('/reviews', [
            'pr_url' => 'https://github.com/owner/repo/pull/123',
        ]);

        $reviewRun = ReviewRun::firstOrFail();

        $response
            ->assertRedirect('/reviews/'.$reviewRun->id)
            ->assertSessionHas('status', 'Review run created.');
    }
}
```

**What to copy**
- Use `RefreshDatabase`.
- Test the real route/controller/service/repository path.
- For ingestion tests, block real network calls and assert stored snapshot rows plus flash success.

---

### `tests/Feature/GitHubPullRequestIngestionFailureTest.php` (test, request-response)

**Analog:** `tests/Feature/ReviewRunHistoryAndDetailTest.php`

**Safe failure assertion pattern** (`tests/Feature/ReviewRunHistoryAndDetailTest.php:62-88`):
```php
public function test_failed_review_detail_displays_only_safe_error_copy_and_next_step(): void
{
    $failed = $this->createReviewRun(
        status: ReviewRunStatus::Failed,
        safeErrorMessage: 'GitHub returned a safe validation error.',
    );

    $this->get('/reviews/'.$failed->id)
        ->assertOk()
        ->assertSee('Review run failed')
        ->assertSee('GitHub returned a safe validation error.')
        ->assertSee('Review the safe error summary, then create a new run after fixing the source issue.');
}
```

**What to copy**
- Assert failure state through user-visible safe copy.
- Cover at least not-found/unreadable, rate-limit, upstream/server, malformed-response, and future auth-failure categories.

---

### `tests/Unit/GitHub/GitHubFailureMapperTest.php` (test, transform)

**Analog:** `tests/Feature/ReviewRunCreationServiceTest.php`

**Table-driven test pattern** (`tests/Feature/ReviewRunCreationServiceTest.php:67-92`):
```php
$cases = [
    'not a url' => 'invalid_url',
    'https://example.com/owner/repo/pull/123' => 'not_github_pr_url',
    'https://github.com/owner/repo/issues/123' => 'not_github_pr_url',
];

foreach ($cases as $url => $expectedCode) {
    $result = $service->createFromPullRequestUrl($url);

    $this->assertFalse($result->successful(), $url);
    $this->assertSame($expectedCode, $result->errorCode(), $url);
}
```

**What to copy**
- Use table-driven cases for GitHub status/header/exception inputs.
- Keep this test fast and pure; do not boot full HTTP fakes here unless the mapper truly depends on responses.

---

### `tests/Fixtures/GitHub/*.json` (test, file-I/O)

**Analog:** none

**What to copy from the repo**
- There is no fixture-file precedent yet.
- Place fixtures under a stable shared directory exactly as the research file recommends: `tests/Fixtures/GitHub/`.
- Keep payloads close to GitHub REST response shapes so later AI-review phases can reuse them.

## Shared Patterns

### Controller / Service / Repository layering
**Sources:** `app/Http/Controllers/ReviewController.php:20-39`, `app/Services/ReviewRunService.php:22-38`, `app/Repositories/ReviewRunRepository.php:12-37`
```php
$result = $reviewRunService->createFromPullRequestUrl($validated['pr_url']);

$reviewRun = $this->reviewRuns->createPendingForPullRequest($pullRequest);

return ReviewRun::query()
    ->with('pullRequest.repository')
    ->findOrFail($id);
```

**Apply to:** All ingestion workflow files

**Rule**
- Controllers stay HTTP-only.
- Services orchestrate business flow.
- Repositories own Eloquent persistence and eager-loading.

### Stable safe failure messaging
**Sources:** `app/Services/ReviewRunService.php:26-30`, `app/Services/ReviewRunService.php:40-47`, `resources/views/reviews/show.blade.php:33-41`
```php
return ReviewRunCreationResult::failure(
    $reference,
    $this->messageForErrorCode($reference),
);
```

```blade
<div>{{ $reviewRun->safe_error_message ?: 'The run failed, but no safe error summary was recorded.' }}</div>
```

**Apply to:** `PullRequestIngestionService`, `GitHubFailureMapper`, `ReviewController`, failure tests

**Rule**
- Persist only whitelisted safe summaries.
- Keep error codes stable enough for assertions.

### Open web routes with CSRF-backed forms
**Sources:** `routes/web.php:6-10`, `resources/views/reviews/index.blade.php:11-49`, `tests/Feature/ReviewRunSubmissionTest.php:14-24`
```php
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
```

```blade
<form method="POST" action="{{ route('reviews.store') }}">
    @csrf
```

**Apply to:** Manual fetch route and detail-page form

**Rule**
- No auth middleware pattern exists yet.
- Keep the fetch action as a normal web POST protected by CSRF, not an ad hoc GET trigger.

### Eloquent model style
**Sources:** `app/Models/GitHubRepository.php:5-20`, `app/Models/PullRequest.php:10-27`, `app/Models/ReviewRun.php:10-45`
```php
#[Fillable(['owner', 'name', 'full_name'])]
class GitHubRepository extends Model
```

```php
protected function casts(): array
{
    return [
        'status' => ReviewRunStatus::class,
        'queued_at' => 'datetime',
    ];
}
```

**Apply to:** `ReviewRunFile` and any `ReviewRun` snapshot-field changes

### Migration style
**Sources:** `database/migrations/2026_06_27_000002_create_pull_requests_table.php:12-22`, `database/migrations/2026_06_27_000003_create_review_runs_table.php:14-24`
```php
$table->foreignId('repository_id')->constrained('repositories')->cascadeOnDelete();
$table->unsignedInteger('number');
$table->string('source_url');
```

**Apply to:** Both new ingestion migrations

### Feature test style
**Sources:** `tests/Feature/ReviewRunSubmissionTest.php:10-67`, `tests/Feature/ReviewRunHistoryAndDetailTest.php:13-129`
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReviewRunHistoryAndDetailTest extends TestCase
{
    use RefreshDatabase;
```

**Apply to:** Both new feature test files

## No Analog Found

| File | Role | Data Flow | Reason |
|---|---|---|---|
| `tests/Fixtures/GitHub/*.json` | test | file-I/O | The repo has no existing fixture directory or JSON fixture files yet. |

## Metadata

**Analog search scope:** `app/`, `routes/`, `resources/views/`, `database/migrations/`, `tests/`, `config/`, `bootstrap/app.php`
**Files scanned:** 19 code files + 2 phase docs + `AGENTS.md`
**Pattern extraction date:** 2026-06-27
