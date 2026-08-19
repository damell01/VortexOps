<?php

namespace App\Http\Controllers;

use App\Support\GuidedTours;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    /**
     * Remember that this person has finished (or dismissed) a tour.
     *
     * Recorded per user rather than in the browser: staff sign in from the
     * packing bench, a phone and a laptop, and a tour that reintroduces itself
     * on every device is one people learn to dismiss without reading.
     */
    public function complete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tour' => ['required', 'string', 'in:' . implode(',', GuidedTours::ids())],
        ]);

        $user = $request->user();

        if (! $user) {
            return response()->json(['ok' => false], 401);
        }

        $seen = $user->completed_tours ?? [];
        $seen[] = $validated['tour'];

        $user->completed_tours = array_values(array_unique($seen));
        $user->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Show every tour again.
     *
     * Each page keeps its own launcher, so a single tour can always be replayed
     * where it lives. This is the other case: someone new sitting at an account
     * that has already dismissed everything, or a screen that has changed enough
     * that the whole set is worth seeing again.
     */
    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['ok' => false], 401);
        }

        $user->completed_tours = [];
        $user->save();

        return response()->json(['ok' => true, 'tours' => count(GuidedTours::ids())]);
    }
}
