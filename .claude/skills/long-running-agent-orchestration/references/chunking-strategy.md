# Chunking Strategy

## Why 5-7 steps

It's a rule of thumb, not a hard law: small enough that a single chunk's context stays focused and coherent, large enough that chunk boundaries don't become pure overhead (a chunk of 1-2 steps spends more effort on hand-off than on work). Adjust by task complexity -- a chunk of 7 simple, mechanical steps is fine; a chunk of 7 steps each requiring significant judgment calls should probably be smaller.

## How to find real chunk boundaries

Don't chunk by an arbitrary step count alone -- chunk at natural dependency breaks:

- A chunk ends where its output becomes a stable input for the next phase (e.g. "schema design" finishes before "implement the migrations that use it" starts).
- A chunk ends before a decision that would be expensive to reverse (a chunk boundary before "pick the auth strategy" lets that decision get reviewed before code is built on top of it).
- A chunk ends at a natural verification point -- somewhere a human can meaningfully assess whether the work so far is on track, not mid-way through an interdependent change that's hard to evaluate half-finished.

A 30-step job rarely decomposes into six clean chunks of exactly five identical-shaped steps. It's fine for chunks to vary in size as long as each one is a coherent, independently-reviewable unit of work.

## What crosses the chunk boundary

Only the state file (see `state-file-design.md`) and whatever concrete artifacts the next chunk needs (file paths, not full file contents re-pasted; a decision, not the entire debate that led to it) should cross into the next chunk's context. The point of chunking is to *not* carry the accumulated conversational weight of every prior chunk forward -- if the next chunk's context ends up reconstructing the full history anyway, the chunking isn't doing its job.

## Self-orchestration in a single session

When Claude is chunking its own work within one ongoing session (rather than literally starting new conversations/subagent calls per chunk), the discipline still matters even though the underlying context is technically continuous: explicitly complete a chunk, update the state file/task list, and pause for review before starting the next chunk's work, rather than treating the whole job as one uninterrupted stream of tool calls. The chunk boundary is a behavioral discipline (stop, summarize, get confirmation) even when it isn't a literal new-session boundary.

When chunk isolation needs to be real (a long job where drift risk is high, or a task decomposed across subagents), prefer actually spawning a fresh subagent per chunk, handing it the state file and the specific chunk's scope rather than the full prior transcript.

## Anti-patterns

- **Front-loading everything into chunk 1's context "just in case."** Defeats the purpose -- pass what's needed for the chunk at hand, not the whole project's history preemptively.
- **Letting a chunk balloon past its boundary because "it's almost done."** This is exactly the drift the pattern is meant to prevent; if a chunk is running long, that's a signal to checkpoint now rather than push through.
- **Treating chunk size as fixed regardless of task type.** A chunk of five trivial config edits is not equivalent to a chunk of five architecture decisions -- calibrate.
