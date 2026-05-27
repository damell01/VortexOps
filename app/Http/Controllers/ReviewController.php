<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ReviewItem;
use App\Models\ReviewItemComment;
use App\Models\ReviewSession;
use App\Modules\ProjectHub\Support\ProjectHub;
use App\Support\ReviewScreenshotStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ReviewController extends Controller
{
    // GET /admin/review/sessions
    public function sessions(): JsonResponse
    {
        $sessions = ReviewSession::where('status', '!=', 'closed')
            ->with('project:id,name')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get(['id', 'project_id', 'title', 'status', 'created_at']);

        return response()->json($sessions);
    }

    // POST /admin/review/sessions
    public function storeSession(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'project_id' => 'nullable|exists:projects,id',
        ]);

        try {
            $projectId = $request->integer('project_id') ?: ProjectHub::defaultProjectId($request->user());

            $session = ReviewSession::create([
                'project_id' => $projectId,
                'title'      => $request->string('title')->toString(),
                'status'     => 'open',
                'created_by' => Auth::id(),
            ]);

            return response()->json($session, 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to create the review session right now.',
            ], 500);
        }
    }

    // GET /admin/review/items?session_id=X
    public function items(Request $request): JsonResponse
    {
        $query = ReviewItem::with(['createdBy:id,name', 'assignedTo:id,name', 'comments'])
            ->orderByDesc('created_at');

        if ($request->session_id) {
            $query->where('review_session_id', $request->session_id);
        }

        if ($request->project_id) {
            $query->whereHas('session', fn (Builder $sessionQuery) => $sessionQuery->where('project_id', $request->project_id));
        }

        return response()->json($query->get());
    }

    // POST /admin/review/items
    public function storeItem(Request $request): JsonResponse
    {
        $request->validate([
            'review_session_id' => 'nullable|exists:review_sessions,id',
            'page_url'          => 'required|string|max:4000',
            'page_title'        => 'nullable|string|max:255',
            'screenshot'        => 'nullable|string',
            'fabric_json'       => 'nullable|string',
            'comment'           => 'nullable|string|max:5000',
            'type'              => 'nullable|in:annotation,bug,suggestion,question',
            'priority'          => 'nullable|in:low,normal,high',
        ]);

        try {
            $reviewSessionId = $this->resolveReviewSessionId($request);

            $item = ReviewItem::create([
                'review_session_id' => $reviewSessionId,
                'page_url'          => $this->normalizePageUrl($request->string('page_url')->toString()),
                'page_title'        => $request->string('page_title')->toString() ?: null,
                'screenshot'        => ReviewScreenshotStore::persist($request->string('screenshot')->toString()),
                'fabric_json'       => $request->string('fabric_json')->toString() ?: null,
                'comment'           => $request->string('comment')->toString() ?: null,
                'type'              => $request->string('type')->toString() ?: 'annotation',
                'status'            => 'open',
                'priority'          => $request->string('priority')->toString() ?: 'normal',
                'created_by'        => Auth::id(),
            ]);

            return response()->json($item->load('createdBy:id,name'), 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Unable to save the review item right now.',
            ], 500);
        }
    }

    // PATCH /admin/review/items/{item}
    public function updateItem(Request $request, ReviewItem $item): JsonResponse
    {
        $request->validate([
            'status'      => 'sometimes|in:open,in_progress,fixed,approved,rejected,wont_fix',
            'priority'    => 'sometimes|in:low,normal,high',
            'type'        => 'sometimes|in:annotation,bug,suggestion,question',
            'assigned_to' => 'sometimes|nullable|exists:users,id',
            'comment'     => 'sometimes|nullable|string|max:5000',
        ]);

        $item->update($request->only(['status', 'priority', 'type', 'assigned_to', 'comment']));

        return response()->json($item->fresh());
    }

    // DELETE /admin/review/items/{item}
    public function deleteItem(ReviewItem $item): JsonResponse
    {
        $item->delete();

        return response()->json(['deleted' => true]);
    }

    // POST /admin/review/items/{item}/comments
    public function storeComment(Request $request, ReviewItem $item): JsonResponse
    {
        $request->validate(['body' => 'required|string|max:2000']);

        try {
            $comment = ReviewItemComment::create([
                'review_item_id' => $item->id,
                'user_id'        => Auth::id(),
                'body'           => $request->string('body')->toString(),
            ]);

            return response()->json($comment->load('user:id,name'), 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to save the comment right now.',
            ], 500);
        }
    }

    private function normalizePageUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '/';
        }

        return mb_substr($url, 0, 4000);
    }

    private function resolveReviewSessionId(Request $request): int
    {
        $sessionId = $request->integer('review_session_id');

        if ($sessionId > 0) {
            return $sessionId;
        }

        $existingSessionId = ReviewSession::query()
            ->where('status', '!=', 'closed')
            ->where('created_by', Auth::id())
            ->orderByDesc('created_at')
            ->value('id');

        if ($existingSessionId) {
            return (int) $existingSessionId;
        }

        $projectId = $request->integer('project_id') ?: ProjectHub::defaultProjectId($request->user());

        $session = ReviewSession::create([
            'project_id' => $projectId,
            'title' => 'Quick Review - ' . now()->format('M j, Y g:i A'),
            'status' => 'open',
            'created_by' => Auth::id(),
        ]);

        return (int) $session->id;
    }
}
