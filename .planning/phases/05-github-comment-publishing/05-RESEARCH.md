# Phase 05 Research: GitHub Comment Publishing

## Goal

Phase 05 publishes approved `ReviewCommentDraft` records to the original GitHub pull request, records per-draft success or safe failure, and supports retrying failed draft publication. Publication must remain explicit and manual: no AI-generated comment is posted unless the user has approved the draft first.

## Existing Code Patterns

### Controller / Service / Repository layering

- `routes/web.php` keeps route definitions thin and controller-backed.
- `app/Http/Controllers/ReviewDraftController.php` handles request validation, redirects, and flash messages for draft generation/edit/approval.
- `app/Services/ReviewDraftService.php` owns draft business rules such as "only draft comments can be edited" and "only approved comments can be returned to draft".
- `app/Repositories/ReviewCommentDraftRepository.php` owns Eloquent queries and status mutations.
- Phase 05 should follow the same shape with a publish-focused controller/service/repository path instead of placing GitHub calls or Eloquent updates in controllers.

### GitHub client boundary

- `app/Contracts/GitHub/GitHubClient.php` currently exposes pull request read operations:
  - `getPullRequest(...)`
  - `listPullRequestFiles(...)`
- `app/Services/GitHub/HttpGitHubClient.php` centralizes the GitHub base URL, API version header, accept header, and optional token from `config('services.github.token')`.
- Publication should extend this interface with fakeable methods rather than using `Http::` directly from a domain service.

Recommended addition:

- `createPullRequestReviewComment(...)` for line-level review comments.
- `createPullRequestIssueComment(...)` or `createIssueComment(...)` for fallback general PR comments, because a PR is also an issue in GitHub's issues comments API.

### Safe GitHub failures

- `app/Services/GitHub/GitHubFailureMapper.php` maps `RequestException`, `ConnectionException`, and malformed payloads to safe `GitHubFailure` values.
- Existing categories include `auth_failed`, `rate_limited`, `not_found_or_unreadable`, `malformed_response`, and `server_unavailable`.
- Phase 05 can reuse this mapper if messages are acceptable for publishing, or add a publication-specific mapper that uses the same categories plus `target_invalid` for line-level comment target failures.
- Do not persist raw GitHub response bodies, request headers, authorization tokens, or provider payloads.

### Draft model and status vocabulary

- `app/Models/ReviewCommentDraft.php` currently stores:
  - `status`
  - `body`
  - `file_path`
  - `line_reference`
  - `github_head_sha`
  - `source_file_sha`
  - `stale_at`
- `app/Enums/ReviewCommentDraftStatus.php` already has `draft`, `approved`, `posted`, and `failed`.
- Existing methods include `isDraft()` and `isApproved()`. Phase 05 should add `isPosted()` / `isFailed()` if views or services need them.
- Phase 05 needs new persisted fields for:
  - `github_comment_id`
  - `github_comment_html_url`
  - `posted_at`
  - `publication_error_message` or equivalent safe error text
  - optionally `publication_error_code` if useful for tests and future UI filtering

### Current draft workflow

- `ReviewDraftService::updateDraftBody()` rejects non-draft statuses, which already locks `approved`, `posted`, and `failed` from editing.
- `ReviewDraftService::unapproveDraft()` only allows approved drafts to return to draft, so posted drafts are already protected if `isApproved()` stays strict.
- Phase 05 should ensure failed drafts are not editable unless that is intentionally changed later; current decisions only require retrying failed drafts.
- `resources/views/reviews/show.blade.php` renders the Comment Drafts section with:
  - Generate Drafts
  - Approve Selected
  - per-draft edit form for draft status
  - Cancel Approval for approved status
- Publish controls should be added to this same section.

## GitHub API Findings

Official GitHub REST docs indicate line-level pull request review comments are created with:

- Endpoint: `POST /repos/{owner}/{repo}/pulls/{pull_number}/comments`
- Required body fields include `body`, `commit_id`, and `path`.
- `line` is the modern line target field; `position` is closing down.
- `side` can be `LEFT` or `RIGHT`; use `RIGHT` for additions or unchanged context and `LEFT` for deletions.
- Responses include `id`, `html_url`, and timestamps.

For fallback general PR comments:

- Endpoint: `POST /repos/{owner}/{repo}/issues/{issue_number}/comments`
- Required body field: `body`.
- Pull requests use the same number as their issue number for this endpoint.
- Responses include `id`, `html_url`, and timestamps.

Sources:

- GitHub REST pull request review comments docs: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28#create-a-review-comment-for-a-pull-request
- GitHub REST issue comments docs: https://docs.github.com/en/rest/issues/comments?apiVersion=2022-11-28#create-an-issue-comment

## Targeting Strategy

Line-level publication should be attempted only when a draft has enough local metadata:

- non-empty `body`
- non-empty `file_path`
- integer-like `line_reference`
- non-empty `github_head_sha`

Suggested request mapping:

- `body` => draft body
- `commit_id` => `draft.github_head_sha`
- `path` => `draft.file_path`
- `line` => parsed `draft.line_reference`
- `side` => `RIGHT` for Phase 05 MVP

If any required line-level target data is missing, publish a general PR comment instead of failing the draft. The fallback body should still be one GitHub comment per draft and can include file/line context in the body so the comment remains understandable outside the diff thread.

If GitHub rejects a line-level target, map it to a safe `target_invalid` failure. The user decision only requires fallback when local target data is insufficient; fallback after GitHub rejects a target is optional and should be planned explicitly if included.

## Recommended Data Objects

Add small DTOs under `app/Data/GitHub/`:

- `GitHubCommentPublicationTarget`
  - owner
  - repository
  - pull request number
  - body
  - optional path
  - optional line
  - optional commit SHA
  - mode/type if useful
- `GitHubCommentPublicationResult`
  - id as string or int
  - htmlUrl
  - postedAt
  - type/mode: `pull_request_review_comment` or `issue_comment`

Keeping the result object small helps enforce "do not store full response JSON".

## Repository Needs

Extend `ReviewCommentDraftRepository` with methods such as:

- `approvedForReviewRun(ReviewRun $reviewRun)`
- `failedForReviewRun(ReviewRun $reviewRun)`
- `markPosted(ReviewCommentDraft $draft, GitHubCommentPublicationResult $result)`
- `markPublicationFailed(ReviewCommentDraft $draft, GitHubFailure $failure)`

The repository should clear old publication error data on success. Retry should overwrite the previous failure message/code.

## Service Workflow

Add a `ReviewCommentPublishingService` or similar:

1. Load the review run with its pull request and repository.
2. Select drafts by mode:
   - publish approved: only `approved`
   - retry failed: only `failed`
3. For each draft, build either a line-level review comment request or fallback issue comment request.
4. Call the GitHub client through the interface.
5. Mark each draft independently:
   - success -> `posted`, store GitHub id/html URL/posted timestamp, clear safe error
   - failure -> `failed`, store safe categorized error
6. Do not roll back already posted drafts if later drafts fail.
7. Return a summary count for UI flash messages.

Because Phase 05 is one-click manual publishing and each draft needs immediate per-row feedback, synchronous HTTP actions are acceptable for MVP unless the planner decides to queue publishing. If queued, tests must still be deterministic and the UI must clearly reflect pending state; no pending state exists yet, so synchronous is the smaller plan.

## UI Implications

In `resources/views/reviews/show.blade.php`:

- Show `Publish Approved` when at least one draft has `approved` status.
- Show `Retry Failed` when at least one draft has `failed` status.
- Keep both controls in the Comment Drafts section.
- For posted drafts:
  - show posted state
  - show GitHub link when `github_comment_html_url` exists
  - do not show edit or unapprove controls
- For failed drafts:
  - show failed state
  - show safe publication error message
  - let the section-level `Retry Failed` action handle retry

## Testing Strategy

Use feature tests with a fake `GitHubClient` binding.

Recommended coverage:

- Approved drafts publish through the GitHub client and become posted.
- Drafts that are not approved are not published by `Publish Approved`.
- Missing line targeting metadata falls back to a general PR comment.
- Successful publication stores id/html URL/posted timestamp and not raw response JSON.
- GitHub failure marks only that draft failed with a safe message.
- Partial success does not roll back earlier posted drafts.
- `Retry Failed` republishes only failed drafts.
- Posted drafts cannot be edited or unapproved.
- Controller tests assert routes redirect back with useful flash summaries.

Fixtures:

- Add GitHub comment response JSON fixtures under `tests/Fixtures/GitHub/`.
- Keep fake publication responses minimal: `id`, `html_url`, `created_at`.

## Risks and Assumptions

- Existing `line_reference` appears to store a logical line reference, not a parsed diff-side target. Using `side: RIGHT` is a practical MVP assumption but may fail for deletion-only findings.
- GitHub may reject comments if the line is not present in the current PR diff or the head SHA changed. These should become safe `target_invalid` failures.
- Public PR reads can work without auth, but comment publishing requires a token with write permission. Missing/rejected tokens should map to `auth_failed`.
- Synchronous publication can be slow if many drafts are approved. The Phase 05 scope is personal-use MVP, so this is acceptable unless draft volume grows.

## RESEARCH COMPLETE
