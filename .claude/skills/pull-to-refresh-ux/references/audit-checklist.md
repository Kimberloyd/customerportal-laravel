# Pull-to-Refresh UX Audit Checklist

Walk the pull-to-refresh implementation under review against all six rules. It's common for 2-3 to be handled (usually threshold and a basic spinner) while the rest are missing -- check every rule, don't stop at the first hit.

## 1. Threshold
- [ ] Is there a defined distance that separates "just dragging" from "committed to refresh," or does any downward pull past 0 eventually trigger a refresh?
- [ ] Does releasing below that distance produce a true no-op (snap back, no fetch call, no reload), or does it still trigger a refresh (or a visible reload) regardless of how far it was pulled?
- [ ] Is the threshold actually reachable within the visible/draggable pull area, or is it set so far that a user can't realistically trigger it?

## 2. Resistance
- [ ] Is the content's drag offset set directly from the raw touch/pan delta (1:1), or is it passed through a damping/diminishing function?
- [ ] Does the resistance apply throughout the whole drag (starting immediately), or only kick in past some point (which would still feel cheap for the first portion of the pull)?
- [ ] Don't confuse this with rule 5 -- resistance is the live feel *during* the drag while the finger is still down, not the release animation.

## 3. Handoff
- [ ] Does the pull indicator (ring/arrow/whatever shows drag progress) visually continue into the loading spinner, or does the code swap to a completely different component/asset the moment loading starts?
- [ ] Is the indicator's progress tied to live drag distance while pulling (partial -> full at threshold), or is it a static icon that doesn't respond to how far the user has pulled?

## 4. Haptic
- [ ] Is there any haptic/vibration feedback at all for the threshold-crossing moment?
- [ ] If present, does it fire during the drag (as it crosses the threshold, before release), or only after release / after the refresh completes? The latter misses the point of a "you're now armed" cue.
- [ ] Does it fire once per crossing (not repeatedly while held past threshold, and ideally re-armable if the user drags back below and past again)?

## 5. Bounce
- [ ] Does the release animation (whether it triggers a refresh or snaps back) use a spring/rubber-band curve with visible overshoot, or a fixed-duration ease that arrives at rest with no overshoot (a hard stop)?
- [ ] Does this apply to both outcomes of a release -- past-threshold (triggers refresh) and below-threshold (snaps back) -- or only one of them?

## 6. Scroll Alive
- [ ] Can the user still scroll the existing list content while a refresh is in flight, or is scrolling/input disabled during the load?
- [ ] Is the loading indicator confined to the top pull area, or does it become a full-screen/modal overlay that blocks the rest of the list?
- [ ] When new data arrives, is it merged/prepended into the existing list in place, or does the whole list get replaced/remounted (causing a flash and losing scroll position)?

## Reporting format

For each violation found, state: the component/file, the specific code involved (e.g. `"PullToRefresh.jsx sets contentOffset = dragDelta directly in onPanMove with no damping function -- 1:1 tracking, not elastic resistance"`), which rule it violates, and the concrete fix. Then implement the fix, don't just report it.
