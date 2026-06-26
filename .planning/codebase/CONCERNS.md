---
last_mapped: 2026-06-26
focus: concerns
---

# Codebase Concerns

## Summary

The repository is a fresh Laravel skeleton, so there are few existing defects. The main concerns are product-readiness gaps: no AI review domain model, no GitHub integration, no webhook security, no API route surface, no auth/product UI, no CI, and only default tests. These are normal for a new project, but they should shape the first GSD phases.

## Technical Debt

- The README is still the default Laravel README at `README.md`.
- The homepage is still the default welcome page at `resources/views/welcome.blade.php`.
- Tests are default examples in `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php`.
- There is no domain-specific application code yet.

## Security Concerns for Planned Work

No application secret leak was found in mapped source files. Future work should handle these areas carefully:

- GitHub webhook signature verification before accepting webhook payloads.
- Replay protection or delivery ID deduplication for GitHub webhooks.
- Safe storage of GitHub installation tokens or OAuth tokens.
- AI API key storage through environment/config only.
- Redaction of tokens, `.env` values, raw headers, and provider responses in logs.
- Avoiding accidental publication of sensitive code snippets in external AI prompts if private repos are reviewed.

## Integration Risks

- No GitHub API client exists yet.
- No AI provider client exists yet.
- No retry/backoff strategy exists for rate limits or temporary provider failures.
- No persistence model exists for review runs, findings, comments, or webhook deliveries.
- No idempotency strategy exists for repeated webhook deliveries.

## Architecture Risks

- If review generation runs inside the webhook request, GitHub deliveries may time out. The existing queue setup should be used.
- If AI response parsing is loosely structured, generated comments may be unreliable. Prefer structured provider output or strict schema validation.
- If raw diffs and generated comments are not normalized early, later rule configuration and history views will become harder.
- If GitHub and AI concerns are mixed into controllers, the codebase will become hard to test.

## Testing Risks

- There is no product-specific test coverage.
- There are no fixtures for GitHub webhook payloads or PR diff responses.
- No CI config was found.
- External API behavior will need fakes from the first implementation phase to avoid brittle tests.

## Operational Risks

- Queue defaults use the database driver, which is acceptable for local MVP work but may need Redis/SQS in production.
- Long-running AI review jobs need timeout, retry, and failure-state design.
- Review jobs need observability beyond logs: status, error reason, provider latency, and GitHub publication result.
- Cost controls are not represented yet; AI review tools should track token usage or at least provider request metadata.

## Laravel Skeleton Notes

- `bootstrap/app.php` has no custom middleware yet.
- `routes/web.php` only exposes `/`.
- There is no `routes/api.php`.
- `app/Http/Controllers/Controller.php` is only the base class.
- `app/Providers/AppServiceProvider.php` has no custom boot logic.

## Recommended First Fixes Through GSD

1. Define the MVP workflow around GitHub PR diff ingestion and an asynchronous review job.
2. Add config entries for GitHub and AI providers in `config/services.php`.
3. Add API/webhook routing with signature verification.
4. Add domain tables for review runs and findings.
5. Add faked feature tests before calling real GitHub or AI APIs.
6. Keep provider calls behind small services so they can be swapped or faked.
