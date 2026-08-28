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
            'shipments' => [
                'label'       => 'Shipments',
                'description' => 'Vendor shipments and receipt tracking.',
                'group'       => 'Inventory',
                'order'       => 36,
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
                'label'       => 'AI Vision & Matching',
                'description' => 'Pallet-sheet vision parsing, receiving item matching, and product embeddings. Requires Ollama. (Advanced)',
                'group'       => 'AI',
                'order'       => 60,
            ],
            'timekeeping' => [
                'label'       => 'Timekeeping',
                'description' => 'Employee time tracking, shift logs, and labor cost reporting. (Owner only)',
                'group'       => 'Operations',
                'order'       => 42,
            ],
            'fulfillment' => [
                'label'       => 'Fulfillment Center',
                'description' => 'Shipping status and tracking for sold items, scoped per fulfillment team member to their assigned shows.',
                'group'       => 'Operations',
                'order'       => 44,
            ],
        ];
    }

    public static function defaultEnabledSlugs(): array
    {
        return ['streams', 'payouts', 'inventory', 'purchasing', 'shipments', 'operations', 'reporting'];
    }

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

    public static function normalizeEnabledSlugs(array $slugs): array
    {
        $valid = array_keys(static::definitions());
        return array_values(array_unique(array_intersect($valid, $slugs)));
    }

    public static function flushMemo(): void
    {
        static::$memoizedSlugs = null;
    }

    public static function presets(): array
    {
        return [
            'basics' => [
                'label'       => 'Month 1 — Basics',
                'description' => 'Just shows, payouts, and inventory — everything else stays hidden.',
                'slugs'       => ['streams', 'payouts', 'inventory'],
            ],
            'standard' => [
                'label'       => 'Standard Ops',
                'description' => 'Basics plus purchasing/receiving, operations, and reporting.',
                'slugs'       => static::defaultEnabledSlugs(),
            ],
            'everything' => [
                'label'       => 'Everything',
                'description' => 'All modules enabled, including AI and timekeeping.',
                'slugs'       => array_keys(static::definitions()),
            ],
        ];
    }

    public static function visibleNavigationGroups(): array
    {
        // A saved Navigation Manager layout becomes the presentation order.
        // Defaults are appended so newly-added code pages/groups never disappear.
        $custom = collect(NavLayout::config()['groups'] ?? [])
            ->sortBy('sort')
            ->pluck('label')
            ->filter()
            ->values()
            ->all();

        $defaults = count(static::visibleOperationalGroups()) <= 1
            ? []
            : static::visibleOperationalGroups();

        $groups = array_values(array_unique(array_merge($custom, $defaults, ['Settings'])));
        return $groups;
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

    public static function labels(): array
    {
        return array_map(
            fn (array $definition): string => $definition['label'],
            static::definitions()
        );
    }
}
