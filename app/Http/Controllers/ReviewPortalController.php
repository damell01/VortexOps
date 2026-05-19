<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ReviewItem;
use App\Models\ReviewItemComment;
use App\Models\ReviewSession;
use App\Modules\ProjectHub\Support\ProjectHub;
use App\Modules\ProjectHub\Support\ProjectHubRoadmap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse as HttpRedirectResponse;
use Illuminate\View\View;

class ReviewPortalController extends Controller
{
    public function index(): View|HttpRedirectResponse
    {
        $user = auth()->user();

        if ($user?->isAdmin() && ! Project::query()->exists()) {
            ProjectHubRoadmap::ensureWorkspace($user);
        }

        $projects = ProjectHub::visibleProjectsFor($user)
            ->withCount([
                'reviewItems as total_review_items_count',
                'reviewItems as open_review_items_count' => fn ($q) => $q->whereIn('review_items.status', ['open', 'in_progress']),
                'reviewItems as resolved_review_items_count' => fn ($q) => $q->whereIn('review_items.status', ['fixed', 'approved']),
                'approvals as pending_approvals_count' => fn ($q) => $q->where('project_approvals.status', 'pending'),
                'milestones as completed_milestones_count' => fn ($q) => $q->whereIn('project_milestones.status', ['completed', 'approved']),
                'milestones as total_milestones_count',
            ])
            ->where('client_visible', true)
            ->with(['owner:id,name', 'manager:id,name'])
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->get();

        if ($projects->count() === 1) {
            return redirect()->route('review.project', $projects->first());
        }

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
            'comments' => fn ($query) => $query->with('user:id,name')->latest()->limit(20),
            'reviewSessions' => fn ($query) => $query->withCount('items')->latest(),
            'reviewItems' => fn ($query) => $query
                ->with(['session:id,title,project_id', 'createdBy:id,name'])
                ->whereIn('status', ['open', 'in_progress', 'fixed'])
                ->latest()
                ->limit(10),
        ]);

        $project->loadCount([
            'reviewItems as open_review_items_count' => fn ($q) => $q->whereIn('review_items.status', ['open', 'in_progress']),
            'reviewItems as resolved_review_items_count' => fn ($q) => $q->whereIn('review_items.status', ['fixed', 'approved']),
            'approvals as pending_approvals_count' => fn ($q) => $q->where('project_approvals.status', 'pending'),
        ]);

        return view('review.project', compact('project'));
    }

    public function storeProjectComment(Request $request, Project $project): RedirectResponse
    {
        abort_unless(ProjectHub::visibleProjectsFor(auth()->user())->whereKey($project->id)->exists(), 403);

        $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        ProjectComment::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'body' => $request->string('body')->trim()->toString(),
        ]);

        return redirect()->route('review.project', $project)->with('success', 'Comment posted to the project conversation.');
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
