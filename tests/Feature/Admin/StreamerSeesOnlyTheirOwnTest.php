<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ShipmentResource;
use App\Filament\Resources\ShowIngestionLogResource;
use App\Filament\Resources\StreamerLoanResource;
use App\Filament\Resources\WhatnotChannelResource;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLoan;
use App\Models\User;
use App\Models\WhatnotChannel;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A streamer signing in must not be handed the rest of the roster.
 *
 * HasModuleAccess admits any signed-in user unless a resource says otherwise,
 * which is the right default for the resources streamers genuinely use — those
 * scope their own rows in getEloquentQuery(). It is the wrong default for a
 * resource that does neither, and four of them did neither:
 *
 *  - Streamer Loans listed every streamer's advance, balance included. The
 *    record pages were covered by StreamerLoanPolicy; the list was not, and
 *    the list is where the numbers are.
 *  - Show Ingestion Logs listed every channel's scraper runs and raw errors.
 *  - Whatnot Channels exposed channel configuration, with create/edit/delete
 *    pages registered.
 *  - Shipments scoped by channel and called it done, so a streamer saw every
 *    other streamer's shipments in that channel — buyer usernames, order
 *    dates, shipping costs.
 *
 * A list page is not protected by its record policy: it never loads a record.
 */
class StreamerSeesOnlyTheirOwnTest extends TestCase
{
    use RefreshDatabase;

    private User $streamerUser;

    private Streamer $streamer;

    private Streamer $otherStreamer;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('streamer', 'web');

        $this->streamerUser = User::factory()->create(['email' => 'streamer@example.com', 'name' => 'Own']);
        $this->streamerUser->assignRole('streamer');
        $this->streamerUser = $this->streamerUser->fresh();

        $this->streamer = Streamer::create([
            'user_id' => $this->streamerUser->id, 'name' => 'Own',
            'email' => 'streamer@example.com', 'status' => 'active', 'payout_type' => 'profit_share',
        ]);

        $other = User::factory()->create(['email' => 'other@example.com', 'name' => 'Other']);
        $this->otherStreamer = Streamer::create([
            'user_id' => $other->id, 'name' => 'Other',
            'email' => 'other@example.com', 'status' => 'active', 'payout_type' => 'profit_share',
        ]);

        $this->channel = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function showFor(Streamer $streamer, string $title): Show
    {
        $show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => $title,
            'show_date'          => now()->subDay()->toDateString(),
            'created_by'         => $this->streamerUser->id,
        ]);
        $show->streamers()->attach($streamer->id);

        return $show;
    }

    /** @return array<int, string> */
    public static function adminOnlyResources(): array
    {
        return [
            'streamer loans'      => [StreamerLoanResource::class],
            'show ingestion logs' => [ShowIngestionLogResource::class],
            'whatnot channels'    => [WhatnotChannelResource::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminOnlyResources')]
    public function test_a_streamer_cannot_open_an_admin_only_resource(string $resource): void
    {
        $this->actingAs($this->streamerUser)
            ->get($resource::getUrl('index'))
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adminOnlyResources')]
    public function test_an_admin_still_can(string $resource): void
    {
        // The gate has to keep the people it is for. An access fix that locks
        // out the admins is not a fix.
        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);

        $this->actingAs($owner)
            ->get($resource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_the_loans_list_is_not_left_to_the_record_policy(): void
    {
        // StreamerLoanPolicy already covered view/edit — this is the list,
        // which shows the same figures without ever loading a record.
        StreamerLoan::create([
            'streamer_id' => $this->otherStreamer->id, 'label' => 'OTHERADVANCE',
            'original_amount' => 5000, 'weekly_repayment' => 250,
            'remaining_balance' => 4750, 'status' => 'active',
        ]);

        $this->actingAs($this->streamerUser)
            ->get(StreamerLoanResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_a_streamer_sees_only_their_own_shipments(): void
    {
        Shipment::create([
            'show_id' => $this->showFor($this->streamer, 'Own show')->id,
            'buyer_username' => 'ownbuyer', 'status' => 'pending',
        ]);
        Shipment::create([
            'show_id' => $this->showFor($this->otherStreamer, 'Other show')->id,
            'buyer_username' => 'otherbuyer', 'status' => 'pending',
        ]);

        $this->actingAs($this->streamerUser);

        $html = Livewire::test(ShipmentResource\Pages\ListShipments::class)
            ->call('loadTable')
            ->html();

        $this->assertStringContainsString('ownbuyer', $html, 'a streamer lost sight of their own shipments');
        $this->assertStringNotContainsString('otherbuyer', $html, "another streamer's buyer is on the page");
    }

    public function test_an_admin_still_sees_every_shipment(): void
    {
        Shipment::create([
            'show_id' => $this->showFor($this->otherStreamer, 'Other show')->id,
            'buyer_username' => 'otherbuyer', 'status' => 'pending',
        ]);

        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));

        $html = Livewire::test(ShipmentResource\Pages\ListShipments::class)
            ->call('loadTable')
            ->html();

        $this->assertStringContainsString('otherbuyer', $html);
    }
}
