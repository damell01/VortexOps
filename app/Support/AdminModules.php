<?php

namespace App\Support;

use App\Models\Setting;

class AdminModules
{
    private static ?array $memoizedSlugs    = null;
    private static ?array $memoizedFeatures = null;

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
                'label'       => 'AI Assistant',
                'description' => 'Vortex AI assistant and AI activity logs. Requires Ollama. (Advanced)',
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

    /**
     * Individual features within each module.
     * Disabling a feature hides that specific page/resource while leaving the rest of its module visible.
     *
     * @return array<string, array{label: string, description: string, module: string, order: int}>
     */
    public static function featureDefinitions(): array
    {
        return [
            // ── Streams ──────────────────────────────────────────────────────────
            'shows'               => ['label' => 'Shows',               'description' => 'Show list, creation, and the full show workflow.',     'module' => 'streams',    'order' => 10],
            'deduction_requests'  => ['label' => 'Deduction Requests',  'description' => 'AI deduction approval and inventory write-off flow.',   'module' => 'streams',    'order' => 20],
            'show_ingestion_logs' => ['label' => 'Ingestion Logs',      'description' => 'Whatnot import history and error logs.',               'module' => 'streams',    'order' => 30],
            'whatnot_sync'        => ['label' => 'Whatnot Sync',        'description' => 'Sync dashboard: pull shows, orders, and buyers from Whatnot.', 'module' => 'streams', 'order' => 35],
            'whatnot_buyers'      => ['label' => 'Buyers',              'description' => 'Buyer profiles aggregated from Whatnot order history.',        'module' => 'streams', 'order' => 36],
            'whatnot_sync_history'=> ['label' => 'Sync History',        'description' => 'Log of all Whatnot sync runs with per-resource counters.',     'module' => 'streams', 'order' => 37],
            'streamer_log'        => ['label' => 'Streamer Log',        'description' => 'Per-show log a streamer fills in, then admin reviews & approves.', 'module' => 'streams', 'order' => 15],

            // ── Payouts ──────────────────────────────────────────────────────────
            'payouts'             => ['label' => 'Payouts',             'description' => 'Individual payout records and calculations.',          'module' => 'payouts',    'order' => 10],
            'weekly_pay_runs'     => ['label' => 'Weekly Pay Runs',     'description' => 'Batch pay run creation and ADP submission.',           'module' => 'payouts',    'order' => 20],
            'profit_share_packet' => ['label' => 'Profit Share Packet', 'description' => 'Printable per-streamer profit-share statement packet.', 'module' => 'payouts',    'order' => 30],
            'streamer_statement'  => ['label' => 'Streamer Statements', 'description' => 'Per-streamer earnings statements.',                     'module' => 'payouts',    'order' => 40],
            'shipping_surcharge'  => ['label' => 'Shipping Surcharge',  'description' => 'Per-package shipping surcharge tracking.',              'module' => 'payouts',    'order' => 50],

            // ── Inventory ────────────────────────────────────────────────────────
            'inventory_items'     => ['label' => 'Inventory Items',     'description' => 'Product catalog, stock operations, and WAC tracking.', 'module' => 'inventory',  'order' => 10],
            'inventory_locations' => ['label' => 'Locations',           'description' => 'Warehouse location management.',                       'module' => 'inventory',  'order' => 20],
            'inventory_stock'     => ['label' => 'Stock Levels',        'description' => 'Current stock view across all locations.',             'module' => 'inventory',  'order' => 30],
            'inventory_movements' => ['label' => 'Movement Log',        'description' => 'Full audit trail of every inventory change.',          'module' => 'inventory',  'order' => 40],
            'inventory_scanner'   => ['label' => 'Inventory Scanner',   'description' => 'Barcode / SKU lookup and quick stock adjustments.',    'module' => 'inventory',  'order' => 50],

            // ── Purchasing ───────────────────────────────────────────────────────
            'vendors'             => ['label' => 'Vendors',             'description' => 'Vendor profiles and contact management.',              'module' => 'purchasing', 'order' => 10],
            'pallets'             => ['label' => 'Pallets & Receiving', 'description' => 'Pallet creation and case-level receiving workflow.',   'module' => 'purchasing', 'order' => 20],
            'receiving_sessions'  => ['label' => 'Receiving Sessions',  'description' => 'Session-based receiving history.',                     'module' => 'purchasing', 'order' => 30],
            'receiving_analytics' => ['label' => 'Receiving Analytics', 'description' => 'Receiving performance dashboards and metrics.',        'module' => 'purchasing', 'order' => 40],
            'catalog_intelligence'=> ['label' => 'Catalog Intelligence','description' => 'Product health scores and catalog quality metrics.',   'module' => 'purchasing', 'order' => 50],
            'duplicate_detector'  => ['label' => 'Duplicate Detector',  'description' => 'Find and merge duplicate product records.',            'module' => 'purchasing', 'order' => 60],
            'receiving_guide'     => ['label' => 'Receiving Guide',     'description' => 'How-to documentation for the receiving workflow.',     'module' => 'purchasing', 'order' => 70],

            // ── Operations ───────────────────────────────────────────────────────
            'streamers'           => ['label' => 'Streamers',           'description' => 'Streamer profiles, rates, and channel routing.',       'module' => 'operations', 'order' => 10],
            'streamer_loans'      => ['label' => 'Streamer Loans',      'description' => 'Loan balances and repayment tracking.',                'module' => 'operations', 'order' => 20],
            'whatnot_channels'    => ['label' => 'Whatnot Channels',    'description' => 'Channel configuration for show imports.',              'module' => 'operations', 'order' => 30],
            'feedback_tickets'    => ['label' => 'Feedback Tickets',    'description' => 'Client feedback submissions and status tracking.',     'module' => 'operations', 'order' => 40],

            // ── Reporting ────────────────────────────────────────────────────────
            'reports'             => ['label' => 'Reports & Analytics', 'description' => 'Revenue, payout trends, and operational summaries.',   'module' => 'reporting',  'order' => 10],
            'streamer_analytics'  => ['label' => 'Streamer Analytics',  'description' => 'Per-streamer performance analytics dashboard.',        'module' => 'reporting',  'order' => 20],
            'ledger'              => ['label' => 'Ledger',              'description' => 'Financial ledger and Whatnot ledger entries.',         'module' => 'reporting',  'order' => 30],

            // ── AI ───────────────────────────────────────────────────────────────
            'ai_assistant'        => ['label' => 'AI Assistant',        'description' => 'Full-screen AI chat and context-aware assistance.',    'module' => 'ai',         'order' => 10],

            // ── Timekeeping ──────────────────────────────────────────────────────
            'timekeeping_page'    => ['label' => 'Timekeeping',         'description' => 'Employee time tracking and shift logs.',               'module' => 'timekeeping','order' => 10],
        ];
    }

    /**
     * Feature slugs within a given module, sorted by order.
     *
     * @return array<string, array{label: string, description: string, module: string, order: int}>
     */
    public static function featuresForModule(string $moduleSlug): array
    {
        return array_filter(
            static::featureDefinitions(),
            fn (array $def) => $def['module'] === $moduleSlug
        );
    }

    // ── Module-level gating ───────────────────────────────────────────────────

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

    // ── Feature-level gating ─────────────────────────────────────────────────

    /**
     * Returns enabled feature slugs.
     * Default: all features enabled (empty/missing setting = everything on).
     *
     * @return array<int, string>
     */
    public static function enabledFeatures(): array
    {
        if (static::$memoizedFeatures !== null) {
            return static::$memoizedFeatures;
        }

        $allFeatures = array_keys(static::featureDefinitions());

        try {
            $raw = Setting::get('enabled_admin_features');
        } catch (\Throwable) {
            return static::$memoizedFeatures = $allFeatures;
        }

        if (! is_string($raw) || trim($raw) === '') {
            return static::$memoizedFeatures = $allFeatures;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return static::$memoizedFeatures = $allFeatures;
        }

        return static::$memoizedFeatures = array_values(
            array_intersect($allFeatures, $decoded)
        );
    }

    public static function isFeatureEnabled(string $featureSlug): bool
    {
        return in_array($featureSlug, static::enabledFeatures(), true);
    }

    public static function flushMemo(): void
    {
        static::$memoizedSlugs    = null;
        static::$memoizedFeatures = null;
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
