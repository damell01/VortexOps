<?php

namespace App\Support;

use App\Models\Setting;

class NavVisibility
{
    /** Pages controlled by their own canAccess() rather than per-role nav allow-lists. */
    /**
     * Pages that sum up how profitable the businesses are. Everyone who can
     * see business financials sees them; the fulfillment admin does not.
     */
    public const BUSINESS_FINANCIAL_PAGES = [
        \App\Filament\Pages\ProfitLoss::class,
    ];

    /** The Fulfillment Center: open to every admin (see isHiddenForUser). */
    public const FULFILLMENT_PAGES = [
        \App\Filament\Resources\FulfillmentResource::class,
        \App\Filament\Pages\FulfillmentCenter::class,
        \App\Filament\Pages\FulfillmentHome::class,
        \App\Filament\Pages\FulfillmentStationBoard::class,
        \App\Filament\Pages\FulfillmentActivity::class,
        \App\Filament\Pages\FulfillmentSchedule::class,
        \App\Filament\Pages\FulfillmentReports::class,
        \App\Filament\Pages\FulfillmentOperations::class,
        \App\Filament\Pages\ShipmentOverview::class,
    ];

    public const ALWAYS_AVAILABLE = [
        // Owner-only preview, deliberately absent from non-owner role settings.
        \App\Filament\Pages\Communications::class,
        \App\Filament\Pages\HelpCenter::class,
        \App\Filament\Pages\DashboardImproved::class,
        \App\Filament\Pages\EditProfile::class,
        // Everyone's own notification choices, like their profile.
        \App\Filament\Pages\MyNotifications::class,
        // Everyone's own Pokédex — every signed-in trainer, whatever their role.
        \App\Filament\Pages\MyPokedex::class,
        // A charged streamer's own ledger charges; its canAccess() limits it to them.
        \App\Filament\Pages\MyLedgerCharges::class,
        \App\Filament\Pages\TwoFactorAuth::class,
        \App\Filament\Pages\TwoFactorVerify::class,
        // New show-first shipment page is part of the existing Streams workflow;
        // its own canAccess() still restricts it to admin/streamer users.
        \App\Filament\Pages\ShowShipments::class,
    ];

    /**
     * Pages a grant carries with it. Quick Add Stock does what the scanner's
     * Add Stock mode does, and the Tools tab links to it, so a role allowed
     * the scanner is allowed it too — saved role lists predate the page and
     * do not name it. An explicit Hide on the page still wins.
     */
    public const COMPANIONS = [
        \App\Filament\Pages\QuickAddStock::class => [\App\Filament\Pages\InventoryScanner::class],
        // The Fulfillment Center pages come with fulfillment access, so a saved
        // role list from before they existed does not lock the team out.
        \App\Filament\Pages\FulfillmentHome::class => [\App\Filament\Resources\FulfillmentResource::class, \App\Filament\Pages\FulfillmentOperations::class],
        \App\Filament\Pages\FulfillmentStationBoard::class => [\App\Filament\Resources\FulfillmentResource::class],
        \App\Filament\Pages\FulfillmentReports::class => [\App\Filament\Resources\FulfillmentResource::class, \App\Filament\Pages\FulfillmentActivity::class, \App\Filament\Pages\FulfillmentHome::class],
        \App\Filament\Pages\FulfillmentActivity::class => [\App\Filament\Resources\FulfillmentResource::class, \App\Filament\Pages\FulfillmentSchedule::class, \App\Filament\Pages\FulfillmentReports::class],
    ];

    /** Default page grants. Assistants have a fixed clock-and-paid-pay workspace. */
    public const ROLE_DEFAULTS = [
        'streamer_assistant' => [\App\Filament\Pages\DashboardImproved::class],
    ];

    private static ?array $roleMemo = null;
    private static ?array $visibleMemo = null;

    public static function visibleByRole(): array
    {
        return self::$visibleMemo ??= (json_decode(Setting::get('role_visible_nav', '{}'), true) ?: []);
    }

    public static function visibleForRole(string $role): array
    {
        $map = self::visibleByRole();
        // A role nobody has configured shows its defaults ticked on the Roles
        // screen, so saving it without changes keeps what the role already had.
        // The fulfillment admin has an admin's pages until someone sets theirs.
        return $map[$role] ?? ($role === 'fulfillment_admin' ? ($map['admin'] ?? (self::ROLE_DEFAULTS['admin'] ?? [])) : (self::ROLE_DEFAULTS[$role] ?? []));
    }

    public static function hasExplicitVisibility(string $role): bool
    {
        $map = self::visibleByRole();
        return array_key_exists($role, $map) || ($role === 'fulfillment_admin' && array_key_exists('admin', $map));
    }

    /**
     * Does any role this user holds explicitly name this page as visible?
     *
     * The difference between this and isHiddenForUser() is the difference
     * between a grant and the absence of a ban. A role with no explicit list
     * is "not hidden from" everything, which is why that method cannot be used
     * to open a page a resource has hardcoded shut — see RoleAccess.
     */
    public static function isExplicitlyGrantedTo(string $class, $user): bool
    {
        if (! $user || ! method_exists($user, 'getRoleNames')) {
            return false;
        }

        if (method_exists($user, 'isStreamerAssistant') && $user->isStreamerAssistant()) return false;

        foreach ($user->getRoleNames() as $role) {
            if (self::hasExplicitVisibility($role) && in_array($class, self::visibleForRole($role), true)) {
                return true;
            }
        }

        return false;
    }

    public static function setVisibleForRole(string $role, array $classes): void
    {
        $map = self::visibleByRole();
        $map[$role] = array_values(array_unique($classes));
        Setting::set('role_visible_nav', json_encode($map));
        self::$visibleMemo = null;
    }

    public static function hiddenByRole(): array
    {
        return self::$roleMemo ??= (json_decode(Setting::get('role_hidden_nav', '{}'), true) ?: []);
    }

    public static function hiddenForRole(string $role): array
    {
        $map = self::hiddenByRole();
        return $map[$role] ?? ($role === 'fulfillment_admin' ? ($map['admin'] ?? []) : []);
    }

    public static function setHiddenForRole(string $role, array $classes): void
    {
        $map = self::hiddenByRole();
        $map[$role] = array_values(array_unique($classes));
        Setting::set('role_hidden_nav', json_encode($map));
        self::$roleMemo = null;
    }

    public static function isHiddenForUser(string $class, $user): bool
    {
        if (! $user || (method_exists($user, 'isOwner') && $user->isOwner())) return false;
        if (method_exists($user, 'isStreamerAssistant') && $user->isStreamerAssistant()) {
            return ! in_array($class, [
                \App\Filament\Pages\DashboardImproved::class,
                \App\Filament\Pages\TwoFactorAuth::class,
                \App\Filament\Pages\TwoFactorVerify::class,
            ], true);
        }
        // The fulfillment admin sees everything an admin does except the
        // summaries of how profitable the businesses are.
        if (in_array($class, self::BUSINESS_FINANCIAL_PAGES, true)
            && method_exists($user, 'canSeeBusinessFinancials') && ! $user->canSeeBusinessFinancials()) {
            return true;
        }
        if (in_array($class, self::ALWAYS_AVAILABLE, true)) return false;
        // Admins run the floor with the fulfillment team, so every Fulfillment
        // Center page is theirs whatever a saved role list from before it says.
        // Only an explicit Hide on the Roles screen takes one away.
        if (in_array($class, self::FULFILLMENT_PAGES, true) && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
            $hiddenEverywhere = $roles !== [] && collect($roles)->every(fn ($role) => in_array($class, self::hiddenForRole($role), true));
            if (! $hiddenEverywhere) return false;
        }
        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        if (empty($roleNames)) return false;

        foreach ($roleNames as $role) {
            if (self::roleGrants($role, $class)) return false;
        }
        return true;
    }

    private static function roleGrants(string $role, string $class): bool
    {
        $navigationClass = is_subclass_of($class, \Filament\Resources\Pages\Page::class)
            ? $class::getResource()
            : $class;
        // Older saved allow-lists predate these core admin tools. Keep their
        // defaults, while an explicit Hide choice (including the Roles form)
        // always takes precedence.
        if (in_array($class, self::hiddenForRole($role), true) || in_array($navigationClass, self::hiddenForRole($role), true)) return false;
        if (in_array($role, ['admin', 'fulfillment_admin'], true) && in_array($navigationClass, [
            \App\Filament\Pages\AppSettings::class,
            \App\Filament\Pages\NotificationSettings::class,
            \App\Filament\Resources\UserResource::class,
            \App\Filament\Resources\StreamerLoanResource::class,
            \App\Filament\Resources\ActivityLogResource::class,
            \App\Filament\Resources\InventoryMovementResource::class,
            \App\Filament\Resources\InventoryItemResource::class,
            \App\Filament\Resources\PalletResource::class,
            \App\Filament\Resources\VendorResource::class,
            \App\Filament\Pages\PalletReceivingHistory::class,
            \App\Filament\Pages\InventoryGuide::class,
        ], true)) return true;
        if (! self::hasExplicitVisibility($role) && isset(self::ROLE_DEFAULTS[$role])) {
            return in_array($class, self::ROLE_DEFAULTS[$role], true)
                || in_array($navigationClass, self::ROLE_DEFAULTS[$role], true);
        }
        if (self::hasExplicitVisibility($role)) {
            $visible = self::visibleForRole($role);

            return in_array($class, $visible, true)
                || in_array($navigationClass, $visible, true)
                || array_intersect(self::COMPANIONS[$class] ?? [], $visible) !== [];
        }
        return ! in_array($class, self::hiddenForRole($role), true)
            && ! in_array($navigationClass, self::hiddenForRole($role), true);
    }

    public static function flushMemo(): void
    {
        self::$roleMemo = null;
        self::$readonlyMemo = null;
        self::$visibleMemo = null;
    }

    private static ?array $readonlyMemo = null;

    public static function readonlyByRole(): array
    {
        return self::$readonlyMemo ??= (json_decode(Setting::get('role_readonly_nav', '{}'), true) ?: []);
    }

    public static function readonlyForRole(string $role): array
    {
        $map = self::readonlyByRole();
        return $map[$role] ?? ($role === 'fulfillment_admin' ? ($map['admin'] ?? []) : []);
    }

    public static function setReadonlyForRole(string $role, array $classes): void
    {
        $map = self::readonlyByRole();
        $map[$role] = array_values(array_unique($classes));
        Setting::set('role_readonly_nav', json_encode($map));
        self::$readonlyMemo = null;
    }

    public static function isReadOnlyForUser(string $class, $user): bool
    {
        if (! $user || (method_exists($user, 'isOwner') && $user->isOwner())) return false;

        $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        if (empty($roleNames)) return false;

        $map = self::readonlyByRole();
        foreach ($roleNames as $role) {
            if (! in_array($class, self::readonlyForRole($role), true)) return false;
        }
        return true;
    }
}
