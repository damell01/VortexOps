<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ReviewItem;
use App\Models\ReviewItemComment;
use App\Models\ReviewSession;
use App\Modules\ProjectHub\Support\ProjectHub;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewPortalController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $projects = ProjectHub::visibleProjectsFor($user)
            ->withCount([
                'reviewItems as total_review_items_count',
                'reviewItems as open_review_items_count' => fn ($q) => $q->whereIn('status', ['open', 'in_progress']),
                'reviewItems as resolved_review_items_count' => fn ($q) => $q->whereIn('status', ['fixed', 'approved']),
                'approvals as pending_approvals_count' => fn ($q) => $q->where('status', 'pending'),
                'milestones as completed_milestones_count' => fn ($q) => $q->whereIn('status', ['completed', 'approved']),
                'milestones as total_milestones_count',
            ])
            ->where('client_visible', true)
            ->with(['owner:id,name', 'manager:id,name'])
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        return view('review.index', compact('projects'));
    }

    public function project(Project $project): View
    {
        abort_unless(ProjectHub::visibleProjectsFor(auth()->user())->whereKey($project->id)->exists(), 403);

        $project->load([
            'milestones' => fn ($query) => $query->where('visible_to_client', true),
            'approvals' => fn ($query) => $query->where('visible_to_client', true)->latest('requested_at'),
            'statusUpdates' => fn ($query) => $query->where('visible_to_client', true)->latest(),
            'statusUpdates.author:id,name',
            'reviewSessions' => fn ($query) => $query->withCount('items')->latest(),
        ]);

        $project->loadCount([
            'reviewItems as open_review_items_count' => fn ($q) => $q->whereIn('status', ['open', 'in_progress']),
            'reviewItems as resolved_review_items_count' => fn ($q) => $q->whereIn('status', ['fixed', 'approved']),
            'approvals as pending_approvals_count' => fn ($q) => $q->where('status', 'pending'),
        ]);

        return view('review.project', compact('project'));
    }

    public function session(ReviewSession $session): View
    {
        if ($session->project_id) {
            abort_unless(ProjectHub::visibleProjectsFor(auth()->user())->whereKey($session->project_id)->exists(), 403);
        }

        $items = $session->items()
            ->with(['createdBy:id,name', 'assignedTo:id,name', 'comments'])
            ->latest()
            ->get();

        $session->load('project');

        return view('review.session', compact('session', 'items'));
    }

    public function item(ReviewItem $item): View
    {
        if ($item->session?->project_id) {
            abort_unless(ProjectHub::visibleProjectsFor(auth()->user())->whereKey($item->session->project_id)->exists(), 403);
        }

        $item->load(['session.project', 'createdBy:id,name', 'assignedTo:id,name', 'comments.user:id,name']);

        return view('review.item', compact('item'));
    }

    public function storeComment(Request $request, ReviewItem $item): RedirectResponse
    {
        if ($item->session?->project_id) {
            abort_unless(ProjectHub::visibleProjectsFor(auth()->user())->whereKey($item->session->project_id)->exists(), 403);
        }

        $request->validate(['body' => 'required|string|max:2000']);

        ReviewItemComment::create([
            'review_item_id' => $item->id,
            'user_id'        => auth()->id(),
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
