---
name: search-bar-ux
description: "Use when building or reviewing a search bar, autocomplete dropdown, or in-app search, and it feels generic or dead-ended -- a placeholder that just says 'Search' with no hint of what/how, an empty focused field showing nothing until typed into, suggestions sorted alphabetically instead of by what people click, a dropdown unusable by keyboard (no visible highlight, arrows do nothing), or a zero-results state with no way forward. Applies a 5-part system: Guiding Placeholder, Empty Isn't Empty (show recent searches), Rank by Clicks (not the alphabet, with scope badges), Keep Focus Visible (keyboard nav with visible highlight, Enter/Esc), and Never a Dead End (zero-results recovery options). Use whenever building or auditing a search bar or autocomplete/typeahead component, even if the user doesn't name these issues directly."
---

# Search Bar UX

Most search bars are built as an afterthought: an `<input>`, a debounced fetch, a dropdown of raw results. That gets the data moving, but it skips every moment where the person searching actually needs help -- before they've typed anything, while they're typing, while they're navigating suggestions, and when nothing matches. This skill is a checklist for those moments.

The through-line: a search bar's job isn't just to return matches for a query -- it's to help someone find something even when they don't know the exact words for it yet, can't finish typing, or gets no matches at all. Each rule below covers one of those moments.

## 1. Guiding Placeholder

A placeholder that just says "Search" tells the user nothing about what they can search for, how specific to be, or what scope they're searching within. On a first use, that's a blank moment where the user has to guess.

- Write the placeholder to answer "search *what*, and *how*": `"Search by name, SKU, or brand"` tells the user both the object being searched and the fields it matches against, instead of a bare `"Search"`.
- If the search scope isn't obvious from context (searching everything vs. just the current section), say so: `"Search this category"` vs `"Search all products"`.
- For a search experience that supports more than plain keyword matching (filters, quoted phrases, operators), a placeholder alone often isn't enough -- pair it with a couple of inline examples the first time a user encounters it (e.g. a few example queries shown below the bar) so the capability doesn't stay hidden.
- This is a first-run/onboarding concern -- it matters most before the user has learned the search bar's conventions through trial and error. Don't make them discover capabilities like SKU search by accident.

## 2. Empty Isn't Empty

An empty search field, focused but not yet typed into, is not "nothing to show" -- it's a moment where showing the right thing (what the user searched before) saves them from retyping it and re-teaches them that the search bar remembers.

- On focus, before any input, show recent searches (typically the last 3-5) as clickable/tappable chips or list items -- one tap should refill the query and (usually) re-run the search, not just populate the text and wait for Enter.
- Don't render an empty dropdown or nothing at all when the field is focused with no query -- that's a missed moment to reduce friction for a returning user, and it silently signals "this search bar has no memory," which trains users not to expect one.
- If there's no search history yet (new user, cleared history), don't show a broken-looking empty panel -- either hide the panel entirely until there's a query, or show a small set of popular/trending searches as a reasonable default instead of leaving a blank box hanging under the input.

## 3. Rank by Clicks, Not Alphabet

Autocomplete suggestions sorted alphabetically (or by raw string match) put the user's actual goal at the mercy of the alphabet. If "Shoes" and "Shop New" both match "sho", the one people actually click 90% of the time should surface first regardless of which comes first alphabetically.

- Rank suggestions by observed popularity/click-through rate for that query prefix, not lexicographic order. This usually means the ranking signal has to be logged and computed server-side (or approximated from available analytics) -- it's not something a client-side alphabetical sort can get right.
- Cap the visible suggestion list to a small number (roughly 3-5) -- more than that turns the dropdown into a wall of text the user has to read instead of a fast shortcut, and defeats the purpose of ranking by relevance in the first place.
- Show what *kind* of thing each suggestion is with a small scope/category badge (a product category tag, a "Section" badge for a site area, etc.) -- the label alone is often ambiguous about what clicking it will do (search vs. navigate vs. filter).

## 4. Keep Focus Visible

A suggestions dropdown that only responds to mouse hover is invisible to anyone navigating with a keyboard -- and even for mouse users, a highlighted state communicates "this is what Enter will select" before they commit.

- Arrow Up/Down must move a visible highlight through the suggestion list, not just move an invisible internal index. If a screen reader or sighted keyboard user can't tell which item is "current," the interaction doesn't really exist for them.
- Enter selects the currently highlighted suggestion; Escape closes the dropdown (and typically clears the highlight, not necessarily the typed query).
- The focus ring/highlight state needs real visual weight (a filled background or a clear border, not a 1px color shift that's easy to miss) -- see the `keyboard-focus-accessibility` skill for the general focus-ring contrast/visibility requirements if this component needs a deeper accessibility pass.
- This isn't just an accessibility nicety bolted on afterward -- a search dropdown that only works by mouse silently excludes keyboard-only and screen-reader users from a core piece of navigation, and often signals the whole component was built and tested with a mouse only.

## 5. Never a Dead End

A "No results found" message with nothing else on the screen is a dead end -- the user typed something, got nothing back, and now has to guess what to try next entirely on their own. Most searches that hit zero results are recoverable with the right nudge.

- Pair the "no matches" message with concrete recovery options, not just an apology: a popular/trending search near what they typed, a link to browse the relevant category directly, and/or their own recent searches as an alternative starting point.
- Where possible, make the recovery suggestions specific to the failed query (fuzzy-matched popular searches, a category that's plausibly related) rather than a single generic "browse everything" link that could show up for any dead-end query.
- This is the highest-leverage moment to fix in a search experience: a user who hits a dead end with no way forward is the most likely to abandon the search (and sometimes the product) entirely, while a well-placed recovery path can turn that same moment into a successful outcome.

## Applying this skill

**When building a new search bar or autocomplete component:** work through all 5 rules together -- they cover different moments in the same flow (before typing, while typing, while navigating, on failure) and are meant to reinforce each other, not be applied piecemeal. See `references/implementation-guide.md` for React code patterns for each rule.

**When auditing an existing search component:** walk the code against all 5 rules systematically. A search bar frequently has several of these gaps at once (a bare placeholder AND no recent-searches AND alphabetical suggestions, all in the same component) since they're all easy to skip when a search bar gets built as a quick wrapper around a fetch call. See `references/audit-checklist.md` for the per-rule checklist and expected reporting format.
