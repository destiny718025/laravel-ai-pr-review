# Requirements: Laravel AI PR Review

**Defined:** 2026-06-26
**Core Value:** Turn a GitHub PR URL into useful, reviewable AI findings and comment drafts that help catch bugs and security issues before code is merged.

## v1 Requirements

Requirements for the initial personal-use MVP. Each requirement is testable and will map to one roadmap phase.

### Review Runs and Management UI

- [x] **RUN-01**: User can open a web management interface without logging in
- [x] **RUN-02**: User can submit a GitHub pull request URL from the management interface
- [x] **RUN-03**: System validates the submitted URL as a GitHub pull request URL before creating a review run
- [x] **RUN-04**: System creates a persisted review run with status, source URL, repository owner/name, pull request number, and timestamps
- [x] **RUN-05**: User can view a review history page listing review runs with status and basic PR identity
- [x] **RUN-06**: User can open a review run detail page from the history page
- [x] **RUN-07**: User can see review run failure status and a safe summarized error message when a run fails

### Architecture and Persistence

- [x] **ARCH-01**: Review run workflows use Controller / Service / Repository layering
- [x] **ARCH-02**: Controllers handle HTTP validation, redirects, and view responses without owning business logic
- [x] **ARCH-03**: Services own business workflows for creating, executing, and publishing review runs
- [x] **ARCH-04**: Repositories own database reads and writes for review runs, findings, drafts, and settings
- [x] **ARCH-05**: External GitHub and AI provider calls are hidden behind interfaces that can be faked in tests

### GitHub PR Ingestion

- [x] **GH-01**: System can parse a GitHub PR URL into owner, repository, and pull request number
- [x] **GH-02**: System can fetch pull request metadata from GitHub through a GitHub client interface
- [x] **GH-03**: System can fetch pull request changed files and patch data through a GitHub client interface
- [x] **GH-04**: System stores replayable diff snapshot data for later line-level comments, including filename, patch, file SHA, and PR head SHA
- [x] **GH-05**: System records a clear failure state when GitHub API calls fail or the PR cannot be read
- [x] **GH-06**: Tests can fake GitHub API responses without calling the real GitHub API

### Review Execution

- [x] **EXEC-01**: System dispatches review execution to a Laravel queued job instead of running AI review work inside the HTTP request
- [x] **EXEC-02**: Review execution job loads the review run and marks it in progress before external work begins
- [x] **EXEC-03**: Review execution job marks the review run completed when findings and drafts are persisted
- [x] **EXEC-04**: Review execution job marks the review run failed with a safe summarized error when GitHub, AI, or parsing work fails
- [x] **EXEC-05**: Review execution avoids logging raw API credentials, authorization headers, or unredacted provider payloads

### AI Review

- [x] **AI-01**: System defines an AI review provider interface for generating structured review output
- [x] **AI-02**: System includes a fake AI review provider for deterministic local tests
- [x] **AI-03**: System can use one concrete AI provider implementation behind the provider interface
- [x] **AI-04**: AI review output is validated against a structured finding schema before persistence
- [x] **AI-05**: Structured findings include severity, category, file path, line reference when available, title, rationale, and suggested comment text
- [x] **AI-06**: Default review instructions prioritize bug and security issues
- [x] **AI-07**: Default review instructions allow Laravel/PHP style feedback when it is useful and not noisy
- [x] **AI-08**: Invalid or incomplete AI output fails the review run safely without creating malformed findings

### Findings and Comment Drafts

- [x] **DRAFT-01**: System persists structured review findings for a completed review run
- [ ] **DRAFT-02**: System creates comment drafts from AI findings instead of posting comments automatically
- [ ] **DRAFT-03**: User can view findings and comment drafts on the review run detail page
- [ ] **DRAFT-04**: User can edit a comment draft before approving it
- [ ] **DRAFT-05**: User can approve one or more comment drafts for publication
- [x] **DRAFT-06**: Comment drafts track publication status such as draft, approved, posted, and failed
- [x] **DRAFT-07**: Comment drafts retain GitHub comment targeting metadata needed for line-level publication when available

### Custom Review Instructions

- [ ] **RULE-01**: User can view current custom review instructions in the management interface
- [ ] **RULE-02**: User can update custom review instructions through a simple textarea
- [ ] **RULE-03**: Review execution includes saved custom instructions when generating AI review output
- [ ] **RULE-04**: System stores custom instructions separately from generated findings and drafts

### GitHub Comment Publishing

- [x] **PUB-01**: User can publish approved comment drafts to GitHub
- [x] **PUB-02**: System publishes comments through a GitHub client interface
- [x] **PUB-03**: System records successful GitHub publication on each published draft
- [x] **PUB-04**: System records failed GitHub publication on each failed draft with a safe summarized error
- [x] **PUB-05**: Tests can fake GitHub comment publication without calling the real GitHub API
- [x] **PUB-06**: System never publishes AI-generated comments without explicit user approval

## v2 Requirements

Deferred to future releases. Tracked but not in the current roadmap.

### GitHub Automation

- **WEB-01**: System can receive GitHub pull request webhook events
- **WEB-02**: System validates GitHub webhook signatures before processing payloads
- **WEB-03**: System deduplicates webhook deliveries to avoid duplicate review runs
- **WEB-04**: System can automatically enqueue review runs for configured repositories

### Authentication and Teams

- **AUTH-01**: User can log in before accessing the management interface
- **AUTH-02**: Multiple users can belong to a team or organization
- **AUTH-03**: Team members can share review history and settings
- **AUTH-04**: Users can control who can publish drafts to GitHub

### Rule Management

- **RULEX-01**: User can create multiple named rule sets
- **RULEX-02**: User can assign rule sets by repository
- **RULEX-03**: User can enable or disable specific review categories
- **RULEX-04**: User can version rule changes over time

### Operations and SaaS

- **OPS-01**: System records provider cost or token usage metadata
- **OPS-02**: System supports production queue monitoring
- **OPS-03**: System supports tenant-aware configuration
- **OPS-04**: System supports billing or subscription state

## Out of Scope

Explicitly excluded from v1 to prevent scope creep.

| Feature | Reason |
|---------|--------|
| User login/authentication | First version is for local/private personal use |
| Team permissions | Core review workflow must be validated before team workflow |
| GitHub webhook automation | Manual PR URL review is the first validation path |
| Full rule engine | A simple custom instructions textarea is enough for v1 |
| Multi-provider UI | Provider abstraction is required, UI for switching providers can wait |
| Billing or SaaS tenant management | Not needed for personal-use MVP |
| Fully automatic GitHub comment posting | Manual approval is required to preserve trust |
| Support for GitLab/Bitbucket | GitHub is the initial integration target |
| Reviewing entire repository history | v1 focuses on pull request diffs |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| RUN-01 | Phase 1 | Complete |
| RUN-02 | Phase 1 | Complete |
| RUN-03 | Phase 1 | Complete |
| RUN-04 | Phase 1 | Complete |
| RUN-05 | Phase 1 | Complete |
| RUN-06 | Phase 1 | Complete |
| RUN-07 | Phase 1 | Complete |
| ARCH-01 | Phase 1 | Complete |
| ARCH-02 | Phase 1 | Complete |
| ARCH-03 | Phase 1 | Complete |
| ARCH-04 | Phase 1 | Complete |
| ARCH-05 | Phase 2 | Complete |
| GH-01 | Phase 1 | Complete |
| GH-02 | Phase 2 | Complete |
| GH-03 | Phase 2 | Complete |
| GH-04 | Phase 2 | Complete |
| GH-05 | Phase 2 | Complete |
| GH-06 | Phase 2 | Complete |
| EXEC-01 | Phase 3 | Complete |
| EXEC-02 | Phase 3 | Complete |
| EXEC-03 | Phase 3 | Complete |
| EXEC-04 | Phase 3 | Complete |
| EXEC-05 | Phase 3 | Complete |
| AI-01 | Phase 3 | Complete |
| AI-02 | Phase 3 | Complete |
| AI-03 | Phase 3 | Complete |
| AI-04 | Phase 3 | Complete |
| AI-05 | Phase 3 | Complete |
| AI-06 | Phase 3 | Complete |
| AI-07 | Phase 3 | Complete |
| AI-08 | Phase 3 | Complete |
| DRAFT-01 | Phase 4 | Complete |
| DRAFT-02 | Phase 4 | Pending |
| DRAFT-03 | Phase 4 | Pending |
| DRAFT-04 | Phase 4 | Pending |
| DRAFT-05 | Phase 4 | Pending |
| DRAFT-06 | Phase 4 | Complete |
| DRAFT-07 | Phase 4 | Complete |
| RULE-01 | Phase 4 | Pending |
| RULE-02 | Phase 4 | Pending |
| RULE-03 | Phase 4 | Pending |
| RULE-04 | Phase 4 | Pending |
| PUB-01 | Phase 5 | Complete |
| PUB-02 | Phase 5 | Complete |
| PUB-03 | Phase 5 | Complete |
| PUB-04 | Phase 5 | Complete |
| PUB-05 | Phase 5 | Complete |
| PUB-06 | Phase 5 | Complete |

**Coverage:**

- v1 requirements: 48 total
- Mapped to phases: 48
- Unmapped: 0

---
*Requirements defined: 2026-06-26*
*Last updated: 2026-06-26 after roadmap creation*
