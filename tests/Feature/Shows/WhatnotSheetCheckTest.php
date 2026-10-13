<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\WhatnotSheetCheck;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\WhatnotSheetReconciler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WhatnotSheetCheckTest extends TestCase
{
    use RefreshDatabase;

    private array $paths = [];
    private WhatnotChannel $cards;
    private WhatnotChannel $breaks;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 12:00:00');
        $this->actingAs(User::factory()->create(['email' => 'dbellcreations@gmail.com']));
        $this->cards = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
        $this->breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) @unlink($path);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sheet-'); $this->paths[] = $path;
        $file = fopen($path, 'wb'); fwrite($file, "\xEF\xBB\xBF");
        fputcsv($file, ['Show','Date','Thumbnail Impressions','Thumbnail CTR','Est. Sales','Est. Earnings','Orders','AOV','Giveaway Spend','Giveaways','Buyers','First Time Buyers','Returning Buyers','Show Shares','Show Duration (mins)','Max Concurrent Viewers','Total Views','Unique Viewers','Average Order Rating'], ',', '"', '');
        foreach ($rows as $row) fputcsv($file, $row, ',', '"', '');
        fclose($file);

        return $path;
    }

    private function row(string $title, string $date, string $sales = '$2,000', string $earnings = '$1,700', string $orders = '100', string $minutes = '200'): array
    {
        return [$title, $date, '1,000', '10%', $sales, $earnings, $orders, '$20', '-$10', '5', '30', '10', '20', '4', $minutes, '150', '1,000', '400', '5.00'];
    }

    private function show(WhatnotChannel $channel, string $title, string $date, array $extra = []): Show
    {
        return Show::withoutEvents(fn () => Show::create($extra + [
            'whatnot_channel_id' => $channel->id, 'title' => $title, 'show_date' => $date, 'status' => 'draft', 'import_source' => 'auto_whatnot',
        ]));
    }

    private function plan(array $sheets): array
    {
        return app(WhatnotSheetReconciler::class)->plan($sheets);
    }

    private function item(array $plan, string $title): array
    {
        return collect($plan['items'])->firstWhere('title', $title);
    }

    public function test_a_show_on_the_right_channel_takes_the_exports_figures(): void
    {
        $show = $this->show($this->cards, '🍀BIG GIVYS W/NIKO🍀', '2026-10-05', ['gross_revenue' => 2000.37, 'show_duration' => 150]);
        $plan = $this->plan([$this->cards->id => [$this->csv([$this->row('🍀BIG GIVYS W/NIKO🍀', '10/05/2026')])]]);

        $item = $this->item($plan, '🍀BIG GIVYS W/NIKO🍀');
        $this->assertSame('update', $item['action']);
        $this->assertArrayNotHasKey('gross_revenue', $item['changes'], 'cents within the export\'s rounding are kept');
        $this->assertSame(200, $item['changes']['show_duration'][1]);

        app(WhatnotSheetReconciler::class)->apply($plan);
        $show->refresh();
        $this->assertSame(200, (int) $show->show_duration);
        $this->assertEqualsWithDelta(2000.37, (float) $show->gross_revenue, 0.001);
        $this->assertEqualsWithDelta(1700, (float) $show->whatnot_net, 0.001);
        $this->assertNotNull($show->last_analytics_synced_at);
    }

    public function test_a_show_imported_into_the_wrong_channel_is_moved(): void
    {
        $show = $this->show($this->breaks, 'MASSIVE $1 STARTS W/Camilleon', '2026-10-06');
        $streamer = Streamer::create(['name' => 'Cam', 'status' => 'active', 'member_type' => 'streamer']);
        $payout = Payout::create(['show_id' => $show->id, 'streamer_id' => $streamer->id, 'whatnot_channel_id' => $this->breaks->id, 'payout_type' => 'profit_share', 'calculated_payout' => 10, 'status' => 'draft']);

        $plan = $this->plan([$this->cards->id => [$this->csv([$this->row('MASSIVE $1 STARTS W/Camilleon', '10/06/2026')])]]);
        $item = $this->item($plan, 'MASSIVE $1 STARTS W/Camilleon');
        $this->assertSame('move', $item['action']);
        $this->assertSame('Vortex Breaks', $item['from_channel']);

        $result = app(WhatnotSheetReconciler::class)->apply($plan);
        $this->assertSame(1, $result['moved']);
        $this->assertSame($this->cards->id, (int) $show->fresh()->whatnot_channel_id);
        $this->assertSame($this->cards->id, (int) $payout->fresh()->whatnot_channel_id);
    }

    public function test_the_same_title_on_two_channels_stays_put_when_both_exports_list_it(): void
    {
        $a = $this->show($this->cards, 'Rapid fire packs', '2026-10-01');
        $b = $this->show($this->breaks, 'Rapid fire packs', '2026-10-01');

        $plan = $this->plan([
            $this->cards->id => [$this->csv([$this->row('Rapid fire packs', '10/01/2026')])],
            $this->breaks->id => [$this->csv([$this->row('Rapid fire packs', '10/01/2026', '$500')])],
        ]);

        $this->assertEmpty(collect($plan['items'])->where('action', 'move'));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], collect($plan['items'])->pluck('show_id')->filter()->all());
    }

    public function test_a_show_missing_from_the_app_is_created_on_its_channel(): void
    {
        $plan = $this->plan([$this->cards->id => [$this->csv([
            $this->row('HOLY HIGH END SINGLES W/TREY!', '08/07/2026', '$6,598', '$6,099'),
            ['B Test', '08/22/2026', '373', '2.14%', '—', '—', '—', '—', '—', '—', '—', '—', '—', '—', '0', '4', '0', '0', '—'],
        ])]]);

        $this->assertSame('create', $this->item($plan, 'HOLY HIGH END SINGLES W/TREY!')['action']);
        $this->assertSame('skip_empty', $this->item($plan, 'B Test')['action']);

        app(WhatnotSheetReconciler::class)->apply($plan);
        $show = Show::where('title', 'HOLY HIGH END SINGLES W/TREY!')->firstOrFail();
        $this->assertSame($this->cards->id, (int) $show->whatnot_channel_id);
        $this->assertSame('2026-08-07', $show->show_date->toDateString());
        $this->assertEqualsWithDelta(6598, (float) $show->gross_revenue, 0.001);
        $this->assertFalse(Show::where('title', 'B Test')->exists());
    }

    public function test_a_show_the_export_does_not_list_is_reported_not_changed(): void
    {
        $this->show($this->cards, 'First day', '2026-10-01');
        $stray = $this->show($this->cards, 'Duplicate import', '2026-10-03');
        $plan = $this->plan([$this->cards->id => [$this->csv([$this->row('First day', '10/01/2026'), $this->row('Last day', '10/05/2026')])]]);

        $extra = $this->item($plan, 'Duplicate import');
        $this->assertSame('extra', $extra['action']);
        $this->assertSame(1, $plan['channels'][$this->cards->id]['counts']['extra']);

        app(WhatnotSheetReconciler::class)->apply($plan);
        $this->assertSame($this->cards->id, (int) $stray->fresh()->whatnot_channel_id);
    }

    public function test_overlapping_pages_count_once_and_totals_compare(): void
    {
        $this->show($this->cards, 'Show A', '2026-10-02', ['gross_revenue' => 2000, 'whatnot_net' => 1700]);
        $rows = [$this->row('Show A', '10/02/2026'), $this->row('Show B', '10/03/2026', '$1,000', '$850')];
        $plan = $this->plan([$this->cards->id => [$this->csv($rows), $this->csv([$rows[1]])]]);

        $c = $plan['channels'][$this->cards->id];
        $this->assertSame(2, $c['sheet']['shows']);
        $this->assertEqualsWithDelta(3000, $c['sheet']['sales'], 0.01);
        $this->assertSame(1, $c['app']['shows']);
        $this->assertSame(1, $c['counts']['create']);
        $this->assertSame([2, 1], array_values(array_map(fn ($f) => $f['rows'], $c['files'])));
        $this->assertSame([2, 0], array_values(array_map(fn ($f) => $f['new'], $c['files'])), 'the repeat page adds nothing');
    }

    public function test_two_shows_with_one_title_on_one_day_pair_by_their_figures(): void
    {
        $small = $this->show($this->cards, 'Same title', '2026-10-06', ['gross_revenue' => 600, 'show_duration' => 90]);
        $big = $this->show($this->cards, 'Same title', '2026-10-06', ['gross_revenue' => 3000, 'show_duration' => 300]);
        $plan = $this->plan([$this->cards->id => [$this->csv([
            $this->row('Same title', '10/06/2026', '$3,020', '$2,545', '300', '301'),
            $this->row('Same title', '10/06/2026', '$602', '$500', '57', '93'),
        ])]]);

        $byShow = collect($plan['items'])->keyBy('show_id');
        $this->assertEqualsWithDelta(3020, $byShow[$big->id]['metrics']['gross_revenue'], 0.01);
        $this->assertEqualsWithDelta(602, $byShow[$small->id]['metrics']['gross_revenue'], 0.01);
    }

    public function test_a_late_show_dated_a_day_off_still_matches(): void
    {
        $show = $this->show($this->breaks, 'Midnight madness', '2026-09-30');
        $plan = $this->plan([$this->cards->id => [$this->csv([$this->row('Midnight madness', '10/01/2026')])]]);

        $item = $this->item($plan, 'Midnight madness');
        $this->assertSame($show->id, $item['show_id']);
        $this->assertSame('move', $item['action']);
    }

    public function test_the_page_checks_uploaded_exports_and_applies_them(): void
    {
        Storage::fake('local');
        $show = $this->show($this->breaks, 'Wrong place', '2026-10-06');
        $upload = \Illuminate\Http\UploadedFile::fake()->createWithContent('cards.csv', file_get_contents($this->csv([$this->row('Wrong place', '10/06/2026')])));

        Livewire::test(WhatnotSheetCheck::class)
            ->assertOk()->assertSee('How it works')
            ->callAction('upload', data: ['since' => '2026-06-01', 'until' => '2026-10-09', 'files_'.$this->cards->id => [$upload]])
            ->assertHasNoActionErrors()
            ->assertSee('Move to right channel')
            ->assertSee('Vortex Breaks →', false)
            ->callAction('apply')
            ->assertSee('Applied.');

        $this->assertSame($this->cards->id, (int) $show->fresh()->whatnot_channel_id);
    }

    public function test_shows_no_export_lists_are_deleted_once_every_channel_is_uploaded(): void
    {
        $this->show($this->cards, 'Real show', '2026-10-01');
        $dupe = $this->show($this->cards, 'Duplicate import', '2026-10-03');
        $paid = $this->show($this->cards, 'Paid but unlisted', '2026-10-03');
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active', 'member_type' => 'streamer']);
        Payout::create(['show_id' => $paid->id, 'streamer_id' => $streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 10, 'status' => 'paid']);
        $draft = Payout::create(['show_id' => $dupe->id, 'streamer_id' => $streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 5, 'status' => 'draft']);

        $cardsSheet = $this->csv([$this->row('Real show', '10/01/2026'), $this->row('Last', '10/05/2026')]);

        // Only one channel uploaded: nothing is deleted.
        $partial = $this->plan([$this->cards->id => [$cardsSheet]]);
        $this->assertFalse($partial['all_channels']);
        $this->assertSame(0, app(WhatnotSheetReconciler::class)->apply($partial, true)['deleted']);
        $this->assertTrue(Show::whereKey($dupe->id)->exists());

        // Every channel uploaded: the unlisted show and its draft pay go; the paid one stays.
        $full = $this->plan([$this->cards->id => [$cardsSheet], $this->breaks->id => [$this->csv([$this->row('Breaks show', '10/02/2026')])]]);
        $this->assertTrue($full['all_channels']);
        $this->assertSame('Has finalized or paid pay', collect($full['items'])->firstWhere('show_id', $paid->id)['blocked']);

        $result = app(WhatnotSheetReconciler::class)->apply($full, true);
        $this->assertSame(1, $result['deleted']);
        $this->assertFalse(Show::whereKey($dupe->id)->exists());
        $this->assertFalse(Payout::whereKey($draft->id)->exists());
        $this->assertTrue(Show::whereKey($paid->id)->exists());
    }

    public function test_before_payroll_is_live_paid_unlisted_shows_can_go_too(): void
    {
        $paid = $this->show($this->cards, 'Paid but unlisted', '2026-10-03');
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active', 'member_type' => 'streamer']);
        $payout = Payout::create(['show_id' => $paid->id, 'streamer_id' => $streamer->id, 'payout_type' => 'profit_share', 'calculated_payout' => 10, 'status' => 'paid']);
        $plan = $this->plan([
            $this->cards->id => [$this->csv([$this->row('Real', '10/01/2026'), $this->row('Last', '10/05/2026')])],
            $this->breaks->id => [$this->csv([$this->row('Breaks show', '10/02/2026')])],
        ]);

        $this->assertSame(1, app(WhatnotSheetReconciler::class)->apply($plan, true, true)['deleted']);
        $this->assertFalse(Show::whereKey($paid->id)->exists());
        $this->assertFalse(Payout::whereKey($payout->id)->exists());
    }

    public function test_only_the_owner_can_open_it(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(WhatnotSheetCheck::getUrl())->assertForbidden();
        $this->assertFalse(WhatnotSheetCheck::canAccess());
    }

    public function test_an_unlisted_show_with_orders_is_never_deleted(): void
    {
        $aired = $this->show($this->cards, 'Renamed on Whatnot', '2026-10-03');
        \App\Models\WhatnotShowOrder::create(['show_id' => $aired->id, 'whatnot_order_id' => 'o-1', 'show_date' => '2026-10-03']);
        $plan = $this->plan([
            $this->cards->id => [$this->csv([$this->row('Real', '10/01/2026'), $this->row('Last', '10/05/2026')])],
            $this->breaks->id => [$this->csv([$this->row('Breaks show', '10/02/2026')])],
        ]);

        $this->assertSame(0, app(WhatnotSheetReconciler::class)->apply($plan, true, true)['deleted']);
        $this->assertTrue(Show::whereKey($aired->id)->exists());
    }

    public function test_the_whole_export_range_is_checked_and_shows_with_sales_are_kept(): void
    {
        // Shop's last show was Aug 10; the app has two more after it.
        $junk = $this->show($this->cards, 'Scheduled, never aired', '2026-09-01');
        $aired = $this->show($this->cards, 'Aired but not listed', '2026-09-02', ['gross_revenue' => 900]);
        $plan = app(WhatnotSheetReconciler::class)->plan([
            $this->cards->id => [$this->csv([$this->row('Early', '06/02/2026'), $this->row('Last', '08/10/2026')])],
            $this->breaks->id => [$this->csv([$this->row('Breaks show', '10/02/2026')])],
        ], '2026-06-01', '2026-10-09');

        $this->assertSame('2026-10-09', $plan['channels'][$this->cards->id]['to']);
        $this->assertSame('2026-08-10', $plan['channels'][$this->cards->id]['last_row']);
        $this->assertSame(1, $plan['channels'][$this->cards->id]['aired_after_last_row'], 'a missing newest page is flagged');
        $this->assertSame('extra', collect($plan['items'])->firstWhere('show_id', $junk->id)['action']);
        $this->assertTrue(collect($plan['items'])->firstWhere('show_id', $aired->id)['aired']);

        $result = app(WhatnotSheetReconciler::class)->apply($plan, true, true);
        $this->assertSame(1, $result['deleted']);
        $this->assertFalse(Show::whereKey($junk->id)->exists());
        $this->assertTrue(Show::whereKey($aired->id)->exists());
    }
}
