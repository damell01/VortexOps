<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * The little "?" help: a short how-to for a screen or a field, opened in a
 * centered modal (resources/js/help.js). Content lives here so it is written
 * once and every "?" for a topic says the same thing.
 *
 * Put a "?" anywhere with HelpTopics::button('topic') — it is Htmlable, so it
 * fits a Filament hint, a section's afterHeader or a page heading — or with
 * <x-vx-help topic="topic" /> in Blade.
 */
class HelpTopics
{
    /**
     * Every topic. A topic has a title and an intro, then any of: steps (a
     * numbered list), sections (headed lists, for the longer setup guides),
     * tips, and related (other topics, opened from the same modal).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return HelpContent::guides() + HelpContent::pages() + self::fields();
    }

    /** Help for one field or panel. @return array<string, array<string, mixed>> */
    public static function fields(): array
    {
        return [
            'locations.overview' => [
                'title' => 'Inventory locations',
                'intro' => 'A location is anywhere stock is tracked: a shelf, a storage room, a streamer\'s own supply. Each one says who sells from it.',
                'steps' => [
                    'Click "New location", name it, and say what it\'s for (Main Storage, Streamer Inventory, Returned…).',
                    'Choose who uses it: everyone, certain businesses, or one streamer.',
                    'Receive stock into it, or transfer stock in from another location.',
                ],
                'tips' => [
                    '"Give a streamer their own location" makes one for a person in a single step.',
                    '"Set up business storage" makes a business\'s own allocation, or one several businesses share.',
                    'A location holding stock can\'t be deleted. Move or zero the stock first.',
                ],
            ],
            'locations.type' => [
                'title' => 'What a location is for',
                'intro' => 'The type decides whether stock there can be sold.',
                'steps' => [
                    'Main Storage: where stock is received and kept. Streamers sell from it.',
                    'Streamer Inventory: stock one streamer holds and sells from.',
                    'Fulfillment: the packing and shipping area.',
                    'Returned and Damaged: held back for checking. Never sold from.',
                ],
            ],
            'locations.audience' => [
                'title' => 'Who uses a location',
                'intro' => 'Streamers sell from their own locations plus the storage their business uses.',
                'steps' => [
                    'Everyone: shared by every business that has no storage of its own.',
                    'Certain businesses: tick one for that business\'s own allocation (Estill\'s, say), or tick several to share it between them.',
                    'One streamer: only that person sells from it.',
                ],
                'tips' => [
                    'Once a business has storage of its own, its streamers stop selling from the shared Main Storage. Move their stock in straight after setting it up.',
                    'Changing a location back to Everyone un-shares it from every business.',
                ],
            ],
            'pay.shift_share' => [
                'title' => 'Hourly + shipments + profit share',
                'intro' => 'For hourly streamers (Estill\'s): paid for their time, for every shipment, and a share of what the show made after those costs.',
                'steps' => [
                    'Hourly: Hours Worked × their hourly rate.',
                    'Shipments: envelopes (PWE) × the envelope rate + packages × the package rate.',
                    'Share: (Whatnot net − product cost − burden − hourly − shipment pay) × their share %.',
                    'Hours and shipments go on one line, the share on another. A weekly "All pay at once" run pays both; separate wage and profit-share runs can pay them at different times, like VCX.',
                ],
                'tips' => [
                    'Example: 4 hrs × $20 = $80; 10 envelopes × $1 + 5 packages × $2 = $20; ($1,000 − $300 − $50 − $80 − $20) × 10% = $55. Pay $155.',
                    'The burden is optional: a flat $ per show, or a % of net.',
                    'The share never goes below $0. A show that loses money still pays the hours and shipments.',
                    'Envelope and package counts come from the show report. With no split entered, every shipment is paid at the package rate.',
                ],
            ],
            'pay.sales_split' => [
                'title' => 'Sales split',
                'intro' => 'For independent streamers: the business keeps a % of what their show earned, and the streamer is paid the rest.',
                'steps' => [
                    'Set the % the business keeps for the team, or per person under their pay.',
                    'For a special show, set "Business keeps (this show only)" on the show\'s Financials tab. It overrides the usual % for that show.',
                ],
                'tips' => ['Example: $1,000 net with the business keeping 20% pays the streamer $800.', 'It is their earnings, not a profit share, so a wages run pays it as well as an all-pay run.'],
            ],
            'pay.run_types' => [
                'title' => 'Pay run types',
                'intro' => 'A run type decides which pay lines a run pays and how often it comes round.',
                'steps' => [
                    'All pay at once: wages and profit share together, everything except tips a business pays monthly.',
                    'Hourly & wages only: hours, shipments, sales splits and other non-share pay.',
                    'Profit share only: profit shares, for a business that pays them on their own schedule (like VCX, monthly).',
                    'Tips: only the tips a business pays monthly. Monthly by default.',
                ],
                'tips' => ['A Tips run never clashes with the weekly runs, so their dates can overlap.'],
            ],
            'business.tips_monthly' => [
                'title' => 'Paying tips monthly',
                'intro' => 'Tips come off each show\'s weekly pay onto a line of their own, paid once a month.',
                'steps' => [
                    'Tick "Pay tips monthly" on the business.',
                    'Under Rates & Adjustments → pay run types, add one that pays Tips on a monthly schedule.',
                    'Weekly runs then leave tips alone; the monthly Tips run pays them.',
                ],
            ],
            'payrun.transfer' => [
                'title' => 'Business account transfer',
                'intro' => 'What to move to the business\'s separate bank account for the shows on this run.',
                'steps' => [
                    'Product cost: what the stock sold on those shows cost.',
                    'Business revenue: Whatnot net earnings − all pay for those shows except tips.',
                    'Transfer = product cost + business revenue.',
                ],
                'tips' => ['It shows on the run that pays the shows\' wages. Profit-share and Tips runs have none, so no show is counted twice.'],
            ],
            'show.house_cut' => [
                'title' => 'Business keeps (this show only)',
                'intro' => 'Changes the % the business keeps from a sales-split streamer, for this show only.',
                'steps' => [
                    'Leave it blank to use each streamer\'s usual %.',
                    'Enter a % for a special show; the pay note says "this show\'s %".',
                ],
                'tips' => ['Only affects streamers paid a sales split.'],
            ],
            'inbox.overview' => [
                'title' => 'Show report inbox',
                'intro' => 'Every streamer\'s show report, as cards, with what each one is paid and anything missing.',
                'steps' => [
                    'Needs attention: reports waiting on you, sent back, or asked to be reopened.',
                    'Open a card\'s button to review, approve, or send it back for changes.',
                    '"Submit for streamer" files a report on their behalf; "Reset" clears it back to not started.',
                ],
                'tips' => [
                    'Missing hours, earnings or shipments show in amber on the card.',
                    'Select mode lets you reset several reports at once.',
                ],
            ],
            'inventory.catalog' => [
                'title' => 'All inventory',
                'intro' => 'Every product, its stock and what it\'s worth.',
                'steps' => [
                    'Search by name, SKU, UPC, barcode or brand.',
                    'Click a stock tile (In stock, Low, Out) to show only those.',
                    'Each card\'s buttons: History, Add stock, View, Edit, Stock (move between locations), Scan (set its barcode), Delete.',
                ],
                'tips' => ['Switch to Table for a sortable list.'],
            ],
        ];
    }

    /** @return array{title:string, intro:string, steps?:list<string>, tips?:list<string>}|null */
    public static function get(string $topic): ?array
    {
        return self::all()[$topic] ?? null;
    }

    /**
     * The help for the page being shown, from the page and resource classes
     * Filament hands a render hook. The page's own entry wins over its
     * resource's; an entry can differ for admins and streamers.
     */
    public static function forPage(array $scopes): ?string
    {
        $map = HelpContent::pageMap();
        $user = auth()->user();
        $isAdmin = (bool) ($user?->isAdmin() || $user?->isOwner());

        foreach ($scopes as $class) {
            $entry = is_string($class) ? ($map[$class] ?? null) : null;
            if (is_array($entry)) {
                $entry = $isAdmin ? ($entry['admin'] ?? null) : ($entry['streamer'] ?? $entry['admin'] ?? null);
            }
            if ($entry && self::get($entry)) {
                return $entry;
            }
        }

        return null;
    }

    /** The "?" button for a topic. */
    public static function button(string $topic): HtmlString
    {
        return new HtmlString(view('components.vx-help', ['topic' => $topic])->render());
    }
}
