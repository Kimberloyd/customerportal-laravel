---
name: form-validation-timing
description: "Use when building or reviewing form input validation (React forms, or any client-side validation) and the timing of error messages feels wrong -- validation that only runs on submit and dumps every error at once, validation that fires on every keystroke and flags a field as wrong before the user has finished typing it, a field that goes silent after passing validation instead of confirming it's correct, or a field that stays silent/still requires a full re-blur to re-validate after the user starts fixing an error. Applies a 3-part timing pattern: On Blur (validate when the user leaves a field, not on submit or every keystroke), Escalate to Live After an Error (once a field has shown an error, switch it to live per-keystroke validation so the fix is confirmed instantly), and Confirm on Success (show a positive indicator when a field passes, not just silence)."
---

# Form Validation Timing

Timing is the UX. The same validation logic feels either respectful or hostile depending on when it runs. Two default timings are both wrong in their own way, and there's a third rule for how a field should behave once it goes from valid to invalid state.

## When to apply this

- **Building** any form with field-level validation (signup, checkout, settings, any input with rules beyond "not empty").
- **Auditing** existing form code -- especially AI-generated forms, which commonly default to one of two easy-but-wrong timings: validate everything on submit, or validate on every keystroke via a naive `onChange` handler.

## The 2 anti-patterns

**On Submit ("errors all at once")**: the form collects input silently and only validates when the user clicks Submit, then dumps every error on screen simultaneously. The user gets no feedback while filling the form, then is confronted with a wall of red at the worst possible moment -- after they thought they were done.

**On Keystroke ("wrong before they finish")**: the form validates on every `onChange`, so typing "a", "al", "ale" into an email field shows "Invalid email" after each character, because none of the partial input is a valid email yet. Technically correct, but the message is premature and reads as hostile -- the user hasn't finished typing.

## The 3-part timing pattern

### 1. On Blur -- validate when they leave the field

Validate a field when it loses focus (blur), not while the user is actively typing into it and not only at form submit. This gives the user uninterrupted room to type, and catches problems before they've moved on to fill out the rest of the form (rather than all at once at the end).

```jsx
function EmailField() {
  const [value, setValue] = useState('')
  const [touched, setTouched] = useState(false)
  const [error, setError] = useState(null)

  function validate(v) {
    return /\S+@\S+\.\S+/.test(v) ? null : 'Invalid email'
  }

  function handleBlur() {
    setTouched(true)
    setError(validate(value))
  }

  return (
    <div>
      <label>Email</label>
      <input
        value={value}
        onChange={(e) => setValue(e.target.value)}
        onBlur={handleBlur}
        aria-invalid={touched && !!error}
      />
      {touched && error && <p className="text-red-400 text-sm">{error}</p>}
    </div>
  )
}
```

### 2. Escalate to Live -- after an error, validate every keystroke until it's fixed

Once a field has shown an error, switch that field into live (per-keystroke) validation mode until it passes. The user is now actively trying to fix a known problem -- making them blur away and back again to find out if they succeeded adds friction and delay right when they want the fastest possible feedback loop. Before an error has occurred, stay on the calmer on-blur timing from rule 1.

```jsx
function EmailField() {
  const [value, setValue] = useState('')
  const [touched, setTouched] = useState(false)
  const [error, setError] = useState(null)
  const [hasErrored, setHasErrored] = useState(false) // once true, stay in live mode for this field

  function validate(v) {
    return /\S+@\S+\.\S+/.test(v) ? null : 'Invalid email'
  }

  function handleChange(e) {
    const v = e.target.value
    setValue(v)
    if (hasErrored) {
      // live validation once this field has already shown an error once
      setError(validate(v))
    }
  }

  function handleBlur() {
    setTouched(true)
    const err = validate(value)
    setError(err)
    if (err) setHasErrored(true)
  }

  return (
    <div>
      <label>Email</label>
      <input
        value={value}
        onChange={handleChange}
        onBlur={handleBlur}
        aria-invalid={touched && !!error}
      />
      {touched && error && <p className="text-red-400 text-sm">{error}</p>}
    </div>
  )
}
```

### 3. Confirm on Success -- don't just go silent

When a field passes validation, show a positive indicator (a checkmark, "Looks good", a green border) rather than simply removing the error and going silent. Silence is ambiguous -- the user can't tell whether the field is fine or simply hasn't been checked yet, and it reads as withholding judgment rather than confirming success.

```jsx
function EmailField() {
  const [value, setValue] = useState('')
  const [touched, setTouched] = useState(false)
  const [error, setError] = useState(null)
  const [hasErrored, setHasErrored] = useState(false)

  function validate(v) {
    return /\S+@\S+\.\S+/.test(v) ? null : 'Invalid email'
  }

  function handleChange(e) {
    const v = e.target.value
    setValue(v)
    if (hasErrored) setError(validate(v))
  }

  function handleBlur() {
    setTouched(true)
    const err = validate(value)
    setError(err)
    if (err) setHasErrored(true)
  }

  const isValid = touched && !error && value.length > 0

  return (
    <div>
      <label>Email</label>
      <div className="relative">
        <input
          value={value}
          onChange={handleChange}
          onBlur={handleBlur}
          aria-invalid={touched && !!error}
          className={isValid ? 'border-emerald-500' : touched && error ? 'border-red-500' : ''}
        />
        {isValid && <CheckIcon className="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-500" />}
      </div>
      {touched && error && <p className="text-red-400 text-sm">{error}</p>}
      {isValid && <p className="text-emerald-400 text-sm">Looks good</p>}
    </div>
  )
}
```

## Putting it together

A field's validation lifecycle: stay quiet while the user types (no validation on keystroke) -> validate on blur -> if it passes, show a positive confirmation (rule 3) -> if it fails, show the error and switch that field into live validation (rule 2) -> as the user corrects it, validate every keystroke and flip to the success confirmation the moment it passes.

On submit, still validate every field (in case some were never touched/blurred), but a form that followed rules 1-3 throughout should rarely surprise the user with a wall of new errors at that point -- most fields will already show either an error or a confirmation.

## Auditing existing code

Check each form field's validation wiring: does it validate on every keystroke unconditionally (rule 1 violation -- premature errors)? Does it validate only on submit with no per-field feedback until then (rule 1 violation -- errors all at once)? Once a field has shown an error, does correcting it require blurring away and back to find out if it's fixed, instead of validating live (rule 2 violation)? Does a passing field just clear its error with no positive confirmation (rule 3 violation)? Report each violation with the specific handler/code involved and the fix, then implement the fixes.

## References

- `references/implementation-guide.md` -- a complete reusable field-validation hook/pattern in React, plus a Laravel-side API error-shape note for server-side validation.
- `references/audit-checklist.md` -- a rule-by-rule checklist for reviewing existing form code.
