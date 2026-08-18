<?php

namespace App\Filament\Plugins;

use App\Support\NavVisibility;
use Awcodes\QuickCreate\QuickCreatePlugin;

/**
 * QuickCreate, restricted to what the signed-in user may actually reach.
 *
 * The packaged plugin builds its menu from every resource on the panel and
 * filters only on canCreate(). That misses two things: a resource hidden for
 * the user's role on Roles & Permissions still appeared in the + menu, and so
 * did resources the user cannot open at all — a non-owner admin was offered
 * "create role", which 403s on click.
 *
 * Filtering here rather than through the plugin's own getResourcesUsing() hook,
 * because QuickCreatePlugin::boot() reassigns that closure to the panel's full
 * resource list after configuration runs, so anything set at config time is
 * overwritten. Overriding the getter cannot be undone the same way.
 */
class ScopedQuickCreatePlugin extends QuickCreatePlugin
{
    public function getResources(): array
    {
        return array_values(array_filter(
            parent::getResources(),
            fn (array $entry): bool => $this->isReachable($entry['resource_name'] ?? null),
        ));
    }

    private function isReachable(?string $resource): bool
    {
        if (! is_string($resource) || ! class_exists($resource)) {
            return false;
        }

        try {
            if (! $resource::canAccess()) {
                return false;
            }
        } catch (\Throwable) {
            // A resource that cannot answer is not something to offer.
            return false;
        }

        return ! NavVisibility::isHiddenForUser($resource, auth()->user());
    }
}
