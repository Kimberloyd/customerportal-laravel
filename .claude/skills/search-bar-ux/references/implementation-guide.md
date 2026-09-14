# Search Bar UX -- Implementation Guide

React patterns for each rule. The state-management shape translates directly to other frameworks.

## 1. Guiding Placeholder

```jsx
// Generic -- tells the user nothing
<input placeholder="Search" />

// Guiding -- what and how
<input placeholder="Search by name, SKU, or brand" />
```

```jsx
// First-run examples, shown once below the bar until the user has searched
{!hasSearchedBefore && (
  <div className="flex gap-2 mt-2 text-xs text-slate-400">
    <span>Try:</span>
    {['"Search 12,000 products"', '"Find a city, address, or POI"', '"Filter by tag, color, or size"'].map(ex => (
      <span key={ex} className="rounded-full border border-slate-700 px-2 py-0.5">{ex}</span>
    ))}
  </div>
)}
```

## 2. Empty Isn't Empty

```jsx
function SearchBar() {
  const [query, setQuery] = useState('')
  const [focused, setFocused] = useState(false)
  const recentSearches = useRecentSearches() // ['Nike Air Max', 'White sneakers', ...]

  const showRecents = focused && query.length === 0 && recentSearches.length > 0

  return (
    <div className="relative">
      <input
        value={query}
        onChange={e => setQuery(e.target.value)}
        onFocus={() => setFocused(true)}
        onBlur={() => setTimeout(() => setFocused(false), 150)} // allow click on a chip before blur closes it
        placeholder="Search by name, SKU, or brand"
      />
      {showRecents && (
        <div className="absolute top-full mt-1 w-full rounded-lg border bg-slate-900 p-3">
          <p className="text-xs text-slate-400 mb-2">Recent searches</p>
          <div className="flex flex-wrap gap-2">
            {recentSearches.map(term => (
              <button
                key={term}
                onClick={() => { setQuery(term); runSearch(term) }}
                className="rounded-full bg-slate-800 px-3 py-1 text-sm"
              >
                {term}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
```

Don't render an empty `<div>` dropdown when `focused && query === '' && recentSearches.length === 0` -- either omit the panel entirely in that case, or fall back to a small popular-searches list instead of leaving a blank box.

## 3. Rank by Clicks, Not Alphabet

```jsx
// The API/query layer should return suggestions pre-ranked by click-through
// rate for that prefix -- don't re-sort alphabetically on the client after
// fetching, and don't rely on the database's default string ordering.
async function fetchSuggestions(prefix) {
  const results = await api.get(`/search/suggest?q=${prefix}`) // already ranked by CTR server-side
  return results.slice(0, 5) // cap the visible list
}

function SuggestionItem({ suggestion }) {
  return (
    <div className="flex items-center justify-between px-3 py-2">
      <div>
        <span>{suggestion.label}</span>
        <span className={`ml-2 rounded px-1.5 py-0.5 text-xs ${scopeBadgeClass(suggestion.scope)}`}>
          {suggestion.scope /* e.g. "Apparel", "Section" */}
        </span>
      </div>
    </div>
  )
}
```

If there's no click-tracking pipeline yet, that's worth flagging directly rather than silently falling back to alphabetical -- an audit finding here is often "there's no ranking signal being collected at all," which is a data/analytics gap as much as a UI one.

## 4. Keep Focus Visible

```jsx
function SuggestionsList({ items, query }) {
  const [highlightIndex, setHighlightIndex] = useState(-1)

  function handleKeyDown(e) {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setHighlightIndex(i => Math.min(i + 1, items.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setHighlightIndex(i => Math.max(i - 1, 0))
    } else if (e.key === 'Enter' && highlightIndex >= 0) {
      selectSuggestion(items[highlightIndex])
    } else if (e.key === 'Escape') {
      setHighlightIndex(-1)
      closeDropdown()
    }
  }

  return (
    <ul role="listbox" onKeyDown={handleKeyDown}>
      {items.map((item, i) => (
        <li
          key={item.id}
          role="option"
          aria-selected={i === highlightIndex}
          className={i === highlightIndex ? 'bg-teal-600/30 text-white' : 'text-slate-300'}
        >
          {item.label}
        </li>
      ))}
    </ul>
  )
}
```

The `onKeyDown` handler needs to live on an element that actually receives keyboard events while the user is typing (usually the `<input>` itself, dispatching into shared state) -- a common miss is wiring `onKeyDown` only onto the `<ul>`/list items, which never receive focus while the user is typing in the input.

## 5. Never a Dead End

```jsx
function ZeroResults({ query, popularSearch, relatedCategory, recentSearch }) {
  return (
    <div className="p-4">
      <p className="font-medium">No matches for "{query}"</p>
      <p className="text-sm text-slate-400 mb-3">Try different keywords.</p>

      <p className="text-xs text-slate-400 mb-2">Try one of these instead:</p>
      <div className="space-y-2">
        {popularSearch && (
          <RecoveryLink label={`Try: ${popularSearch}`} sub="Popular search" onClick={() => runSearch(popularSearch)} />
        )}
        {relatedCategory && (
          <RecoveryLink label={`Browse: ${relatedCategory.name}`} sub="Full category" onClick={() => navigateTo(relatedCategory.href)} />
        )}
        {recentSearch && (
          <RecoveryLink label={`Recent: ${recentSearch}`} sub="Last visited" onClick={() => runSearch(recentSearch)} />
        )}
      </div>
    </div>
  )
}
```

Pick recovery suggestions that are plausibly related to the failed query when possible (fuzzy-matched popular terms, a category guess from the query text) rather than always showing the same static list regardless of what was typed.
