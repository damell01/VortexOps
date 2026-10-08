<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->must_change_password
            && ! $request->routeIs('account.password.*', 'filament.admin.auth.logout')) {
            return redirect()->route('account.password.edit');
        }

        return $next($request);
    }
}
