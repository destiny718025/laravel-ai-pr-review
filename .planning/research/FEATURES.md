# Feature Research

**Domain:** AI-assisted GitHub pull request review tool
**Researched:** 2026-06-26
**Confidence:** MEDIUM-HIGH

## Feature Landscape

### Table Stakes (Users Expect These)

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| PR URL submission | Fastest way to validate the workflow without webhook setup | LOW | Parse owner, repo, and PR number |
| Review run persistence | User expects history and failure visibility | MEDIUM | Store status, source PR, timestamps, error reason |
| GitHub PR file/diff ingestion | Review quality depends on precise changed-file context | MEDIUM | Use list PR files and preserve patch metadata |
| AI findings with severity/category | Raw prose is hard to triage | MEDIUM | Schema should include severity, category, file path, line, rationale |
| Comment draft generation | Core value is GitHub-ready feedback | MEDIUM | Drafts should be editable/approvable before posting |
| Manual approval before posting | Builds trust and avoids noisy automation | MEDIUM | v1 should not auto-post |
| Review detail page | User needs to inspect findings and drafts | MEDIUM | Group by file and severity |
| Custom instructions textarea | User asked for rule management in v1 | LOW-MEDIUM | Store one active instruction set at first |
| Test fakes for external APIs | Without fakes, tests become slow/costly/flaky | LOW-MEDIUM | Fake GitHub and AI provider responses |

### Differentiators (Competitive Advantage)

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Provider interface | User can switch AI providers later | MEDIUM | Also improves testing |
| Draft-first UX | Keeps user control over GitHub comments | MEDIUM | Important trust differentiator |
| Laravel/PHP-aware review focus | Makes output more relevant for this project | MEDIUM | Encode in prompt/custom instructions |
| Finding-to-comment traceability | Helps explain why a comment exists | MEDIUM | Link draft comments back to finding IDs |
| Run-level diagnostics | Shows why a review failed | LOW-MEDIUM | Store API errors, parse failures, publish failures |

### Anti-Features (Commonly Requested, Often Problematic)

| Feature | Why Requested | Why Problematic | Alternative |
|---------|---------------|-----------------|-------------|
| Fully automatic posting | Feels productive | False positives and noisy comments reduce trust | Draft first, manual approval |
| Full repo context in every prompt | Seems more intelligent | Can dilute attention and raise cost | Use structured diff context first, add targeted context later |
| Complex rule engine in v1 | Sounds powerful | Delays workflow validation | Single custom instructions textarea |
| Multi-user/team workflow in v1 | Future SaaS direction | Adds auth/permissions before core value is proven | Personal-use local interface first |
| Webhook-first build | More automated | Adds signature, idempotency, and delivery retries early | Manual PR URL first, webhook later |

## Feature Dependencies

```text
PR URL submission
    -> GitHub PR metadata/files
        -> Diff normalization
            -> AI review run
                -> Findings
                    -> Comment drafts
                        -> Manual approval
                            -> GitHub comment publication

Custom instructions
    -> AI review run

Review run persistence
    -> History page
    -> Detail page
    -> Failure diagnostics
```

## MVP Definition

### Launch With (v1)

- [ ] Manual PR URL submission — validates core flow without webhook setup
- [ ] Review run database records — supports history and diagnostics
- [ ] GitHub PR files/diff fetching — required review input
- [ ] Queued review execution — avoids slow controller requests
- [ ] AI provider interface with one concrete provider/fake — keeps architecture flexible
- [ ] Structured findings and comment drafts — core output
- [ ] History and detail pages — management interface requirement
- [ ] Custom instructions textarea — simple rule control
- [ ] Manual approval/post action — safe GitHub publication

### Add After Validation (v1.x)

- [ ] Webhook trigger — add after manual flow is stable
- [ ] GitHub App installation flow — better private repo/token lifecycle
- [ ] Review retries and richer status events — useful once jobs run repeatedly
- [ ] Search/filter history — add when enough runs exist to need it

### Future Consideration (v2+)

- [ ] Multi-user auth and team permissions — needed for SaaS/team use
- [ ] Organization-level rule sets — after simple custom instructions prove useful
- [ ] Multi-provider configuration UI — after one provider path works
- [ ] Inline code context expansion — only if findings need broader context
- [ ] Billing/tenant management — only after the product direction is validated

## Feature Prioritization Matrix

| Feature | User Value | Implementation Cost | Priority |
|---------|------------|---------------------|----------|
| Manual PR URL submission | HIGH | LOW | P1 |
| GitHub PR file ingestion | HIGH | MEDIUM | P1 |
| Review run persistence | HIGH | MEDIUM | P1 |
| Queued review job | HIGH | MEDIUM | P1 |
| Structured AI findings | HIGH | MEDIUM | P1 |
| Comment draft review UI | HIGH | MEDIUM | P1 |
| Manual GitHub posting | HIGH | MEDIUM | P1 |
| Custom instructions | MEDIUM | LOW-MEDIUM | P1 |
| Webhook automation | MEDIUM | MEDIUM-HIGH | P2 |
| Auth/team workflow | LOW for first user | HIGH | P3 |

## Competitor / Market Signals

Published research on AI code review tools suggests comments that are concise, hunk-level, manually triggered, and include code snippets are more likely to lead to code changes. Security-focused studies also warn that AI code review may miss important vulnerabilities and over-index on low-severity style feedback. This supports the v1 choice to keep manual approval, store findings for review, and focus on high-signal bug/security comments.

## Sources

- GitHub pull request files endpoint: https://docs.github.com/en/rest/pulls/pulls?apiVersion=2022-11-28#list-pull-requests-files
- GitHub review comments endpoint: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28#create-a-review-comment-for-a-pull-request
- SWE-PRBench paper: https://arxiv.org/abs/2603.26130
- AI code review impact case study: https://arxiv.org/abs/2508.18771
- GitHub Copilot security review paper: https://arxiv.org/abs/2509.13650

---
*Feature research for: AI-assisted GitHub pull request review tool*
*Researched: 2026-06-26*
