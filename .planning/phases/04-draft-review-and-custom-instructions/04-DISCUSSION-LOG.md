# Phase 4: Draft Review and Custom Instructions - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-28T14:14:27+08:00
**Phase:** 04-Draft Review and Custom Instructions
**Areas discussed:** Draft creation timing, draft status and approval, detail page presentation, custom instructions, retry and stale drafts, targeting metadata

---

## Draft Creation Timing

| Option | Description | Selected |
|--------|-------------|----------|
| Automatic | Generate drafts automatically after a successful AI review. | |
| Manual | Let the user explicitly generate drafts from findings. | ✓ |
| Lazy | Generate drafts when the user opens the detail page. | |

**User's choice:** Manual draft generation.
**Notes:** Draft generation should be a user-triggered action on the review run detail page.

---

## Existing Drafts During Generation

| Option | Description | Selected |
|--------|-------------|----------|
| Missing only | Create drafts only for findings that do not already have drafts. | ✓ |
| Always new | Generate a new batch each time and preserve older drafts. | |
| User choice | Ask the user whether to regenerate. | |

**User's choice:** Missing only.
**Notes:** This prevents duplicate draft records when the action is clicked repeatedly.

---

## Draft Status and Approval

| Option | Description | Selected |
|--------|-------------|----------|
| Editable after approval | Approved drafts can still be edited directly. | |
| Locked after approval | Approved drafts cannot be edited. | ✓ |
| Approval only | Keep approval minimal and defer edit-lock nuance. | |

**User's choice:** Approved drafts cannot be edited.
**Notes:** The user also chose that approval can be cancelled, returning the draft to editable draft state.

---

## Cancel Approval

| Option | Description | Selected |
|--------|-------------|----------|
| Allow cancellation | User can cancel approval and return a draft to draft state. | ✓ |
| No cancellation | Approved drafts stay locked until publication. | |
| Defer | Do not build cancellation in Phase 4. | |

**User's choice:** Allow cancellation.
**Notes:** Editing requires cancelling approval first.

---

## Detail Page Presentation

| Option | Description | Selected |
|--------|-------------|----------|
| Combined | Show findings and drafts together. | |
| Separate | Show Structured Findings and Comment Drafts as separate sections. | ✓ |
| Draft-focused | Show drafts first and findings as secondary context. | |

**User's choice:** Separate sections.
**Notes:** Findings remain read-only; drafts become the operational workflow area.

---

## Custom Instructions Shape

| Option | Description | Selected |
|--------|-------------|----------|
| Single global textarea | Store one global set of custom review instructions. | ✓ |
| Multiple rule sets | Support named instruction profiles/rules. | |
| Config only | Keep instructions in config/env for now. | |

**User's choice:** Single global textarea.
**Notes:** Multiple profiles and team rules are deferred.

---

## Custom Instructions Scope

| Option | Description | Selected |
|--------|-------------|----------|
| Future requests | Saved instructions apply to future AI reviews. | ✓ |
| Rewrite current data | Saving instructions rewrites existing findings or drafts. | |
| Save only | Store settings without wiring into review execution yet. | |

**User's choice:** Future requests.
**Notes:** Future retries should use the latest saved custom instructions. Existing findings and drafts should not be rewritten just because instructions were saved.

---

## Retry and Existing Drafts

| Option | Description | Selected |
|--------|-------------|----------|
| Delete old drafts | Remove existing drafts when retry refreshes findings. | |
| Preserve drafts | Keep existing drafts after retry. | ✓ |
| Ask user | Let the user choose during retry. | |

**User's choice:** Preserve drafts.
**Notes:** Preserved drafts may no longer match the latest findings, so stale tracking is needed.

---

## Stale Draft Tracking

| Option | Description | Selected |
|--------|-------------|----------|
| Mark stale | Mark existing drafts stale when AI review is rerun. | ✓ |
| Preserve silently | Keep existing drafts without marking them. | |
| UI warning only | Show a general warning without per-draft stale state. | |

**User's choice:** Mark stale.
**Notes:** The UI should make stale drafts visible enough to avoid accidental approval.

---

## Targeting Metadata

| Option | Description | Selected |
|--------|-------------|----------|
| Current metadata | Retain file path and line reference currently available from findings. | ✓ |
| Full GitHub publishing fields | Derive all GitHub line comment fields now. | |
| Defer metadata | Keep only draft text and source finding. | |

**User's choice:** Current metadata.
**Notes:** Phase 4 should avoid losing targeting metadata, but exact GitHub publication fields can be completed in Phase 5.

---

## the agent's Discretion

- Exact class, enum, migration, route, and service method names.
- Exact persistence shape for single global custom instructions.
- Exact stale metadata shape, as long as stale drafts are visible in the UI.
- Exact UI layout details within the existing Laravel Blade management interface.

## Deferred Ideas

- Posting approved drafts to GitHub.
- Multiple custom instruction profiles or team rule sets.
- Webhook automation and team workflow management.
