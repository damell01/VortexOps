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

        return $next($request);
    }
}
