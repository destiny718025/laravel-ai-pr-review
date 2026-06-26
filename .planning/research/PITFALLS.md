# Pitfalls Research

**Domain:** AI-assisted GitHub pull request review tool
**Researched:** 2026-06-26
**Confidence:** HIGH

## Critical Pitfalls

### Pitfall 1: Posting Noisy AI Comments Automatically

**What goes wrong:**
The app posts every generated finding directly to GitHub, creating false positives and noisy review threads.

**Why it happens:**
Automation feels like the product goal, but AI review quality still varies and users need trust before write actions.

**How to avoid:**
Generate comment drafts first. Require manual approval before any GitHub write call.

**Warning signs:**
No `ReviewCommentDraft` state, no approval UI, controller/job posts directly after AI response.

**Phase to address:**
Phase 1 or 2, before GitHub comment publishing.

---

### Pitfall 2: Using Deprecated/Fragile GitHub Diff Position Logic

**What goes wrong:**
Generated comments fail validation or attach to wrong lines because the app uses legacy `position` values instead of modern `line` and `side` fields.

**Why it happens:**
Older examples use diff positions, but GitHub now marks `position` as closing down.

**How to avoid:**
Normalize PR file patches into file path, target line, side, optional start line, and commit SHA. Store this metadata with drafts.

**Warning signs:**
Drafts only store body/path/position, no `side`, no `line`, no commit SHA.

**Phase to address:**
Diff ingestion phase.

---

### Pitfall 3: Blocking HTTP Requests on AI Review Work

**What goes wrong:**
Submitting a PR URL times out or feels broken because GitHub and AI calls happen synchronously.

**Why it happens:**
It is simpler to implement everything inside a controller at first.

**How to avoid:**
Create review run records synchronously, dispatch a queued job, and use status pages for progress.

**Warning signs:**
Controller methods call AI provider directly or perform multiple external API calls before responding.

**Phase to address:**
Review run creation phase.

---

### Pitfall 4: Trusting Raw AI JSON Without Validation

**What goes wrong:**
Malformed or partial AI output crashes the job, creates invalid records, or posts bad comments.

**Why it happens:**
Prompting for JSON feels sufficient, but valid JSON is not the same as schema adherence.

**How to avoid:**
Use provider-specific structured output/tool schema features where possible, then validate server-side before persistence.

**Warning signs:**
Code uses `json_decode()` and immediately saves records without schema/value validation.

**Phase to address:**
AI provider phase.

---

### Pitfall 5: Leaking Source Code, Tokens, or Provider Payloads

**What goes wrong:**
Sensitive repo data, GitHub tokens, or AI provider responses end up in logs or database fields.

**Why it happens:**
Debugging external integrations often tempts developers to log full requests/responses.

**How to avoid:**
Redact auth headers, avoid storing raw provider payloads by default, and log only stable IDs, status codes, and summarized errors.

**Warning signs:**
Logs contain authorization headers, raw `.env` values, or full patch payloads.

**Phase to address:**
Every integration phase.

## Technical Debt Patterns

| Shortcut | Immediate Benefit | Long-term Cost | When Acceptable |
|----------|-------------------|----------------|-----------------|
| Putting Eloquent queries in services | Faster early coding | Harder to enforce repository boundary | Avoid because user explicitly chose repositories |
| One giant review service | Quick orchestration | Hard to test and modify | Split once more than one workflow exists |
| Storing only raw AI output | Simple database | Poor UI, hard publishing, poor diagnostics | Never for v1 core records |
| No explicit status transitions | Less code | Confusing failed/stuck runs | Never for review runs |
| No fake providers | Less test setup | Real API calls in tests | Never once external calls exist |

## Integration Gotchas

| Integration | Common Mistake | Correct Approach |
|-------------|----------------|------------------|
| GitHub PR files | Ignoring pagination and file count limits | Persist enough metadata and detect oversized PRs |
| GitHub review comments | Using `position` only | Store `line`, `side`, path, and commit SHA |
| GitHub webhooks | Trusting payloads without signature validation | Validate `X-Hub-Signature-256` with configured secret |
| AI provider | Free-form prose output | Schema-shaped output plus validation |
| Laravel queue | Infinite retries on provider failures | Set attempts/timeouts and throttle exceptions |

## Performance Traps

| Trap | Symptoms | Prevention | When It Breaks |
|------|----------|------------|----------------|
| Reviewing huge PRs in one prompt | High cost, worse findings, timeouts | Add file limits and summarize/segment input | Large diffs or many files |
| Re-fetching PR data on every page load | Slow detail pages | Persist review input/output snapshots | Once history grows |
| Posting comments one by one with no rate handling | 403/422 failures | Track publish status, retry safely, throttle | Many drafts |

## Security Mistakes

| Mistake | Risk | Prevention |
|---------|------|------------|
| Logging auth headers | Token exposure | Redact headers and config values |
| Accepting arbitrary PR URLs without validation | SSRF-like surprises or invalid requests | Parse and restrict to GitHub owner/repo/number |
| Webhook without HMAC validation | Forged review triggers | Validate signature before enqueueing |
| Sending private code to AI unintentionally | Confidentiality risk | Make provider use explicit and documented |
| Auto-posting AI comments | Reputation/trust damage | Manual approval gate |

## UX Pitfalls

| Pitfall | User Impact | Better Approach |
|---------|-------------|-----------------|
| Showing raw AI output only | Hard to act on | Show findings and drafts grouped by file |
| Hiding failures in logs | User thinks review is stuck | Store visible run status and error reason |
| No edit/approve step | User loses control | Draft-first workflow |
| Too many low-severity comments | User stops trusting tool | Severity/category filters and concise drafts |

## "Looks Done But Isn't" Checklist

- [ ] **PR URL review:** Often missing invalid URL handling — verify validation and helpful error display
- [ ] **Diff ingestion:** Often missing line/side metadata — verify drafts can map to GitHub comment API
- [ ] **AI review:** Often missing schema validation — verify invalid provider output marks run failed cleanly
- [ ] **Comment posting:** Often missing per-draft status — verify partial publish failure is visible
- [ ] **Tests:** Often missing external API fakes — verify no tests hit real GitHub or AI APIs

## Pitfall-to-Phase Mapping

| Pitfall | Prevention Phase | Verification |
|---------|------------------|--------------|
| Noisy auto-posting | Draft UI / publish phase | Comments cannot be posted without user action |
| Fragile diff positions | GitHub ingestion phase | Drafts store path, line, side, commit SHA |
| Blocking requests | Review run creation phase | Controller dispatches job and returns quickly |
| Raw AI JSON trust | AI provider phase | Invalid schema is tested and handled |
| Secret leakage | Integration phases | Logs and persisted errors are redacted |

## Sources

- GitHub review comment API: https://docs.github.com/en/rest/pulls/comments?apiVersion=2022-11-28#create-a-review-comment-for-a-pull-request
- GitHub webhook validation: https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries
- Laravel queues: https://laravel.com/docs/13.x/queues
- Laravel HTTP client testing: https://laravel.com/docs/13.x/http-client#testing
- OpenAI Structured Outputs: https://developers.openai.com/api/docs/guides/structured-outputs
- SWE-PRBench paper: https://arxiv.org/abs/2603.26130
- AI code review impact case study: https://arxiv.org/abs/2508.18771

---
*Pitfalls research for: AI-assisted GitHub pull request review tool*
*Researched: 2026-06-26*
