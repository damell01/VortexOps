<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * Turns each role's stored "pages this role cannot see" into "pages this role
 * can see".
 *
 * The two are only equivalent at a single moment in time. A hide-list grants
 * everything absent from it, so every page shipped after a role was last saved
 * was granted to that role automatically and the sidebar drifted away from the
 * Roles & Permissions screen with each release. An allow-list does not drift:
 * a page nobody has ticked is simply not granted.
 *
 * The conversion has to happen once, here, against the page list as it stands
 * now — doing it at read time would just re-derive the same permissive answer
 * every time a new page appeared.
 *
 * Roles with no stored hide-list are left alone. They have never been
 * configured, and writing an allow-list for them would mean deciding on their
 * behalf what they may see; they keep falling back to the old behaviour until
 * somebody saves them on the Roles screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hiddenByRole = json_decode(Setting::get('role_hidden_nav', '{}'), true) ?: [];

        if ($hiddenByRole === []) {
            return;
        }

        $allPages = $this->navigablePages();

        if ($allPages === []) {
            // Panels are not resolvable in every context a migration runs in
            // (a bare `migrate` on a fresh container, for one). Writing an
            // empty allow-list from an empty page list would hide the whole
            // application from every role, so do nothing instead.
            return;
        }

        $existing = json_decode(Setting::get('role_visible_nav', '{}'), true) ?: [];
        $visibleByRole = $existing;

        foreach ($hiddenByRole as $role => $hidden) {
            if (array_key_exists($role, $visibleByRole)) {
                continue; // already converted
            }

            $visibleByRole[$role] = array_values(array_diff($allPages, (array) $hidden));
        }

        Setting::set('role_visible_nav', json_encode($visibleByRole));
    }

    public function down(): void
    {
        // The hide-list it was derived from is untouched, so dropping this
        // restores the previous behaviour exactly.
        Setting::set('role_visible_nav', json_encode([]));
    }

    /**
     * The same list the Roles screen offers, not a second one assembled here.
     *
     * Building it independently produced a set one longer — pageOptions()
     * excludes the roles manager itself so a role cannot hide it, and this did
     * not — which would have granted that page to every converted role.
     *
     * @return array<int, class-string>
     */
    private function navigablePages(): array
    {
        try {
            \Filament\Facades\Filament::setCurrentPanel(
                \Filament\Facades\Filament::getPanel('admin')
            );

            return \App\Filament\Resources\RoleResource::roleControlledPages();
        } catch (\Throwable) {
            return [];
        }
    }
};
