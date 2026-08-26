# Voice and tone rules

These apply across every message type — the formulas in `message-formulas.md` shape *what* a message says; this is about *how* it says it.

## Plain language

Aim for roughly a 7th-8th grade reading level (the level newspapers and most consumer software target) — not because users can't handle more, but because a message is read in a moment of friction or urgency, not leisure, and simple wording is processed faster under that kind of load. A quick gut check: would you say this sentence out loud to a coworker who isn't technical, in these exact words? If the honest answer involves you translating it first, that's the rewrite.

Concrete swaps:

| Instead of | Use |
|---|---|
| "Invalid" / "Illegal" | Describe the actual expected format ("Enter a date like MM/DD/YYYY") |
| "Failed to..." | "We couldn't..." (same fact, without a tone that implies fault) |
| "Utilize" | "Use" |
| "Authenticate" | "Sign in" |
| "Terminate" | "End" / "Cancel" |
| "Insufficient" | "Not enough" |
| "Prior to" | "Before" |
| "In order to" | "To" |
| A raw exception name or error code as the primary message | The plain-language situation, with the technical detail available separately (a "details" expander, a link, or a support-reference code) if it's genuinely useful to include |

## No blame

The system is talking to the user, not accusing them. "You entered an invalid password" implies the user did something wrong; "That password doesn't match" or "Enter a valid password" describes a fact without assigning fault. This matters even when the user genuinely did make a mistake — the message's job is to get them to a fixed state quickly, not to establish whose fault it was.

Avoid, or use carefully:
- "You must..." / "You failed to..." — reads as a command or an accusation. "Enter your email to continue" does the same job without either.
- "Please" used to soften a command doesn't fix an underlying blaming tone — "Please enter a valid email" is still blaming, just more politely.
- Exclamation points on errors — they read as alarmed or annoyed rather than helpful, even when the underlying words are fine.

## Skip humor in errors

A joke in an error message is charming exactly once — by the fifth time a user hits the same "Oops! Looks like our hamsters fell off the wheel!" message, it reads as flippant about something actually blocking their work. This is a specific, well-documented finding (Nielsen Norman Group's error-message research), not a matter of taste — humor is fine in onboarding, empty states, and other low-stakes moments, but avoid it specifically in error and warning copy where the user is already mildly frustrated.

## Consistency

Pick one term per concept and use it everywhere in the product — if the UI calls it a "Project" on the dashboard, don't call it a "Workspace" in an error message about the same object. Inconsistent terminology forces the user to do translation work at the exact moment they're least equipped for it. When auditing an existing codebase, a quick scan for synonyms referring to the same entity (delete/remove/trash, sign in/log in, project/workspace) across different message call sites is worth doing before rewriting wording — fixing tone on a message that uses the wrong name for something is a half-fix.

## When the audience isn't the end user

Not everything that looks like a message is meant for the person using the UI. Logs, stack traces, and API error responses meant for another developer integrating against the API have different, legitimate conventions — precision and machine-parseability matter more than warmth there, and a raw error code or exception type is often exactly right. The rules in this skill are for text a non-technical end user reads inside the product; don't "soften" a developer-facing API error response into vague friendliness — that makes it worse for its actual audience. If a single underlying error needs to reach both audiences (a user-facing toast and a developer-facing API log), write two different messages rather than compromising on one that serves neither well.
