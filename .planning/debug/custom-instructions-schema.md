---
status: resolved
trigger: "Run AI Review fails with invalid schema after adding Custom Review Instructions: 請用繁體中文回覆我"
created: 2026-07-01
updated: 2026-07-01
---

# Debug Session: custom-instructions-schema

## Symptoms

- Expected behavior: Run AI Review should accept custom review instructions and still persist validated findings.
- Actual behavior: Review run fails with safe message `AI provider returned an unexpected review format. Try running the review again.`
- Error messages: UI shows only the safe schema failure summary.
- Timeline: Started after adding Custom Review Instructions requesting Traditional Chinese output.
- Reproduction: Save `請用繁體中文回覆我`, then run AI review through the management UI.

## Current Focus

- hypothesis: Custom instructions are appended after the output contract and can cause the model to translate JSON keys or enum values.
- test: Strengthen instruction composition so custom instructions affect natural-language finding text only, then preserve strict output contract at the end.
- expecting: Prompt builder tests should prove the final output contract follows custom instructions and explicitly forbids changing JSON keys/enums.
- next_action: Restart queue worker and rerun AI review from the UI.

## Evidence

- timestamp: 2026-07-01
  observation: Database contains global custom instructions `請用繁體中文回覆我`.
- timestamp: 2026-07-01
  observation: Latest review run failed with safe schema summary after queue worker was restarted with the current validator code.

## Eliminated

- hypothesis: Queue worker was still using the old validator.
  reason: Worker start time was after the validator file modification time.

## Resolution

- root_cause: Custom instructions were appended after the JSON output contract, so broad language instructions could influence schema keys or enum labels.
- fix: `ReviewInstructionBuilder` now appends an output contract reminder after custom instructions, limiting custom language/focus changes to natural-language finding fields while preserving JSON keys, required fields, and severity/category labels.
- verification: Related tests passed with 26 tests and 285 assertions. Full suite passed with 137 tests and 917 assertions.
- files_changed: app/Services/AI/ReviewInstructionBuilder.php, tests/Unit/AI/ReviewInstructionBuilderTest.php
