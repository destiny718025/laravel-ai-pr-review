# Phase 3: Queued AI Review and Structured Findings - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-27T23:43:12Z
**Phase:** 3-Queued AI Review and Structured Findings
**Areas discussed:** Review trigger, AI provider strategy, structured findings, failure/retry safety

---

## Review Trigger

| Option | Description | Selected |
|--------|-------------|----------|
| Manual `Run AI Review` | User starts queued AI review from the review detail page after GitHub data is fetched. | ✓ |
| Auto-run after GitHub fetch | Successful GitHub `Fetch` immediately enqueues AI review. | |
| Auto-fetch then run | `Run AI Review` also fetches GitHub data if missing. | |

**User's choice:** 手動 Run AI Review.
**Notes:** This keeps Phase 3 explicit and avoids surprising AI calls or queued work during GitHub ingestion.

---

## AI Provider Strategy

| Option | Description | Selected |
|--------|-------------|----------|
| Fake provider first | Build the provider interface and deterministic fake provider before any live AI dependency. | ✓ |
| Live provider now | Add a real provider implementation as part of the first execution slice. | |
| Config-driven adapter first | Prioritize provider selection/config before the fake execution path. | |

**User's choice:** fake provider 優先、OpenAI adapter 預留 config.
**Notes:** Tests should stay deterministic and no-network. OpenAI config should be reserved without making Phase 3 depend on live calls.

---

## Structured Findings

| Option | Description | Selected |
|--------|-------------|----------|
| Findings with suggested comment text | Persist structured findings and include suggested comment text for later draft creation. | ✓ |
| Findings only | Persist findings without suggested comment text; draft wording comes later. | |
| Findings plus draft records | Persist findings and create draft records in the same phase. | |

**User's choice:** findings 含 suggested comment text 但不建立 draft.
**Notes:** Phase 3 prepares review output for Phase 4 while keeping editable comment drafts out of scope.

---

## Failure, Retry, and Safety

| Option | Description | Selected |
|--------|-------------|----------|
| Safe failure and retry | Map provider/validation/runtime failures to safe messages and allow manual retry. | ✓ |
| Fail once only | Failed AI review remains failed without retry in the MVP. | |
| Raw debug detail | Persist provider detail for debugging. | |

**User's choice:** 支援安全失敗與重試.
**Notes:** Persisted/rendered failure messages must not contain API keys, authorization headers, raw provider payloads, or unredacted secrets.

---

## the agent's Discretion

- Planner may decide exact class, migration, repository, service, route, and job names.
- Planner may decide whether a minimal OpenAI adapter stub belongs in Phase 3 or later, as long as config is reserved and fake provider workflow is complete.
- Planner may decide the exact AI output validation mechanism.

## Deferred Ideas

- Comment draft records, editing, approval, and statuses remain Phase 4.
- GitHub comment publication remains Phase 5.
- Live provider integration can wait until the fake provider path is stable.
