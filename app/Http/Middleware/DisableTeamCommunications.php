<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Stop disabled communications before package bindings, auth or database work. */
class DisableTeamCommunications
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('chatify-preview.available', false)
            && $request->is('admin/communications', 'admin/communications/*', 'chatify', 'chatify/*', 'api/chatify', 'api/chatify/*')) {
            abort(404);
        }

        return $next($request);
    }
}
