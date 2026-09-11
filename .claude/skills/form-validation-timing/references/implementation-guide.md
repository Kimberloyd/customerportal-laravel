# Form Validation Timing -- Implementation Guide

A reusable pattern for the full 3-rule lifecycle, plus notes on wiring it to a Laravel API's validation errors.

## Reusable `useFieldValidation` hook

```jsx
import { useState, useCallback } from 'react'

function useFieldValidation(validate) {
  const [value, setValue] = useState('')
  const [touched, setTouched] = useState(false)
  const [error, setError] = useState(null)
  const [hasErrored, setHasErrored] = useState(false)

  const onChange = useCallback((e) => {
    const v = e.target.value
    setValue(v)
    // Rule 2: once this field has errored before, validate live as they type
    if (hasErrored) {
      setError(validate(v))
    }
  }, [hasErrored, validate])

  const onBlur = useCallback(() => {
    setTouched(true)
    const err = validate(value)
    setError(err)
    if (err) setHasErrored(true) // Rule 2: escalate to live mode from here on
  }, [value, validate])

  const isValid = touched && !error && value.length > 0

  return { value, setValue, touched, error, isValid, onChange, onBlur }
}

// Usage
function SignupForm() {
  const email = useFieldValidation((v) =>
    /\S+@\S+\.\S+/.test(v) ? null : 'Invalid email'
  )
  const password = useFieldValidation((v) =>
    v.length >= 8 ? null : 'Must be at least 8 characters'
  )

  return (
    <form>
      <Field label="Email" {...email} />
      <Field label="Password" type="password" {...password} />
    </form>
  )
}

function Field({ label, value, onChange, onBlur, touched, error, isValid, type = 'text' }) {
  return (
    <div>
      <label>{label}</label>
      <div className="relative">
        <input
          type={type}
          value={value}
          onChange={onChange}
          onBlur={onBlur}
          aria-invalid={touched && !!error}
          className={
            isValid
              ? 'border-emerald-500'
              : touched && error
              ? 'border-red-500'
              : 'border-slate-700'
          }
        />
        {isValid && <span className="absolute right-3 top-1/2 -translate-y-1/2 text-emerald-500">✓</span>}
      </div>
      {touched && error && <p className="text-red-400 text-sm mt-1">{error}</p>}
      {isValid && <p className="text-emerald-400 text-sm mt-1">Looks good</p>}
    </div>
  )
}
```

## Submit-time validation (safety net, not the primary mechanism)

Still validate everything on submit -- some fields may never have been blurred (e.g. the user tabbed past a field without typing, or pasted a whole form's worth of data). But with rules 1-3 applied throughout, most fields will already carry either an error or a confirmation by the time the user submits, so submit-time validation should rarely surface *new* information.

```jsx
function handleSubmit(e) {
  e.preventDefault()
  const emailError = validateEmail(email.value)
  const passwordError = validatePassword(password.value)
  if (emailError || passwordError) {
    // surface any fields that were never touched
    email.forceValidate?.()
    password.forceValidate?.()
    return
  }
  // submit
}
```

## Server-side validation (Laravel)

Client-side rules 1-3 handle format/presence checks the browser can verify instantly (email shape, min length, required). Checks that need the server (email already taken, coupon code invalid) still only resolve on submit or blur-triggered async calls -- apply the same principles there:

```php
// Laravel: return field-keyed errors, not one flat message
public function store(Request $request)
{
    $validated = $request->validate([
        'email' => 'required|email|unique:users,email',
        'password' => 'required|min:8',
    ]);
    // ...
}
```

Laravel's default validation exception response already shapes errors per-field (`{"errors": {"email": ["The email has already been taken."]}}`), which maps directly onto the same per-field error/confirmation UI used for client-side checks -- so an async "check availability on blur" call for the email field can reuse the exact same `error`/`isValid` state shape as the synchronous rules.

```jsx
async function checkEmailAvailable(email) {
  const res = await fetch('/api/check-email', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email }),
  })
  const data = await res.json()
  return data.available ? null : 'Email already in use'
}

function handleBlur() {
  setTouched(true)
  const syncError = validate(value)
  if (syncError) {
    setError(syncError)
    setHasErrored(true)
    return
  }
  // async check only runs once the sync format check already passed
  checkEmailAvailable(value).then((err) => {
    setError(err)
    if (err) setHasErrored(true)
  })
}
```
