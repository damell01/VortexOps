<?php

namespace Tests\Feature\Streamers;

use App\Filament\Resources\PayoutResource;
use App\Filament\Resources\ShowResource;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StreamerRowScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.owner_email' => 'owner@vortex.com']);
        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);
    }

    public function test_streamer_only_sees_their_own_shows_and_payouts(): void
    {
        $channel = WhatnotChannel::create(['name' => 'Breaks', 'status' => 'active']);

        $me = User::factory()->create();
        $me->assignRole('streamer');

        $myStreamer    = Streamer::create(['name' => 'Me', 'status' => 'active', 'include_tips' => false, 'user_id' => $me->id]);
        $otherStreamer = Streamer::create(['name' => 'Other', 'status' => 'active', 'include_tips' => false]);

        $myShow = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'My Show',
            'show_date'          => now()->toDateString(),
            'gross_revenue'      => 1000,
            'whatnot_net'        => 900,
            'status'             => 'reconciled',
            'created_by'         => $me->id,
        ]);
        $otherShow = Show::create([
            'whatnot_channel_id' => $channel->id,
            'title'              => 'Other Show',
            'show_date'          => now()->toDateString(),
            'gross_revenue'      => 1000,
            'whatnot_net'        => 900,
            'status'             => 'reconciled',
            'created_by'         => $me->id,
        ]);
        $myShow->streamers()->attach($myStreamer->id, ['is_primary' => true]);
        $otherShow->streamers()->attach($otherStreamer->id, ['is_primary' => true]);

        Payout::create([
            'streamer_id'       => $myStreamer->id,
            'show_id'           => $myShow->id,
            'payout_type'       => 'profit_share',
            'calculated_payout' => 100,
            'status'            => 'draft',
        ]);
        Payout::create([
            'streamer_id'       => $otherStreamer->id,
            'show_id'           => $otherShow->id,
            'payout_type'       => 'profit_share',
            'calculated_payout' => 200,
            'status'            => 'draft',
        ]);

        $this->actingAs($me);

        $this->assertTrue($me->fresh()->isStreamer(), 'sanity: isStreamer() should be true for this account');
        $this->assertFalse($me->fresh()->isAdmin(), 'sanity: isAdmin() should be false for this account');

        $shows = ShowResource::getEloquentQuery()->get();
        $this->assertCount(1, $shows, 'streamer should only see their own show in ShowResource');
        $this->assertEquals('My Show', $shows->first()->title);

        $payouts = PayoutResource::getEloquentQuery()->get();
        $this->assertCount(1, $payouts, 'streamer should only see their own payout in PayoutResource');
        $this->assertEquals($myStreamer->id, $payouts->first()->streamer_id);
    }
}
