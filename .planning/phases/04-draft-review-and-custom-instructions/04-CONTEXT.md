# Phase 4: Draft Review and Custom Instructions - Context

**Gathered:** 2026-06-28T14:14:27+08:00
**Status:** Ready for planning

<domain>
## Phase Boundary

Phase 4 turns completed AI review findings into manually generated, editable comment draft records and lets the user manage a single set of global custom review instructions. The user can view structured findings and comment drafts separately on the review run detail page, edit draft text while the draft is still in draft state, approve drafts without posting them to GitHub, and cancel approval to return a draft to editable state. Saved custom instructions should be included in future AI review execution requests, including future retries.

This phase does not post comments to GitHub, create GitHub draft reviews, automate webhooks, introduce authentication, support team rule sets, or build multiple instruction profiles. GitHub publication remains Phase 5.

</domain>

<decisions>
## Implementation Decisions

### Draft Creation
- **D-01:** Comment draft creation is manual in Phase 4. The app should provide an explicit action on the review run detail page to generate drafts from persisted findings.
- **D-02:** Manual draft generation should only create drafts for findings that do not already have a draft. This avoids duplicate draft records when the action is clicked more than once.
- **D-03:** Drafts are created from each finding's existing `suggested_comment_text`, not by calling the AI provider again.
- **D-04:** Draft records should keep their source finding relationship so the UI and future publishing workflow can trace a draft back to the finding that produced it.

### Draft Status and Approval
- **D-05:** Drafts should track publication workflow status with at least `draft`, `approved`, `posted`, and `failed` states.
- **D-06:** Users can edit draft text only while a draft is in `draft` state.
- **D-07:** Users can approve one or more drafts, but approval must not post anything to GitHub in Phase 4.
- **D-08:** Approved drafts cannot be edited directly. The user may cancel approval to return the draft to `draft` state, then edit it.
- **D-09:** `posted` and `failed` states are included now so the schema is ready for Phase 5, but Phase 4 should not transition drafts into `posted` through GitHub publication.

### Detail Page Presentation
- **D-10:** The review run detail page should show Structured Findings and Comment Drafts as separate sections.
- **D-11:** The findings section remains a read-only view of AI output.
- **D-12:** The drafts section is the operational area for generating drafts, editing draft text, approving drafts, and cancelling approval.

### Custom Instructions
- **D-13:** Phase 4 should use a single global custom instructions textarea, not multiple rule sets or named profiles.
- **D-14:** Custom instructions must be stored separately from generated findings and drafts.
- **D-15:** Updating custom instructions only changes future AI review requests. Existing findings and existing drafts should not be rewritten when the instructions are saved.
- **D-16:** If a review run is retried after custom instructions are updated, the retry should use the latest saved custom instructions.
- **D-17:** `ReviewInstructionBuilder` should combine the existing default instructions with saved custom instructions in a deterministic way.

### Retry and Stale Drafts
- **D-18:** Retry behavior should preserve existing comment drafts instead of deleting them.
- **D-19:** When a review run is retried and fresh findings replace the previous findings, existing drafts should be marked stale because they may no longer match the latest AI output.
- **D-20:** The UI should make stale drafts visible enough that the user can avoid approving outdated text by accident.
- **D-21:** New draft generation after retry should still only create drafts for current findings that do not already have drafts.

### Targeting Metadata
- **D-22:** Drafts should retain the GitHub targeting metadata currently available from findings, especially file path and line reference when present.
- **D-23:** Exact GitHub review-comment publication fields may be finalized in Phase 5, but Phase 4 should avoid losing metadata needed for line-level publication.

### the agent's Discretion
- Planner may choose exact class, enum, migration, route, and service method names as long as Controller / Service / Repository layering is preserved.
- Planner may decide whether draft generation lives in a dedicated `ReviewDraftService` or a broader review workflow service, but business rules must stay in the service layer and database writes in repositories.
- Planner may choose the storage shape for the single global custom instructions record, provided it is separate from findings and drafts and is easy to extend later.
- Planner may decide whether stale tracking is a boolean column, timestamp, or status-adjacent metadata, provided the UI can clearly show stale draft state.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Project and Requirements
- `.planning/PROJECT.md` — Product purpose, manual approval safety, queueing requirement, AI provider abstraction, and Controller / Service / Repository architecture.
- `.planning/REQUIREMENTS.md` — Phase 4 requirements: `DRAFT-01` through `DRAFT-07` and `RULE-01` through `RULE-04`.
- `.planning/ROADMAP.md` — Phase 4 goal, dependencies, success criteria, and Phase 5 boundary for GitHub publication.
- `.planning/STATE.md` — Current project position and completed Phase 3 transition.

### Prior Phase Context
- `.planning/phases/03-queued-ai-review-and-structured-findings/03-CONTEXT.md` — Phase 3 decisions that findings include `suggested_comment_text`, drafts were deferred to Phase 4, retry replaces findings, and GitHub posting is out of scope.
- `.planning/phases/03-queued-ai-review-and-structured-findings/03-VERIFICATION.md` — Verified Phase 3 behavior for queued AI review, persisted findings, safe failures, and retry.
- `.planning/phases/02-github-pr-ingestion/02-CONTEXT.md` — GitHub snapshot data shape: PR metadata and raw file snapshots with `filename`, `patch`, and `sha`.

### Current Code
- `app/Http/Controllers/ReviewController.php` — Current review-run HTTP actions for index, create, fetch, show, and run AI review.
- `app/Models/ReviewRun.php` — Existing review run relationships for files and findings.
- `app/Models/ReviewFinding.php` — Existing persisted finding shape, including `suggested_comment_text`.
- `app/Repositories/ReviewRunRepository.php` — Current review run loading and status transition repository.
- `app/Repositories/ReviewFindingRepository.php` — Current finding replacement behavior on AI review success.
- `app/Services/ReviewExecutionService.php` — Current queued AI execution workflow and retry behavior.
- `app/Services/AI/ReviewInstructionBuilder.php` — Current default instruction builder to extend with saved custom instructions.
- `resources/views/reviews/show.blade.php` — Current detail page where findings and draft workflow should be presented.
- `routes/web.php` — Current route surface for review-run workflow actions.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `ReviewFinding` model: Provides persisted AI review findings with severity, category, file path, line reference, title, rationale, and suggested comment text.
- `ReviewRun` model: Already has `files`, `findings`, and PR repository relationships; it can gain a drafts relationship in Phase 4.
- `ReviewFindingRepository::replaceForReviewRun()`: Current retry path deletes/replaces findings, which means draft stale handling must happen before or alongside this replacement.
- `ReviewExecutionService`: Central place where successful AI review execution can mark existing drafts stale before or after replacing findings.
- `ReviewInstructionBuilder`: Existing default instruction builder should be extended to include saved custom instructions.
- `resources/views/reviews/show.blade.php`: Existing detail page already has status, fetch, run AI review, GitHub snapshot, files, and structured findings sections.

### Established Patterns
- Controllers handle HTTP concerns and redirect/session messaging only.
- Services own business workflow orchestration and user-visible rules.
- Repositories own Eloquent reads, writes, relationship persistence, and status transitions.
- External provider behavior remains behind interfaces and is fakeable in tests.
- Safe failure and retry behavior should not leak secrets or raw provider payloads.
- Tests should fake external AI and GitHub behavior; PHP/artisan/composer commands run inside the Docker workspace container in this environment.

### Integration Points
- Add draft generation, edit, approve, and cancel-approval routes near the existing review routes.
- Add a draft repository/model/migration and connect it to `ReviewRun` and `ReviewFinding`.
- Add a custom instructions repository/model/migration or settings-style persistence layer, then inject it into the instruction-building service.
- Update `ReviewExecutionService` so retries preserve existing drafts but mark them stale when findings are refreshed.
- Update the review detail Blade page with a separate Comment Drafts section and a separate custom instructions management area or link.

</code_context>

<specifics>
## Specific Ideas

- The user chose manual draft generation rather than automatic draft creation after AI review completion.
- The user chose separate Structured Findings and Comment Drafts sections on the detail page.
- The user chose a single global custom instructions textarea.
- The user chose preserving old drafts after retry.
- Follow-up decisions: only create drafts for findings without drafts; approved drafts cannot be edited directly but approval can be cancelled; retry should mark existing drafts stale; saved custom instructions apply to future AI review requests and future retries.

</specifics>

<deferred>
## Deferred Ideas

- Posting approved drafts to GitHub belongs to Phase 5.
- Exact GitHub API publication behavior and posted/failed transitions belong to Phase 5.
- Multiple rule sets, named instruction profiles, team workflows, authentication, and webhook automation remain out of scope for the personal-use MVP slice.

</deferred>

---

*Phase: 4-Draft Review and Custom Instructions*
*Context gathered: 2026-06-28T14:14:27+08:00*
