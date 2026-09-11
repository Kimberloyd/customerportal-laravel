# Form Validation Timing Audit Checklist

Walk every validated field in the form(s) under review against these three checks. For each hit, cite the actual handler/code and the specific fix.

## 1. On Blur (not on-keystroke, not on-submit-only)
- [ ] Does the field validate on every `onChange`/keystroke unconditionally, before the user has finished typing? Flag it -- this fires premature errors (e.g. "Invalid email" after typing "al").
- [ ] Does the field have no validation at all until form submit, with no per-field feedback while filling out the form? Flag it -- this dumps every error at once at the worst time.
- [ ] Is there an `onBlur` handler (or equivalent focus-loss check) that validates the field once the user leaves it? This is the expected default state.

## 2. Escalate to Live After an Error
- [ ] Once a field has shown an error, does fixing it require blurring away and back to find out whether it's now valid, or does it re-validate live as the user types the correction?
- [ ] Look for a per-field "has errored" flag (or equivalent) that switches the field's `onChange` handler into validating mode. Its absence means the field always waits for the next blur, even mid-correction -- flag it.

## 3. Confirm on Success
- [ ] When a field passes validation, does the UI show any positive indicator (checkmark, success message, colored border), or does it just clear the error and go silent?
- [ ] Flag any field where a passing state is visually indistinguishable from an unvalidated/untouched state.

## Reporting format

For each violation found, state: the field/file, the specific handler or code involved (e.g. "`onChange={(e) => validate(e.target.value)}` in `EmailField.jsx` fires validation on every keystroke with no gating"), which rule it violates, and the concrete fix. Then implement the fix, don't just report it.
