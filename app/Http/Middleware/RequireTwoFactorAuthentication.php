<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTwoFactorAuthentication
{
    private const SESSION_KEY = 'vx_2fa_verified';

    // Paths that must be reachable before the 2FA challenge is completed
    private const EXEMPT_PATHS = [
        'admin/two-factor-verify',
        'admin/logout',
        'admin/login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user) {
            return $next($request);
        }

        // User has 2FA confirmed on their account
        if ($user->two_factor_confirmed_at === null) {
            return $next($request);
        }

        // Already verified this session
        if ($request->session()->get(self::SESSION_KEY)) {
            return $next($request);
        }

        // Allow the challenge page and its POST target through
        foreach (self::EXEMPT_PATHS as $path) {
            if ($request->is($path) || $request->is($path . '/*')) {
                return $next($request);
            }
        }

        return redirect()->route('filament.admin.pages.two-factor-verify');
    }
}
