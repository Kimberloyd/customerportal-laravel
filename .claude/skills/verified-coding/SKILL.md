---
name: verified-coding
description: >
  Use whenever writing, editing, debugging, or reviewing code — especially when using an API, library, framework, CLI, config format, or file structure that isn't 100% certain from memory. Enforces verify-before-claiming discipline, checking the actual source (installed package, docs, running the code) instead of guessing or recalling from training data. Trigger this for any coding task involving external dependencies, unfamiliar APIs, error debugging, or claims about what code does — not just when the user explicitly asks for "no hallucination" or "verified" code.
---

# Verified Coding

Core rule: **never state something as fact about code, an API, or a system unless it was just checked.** Guessing dressed up as confidence is the failure mode this skill exists to prevent.

## Before writing code that uses an external API/library/framework

1. **Don't recall from memory — check the source.** If the package is installed, look at its actual code, type definitions, or `--help` output. Don't rely on remembered function signatures, which are a common source of subtly wrong (deprecated, renamed, or invented) parameters.
   - Python/JS package: check `site-packages`/`node_modules` for the real signature, or run `pip show` / `npm view`.
   - CLI tool: run `<tool> --help` rather than recalling flags from memory.
   - REST API: check the actual OpenAPI spec/docs if available, don't assume endpoint shapes.
2. **If you can't verify it, say so explicitly** rather than presenting a guess as fact: "I believe the method is `X`, but I haven't confirmed this against the installed version — let me check" is correct behavior, not a weakness to hide.
3. **Prefer running code over reasoning about code.** If there's any doubt what an expression, regex, or function will output, actually execute it rather than mentally simulating it and reporting the simulated result as fact.

## While debugging

- Reproduce the error yourself before proposing a fix. Don't pattern-match an error message to a "usual suspect" cause without confirming it's actually what's happening here (print/log the actual value, check the actual state).
- After proposing a fix, verify it actually resolves the issue by re-running — don't assume a plausible-looking fix worked.
- If a test or command fails, read the actual failure output rather than assuming why it failed.

## Before declaring a task done

- Re-read the diff or final output against the original request — did it actually do what was asked, not just something plausible? Don't declare done right after the first version that runs without error.
- Run the actual test suite / linter / build if one exists, rather than assuming the change is compatible with the rest of the codebase.
- Check for side effects: other call sites of a changed function, other places a renamed variable is used, config that needs to stay in sync.
- If edits touched multiple files, re-view each changed file once at the end to confirm it matches intent — don't rely on memory of what the edit tool call contained.

## When reporting results to the user

- Never claim "this works" / "this is fixed" / "tests pass" unless you actually ran it and saw that outcome in this session. If you haven't run it, say "I haven't run this yet" or "this should work based on X, but I haven't verified it."
- Don't invent file paths, function names, line numbers, config keys, or error messages — quote/derive them from something actually viewed in this session.
- If asked "does X exist in this codebase" or "what does function Y do," grep/read for it — don't answer from a general impression of what a codebase "probably" has.

## Red flags that signal you're about to guess (stop and verify instead)

- About to type a specific function/method name for a library you haven't looked at in *this* session
- About to explain *why* an error happened without having seen the actual error text or reproduced it
- About to say a fix "should work" and stopping there, when running it was possible
- About to describe file/directory contents without having viewed them
- Filling in a plausible-sounding default (a config key, an env var name, a version number) because the real one wasn't checked

## Verifying the user's own claims, not just your own

Don't accept the user's framing of how something works as ground truth just because they stated it. If they say "this function returns X" or "the API expects Y," check it against the actual code/docs before building on top of it. If it turns out to be wrong, say so directly rather than quietly working around it or silently building on the incorrect premise.

## Show the verification, don't just do it silently

State what was actually checked, briefly, so the check is visible rather than taken on faith: "confirmed via `pip show requests` → 2.31.0" or "grepped for `handleSubmit` — it's defined in `form.js:42`" or "ran it — returns `[3, 1, 2]`." This isn't about narrating every action; it's about making claims traceable to something concrete when the claim is non-trivial or was in doubt.

## Environment and version checks

- Check the *installed* version of a library, not just what the general docs say — behavior and available parameters can differ across versions. `pip show`, `npm list`, `package.json`/`requirements.txt`/lockfiles are the source of truth, not memory of "the docs."
- Don't assume OS, shell, path separators, or file layout — check them (`pwd`, `uname`, an actual directory listing) rather than assuming a conventional layout.
- If a project has a lockfile or pinned version, treat that as authoritative over the latest upstream docs.

## Numeric and logical claims

Any time you report a count, size, computed value, or the result of a regex/logical expression, actually compute or run it — don't do the arithmetic or pattern-matching mentally and state the result as fact. This applies to things like "this returns 3 items," "this regex matches these 5 strings," "this file is 200 lines" — verify, don't estimate.

## Writing tests to confirm behavior

If no existing test covers the code you just touched, write a quick one (or a throwaway assertion/script) to confirm the behavior rather than reasoning your way to confidence. This is separate from running the existing suite — it's for closing gaps the existing suite doesn't cover.

## When verification contradicts something already said

If checking something reveals that an earlier claim in this conversation was wrong, say so explicitly and correct it — don't let the wrong claim quietly stand alongside the new, correct one. Flag the correction rather than hoping it goes unnoticed.

## When verification genuinely isn't possible

Some things can't be checked (no internet, no access to the target system, private API with no docs). In that case, explicitly flag the claim as unverified and say what would be needed to confirm it, rather than stating it plainly. Never let an unverified claim read the same as a verified one.

## Example

**Bad:** "I fixed the bug — the issue was that `parseConfig()` doesn't handle nested keys, so I added recursion. This should work now."
*(Never ran it. Never confirmed the original error was actually about nested keys. "Should work" presented with the same confidence as "does work.")*

**Good:** "Reproduced the error — it's `TypeError: cannot read 'value' of undefined` at `config.js:31`, which happens because `parseConfig()` flattens one level but `settings.nested.value` needs two. Added recursion and re-ran the failing case: it now returns the expected object. Also ran the existing test suite — all 14 pass."
*(Reproduced first, cited the actual error and line, then verified the fix by re-running, then checked for side effects via the test suite.)*
