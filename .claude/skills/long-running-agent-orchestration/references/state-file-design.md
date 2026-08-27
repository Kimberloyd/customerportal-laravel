# State File Design

The state file is the agent's substitute for memory it doesn't natively have across a chunked, multi-session job. It needs to be structured enough that a fresh context can pick up exactly where the last one left off, and honest enough that it doesn't paper over problems.

## Minimum sections

1. **Completed** -- what's actually done and verified, not just attempted. Be specific (file paths, function names, decisions locked in), not "did the backend stuff."
2. **In progress** -- exactly what the current/next chunk is working on, narrow enough that picking it up doesn't require re-deriving scope from scratch.
3. **Constraints** -- anything a later chunk must not violate: an API contract already committed to, a naming convention already established, a performance budget, a decision the user explicitly made that shouldn't be silently revisited.
4. **Decisions and rationale** -- not just *what* was decided but *why*, so a later chunk doesn't "fix" something that was a deliberate tradeoff. A one-line reason is usually enough: "Used polling instead of websockets -- infra doesn't support persistent connections yet."
5. **Open questions / risks** -- anything flagged but not yet resolved, so it doesn't silently drop off the radar between chunks.

## Format

Markdown is usually the right choice for a human-and-agent-readable file that also gets shown to the user at checkpoints. A JSON/YAML schema is worth it only when the state needs to be machine-processed by other code (a real orchestration pipeline), not for a single long agentic session.

```markdown
# Project State: <name>

## Completed
- [x] Step 1: <what, where, verified how>
- [x] Step 2: <...>

## In Progress
- [ ] Step 3: <specific current focus>

## Constraints
- <thing that must not be violated, and why>

## Decisions
- <decision> -- <one-line rationale>

## Open Questions
- <anything unresolved that a later chunk needs to address>
```

## Update discipline

Update the file at the end of every step, not just at chunk boundaries -- if a chunk gets interrupted mid-way (session limit, error, user stepping away), the state file should still reflect real progress, not just the last checkpoint. Treat "update the state file" as part of finishing a step, the same way committing code is part of finishing a change.

## Common failure modes

- **Stale "in progress" sections**: a chunk finishes a step but doesn't move it to Completed, so the next chunk re-derives what's actually done from scratch (or worse, redoes it).
- **Decisions without rationale**: a later chunk reverses an earlier deliberate tradeoff because the state file recorded the *what* but not the *why*.
- **Growing without pruning**: on a very long job, a state file that keeps every historical detail becomes exactly the kind of unbounded context this pattern exists to avoid. Completed items older than a few chunks back can usually be compressed to a one-line summary rather than kept in full detail.
