<?php

namespace Tests\Feature\Reports;

use App\Filament\Resources\WhatnotLedgerResource;
use App\Filament\Resources\WhatnotLedgerResource\Pages\LedgerTips;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The tips page once shipped with a corrupted view and answered every visit with a 500. */
class LedgerTipsDrawerRendersTest extends TestCase
{
    use RefreshDatabase;

    public function test_tips_page_and_unmatched_drawer_render(): void
    {
        $this->enableAdminModules();
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $channel = WhatnotChannel::create(['name' => 'Main', 'status' => 'active']);
        WhatnotLedgerEntry::create(['whatnot_channel_id' => $channel->id, 'created_date' => now(), 'amount' => 12.34,
            'transaction_type' => 'Tip', 'status' => 'Completed', 'whatnot_order_id' => 'T-1', 'dedup_key' => 'tip-1']);

        $this->artisan('ledger:match-shows')->assertSuccessful();

        $this->withoutVite()->get(WhatnotLedgerResource::getUrl('tips'))->assertOk()->assertSee('Net ledger tips');

        Livewire::test(LedgerTips::class)->call('openBusinessReview')
            ->assertSee('Unmatched business tips')->assertSee('T-1')->assertSee('$12.34')
            ->call('closeBusinessReview')->assertDontSee('Unmatched business tips');
    }
}
