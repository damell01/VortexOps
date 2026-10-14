<?php

namespace Tests\Feature\Reports;

use App\Filament\Resources\WhatnotLedgerResource;
use App\Filament\Resources\WhatnotLedgerResource\Pages\ListWhatnotLedgerEntries;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use App\Support\ChannelContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WhatnotLedgerPageTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $breaks;

    private WhatnotChannel $cards;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        ChannelContext::flushMemo();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));

        $this->breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
        $this->cards = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);

        $this->entry($this->breaks, 42.00, 'Sales Earnings', 'Completed', '1441763281', 'Live sale - 3 items', 'abcHASH', now()->subDays(2));
        $this->entry($this->cards, -1.09, 'Giveaway Deduction', 'Processing', '1441625692', 'Giveaway order deduction', null, now()->subDays(3));
        $this->entry($this->cards, 28.50, 'Sales Earnings', 'Completed', '1441587720', 'Live sale - 2 items', null, now()->subDays(60));
        $this->entry(null, -5.00, 'Shipping Label', 'Completed', '1441000001', 'Label for unassigned', null, now()->subDay());
    }

    private function entry(?WhatnotChannel $channel, float $amount, string $type, string $status, string $order, string $message, ?string $hash, $date): WhatnotLedgerEntry
    {
        return WhatnotLedgerEntry::create([
            'whatnot_channel_id' => $channel?->id, 'created_date' => $date, 'completed_date' => $date,
            'amount' => $amount, 'transaction_type' => $type, 'status' => $status,
            'whatnot_order_id' => $order, 'order_hash' => $hash, 'message' => $message, 'dedup_key' => $order,
        ]);
    }

    public function test_page_renders_header_chips_stats_and_rows(): void
    {
        $this->withoutVite();
        $this->get(WhatnotLedgerResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Whatnot Ledger')
            ->assertSee('Reconcile credits, debits and transaction status by channel.')
            ->assertSee('Export CSV')
            ->assertSee('All Channels')
            ->assertSee('Vortex Breaks')
            ->assertSee('Unassigned')
            ->assertSee('Search order or details')
            ->assertSee('Credits')->assertSee('$70.50')
            ->assertSee('Debits')->assertSee('$6.09')
            ->assertSee('Net Activity')->assertSee('$64.41')
            ->assertSee('1 transaction')
            ->assertSee('1441763281')
            ->assertSee('+$42.00')
            ->assertSee('-$1.09')
            ->assertSee('Showing 1–4 of 4 transactions');
    }

    public function test_filters_channel_chip_and_search_narrow_rows_and_totals(): void
    {
        $page = Livewire::test(ListWhatnotLedgerEntries::class);

        $page->call('setChannel', (string) $this->cards->id)
            ->assertSee('1441625692')->assertDontSee('1441763281');
        $this->assertSame(28.5, $page->instance()->stats['credits']);

        $page->set('preset', '30')->assertDontSee('1441587720')->assertSee('1441625692');
        $page->call('setChannel', 'all')->set('type', 'Sales Earnings')
            ->assertSee('1441763281')->assertDontSee('1441625692');
        $page->set('type', '')->set('status', 'Processing')
            ->assertSee('1441625692')->assertDontSee('1441763281');
        $page->set('status', '')->set('search', 'unassigned')
            ->assertSee('1441000001')->assertDontSee('1441763281');
        $page->set('search', '')->call('setChannel', 'unassigned')
            ->assertSee('1441000001')->assertDontSee('1441625692');
    }

    public function test_custom_date_range_filters_created_date(): void
    {
        Livewire::test(ListWhatnotLedgerEntries::class)
            ->set('from', now()->subDays(70)->toDateString())
            ->set('until', now()->subDays(50)->toDateString())
            ->assertSet('preset', 'custom')
            ->assertSee('1441587720')->assertDontSee('1441763281');
    }

    public function test_legacy_channel_filter_link_still_selects_the_channel(): void
    {
        $this->withoutVite();
        $this->get(WhatnotLedgerResource::getUrl('index', ['tableFilters' => ['whatnot_channel_id' => ['value' => $this->breaks->id]]]))
            ->assertOk()->assertSee('1441763281')->assertDontSee('1441625692');
    }

    public function test_details_panel_offers_view_order_only_with_a_real_order_url(): void
    {
        $withHash = WhatnotLedgerEntry::where('whatnot_order_id', '1441763281')->first();
        $without = WhatnotLedgerEntry::where('whatnot_order_id', '1441625692')->first();

        Livewire::test(ListWhatnotLedgerEntries::class)
            ->call('select', $withHash->id)
            ->assertSee('Transaction Details')
            ->assertSee('Copy Order ID')
            ->assertSee('View Order')
            ->assertSee('https://www.whatnot.com/dashboard/orders/abcHASH')
            ->call('select', $without->id)
            ->assertSee('Giveaway order deduction')
            ->assertSee('Copy Order ID')
            ->assertDontSee('View Order')
            ->call('select', null)
            ->assertDontSee('Transaction Details');
    }

    public function test_sorting_by_amount(): void
    {
        $page = Livewire::test(ListWhatnotLedgerEntries::class)->call('sortBy', 'amount');
        $this->assertSame('42.00', $page->instance()->entries->first()->amount);
        $page->call('sortBy', 'amount');
        $this->assertSame('-5.00', $page->instance()->entries->first()->amount);
    }

    public function test_export_csv_contains_filtered_rows(): void
    {
        $response = Livewire::test(ListWhatnotLedgerEntries::class)
            ->call('setChannel', (string) $this->breaks->id)
            ->call('exportCsv');

        $response->assertFileDownloaded('whatnot-ledger-'.now()->format('Y-m-d').'.csv');
    }

    public function test_owner_can_delete_selected_entries(): void
    {
        $id = WhatnotLedgerEntry::where('whatnot_order_id', '1441000001')->value('id');

        Livewire::test(ListWhatnotLedgerEntries::class)
            ->set('checked', [(string) $id])
            ->assertSee('Delete selected (1)')
            ->call('deleteChecked');

        $this->assertNull(WhatnotLedgerEntry::find($id));
        $this->assertSame(3, WhatnotLedgerEntry::count());
    }

    public function test_top_bar_channel_context_scopes_the_page(): void
    {
        ChannelContext::setActive($this->breaks->id);

        $page = Livewire::test(ListWhatnotLedgerEntries::class)
            ->assertSee('1441763281')
            ->assertDontSee('1441625692')
            ->assertDontSee('Vortex Cards');
        $this->assertSame(1, $page->instance()->stats['count']);

        ChannelContext::setActive(null);
    }
}
