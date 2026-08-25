<?php

namespace Tests\Feature\Shows;

use App\Filament\Resources\FulfillmentResource\Pages\ListFulfillmentShows;
use App\Filament\Widgets\ShowMetricsWidget;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Getting word from the person who ran the stream to the people who ship it.
 */
class FulfillmentHeadsUpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private WhatnotChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create(['email' => 'admin@test.com']);
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        Setting::set('enabled_admin_modules', json_encode(array_keys(AdminModules::definitions())));
        AdminModules::flushMemo();

        $this->channel = WhatnotChannel::create(['name' => 'Chan', 'status' => 'active']);
    }

    private function show(array $attributes = []): Show
    {
        return Show::create(array_merge([
            'whatnot_channel_id' => $this->channel->id,
            'title'              => 'Break Night',
            'show_date'          => today()->toDateString(),
            'status'             => 'draft',
            'created_by'         => $this->admin->id,
        ], $attributes));
    }

    public function test_a_show_can_carry_a_note_and_a_slow_flag(): void
    {
        $show = $this->show([
            'is_slow_pack'      => true,
            'fulfillment_notes' => 'All jumbo boxes — allow an extra hour.',
        ]);

        $show->refresh();

        $this->assertTrue($show->is_slow_pack);
        $this->assertSame('All jumbo boxes — allow an extra hour.', $show->fulfillment_notes);
    }

    public function test_shows_are_not_flagged_by_default(): void
    {
        // A flag everything carries is not a flag.
        $this->assertFalse($this->show()->refresh()->is_slow_pack);
    }

    private function stats(Show $show): array
    {
        $widget = new ShowMetricsWidget();
        $widget->record = $show;

        return collect($widget->getStats())->keyBy('label')->all();
    }

    public function test_the_show_page_says_when_a_show_will_take_a_while(): void
    {
        // It rides on the Shipments tile rather than taking one of its own:
        // it is a fact about shipping this show, and it belongs where someone
        // is already reading the shipping numbers.
        $flagged = $this->show(['is_slow_pack' => true]);

        $this->assertStringContainsString('takes a while', strtolower($this->stats($flagged)['Shipments']['sub']));
    }

    public function test_an_unflagged_show_still_reports_its_shipments_normally(): void
    {
        $ordinary = $this->show();

        $this->assertStringNotContainsString('takes a while', strtolower($this->stats($ordinary)['Shipments']['sub']));
    }

    /**
     * The queue lists non-draft shows that have something to ship, so a
     * fixture without either never appears in it whatever it is flagged.
     */
    private function queuedShow(array $attributes = []): Show
    {
        $show = $this->show(array_merge(['status' => 'reconciled'], $attributes));

        Shipment::create([
            'show_id'         => $show->id,
            'tracking_number' => 'TRK-' . uniqid(),
            'status'          => 'pending',
        ]);

        return $show;
    }

    public function test_the_fulfillment_queue_can_be_narrowed_to_the_long_jobs(): void
    {
        $slow     = $this->queuedShow(['title' => 'Jumbo Night', 'is_slow_pack' => true]);
        $ordinary = $this->queuedShow(['title' => 'Quick Night']);

        Livewire::test(ListFulfillmentShows::class)
            ->loadTable()
            ->filterTable('is_slow_pack', true)
            ->assertCanSeeTableRecords([$slow])
            ->assertCanNotSeeTableRecords([$ordinary]);
    }

    public function test_the_queue_can_also_be_narrowed_to_the_quick_ones(): void
    {
        // Finding the short jobs to clear a queue is the other half of it.
        $slow     = $this->queuedShow(['title' => 'Jumbo Night', 'is_slow_pack' => true]);
        $ordinary = $this->queuedShow(['title' => 'Quick Night']);

        Livewire::test(ListFulfillmentShows::class)
            ->loadTable()
            ->filterTable('is_slow_pack', false)
            ->assertCanSeeTableRecords([$ordinary])
            ->assertCanNotSeeTableRecords([$slow]);
    }
}
