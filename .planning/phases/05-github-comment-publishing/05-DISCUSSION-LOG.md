# Phase 05: GitHub Comment Publishing - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-29T00:32:24+08:00
**Phase:** 05-GitHub Comment Publishing
**Areas discussed:** Publish trigger, GitHub comment type, posted draft state, failure and retry, UI presentation

---

## Publish Trigger

| Option | Description | Selected |
|--------|-------------|----------|
| Publish all approved drafts | One-click publish of every `approved` draft; simplest MVP flow. | ✓ |
| Select approved drafts | User can choose a subset of approved drafts; more control but more UI/test complexity. | |
| Support both | Maximum flexibility but larger Phase 05 scope. | |

**User's choice:** Publish all approved drafts.
**Notes:** The user prefers the MVP publish model to stay simple and stable.

| Option | Description | Selected |
|--------|-------------|----------|
| Retry all failed drafts | Separate one-click retry for every `failed` draft. | ✓ |
| Publish approved and failed together | Next publish action also retries failed drafts; fewer buttons but mixed semantics. | |
| Return failed to approved first | Failed drafts must be moved back to approved before publishing. | |

**User's choice:** Retry all failed drafts.
**Notes:** Phase 05 should expose separate `Publish Approved` and `Retry Failed` actions.

---

## GitHub Comment Type

| Option | Description | Selected |
|--------|-------------|----------|
| Line comment first, fallback to PR comment | Prefer line-level PR review comments; if targeting is unavailable, publish a general PR comment. | ✓ |
| Line comments only | Drafts without valid line targets fail. | |
| General PR comments only | Simplest implementation but loses line-level review value. | |

**User's choice:** Line comment first, fallback to PR comment.
**Notes:** Publication should not get blocked solely because line targeting metadata is incomplete.

| Option | Description | Selected |
|--------|-------------|----------|
| One fallback PR comment per draft | Each fallback draft publishes separately and has its own result. | ✓ |
| Merge fallback drafts into one PR comment | Less noisy but harder to map one draft to one publication result. | |
| Skip fallback drafts | Mark fallback drafts failed instead of publishing. | |

**User's choice:** One fallback PR comment per draft.
**Notes:** One draft should map to one GitHub comment result for tracking and retry.

---

## Posted Draft State

| Option | Description | Selected |
|--------|-------------|----------|
| Lock posted drafts | Posted drafts cannot be edited or unapproved locally. | ✓ |
| Allow local edits only | Posted draft text can change locally but will not update GitHub. | |
| Edit and update GitHub | Editing posted drafts updates the GitHub comment too. | |

**User's choice:** Lock posted drafts.
**Notes:** GitHub comment updates are not part of Phase 05.

| Option | Description | Selected |
|--------|-------------|----------|
| Store id, URL, and posted timestamp | Store GitHub comment id, HTML URL, and `posted_at`. | ✓ |
| Store posted timestamp only | Minimal data, but weak traceability. | |
| Store full GitHub response JSON | Most information, but unnecessary and riskier. | |

**User's choice:** Store GitHub comment id, HTML URL, and `posted_at`.
**Notes:** Do not store full response JSON.

---

## Failure and Retry

| Option | Description | Selected |
|--------|-------------|----------|
| Mark failed with safe error | Draft becomes `failed`, keeps safe error message, and can be retried. | ✓ |
| Return to approved | Failure moves the draft back to approved for the next publish. | |
| Roll back the batch | Any failure rolls the whole batch back to approved. | |

**User's choice:** Mark failed with safe error.
**Notes:** Already-posted GitHub comments should not be rolled back locally.

| Option | Description | Selected |
|--------|-------------|----------|
| Categorized safe messages | Show only safe categories such as rate limit, auth rejected, invalid target, unavailable, unexpected response. | ✓ |
| Status code plus safe message | Adds HTTP status code for debugging. | |
| Full response body | Shows raw GitHub response body. | |

**User's choice:** Categorized safe messages.
**Notes:** Do not show or persist raw response, headers, tokens, or secret-bearing payloads.

---

## UI Presentation

| Option | Description | Selected |
|--------|-------------|----------|
| Controls inside Comment Drafts section | Publish/retry controls live beside draft status and actions. | ✓ |
| Separate Publish panel | More prominent, but adds another detail page section. | |
| Top-level Status/Pull Request area | Easier to see but far from draft row state. | |

**User's choice:** Controls inside Comment Drafts section.
**Notes:** Keep publish controls close to draft rows.

| Option | Description | Selected |
|--------|-------------|----------|
| Per-row status details | Each draft row shows status, GitHub link, or safe error message. | ✓ |
| Summary counts only | Cleaner UI but harder to trace individual failures. | |
| Summary plus row details | Most information, but larger UI scope. | |

**User's choice:** Per-row status details.
**Notes:** Posted rows should show GitHub links; failed rows should show safe errors.

---

## the agent's Discretion

- Exact class, route, method, DTO, and migration names can be decided during planning.
- Planner may decide whether publication runs synchronously or through a queued job for the MVP.
- Planner may decide line-target eligibility rules as long as insufficient targets fall back to PR comments.

## Deferred Ideas

- Selecting a subset of approved drafts for publication.
- Updating already-posted GitHub comments.
- Merging fallback drafts into one PR comment.
- Webhook automation, auth/team permissions, and non-GitHub providers.
