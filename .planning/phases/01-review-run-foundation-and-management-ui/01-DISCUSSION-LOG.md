# Phase 1: Review Run Foundation and Management UI - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-06-26
**Phase:** 1-Review Run Foundation and Management UI
**Areas discussed:** Review run status model, Management interface shape, Data model naming and boundaries, PR URL validation and error presentation

---

## Review Run Status Model

| Option | Description | Selected |
|--------|-------------|----------|
| Simplified | `pending / completed / failed`; minimal Phase 1 statuses, later phases add queue states | |
| Future-ready | `pending / queued / running / completed / failed / cancelled`; Phase 1 uses only what it needs, but schema/enum reserves later states | ✓ |
| Detailed | `draft / pending / queued / fetching / reviewing / completed / failed / cancelled`; more debug detail but heavier for early v1 | |

**User's choice:** Future-ready status set.
**Notes:** Phase 1 should reserve later queue/AI states while primarily using `pending` and structured validation failures.

---

## Management Interface Shape

| Option | Description | Selected |
|--------|-------------|----------|
| Single-page dashboard | `/` contains PR URL form and recent review runs | |
| Three-page structure | `/reviews/new`, `/reviews`, and `/reviews/{id}` are separate views | |
| Hybrid | `/` redirects to `/reviews`; `/reviews` contains form + history; `/reviews/{id}` contains detail shell | ✓ |

**User's choice:** Hybrid route structure.
**Notes:** This keeps URLs clean while keeping the personal MVP workflow compact.

---

## Data Model Naming and Boundaries

| Option | Description | Selected |
|--------|-------------|----------|
| Single ReviewRun | One `review_runs` table contains repository owner/name, PR number, source URL, and status | |
| Three models | Separate `repositories`, `pull_requests`, and `review_runs` models/tables from Phase 1 | ✓ |
| ReviewRun + Repository | Separate repository model, with pull request number stored on review run | |

**User's choice:** Three models.
**Notes:** The user prefers cleaner domain boundaries from Phase 1 even though it is slightly heavier than the simplest MVP data model.

---

## PR URL Validation and Error Presentation

| Option | Description | Selected |
|--------|-------------|----------|
| Simple UI message | Invalid URL shows one friendly message such as "Please enter a valid GitHub PR URL" | |
| Structured error | Service returns error code and user-facing message; UI displays message and tests assert code | ✓ |
| Failed review run | Invalid URLs create failed review runs for full attempt history | |

**User's choice:** Structured error.
**Notes:** Invalid URLs should not create failed review runs. History should stay focused on actual review attempts.

---

## the agent's Discretion

- Exact Blade/CSS componentization is left to planning/execution.
- Exact repository method names are left to planning/execution as long as boundaries remain clear.

## Deferred Ideas

- GitHub ingestion, AI execution, draft review, comment publishing, and webhook automation remain in later phases.
