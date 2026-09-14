<?php

namespace App\Http\Controllers;

use App\Models\SearchSelection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Backs the "recent searches" and "rank by clicks" behaviors described in
 * the search-bar-ux skill: every entity a user picks from a search field is
 * logged here, then read back two ways -- this user's own recent picks
 * (for the empty-query state), and the most-picked entities across all
 * users for that context (to rank suggestions ahead of plain alphabetical
 * order).
 */
class SearchSelectionController extends Controller
{
    private const RECENT_LIMIT = 5;

    private const POPULAR_LIMIT = 8;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'context' => 'required|string|in:'.implode(',', SearchSelection::CONTEXTS),
        ]);
        $context = $validated['context'];

        // Most recent distinct pick per entity_key (or, for order_search,
        // per typed label) by this user -- a later duplicate pick bumps its
        // position back to the top rather than showing twice.
        $recent = SearchSelection::query()
            ->where('user_id', Auth::id())
            ->where('context', $context)
            // created_at is second-precision, so rapid repeat picks (or, in
            // tests, several picks within the same second) can tie on it --
            // MAX(id) breaks the tie using real insertion order.
            ->select('entity_key', 'label', DB::raw('MAX(created_at) as last_used_at'), DB::raw('MAX(id) as last_id'))
            ->groupBy('entity_key', 'label')
            ->orderByDesc('last_used_at')
            ->orderByDesc('last_id')
            ->limit(self::RECENT_LIMIT)
            ->get(['entity_key', 'label'])
            ->map(fn (SearchSelection $row) => [
                'entity_key' => $row->entity_key,
                'label' => $row->label,
            ]);

        // Popularity is meaningless for free-text order search (nothing to
        // rank -- it isn't a fixed list of entities).
        $popular = $context === SearchSelection::CONTEXT_ORDER_SEARCH
            ? []
            : SearchSelection::query()
                ->where('context', $context)
                ->whereNotNull('entity_key')
                ->select('entity_key', DB::raw('COUNT(*) as total'))
                ->groupBy('entity_key')
                ->orderByDesc('total')
                ->limit(self::POPULAR_LIMIT)
                ->get()
                ->map(fn (SearchSelection $row) => [
                    'entity_key' => $row->entity_key,
                    'count' => (int) $row->total,
                ]);

        return response()->json([
            'recent' => $recent,
            'popular' => $popular,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'context' => 'required|string|in:'.implode(',', SearchSelection::CONTEXTS),
            'entity_key' => 'nullable|string|max:190',
            'label' => 'required|string|max:190',
        ]);

        SearchSelection::create([
            'user_id' => Auth::id(),
            'context' => $validated['context'],
            'entity_key' => $validated['entity_key'] ?? null,
            'label' => $validated['label'],
        ]);

        return response()->json(['saved' => true], 201);
    }
}
