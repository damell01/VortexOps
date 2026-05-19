<?php

namespace App\Http\Controllers;

use App\Models\ReviewItem;
use App\Models\ReviewItemComment;
use App\Models\ReviewSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    // GET /admin/review/sessions
    public function sessions(): JsonResponse
    {
        $sessions = ReviewSession::where('status', '!=', 'closed')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'status', 'created_at']);

        return response()->json($sessions);
    }

    // POST /admin/review/sessions
    public function storeSession(Request $request): JsonResponse
    {
        $request->validate(['title' => 'required|string|max:255']);

        $session = ReviewSession::create([
            'title'      => $request->title,
            'status'     => 'open',
            'created_by' => Auth::id(),
        ]);

        return response()->json($session, 201);
    }

    // GET /admin/review/items?session_id=X
    public function items(Request $request): JsonResponse
    {
        $query = ReviewItem::with(['createdBy:id,name', 'assignedTo:id,name', 'comments'])
            ->orderByDesc('created_at');

        if ($request->session_id) {
            $query->where('review_session_id', $request->session_id);
        }

        return response()->json($query->get());
    }

    // POST /admin/review/items
    public function storeItem(Request $request): JsonResponse
    {
        $request->validate([
            'review_session_id' => 'required|exists:review_sessions,id',
            'page_url'          => 'required|string|max:1000',
            'page_title'        => 'nullable|string|max:255',
            'screenshot'        => 'nullable|string',
            'fabric_json'       => 'nullable|string',
            'comment'           => 'nullable|string|max:5000',
            'type'              => 'nullable|in:annotation,bug,suggestion,question',
            'priority'          => 'nullable|in:low,normal,high',
        ]);

        $item = ReviewItem::create([
            'review_session_id' => $request->review_session_id,
            'page_url'          => $request->page_url,
            'page_title'        => $request->page_title,
            'screenshot'        => $request->screenshot,
            'fabric_json'       => $request->fabric_json,
            'comment'           => $request->comment,
            'type'              => $request->type ?? 'annotation',
            'status'            => 'open',
            'priority'          => $request->priority ?? 'normal',
            'created_by'        => Auth::id(),
        ]);

        return response()->json($item->load('createdBy:id,name'), 201);
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

        $comment = ReviewItemComment::create([
            'review_item_id' => $item->id,
            'user_id'        => Auth::id(),
            'body'           => $request->body,
        ]);

        return response()->json($comment->load('user:id,name'), 201);
    }
}
