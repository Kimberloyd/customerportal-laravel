# Implementing Orchestration as Infrastructure

When the task is to build this pattern into a real system (a product that runs its own AI agents on long jobs), the three parts become concrete infrastructure, not just a working discipline for one session.

## State store

A minimal schema, whatever the storage backend (a JSON file, a DB table, a key-value store):

```json
{
  "job_id": "job_8f2a...",
  "completed": [
    {"step": 1, "summary": "...", "artifacts": ["path/or/id"], "completed_at": "..."}
  ],
  "in_progress": {"step": 4, "scope": "..."},
  "constraints": ["..."],
  "decisions": [
    {"decision": "...", "rationale": "...", "made_at": "..."}
  ],
  "open_questions": ["..."]
}
```

Write to it after every step completes (not just at chunk end) so a crash or interruption mid-chunk doesn't lose progress. Read it at the start of every chunk's agent run and inject the relevant fields into that run's system/context prompt -- don't replay the full history of every prior chunk's transcript.

## Chunk dispatch

Each chunk should be its own agent invocation with a fresh context, not a continuation of the same growing conversation:

- Define chunk boundaries up front where possible (a static task breakdown), or derive them dynamically if the job's shape isn't known in advance (e.g. an agent that proposes the next chunk's scope based on current state).
- Each chunk's agent call is seeded with: the current state (from the store above), the specific scope for this chunk, and any constraints -- not the raw transcript of prior chunks.
- Cap chunk size (5-7 steps as a default) both to bound context growth within the chunk and to bound how much unreviewed work can happen before the next checkpoint.

## Approval gate

The gate needs to be a real block on execution, not a notification that gets ignored:

- Simplest version: the pipeline writes the chunk's summary somewhere a human checks, and the next chunk's dispatch is gated on an explicit approval record (a flag flipped by a reviewer, an approved webhook, a merged PR) rather than firing on a timer or immediately.
- For a system with many concurrent jobs, this usually means a queue/workflow engine where "chunk complete" transitions the job to a `pending_review` state, and only an explicit approval action transitions it to `next_chunk_dispatched`.
- If full human review isn't feasible for every chunk at scale, an automated check can stand in for the low-stakes case (tests passing, a lint/schema validator, a policy check) -- but treat that as a deliberate risk tradeoff to make explicitly, not a default silently substituted for real review under the same "checkpoint" label.

## Example: a simple orchestrator loop (pseudocode)

```
job = load_or_create_job(job_id)

while job.has_remaining_work():
    chunk_scope = plan_next_chunk(job.state, max_steps=7)
    result = run_agent(
        context=build_context(job.state, chunk_scope),  # state + scope only
        scope=chunk_scope,
    )
    job.state = apply_result_to_state(job.state, result)
    persist(job.state)

    summary = build_checkpoint_summary(result, job.state)
    job.status = "pending_review"
    notify_reviewer(summary)

    approval = await_approval(job.id)  # blocks until a real approval signal
    if not approval.approved:
        handle_rejection(job, approval.feedback)
        continue  # re-plan or retry the chunk with feedback folded into state

    job.status = "in_progress"
```

The key properties this preserves: state is durable and incremental, each chunk gets a bounded/fresh context rather than an ever-growing one, and forward progress is gated on a real approval signal rather than assumed.
