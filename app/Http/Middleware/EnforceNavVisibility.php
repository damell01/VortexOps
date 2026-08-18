<?php

namespace App\Http\Middleware;

use App\Filament\Resources\RoleResource;
use App\Support\NavVisibility;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces the per-role page visibility set on Roles & Permissions.
 *
 * That setting used to be advisory. Hiding a page removed its sidebar link but
 * left the URL reachable, because the block lived in each class's canAccess()
 * and only the ones using HasModuleAccess actually had it — any class that
 * wrote its own canAccess() replaced the trait's and dropped the check with it,
 * and a page with neither was never gated at all. Twenty-one classes overrode
 * it and thirteen had no gate; every one of them opened for a role the setting
 * said could not see it.
 *
 * Enforcing it here instead of in each class means a page cannot forget: the
 * check runs for whatever the route resolves to, including pages added later.
 * Per-class canAccess() rules stay as they are — they express business scoping
 * ("streamers see their own shows"), which is a different question from whether
 * a role is allowed the page at all. Both must pass.
 */
class EnforceNavVisibility
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // isHiddenForUser() already exempts the owner and role-less users, but
        // returning early keeps this off the path entirely for guests.
        if (! $user) {
            return $next($request);
        }

        $class = $this->governingClass($request);

        // Only pages the Roles screen can actually grant are subject to this.
        // Checking anything else denies it: an allow-list refuses whatever it
        // does not name, and it never names the logout route, Livewire's
        // update endpoint, or a resource's List/Edit page — only the resource
        // itself. Enforcing on those 403'd logout and every resource page.
        if ($class === null || ! $this->isRoleControlled($class)) {
            return $next($request);
        }

        if (NavVisibility::isHiddenForUser($class, $user)) {
            abort(403, 'This page is not available for your role.');
        }

        return $next($request);
    }

    /**
     * The one class a route's access is decided by.
     *
     * Visibility is configured against the resource — that is what the sidebar
     * lists — while the route resolves to one of its pages, so a resource page
     * defers to its parent. Hiding "Inventory Items" therefore closes
     * List/Create/Edit/View beneath it, and granting it opens all four.
     */
    private function governingClass(Request $request): ?string
    {
        $controller = $request->route()?->getAction('controller');

        if (! is_string($controller) || ! class_exists($controller)) {
            return null;
        }

        if (method_exists($controller, 'getResource')) {
            try {
                $resource = $controller::getResource();

                if (is_string($resource) && $resource !== '') {
                    return $resource;
                }
            } catch (\Throwable) {
                // A page that declares getResource() but cannot resolve one is
                // governed by its own class.
            }
        }

        return $controller;
    }

    /**
     * Whether this class is one the Roles screen offers as a toggle.
     *
     * Anything else is infrastructure — logout, profile, Livewire's endpoints —
     * and is not the owner's to grant or withhold. Failing open here is
     * deliberate: the alternative when the page list cannot be resolved is
     * denying every request, which locks the application shut.
     */
    private function isRoleControlled(string $class): bool
    {
        try {
            return in_array($class, RoleResource::roleControlledPages(), true);
        } catch (\Throwable) {
            return false;
        }
    }
}
