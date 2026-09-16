<?php

namespace App\Http\Controllers;

use App\Models\SavedOrderFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Named, reusable Orders-page filter combinations -- lets a user save the
 * current status/customer/date-range combo under a name and reapply it
 * later in one click, instead of rebuilding it from scratch every session.
 * Mirrors SearchSelectionController's shape (save a per-user pick, list it
 * back, scoped to the owner) almost exactly.
 */
class SavedOrderFilterController extends Controller
{
    public function index()
    {
        $filters = SavedOrderFilter::query()
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'name', 'filters']);

        return response()->json($filters);
    }

    public function store(Request $request)
    {
        $existingCount = SavedOrderFilter::query()->where('user_id', Auth::id())->count();
        if ($existingCount >= SavedOrderFilter::MAX_PER_USER) {
            return response()->json([
                'message' => 'You have reached the limit of 20 saved filters. Delete one to save another.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'filters' => 'required|array',
            'filters.status' => 'nullable|string|max:40',
            'filters.customer_id' => 'nullable',
            'filters.date_filter' => 'nullable|string|max:40',
            'filters.start_date' => 'nullable|string|max:20',
            'filters.end_date' => 'nullable|string|max:20',
        ]);

        $saved = SavedOrderFilter::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'filters' => $validated['filters'],
        ]);

        return response()->json($saved->only(['id', 'name', 'filters']), 201);
    }

    public function destroy(SavedOrderFilter $savedOrderFilter)
    {
        abort_if($savedOrderFilter->user_id !== Auth::id(), 403);

        $savedOrderFilter->delete();

        return response()->noContent();
    }
}
