<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail closed on every Chatify endpoint until explicitly enabled for the owner.
 * Registered globally so both package web and API routes are covered.
 */
class GuardChatifyPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('chatify', 'chatify/*', 'api/chatify', 'api/chatify/*')) {
            abort_unless(
                config('chatify-preview.enabled', false) === true
                    && auth()->user()?->isOwner() === true,
                403,
                'Messaging preview is not enabled for this account.'
            );
        }

        return $next($request);
    }
}
