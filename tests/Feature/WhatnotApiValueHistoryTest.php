<?php

namespace Tests\Feature;

use App\Models\WhatnotChannel;
use App\Services\WhatnotApiValueHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WhatnotApiValueHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_changed_values_create_new_encrypted_observations(): void
    {
        $channel = WhatnotChannel::create(['name' => 'History Seller', 'whatnot_username' => 'history-seller']);
        $history = app(WhatnotApiValueHistory::class);
        $this->assertTrue($history->observe($channel->id, 'inventory', 'sku-1', 'price', 120));
        $this->assertFalse($history->observe($channel->id, 'inventory', 'sku-1', 'price', 120));
        $this->assertTrue($history->observe($channel->id, 'inventory', 'sku-1', 'price', 105));
        $rows = DB::table('whatnot_api_observed_values')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('120', Crypt::decryptString($rows[0]->value_encrypted));
        $this->assertSame('105', Crypt::decryptString($rows[1]->value_encrypted));
        $this->assertSame(0, DB::table('whatnot_ledger_entries')->count());
    }

    public function test_independent_fields_have_independent_history(): void
    {
        $channel = WhatnotChannel::create(['name' => 'History Seller', 'whatnot_username' => 'history-seller']);
        $history = app(WhatnotApiValueHistory::class);
        $this->assertTrue($history->observe($channel->id, 'orders', 'order-1', 'status', 'pending'));
        $this->assertTrue($history->observe($channel->id, 'orders', 'order-1', 'total', 50));
        $this->assertFalse($history->observe($channel->id, 'orders', 'order-1', 'status', 'pending'));
        $this->assertSame(2, DB::table('whatnot_api_observed_values')->count());
    }
}
