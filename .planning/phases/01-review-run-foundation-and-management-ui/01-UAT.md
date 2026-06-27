---
status: complete
phase: 01-review-run-foundation-and-management-ui
source:
  - 01-01-SUMMARY.md
  - 01-02-SUMMARY.md
  - 01-03-SUMMARY.md
  - 01-04-SUMMARY.md
started: 2026-06-27T03:44:27Z
updated: 2026-06-27T03:48:00Z
---

## Current Test

number: complete
name: Phase 1 UAT Complete
expected: |
  All Phase 1 user-facing acceptance checks passed.
awaiting: none

## Tests

### 1. Create a Review Run From the Dashboard
expected: |
  Open `/reviews` without logging in. You should see the `Laravel AI PR Review` header, the `Review Runs` page title, and the `Create a Review Run` form above `Recent Review Runs`.

  Paste a valid GitHub pull request URL such as `https://github.com/owner/repo/pull/123` and submit it. The app should create a pending review run, redirect to `Review Run #{id}`, show a `Pending` status pill, show repository `owner/repo`, show `PR #123`, show the source URL, and show `This review run is ready for the next processing step.`
result: pass

### 2. Review Run History and Detail Navigation
expected: |
  Return to `/reviews`. The new review run should appear in `Recent Review Runs` with status, repository full name, PR number, source URL, created timestamp, and one `View review run` link. Opening the link should return to the matching detail page.
result: pass

### 3. Invalid URL Handling
expected: |
  Submit an invalid pull request URL from `/reviews`. The app should stay on the dashboard, show `Review run was not created`, tell you to check that the URL points to a GitHub pull request, preserve safe input, and not add a new history row.
result: pass

### 4. Safe Status and Failure Presentation
expected: |
  Review run status labels should read as `Pending`, `Queued`, `Running`, `Completed`, `Failed`, or `Cancelled` when those statuses exist. Failed review runs should show only safe error summary copy or `The run failed, but no safe error summary was recorded.`, plus `Review the safe error summary, then create a new run after fixing the source issue.`
result: pass

### 5. Automated Schema and Layering Coverage
expected: |
  Automated tests cover persistence schema, PR URL parsing, controller/service/repository boundaries, history ordering, detail metadata, safe failed copy, and full suite regression.
result: pass
source: automated
coverage_id: phase-1-automated-tests

## Summary

total: 5
passed: 5
issues: 0
pending: 0
skipped: 0

## Gaps

None yet.
