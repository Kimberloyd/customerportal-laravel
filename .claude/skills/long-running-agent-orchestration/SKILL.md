---
name: long-running-agent-orchestration
description: "Keeps AI agents coherent on long, multi-step work using a 3-part pattern: a running state file the agent reads/updates every step, decomposition into 5-7 step chunks each its own scoped session, and a human validation checkpoint between chunks. An agent running 30 steps as one continuous run drifts and contradicts itself -- an architecture problem, not a model-quality one. Applies two ways: (1) self-orchestration -- when Claude gets a long, complex, multi-step task, apply this to its own work (state file, chunking, pause for review between chunks); (2) implementation -- when asked to build agent orchestration infrastructure into a codebase, implement the state-file/chunking/checkpoint pattern as code. Trigger on requests to build/design AI agent orchestration or multi-step agent workflows; on tasks spanning many steps; when a user describes their agent losing context or forgetting earlier steps; or when discussing context window limits or long-running agent reliability."
---

# Long-Running Agent Orchestration

Context windows are finite. An agent working through a 30-step job as one continuous, unbroken run starts strong, gets shakier by the middle, and by the end has drifted from or outright contradicted its own earlier decisions -- not because the model got worse, but because the architecture asked it to hold too much in an ever-growing, ever-more-diluted context. This is an architecture problem, not a prompting problem, and it has a specific fix: a state file, chunked execution, and validation checkpoints between chunks.

## The 3-part pattern

**1. A running project state file that travels with every step.** A structured document -- not a mental model held only in the conversation -- tracking what's been completed, what's in progress, what constraints apply, and what decisions have been made and why. The agent reads this file at the start of every step/chunk before doing anything else; it's the memory the agent doesn't have natively. See `references/state-file-design.md`.

**2. Task decomposition before execution.** A long job never runs as one continuous conversation. It's broken into discrete chunks of roughly 5-7 steps each, and each chunk runs as its own scoped session/context, seeded at the start with the current state file rather than the full history of everything that came before. Smaller scopes mean the agent never accumulates enough drift to contradict itself. See `references/chunking-strategy.md`.

**3. A validation checkpoint between every chunk.** Before moving from one chunk to the next, something has to verify the output -- and that something is a human, not the agent grading its own work. Direct the agent to pause after each chunk and present a concrete summary for review before continuing. Agents left to run unsupervised for 30 steps produce confidently-presented, unverified output; agents checked at every chunk boundary produce output someone actually vetted. See `references/validation-checkpoints.md`.

The architecture of how work is fed to an agent matters more than which model runs it.

## Mode 1: Self-orchestration (Claude's own long tasks)

When given a task in this session that will clearly span many steps -- a large migration, a multi-file build, a long audit, anything that would otherwise become one unbroken 20+ tool-call run -- apply the pattern to your own work rather than attempting it end to end in a single pass:

- **State file**: maintain a running state document (a markdown or JSON file in the working directory, or this session's task-list tool if one is available) recording what's done, what's in progress, key constraints, and decisions made and why. Read it back at the start of each new chunk of work, don't rely on remembering everything from earlier in the conversation.
- **Chunking**: break the job into chunks of about 5-7 steps. Don't silently execute all 30 steps of a big job back to back -- complete a chunk, update the state file, then stop.
- **Checkpoint**: at each chunk boundary, present a concrete summary of what changed and what's next, and pause for the user's go-ahead before continuing to the next chunk, rather than barreling through the whole job and presenting one giant result at the end. Use whatever the environment's actual checkpoint mechanism is (an explicit question to the user, a task-list update, a diff/summary message) -- the point is a real pause for review, not a rhetorical one.

This applies whether or not the user explicitly asked for it -- if a task is going to be long and multi-step, structuring it this way produces a more reliable result than trying to hold the whole thing in one continuous run.

## Mode 2: Building this into a codebase/product

When asked to design or implement agent orchestration -- a system that runs its own AI agents on long or complex jobs -- implement the same three parts as actual infrastructure:

- A persisted state store (file, DB row, or object) with a defined schema for completed/in-progress/constraints/decisions, written after every step and read at the start of every chunk.
- A chunk boundary and hand-off mechanism: each chunk gets a fresh, scoped context (new conversation/session/subagent call) seeded with the current state, not the accumulated transcript of every prior chunk.
- An explicit approval gate between chunks: the pipeline pauses and requires a human (or a defined automated check standing in for one) to approve before the next chunk's agent run starts.

See `references/implementing-in-code.md` for concrete patterns (state schema, chunk hand-off, and approval-gate implementations across common stacks).
