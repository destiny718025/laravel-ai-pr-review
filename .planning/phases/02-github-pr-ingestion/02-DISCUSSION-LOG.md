# Phase 2: GitHub PR Ingestion - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-27T04:21:15Z
**Phase:** 2-GitHub PR Ingestion
**Areas discussed:** GitHub token usage, ingestion trigger, diff metadata storage, GitHub failure behavior, test fixture strategy

---

## GitHub Token Usage

| Option | Description | Selected |
|--------|-------------|----------|
| Public-only first | Start with public GitHub PRs; no token required for the first ingestion slice. | ✓ |
| Environment token | Read a token from `.env`/config for private repos without storing it. | |
| Private repo first | Require token/private repository support before ingestion is considered useful. | |

**User's choice:** Public-only first.
**Notes:** Private repository/token setup is deferred. Secrets must still stay out of database records and logs.

---

## Ingestion Trigger

| Option | Description | Selected |
|--------|-------------|----------|
| Manual Fetch | Add an explicit fetch action, likely on the review run detail page. | ✓ |
| Auto-fetch on creation | Fetch GitHub data immediately after a review run is created. | |
| Queue-only trigger | Leave fetching to Phase 3 queued execution. | |

**User's choice:** Manual `Fetch`.
**Notes:** Phase 1 redirects successful submissions to the detail page, so the detail page is the natural first placement.

---

## Diff Metadata Storage

| Option | Description | Selected |
|--------|-------------|----------|
| Store files API basics | Store filename, patch, and sha from GitHub files API. | ✓ |
| Parse hunk/line structures | Normalize patches into comment-targeting line/hunk metadata now. | |
| Store raw API payloads | Persist full GitHub files API responses for maximum flexibility. | |

**User's choice:** Store filename, patch, and sha only.
**Notes:** Deeper hunk/line targeting normalization is deferred until later phases need exact line-level comment placement.

---

## GitHub Failure Behavior

| Option | Description | Selected |
|--------|-------------|----------|
| Distinct failure categories | Use different safe error codes/messages for not found, rate limit, auth, server/network, and malformed responses. | ✓ |
| Generic failure | Mark all GitHub ingestion failures with one generic message. | |
| Retry-first behavior | Add retry/cancel controls and retry states in this phase. | |

**User's choice:** Distinct failure categories.
**Notes:** Failures should mark review runs failed and store only safe summaries. Retry controls remain out of scope.

---

## Test Fixture Strategy

| Option | Description | Selected |
|--------|-------------|----------|
| JSON fixture files | Store fake GitHub API responses as reusable JSON files. | ✓ |
| Inline arrays | Keep fake GitHub responses inside tests for quick iteration. | |
| Real API smoke tests | Call public GitHub during tests. | |

**User's choice:** JSON fixture files.
**Notes:** Fixtures should be shaped close to GitHub API responses and reusable by later AI review tests. Tests must not call the real GitHub API.

---

## the agent's Discretion

- Exact class, table, method, and fixture path names may be chosen during planning.
- Concrete GitHub client implementation details may be chosen during planning as long as workflows depend on a fakeable interface.

## Deferred Ideas

- Private repo/token support.
- Automatic fetch on review run creation.
- Hunk/line-level diff normalization.
- Queue-based review execution.
- Webhook-triggered review runs.
