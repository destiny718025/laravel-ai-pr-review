# Stack Research

**Domain:** AI-assisted GitHub pull request review tool
**Researched:** 2026-06-26
**Confidence:** HIGH

## Recommended Stack

### Core Technologies

| Technology | Version | Purpose | Why Recommended |
|------------|---------|---------|-----------------|
| Laravel | 13.x | Web app, routing, jobs, database, HTTP client | Already installed; provides queues, HTTP client fakes, database testing, Blade/Vite support |
| PHP | ^8.3 | Runtime | Already required by `composer.json`; enough for typed service/repository code |
| SQLite | Current local default | MVP persistence | Matches current Laravel default and keeps personal/local validation simple |
| Laravel database queue | 13.x | Async AI review execution | Already scaffolded; AI/GitHub calls should not block HTTP requests |
| Laravel HTTP client | 13.x | GitHub and AI provider calls | Supports faked responses and stray request prevention in tests |
| Vite + Tailwind CSS | Vite 8, Tailwind 4 | Management UI | Already installed; enough for PR URL input, history, detail, findings, and drafts |

### Supporting Libraries

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `laravel/pint` | ^1.27 | PHP style formatting | Keep Laravel/PHP code consistent |
| `phpunit/phpunit` | ^12.5 | Test runner | Existing test stack |
| `mockery/mockery` | ^1.6 | Test doubles | Mock provider interface or repositories where HTTP fake is not enough |
| `laravel/pail` | ^1.2.5 | Local log inspection | Helpful while debugging queued review jobs |

### External APIs

| API | Purpose | Implementation Notes |
|-----|---------|----------------------|
| GitHub REST API: pull request files | Fetch changed files and patches | Requires Pull requests read permission for private repos; public resources can be read unauthenticated |
| GitHub REST API: pull request review comments | Publish approved draft comments | Requires Pull requests write permission |
| GitHub Webhooks | Later automatic review trigger | Validate `X-Hub-Signature-256` with HMAC-SHA256 before trusting payloads |
| AI provider API | Generate findings and comment drafts | Hide behind `AiReviewProvider` interface and require schema-shaped output |

## AI Provider Recommendation

Use an application-level provider interface first:

- `AiReviewProviderInterface`
- `OpenAiReviewProvider`
- future `AnthropicReviewProvider`
- fake provider for tests

OpenAI is a strong first concrete provider because its official Structured Outputs guidance says schema-based output can enforce schema adherence, while JSON mode only guarantees valid JSON. Anthropic is also viable through tool definitions with `input_schema`, but provider-specific response handling differs enough that the app should not hardcode one provider throughout the domain flow.

## Installation

No required package install is recommended during project initialization. The first implementation phase can use Laravel's built-in HTTP client directly.

Optional future packages:

```bash
# Only if handwritten HTTP wrappers become too noisy
composer require openai-php/client
```

Do not install an SDK until the provider interface and tests show it is worth the dependency.

## Alternatives Considered

| Recommended | Alternative | When to Use Alternative |
|-------------|-------------|-------------------------|
| Laravel HTTP client | Provider SDK | Use SDK if auth/retry/streaming complexity grows |
| Database queue | Redis queue | Use Redis when queue throughput or worker visibility becomes important |
| SQLite | MySQL/PostgreSQL | Use a server DB for hosted/team workflows |
| Blade/Tailwind | SPA frontend | Use SPA only if the review UI becomes highly interactive |
| GitHub REST API | GraphQL API | Use GraphQL if REST requires too many round trips for future dashboard queries |

## What NOT to Use

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| Blocking AI review inside controller request | AI calls are slow and failure-prone | Dispatch a queued review job |
| Direct Eloquent calls scattered through services/controllers | Violates project architecture decision | Repository classes own database access |
| Raw unvalidated AI JSON | Models can produce malformed or schema-drifting data | Structured output plus server-side validation |
| GitHub `position` for new review comments | GitHub says `position` is closing down | Use `line`, `side`, `start_line`, `start_side` |
| Auto-posting every AI finding | Noisy false positives damage trust | Draft first, manual approval before posting |

## Version Compatibility

| Package / API | Compatible With | Notes |
|---------------|-----------------|-------|
| Laravel 13 queue | Current repo migration | `0001_01_01_000002_create_jobs_table.php` already exists |
| Laravel HTTP fake | Current PHPUnit setup | Use `Http::fake()` and `Http::preventStrayRequests()` |
| GitHub review comments API | REST API versioned endpoints | Requires correct commit SHA, path, line, and side for line comments |
| OpenAI Structured Outputs | Modern OpenAI models | Prefer schema-constrained responses over JSON mode |

## Sources

- GitHub REST API pull request files: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28#list-pull-requests-files
- GitHub REST API review comments: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28#create-a-review-comment-for-a-pull-request
- GitHub webhook signature validation: https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries
- Laravel queues: https://laravel.com/docs/13.x/queues
- Laravel HTTP client testing: https://laravel.com/docs/13.x/http-client#testing
- OpenAI Structured Outputs: https://developers.openai.com/api/docs/guides/structured-outputs
- Anthropic tool definitions: https://platform.claude.com/docs/en/agents-and-tools/tool-use/define-tools

---
*Stack research for: AI-assisted GitHub pull request review tool*
*Researched: 2026-06-26*
