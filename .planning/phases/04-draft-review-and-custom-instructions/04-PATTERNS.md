# Phase 04: Draft Review and Custom Instructions - Pattern Map

**Mapped:** 2026-06-28 14:34:05 +0800
**Files analyzed:** 22
**Analogs found:** 22 / 22

## File Classification

| New/Modified File | Role | Data Flow | Closest Analog | Match Quality |
|-------------------|------|-----------|----------------|---------------|
| `app/Http/Controllers/ReviewController.php` | controller | request-response | `app/Http/Controllers/ReviewController.php` | exact |
| `app/Models/ReviewRun.php` | model | CRUD | `app/Models/ReviewRun.php` | exact |
| `app/Models/ReviewFinding.php` | model | CRUD | `app/Models/ReviewFinding.php` | exact |
| `app/Models/ReviewCommentDraft.php` | model | CRUD | `app/Models/ReviewFinding.php` | role-match |
| `app/Models/ReviewInstructionSetting.php` | model | CRUD | `app/Models/GitHubRepository.php` | role-match |
| `app/Enums/ReviewCommentDraftStatus.php` | utility | transform | `app/Enums/ReviewRunStatus.php` | role-match |
| `app/Repositories/ReviewRunRepository.php` | repository | CRUD | `app/Repositories/ReviewRunRepository.php` | exact |
| `app/Repositories/ReviewFindingRepository.php` | repository | CRUD | `app/Repositories/ReviewFindingRepository.php` | exact |
| `app/Repositories/ReviewCommentDraftRepository.php` | repository | CRUD | `app/Repositories/ReviewFindingRepository.php` | role-match |
| `app/Repositories/ReviewInstructionSettingRepository.php` | repository | CRUD | `app/Repositories/GitHubRepositoryRepository.php` | role-match |
| `app/Services/ReviewExecutionService.php` | service | batch | `app/Services/ReviewExecutionService.php` | exact |
| `app/Services/ReviewDraftService.php` | service | batch | `app/Services/ReviewExecutionDispatchService.php` | role-match |
| `app/Services/ReviewInstructionSettingService.php` | service | CRUD | `app/Services/ReviewRunService.php` | role-match |
| `app/Services/AI/ReviewInstructionBuilder.php` | service | transform | `app/Services/AI/ReviewInstructionBuilder.php` | exact |
| `resources/views/reviews/show.blade.php` | component | request-response | `resources/views/reviews/show.blade.php` | exact |
| `routes/web.php` | route | request-response | `routes/web.php` | exact |
| `database/migrations/*_create_review_comment_drafts_table.php` | migration | transform | `database/migrations/2026_06_28_000000_create_review_findings_table.php` | role-match |
| `database/migrations/*_create_review_instruction_settings_table.php` | migration | transform | `database/migrations/2026_06_27_000001_create_repositories_table.php` | role-match |
| `database/migrations/*_add_superseded_at_to_review_findings_table.php` | migration | transform | `database/migrations/2026_06_27_100000_add_github_snapshot_columns_to_review_runs_table.php` | role-match |
| `tests/Feature/ReviewDraftPresentationTest.php` | test | request-response | `tests/Feature/ReviewRunHistoryAndDetailTest.php` | role-match |
| `tests/Feature/ReviewDraftGenerationTest.php` | test | batch | `tests/Feature/QueuedReviewExecutionTest.php` | role-match |
| `tests/Feature/ReviewDraftWorkflowTest.php` | test | request-response | `tests/Feature/ReviewRunSubmissionTest.php` | role-match |
| `tests/Feature/ReviewDraftMetadataTest.php` | test | CRUD | `tests/Feature/QueuedReviewExecutionTest.php` | role-match |
| `tests/Feature/CustomReviewInstructionsTest.php` | test | request-response | `tests/Feature/ReviewRunSubmissionTest.php` | role-match |
| `tests/Feature/CustomReviewInstructionsPersistenceTest.php` | test | CRUD | `tests/Feature/QueuedReviewExecutionTest.php` | role-match |
| `tests/Unit/AI/ReviewInstructionBuilderTest.php` | test | transform | `tests/Unit/AI/FakeAIReviewProviderTest.php` | role-match |

## Pattern Assignments

### `app/Http/Controllers/ReviewController.php` (controller, request-response)

**Analog:** `app/Http/Controllers/ReviewController.php`

**Imports + method shape** (lines 5-11):
```php
use App\Repositories\ReviewRunRepository;
use App\Services\PullRequestIngestionService;
use App\Services\ReviewExecutionDispatchService;
use App\Services\ReviewRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
```

**Validate input in controller, keep business rules in services** (lines 22-40):
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

**POST mutation + redirect-back flash pattern** (lines 50-79):
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

**Apply to Phase 4:** Keep draft generation, draft edit, approve/cancel approval, and custom-instructions update methods thin. Validation belongs here, but state guards and idempotency belong in `ReviewDraftService` / `ReviewInstructionSettingService`. Use named error bags for the multi-form detail page.

---

### `app/Models/ReviewRun.php` (model, CRUD)

**Analog:** `app/Models/ReviewRun.php`

**Fillable + relations + enum cast pattern** (lines 11-24, 35-49, 56-66):
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
```

```php
public function files(): HasMany
{
    return $this->hasMany(ReviewRunFile::class);
}

public function findings(): HasMany
{
    return $this->hasMany(ReviewFinding::class);
}
```

```php
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
```

**Apply to Phase 4:** Add `drafts()` and, if the planner follows the research recommendation, `currentFindings()` with the same typed `HasMany` style. Add any new datetime casts such as `stale_at` or `superseded_at` to the related models rather than overloading `ReviewRun`.

---

### `app/Models/ReviewFinding.php` (model, CRUD)

**Analog:** `app/Models/ReviewFinding.php`

**Lean persisted shape pattern** (lines 9-18, 20-27):
```php
#[Fillable([
    'review_run_id',
    'severity',
    'category',
    'file_path',
    'line_reference',
    'title',
    'rationale',
    'suggested_comment_text',
])]
```

```php
public function reviewRun(): BelongsTo
{
    return $this->belongsTo(ReviewRun::class);
}
```

**Apply to Phase 4:** Extend this model with only the extra provenance fields the retry strategy needs, such as `superseded_at`, while keeping AI output itself immutable. If the planner adds `sourceDrafts()` later, follow the same relationship style.

---

### `app/Models/ReviewCommentDraft.php` (model, CRUD)

**Analog:** `app/Models/ReviewFinding.php` and `app/Models/ReviewRun.php`

**Copy fillable layout from `ReviewFinding`** (lines 9-18) and **copy cast style from `ReviewRun`** (lines 56-66). Use:
- attribute-based `#[Fillable([...])]`
- typed relationship methods
- enum casts for status
- datetime casts for stale/approved/posted/failed timestamps if introduced

**Concrete relation pattern to copy** from `ReviewRun.php` lines 43-49:
```php
public function findings(): HasMany
{
    return $this->hasMany(ReviewFinding::class);
}
```

**Apply to Phase 4:** The draft model should mirror current finding metadata explicitly: `review_run_id`, `source_review_finding_id`, `status`, editable comment body, copied `file_path` / `line_reference`, and stale metadata. Keep GitHub publication fields limited to what Phase 4 already knows.

---

### `app/Models/ReviewInstructionSetting.php` (model, CRUD)

**Analog:** `app/Models/GitHubRepository.php`

**Minimal singleton model pattern** (lines 9-20):
```php
#[Fillable(['owner', 'name', 'full_name'])]
class GitHubRepository extends Model
{
    protected $table = 'repositories';
}
```

**Apply to Phase 4:** Keep the settings model similarly small: one table name if needed, narrow fillable list, and no unnecessary relationships. This is mutable application settings data, not generated AI output.

---

### `app/Enums/ReviewCommentDraftStatus.php` (utility, transform)

**Analog:** `app/Enums/ReviewRunStatus.php`

**Enum format** (lines 3-12):
```php
namespace App\Enums;

enum ReviewRunStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
```

**Apply to Phase 4:** Follow the exact enum layout and naming style for `Draft`, `Approved`, `Posted`, and `Failed`. Use the enum in model casts instead of stringly-typed status checks scattered through services and Blade.

---

### `app/Repositories/ReviewRunRepository.php` (repository, CRUD)

**Analog:** `app/Repositories/ReviewRunRepository.php`

**Eager-load detail aggregates in repository** (lines 35-40):
```php
public function findWithPullRequestRepositoryOrFail(int|string $id): ReviewRun
{
    return ReviewRun::query()
        ->with(['files', 'findings', 'pullRequest.repository'])
        ->findOrFail($id);
}
```

**Transactional persistence pattern** (lines 45-69):
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

    foreach ($files as $file) {
        $reviewRun->files()->create([
            'filename' => $file->filename,
            'patch' => $file->patch,
            'sha' => $file->sha,
        ]);
    }

    return $reviewRun->refresh()->load(['files', 'findings', 'pullRequest.repository']);
});
```

**State transition pattern** (lines 83-133): use `forceFill([...])->save()` followed by `refresh()->load(...)`.

**Apply to Phase 4:** Expand the eager load list to whatever the detail page needs in one read: current findings, drafts, source finding, and instruction settings if the controller renders them together. Keep the refresh/load pattern after every repository mutation.

---

### `app/Repositories/ReviewFindingRepository.php` (repository, CRUD)

**Analog:** `app/Repositories/ReviewFindingRepository.php`

**Current batch write pattern** (lines 14-22):
```php
public function replaceForReviewRun(ReviewRun $reviewRun, array $findings): void
{
    DB::transaction(function () use ($reviewRun, $findings): void {
        $reviewRun->findings()->delete();

        foreach ($findings as $finding) {
            $reviewRun->findings()->create($finding->toDatabaseArray());
        }
    });
}
```

**Apply to Phase 4:** Keep the repository in charge of writing findings, but replace hard deletion with a current/superseded write pattern so preserved drafts can retain source-finding provenance. The transaction boundary stays here or at the orchestration service, not in the controller.

---

### `app/Repositories/ReviewCommentDraftRepository.php` (repository, CRUD)

**Analog:** `app/Repositories/ReviewFindingRepository.php` and `app/Repositories/ReviewRunRepository.php`

**Batch create loop to copy** from `ReviewFindingRepository.php` lines 16-21:
```php
DB::transaction(function () use ($reviewRun, $findings): void {
    foreach ($findings as $finding) {
        $reviewRun->findings()->create($finding->toDatabaseArray());
    }
});
```

**Refresh/load-after-write pattern to copy** from `ReviewRunRepository.php` lines 68-69:
```php
return $reviewRun->refresh()->load(['files', 'findings', 'pullRequest.repository']);
```

**Apply to Phase 4:** Put idempotent draft creation, stale marking, and status/body updates here. Use repository methods like:
- `createMissingForFindings(...)`
- `markStaleForReviewRun(...)`
- `updateDraftBody(...)`
- `approveDrafts(...)`
- `revertApproval(...)`

Keep uniqueness and row-selection logic out of Blade and out of controller methods.

---

### `app/Repositories/ReviewInstructionSettingRepository.php` (repository, CRUD)

**Analog:** `app/Repositories/GitHubRepositoryRepository.php`

**Simple persistence API pattern** (lines 8-19):
```php
class GitHubRepositoryRepository
{
    public function findOrCreateFromReference(GitHubPullRequestReference $reference): GitHubRepository
    {
        return GitHubRepository::firstOrCreate(
            ['full_name' => $reference->fullName()],
            [
                'owner' => $reference->owner,
                'name' => $reference->repositoryName,
            ],
        );
    }
}
```

**Apply to Phase 4:** Follow the same small-surface repository style for the singleton settings row, but use `updateOrCreate` rather than `firstOrCreate`. Keep the lookup key stable, for example `scope = global`.

---

### `app/Services/ReviewExecutionService.php` (service, batch)

**Analog:** `app/Services/ReviewExecutionService.php`

**Constructor injection pattern** (lines 17-24):
```php
public function __construct(
    private readonly ReviewRunRepository $reviewRuns,
    private readonly ReviewFindingRepository $findings,
    private readonly AIReviewProvider $provider,
    private readonly AIReviewPayloadValidator $validator,
    private readonly AIReviewFailureMapper $failureMapper,
    private readonly ReviewInstructionBuilder $instructionBuilder,
) {}
```

**Happy-path orchestration pattern** (lines 31-48):
```php
try {
    $payload = json_decode(
        $this->provider->review($this->makeRequest($reviewRun)),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    if (! is_array($payload)) {
        throw new \UnexpectedValueException('AI review payload must be an object.');
    }

    $validatedFindings = $this->validator->validate($payload);

    DB::transaction(function () use ($reviewRun, $validatedFindings): void {
        $this->findings->replaceForReviewRun($reviewRun, $validatedFindings);
        $this->reviewRuns->markCompleted($reviewRun);
    });
} catch (\Throwable $throwable) {
```

**Safe failure mapping pattern** (lines 49-53):
```php
} catch (\Throwable $throwable) {
    $failure = $this->failureMapper->map($throwable);

    $this->reviewRuns->markExecutionFailed($reviewRun, $failure->message);
}
```

**Apply to Phase 4:** This is the exact seam for retry-safe stale handling and instruction composition. Keep provider calls, JSON decode, validation, and failure mapping unchanged in shape. Only extend the transaction body to mark old drafts stale and store current findings safely.

---

### `app/Services/ReviewDraftService.php` (service, batch)

**Analog:** `app/Services/ReviewExecutionDispatchService.php` and `app/Services/PullRequestIngestionService.php`

**Thin input normalization pattern** from `ReviewExecutionDispatchService.php` lines 14-19:
```php
public function dispatch(ReviewRun|int|string $reviewRun): ReviewExecutionResult
{
    $reviewRun = $reviewRun instanceof ReviewRun
        ? $reviewRun->loadMissing('pullRequest.repository', 'files', 'findings')
        : $this->reviewRuns->findWithPullRequestRepositoryOrFail($reviewRun);
```

**Service-owned rule checks pattern** from `ReviewExecutionDispatchService.php` lines 20-32:
```php
if ($reviewRun->github_fetched_at === null) {
    return ReviewExecutionResult::failure(
        $reviewRun,
        'Fetch GitHub pull request data before running AI review.',
        'github_snapshot_missing',
    );
}
```

**Try/catch around external-or-risky work** from `PullRequestIngestionService.php` lines 29-50:
```php
try {
    // work
    return PullRequestIngestionResult::success($reviewRun);
} catch (\Throwable $throwable) {
    $failure = $this->failureMapper->map($throwable);
    $reviewRun = $this->reviewRuns->markGitHubFetchFailed($reviewRun, $failure->message);

    return PullRequestIngestionResult::failure($reviewRun, $failure);
}
```

**Apply to Phase 4:** Build all draft generation and state-transition rules here:
- create drafts only for findings without an existing draft
- permit body edits only while status is `draft`
- approve without publishing
- cancel approval back to `draft`
- block or flag stale approval according to the chosen policy

Return a small result object or reuse the current flash-message style so controllers remain thin.

---

### `app/Services/ReviewInstructionSettingService.php` (service, CRUD)

**Analog:** `app/Services/ReviewRunService.php`

**Small workflow service pattern** (lines 14-38):
```php
public function __construct(
    private readonly GitHubPullRequestUrlParser $parser,
    private readonly GitHubRepositoryRepository $repositories,
    private readonly PullRequestRepository $pullRequests,
    private readonly ReviewRunRepository $reviewRuns,
) {
}

public function createFromPullRequestUrl(string $url): ReviewRunCreationResult
{
    $reference = $this->parser->parse($url);

    if (! $reference instanceof GitHubPullRequestReference) {
        return ReviewRunCreationResult::failure(
            $reference,
            $this->messageForErrorCode($reference),
        );
    }
```

**Apply to Phase 4:** Use the same constructor/property style and return-shape discipline for saving global instructions. The controller should validate the textarea, and this service should decide how to normalize blank values and how to persist the singleton row through its repository.

---

### `app/Services/AI/ReviewInstructionBuilder.php` (service, transform)

**Analog:** `app/Services/AI/ReviewInstructionBuilder.php`

**Current deterministic string builder** (lines 7-18):
```php
public function buildDefault(): string
{
    return implode("\n", [
        'Review this GitHub pull request for actionable code review findings.',
        'Prioritize bug and security findings first.',
        'Include performance or maintainability findings when they are warranted.',
        'Include style findings only when they are useful and not noisy.',
        'Use only these severity labels: critical, high, medium, low.',
        'Use only these category labels: bug, security, performance, maintainability, style.',
        'Return JSON with a findings array. Each finding must include severity, category, file_path, line_reference, title, rationale, and suggested_comment_text.',
        'Do not include comment draft state, approval state, or GitHub publication metadata.',
    ]);
}
```

**Apply to Phase 4:** Keep the default block intact and add deterministic composition, not ad hoc concatenation from controllers. The builder is the right place to combine default instructions with the persisted global custom text.

---

### `resources/views/reviews/show.blade.php` (component, request-response)

**Analog:** `resources/views/reviews/show.blade.php`

**Sectioned detail-page layout** (lines 20-35, 37-48):
```blade
<div class="detail-header">
    <h1>Review Run #{{ $reviewRun->id }}</h1>
    <x-review-status :status="$reviewRun->status" />
</div>

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

**Mutation form pattern** (lines 50-64):
```blade
<form method="POST" action="{{ route('reviews.fetch', $reviewRun) }}" style="margin-bottom: 20px;">
    @csrf
    <button type="submit">Fetch</button>
</form>

@if ($reviewRun->github_fetched_at)
    <form method="POST" action="{{ route('reviews.run', $reviewRun) }}" style="margin-bottom: 20px;">
        @csrf
        <button type="submit">Run AI Review</button>
    </form>
@endif
```

**Read-only findings rendering pattern** (lines 123-142):
```blade
<section class="section">
    <h2>Structured Findings</h2>
    @if ($reviewRun->findings->isEmpty())
        <p class="muted">No AI review findings have been persisted for this run.</p>
    @else
        <div class="metadata">
            @foreach ($reviewRun->findings as $finding)
```

**Apply to Phase 4:** Preserve the current SSR section pattern. Add a separate `Comment Drafts` section rather than mixing draft actions into the `Structured Findings` block. Keep every state-changing form explicit with `@csrf`.

---

### `routes/web.php` (route, request-response)

**Analog:** `routes/web.php`

**Flat named-route pattern** (lines 6-12):
```php
Route::redirect('/', '/reviews');

Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
Route::post('/reviews/{reviewRun}/fetch', [ReviewController::class, 'fetch'])->name('reviews.fetch');
Route::post('/reviews/{reviewRun}/run', [ReviewController::class, 'run'])->name('reviews.run');
Route::get('/reviews/{reviewRun}', [ReviewController::class, 'show'])->name('reviews.show');
```

**Apply to Phase 4:** Add draft/settings mutation routes adjacent to the existing review-run actions and keep route names under the `reviews.*` namespace. Current codebase has no route groups; follow that unless the planner intentionally introduces one.

---

### `database/migrations/*_create_review_comment_drafts_table.php` (migration, transform)

**Analog:** `database/migrations/2026_06_28_000000_create_review_findings_table.php` and `database/migrations/2026_06_27_100001_create_review_run_files_table.php`

**Create-table shape** from `create_review_findings_table.php` lines 14-28:
```php
Schema::create('review_findings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('review_run_id')->constrained('review_runs')->cascadeOnDelete();
    $table->string('severity');
    $table->string('category');
    $table->string('file_path');
    $table->string('line_reference')->nullable();
    $table->string('title');
    $table->text('rationale');
    $table->text('suggested_comment_text');
    $table->timestamps();
```

**Index pattern** from `create_review_run_files_table.php` lines 16-23:
```php
$table->foreignId('review_run_id')->constrained('review_runs')->cascadeOnDelete();
$table->string('filename');
$table->text('patch');
$table->string('sha', 40);
$table->timestamps();

$table->index(['review_run_id', 'filename']);
```

**Apply to Phase 4:** Follow the same anonymous-class migration style, foreign key layout, and end-of-table indexes. Drafts will likely need:
- foreign keys to `review_runs` and `review_findings`
- status/body fields
- copied targeting metadata fields
- stale metadata
- uniqueness/index support for idempotent generation

---

### `database/migrations/*_create_review_instruction_settings_table.php` (migration, transform)

**Analog:** `database/migrations/2026_06_27_000001_create_repositories_table.php`

**Small singleton table pattern** (lines 12-20):
```php
Schema::create('repositories', function (Blueprint $table) {
    $table->id();
    $table->string('owner');
    $table->string('name');
    $table->string('full_name')->unique();
    $table->timestamps();
});
```

**Apply to Phase 4:** Keep the settings table minimal: one stable lookup key such as `scope`, one nullable text column for instructions, and a unique constraint to enforce singleton behavior through normal Eloquent writes.

---

### `database/migrations/*_add_superseded_at_to_review_findings_table.php` (migration, transform)

**Analog:** `database/migrations/2026_06_27_100000_add_github_snapshot_columns_to_review_runs_table.php`

**Alter-table pattern** (lines 12-19, 25-34):
```php
Schema::table('review_runs', function (Blueprint $table) {
    $table->string('github_title')->nullable()->after('safe_error_message');
    $table->string('github_state')->nullable()->after('github_title');
    $table->string('github_head_sha')->nullable()->after('github_state');
    $table->timestamp('github_fetched_at')->nullable()->after('github_head_sha');
});
```

```php
Schema::table('review_runs', function (Blueprint $table) {
    $table->dropColumn([
        'github_title',
        'github_state',
        'github_head_sha',
        'github_fetched_at',
    ]);
});
```

**Apply to Phase 4:** Use the same additive migration style for `review_findings`, whether the planner picks `superseded_at`, `is_current`, or another provenance-safe column. Keep the rollback explicit.

---

### Test Files (test, mixed)

#### `tests/Feature/ReviewDraftPresentationTest.php`
**Analog:** `tests/Feature/ReviewRunHistoryAndDetailTest.php`

**Feature-test shape** (lines 13-18, 43-60):
```php
class ReviewRunHistoryAndDetailTest extends TestCase
{
    use RefreshDatabase;
```

```php
public function test_review_detail_displays_identity_metadata_and_pending_summary(): void
{
    $reviewRun = app(ReviewRunService::class)
        ->createFromPullRequestUrl('https://github.com/owner/repo/pull/123')
        ->reviewRun();

    $this->get('/reviews/'.$reviewRun->id)
        ->assertOk()
        ->assertSee('Back to review runs')
        ->assertSee('Review Run #'.$reviewRun->id);
}
```

#### `tests/Feature/ReviewDraftGenerationTest.php`
**Analog:** `tests/Feature/QueuedReviewExecutionTest.php`

**Database assertion + flow test pattern** (lines 21-57):
```php
$this->post(route('reviews.run', $reviewRun))
    ->assertRedirect(route('reviews.show', $reviewRun))
    ->assertSessionHas('status', 'AI review queued.');

$reviewRun = ReviewRun::query()->with('findings')->findOrFail($reviewRun->id);

$this->assertDatabaseHas('review_findings', [
    'review_run_id' => $reviewRun->id,
    'severity' => 'high',
    'category' => 'bug',
]);
```

#### `tests/Feature/ReviewDraftWorkflowTest.php`
**Analog:** `tests/Feature/ReviewRunSubmissionTest.php`

**POST + redirect + session assertions** (lines 26-46, 49-67):
```php
$response = $this->post('/reviews', [
    'pr_url' => 'https://github.com/owner/repo/pull/123',
]);

$response
    ->assertRedirect('/reviews/'.$reviewRun->id)
    ->assertSessionHas('status', 'Review run created.');
```

#### `tests/Feature/ReviewDraftMetadataTest.php`
**Analog:** `tests/Feature/QueuedReviewExecutionTest.php`

**Metadata persistence assertions** (lines 43-56):
```php
$this->assertDatabaseHas('review_findings', [
    'review_run_id' => $reviewRun->id,
    'file_path' => 'app/Services/GitHub/HttpGitHubClient.php',
    'line_reference' => '24',
]);
```

#### `tests/Feature/CustomReviewInstructionsTest.php`
**Analog:** `tests/Feature/ReviewRunSubmissionTest.php`

**Form validation + redirect-back style**: follow the same POST assertions, but target the detail-page instructions form and named error bag behavior.

#### `tests/Feature/CustomReviewInstructionsPersistenceTest.php`
**Analog:** `tests/Feature/QueuedReviewExecutionTest.php`

**Use direct database assertions** to prove instructions live outside findings/drafts tables and are reused on future execution paths.

#### `tests/Unit/AI/ReviewInstructionBuilderTest.php`
**Analog:** `tests/Unit/AI/FakeAIReviewProviderTest.php`

**Unit-test layout** (lines 11-18, 37-51):
```php
class FakeAIReviewProviderTest extends TestCase
{
    public function test_container_resolves_fake_provider_by_default(): void
    {
        config(['services.openai.enabled' => false]);

        $this->assertInstanceOf(FakeAIReviewProvider::class, app(AIReviewProvider::class));
    }
}
```

```php
$instructions = app(ReviewInstructionBuilder::class)->buildDefault();

$this->assertStringContainsString('Prioritize bug and security findings first.', $instructions);
$this->assertStringContainsString('Do not include comment draft state', $instructions);
```

**Apply to Phase 4:** Keep unit coverage focused on deterministic string composition. Keep feature coverage focused on page rendering, POST flows, redirects, session flash state, and database persistence.

## Shared Patterns

### Controller Redirect + Flash Messaging
**Source:** `app/Http/Controllers/ReviewController.php` lines 30-40 and 54-79
**Apply to:** All draft/settings controller mutations
```php
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

### Repository-Owned Eager Loading
**Source:** `app/Repositories/ReviewRunRepository.php` lines 35-40
**Apply to:** Review detail loading after draft/settings additions
```php
return ReviewRun::query()
    ->with(['files', 'findings', 'pullRequest.repository'])
    ->findOrFail($id);
```

### Transaction Boundaries for Multi-Write Workflows
**Source:** `app/Repositories/ReviewRunRepository.php` lines 47-69 and `app/Services/ReviewExecutionService.php` lines 45-48
**Apply to:** Draft generation, retry stale-marking, finding supersession, and batch approval
```php
DB::transaction(function () use ($reviewRun, $validatedFindings): void {
    $this->findings->replaceForReviewRun($reviewRun, $validatedFindings);
    $this->reviewRuns->markCompleted($reviewRun);
});
```

### Safe Failure Mapping
**Source:** `app/Services/PullRequestIngestionService.php` lines 45-49 and `app/Services/ReviewExecutionService.php` lines 49-53
**Apply to:** Service-layer mutations that can fail for policy or data-integrity reasons
```php
} catch (\Throwable $throwable) {
    $failure = $this->failureMapper->map($throwable);
    $reviewRun = $this->reviewRuns->markGitHubFetchFailed($reviewRun, $failure->message);

    return PullRequestIngestionResult::failure($reviewRun, $failure);
}
```

### Multi-Form Detail Page Structure
**Source:** `resources/views/reviews/show.blade.php` lines 50-64 and 123-142
**Apply to:** Separate findings, draft actions, and custom instructions on one SSR page
```blade
<section class="section">
    <h2>Structured Findings</h2>
    ...
</section>
```

**Planner note:** There is no in-code analog yet for named validation bags. Use the Laravel pattern captured in `04-RESEARCH.md` lines 223-232 when wiring multiple POST forms on the same page.

### Feature Test Skeleton
**Source:** `tests/Feature/ReviewRunHistoryAndDetailTest.php` lines 13-18 and `tests/Feature/QueuedReviewExecutionTest.php` lines 17-20
**Apply to:** All new Phase 4 feature tests
```php
class ReviewRunHistoryAndDetailTest extends TestCase
{
    use RefreshDatabase;
}
```

## No Exact In-Code Analog Yet

| File / Concern | Role | Data Flow | Reason |
|----------------|------|-----------|--------|
| Named error bag handling on one Blade detail page | controller/view | request-response | Existing code has only one mutation form per concern; use `04-RESEARCH.md` lines 223-232 for the Laravel pattern. |
| Singleton settings persistence via `updateOrCreate` | repository | CRUD | Current repositories use `firstOrCreate`, not `updateOrCreate`; use the small repository surface from `GitHubRepositoryRepository` and the research example together. |
| Retry-safe finding supersession | repository/service | batch | Current implementation hard-deletes findings; Phase 4 must introduce a new provenance-safe variant rather than copy current delete/replace behavior literally. |

## Metadata

**Analog search scope:** `app/`, `database/migrations/`, `routes/`, `resources/views/`, `tests/`
**Files scanned:** 82
**Pattern extraction date:** 2026-06-28
