---
name: clear-user-messages
description: "Writes and audits user-facing messages -- error messages, success/confirmation messages, warnings, empty states, and notifications -- so they're plain, specific, and actually help the person using the product. Grounded in Nielsen Norman Group's error-message research and Google's UX writing guidance. Use whenever writing or reviewing any text a user reads in a UI: validation errors, form field errors, toast/snackbar notifications, alert dialogs, confirmation messages, empty-state copy, loading/status text, or destructive-action warnings, in any language or framework. Also trigger if the user pastes an existing error/success message and asks whether it's good, wants copy for a new feature, mentions users being confused by messages, or asks to clean up/improve messaging across a codebase."
---

# Clear User Messages

## Why this exists

A message a user reads at the exact moment something goes right or wrong carries more weight than almost any other text in a product — it's the one line standing between them and either finishing their task or giving up and contacting support. Most bad messages aren't bad because anyone tried to write a bad one; they're bad because they were the fastest thing to type while focused on the code path itself: `throw new Error("Invalid input")`, a generic `catch` block reusing an exception's message verbatim, a success toast that just says "Done." None of that is malicious, it's just what happens when message text is an afterthought to the logic around it.

The fix isn't a tone rule to memorize — it's a habit of asking, for every message: does this tell the person specifically what happened, in words they'd use themselves, and does it tell them what to do next? A message that fails either half of that is the one that turns a small hiccup into a support ticket.

## Before starting

Figure out whether this is a **writing** task (new messages for a feature being built) or an **audit** task (finding and fixing unclear messages already in a codebase) — often it's both, since writing one good error message tends to surface three bad ones sitting nearby in the same file. For an audit, search broadly rather than fixing the first thing found: grep for the patterns a codebase actually uses for user-facing text — `throw new Error(`, `.setError(`, `toast(`, `alert(`, `flash(`, form validation messages, API error response bodies that reach the frontend, catch blocks that surface `err.message` directly to a user. Not every string you find is user-facing — a message that only ever reaches a log file or a developer-facing API response has different rules (precision over friendliness, stack traces are fine); the guidance here is specifically for text a non-technical end user reads.

Identify which kind of message each one is — error, success/confirmation, warning (especially before a destructive or irreversible action), empty state, or ambient notification/status — since each has a different job and a different formula. Read `references/message-formulas.md` for the shape each type should take, and `references/voice-and-tone.md` for the language-level rules that apply across all of them. When auditing a larger set of existing messages, `references/audit-checklist.md` has a scoring rubric (adapted from Nielsen Norman Group's research) worth running each message against systematically rather than fixing by feel.

## The core test for any message

Before finalizing a message, check it against these, which cover most of what separates a message that helps from one that doesn't:

- **Specific, not generic.** "Something went wrong" or "An error occurred" tells the user nothing they didn't already know from the fact that something clearly isn't working. Say what actually happened: which field, which action, which limit was hit.
- **No blame, no jargon.** Words like "invalid," "illegal," "failed," or a raw exception class name put the fault on the user or expose implementation detail they can't act on. Describe the situation in plain language instead of the internal reason the system rejected it.
- **Tells them what to do next.** A description of the problem without a path forward leaves the user to guess. This is the single most commonly skipped piece — it's easy to state what's wrong and stop there.
- **Doesn't make them start over.** If the user filled out a nine-field form and one thing was wrong, the message (and the surrounding UI) shouldn't cost them the other eight fields.
- **Shows up where the problem is.** A validation error belongs next to the field it's about, not in a banner at the top of a long page the user has to hunt to connect back to the input.
- **Weight matches stakes.** A destructive, irreversible action earns a message that makes the consequence and the reversibility (or lack of it) unambiguous. A routine autosave doesn't need a modal.

## Writing new messages

Start from the formula for the message's type in `references/message-formulas.md` rather than free-writing — the formulas exist because each message type is solving a slightly different problem (an error needs a fix, a success needs either nothing or a next step, an empty state needs to explain potential without sounding like a button). Draft the message, then run it against the core test above and the voice rules in `references/voice-and-tone.md` before treating it as final. If the message reports on something technical (a failed API call, a validation rule, a background job), write the plain-language version for the user and, only if the underlying detail is genuinely useful to them, offer it as something they can expand into or a link to more detail — don't cram the technical explanation into the primary sentence they have to read every time.

## Auditing existing messages

Work through the strings found during the initial search one at a time, but look for patterns rather than treating each as an isolated fix — a codebase that surfaces `err.message` directly to users in one place is very likely doing it in several, and fixing the pattern (a shared error-formatting helper, a consistent toast component with a required "what to do" parameter) is worth more than patching each call site individually. Score a representative sample against `references/audit-checklist.md` before rewriting everything, both to calibrate what "good" means for this specific codebase's voice and to catch whether the problem is really about wording or about something structural (errors with nowhere sensible to display, or a design that only allows one line of text where the fix genuinely needs two).

## Wrapping up

Read the finished messages back as if seeing them for the first time, ideally out loud — stiffness and jargon are far easier to catch by ear than by eye. For an audit, summarize what was changed and, if a structural pattern came up repeatedly (the same generic catch-all message reused everywhere, or no shared place to standardize on), say so explicitly rather than only listing the individual strings that changed — that's usually the more valuable finding.
