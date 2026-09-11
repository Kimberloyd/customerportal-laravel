---
name: file-upload-feedback
description: "Use when building or reviewing a file upload UI (drag-and-drop dropzone, file input, upload progress, or multi-file upload list) and the feedback feels missing or dishonest -- a drop zone that looks the same whether or not a file is being dragged over it, a spinner with no percent/time-left during a long upload, an upload that fails partway and forces the user to restart from zero, a completed upload that only shows a filename with no visual preview, or a multi-file upload where one failed file blocks or stalls the rest of the batch. Applies a 5-part signal system: Drag Feedback (border/glow/copy change on dragover, before drop), Honest Progress (percent, time remaining, and speed instead of a bare spinner), Inline Retry (resume a failed upload from where it stopped, file kept in memory, never restart from zero), Upload Preview (a real thumbnail plus type/size, not just a filename), and Independent Queue (each file in a multi-upload has its own progress/failure, one failure never blocks the others)."
---

# File Upload Feedback

Users hesitate when nothing moves, and they abandon when a failure means starting over. Five signals make an upload flow feel responsive and trustworthy instead of opaque.

## When to apply this

- **Building** any file upload UI: a drag-and-drop dropzone, a file input with progress, a multi-file upload queue, or an upload-preview/attachment list.
- **Auditing** existing upload flows -- especially AI-generated ones, which commonly render a static dropzone with no dragover state, a generic spinner instead of real progress, and a full-restart-on-failure upload with no resume.

## The 5 signals

### 1. Drag Feedback -- it has to answer back

A drop zone that looks identical whether or not a file is currently being dragged over it makes users hesitate, because nothing confirms the drop will register. On `dragover`, change at least: the border (e.g. dashed -> solid, color shift), a glow/background highlight, and the copy (e.g. "Drop your file" -> "Release to upload"). On `dragleave` or after the drop completes, revert.

```jsx
function Dropzone({ onFile }) {
  const [isDragging, setIsDragging] = useState(false)

  return (
    <div
      onDragOver={(e) => { e.preventDefault(); setIsDragging(true) }}
      onDragLeave={() => setIsDragging(false)}
      onDrop={(e) => {
        e.preventDefault()
        setIsDragging(false)
        onFile(e.dataTransfer.files[0])
      }}
      className={
        isDragging
          ? 'border-2 border-dashed border-teal-400 bg-teal-500/10 shadow-[0_0_30px_rgba(45,212,191,0.15)]'
          : 'border-2 border-dashed border-slate-700'
      }
    >
      <p>{isDragging ? 'Release to upload' : 'Drop your file'}</p>
    </div>
  )
}
```

### 2. Honest Progress -- a spinner hides the truth

An indeterminate spinner tells the user nothing: not how far along the upload is, how long is left, or whether it's stuck. For any upload expected to take more than a couple seconds, show the actual percentage, and ideally time remaining and/or transfer speed, so the user can decide to wait or walk away instead of staring at a mystery.

```jsx
function UploadProgress({ file, progress, bytesPerSecond }) {
  const remainingBytes = file.size * (1 - progress / 100)
  const secondsLeft = bytesPerSecond > 0 ? Math.ceil(remainingBytes / bytesPerSecond) : null

  return (
    <div>
      <p>{file.name} — {progress}%</p>
      <progress value={progress} max={100} />
      {secondsLeft != null && <p>{secondsLeft}s left</p>}
    </div>
  )
}
```

A bare `<Spinner /> Uploading...` with no percentage is the anti-pattern to flag when auditing.

### 3. Inline Retry -- never make them start over

When an upload fails partway (connection lost, server error), don't discard the file and force the user to re-select it from scratch. Keep the file object in memory/state, show an inline "Retry" action, and resume the upload from where it stopped (or at minimum restart automatically without requiring the user to re-pick the file) rather than resetting progress to 0%.

```jsx
function UploadItem({ file, progress, status, onRetry }) {
  return (
    <div>
      <p>{file.name} — {progress}%</p>
      <progress value={progress} max={100} />
      {status === 'failed' && (
        <div className="text-red-400">
          <p>Upload failed — connection lost</p>
          <button onClick={() => onRetry(file, /* resumeFrom */ progress)}>
            Retry
          </button>
        </div>
      )}
    </div>
  )
}

// The retry handler must reuse the already-selected File object and (where the
// upload protocol supports it, e.g. chunked/resumable uploads) resume from the
// last successfully-uploaded byte range instead of re-uploading from 0.
function handleRetry(file, resumeFromPercent) {
  const resumeFromByte = Math.floor(file.size * (resumeFromPercent / 100))
  uploadChunk(file, resumeFromByte)
}
```

If the backend doesn't support resumable/chunked uploads, retrying still means re-sending the whole file automatically on one tap -- the requirement is that the user never has to re-open a file picker and re-select the file.

### 4. Upload Preview -- a filename is not feedback

A text-only confirmation ("IMG_4032.jpg uploaded") gives no visual proof of what was actually uploaded. Show a real thumbnail (for images/video) or a type icon, plus file type and size, and clear success confirmation -- not just the filename as plain text.

```jsx
function UploadedFilePreview({ file, thumbnailUrl }) {
  return (
    <div>
      <img src={thumbnailUrl} alt="" className="w-24 h-24 object-cover rounded-md" />
      <div>
        <p>{file.name}</p>
        <p>{file.type.split('/')[1]?.toUpperCase()} · {formatBytes(file.size)}</p>
        <p className="text-emerald-400">✓ Uploaded just now</p>
      </div>
      <button onClick={() => replaceFile(file)}>Replace</button>
      <button onClick={() => removeFile(file)}>Remove</button>
    </div>
  )
}
```

For non-image files (PDF, video, audio), a distinct type icon plus the same type/size/confirmation metadata satisfies this rule -- the point is proof beyond a bare filename string, not that every file type needs a rendered visual thumbnail.

### 5. Independent Queue -- every file, its own lane

When uploading multiple files at once, each file needs its own progress state and its own failure/retry handling. One file failing must never stall, block, or reset the others -- they keep uploading and completing independently.

```jsx
function MultiUpload({ files }) {
  const [uploads, setUploads] = useState(
    files.map((f) => ({ file: f, progress: 0, status: 'uploading' }))
  )

  function updateUpload(fileName, patch) {
    setUploads((prev) =>
      prev.map((u) => (u.file.name === fileName ? { ...u, ...patch } : u))
    )
  }

  useEffect(() => {
    // each file uploads independently -- one promise per file, not sequential/blocking
    uploads.forEach((u) => {
      uploadFile(u.file, {
        onProgress: (p) => updateUpload(u.file.name, { progress: p }),
        onError: () => updateUpload(u.file.name, { status: 'failed' }),
        onComplete: () => updateUpload(u.file.name, { status: 'done', progress: 100 }),
      })
    })
  }, [])

  return (
    <div>
      {uploads.map((u) => (
        <UploadItem
          key={u.file.name}
          file={u.file}
          progress={u.progress}
          status={u.status}
          onRetry={(file) => retryUpload(file, updateUpload)}
        />
      ))}
    </div>
  )
}
```

The bug to flag when auditing: a `for` loop with `await` that uploads files sequentially and aborts the whole batch (or stalls subsequent files) when one throws, instead of kicking off independent, isolated upload operations per file.

## Auditing existing code

Check the upload UI against all five signals: does the dropzone visually change during `dragover`/`dragenter` (border, background/glow, and copy), or does it look static (rule 1)? Does a long-running upload show a real percentage/time estimate, or just an indeterminate spinner (rule 2)? Does a failed upload keep the file in memory and offer a retry that doesn't restart from 0%, or does it force a full re-selection (rule 3)? Does a completed upload show a thumbnail/type/size, or just a filename string (rule 4)? In a multi-file upload, is each file's progress/failure independent, or does one failure block/stall the batch (rule 5)? Report each violation with the specific code involved and the fix, then implement the fixes.

## References

- `references/implementation-guide.md` -- full worked examples for all 5 signals in React.
- `references/audit-checklist.md` -- a rule-by-rule checklist for reviewing existing upload flows.
