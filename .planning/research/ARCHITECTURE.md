# Architecture Research

**Domain:** AI-assisted GitHub pull request review tool
**Researched:** 2026-06-26
**Confidence:** HIGH

## Standard Architecture

### System Overview

```text
Browser UI
  -> Laravel Controllers
      -> Services
          -> Repositories
              -> SQLite / future DB
          -> GitHub Client
          -> AI Review Provider Interface
      -> Queue Jobs
          -> Services
              -> GitHub Client
              -> AI Review Provider Interface
              -> Repositories
```

### Component Responsibilities

| Component | Responsibility | Typical Implementation |
|-----------|----------------|------------------------|
| Controllers | HTTP request validation, response/view selection | `app/Http/Controllers/*Controller.php` |
| Services | Business workflows and orchestration | `app/Services/*Service.php` |
| Repositories | Database reads/writes | `app/Repositories/*Repository.php` |
| Jobs | Async execution of review workflow | `app/Jobs/RunPullRequestReview.php` |
| GitHub client | External GitHub API calls | `app/Services/GitHub/GitHubClient.php` or interface |
| AI provider | Structured review generation | `app/Services/AiReview/AiReviewProviderInterface.php` |
| Views | Management UI | Blade views under `resources/views/` |

## Recommended Project Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── ReviewRunController.php
│   │   ├── ReviewDraftController.php
│   │   └── ReviewSettingsController.php
│   └── Requests/
│       ├── StoreReviewRunRequest.php
│       ├── PublishReviewDraftRequest.php
│       └── UpdateReviewSettingsRequest.php
├── Jobs/
│   └── RunPullRequestReview.php
├── Models/
│   ├── ReviewRun.php
│   ├── ReviewFinding.php
│   ├── ReviewCommentDraft.php
│   └── ReviewSetting.php
├── Repositories/
│   ├── ReviewRunRepository.php
│   ├── ReviewFindingRepository.php
│   ├── ReviewCommentDraftRepository.php
│   └── ReviewSettingRepository.php
└── Services/
    ├── ReviewRuns/
    │   ├── CreateReviewRunService.php
    │   ├── ExecuteReviewRunService.php
    │   └── PublishReviewDraftService.php
    ├── GitHub/
    │   ├── GitHubClientInterface.php
    │   └── GitHubHttpClient.php
    └── AiReview/
        ├── AiReviewProviderInterface.php
        ├── OpenAiReviewProvider.php
        ├── FakeAiReviewProvider.php
        └── ReviewOutputSchema.php
```

### Structure Rationale

- **Controllers:** Stay thin and Laravel-native. They validate input, call services, and return views/redirects.
- **Services:** Own workflows such as "create a run", "execute a run", and "publish approved drafts".
- **Repositories:** Encapsulate persistence and query intent. Services should not scatter Eloquent query logic.
- **Jobs:** Contain queue boundary and delegate real work to services.
- **Provider interfaces:** Keep GitHub and AI integrations swappable and fakeable.

## Architectural Patterns

### Pattern 1: Draft-First Review Publishing

**What:** AI findings produce `ReviewCommentDraft` records. The user approves drafts before any GitHub write call.

**When to use:** Always in v1.

**Trade-offs:** Adds UI and state but preserves trust and prevents noisy AI comments.

### Pattern 2: Schema-Shaped AI Output

**What:** AI provider returns a strict shape such as `{ findings: [...] }` with severity, category, path, line, side, rationale, and draft body.

**When to use:** When converting model output into database records and GitHub comments.

**Trade-offs:** Requires schema/versioning work, but avoids brittle prose parsing.

### Pattern 3: Queue Boundary Around Review Execution

**What:** Controller creates a `ReviewRun`, dispatches `RunPullRequestReview`, and immediately returns.

**When to use:** Any workflow involving GitHub API calls, AI calls, or comment generation.

**Trade-offs:** Requires status persistence and worker execution, but avoids slow request timeouts.

### Pattern 4: Provider Interface + Fake Provider

**What:** Services depend on an interface, while tests bind a fake implementation.

**When to use:** GitHub and AI integrations.

**Trade-offs:** Slightly more structure up front, much easier tests and future provider swaps.

## Data Flow

### Manual Review Request Flow

```text
User submits PR URL
  -> ReviewRunController@store
  -> CreateReviewRunService
  -> ReviewRunRepository creates queued run
  -> RunPullRequestReview job dispatched
  -> UI redirects to review detail/status page
```

### Review Execution Flow

```text
RunPullRequestReview job
  -> ExecuteReviewRunService
  -> GitHubClient fetches PR metadata/files
  -> Diff normalizer builds review input
  -> AiReviewProvider returns structured findings
  -> Repositories persist findings and comment drafts
  -> ReviewRun marked completed or failed
```

### Draft Publication Flow

```text
User approves selected drafts
  -> ReviewDraftController@publish
  -> PublishReviewDraftService
  -> GitHubClient posts review comments
  -> Repository marks drafts as posted or failed
```

## Scaling Considerations

| Scale | Architecture Adjustments |
|-------|--------------------------|
| Personal/local | SQLite + database queue is enough |
| Small team | Add auth, GitHub App installation, Redis queue, stronger audit log |
| SaaS/team | Multi-tenant data model, per-installation tokens, worker monitoring, rate/cost controls |

## Anti-Patterns

### Fat Controller Review Flow

**What people do:** Fetch GitHub data, call AI, save findings, and post comments inside a controller.

**Why it's wrong:** Hard to test, likely to time out, violates the project architecture decision.

**Do this instead:** Controller -> service -> queued job -> services/repositories.

### Repository as Business Logic Dump

**What people do:** Put review status transitions and AI orchestration inside repositories.

**Why it's wrong:** Repositories should own database access, not product workflows.

**Do this instead:** Services own transitions; repositories save/query state.

### Prompt-Only Data Model

**What people do:** Store only raw prompts and raw AI text.

**Why it's wrong:** UI cannot reliably group findings, publish comments, or diagnose errors.

**Do this instead:** Store structured review runs, findings, and comment drafts.

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| GitHub REST API | HTTP client wrapper | Use read endpoint for files and write endpoint for comments |
| OpenAI | Provider implementation | Use structured outputs when available |
| Anthropic | Future provider implementation | Tool schemas can support structured provider behavior |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| Controller -> Service | Method calls with request DTO/validated data | Keep controllers thin |
| Service -> Repository | Repository methods | Keep Eloquent queries out of services |
| Job -> Service | Container-injected service | Job should orchestrate queue boundary only |
| Service -> Provider | Interface call | Enables fake providers in tests |

## Sources

- Laravel queues: https://laravel.com/docs/13.x/queues
- Laravel HTTP client testing: https://laravel.com/docs/13.x/http-client#testing
- Laravel database testing: https://laravel.com/docs/13.x/database-testing
- GitHub REST pull request files: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28#list-pull-requests-files
- GitHub REST review comments: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28#create-a-review-comment-for-a-pull-request
- OpenAI Structured Outputs: https://developers.openai.com/api/docs/guides/structured-outputs
- Anthropic tool definitions: https://platform.claude.com/docs/en/agents-and-tools/tool-use/define-tools

---
*Architecture research for: AI-assisted GitHub pull request review tool*
*Researched: 2026-06-26*
