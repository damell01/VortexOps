<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail closed unless the owner preview or explicit authenticated team rollout is enabled.
 * Registered globally so both package web and API routes are covered.
 */
class GuardChatifyPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin/communications', 'admin/communications/*', 'chatify', 'chatify/*', 'api/chatify', 'api/chatify/*')) {
            abort_unless(config('chatify-preview.available', false), 404);
        }

        if ($request->is('chatify', 'chatify/*', 'api/chatify', 'api/chatify/*')) {
            abort_unless(
                (config('chatify-preview.enabled', false) === true && \App\Services\TeamCommunications::canAccess(auth()->user()))
                    || (config('chatify-preview.team_enabled', false) === true && auth()->check()),
                403,
                'Messaging is not enabled for this account.'
            );
        }

        // In the privileged pilot, never create a Chatify conversation with a
        // teammate who cannot access it. Enforce this server-side as well as in
        // the people picker, including when the API is called directly.
        if (config('chatify-preview.privileged_preview_enabled', false)
            && ! config('chatify-preview.team_enabled', false)
            && $request->isMethod('post')) {
            $path = trim($request->path(), '/');
            $ids = null;
            $isRecipientMutation = false;
            if ($path === 'api/chatify/v1/conversations/direct') {
                $isRecipientMutation = true;
                $ids = [$request->input('user_id')];
            } elseif ($path === 'api/chatify/v1/conversations/group'
                || preg_match('#^api/chatify/v1/conversations/[^/]+/participants$#', $path)) {
                $isRecipientMutation = true;
                $ids = $request->input('user_ids');
            }

            if ($isRecipientMutation) {
                abort_unless(is_array($ids) && count($ids) > 0 && count($ids) <= 49, 403);
                $ids = array_map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]), $ids);
                abort_unless(! in_array(false, $ids, true) && count(array_unique($ids)) === count($ids), 403);
                $allowed = \App\Services\TeamCommunications::previewRecipients(\App\Models\User::query())
                    ->whereKey($ids)->count();
                abort_unless($allowed === count($ids), 403, 'Only owner and admin accounts may join pilot conversations.');
            }
        }

        return $next($request);
    }
}
