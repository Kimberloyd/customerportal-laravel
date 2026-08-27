# Validation Checkpoints

## Why the agent can't grade its own chunk

An agent asked "did that chunk go well?" will very often say yes -- not out of dishonesty, but because self-assessment from inside the same context that produced the work is a weak check. The whole point of a checkpoint is an independent look, and the most reliable independent look available is the human who actually knows what "correct" means for this specific job.

## What a good checkpoint summary contains

A checkpoint isn't "I finished, moving on" -- it's a concrete, reviewable artifact:

- **What changed**: specific, not vague -- file paths touched, decisions made, concrete before/after where relevant. Not "updated the backend."
- **What's still open**: anything flagged in the state file's Open Questions, or anything the chunk didn't fully resolve.
- **What the next chunk will do**: so the reviewer can catch a wrong direction before it's acted on, not after.
- **Anything that needs an explicit decision**: don't bury a real choice inside a summary as if it were already settled -- surface it as a question.

A wall of text isn't a checkpoint either -- the goal is something a reviewer can actually evaluate in the time they're willing to spend, which usually means being concise and concrete rather than exhaustive.

## When to pause vs. when to proceed autonomously

Not every step needs a human gate -- that would defeat the purpose of automation entirely. The checkpoint sits at chunk boundaries (see `chunking-strategy.md`), not after every individual step. Within a chunk, the agent works autonomously; between chunks, it stops.

Judgment calls on tightening or loosening this:
- Higher-stakes or harder-to-reverse work (schema changes, anything touching production, anything expensive to redo) warrants smaller chunks and tighter checkpoints.
- Low-stakes, easily-verified, easily-reversible work (a chunk of pure formatting fixes, for instance) can run with a lighter-touch checkpoint -- but "no checkpoint at all for a 30-step job" is the exact failure mode this pattern exists to prevent.

## Implementing the pause

In an interactive session, the checkpoint is a real stop: present the summary, then wait for an actual go-ahead rather than proceeding immediately regardless of response. In an automated pipeline (Mode 2), the checkpoint is an actual gate in the code -- the next chunk's job doesn't get dispatched until an approval signal (a human clicking approve, a webhook, a review step passing) is recorded, not just logged for someone to look at eventually.

## Common failure modes

- **The checkpoint that isn't one.** Presenting a summary and then immediately continuing regardless of any response isn't a checkpoint, it's a status update.
- **Checkpoint fatigue from too much granularity.** Checkpointing after every single step (rather than every chunk) trains the reviewer to skim and rubber-stamp, which defeats the purpose just as thoroughly as no checkpoint at all.
- **Summaries that assert quality instead of showing it.** "This chunk went well" is not reviewable; the specific diffs/decisions/outputs are.
