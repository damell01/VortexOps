<?php

namespace App\Filament\Concerns;

use App\Support\NavVisibility;

/**
 * For pages that belong to no module but still answer to Roles & Permissions.
 *
 * Twenty pages had no access check of any kind — not this, not
 * HasModuleAccess, not a canAccess() of their own — so any signed-in user
 * could open them whatever the Roles screen said. A custom role granted only
 * Pallets still reached StreamerHub, Manager Hub, the scanners and the rest.
 *
 * The rule is the one NavVisibility already encodes, just nobody was asking
 * it here: a role with an explicit visible list gets what is on that list and
 * nothing else; a role without one is unrestricted, which is every built-in
 * role, so admin, streamer and fulfillment are unaffected.
 *
 * Kept separate from HasModuleAccess because these pages have no $moduleSlug
 * to gate on. A page that does belong to a module wants that trait instead.
 */
trait RespectsRoleVisibility
{
    public static function canAccess(): bool
    {
        return ! NavVisibility::isHiddenForUser(static::class, auth()->user());
    }

    // Deliberately no shouldRegisterNavigation(). These pages already get
    // their sidebar rules from elsewhere — HasAdminNavVisibility on some,
    // Filament's own default on the rest — and adding one here only produced
    // trait collisions. This trait answers one question: may you open it.
}
