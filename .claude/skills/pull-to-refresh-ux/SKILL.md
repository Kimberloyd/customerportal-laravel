---
name: pull-to-refresh-ux
description: "Use when building or reviewing pull-to-refresh (a list that refreshes on pulling down past the top) and it feels cheap or broken -- refresh firing on any pull instead of a real threshold, content tracking the finger 1:1 instead of resisting, a jarring cut from pull indicator to spinner, no haptic (or only after release), content stopping dead on release instead of settling, or a list that locks up while refreshing. Applies a 6-part system: Threshold (fires only past a defined distance), Resistance (elastic drag, content moves less than the finger), Handoff (indicator smoothly becomes the spinner), Haptic (a tick at threshold, before release), Bounce (release overshoots then settles), Scroll Alive (list stays live while refreshing). Use for any pull-to-refresh gesture, any platform, even without these exact terms."
---

# Pull-to-Refresh UX

Pull-to-refresh looks simple -- drag down, let go, list refreshes -- which is exactly why it's easy to build a version that technically works but feels wrong. The physical metaphor (pulling something taut, feeling it resist, letting it snap) is what makes the gesture satisfying, and most of the ways implementations go wrong are places where the code drops the physical metaphor and substitutes something mechanically simpler: a 1:1 drag instead of resistance, a hard stop instead of a bounce, a frozen list instead of a live one. None of these individually look "broken" in a code review, but together they're the difference between a refresh gesture that feels considered and one that feels like a fetch call bolted onto a scroll listener.

The through-line: pull-to-refresh is a physical promise (I'm pulling something down, it's giving me feedback the whole way, and something will happen if I commit) -- every rule below is about keeping that promise instead of taking a mechanical shortcut that breaks the illusion.

## 1. Threshold

The refresh action should only fire once the user has pulled past a defined distance -- not on any downward pull, and not only after they've released. If they pull down 20px and let go, nothing should happen beyond the content snapping back into place; no network call, no spinner, no reload.

- Define a clear threshold distance (commonly somewhere in the 60-100px range, tunable per design) that separates "just scrolling/browsing the gesture" from "committing to a refresh."
- Release below the threshold: the pull view/content animates back to its resting position with no refresh triggered at all -- this needs to be a real no-op, not a refresh that just happens to load quickly.
- Release past the threshold: this is what actually fires the refresh action (see Handoff below for what happens visually).
- Getting this wrong either direction is bad: no threshold at all means every accidental small drag triggers a refresh (wasteful, and confusing when data flashes/reloads unexpectedly); a threshold that's never reachable (too far, or accidentally requires a distance longer than the visible pull area allows) means users can't trigger a refresh no matter how hard they pull.

## 2. Resistance

While the user is dragging, the content should not track the finger 1:1 -- it should feel elastic, like there's real resistance building up the further they pull, the way a rubber band gets harder to stretch the more you pull it. A finger that has moved 195px but content that has also moved exactly 195px reads as cheap and mechanical; a finger that's moved 195px while the content has only moved ~100px (with the relationship following a curve that flattens out, not a straight line) reads as something with real weight and resistance.

- Implement the content offset as a damped/logarithmic (or similarly diminishing) function of the raw drag distance, not a direct pass-through. A common approach: `contentOffset = threshold * (1 - 1/(dragDistance/threshold + 1))` or similar, so early pull moves content almost 1:1 but the further past the threshold the finger travels, the less additional content offset each extra pixel of drag produces.
- This resistance should be present for the whole drag, not just past the threshold -- the elastic feeling starts as soon as the user starts pulling.
- Don't confuse resistance with the bounce-back animation (rule 5) -- resistance happens live, during the drag, while the finger is still down; bounce is what happens after release.

## 3. Handoff

Once the user releases past the threshold, the stretched pull indicator (whatever visual represents "how far you've pulled" -- often a partial ring or arrow that fills in as you drag) needs to hand off smoothly into the loading spinner, not cut abruptly from one visual state to an unrelated one. The natural progression is: a stretch/partial indicator tied to pull distance while dragging, becoming a full ring exactly at the threshold, which then continues seamlessly into a spinning/loading animation once released and the refresh is in flight.

- Use the same visual element (same ring, same size, same position) across the stretch -> full-ring -> spinner states rather than swapping to a completely different asset or animation when loading starts -- the continuity is what makes it read as one gesture instead of two disconnected UI states glued together.
- If the pull indicator and the loading spinner are visually unrelated (different icon, different size, a jarring pop-in), that's worth flagging even if both states work correctly in isolation -- the discontinuity is itself the UX problem.

## 4. Haptic

On a platform that supports haptics, a tick/vibration should fire at the moment the pull crosses the threshold -- while the user is still holding, before they've released -- confirming "this is now armed, if you let go a refresh will happen." Firing the haptic only after release (or only once loading completes) misses the point: the value of the haptic is that it lets the user feel the commitment point without having to watch the screen closely, the same way a physical toggle or ratchet clicks right when it catches.

- The haptic should fire once per crossing of the threshold during a single drag (not repeatedly as the user holds past it, and ideally it can re-arm if the user pulls back below the threshold and past it again in the same gesture).
- This is a small detail, but it's one of the more noticeable ones when it's absent -- a pull-to-refresh with no haptic at the threshold moment can still work perfectly and simply feel less "solid" than one that has it, especially on mobile where haptic feedback is an established convention for exactly this kind of commit-point.

## 5. Bounce

When the user releases (whether or not the release triggers a refresh), the content's return to its resting position should overshoot slightly and settle, not stop dead the instant it reaches its target. A spring/rubber-band settle (overshoot past rest, come back, maybe a small secondary oscillation) reads as physical and alive; content that hits its resting position and simply stops reads as mechanical -- a "hard stop" -- even though functionally both end up in the same place.

- Implement the release animation with a spring curve (real spring physics, or a spring-like easing curve) rather than a fixed-duration ease-out that arrives exactly at rest with no overshoot.
- This applies whether the gesture triggered a refresh or snapped back below-threshold -- both releases should feel physical, not just the "successful" one.

## 6. Scroll Alive

The list underneath the refresh gesture should never freeze while a refresh is in flight. The user should still be able to scroll the existing content, and when new data arrives it should update in place (new items appearing, existing items shifting) rather than the whole list going blank, showing a full-screen loading state, or becoming unresponsive to touch until the network call resolves.

- The loading spinner belongs at the top of the list (where the pull gesture happened), not as a modal/overlay that blocks the rest of the screen -- the user should be able to keep reading/scrolling the content they already have while new content loads in.
- When the refresh completes, insert/update content in place (e.g., a new item sliding in at the top, existing items unaffected) rather than discarding and re-rendering the whole list, which causes a visible flash and loses the user's scroll position.
- A pull-to-refresh that locks input or blanks the screen during the load defeats a big part of the gesture's appeal -- the promise is "check for new stuff while I keep doing what I was doing," not "wait here while I reload everything."

## Applying this skill

**When building a new pull-to-refresh interaction:** implement all 6 rules together -- they're meant to compose into one continuous physical gesture (drag with resistance -> cross threshold with a haptic tick -> release into a bouncy settle with a smooth spinner handoff -> list stays alive and updates in place), not six independent features bolted on separately. See `references/implementation-guide.md` for concrete patterns (React Native / Framer Motion / CSS-friendly math for the resistance curve, spring configs for the bounce, etc.).

**When auditing an existing pull-to-refresh implementation:** walk the code against all 6 rules -- it's common for an implementation to get 2-3 of these right (usually threshold and the basic spinner) while missing the more easily-skipped ones (resistance curve, the haptic timing, the list freezing during load). See `references/audit-checklist.md` for the per-rule checklist and expected reporting format.
