# Roadmap: Laravel AI PR Review

## Overview

The v1 roadmap builds a vertical MVP from a usable Laravel management interface toward a complete manual AI review workflow. The first phase creates review run persistence and UI shell, the second brings in GitHub PR data, the third executes queued AI review into structured findings, the fourth turns findings into editable drafts with simple custom instructions, and the fifth safely publishes approved drafts back to GitHub.

## Phases

**Phase Numbering:**

- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

- [x] **Phase 1: Review Run Foundation and Management UI** - User can submit PR URLs and see persisted review run status/history
- [x] **Phase 2: GitHub PR Ingestion** - System fetches PR metadata/files and stores replayable diff snapshots (completed 2026-06-27)
- [x] **Phase 3: Queued AI Review and Structured Findings** - System executes review jobs through an AI provider interface and persists validated findings (completed 2026-06-28)
- [x] **Phase 4: Draft Review and Custom Instructions** - User can inspect findings, edit drafts, approve drafts, and tune simple instructions (completed 2026-06-28)
- [x] **Phase 5: GitHub Comment Publishing** - User can publish approved drafts to GitHub with per-draft status and error handling (completed 2026-06-29)

## Phase Details

### Phase 1: Review Run Foundation and Management UI

**Goal**: Build the first usable vertical slice: a no-login management UI that creates and displays persisted review runs.
**Mode:** mvp
**Depends on**: Nothing (first phase)
**Requirements**: [RUN-01, RUN-02, RUN-03, RUN-04, RUN-05, RUN-06, RUN-07, ARCH-01, ARCH-02, ARCH-03, ARCH-04, GH-01]
**Success Criteria** (what must be TRUE):

  1. User can open the management interface without logging in.
  2. User can submit a GitHub PR URL and see a persisted review run created.
  3. User can view a history page with review run status and basic PR identity.
  4. User can open a review run detail page and see safe failure/status information.
  5. Review run creation uses thin controllers, services for workflow, and repositories for database access.

**Plans**: 4/4 plans executed

Plans:

- [x] 01-01-PLAN.md
- [x] 01-02-PLAN.md
- [x] 01-03-PLAN.md
- [x] 01-04-PLAN.md

**Wave 1**

- [x] 01-01: Create schema, models, and status foundation

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 01-02: Add PR URL parser, DTOs, repositories, and review run service

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 01-03: Build review routes, create dashboard, and minimal detail shell

**Wave 4** *(blocked on Wave 3 completion)*

- [x] 01-04: Build review history/detail pages and safe status/failure display

### Phase 2: GitHub PR Ingestion

**Goal**: Fetch GitHub pull request metadata and changed file data through a fakeable GitHub client.
**Mode:** mvp
**Depends on**: Phase 1
**Requirements**: [ARCH-05, GH-02, GH-03, GH-04, GH-05, GH-06]
**Success Criteria** (what must be TRUE):

  1. System can fetch PR metadata and changed files through a GitHub client interface.
  2. System stores raw changed-file snapshots with filename, patch, file SHA, and PR head SHA so later phases can derive line-level comment targeting.
  3. GitHub failures mark the review run failed with a safe summarized error.
  4. Tests fake GitHub responses and do not call the real GitHub API.

**Plans**: 3 plans

Plans:
**Wave 1**

- [x] 02-01: Add GitHub client interface, HTTP implementation, and fake/test fixtures

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 02-02: Implement PR metadata/files ingestion and diff metadata persistence

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 02-03: Add GitHub ingestion failure handling and tests

### Phase 3: Queued AI Review and Structured Findings

**Goal**: As a reviewer, I want to run AI review asynchronously for a fetched GitHub pull request, so that validated findings are persisted and visible without blocking the request.
**Mode:** mvp
**Depends on**: Phase 2
**Requirements**: [EXEC-01, EXEC-02, EXEC-03, EXEC-04, EXEC-05, AI-01, AI-02, AI-03, AI-04, AI-05, AI-06, AI-07, AI-08]
**Success Criteria** (what must be TRUE):

  1. Submitting a review run dispatches a queued job rather than doing AI work in the HTTP request.
  2. Review execution transitions runs through in-progress, completed, and failed states.
  3. AI review runs through a provider interface with deterministic fake provider tests.
  4. Structured AI output is validated before findings are persisted.
  5. Invalid AI output fails safely without malformed findings or secret leakage.

**Plans**: 5 plans

Plans:

- [x] 03-01-PLAN.md — Add manual run action, queued dispatch, and lifecycle status transitions
- [x] 03-02-PLAN.md — Add fake-first AI provider contract, request DTO, fixtures, and default instructions
- [x] 03-03-PLAN.md — Add structured output validation, failure mapping, and execution-service safety wiring
- [x] 03-04-PLAN.md — Add the opt-in OpenAI adapter seam behind the provider interface
- [x] 03-05-PLAN.md — Persist validated findings, render them on the detail page, and harden retry/failure behavior

**Wave 1**

- [x] 03-01: Add manual run action, queued dispatch, and lifecycle status transitions

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 03-02: Add fake-first AI provider contract, request DTO, fixtures, and default instructions

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 03-03: Add structured output validation, failure mapping, and execution-service safety wiring
- [x] 03-04: Add the opt-in OpenAI adapter seam behind the provider interface

**Wave 4** *(blocked on Wave 3 completion)*

- [x] 03-05: Persist validated findings, render them on the detail page, and harden retry/failure behavior

### Phase 4: Draft Review and Custom Instructions

**Goal**: Convert findings into editable comment drafts and let the user tune simple review instructions.
**Mode:** mvp
**Depends on**: Phase 3
**Requirements**: [DRAFT-01, DRAFT-02, DRAFT-03, DRAFT-04, DRAFT-05, DRAFT-06, DRAFT-07, RULE-01, RULE-02, RULE-03, RULE-04]
**Success Criteria** (what must be TRUE):

  1. Completed review runs show structured findings and generated comment drafts.
  2. User can edit a draft before approving it.
  3. User can approve one or more drafts without posting them automatically.
  4. Drafts track draft/approved/posted/failed state and retain GitHub targeting metadata.
  5. User can edit custom review instructions and future AI reviews include them.

**Plans**: 5/5 plans executed

Plans:

- [x] 04-01-PLAN.md
- [x] 04-02-PLAN.md
- [x] 04-03-PLAN.md
- [x] 04-04-PLAN.md
- [x] 04-05-PLAN.md

**Wave 1**

- [x] 04-01: Add draft persistence foundation and superseded-finding provenance

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 04-02: Add manual draft generation and split detail-page presentation

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 04-03: Build draft edit/approve/unapprove workflow and retry stale marking

**Wave 4** *(blocked on Wave 3 completion)*

- [x] 04-04: Add global custom-instructions storage and management UI

**Wave 5** *(blocked on Wave 4 completion)*

- [x] 04-05: Integrate saved custom instructions into future AI execution and retries

### Phase 5: GitHub Comment Publishing

**Goal**: Publish only approved comment drafts to GitHub and track the result for each draft.
**Mode:** mvp
**Depends on**: Phase 4
**Requirements**: [PUB-01, PUB-02, PUB-03, PUB-04, PUB-05, PUB-06]
**Success Criteria** (what must be TRUE):

  1. User can publish approved drafts to GitHub from the review detail page.
  2. Publishing uses a GitHub client interface and can be fully faked in tests.
  3. Each draft records posted or failed publication status.
  4. Failed publication shows a safe summarized error.
  5. The system never posts AI-generated comments without explicit user approval.

**Plans**: 3/3 plans complete

Plans:

- [x] 05-01-PLAN.md
- [x] 05-02-PLAN.md
- [x] 05-03-PLAN.md

**Wave 1**

- [x] 05-01: Add GitHub publication client write path and safe failure mapping

**Wave 2** *(blocked on Wave 1 completion)*

- [x] 05-02: Implement approved-draft publication service and per-draft persistence

**Wave 3** *(blocked on Wave 2 completion)*

- [x] 05-03: Add publish UI, per-draft status handling, and fake GitHub publication tests

## Future Direction

After v1 validates the manual review workflow, the next milestone should add GitHub webhook automation with signature validation and idempotency, followed by authentication/team workflows and richer rule management.

## Progress

**Execution Order:**
Phases execute in numeric order: 1 -> 2 -> 3 -> 4 -> 5

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Review Run Foundation and Management UI | 4/4 | Complete | 2026-06-27 |
| 2. GitHub PR Ingestion | 3/3 | Complete    | 2026-06-27 |
| 3. Queued AI Review and Structured Findings | 5/5 | Complete    | 2026-06-28 |
| 4. Draft Review and Custom Instructions | 5/5 | Complete    | 2026-06-28 |
| 5. GitHub Comment Publishing | 3/3 | Complete    | 2026-06-29 |
