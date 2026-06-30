# Phase 05: GitHub Comment Publishing - Pattern Map

**Mapped:** 2026-06-29
**Files analyzed:** 12 file groups
**Analogs found:** 12 / 12

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|---|---|---|---|---|
| `routes/web.php` | route | request-response | `routes/web.php` | exact |
| `app/Http/Controllers/ReviewDraftController.php` publish methods or `app/Http/Controllers/ReviewCommentPublishingController.php` | controller | request-response | `app/Http/Controllers/ReviewDraftController.php`, `app/Http/Controllers/ReviewController.php` | role-match |
| `app/Services/ReviewCommentPublishingService.php` | service | request-response | `app/Services/PullRequestIngestionService.php`, `app/Services/ReviewDraftService.php` | role-match |
| `app/Data/GitHub/GitHubCommentPublicationResult.php` and optional publish summary result DTO | data | transform | `app/Data/GitHub/PullRequestSnapshot.php`, `app/Data/GitHub/PullRequestIngestionResult.php` | role-match |
| `app/Repositories/ReviewCommentDraftRepository.php` | repository | CRUD | `app/Repositories/ReviewCommentDraftRepository.php` | exact |
| `app/Contracts/GitHub/GitHubClient.php` | contract | request-response | `app/Contracts/GitHub/GitHubClient.php` | exact |
| `app/Services/GitHub/HttpGitHubClient.php` | service | request-response | `app/Services/GitHub/HttpGitHubClient.php` | exact |
| `app/Services/GitHub/GitHubFailureMapper.php` or publication-specific mapper | service | transform | `app/Services/GitHub/GitHubFailureMapper.php` | exact |
| `app/Models/ReviewCommentDraft.php` and `app/Enums/ReviewCommentDraftStatus.php` | model / enum | CRUD | `app/Models/ReviewCommentDraft.php`, `app/Enums/ReviewCommentDraftStatus.php` | exact |
| `database/migrations/*_add_publication_columns_to_review_comment_drafts_table.php` | migration | CRUD | `database/migrations/2026_06_28_100100_create_review_comment_drafts_table.php` | role-match |
| `resources/views/reviews/show.blade.php` | view | request-response | `resources/views/reviews/show.blade.php` | exact |
| `tests/Feature/*GitHubCommentPublishing*Test.php`, `tests/Unit/GitHub/*Publication*Test.php`, GitHub fixtures | test | request-response | `tests/Feature/ReviewDraftWorkflowTest.php`, `tests/Feature/GitHubPullRequestIngestionFailureTest.php`, `tests/Feature/QueuedReviewFailureTest.php`, `tests/Unit/GitHub/GitHubFailureMapperTest.php` | role-match |

## Pattern Assignments

### `routes/web.php`

**Analog:** `routes/web.php` lines 10-19

Keep Phase 05 publish and retry routes adjacent to existing draft actions and keep them controller-backed:

```php
Route::post('/reviews/{reviewRun}/drafts/generate', [ReviewDraftController::class, 'generate'])->name('reviews.drafts.generate');
Route::patch('/reviews/{reviewRun}/drafts/{reviewCommentDraft}', [ReviewDraftController::class, 'update'])->name('reviews.drafts.update');
Route::post('/reviews/{reviewRun}/drafts/approve', [ReviewDraftController::class, 'approve'])->name('reviews.drafts.approve');
Route::post('/reviews/{reviewRun}/drafts/{reviewCommentDraft}/unapprove', [ReviewDraftController::class, 'unapprove'])->name('reviews.drafts.unapprove');
```

Follow this shape for `publish-approved` and `retry-failed` POST actions; do not put publishing behind a GET route.

### Publish Controller

**Analog:** `app/Http/Controllers/ReviewDraftController.php` lines 11-63, `app/Http/Controllers/ReviewController.php` lines 55-84

Keep the controller thin: call a service, redirect to `reviews.show`, and flash either `status` or safe error session keys.

```php
$result = $service->handle($reviewRun);

if (! $result->successful()) {
    return redirect()
        ->route('reviews.show', $result->reviewRun())
        ->with('service_error_code', $result->errorCode())
        ->with('service_error_message', $result->message());
}

return redirect()
    ->route('reviews.show', $result->reviewRun())
    ->with('status', $result->message());
```

If the planner keeps publish actions in `ReviewDraftController`, mirror the existing method signatures and redirect style.

### `app/Services/ReviewCommentPublishingService.php`

**Analog:** `app/Services/PullRequestIngestionService.php` lines 20-49, `app/Services/ReviewDraftService.php` lines 44-99

Use constructor injection for the GitHub client, review-run repository, draft repository, and failure mapper. Keep business rules here, not in the controller.

```php
$reviewRun = $reviewRun instanceof ReviewRun
    ? $reviewRun->loadMissing('pullRequest.repository')
    : $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRun);
```

```php
DB::transaction(function () use ($reviewRun, $draftId): void {
    $draft = $this->drafts->findForReviewRunOrFail($reviewRun, $draftId);

    if (! $draft->status->isApproved()) {
        throw new AuthorizationException('Only approved comment drafts can be returned to draft.');
    }

    $this->drafts->markDraft($draft);
});
```

Apply the same guard style for publishable sets:
- `Publish Approved` only loads `approved` drafts.
- `Retry Failed` only loads `failed` drafts.
- Mark each draft independently so one GitHub failure does not roll back earlier successes. This is an implementation inference from D-14.

Prefer a small summary/result object, following `PullRequestIngestionResult`, so the controller does not format publish counts itself.

### Publication DTOs

**Analog:** `app/Data/GitHub/PullRequestSnapshot.php` lines 5-44, `app/Data/GitHub/PullRequestIngestionResult.php` lines 7-45

New GitHub publication result objects should stay small and readonly:

```php
readonly class PullRequestSnapshot
{
    public function __construct(
        public string $title,
        public string $state,
        public string $headSha,
    ) {
    }
}
```

```php
public static function failure(ReviewRun $reviewRun, GitHubFailure $failure): self
{
    return new self(false, $reviewRun, $failure->message, $failure->code);
}
```

Store only the fields the phase requires: GitHub comment id, HTML URL, posted timestamp, and optionally publication mode.

### `app/Repositories/ReviewCommentDraftRepository.php`

**Analog:** `app/Repositories/ReviewCommentDraftRepository.php` lines 36-103

Keep all draft queries and state mutations here.

```php
return ReviewCommentDraft::query()
    ->where('review_run_id', $reviewRun->id)
    ->whereIn('id', $draftIds)
    ->orderBy('id')
    ->get();
```

```php
$draft->forceFill(['status' => ReviewCommentDraftStatus::Draft])->save();

return $draft->refresh();
```

Phase 05 repository additions should follow the same style:
- `approvedForReviewRun(...)`
- `failedForReviewRun(...)`
- `markPosted(...)`
- `markPublicationFailed(...)`

Use `forceFill(...)->save()` for per-draft updates, clear stale failure fields on success, and refresh before returning.

### GitHub Client Boundary

**Analog:** `app/Contracts/GitHub/GitHubClient.php` lines 7-14, `app/Services/GitHub/HttpGitHubClient.php` lines 13-85, `app/Providers/AppServiceProvider.php` lines 17-26

Extend the existing interface instead of calling `Http::` from a publishing service:

```php
interface GitHubClient
{
    public function getPullRequest(string $owner, string $repository, int $pullRequestNumber): PullRequestSnapshot;

    public function listPullRequestFiles(string $owner, string $repository, int $pullRequestNumber): array;
}
```

```php
$request = Http::baseUrl((string) config('services.github.base_url', 'https://api.github.com'))
    ->accept('application/vnd.github+json')
    ->withHeaders([
        'X-GitHub-Api-Version' => (string) config('services.github.api_version', '2022-11-28'),
    ]);
```

```php
$this->app->bind(GitHubClient::class, HttpGitHubClient::class);
```

Follow the same pattern for:
- `createPullRequestReviewComment(...)`
- `createPullRequestIssueComment(...)`

Keep token/config/header handling inside `HttpGitHubClient::request()`.

### Safe GitHub Failure Mapping

**Analog:** `app/Services/GitHub/GitHubFailureMapper.php` lines 11-79, `tests/Unit/GitHub/GitHubFailureMapperTest.php` lines 14-68

Map HTTP failures to safe categories only; never persist raw GitHub bodies or token fragments.

```php
if ($status === 401 || $status === 403 && $this->hasConfiguredToken() && ! $this->isRateLimited($exception)) {
    return new GitHubFailure(
        'auth_failed',
        'GitHub rejected the configured token. Check the token before trying again.',
    );
}
```

```php
if ($throwable instanceof \UnexpectedValueException) {
    return new GitHubFailure(
        'malformed_response',
        'GitHub returned an unexpected response. Try again later.',
    );
}
```

Phase 05 can extend this mapper with a publication-safe `target_invalid` case, but it should keep the same input and output shape.

### Draft Model, Enum, and Migration

**Analog:** `app/Models/ReviewCommentDraft.php` lines 11-53, `app/Enums/ReviewCommentDraftStatus.php` lines 5-20, `database/migrations/2026_06_28_100100_create_review_comment_drafts_table.php` lines 14-30

Preserve attribute-based fillable configuration and explicit casts:

```php
#[Fillable([
    'review_run_id',
    'source_review_finding_id',
    'status',
    'body',
    'file_path',
    'line_reference',
    'github_head_sha',
    'source_file_sha',
    'stale_at',
])]
```

```php
protected function casts(): array
{
    return [
        'status' => ReviewCommentDraftStatus::class,
        'stale_at' => 'datetime',
    ];
}
```

```php
$table->index(['review_run_id', 'status']);
```

Phase 05 should add publication columns with an additive migration and extend the enum with helpers like `isPosted()` and `isFailed()` for cleaner Blade and service guards.

### `resources/views/reviews/show.blade.php`

**Analog:** `resources/views/reviews/show.blade.php` lines 144-206

Keep publish controls inside the existing Comment Drafts section and continue to branch directly on enum helper methods.

```blade
<form id="approve-drafts-form" method="POST" action="{{ route('reviews.drafts.approve', $reviewRun) }}" style="margin-bottom: 16px;">
    @csrf
    <button type="submit">Approve Selected</button>
</form>
```

```blade
@if ($draft->status->isDraft())
    ...
@else
    {{ $draft->body }}<br>
@endif

@if ($draft->status->isApproved())
    <form method="POST" action="{{ route('reviews.drafts.unapprove', [$reviewRun, $draft]) }}">
```

Follow this exact section-local pattern for:
- `Publish Approved` button when approved drafts exist
- `Retry Failed` button when failed drafts exist
- per-row posted link and posted timestamp
- per-row safe failure message

Posted drafts should remain read-only in the UI.

### Feature and Unit Tests

**Analog:** `tests/Feature/ReviewDraftWorkflowTest.php` lines 53-112, `tests/Feature/GitHubPullRequestIngestionFailureTest.php` lines 17-185, `tests/Feature/QueuedReviewFailureTest.php` lines 24-137, `tests/Feature/GitHubPullRequestIngestionTest.php` lines 20-163

Use feature tests for route behavior, flash messages, and database state:

```php
$this->post(route('reviews.drafts.approve', $reviewRun), [
    'draft_ids' => [$firstDraft->id, $secondDraft->id],
])
    ->assertRedirect(route('reviews.show', $reviewRun))
    ->assertSessionHas('status', 'Approved 2 comment drafts.');
```

```php
$this->post(route('reviews.fetch', $reviewRun))
    ->assertRedirect(route('reviews.show', $reviewRun))
    ->assertSessionHas('service_error_code', 'not_found_or_unreadable');
```

For fakeable boundary tests, copy the AI-provider replacement pattern and apply it to `GitHubClient`:

```php
$this->app->instance(AIReviewProvider::class, new class implements AIReviewProvider
{
    public function review(AIReviewRequest $request): string
    {
        return '{"findings": [';
    }
});
```

Phase 05 should use the same container-swap approach with `GitHubClient::class` so publish tests can fake:
- successful line-level comment publication
- successful fallback issue comment publication
- partial failures across multiple drafts
- retry success after previous failure

Use `Http::fake()` only for `HttpGitHubClient`-level tests that verify endpoint shape, headers, and payload parsing.

## Shared Patterns

### Layering

Copy the existing split exactly:
- controllers validate and redirect: `app/Http/Controllers/ReviewDraftController.php` lines 20-63
- services own workflow and guards: `app/Services/ReviewDraftService.php` lines 44-99 and `app/Services/PullRequestIngestionService.php` lines 20-49
- repositories own Eloquent reads and writes: `app/Repositories/ReviewCommentDraftRepository.php` lines 36-103

### Fakeable External Boundary

Keep all GitHub publication calls behind `App\Contracts\GitHub\GitHubClient`, bound in `app/Providers/AppServiceProvider.php` lines 17-26. Feature tests should replace the interface binding instead of reaching live HTTP.

### Safe Errors

Mirror the safe-message discipline from `app/Services/GitHub/GitHubFailureMapper.php` lines 11-79 and `tests/Feature/GitHubPullRequestIngestionFailureTest.php` lines 159-179. No raw body, token, header, or request fragment belongs in draft error storage or UI.

### Partial Success Semantics

Follow the repo’s existing per-record mutation style in `ReviewCommentDraftRepository` and `ReviewRunRepository`; do not wrap the whole publish-all batch in a single all-or-nothing transaction. Persist each draft result independently so already-posted comments stay posted.

## No Exact Analog

| File | Reason | Fallback Pattern |
|---|---|---|
| `app/Data/GitHub/GitHubCommentPublicationResult.php` | No publication DTO exists yet | Copy readonly constructor style from `app/Data/GitHub/PullRequestSnapshot.php` |
| Fake `GitHubClient` feature tests | No existing test swaps the GitHub interface yet | Copy interface replacement style from `tests/Feature/QueuedReviewFailureTest.php` |
| `target_invalid` publication failure category | Existing mapper is fetch-oriented | Extend `app/Services/GitHub/GitHubFailureMapper.php` without changing its safe-output contract |

## Metadata

**Analog search scope:** `routes/`, `app/Http/Controllers/`, `app/Services/`, `app/Repositories/`, `app/Contracts/`, `app/Data/`, `app/Models/`, `resources/views/`, `database/migrations/`, `tests/Feature/`, `tests/Unit/`

## PATTERN MAPPING COMPLETE
