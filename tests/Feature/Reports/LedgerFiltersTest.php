<?php

namespace Tests\Feature\Reports;

use App\Filament\Resources\ShowResource;
use App\Filament\Resources\WhatnotLedgerResource;
use App\Filament\Resources\WhatnotLedgerResource\Pages\ListWhatnotLedgerEntries;
use App\Models\Business;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\LedgerAttributionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The ledger page finds a show's, streamer's or business's money quickly:
 * filters for show, streamer, business and category (with one chip for
 * every chargeback), a summary of the chosen show, and order search that
 * uses the order index.
 */
class LedgerFiltersTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $channel;
    private Show $show;
    private Streamer $ed;
    private Streamer $nat;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::set('enabled_admin_modules', json_encode(['streams', 'payouts', 'reporting']));
        \App\Support\AdminModules::flushMemo();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $this->channel = WhatnotChannel::create(['name' => 'VortexBreaks', 'status' => 'active']);
        $this->ed = Streamer::create(['name' => 'Estill Ed', 'status' => 'active', 'business_id' => Business::where('short_name', 'Estill')->value('id')]);
        $this->nat = Streamer::create(['name' => 'Natbat', 'status' => 'active', 'business_id' => Business::where('short_name', 'VCX')->value('id')]);
        $this->show = Show::create(['title' => 'Friday Breaks', 'show_date' => now()->subDays(2)->toDateString(), 'whatnot_channel_id' => $this->channel->id,
            'status' => 'reconciled', 'whatnot_net' => 500]);

        $this->entry('Sales Earnings', 120, 'sales', $this->ed, $this->show, '7001001');
        $this->entry('Shipping charge', -6.5, 'shipping', $this->ed, $this->show, '7001002');
        $this->entry('Refund', -20, 'refunds', $this->ed, $this->show, '7001003');
        $this->entry('Adjustment', -3, 'correction', $this->nat, null, '7001004');
        $this->entry('Tip', 10, 'tips', $this->nat, null, '7001005');
        $this->entry('Unmatched credit', 4, null, null, null, '9009999');
    }

    private function entry(string $type, float $amount, ?string $category, ?Streamer $streamer, ?Show $show, string $order): void
    {
        $id = DB::table('whatnot_ledger_entries')->insertGetId(['whatnot_channel_id' => $this->channel->id, 'created_date' => now()->subDay(),
            'amount' => $amount, 'transaction_type' => $type, 'whatnot_order_id' => $order, 'message' => $type . ' for order ' . $order,
            'status' => 'Completed', 'dedup_key' => 'k' . $order, 'created_at' => now(), 'updated_at' => now()]);
        if ($category) {
            DB::table('ledger_attributions')->insert(['whatnot_ledger_entry_id' => $id, 'category' => $category, 'target' => $show ? 'show' : 'streamer',
                'show_id' => $show?->id, 'streamer_id' => $streamer?->id, 'business_id' => null, 'status' => 'approved', 'reason' => 't',
                'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function orders($page): array
    {
        return $page->instance()->entries->pluck('whatnot_order_id')->sort()->values()->all();
    }

    public function test_show_streamer_business_and_chargeback_filters_narrow_the_ledger(): void
    {
        $page = Livewire::test(ListWhatnotLedgerEntries::class)
            ->assertSee('Chargebacks &amp; corrections', false)->assertSee('All businesses');
        $this->assertCount(6, $this->orders($page));

        // Chargebacks & corrections (refunds and adjustments) in one tap; shipping charges are their own type.
        $page->set('categoryFilter', 'chargebacks');
        $this->assertSame(['7001003', '7001004'], $this->orders($page));
        $this->assertSame(-23.0, $page->instance()->stats['net']);
        $page->set('categoryFilter', 'shipping');
        $this->assertSame(['7001002'], $this->orders($page));

        // Business: entries tied to its streamers.
        $page->set('categoryFilter', '')->set('businessFilter', (string) $this->ed->business_id);
        $this->assertSame(['7001001', '7001002', '7001003'], $this->orders($page));
        $page->set('businessFilter', (string) $this->nat->business_id);
        $this->assertSame(['7001004', '7001005'], $this->orders($page));

        // Streamer, and the show with its summary panel.
        $page->set('businessFilter', '')->set('streamerFilter', (string) $this->nat->id);
        $this->assertSame(['7001004', '7001005'], $this->orders($page));
        $page->set('streamerFilter', '')->set('showFilter', (string) $this->show->id)
            ->assertSee('Friday Breaks')->assertSee('Open show')->assertSee('Ledger money (no tips)');
        $this->assertSame(['7001001', '7001002', '7001003'], $this->orders($page));

        $page->call('clearFilters')->set('preset', 'all');
        $this->assertCount(6, $this->orders($page));
    }

    public function test_search_finds_an_order_number_or_words(): void
    {
        $page = Livewire::test(ListWhatnotLedgerEntries::class)->set('search', '7001003');
        $this->assertSame(['7001003'], $this->orders($page));
        $page->set('search', '#9009999');
        $this->assertSame(['9009999'], $this->orders($page));
        $page->set('search', 'shipping');
        $this->assertSame(['7001002'], $this->orders($page));
    }

    public function test_reports_follow_the_table_and_saving_an_allocation_refreshes_totals(): void
    {
        $page = Livewire::test(ListWhatnotLedgerEntries::class)->call('loadReports')->assertSet('reportsReady', true);
        $page->set('categoryFilter', 'refunds')->assertSet('reportsReady', false)->assertSee('Updating reconciliation totals');
        $page->call('readyReports')->assertSet('reportsReady', true)->assertDontSee('Updating reconciliation totals');

        // Totals are cached per filter; an allocation saved here shows at once.
        $page->set('categoryFilter', 'correction');
        $this->assertSame(-3.0, $page->instance()->stats['net']);
        $unmatched = \App\Models\WhatnotLedgerEntry::query()->where('whatnot_order_id', '9009999')->firstOrFail();
        app(LedgerAttributionService::class)->save($unmatched, ['category' => 'correction', 'target' => 'streamer', 'streamer_id' => $this->nat->id, 'reason' => 'credit back'], auth()->id());
        $row = DB::table('ledger_attributions')->where('whatnot_ledger_entry_id', $unmatched->id)->first();
        if ($row->status !== 'approved') app(LedgerAttributionService::class)->review($unmatched, User::factory()->create()->id, true);
        $fresh = Livewire::test(ListWhatnotLedgerEntries::class)->set('categoryFilter', 'correction');
        $this->assertSame(1.0, $fresh->instance()->stats['net']);
    }

    public function test_a_show_links_to_its_ledger_entries(): void
    {
        $this->get(ShowResource::getUrl('view', ['record' => $this->show->id]))->assertOk()
            ->assertSee(e(WhatnotLedgerResource::getUrl('index', ['show' => $this->show->id, 'period' => 'all'])), false);
    }

    public function test_the_show_picker_searches_titles_channels_and_dates(): void
    {
        Show::create(['title' => 'Monday Mystery Night', 'show_date' => now()->subDays(9)->toDateString(), 'whatnot_channel_id' => $this->channel->id, 'status' => 'reconciled']);
        $page = Livewire::test(ListWhatnotLedgerEntries::class);
        $this->assertSame(['Friday Breaks', 'Monday Mystery Night'], array_column($page->instance()->searchShows(''), 'title'));
        $this->assertSame(['Monday Mystery Night'], array_column($page->instance()->searchShows('mystery'), 'title'));
        $this->assertSame(['Friday Breaks'], array_column($page->instance()->searchShows($this->show->show_date->format('n/j/Y')), 'title'));
        $this->assertSame('VortexBreaks', $page->instance()->searchShows('friday')[0]['channel']);
        $page->set('showFilter', (string) $this->show->id)->assertSee('Friday Breaks')->assertSee('Clear show filter', false);
    }
}
