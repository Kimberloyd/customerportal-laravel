# Search Bar UX Audit Checklist

Walk the search component under review against all five rules. Search bars frequently have several of these gaps at once -- check every rule, don't stop at the first hit.

## 1. Guiding Placeholder
- [ ] Is the placeholder just `"Search"` / `"Search..."`, or does it tell the user what they can search for and how (fields matched, scope)?
- [ ] If the component supports special syntax (filters, quoted phrases, SKU/ID lookup), is that capability discoverable anywhere, or does the user have to already know about it?

## 2. Empty Isn't Empty
- [ ] On focus with an empty query, does anything render, or does the dropdown/panel stay empty or absent until the user types?
- [ ] If something renders, is it the user's actual recent searches (stored and retrieved per-user/session), or a placeholder that never gets populated?
- [ ] Does tapping/clicking a recent search actually refill and (usually) re-run the query, or does it do nothing / just focus the input?
- [ ] If there's no history yet, is there a reasonable fallback (popular searches) instead of a blank or broken-looking panel?

## 3. Rank by Clicks, Not Alphabet
- [ ] Are autocomplete suggestions ordered by a popularity/click-through signal, or by alphabetical/lexicographic sort (often the default of `ORDER BY name` or a naive `.sort()` in the client)?
- [ ] Is there any ranking signal being collected/used at all, or is the ordering effectively arbitrary (database insertion order, raw string match)?
- [ ] Is the suggestion list capped to a small, scannable number, or does it dump every partial match with no limit?
- [ ] Does each suggestion show what kind of result it is (a scope/category badge), or is it ambiguous what clicking it will do?

## 4. Keep Focus Visible
- [ ] Do Arrow Up/Down move a visible highlight through the suggestions list, or do the keys do nothing (mouse-hover only)?
- [ ] Does Enter select the currently highlighted suggestion? Does Escape close the dropdown?
- [ ] Is the highlighted state visually obvious (background fill, clear border) rather than a subtle color shift that's easy to miss?
- [ ] Is the keydown handler actually attached somewhere that receives events while the user is typing (commonly missed: wired to the list/dropdown instead of the input or a shared listener)?

## 5. Never a Dead End
- [ ] Does a zero-results state say only "no results" with nothing else actionable?
- [ ] Are there concrete recovery options offered (a popular search, a related category link, a recent search) rather than a generic dead end?
- [ ] Are the recovery suggestions at all related to the failed query, or always the same static fallback regardless of what was typed?

## Reporting format

For each violation found, state: the component/file, the specific code involved (e.g. `"SearchBar.jsx renders suggestions sorted with a plain .sort((a,b) => a.label.localeCompare(b.label)) -- alphabetical, not by click-through rate"`), which rule it violates, and the concrete fix. Then implement the fix, don't just report it.
