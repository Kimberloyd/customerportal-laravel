# Constructing the palette

## Start from HSL, not hex guessing

Picking colors by eyeballing hex codes is how palettes end up accidentally clashing. Work in HSL (hue, saturation, lightness) instead — it lets you reason about relationships explicitly, then convert to hex/rgb at the end for implementation. The three roles have characteristic HSL profiles:

- **Dominant (60%):** low-to-moderate saturation (roughly 5-20%), and lightness pushed toward one end — very light (90%+) for a light-mode surface, very dark (10-15%) for a light-on-dark or dark-mode surface. It needs to recede, not compete. A dominant color at high saturation is the single most common mistake in a first attempt at this rule — it turns the whole page into "the accent" and leaves nothing left to draw the eye toward.
- **Secondary (30%):** a step up in presence from the dominant — can share its hue family for cohesion (a slightly darker/more saturated version of the dominant hue) or introduce a second, related hue. This is what gives the design structure and separation (cards standing off a background, headers distinguishing from body) without yet being "the color that means click here."
- **Accent (10%):** the one color allowed real saturation and, often, a contrasting hue from the other two — this is deliberate: a color theory relationship (complementary, or a split-complementary/triadic pull) makes the accent pop precisely because it's the odd one out against the calmer dominant/secondary pair. High saturation, mid-range lightness (40-60%) so it holds up against both light and dark surfaces.

## Deriving from a single seed color

Most real projects start with one given color — a brand color, a logo color, something from a reference image. Where that seed lands determines the derivation path:

**If the seed is saturated/vibrant (S > 50%):** it's almost always the accent. Build outward from it:
- Dominant: take the same hue, drop saturation to ~10%, push lightness to 95-97% (light mode) or 12-15% (dark mode). This keeps a whisper of brand identity in the background without it reading as "colored."
- Secondary: same hue, slightly higher saturation than dominant (~15-20%), lightness around 92% (light mode, for card surfaces sitting just off the background) or 20-22% (dark mode). Alternatively, pick an analogous hue (±20-30° on the color wheel) at similar saturation/lightness if the design needs more visual variety than a monochrome ramp gives it.
- Accent: the seed color itself, largely unchanged — this is what it was already good at being.

**If the seed is muted/neutral (S < 20%, or it's explicitly "our gray" or "our navy"):** it's almost always the dominant or secondary, not the accent. An accent still needs to be chosen separately, and it needs enough contrast in hue and saturation from the seed to actually stand out — pulling the accent from a complementary or triadic relationship to the seed's hue (even at low seed saturation, the hue still has a wheel position) keeps the accent from feeling arbitrary.

## Example derivations

**Seed: a vibrant brand blue, `#2563EB` (H 217°, S 83%, L 55%)**
- Accent: `#2563EB` itself (unchanged)
- Secondary: `#EFF4FF` (H 217°, S 60%→ drop to ~20%, L 95%) for card surfaces; or `#1E3A5F` (H 210°, S 45%, L 22%) if a darker secondary panel/header is needed
- Dominant: `#FAFBFF` (H 217°, S 10%, L 98%) light mode background — barely-there blue-white
- Dark mode dominant: `#0B1220` (H 217°, S 25%, L 8%), dark mode secondary: `#151F30` (H 217°, S 22%, L 14%), accent lightened slightly for dark backgrounds: `#5B8DEF` (H 217°, S 75%, L 65%) since the original `#2563EB` loses some perceived contrast against a near-black surface

**Seed: a muted brand forest green, `#3D5C4A` (H 152°, S 19%, L 30%)**
- Dominant: `#F7F9F8` (H 152°, S 8%, L 98%) — nearly-neutral, hints at the hue
- Secondary: `#3D5C4A` itself, or a lighter tint `#DCE6E1` for surfaces
- Accent: pull from a complementary/split relationship rather than the seed's own hue family — something like `#D9663B` (H 17°, S 65%, L 52%, a warm terracotta) reads as intentional against the muted green rather than an arbitrary bright color dropped in

## Neutrals aren't "no color"

A dominant or secondary that's meant to read as "white," "gray," or "black" should still carry a faint hue tint matching the palette's temperature (the small S value in the HSL profiles above) rather than being a literal `#FFFFFF`/`#000000`/pure gray. Pure neutrals next to a tinted accent tend to make the accent look slightly dirty or mismatched; a background with a whisper of the same hue family makes the whole palette read as designed together rather than assembled from unrelated pieces. This is a subtle effect but it's a reliable tell between a palette a professional built and one that wasn't.

## Building the tint/shade ramp for each role

Each role usually needs more than one value in practice: a base, a hover/active state (typically 8-12% darker in lightness for light-mode elements, lighter for dark-mode), a subtle background tint (very high lightness, low saturation version for things like a selected-row highlight), and a border/divider value (small lightness step from the surface it sits on, just enough to be perceptible — roughly a 10-15% lightness difference is the practical floor for a border to read as a border rather than disappear).
