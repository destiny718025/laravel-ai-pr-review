# Requirements: Laravel AI PR Review

**Defined:** 2026-06-26
**Core Value:** Turn a GitHub PR URL into useful, reviewable AI findings and comment drafts that help catch bugs and security issues before code is merged.

## v1 Requirements

Requirements for the initial personal-use MVP. Each requirement is testable and will map to one roadmap phase.

### Review Runs and Management UI

- [ ] **RUN-01**: User can open a web management interface without logging in
- [ ] **RUN-02**: User can submit a GitHub pull request URL from the management interface
- [ ] **RUN-03**: System validates the submitted URL as a GitHub pull request URL before creating a review run
- [ ] **RUN-04**: System creates a persisted review run with status, source URL, repository owner/name, pull request number, and timestamps
- [ ] **RUN-05**: User can view a review history page listing review runs with status and basic PR identity
- [ ] **RUN-06**: User can open a review run detail page from the history page
- [ ] **RUN-07**: User can see review run failure status and a safe summarized error message when a run fails

### Architecture and Persistence

- [ ] **ARCH-01**: Review run workflows use Controller / Service / Repository layering
- [ ] **ARCH-02**: Controllers handle HTTP validation, redirects, and view responses without owning business logic
- [ ] **ARCH-03**: Services own business workflows for creating, executing, and publishing review runs
- [ ] **ARCH-04**: Repositories own database reads and writes for review runs, findings, drafts, and settings
- [ ] **ARCH-05**: External GitHub and AI provider calls are hidden behind interfaces that can be faked in tests

### GitHub PR Ingestion

- [ ] **GH-01**: System can parse a GitHub PR URL into owner, repository, and pull request number
- [ ] **GH-02**: System can fetch pull request metadata from GitHub through a GitHub client interface
- [ ] **GH-03**: System can fetch pull request changed files and patch data through a GitHub client interface
- [ ] **GH-04**: System stores enough diff metadata to later publish line-level comments, including file path, line, side, and commit SHA when available
- [ ] **GH-05**: System records a clear failure state when GitHub API calls fail or the PR cannot be read
- [ ] **GH-06**: Tests can fake GitHub API responses without calling the real GitHub API

### Review Execution

- [ ] **EXEC-01**: System dispatches review execution to a Laravel queued job instead of running AI review work inside the HTTP request
- [ ] **EXEC-02**: Review execution job loads the review run and marks it in progress before external work begins
- [ ] **EXEC-03**: Review execution job marks the review run completed when findings and drafts are persisted
- [ ] **EXEC-04**: Review execution job marks the review run failed with a safe summarized error when GitHub, AI, or parsing work fails
- [ ] **EXEC-05**: Review execution avoids logging raw API credentials, authorization headers, or unredacted provider payloads

### AI Review

- [ ] **AI-01**: System defines an AI review provider interface for generating structured review output
- [ ] **AI-02**: System includes a fake AI review provider for deterministic local tests
- [ ] **AI-03**: System can use one concrete AI provider implementation behind the provider interface
- [ ] **AI-04**: AI review output is validated against a structured finding schema before persistence
- [ ] **AI-05**: Structured findings include severity, category, file path, line reference when available, title, rationale, and suggested comment text
- [ ] **AI-06**: Default review instructions prioritize bug and security issues
- [ ] **AI-07**: Default review instructions allow Laravel/PHP style feedback when it is useful and not noisy
- [ ] **AI-08**: Invalid or incomplete AI output fails the review run safely without creating malformed findings

### Findings and Comment Drafts

- [ ] **DRAFT-01**: System persists structured review findings for a completed review run
- [ ] **DRAFT-02**: System creates comment drafts from AI findings instead of posting comments automatically
- [ ] **DRAFT-03**: User can view findings and comment drafts on the review run detail page
- [ ] **DRAFT-04**: User can edit a comment draft before approving it
- [ ] **DRAFT-05**: User can approve one or more comment drafts for publication
- [ ] **DRAFT-06**: Comment drafts track publication status such as draft, approved, posted, and failed
- [ ] **DRAFT-07**: Comment drafts retain GitHub comment targeting metadata needed for line-level publication when available

### Custom Review Instructions

- [ ] **RULE-01**: User can view current custom review instructions in the management interface
- [ ] **RULE-02**: User can update custom review instructions through a simple textarea
- [ ] **RULE-03**: Review execution includes saved custom instructions when generating AI review output
- [ ] **RULE-04**: System stores custom instructions separately from generated findings and drafts

### GitHub Comment Publishing

- [ ] **PUB-01**: User can publish approved comment drafts to GitHub
- [ ] **PUB-02**: System publishes comments through a GitHub client interface
- [ ] **PUB-03**: System records successful GitHub publication on each published draft
- [ ] **PUB-04**: System records failed GitHub publication on each failed draft with a safe summarized error
- [ ] **PUB-05**: Tests can fake GitHub comment publication without calling the real GitHub API
- [ ] **PUB-06**: System never publishes AI-generated comments without explicit user approval

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
| RUN-01 | TBD | Pending |
| RUN-02 | TBD | Pending |
| RUN-03 | TBD | Pending |
| RUN-04 | TBD | Pending |
| RUN-05 | TBD | Pending |
| RUN-06 | TBD | Pending |
| RUN-07 | TBD | Pending |
| ARCH-01 | TBD | Pending |
| ARCH-02 | TBD | Pending |
| ARCH-03 | TBD | Pending |
| ARCH-04 | TBD | Pending |
| ARCH-05 | TBD | Pending |
| GH-01 | TBD | Pending |
| GH-02 | TBD | Pending |
| GH-03 | TBD | Pending |
| GH-04 | TBD | Pending |
| GH-05 | TBD | Pending |
| GH-06 | TBD | Pending |
| EXEC-01 | TBD | Pending |
| EXEC-02 | TBD | Pending |
| EXEC-03 | TBD | Pending |
| EXEC-04 | TBD | Pending |
| EXEC-05 | TBD | Pending |
| AI-01 | TBD | Pending |
| AI-02 | TBD | Pending |
| AI-03 | TBD | Pending |
| AI-04 | TBD | Pending |
| AI-05 | TBD | Pending |
| AI-06 | TBD | Pending |
| AI-07 | TBD | Pending |
| AI-08 | TBD | Pending |
| DRAFT-01 | TBD | Pending |
| DRAFT-02 | TBD | Pending |
| DRAFT-03 | TBD | Pending |
| DRAFT-04 | TBD | Pending |
| DRAFT-05 | TBD | Pending |
| DRAFT-06 | TBD | Pending |
| DRAFT-07 | TBD | Pending |
| RULE-01 | TBD | Pending |
| RULE-02 | TBD | Pending |
| RULE-03 | TBD | Pending |
| RULE-04 | TBD | Pending |
| PUB-01 | TBD | Pending |
| PUB-02 | TBD | Pending |
| PUB-03 | TBD | Pending |
| PUB-04 | TBD | Pending |
| PUB-05 | TBD | Pending |
| PUB-06 | TBD | Pending |

**Coverage:**
- v1 requirements: 48 total
- Mapped to phases: 0
- Unmapped: 48 (will be mapped during roadmap creation)

---
*Requirements defined: 2026-06-26*
*Last updated: 2026-06-26 after initial definition*
