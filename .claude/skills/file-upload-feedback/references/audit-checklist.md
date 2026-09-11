# File Upload Feedback Audit Checklist

Walk the upload UI under review against these five checks. For each hit, cite the actual code and the specific fix.

## 1. Drag Feedback
- [ ] Does the dropzone have `onDragEnter`/`onDragOver`/`onDragLeave` handlers at all?
- [ ] Does dragging a file over it change the border, background/glow, and copy (e.g. "Drop your file" -> "Release to upload")? Flag a dropzone that looks visually identical whether or not something is being dragged over it.

## 2. Honest Progress
- [ ] Does a long-running upload (more than ~2s) show a real percentage, or only an indeterminate spinner/"Uploading..." text with no number?
- [ ] Bonus: does it show time remaining or transfer speed? Not required, but flag a bare spinner with no percent as the primary violation.

## 3. Inline Retry
- [ ] When an upload fails, is the `File` object discarded/lost, forcing the user to re-open a file picker and re-select it?
- [ ] Does retry restart progress from 0%, or does it resume from the last known position (for chunked/resumable uploads) or at minimum auto-resubmit the already-held file with one tap?
- [ ] Flag any failure state with no retry action at all -- a dead end with no way to recover.

## 4. Upload Preview
- [ ] After a successful upload, does the UI show only a filename as plain text (e.g. "IMG_4032.jpg uploaded"), or does it show a visual thumbnail (for images/video) or a type icon plus file type and size?
- [ ] Flag a completed-upload state that gives no visual/metadata proof of what was uploaded beyond the name string.

## 5. Independent Queue
- [ ] In a multi-file upload, is each file's progress tracked and rendered independently (its own progress bar/state)?
- [ ] Does uploading use a sequential `for`/`for-await` loop that awaits each file in turn (meaning a stalled/failed file blocks the rest), or are uploads fired independently/concurrently per file?
- [ ] Does one file failing block, cancel, or freeze the progress of the others? Flag any batch-level failure handling that isn't scoped to the individual file.

## Reporting format

For each violation found, state: the component/file, the specific code involved (e.g. "`Dropzone.jsx` has no `onDragEnter`/`onDragLeave` handlers, so `className` never changes on drag"), which signal it violates, and the concrete fix. Then implement the fix, don't just report it.
