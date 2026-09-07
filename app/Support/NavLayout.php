<?php

namespace App\Support;

use App\Models\Setting;

final class NavLayout
{
    private const SETTING_KEY = 'navigation_layout_v1';

    private static ?array $memo = null;

    /**
     * @return array{groups: array<int,array{id:string,label:string,sort:int}>, items: array<string,array{group:string,sort:int,label?:string|null}>}
     */
    public static function config(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            $decoded = json_decode((string) Setting::get(self::SETTING_KEY, '{}'), true);
        } catch (\Throwable) {
            $decoded = [];
        }

        if (! is_array($decoded)) {
            $decoded = [];
        }

        return self::$memo = [
            'groups' => is_array($decoded['groups'] ?? null) ? array_values($decoded['groups']) : [],
            'items'  => is_array($decoded['items'] ?? null) ? $decoded['items'] : [],
        ];
    }

    public static function set(array $groups, array $items): void
    {
        $cleanGroups = [];
        foreach ($groups as $i => $group) {
            $label = trim((string) ($group['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $cleanGroups[] = [
                'id'    => (string) ($group['id'] ?? str($label)->slug()),
                'label' => $label,
                'sort'  => (int) ($group['sort'] ?? (($i + 1) * 10)),
            ];
        }

        $cleanItems = [];
        foreach ($items as $class => $item) {
            if (! is_string($class) || ! is_array($item)) {
                continue;
            }

            $group = trim((string) ($item['group'] ?? ''));
            if ($group === '') {
                continue;
            }

            $cleanItems[$class] = [
                'group' => $group,
                'sort'  => (int) ($item['sort'] ?? 0),
                'label' => isset($item['label']) ? trim((string) $item['label']) : null,
            ];
        }

        Setting::set(self::SETTING_KEY, json_encode(['groups' => $cleanGroups, 'items' => $cleanItems]));
        self::$memo = null;
    }

    public static function item(string $class): ?array
    {
        $saved = self::config()['items'][$class] ?? null;
        $saved = is_array($saved) ? $saved : null;

        // Keep the main operational navigation stable even when an older saved
        // Navigation Manager layout still says "Streams". Technical utility
        // pages remain configurable/access-controlled but are hidden elsewhere.
        $canonical = match (class_basename($class)) {
            'StreamsOverview' => ['group' => 'Shows', 'sort' => 10, 'label' => 'Shows Overview'],
            'Shows' => ['group' => 'Shows', 'sort' => 20, 'label' => 'Shows'],
            'ShowShipments' => ['group' => 'Shows', 'sort' => 30, 'label' => 'Show Shipments'],
            'FulfillmentCenter' => ['group' => 'Fulfillment', 'sort' => 10, 'label' => 'Fulfillment Center'],
            default => null,
        };

        if ($canonical) {
            return array_merge($saved ?? [], $canonical);
        }

        return $saved;
    }

    /**
     * Apply saved presentation overrides to Filament NavigationItem instances.
     * Access is deliberately left to NavVisibility / RoleAccess.
     */
    public static function apply(string $class, array $navigationItems): array
    {
        $override = self::item($class);
        if (! $override) {
            return $navigationItems;
        }

        foreach ($navigationItems as $item) {
            if (method_exists($item, 'group')) {
                $item->group($override['group'] ?? null);
            }
            if (method_exists($item, 'sort')) {
                $item->sort((int) ($override['sort'] ?? 0));
            }
            if (! empty($override['label']) && method_exists($item, 'label')) {
                $item->label($override['label']);
            }
        }

        return $navigationItems;
    }

    /** @return array<int,string> */
    public static function visibleNavigationGroups(): array
    {
        $configured = collect(self::config()['groups'])
            ->sortBy('sort')
            ->pluck('label')
            ->filter()
            ->values()
            ->all();

        $defaults = AdminModules::visibleNavigationGroups();
        $groups = array_values(array_unique(array_merge($configured, $defaults)));

        if (! in_array('Settings', $groups, true)) {
            $groups[] = 'Settings';
        }

        return $groups;
    }

    public static function flushMemo(): void
    {
        self::$memo = null;
    }
}
