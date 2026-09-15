# Pull-to-Refresh UX -- Implementation Guide

Patterns for each rule, primarily in React/React Native flavored pseudocode (using Framer Motion-style spring configs where relevant). The underlying math (damped drag curve, spring physics) translates to any platform -- UIKit, Android Views/Compose, plain DOM touch events.

## 1. Threshold

```js
const THRESHOLD = 80; // px

function handleRelease(dragDistance) {
  if (dragDistance >= THRESHOLD) {
    triggerRefresh(); // only past the line
  } else {
    snapBack(); // pure no-op: no fetch, no reload, just animate to rest
  }
}
```

Never call the refresh handler unconditionally on release, and never call it mid-drag (before release) -- the commit point is release-past-threshold, not drag-past-threshold (haptic feedback at the drag-past-threshold moment is separate, see rule 4).

## 2. Resistance

```js
// Diminishing-returns curve: early drag moves content almost 1:1,
// further drag adds progressively less offset.
function resistedOffset(rawDragDistance, threshold = 80) {
  return threshold * (1 - 1 / (rawDragDistance / threshold + 1));
}

// finger moved 195px, threshold 80 -> content offset ~57px, not 195px
```

```jsx
// In a touch/pan handler:
function onPanMove(e) {
  const rawDelta = Math.max(0, e.translationY);
  setContentOffset(resistedOffset(rawDelta));
}
```

A quick smell test for an existing implementation: search for the drag handler and see whether the content's transform/offset is set directly from the raw touch delta (`setOffset(delta)`) with no damping function in between -- that's the 1:1 "cheap" version this rule is about.

## 3. Handoff

```jsx
function PullIndicator({ dragOffset, threshold, isLoading }) {
  const progress = Math.min(dragOffset / threshold, 1); // 0 to 1 while dragging

  if (isLoading) {
    // Same ring component, now spinning, instead of swapping to an
    // unrelated spinner asset.
    return <Ring progress={1} spinning />;
  }
  return <Ring progress={progress} />; // partial arc while dragging, full at threshold
}
```

Keep the same `Ring` component (same size/stroke/position) across all three states -- the only things that change are `progress` (tied to drag distance while pulling) and `spinning` (true once released past threshold and loading). Swapping to a visually distinct spinner component/asset the moment loading starts is the abrupt handoff this rule flags.

## 4. Haptic

```js
let hasFiredHapticThisGesture = false;

function onPanMove(e) {
  const rawDelta = Math.max(0, e.translationY);
  const pastThreshold = rawDelta >= THRESHOLD;

  if (pastThreshold && !hasFiredHapticThisGesture) {
    triggerHaptic('light'); // fires once, right as it crosses, before release
    hasFiredHapticThisGesture = true;
  } else if (!pastThreshold) {
    hasFiredHapticThisGesture = false; // re-arm if they pull back below and past again
  }
}

function onPanEnd() {
  hasFiredHapticThisGesture = false; // reset for the next gesture
}
```

The haptic call belongs in the drag/move handler (fires while still pulling), not in the release handler or the refresh-complete callback -- putting it there is the most common way this rule gets missed even when someone remembers to add haptics at all.

## 5. Bounce

```js
// Framer Motion-style spring config for the release animation
const releaseSpring = { type: 'spring', stiffness: 300, damping: 20, mass: 0.5 };

// Lower damping relative to stiffness = more visible overshoot before settling.
// A damping value close to or above stiffness's critical damping point will
// look like a hard stop even though it's technically "a spring."
```

```jsx
<motion.div
  animate={{ y: isDragging ? dragOffset : 0 }}
  transition={isDragging ? { duration: 0 } : releaseSpring}
/>
```

If the codebase uses a fixed-duration ease-out (`transition: { duration: 0.2, ease: 'easeOut' }`) for the release instead of a spring, that's the hard-stop version this rule is about -- ease-out curves approach their target asymptotically and never overshoot it.

## 6. Scroll Alive

```jsx
function RefreshableList({ items, onRefresh }) {
  const [loading, setLoading] = useState(false);

  async function handleRefresh() {
    setLoading(true);
    const newItems = await onRefresh(); // list stays visible/scrollable the whole time
    mergeItemsInPlace(newItems); // prepend/update, don't replace-and-remount
    setLoading(false);
  }

  return (
    <ScrollView scrollEnabled={true /* never disabled during loading */}>
      <PullIndicator isLoading={loading} />
      {items.map(item => <ListRow key={item.id} item={item} />)}
    </ScrollView>
  );
}
```

Watch for two common violations in an audit: (1) a full-screen loading overlay or spinner that covers the list during refresh (instead of just the top pull-indicator area), and (2) the refreshed data replacing `items` wholesale via a new array/key that forces the list to remount, which resets scroll position and causes a visible flash even if `scrollEnabled` was never explicitly turned off.
