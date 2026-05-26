<?php

namespace App\Support;

use App\Models\Setting;

class AdminModules
{
    /**
     * @return array<string, array{label: string, description: string, group: string, order: int}>
     */
    public static function definitions(): array
    {
        return [
            'projects' => [
                'label' => 'Project Workspace',
                'description' => 'Project hub, roadmap, milestones, approvals, comments, and rollout updates.',
                'group' => 'Project Delivery',
                'order' => 10,
            ],
            'reviews' => [
                'label' => 'Review & Feedback',
                'description' => 'Client review portal, review mode, feedback sessions, and annotated review items.',
                'group' => 'Project Delivery',
                'order' => 15,
            ],
            'streams' => [
                'label' => 'Streams',
                'description' => 'Shows and pending approvals for operational review.',
                'group' => 'Streams',
                'order' => 20,
            ],
            'payouts' => [
                'label' => 'Payouts & Pay Runs',
                'description' => 'Payout records, pay runs, and reconciliation outputs.',
                'group' => 'Payouts & Pay Runs',
                'order' => 30,
            ],
            'inventory' => [
                'label' => 'Inventory',
                'description' => 'Items, locations, stock levels, and movement logs.',
                'group' => 'Inventory',
                'order' => 40,
            ],
            'operations' => [
                'label' => 'Operations',
                'description' => 'Streamers, channels, and other supporting ops tools.',
                'group' => 'Operations',
                'order' => 50,
            ],
            'ai' => [
                'label' => 'AI',
                'description' => 'Vortex Assistant and AI activity logs.',
                'group' => 'AI',
                'order' => 60,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function defaultEnabledSlugs(): array
    {
        return array_keys(static::definitions());
    }

    /**
     * @return array<int, string>
     */
    public static function enabledSlugs(): array
    {
        try {
            $raw = Setting::get('enabled_admin_modules');
        } catch (\Throwable) {
            return static::defaultEnabledSlugs();
        }

        if (! is_string($raw) || trim($raw) === '') {
            return static::defaultEnabledSlugs();
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return static::defaultEnabledSlugs();
        }

        return static::normalizeEnabledSlugs($decoded);
    }

    /**
     * @param  array<int, string>  $slugs
     * @return array<int, string>
     */
    public static function normalizeEnabledSlugs(array $slugs): array
    {
        $normalized = [];

        foreach ($slugs as $slug) {
            if ($slug === 'project_hub') {
                $normalized[] = 'projects';
                $normalized[] = 'reviews';

                continue;
            }

            $normalized[] = $slug;
        }

        if (in_array('reviews', $normalized, true) && ! in_array('projects', $normalized, true)) {
            $normalized[] = 'projects';
        }

        $valid = array_keys(static::definitions());

        return array_values(array_unique(array_intersect($valid, $normalized)));
    }

    public static function isEnabled(string $slug): bool
    {
        if ($slug === 'ai' && ! Setting::getBool('ai_enabled', false)) {
            return false;
        }

        return in_array($slug, static::enabledSlugs(), true);
    }

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
