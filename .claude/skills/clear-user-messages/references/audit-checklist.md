# Audit checklist

Adapted from Nielsen Norman Group's error-message research, which scores messages across three dimensions. It's built around error messages specifically, but the same three angles (can the user see it, can they understand it, can they act on it) work as a lens on success, warning, and empty-state messages too — apply what's relevant to the type.

Score each message you're auditing against these, thinking in terms of "does this hold up" rather than a precise numeric score — the value is in systematically checking every angle, not in the arithmetic.

## Visibility — can the user actually see and locate it?

- Does it appear next to the thing it's about (the field, the button, the row), not in a disconnected banner the user has to mentally reconnect to the cause?
- Is it visually distinct enough to notice — color plus at least one other cue (an icon, a border, bold text), since color alone fails for colorblind users and anyone glancing quickly?
- Does its visual weight match its importance — a blocking modal for something that stops the user cold, a lighter inline or toast treatment for something minor?
- Does it appear at the right moment — after the user has actually done something wrong, not the instant they start typing into a field (premature validation reads as impatient and interrupts before the user has finished expressing intent)?

## Communication — can the user understand it?

- Plain language, no unexplained jargon or raw technical identifiers as the primary text?
- Specific to this exact situation, not a generic catch-all ("An error occurred")?
- Frontloaded — does the important part come first, rather than making the user read a long sentence to get to the actual point?
- Neutral or positive tone, no blame, no humor (in error/warning contexts specifically)?

## Efficiency — can the user actually resolve it, with minimal extra effort?

- Does it say what to do next, not just what went wrong?
- Is the fix it suggests actually the easiest available path, not just *a* path? (If there's a one-click fix available, offering only a manual workaround is a miss.)
- Did the user keep their other work/input, or does fixing this one thing cost them everything else they'd already done?
- If the issue is complex enough to need more than a sentence, is there a link or expandable detail rather than a wall of text crammed into the primary message?

## Applying this at scale

When auditing many messages at once, this checklist is more useful as a way to spot *patterns* than as a per-message scorecard: if the same weak point (say, every error is visually identical regardless of severity, or nothing anywhere offers a next step) shows up across a dozen messages, that's a signal to fix the shared component or convention generating them, not to hand-edit each string individually. Call out that kind of structural finding explicitly when reporting results — it's usually worth more than the list of individual rewrites.
