<?php

namespace App\Support;

use App\Models\Setting;

class AdminModules
{
    private static ?array $memoizedSlugs = null;

    /**
     * @return array<string, array{label: string, description: string, group: string, order: int}>
     */
    public static function definitions(): array
    {
        return [
            'streams' => [
                'label'       => 'Streams',
                'description' => 'Shows and pending approvals for operational review.',
                'group'       => 'Streams',
                'order'       => 10,
            ],
            'payouts' => [
                'label'       => 'Payouts & Pay Runs',
                'description' => 'Payout records, pay runs, and reconciliation outputs.',
                'group'       => 'Payouts & Pay Runs',
                'order'       => 20,
            ],
            'inventory' => [
                'label'       => 'Inventory',
                'description' => 'Items, locations, stock levels, and movement logs.',
                'group'       => 'Inventory',
                'order'       => 30,
            ],
            'purchasing' => [
                'label'       => 'Purchasing & Receiving',
                'description' => 'Vendors, pallets, and the case-level receiving workflow.',
                'group'       => 'Inventory',
                'order'       => 35,
            ],
            'operations' => [
                'label'       => 'Operations',
                'description' => 'Streamers, channels, and other supporting ops tools.',
                'group'       => 'Operations',
                'order'       => 40,
            ],
            'reporting' => [
                'label'       => 'Reports & Analytics',
                'description' => 'Revenue, payout, and operational performance reports.',
                'group'       => 'Reports',
                'order'       => 45,
            ],
            'ai' => [
                'label'       => 'AI Mapping & Vision',
                'description' => 'AI show mapping, pallet-sheet vision parsing, and product embeddings. Requires Ollama. (Advanced)',
                'group'       => 'AI',
                'order'       => 60,
            ],
            'timekeeping' => [
                'label'       => 'Timekeeping',
                'description' => 'Employee time tracking, shift logs, and labor cost reporting. (Owner only)',
                'group'       => 'Operations',
                'order'       => 42,
            ],
        ];
    }

    // ── Module-level gating ───────────────────────────────────────────────────
    //
    // This is the only global on/off switch — a disabled module hides its pages
    // from navigation and blocks their routes for everyone, including the
    // owner. Anything finer-grained (which specific page a given role can see
    // within an enabled module) is handled per-role by NavVisibility, edited on
    // the Roles & Permissions page — there is deliberately no second,
    // module-level "features" toggle competing with it.

    /**
     * @return array<int, string>
     */
    public static function defaultEnabledSlugs(): array
    {
        return ['streams', 'payouts', 'inventory', 'purchasing', 'operations', 'reporting'];
    }

    /**
     * @return array<int, string>
     */
    public static function enabledSlugs(): array
    {
        if (static::$memoizedSlugs !== null) {
            return static::$memoizedSlugs;
        }

        try {
            $raw = Setting::get('enabled_admin_modules');
        } catch (\Throwable) {
            return static::$memoizedSlugs = static::defaultEnabledSlugs();
        }

        if (! is_string($raw) || trim($raw) === '') {
            return static::$memoizedSlugs = static::defaultEnabledSlugs();
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return static::$memoizedSlugs = static::defaultEnabledSlugs();
        }

        return static::$memoizedSlugs = static::normalizeEnabledSlugs($decoded);
    }

    public static function isEnabled(string $slug): bool
    {
        return in_array($slug, static::enabledSlugs(), true);
    }

    /**
     * @param  array<int, string>  $slugs
     * @return array<int, string>
     */
    public static function normalizeEnabledSlugs(array $slugs): array
    {
        $normalized = $slugs;
        $valid = array_keys(static::definitions());

        return array_values(array_unique(array_intersect($valid, $normalized)));
    }

    public static function flushMemo(): void
    {
        static::$memoizedSlugs = null;
    }

    // ── Navigation helpers ────────────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    public static function visibleNavigationGroups(): array
    {
        $groups = count(static::visibleOperationalGroups()) <= 1
            ? []
            : static::visibleOperationalGroups();

        $groups[] = 'Settings';

        return array_values(array_unique($groups));
    }

    public static function navigationGroupFor(string $slug): string|\UnitEnum|null
    {
        $definition = static::definitions()[$slug] ?? null;

        if (! $definition) {
            return null;
        }

        if (count(static::visibleOperationalGroups()) <= 1) {
            return null;
        }

        return $definition['group'];
    }

    /**
     * @return array<int, string>
     */
    public static function visibleOperationalGroups(): array
    {
        $groups = [];

        foreach (static::definitions() as $slug => $definition) {
            if (static::isEnabled($slug)) {
                $groups[] = $definition['group'];
            }
        }

        return array_values(array_unique($groups));
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(
            fn (array $definition): string => $definition['label'],
            static::definitions()
        );
    }
}
