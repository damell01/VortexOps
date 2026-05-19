<?php

namespace App\Http\Controllers;

use App\Models\ReviewItem;
use App\Models\ReviewItemComment;
use App\Models\ReviewSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewPortalController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $sessions = ReviewSession::query()
            ->withCount([
                'items as total_count' => function ($q) use ($user) {
                    if (! $user->isSuperAdmin()) {
                        $q->where('created_by', $user->id);
                    }
                },
                'items as open_count' => function ($q) use ($user) {
                    $q->where('status', 'open');
                    if (! $user->isSuperAdmin()) {
                        $q->where('created_by', $user->id);
                    }
                },
                'items as fixed_count' => function ($q) use ($user) {
                    $q->whereIn('status', ['fixed', 'approved']);
                    if (! $user->isSuperAdmin()) {
                        $q->where('created_by', $user->id);
                    }
                },
            ])
            ->when(! $user->isSuperAdmin(), function ($q) use ($user) {
                $q->whereHas('items', fn ($q2) => $q2->where('created_by', $user->id));
            })
            ->orderByDesc('created_at')
            ->get();

        return view('review.index', compact('sessions'));
    }

    public function session(ReviewSession $session): View
    {
        $user  = auth()->user();
        $items = $session->items()
            ->with(['createdBy:id,name', 'assignedTo:id,name', 'comments'])
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('created_by', $user->id))
            ->latest()
            ->get();

        return view('review.session', compact('session', 'items'));
    }

    public function item(ReviewItem $item): View
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $item->created_by !== $user->id) {
            abort(403);
        }

        $item->load(['session', 'createdBy:id,name', 'assignedTo:id,name', 'comments.user:id,name']);

        return view('review.item', compact('item'));
    }

    public function storeComment(Request $request, ReviewItem $item): RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && $item->created_by !== $user->id) {
            abort(403);
        }

        $request->validate(['body' => 'required|string|max:2000']);

        ReviewItemComment::create([
            'review_item_id' => $item->id,
            'user_id'        => $user->id,
            'body'           => $request->body,
        ]);

        return redirect()->back()->with('success', 'Comment added.');
    }

    public function updateStatus(Request $request, ReviewItem $item): RedirectResponse
    {
        abort_if(! auth()->user()->isSuperAdmin(), 403);

        $request->validate([
            'status' => 'required|in:open,in_progress,fixed,approved,rejected,wont_fix',
        ]);

        $item->update(['status' => $request->status]);

        return redirect()->back()->with('success', 'Status updated to ' . $request->status . '.');
    }
}
