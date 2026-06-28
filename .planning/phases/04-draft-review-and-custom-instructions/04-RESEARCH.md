# Phase 4: Draft Review and Custom Instructions - Research

**Researched:** 2026-06-28 [VERIFIED: session metadata]
**Domain:** Laravel 13 server-rendered draft workflow, Eloquent persistence, and GitHub review-comment preparation [VERIFIED: codebase grep][CITED: https://laravel.com/docs/13.x/eloquent-relationships][CITED: https://docs.github.com/en/rest/pulls/comments]
**Confidence:** MEDIUM [VERIFIED: gsd-tools classify-confidence]

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
DATA_LK9Q2M7R_START
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
DATA_LK9Q2M7R_END

### the agent's Discretion
DATA_DJ4V8P1S_START
- Planner may choose exact class, enum, migration, route, and service method names as long as Controller / Service / Repository layering is preserved.
- Planner may decide whether draft generation lives in a dedicated `ReviewDraftService` or a broader review workflow service, but business rules must stay in the service layer and database writes in repositories.
- Planner may choose the storage shape for the single global custom instructions record, provided it is separate from findings and drafts and is easy to extend later.
- Planner may decide whether stale tracking is a boolean column, timestamp, or status-adjacent metadata, provided the UI can clearly show stale draft state.
DATA_DJ4V8P1S_END

### Deferred Ideas (OUT OF SCOPE)
DATA_FT6N3X5K_START
- Posting approved drafts to GitHub belongs to Phase 5.
- Exact GitHub API publication behavior and posted/failed transitions belong to Phase 5.
- Multiple rule sets, named instruction profiles, team workflows, authentication, and webhook automation remain out of scope for the personal-use MVP slice.
DATA_FT6N3X5K_END
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| DRAFT-01 | System persists structured review findings for a completed review run. [VERIFIED: .planning/REQUIREMENTS.md] | Current findings persistence already exists; Phase 4 must preserve it while introducing draft-safe retry semantics instead of hard-deleting source rows that drafts still need. [VERIFIED: codebase grep] |
| DRAFT-02 | System creates comment drafts from AI findings instead of posting comments automatically. [VERIFIED: .planning/REQUIREMENTS.md] | Manual generation from persisted `suggested_comment_text` is the locked path; no new AI call or GitHub call is needed. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md] |
| DRAFT-03 | User can view findings and comment drafts on the review run detail page. [VERIFIED: .planning/REQUIREMENTS.md] | Laravel eager loading and SSR Blade sections are the standard fit for a detail page with separate findings and drafts read models. [VERIFIED: codebase grep][CITED: https://laravel.com/docs/13.x/eloquent-relationships] |
| DRAFT-04 | User can edit a comment draft before approving it. [VERIFIED: .planning/REQUIREMENTS.md] | Controller validation plus service-layer state guards should allow edits only in `draft` state. [CITED: https://laravel.com/docs/13.x/validation][VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md] |
| DRAFT-05 | User can approve one or more comment drafts for publication. [VERIFIED: .planning/REQUIREMENTS.md] | Approval is a local state transition only in Phase 4; the business rule belongs in a service, not in Blade or the controller. [VERIFIED: AGENTS.md][VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md] |
| DRAFT-06 | Comment drafts track publication status such as draft, approved, posted, and failed. [VERIFIED: .planning/REQUIREMENTS.md] | A dedicated enum-backed status field keeps state explicit and prepares Phase 5 without introducing publish behavior now. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][ASSUMED] |
| DRAFT-07 | Comment drafts retain GitHub comment targeting metadata needed for line-level publication when available. [VERIFIED: .planning/REQUIREMENTS.md] | GitHub review-comment APIs require `body`, `commit_id`, `path`, and either `position` or `line` with `side`/`start_line`; Phase 4 must not discard the metadata it already has. [CITED: https://docs.github.com/en/rest/pulls/comments][VERIFIED: codebase grep] |
| RULE-01 | User can view current custom review instructions in the management interface. [VERIFIED: .planning/REQUIREMENTS.md] | A dedicated settings record can be loaded into Blade with the review detail or a focused settings surface without touching generated findings/drafts. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][CITED: https://laravel.com/docs/13.x/eloquent] |
| RULE-02 | User can update custom review instructions through a simple textarea. [VERIFIED: .planning/REQUIREMENTS.md] | The standard Laravel pattern is validated form submission with redirect-back errors and flash state. [CITED: https://laravel.com/docs/13.x/validation] |
| RULE-03 | Review execution includes saved custom instructions when generating AI review output. [VERIFIED: .planning/REQUIREMENTS.md] | `ReviewExecutionService` already passes a single `instructions` string into `AIReviewRequest`; planner only needs to swap `buildDefault()` for deterministic composition with stored custom text. [VERIFIED: codebase grep] |
| RULE-04 | System stores custom instructions separately from generated findings and drafts. [VERIFIED: .planning/REQUIREMENTS.md] | A singleton settings table updated through `updateOrCreate` is simpler and safer than embedding mutable instructions on runs/findings. [CITED: https://laravel.com/docs/13.x/eloquent][VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md] |
</phase_requirements>

## Project Constraints (from AGENTS.md)

- Communicate with the user in Chinese. [VERIFIED: AGENTS.md]
- Do not commit changes without user confirmation. [VERIFIED: user prompt]
- Run `php`, `artisan`, and `composer` commands inside the Docker container in this environment. [VERIFIED: user prompt]
- Preserve Controller / Service / Repository layering: controllers own HTTP concerns, services own business rules, repositories own database reads/writes. [VERIFIED: AGENTS.md]
- Keep AI provider access behind an interface and keep secrets in environment/config rather than stored records or logs. [VERIFIED: AGENTS.md]
- External GitHub and AI calls must remain fakeable in tests. [VERIFIED: AGENTS.md]

## Summary

Phase 4 fits the existing Laravel stack without new packages: add persisted draft records plus a singleton custom-instructions record, load them on the review detail page, and keep all state transitions inside service/repository boundaries. `ReviewExecutionService` currently only replaces findings and passes `buildDefault()` into `AIReviewRequest`, so the new phase seam is concentrated around draft persistence, stale handling, and instruction composition. [VERIFIED: codebase grep][CITED: https://laravel.com/docs/13.x/validation][CITED: https://laravel.com/docs/13.x/database]

The main planning risk is not the UI; it is retry semantics. `ReviewFindingRepository::replaceForReviewRun()` currently hard-deletes findings inside a transaction. If Phase 4 adds a draft-to-finding relationship and also preserves drafts across retries, hard deletion will either cascade-delete preserved drafts or leave them pointing at missing source rows. The planner should therefore treat “logical replace” as the default: keep old findings as superseded rows, show only current findings, and mark linked drafts stale on retry. [VERIFIED: codebase grep][VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][CITED: https://laravel.com/docs/13.x/database]

The second planning concern is form ergonomics on a single SSR page. The review detail page will now host at least three mutation flows: generate drafts, edit/approve drafts, and update custom instructions. Laravel supports controller validation with redirect-back behavior and named error bags, so the planner should keep forms separate and use named bags to avoid cross-form validation noise. [VERIFIED: codebase grep][CITED: https://laravel.com/docs/13.x/validation]

**Primary recommendation:** Implement Phase 4 with `review_comment_drafts` plus a singleton `review_instruction_settings` table, and change finding replacement from physical deletion to “superseded/current” semantics so retry can preserve draft traceability. [VERIFIED: codebase grep][VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][ASSUMED]

## Architectural Responsibility Map

| Capability | Primary Tier | Secondary Tier | Rationale |
|------------|-------------|----------------|-----------|
| Render separate findings and drafts sections on the review detail page | Frontend Server (SSR) | Database / Storage | Blade composes the server-rendered page, but it depends on eager-loaded current findings, drafts, and settings data. [VERIFIED: codebase grep][CITED: https://laravel.com/docs/13.x/eloquent-relationships] |
| Generate drafts from persisted findings | API / Backend | Database / Storage | Draft generation is business workflow from existing findings and must stay idempotent and transactional. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][CITED: https://laravel.com/docs/13.x/database] |
| Edit, approve, and cancel approval for drafts | API / Backend | Frontend Server (SSR) | Controllers receive validated forms, but services must enforce allowed state transitions. [VERIFIED: AGENTS.md][CITED: https://laravel.com/docs/13.x/validation] |
| Persist and load global custom instructions | API / Backend | Database / Storage | Instructions are mutable settings data used by future executions and should not live on transient request objects or generated findings. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][CITED: https://laravel.com/docs/13.x/eloquent] |
| Preserve GitHub targeting metadata for future publication | Database / Storage | API / Backend | Phase 4 stores the metadata; Phase 5 will transform it into final GitHub API payloads. [CITED: https://docs.github.com/en/rest/pulls/comments][VERIFIED: codebase grep] |

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `laravel/framework` | `^13.8` in `composer.json`, resolved to `v13.17.0` in `composer.lock`. [VERIFIED: codebase grep] | Provides Blade, routing, validation, Eloquent relationships, and `DB::transaction` primitives needed for Phase 4. [VERIFIED: codebase grep][CITED: https://laravel.com/docs/13.x/validation][CITED: https://laravel.com/docs/13.x/eloquent-relationships][CITED: https://laravel.com/docs/13.x/database] | Phase 4 requirements can be satisfied with first-party Laravel features already in the repo; no package gap was found. [VERIFIED: codebase grep][ASSUMED] |
| Blade + web routes | Built into Laravel 13. [VERIFIED: codebase grep] | Supports SSR sections and HTML forms for draft generation, edit, approval, and global instruction updates. [VERIFIED: codebase grep][CITED: https://laravel.com/docs/13.x/validation] | The product is still a personal-use MVP with no SPA shell, and the existing review detail page is already Blade-based. [VERIFIED: AGENTS.md][VERIFIED: codebase grep] |
| SQLite-backed Eloquent models | SQLite-first default in project config. [VERIFIED: AGENTS.md] | Stores drafts, superseded/current findings, and singleton instruction settings with simple relational queries. [VERIFIED: AGENTS.md][CITED: https://laravel.com/docs/13.x/eloquent] | The project already chose SQLite-first MVP persistence, so Phase 4 should extend that instead of adding another settings store. [VERIFIED: AGENTS.md] |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `phpunit/phpunit` | `^12.5.12` in `composer.json`, resolved to `12.5.30` in `composer.lock`. [VERIFIED: codebase grep] | Feature tests for draft workflow and settings persistence; unit tests for instruction composition and state guards. [VERIFIED: codebase grep] | Use for every Phase 4 requirement because host `php` is absent and the project already depends on Docker-backed Laravel test execution. [VERIFIED: shell probe][VERIFIED: AGENTS.md] |
| `laravel/pint` | `^1.27` in `composer.json`, resolved to `v1.29.3` in `composer.lock`. [VERIFIED: codebase grep] | Formatting for new models, repositories, services, and tests. [VERIFIED: codebase grep] | Run after implementation if the Docker PHP container exposes Composer/Artisan workflow. [VERIFIED: AGENTS.md][ASSUMED] |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Dedicated `review_comment_drafts` records | Reusing `review_findings` as editable comments | Editing, approval state, stale tracking, and future publication results become entangled with immutable AI output. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][ASSUMED] |
| Singleton custom-instructions table | Storing instructions on each `review_run` | Per-run storage violates the locked rule that saving instructions only affects future reviews and retries. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md] |
| Blade forms on the existing detail page | Adding a richer JS or SPA workflow | The MVP already uses Blade, and extra frontend state would add complexity before publication exists. [VERIFIED: AGENTS.md][VERIFIED: codebase grep][ASSUMED] |

**Installation:**
```bash
# No additional Composer or npm packages are recommended for Phase 4.
```

**Version verification:** Phase 4 uses the packages already declared and installed in the repository; no new registry verification is required because no new external package installs are recommended. [VERIFIED: codebase grep][ASSUMED]

## Package Legitimacy Audit

No new external Composer or npm packages are recommended for this phase, so the Package Legitimacy Gate does not need to approve new installs here. [VERIFIED: codebase grep][ASSUMED]

## Architecture Patterns

### System Architecture Diagram

```text
Browser form submit
    |
    v
ReviewController / dedicated draft/settings controllers
    |
    +--> validate request / choose error bag
    |
    v
Draft or instruction service
    |
    +--> repository loads current findings + existing drafts/settings
    |
    +--> business rules:
    |      - create only missing drafts
    |      - edit only draft-state drafts
    |      - approve/cancel approval with state guards
    |      - compose default + saved instructions
    |      - mark preserved drafts stale when retry supersedes findings
    |
    v
Repositories inside DB transaction
    |
    +--> review_findings (current / superseded)
    +--> review_comment_drafts
    +--> review_instruction_settings
    |
    v
Review detail Blade render
```

### Recommended Project Structure
```text
app/
├── Enums/                    # Draft workflow status enum
├── Http/Controllers/         # Review show + focused draft/settings mutation controllers
├── Models/                   # ReviewCommentDraft, ReviewInstructionSetting, ReviewFinding updates
├── Repositories/             # Draft, finding, instruction, and review-run data access
├── Services/                 # Draft generation/state transitions and instruction composition
└── Data/                     # DTOs/value objects if draft transition payloads become repetitive
```

### Pattern 1: Superseded Findings Instead of Hard Delete
**What:** Keep previous findings as superseded rows and load only current findings for the review detail page, so preserved drafts can keep a valid source-finding relationship. [VERIFIED: codebase grep][VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]
**When to use:** Use on every successful retry once drafts can outlive the finding set that created them. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]
**Example:**
```php
// Source: https://laravel.com/docs/13.x/eloquent-relationships
class ReviewRun extends Model
{
    public function currentFindings(): HasMany
    {
        return $this->hasMany(ReviewFinding::class)->whereNull('superseded_at');
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(ReviewCommentDraft::class);
    }
}
```

### Pattern 2: Transactional Draft Generation and Retry Handling
**What:** Group draft generation, stale marking, and current-finding replacement in transactions so state cannot partially update. [CITED: https://laravel.com/docs/13.x/database]
**When to use:** Use for batch draft creation and for successful retries that insert new findings. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]
**Example:**
```php
// Source: https://laravel.com/docs/13.x/database
DB::transaction(function () use ($reviewRun, $validatedFindings): void {
    $this->drafts->markStaleForReviewRun($reviewRun);
    $this->findings->supersedeCurrentForReviewRun($reviewRun);
    $this->findings->storeCurrentForReviewRun($reviewRun, $validatedFindings);
});
```

### Pattern 3: Named Error Bags for Multi-Form Detail Pages
**What:** Keep draft-edit validation and instruction-edit validation isolated so one failed form does not pollute the other's messages. [CITED: https://laravel.com/docs/13.x/validation]
**When to use:** Use on the review detail page if it hosts more than one update form. [VERIFIED: codebase grep]
**Example:**
```php
// Source: https://laravel.com/docs/13.x/validation
$validated = $request->validateWithBag('instructions', [
    'instructions' => ['nullable', 'string', 'max:20000'],
]);
```

### Pattern 4: Singleton Settings Row via `updateOrCreate`
**What:** Persist the single global custom-instructions record with one stable lookup key rather than attaching mutable text to each review run. [CITED: https://laravel.com/docs/13.x/eloquent][VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]
**When to use:** Use for the one global textarea in Phase 4. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]
**Example:**
```php
// Source: https://laravel.com/docs/13.x/eloquent
$setting = ReviewInstructionSetting::query()->updateOrCreate(
    ['scope' => 'global'],
    ['instructions' => $instructions],
);
```

### Anti-Patterns to Avoid
- **Hard-deleting findings on retry after drafts exist:** This conflicts with preserved drafts plus source-finding traceability. [VERIFIED: codebase grep][VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]
- **Re-calling the AI provider to make drafts:** Locked decisions require generating drafts from persisted `suggested_comment_text`. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]
- **Putting approval/edit rules in Blade conditionals only:** The server must reject invalid status transitions even if the UI hides buttons. [VERIFIED: AGENTS.md][ASSUMED]
- **Saving custom instructions on `review_runs` or `review_findings`:** This breaks RULE-04 and makes historical data ambiguous. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Multi-record consistency | Ad hoc manual save ordering | `DB::transaction` around repository writes | Retry, draft generation, and stale marking are coupled writes that should commit atomically. [CITED: https://laravel.com/docs/13.x/database] |
| Form validation on one SSR page | Custom session/error plumbing | Laravel request validation and named error bags | Laravel already handles redirect-back errors cleanly for HTML forms. [CITED: https://laravel.com/docs/13.x/validation] |
| Global instructions storage | Flat files, env vars, or JSON blobs on runs | Eloquent singleton settings row | The instructions are user-editable application data and must be separate from generated artifacts. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][CITED: https://laravel.com/docs/13.x/eloquent] |
| Draft source traceability | Free-text-only drafts with no linkage | Draft row linked to a source finding plus copied targeting fields | Phase 5 will need both readable draft text and publication targeting context. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][CITED: https://docs.github.com/en/rest/pulls/comments] |

**Key insight:** The dangerous “hand-rolled” part of this phase is not HTML forms; it is inventing a retry model that silently breaks draft provenance. Solve provenance first, then the rest of the phase becomes routine Laravel CRUD. [VERIFIED: codebase grep][ASSUMED]

## Common Pitfalls

### Pitfall 1: Retry Deletes the Very Findings Drafts Need
**What goes wrong:** Preserved drafts disappear or point at missing source findings after a successful retry. [VERIFIED: codebase grep]
**Why it happens:** The current repository implementation deletes all findings for a review run before recreating them. [VERIFIED: codebase grep]
**How to avoid:** Replace hard deletion with supersede/current semantics, or at minimum redesign provenance before adding a foreign key from drafts to findings. [VERIFIED: codebase grep][ASSUMED]
**Warning signs:** Cascade-delete proposals on `source_review_finding_id`, or planner text that still says “replace findings” without defining “replace.” [VERIFIED: codebase grep]

### Pitfall 2: Duplicate Drafts from Repeated Generate Clicks
**What goes wrong:** Clicking “Generate Drafts” twice creates multiple drafts for the same current finding. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]
**Why it happens:** Generation logic inserts unconditionally instead of checking for an existing current draft per finding. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][ASSUMED]
**How to avoid:** Enforce idempotent generation in the service and back it with a unique constraint or repository existence check for the current source finding. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][ASSUMED]
**Warning signs:** The planner does not mention deduplication or database uniqueness anywhere. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]

### Pitfall 3: Cross-Form Validation Noise on the Detail Page
**What goes wrong:** Saving custom instructions shows draft-edit errors, or a failed draft edit loses unrelated form state. [CITED: https://laravel.com/docs/13.x/validation][ASSUMED]
**Why it happens:** Multiple POST forms on one page share the default error bag. [CITED: https://laravel.com/docs/13.x/validation]
**How to avoid:** Use named error bags or dedicated form requests per workflow. [CITED: https://laravel.com/docs/13.x/validation]
**Warning signs:** A single generic `$errors` display block is used for every form on the page. [VERIFIED: codebase grep][ASSUMED]

### Pitfall 4: Approving Stale or Non-Draft Records
**What goes wrong:** Users can approve drafts that were invalidated by retry, or edit approved drafts without cancelling approval first. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]
**Why it happens:** State rules are only enforced in the UI, not in the service layer. [VERIFIED: AGENTS.md][ASSUMED]
**How to avoid:** Centralize state guards in a draft service and cover them with feature tests. [VERIFIED: AGENTS.md][ASSUMED]
**Warning signs:** Approval/edit endpoints perform direct repository writes with no state checks. [VERIFIED: AGENTS.md][ASSUMED]

## Code Examples

Verified patterns from official sources:

### Eager Load the Review Detail Aggregates
```php
// Source: https://laravel.com/docs/13.x/eloquent-relationships
return ReviewRun::query()
    ->with([
        'pullRequest.repository',
        'currentFindings',
        'drafts.sourceFinding',
    ])
    ->findOrFail($id);
```

### Wrap Retry-Side Persistence in One Transaction
```php
// Source: https://laravel.com/docs/13.x/database
DB::transaction(function () use ($reviewRun, $validatedFindings): void {
    $this->drafts->markStaleForReviewRun($reviewRun);
    $this->findings->supersedeCurrentForReviewRun($reviewRun);
    $this->findings->storeCurrentForReviewRun($reviewRun, $validatedFindings);
    $this->reviewRuns->markCompleted($reviewRun);
});
```

### Keep Multi-Form Validation Isolated
```php
// Source: https://laravel.com/docs/13.x/validation
$validated = $request->validateWithBag('draft-'.$draft->id, [
    'body' => ['required', 'string', 'max:10000'],
]);
```

### Persist One Global Instructions Record
```php
// Source: https://laravel.com/docs/13.x/eloquent
ReviewInstructionSetting::query()->updateOrCreate(
    ['scope' => 'global'],
    ['instructions' => $validated['instructions'] ?? null],
);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| AI findings only, no editable draft records | Separate read-only findings plus editable draft records with approval state | Phase 4 roadmap scope. [VERIFIED: .planning/ROADMAP.md] | Keeps AI output immutable while allowing human-reviewed comment text. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md] |
| Physical replacement of findings on retry | Logical replacement with superseded/current findings is the safer Phase 4 planning target | Needs to change in Phase 4 because drafts must survive retry. [VERIFIED: codebase grep][ASSUMED] | Preserves draft provenance and stale visibility. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][ASSUMED] |
| No persisted instruction customization | One global persisted custom-instructions record applied to future AI requests | Phase 4 roadmap scope. [VERIFIED: .planning/ROADMAP.md] | Keeps old findings/drafts stable while future runs use the latest review guidance. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md] |

**Deprecated/outdated:**
- Treating `ReviewInstructionBuilder::buildDefault()` as the only instruction source is outdated for Phase 4 because saved custom instructions must be composed into future AI requests. [VERIFIED: codebase grep][VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md]

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|---------------|
| A1 | Superseding old findings is an acceptable interpretation of “fresh findings replace the previous findings,” even though Phase 3 currently hard-deletes them. [ASSUMED] | Summary; Architecture Patterns; State of the Art | Planner may need a different provenance design if stakeholders require literal deletion. |
| A2 | A unique constraint or equivalent idempotency guard can be defined against the current source finding so manual draft generation stays duplicate-safe. [ASSUMED] | Common Pitfalls | Planner may under-spec database constraints and rely only on controller behavior. |
| A3 | Phase 5 can derive any missing GitHub `side`/position details later from the preserved snapshot data plus draft source linkage, so Phase 4 only needs to preserve currently available metadata. [ASSUMED] | Phase Requirements; Don't Hand-Roll | Publication may later require an additional migration if retained metadata is insufficient. |

## Open Questions (RESOLVED)

1. **Canonical Docker test command**
   - Resolved command: `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test`
   - Planning consequence: all Phase 4 verification commands should use the same `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 ...` prefix.

2. **Stale-state encoding**
   - Resolved shape: use nullable `stale_at` on `review_comment_drafts`.
   - Planning consequence: retry logic sets `stale_at` when preserved drafts are invalidated by fresh findings, and the detail page can display when staleness was introduced.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Docker CLI | Running required PHP/Composer/Artisan commands in this environment | ✓ [VERIFIED: shell probe] | `29.4.0` [VERIFIED: shell probe] | — |
| Host `php` CLI | Direct local test execution | ✗ [VERIFIED: shell probe] | — | Use the Docker container. [VERIFIED: user prompt] |
| Host Composer | Direct local dependency/test scripts | ✗ [VERIFIED: shell probe] | — | Use the Docker container. [VERIFIED: user prompt] |
| Node.js | Frontend asset or helper scripts if touched during Phase 4 | ✓ [VERIFIED: shell probe] | `v24.1.0` [VERIFIED: shell probe] | — |
| npm | Frontend asset workflow if touched during Phase 4 | ✓ [VERIFIED: shell probe] | `11.3.0` [VERIFIED: shell probe] | — |

**Missing dependencies with no fallback:**
- None identified for planning.

**Missing dependencies with fallback:**
- Host `php` and host Composer are absent; Docker is the required fallback. [VERIFIED: shell probe][VERIFIED: user prompt]

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit `12.5.30` via Laravel test runner. [VERIFIED: codebase grep] |
| Config file | `phpunit.xml`. [VERIFIED: codebase grep] |
| Quick run command | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter=ReviewDraft` [VERIFIED: user prompt] |
| Full suite command | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test` [VERIFIED: user prompt] |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| DRAFT-01 | Completed runs still render current structured findings after provenance-safe Phase 4 changes. [VERIFIED: .planning/REQUIREMENTS.md] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPersistenceFoundationTest|QueuedReviewExecutionTest'` [VERIFIED: user prompt] | ❌ Wave 0 |
| DRAFT-02 | Manual generation creates drafts from `suggested_comment_text` and is idempotent. [VERIFIED: .planning/REQUIREMENTS.md] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPresentationTest|ReviewDraftGenerationTest|ReviewDraftMetadataTest'` [VERIFIED: user prompt] | ❌ Wave 0 |
| DRAFT-03 | Detail page shows separate findings and drafts sections. [VERIFIED: .planning/REQUIREMENTS.md] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPresentationTest|ReviewDraftGenerationTest|ReviewDraftMetadataTest'` [VERIFIED: user prompt] | ❌ Wave 0 |
| DRAFT-04 | Draft text edits only succeed while status is `draft`. [VERIFIED: .planning/REQUIREMENTS.md] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftWorkflowTest|QueuedReviewExecutionTest'` [VERIFIED: user prompt] | ❌ Wave 0 |
| DRAFT-05 | One or more drafts can be approved without publishing. [VERIFIED: .planning/REQUIREMENTS.md] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftWorkflowTest|QueuedReviewExecutionTest'` [VERIFIED: user prompt] | ❌ Wave 0 |
| DRAFT-06 | Draft states and retry-stale behavior are enforced server-side. [VERIFIED: .planning/REQUIREMENTS.md] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftWorkflowTest|QueuedReviewExecutionTest|QueuedReviewFailureTest'` [VERIFIED: user prompt] | ❌ Wave 0 |
| DRAFT-07 | Drafts retain file/line metadata and source linkage needed for later publication. [VERIFIED: .planning/REQUIREMENTS.md] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewDraftPresentationTest|ReviewDraftGenerationTest|ReviewDraftMetadataTest'` [VERIFIED: user prompt] | ❌ Wave 0 |
| RULE-01 | Current custom instructions render in the management UI. [VERIFIED: .planning/REQUIREMENTS.md] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='CustomReviewInstructionsTest|CustomReviewInstructionsPersistenceTest'` [VERIFIED: user prompt] | ❌ Wave 0 |
| RULE-02 | Custom instructions update through a textarea with validation. [VERIFIED: .planning/REQUIREMENTS.md] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='CustomReviewInstructionsTest|CustomReviewInstructionsPersistenceTest'` [VERIFIED: user prompt] | ❌ Wave 0 |
| RULE-03 | Future AI execution requests include saved custom instructions. [VERIFIED: .planning/REQUIREMENTS.md] | unit + feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='ReviewInstructionBuilderTest|QueuedReviewExecutionTest'` [VERIFIED: user prompt] | ❌ Wave 0 |
| RULE-04 | Custom instructions persist separately from findings and drafts. [VERIFIED: .planning/REQUIREMENTS.md] | feature | `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test --filter='CustomReviewInstructionsTest|CustomReviewInstructionsPersistenceTest'` [VERIFIED: user prompt] | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** run the task-specific Docker `php artisan test --filter=...` command from the active plan. [VERIFIED: user prompt]
- **Per wave merge:** `docker exec -w /var/www/laravel-ai-pr-review laradock-workspace-85-1 php artisan test` [VERIFIED: user prompt]
- **Phase gate:** Full suite green before `$gsd-verify-work`. [VERIFIED: .planning/config.json]

### Wave 0 Gaps
- [ ] `tests/Feature/ReviewDraftPersistenceFoundationTest.php` — provenance-safe draft and finding persistence foundation. [ASSUMED]
- [ ] `tests/Feature/ReviewDraftPresentationTest.php` — findings + drafts detail rendering and generate action. [ASSUMED]
- [ ] `tests/Feature/ReviewDraftWorkflowTest.php` — edit/approve/cancel-approval rules and stale guards. [ASSUMED]
- [ ] `tests/Feature/CustomReviewInstructionsTest.php` — view/update/settings persistence and future execution usage. [ASSUMED]
- [ ] `tests/Unit/AI/ReviewInstructionBuilderTest.php` — deterministic default + custom instruction composition. [ASSUMED]

## Security Domain

### Applicable ASVS Categories

| ASVS Category | Applies | Standard Control |
|---------------|---------|-----------------|
| V2 Authentication | no [VERIFIED: AGENTS.md] | The v1 management UI is intentionally no-login and local/personal-use. [VERIFIED: AGENTS.md] |
| V3 Session Management | no [VERIFIED: AGENTS.md] | No auth/session feature work is introduced in this phase beyond normal Laravel flash/error flow. [VERIFIED: AGENTS.md][ASSUMED] |
| V4 Access Control | no [VERIFIED: AGENTS.md] | There is no user-role system yet, but draft approval/edit transitions still need service-level guards. [VERIFIED: AGENTS.md][ASSUMED] |
| V5 Input Validation | yes [VERIFIED: .planning/config.json] | Use Laravel request validation and named error bags for draft text and instructions textarea inputs. [CITED: https://laravel.com/docs/13.x/validation] |
| V6 Cryptography | no [VERIFIED: AGENTS.md] | No phase-specific crypto should be introduced; secrets remain in environment/config only. [VERIFIED: AGENTS.md] |

### Known Threat Patterns for Laravel draft workflow

| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Mass assignment of draft/settings input into broader model columns | Tampering | Keep fillable lists narrow and write through repositories/services instead of `request()->all()`. [CITED: https://laravel.com/docs/13.x/eloquent] |
| Stale draft approval after retry | Tampering | Persist stale metadata, surface it clearly in the UI, and block approval in the service if policy requires it. [VERIFIED: .planning/phases/04-draft-review-and-custom-instructions/04-CONTEXT.md][ASSUMED] |
| Secret leakage through failure summaries or instruction persistence | Information Disclosure | Reuse safe error mapping patterns and never store raw provider payloads or secrets on findings/drafts/settings. [VERIFIED: AGENTS.md][VERIFIED: codebase grep] |
| CSRF on state-changing draft/settings forms | Spoofing / Tampering | Keep POST/PUT/PATCH forms behind Laravel web middleware and include `@csrf` on every mutation form. [VERIFIED: codebase grep][ASSUMED] |

## Sources

### Primary (HIGH confidence)
- None in this session; `context7` was not callable in the available tool set. [VERIFIED: tool discovery]

### Secondary (MEDIUM confidence)
- https://laravel.com/docs/13.x/eloquent-relationships — one-to-many relationships, eager loading, and related-model creation. [CITED: https://laravel.com/docs/13.x/eloquent-relationships]
- https://laravel.com/docs/13.x/eloquent — `updateOrCreate` and mass-assignment guidance. [CITED: https://laravel.com/docs/13.x/eloquent]
- https://laravel.com/docs/13.x/database — `DB::transaction` usage. [CITED: https://laravel.com/docs/13.x/database]
- https://laravel.com/docs/13.x/validation — controller validation, redirect behavior, and named error bags. [CITED: https://laravel.com/docs/13.x/validation]
- https://docs.github.com/en/rest/pulls/comments — review-comment payload fields used to reason about metadata retention. [CITED: https://docs.github.com/en/rest/pulls/comments]

### Tertiary (LOW confidence)
- Local code inspection of `app/`, `resources/views/`, `routes/`, `tests/`, `composer.json`, `composer.lock`, and planning files. [VERIFIED: codebase grep]

## Metadata

**Confidence breakdown:**
- Standard stack: MEDIUM - repo versions are verified locally, and the behavioral recommendations rely on official Laravel docs rather than third-party guidance. [VERIFIED: codebase grep][CITED: https://laravel.com/docs/13.x/eloquent-relationships]
- Architecture: MEDIUM - the retry/provenance conflict is directly visible in code, but the recommended supersede strategy is still a planning recommendation that needs implementation choice. [VERIFIED: codebase grep][ASSUMED]
- Pitfalls: MEDIUM - the key retry pitfall is verified, while some UI/workflow pitfalls are reasoned from standard Laravel behavior. [VERIFIED: codebase grep][CITED: https://laravel.com/docs/13.x/validation][ASSUMED]

**Research date:** 2026-06-28 [VERIFIED: session metadata]
**Valid until:** 2026-07-12 for planning purposes, or sooner if Phase 3/4 code changes materially before planning. [ASSUMED]
