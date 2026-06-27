---
phase: 01
slug: review-run-foundation-and-management-ui
status: approved
shadcn_initialized: false
preset: none
created: 2026-06-26
reviewed_at: 2026-06-26
---

# Phase 01 — UI Design Contract

Visual and interaction contract for Phase 01. This contract was produced through a generic-agent workaround because typed `gsd-ui-researcher` dispatch was unavailable; do not treat it as typed-agent verified.

## Design System

| Property | Value |
|----------|-------|
| Tool | none |
| Preset | none |
| Component library | none |
| Icon library | none for Phase 1; use text labels and status pills only |
| Font | Instrument Sans via existing Vite Bunny font setup |
| Rendering stack | Laravel Blade views with Tailwind CSS v4 utilities |
| Layout style | Restrained operational tool, full-width page bands with constrained inner content |
| Component extraction | Optional Blade partials/components for repeated status pills, form field errors, and review run rows only |

Phase 1 establishes the first app-specific UI convention. Do not reuse the default Laravel welcome page composition, illustration, gradients, dark marketing palette, or oversized hero treatment. The first screen must be the usable `/reviews` dashboard.

Use a single app shell for `/reviews` and `/reviews/{id}`:

- Page background: full viewport neutral background.
- Header band: product name `Laravel AI PR Review`, current section label, no marketing copy.
- Main content: constrained readable width with full-width sections, not floating page cards.
- Repeated review run items may use row cards or a table-like list, but do not put cards inside cards.
- Border radius: maximum `8px` for cards, inputs, status pills, and buttons.

## Spacing Scale

Declared values only:

| Token | Value | Usage |
|-------|-------|-------|
| xs | 4px | Status dot gaps, inline metadata separators, compact help/error text gap |
| sm | 8px | Label-to-input gap, table cell inner gaps, status pill padding |
| md | 16px | Default control padding, row spacing, form field stack spacing |
| lg | 24px | Section padding, dashboard form block spacing, detail metadata group spacing |
| xl | 32px | Page header-to-content gap, dashboard form-to-history gap |
| 2xl | 48px | Empty state vertical padding, major page section break |
| 3xl | 64px | Maximum top/bottom page shell padding on wide screens |

Exceptions: none.

Layout contracts:

- Page shell horizontal padding: `16px` on mobile, `24px` on tablet, `32px` on desktop.
- Main content max width: use a stable operational width around `1120px`; avoid full-bleed text lines.
- Dashboard sections stack with `32px` between form and history.
- Form controls use `8px` label/input spacing and `16px` field spacing.
- Review run rows use `16px` vertical padding and `24px` horizontal padding.
- Detail metadata groups use `24px` internal spacing and `32px` between major sections.
- Do not introduce spacing values outside `4, 8, 16, 24, 32, 48, 64`.

## Typography

Use exactly these sizes and weights for Phase 1:

| Role | Size | Weight | Line Height | Usage |
|------|------|--------|-------------|-------|
| Body | 16px | 400 | 1.5 | Form input text, normal paragraphs, source URLs, metadata values |
| Label | 14px | 600 | 1.4 | Form labels, table headers, metadata labels, status text |
| Heading | 20px | 600 | 1.3 | Section headings such as `Create a Review Run` and `Recent Review Runs` |
| Page Title | 28px | 600 | 1.2 | Page titles: `Review Runs`, `Review Run #123` |

Type rules:

- Font family: `Instrument Sans`, falling back to the existing sans stack in `resources/css/app.css`.
- Only weights `400` and `600` are allowed in implemented UI, even though the font loader also fetches `500`.
- Letter spacing must be `0`.
- Do not scale font sizes with viewport width.
- Truncate or wrap long repository names and URLs without overlapping neighboring metadata.
- Links should be underlined or visually distinct without relying on accent color alone.

## Color

Use a neutral light operational palette with a controlled teal accent.

| Role | Value | Usage |
|------|-------|-------|
| Dominant (60%) | `#F8FAF9` | Page background and broad app shell |
| Secondary (30%) | `#FFFFFF` | Form section surface, history rows, detail metadata sections |
| Secondary Border/Text Support | `#D7DEE2`, `#4B5563` | Borders, dividers, secondary metadata, helper text |
| Primary Text | `#111827` | Main readable text and headings |
| Accent (10%) | `#0F766E` | Primary CTA, focused input ring, active links, `pending` status indicator |
| Error | `#B42318` | Validation messages, failed status text, failed status border |
| Warning | `#A15C00` | Queued/running/cancelled status indicators if rendered |
| Success | `#15803D` | Completed status indicator if rendered |

Accent reserved for:

- `Create Review Run` button background.
- Input focus ring and focused link outline.
- Detail/history link affordance for opening an existing review run.
- `pending` status dot or pill border.

Do not use the accent for every clickable item, section decoration, background gradients, decorative bars, or empty state artwork. Do not introduce purple, beige, brown/orange, or dark blue/slate-dominant page themes.

Status color contracts:

- `pending`: teal indicator with neutral text.
- `queued` and `running`: warning indicator with neutral text.
- `completed`: green indicator with neutral text.
- `failed`: red indicator and safe error summary.
- `cancelled`: neutral or warning indicator; no destructive action is available in Phase 1.

## Copywriting Contract

| Element | Copy |
|---------|------|
| Product label | `Laravel AI PR Review` |
| Dashboard page title | `Review Runs` |
| Dashboard form heading | `Create a Review Run` |
| PR URL label | `GitHub Pull Request URL` |
| PR URL placeholder | `https://github.com/owner/repo/pull/123` |
| Primary CTA | `Create Review Run` |
| Recent history heading | `Recent Review Runs` |
| Empty state heading | `No review runs yet` |
| Empty state body | `Paste a GitHub pull request URL above to create your first review run.` |
| Validation error heading | `Review run was not created` |
| Validation error body | Display the service user-facing message, then include `Check that the URL points to a GitHub pull request and try again.` |
| Success flash | `Review run created.` |
| Detail page title | `Review Run #{id}` |
| Detail pending summary | `This review run is ready for the next processing step.` |
| Detail failed heading | `Review run failed` |
| Detail failed body | Display only `safe_error_message`; if missing, use `The run failed, but no safe error summary was recorded.` |
| Back link | `Back to review runs` |
| Destructive confirmation | Not applicable. Phase 1 has no destructive actions. |

Copy rules:

- Use specific nouns: `review run`, `pull request`, `repository`, `status`.
- Do not imply GitHub data was fetched in Phase 1. Avoid copy such as `Analyzing files`, `Review complete`, or `AI findings`.
- Error copy must always tell the user what to fix next.
- Empty state copy must always name the next step.
- Do not show raw exception messages, stack traces, API credentials, authorization headers, provider payloads, or unsanitized secrets.

## Screen Contracts

### `/reviews` Dashboard

Purpose: create a persisted review run from a GitHub PR URL and scan recent review run history.

Required content order:

1. App header: `Laravel AI PR Review`.
2. Page title: `Review Runs`.
3. Form section headed `Create a Review Run`.
4. Recent history section headed `Recent Review Runs`.

Form contract:

- Primary focal point: the `Create a Review Run` form at the top of `/reviews`.
- The form appears before history on all viewport sizes.
- The form has one text input named for the PR URL and one primary CTA, `Create Review Run`.
- The input accepts pasted URLs without hidden formatting helpers.
- On desktop, input and button may share one row if the input remains comfortably wider than the button.
- On mobile, input and button stack vertically, full width.
- Form helper text may state the accepted shape: `Use a GitHub pull request URL like https://github.com/owner/repo/pull/123.`
- HTTP validation and service validation errors render directly below the input/form, not in a detached toast-only location.
- On service failure, stay on `/reviews`, preserve the submitted URL when safe, and show the error code only as non-prominent diagnostic text if needed for development.

History contract:

- If there are no persisted runs, show the empty state copy from the copywriting contract.
- If runs exist, render a scan-friendly table or list with these visible fields: status, repository full name, PR number, source URL, created timestamp, and safe error summary when failed.
- Each run row must have one obvious detail link using copy `View review run`.
- Rows should be ordered newest first.
- Long source URLs wrap or truncate with a readable title/label; they must not force horizontal scrolling on mobile.
- Status appears near the left edge of each row so history can be scanned quickly.
- Failed run summaries must be short, safe, and visually tied to the failed row.

Out of scope on this screen:

- Login prompts.
- GitHub token setup.
- Provider selection.
- Queue controls.
- Findings, drafts, approval, publishing, or webhook settings.
- Delete, cancel, retry, or bulk actions.

### `/reviews/{id}` Detail Shell

Purpose: show one review run's persisted identity, lifecycle state, and safe error information.

Required content order:

1. Back link: `Back to review runs`.
2. Page title: `Review Run #{id}`.
3. Status summary band.
4. Pull request identity section.
5. Run metadata section.
6. Failure section only when status is `failed`.

Status summary contract:

- Show the status pill prominently near the page title.
- For `pending`, show `This review run is ready for the next processing step.`
- For `failed`, show `Review run failed` plus the safe error summary.
- Reserved statuses `queued`, `running`, `completed`, and `cancelled` must have stable visual treatment even if Phase 1 does not naturally create them.

Pull request identity contract:

- Show repository owner/name as the primary identity line.
- Show PR number as `PR #{number}`.
- Show `source_url` as a clickable external link if safe and valid.
- Do not show GitHub file lists, diff hunks, commits, findings, or drafts in Phase 1.

Run metadata contract:

- Show created timestamp.
- Show updated timestamp if available.
- Show lifecycle timestamps only if stored and non-null.
- Label metadata clearly; do not rely on table position alone.
- Missing optional timestamps should be omitted, not shown as noisy null values.

Failure contract:

- Render only safe summarized error text from the review run.
- Do not show raw submitted invalid URL attempts as failed runs; invalid URLs are form errors and should not persist records.
- The failed section must include what to do next: `Review the safe error summary, then create a new run after fixing the source issue.`

## Interaction States

### Loading and Submission

- On form submit, the primary button should enter a disabled/submitting state if JavaScript is present; without JavaScript, normal server submit is acceptable.
- Submitting copy may be `Creating...`.
- The disabled state must preserve the button's dimensions to avoid layout shift.
- Do not block the page with a modal or spinner overlay.

### Validation Error

- Required-field errors render under the PR URL field.
- Service errors render in a visible error block near the form with heading `Review run was not created`.
- Error blocks use error color for border/text accents only; keep background light and readable.
- Focus should return to the PR URL input after a validation failure when practical.
- Invalid submissions must not add rows to history.

### Empty History

- Empty history is rendered inside the history section, not as a separate page.
- Use the required empty state heading and body.
- The next step points back to the form above; do not add a second CTA that duplicates `Create Review Run`.

### Pending and Failed Status

- `pending` status must read as neutral and ready, not as an error or completed analysis.
- `failed` status must be visually distinct and paired with a safe error summary.
- Reserved statuses must not break layout when shown in tests or seeded data.
- Status labels should use the exact enum vocabulary in user-friendly title case: `Pending`, `Queued`, `Running`, `Completed`, `Failed`, `Cancelled`.

### Focus States

- Inputs, buttons, and links must have visible keyboard focus.
- Focus ring color uses accent `#0F766E`.
- Focus treatment must be visible against both white surfaces and the dominant page background.
- Do not remove browser focus outlines unless replaced with an equally visible Tailwind focus style.

### Responsive Behavior

- Mobile: single-column layout, full-width input/button, stacked history rows with labels.
- Tablet/desktop: dashboard may use a wider form row and table-like history, but form remains above history.
- No horizontal scrolling should be required for core content.
- Long repository names and URLs wrap or truncate safely.

## Registry Safety

No third-party registries. shadcn official: none. Safety gate: not required.

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| shadcn official | none | not required |
| third-party registries | none | not applicable |

## Checker Sign-Off

- [x] Dimension 1 Copywriting: PASS with non-blocking recommendation applied
- [x] Dimension 2 Visuals: PASS with non-blocking recommendation applied
- [x] Dimension 3 Color: PASS
- [x] Dimension 4 Typography: PASS
- [x] Dimension 5 Spacing: PASS
- [x] Dimension 6 Registry Safety: PASS

Approval: approved 2026-06-26.
