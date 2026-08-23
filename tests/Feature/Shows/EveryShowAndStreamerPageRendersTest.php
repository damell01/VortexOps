<?php

namespace Tests\Feature\Shows;

use App\Models\Payout;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShowOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Open every shows and streamer screen and see that it opens.
 *
 * The same sweep the inventory side got, for the same reason: the last two
 * production breaks were views that compiled to invalid PHP, and a Blade view
 * is only compiled when something asks for it. One of those was the activity
 * widget on the show page — so this half of the app has already demonstrated
 * it needs the check.
 *
 * The fixture is a show that has actually happened: a channel, a streamer
 * attached, orders against it, a payout, a submitted report. An empty show
 * exercises none of the totalling and relation-loading these pages exist to do.
 */
class EveryShowAndStreamerPageRendersTest extends TestCase
{
    use RefreshDatabase;

    private Show $show;

    private Streamer $streamer;

    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableAdminModules();

        $owner = User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);

        $this->actingAs($owner->fresh());

        $this->channel = WhatnotChannel::create([
            'name' => 'Vortex Cards', 'whatnot_username' => 'vortexcards', 'status' => 'active',
        ]);

        $this->streamer = Streamer::create([
            'whatnot_channel_id' => $this->channel->id,
            'name'               => 'Tyler',
            'email'              => 'tyler@example.com',
            'payout_type'        => 'profit_share',
            'payout_percentage'  => 20,
        ]);

        $this->show = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'GRAIL HIGH END CLAIMS W/Tyler',
            'show_date'          => now()->subDays(2)->toDateString(),
            'gross_revenue'      => 8801.00,
            'whatnot_net'        => 6496.34,
            'units_sold'         => 98,
            'status'             => 'mapping',
            'created_by'         => $owner->id,
        ]);

        $this->show->streamers()->attach($this->streamer->id);

        foreach (range(1, 4) as $i) {
            WhatnotShowOrder::create([
                'show_id'          => $this->show->id,
                'whatnot_order_id' => "SWEEP-{$i}",
                'buyer_username'   => "buyer{$i}",
                'item_name'        => "Lot {$i}",
                'quantity'         => 1,
                'unit_price'       => 89.81,
                'total_price'      => 89.81,
            ]);
        }

        Payout::create([
            'streamer_id' => $this->streamer->id,
            'show_id'     => $this->show->id,
            'payout_type' => 'profit_share',
            'amount'      => 250.00,
            'status'      => 'pending',
        ]);

        StreamerLogEntry::create([
            'show_id'     => $this->show->id,
            'streamer_id' => $this->streamer->id,
            'status'      => 'pending',
        ]);

        ShowIngestionLog::create([
            'show_id'     => $this->show->id,
            'source'      => 'whatnot',
            'status'      => 'success',
            'raw_payload' => ['title' => $this->show->title],
        ]);
    }

    /** Mount a record-bound page with whatever its $record property accepts. */
    private function open(string $page, Model $record): \Livewire\Features\SupportTesting\Testable
    {
        $type = (new \ReflectionClass($page))->hasProperty('record')
            ? (new \ReflectionProperty($page, 'record'))->getType()
            : null;

        $names = match (true) {
            $type instanceof \ReflectionNamedType => [$type->getName()],
            $type instanceof \ReflectionUnionType => array_map(
                fn ($t) => $t instanceof \ReflectionNamedType ? $t->getName() : '',
                $type->getTypes(),
            ),
            default => [],
        };

        $takesKey = (bool) array_filter($names, fn ($n) => in_array($n, ['int', 'string'], true));
        $takesModel = (bool) array_filter(
            $names,
            fn ($n) => $n !== '' && class_exists($n) && is_a($record, $n),
        );

        return Livewire::test($page, [
            'record' => ($takesKey || ! $takesModel) ? $record->getKey() : $record,
        ]);
    }

    /** @return array<string, array{class-string}> */
    public static function standalonePages(): array
    {
        $pages = [
            \App\Filament\Pages\Shows::class,
            \App\Filament\Pages\ShowStatusBoard::class,
            \App\Filament\Pages\ShowShipments::class,
            \App\Filament\Pages\Reports::class,
            \App\Filament\Pages\StreamerAnalytics::class,
            \App\Filament\Pages\StreamerStatement::class,

            \App\Filament\Resources\ShowResource\Pages\ListShows::class,
            \App\Filament\Resources\ShowResource\Pages\CreateShow::class,
            \App\Filament\Resources\StreamerResource\Pages\ListStreamers::class,
            \App\Filament\Resources\StreamerResource\Pages\CreateStreamer::class,
            \App\Filament\Resources\PayoutResource\Pages\ListPayouts::class,
            \App\Filament\Resources\ShipmentResource\Pages\ListShipments::class,
            \App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries::class,
            \App\Filament\Resources\StreamerLoanResource\Pages\ListStreamerLoans::class,
            \App\Filament\Resources\StreamerLoanResource\Pages\CreateStreamerLoan::class,
            \App\Filament\Resources\DeductionRequestResource\Pages\ListDeductionRequests::class,
            \App\Filament\Resources\WeeklyPayoutBatchResource\Pages\ListWeeklyPayoutBatches::class,
            \App\Filament\Resources\WeeklyPayoutBatchResource\Pages\CreateWeeklyPayoutBatch::class,
            \App\Filament\Resources\ShowIngestionLogResource\Pages\ListShowIngestionLogs::class,
        ];

        $out = [];
        foreach ($pages as $page) {
            $out[class_basename($page)] = [$page];
        }

        return $out;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('standalonePages')]
    public function test_the_page_opens(string $page): void
    {
        if (! class_exists($page)) {
            $this->markTestSkipped("{$page} does not exist");
        }

        Livewire::test($page)->assertOk();
    }

    /**
     * The streamer's own screens, opened by an actual streamer.
     *
     * These 403 for anyone without a Streamer record attached to their user —
     * correctly, since they are somebody's own hub and statement — so opening
     * them as the owner proves only that the guard works. The module still has
     * to render for the people it is for.
     *
     * @return array<string, array{class-string}>
     */
    public static function streamerPages(): array
    {
        return [
            'StreamerHub'         => [\App\Filament\Pages\StreamerHub::class],
            'StreamerShows'       => [\App\Filament\Pages\StreamerShows::class],
            'StreamerProfitShare' => [\App\Filament\Pages\StreamerProfitShare::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('streamerPages')]
    public function test_the_streamer_page_opens_for_a_streamer(string $page): void
    {
        if (! class_exists($page)) {
            $this->markTestSkipped("{$page} does not exist");
        }

        $this->actingAs($this->streamerUser());

        Livewire::test($page)->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('streamerPages')]
    public function test_the_streamer_page_is_refused_without_a_streamer_record(string $page): void
    {
        if (! class_exists($page)) {
            $this->markTestSkipped("{$page} does not exist");
        }

        // Signed in, and still nobody's streamer — these pages show one
        // person's earnings, so the guard matters as much as the render.
        $this->actingAs(User::factory()->create());

        Livewire::test($page)->assertStatus(403);
    }

    private function streamerUser(): User
    {
        // Roles are not seeded between tests in this suite, so the role has to
        // exist before it can be given to anyone.
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'streamer', 'guard_name' => 'web']);

        $user = User::factory()->create(['email' => 'tyler@example.com', 'name' => 'Tyler']);
        $user->assignRole('streamer');

        $this->streamer->update(['user_id' => $user->id]);

        return $user->fresh();
    }

    /** @return array<string, array{class-string}> */
    public static function showPages(): array
    {
        return [
            'ViewShow'               => [\App\Filament\Resources\ShowResource\Pages\ViewShow::class],
            'EditShow'               => [\App\Filament\Resources\ShowResource\Pages\EditShow::class],
            'AddShowItems'           => [\App\Filament\Resources\ShowResource\Pages\AddShowItems::class],
            'ShowInventoryBreakdown' => [\App\Filament\Resources\ShowResource\Pages\ShowInventoryBreakdown::class],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('showPages')]
    public function test_the_show_page_opens(string $page): void
    {
        if (! class_exists($page)) {
            $this->markTestSkipped("{$page} does not exist");
        }

        $this->open($page, $this->show)->assertOk();
    }

    public function test_the_streamer_pages_open(): void
    {
        $this->open(\App\Filament\Resources\StreamerResource\Pages\ViewStreamer::class, $this->streamer)
            ->assertOk();

        $this->open(\App\Filament\Resources\StreamerResource\Pages\EditStreamer::class, $this->streamer)
            ->assertOk();
    }

    public function test_the_payout_page_opens(): void
    {
        $this->open(\App\Filament\Resources\PayoutResource\Pages\ViewPayout::class, Payout::first())
            ->assertOk();
    }

    public function test_the_report_pages_open(): void
    {
        $entry = StreamerLogEntry::first();

        $this->open(\App\Filament\Resources\StreamerLogResource\Pages\ViewStreamerLogEntry::class, $entry)
            ->assertOk();

        $this->open(\App\Filament\Resources\StreamerLogResource\Pages\EditStreamerLogEntry::class, $entry)
            ->assertOk();
    }

    public function test_the_ingestion_log_page_opens(): void
    {
        $this->open(
            \App\Filament\Resources\ShowIngestionLogResource\Pages\ViewShowIngestionLog::class,
            ShowIngestionLog::first(),
        )->assertOk();
    }

    /**
     * The show page with nothing on it yet.
     *
     * A scraped show arrives with a title, a date and no money at all, and that
     * is the state it is looked at in first — so it is the state most likely to
     * be formatted wrongly.
     */
    public function test_a_show_with_no_orders_or_payouts_opens(): void
    {
        $bare = Show::create([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Just imported, nothing else',
            'show_date'          => now()->toDateString(),
            'created_by'         => auth()->id(),
        ]);

        $this->open(\App\Filament\Resources\ShowResource\Pages\ViewShow::class, $bare)->assertOk();
    }
}
