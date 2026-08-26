# Message formulas by type

Each message type is doing a different job. Using the right shape for the job is most of what makes a message feel right without having to fuss over individual word choices.

## Error messages

**Shape:** What happened (specific to this situation) + why, only if it helps them fix it + what to do next.

The "why" clause is optional and should be cut if it doesn't change what the user does — "Your password must be at least 8 characters" doesn't need a clause explaining that short passwords are less secure; the user doesn't need to be convinced, they need to know the rule.

| Bad | Better | Why |
|---|---|---|
| "An error occurred." | "We couldn't save your changes because the connection dropped. Check your internet and try again." | Names what happened and gives a concrete next step, not just an acknowledgment something broke. |
| "Invalid input." | "Enter a valid email address, like name@example.com." | Drops the blaming "invalid," shows the expected format instead of just naming the rule. |
| "Error 500: NullPointerException at OrderService.java:142" | "Something went wrong on our end while placing your order. Your cart is saved — try again in a moment, or contact support if it keeps happening." | Technical detail moved out of the primary message (kept in logs/an expandable "details" link if genuinely useful); reassures the user's work isn't lost; gives a real next step and an escape hatch. |
| "You must enter a valid phone number." | "Enter your phone number as 10 digits, like 5551234567." | Replaces "valid" (which restates the problem without describing it) with the actual expected format. |
| "Failed to upload file." | "This file is too large to upload (12 MB — the limit is 10 MB). Try compressing it or choosing a smaller file." | States the specific reason (not just "failed") and gives two concrete paths forward. |

For a **field-level validation error**, keep it to the field itself and its rule — don't repeat the field name back in a full sentence if the field's label already makes that clear from position: "Must be at least 8 characters" next to a labeled Password field, not "Your password must be at least 8 characters" positioned right under a field labeled "Password."

For a **system/network error** the user can't personally fix (a downed service, a timeout), be honest that it's not their fault, tell them the practical next step (retry, wait, contact support), and say what happens to their in-progress work (saved as a draft? lost? still processing?) — that last part is the difference between mild annoyance and real anxiety.

## Success / confirmation messages

**Shape:** Confirm what happened + a next step, if there's a natural one — otherwise nothing more is needed.

Success messages are the easiest type to overdo. A routine, frequent action (autosave, marking a to-do complete, adding an item to a list) needs at most a brief, low-key acknowledgment — a checkmark, a subtle inline change, a toast that fades on its own. Reserve a more prominent confirmation (a dedicated screen, a modal, an email) for actions that are infrequent, high-stakes, or the user was clearly anxious about (submitting a job application, completing a payment, sending an important message).

| Situation | Message | Why |
|---|---|---|
| Routine save | (a subtle inline "Saved" indicator, no modal) | Doesn't interrupt for something the user does constantly. |
| Account created | "Your account is ready. Check your email to verify your address before you sign in." | Confirms success and states the next required action — without it, the user doesn't know verification is needed. |
| Payment submitted | "Payment received — order #48213. We'll email you a receipt and tracking info once it ships." | High-stakes action gets a specific confirmation (order number) plus what happens next, reducing the urge to immediately check "did that actually go through." |
| Item deleted | "Message deleted. Undo" | Confirms the action and offers a way to reverse it when the action is otherwise hard to recover from — this is often better than a "are you sure?" warning beforehand, since it doesn't slow down the common case where the user meant it. |

Don't let a success message just restate the button the user already clicked ("Saved" after clicking "Save" is fine and minimal; "You have successfully completed the save operation" is not — the ceremony doesn't add information).

## Warnings before destructive or irreversible actions

**Shape:** What will happen, stated plainly + whether it can be undone + two clearly labeled actions (not generic OK/Cancel).

| Bad | Better | Why |
|---|---|---|
| "Are you sure?" / [OK] [Cancel] | "Delete this project? This removes all 14 files in it and can't be undone." / [Delete project] [Keep project] | States the actual consequence and scope (14 files, not just "this"), says explicitly it's permanent, and labels each button with the action it takes rather than a generic yes/no that requires re-reading the question to know which is which. |
| "This will delete your data." | "This deletes your last 30 days of activity history. Your account and current settings aren't affected." | Scopes precisely what is and isn't affected — vague warnings make people either over-cautious about safe actions or under-cautious about real ones. |

If the action is actually reversible (soft-delete with an undo window, a trash/recycle bin), say so — a warning that overstates permanence trains people to distrust future warnings, including the ones that matter.

## Empty states

**Shape:** What this space is for (not a restated screen title) + how to get started, as a separate action element — not as text that reads like a clickable instruction itself.

| Bad | Better | Why |
|---|---|---|
| "No items." | "Nothing here yet. Projects you create will show up on this page." + a "Create your first project" button | Explains the space's purpose instead of just stating absence, and puts the action in an actual button rather than implying the text itself is clickable. |
| "You have no notifications. Click here to enable notifications." | "You're all caught up. New activity on your projects will appear here." | The first version reads as if the empty state itself is at fault and pushes an unrelated settings action into text that looks like a description; the second describes the state accurately without manufacturing a call to action that doesn't belong there. |

## Ambient notifications / status text

**Shape:** Present tense, minimal, states current state rather than a completed sentence — "Saving…", "3 people online", "Last synced 2 minutes ago."

These are read peripherally, not focused on — keep them short enough to be absorbed at a glance, and update them in place rather than stacking new ones, unless each one represents genuinely distinct information the user needs to see individually (a chat app's incoming messages vs. a single "typing…" indicator that should update rather than repeat).
