<?php

namespace Tests\Feature\Reliability;

use App\Filament\Widgets\NeedsAttentionWidget;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\DuplicateShowFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DuplicateShowFinderTest extends TestCase
{
    use RefreshDatabase;

    private WhatnotChannel $breaks;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 12:00:00');
        $this->breaks = WhatnotChannel::create(['name' => 'Vortex Breaks', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function show(array $data): Show
    {
        return Show::withoutEvents(fn () => Show::create(array_merge([
            'title' => 'Show', 'show_date' => '2026-10-08', 'status' => 'reconciled', 'whatnot_channel_id' => $this->breaks->id,
        ], $data)));
    }

    public function test_same_stream_imported_twice_is_paired(): void
    {
        $a = $this->show(['title' => '🍀MASSIVE GIVVYS AND $1 STARTS W/NIKO🍀', 'whatnot_show_id' => 'abc', 'gross_revenue' => 900]);
        $b = $this->show(['title' => 'MASSIVE GIVVYS AND $1 STARTS w/Niko', 'gross_revenue' => 900]);
        // Different day, different channel, different title, cancelled: none of these pair.
        $this->show(['title' => 'MASSIVE GIVVYS AND $1 STARTS W/NIKO', 'show_date' => '2026-10-09']);
        $other = WhatnotChannel::create(['name' => 'Vortex Cards', 'status' => 'active']);
        $this->show(['title' => 'MASSIVE GIVVYS AND $1 STARTS W/NIKO', 'whatnot_channel_id' => $other->id]);
        $this->show(['title' => 'Trail Blazing Thursday w/ Connor']);
        $this->show(['title' => 'MASSIVE GIVVYS AND $1 STARTS W/NIKO', 'status' => 'cancelled']);

        $pairs = (new DuplicateShowFinder)->pairs();

        $this->assertCount(1, $pairs);
        $this->assertSame([$a->id, $b->id], [$pairs[0]['a']->id, $pairs[0]['b']->id]);
        $this->assertSame(100, $pairs[0]['similarity']);
    }

    public function test_two_real_streams_upcoming_shows_and_dismissed_pairs_are_left_out(): void
    {
        // Whatnot gave them different IDs and they started hours apart: a part 2.
        $this->show(['title' => 'Sunday Funday', 'whatnot_show_id' => 'one', 'start_time' => '2026-10-08 14:00:00']);
        $this->show(['title' => 'Sunday Funday', 'whatnot_show_id' => 'two', 'start_time' => '2026-10-08 19:00:00']);
        // Not run yet.
        $this->show(['title' => 'Tomorrow night', 'show_date' => '2026-10-13']);
        $this->show(['title' => 'Tomorrow night', 'show_date' => '2026-10-13']);
        $a = $this->show(['title' => 'Packs every 5 minutes']);
        $b = $this->show(['title' => 'Packs every 5 minutes!']);

        $finder = new DuplicateShowFinder;
        $this->assertSame(["{$a->id}-{$b->id}"], array_column($finder->pairs(), 'key'));

        $finder->dismiss("{$a->id}-{$b->id}");
        $this->assertSame([], (new DuplicateShowFinder)->pairs());
    }

    public function test_needs_attention_lists_pairs_and_can_dismiss_one(): void
    {
        $this->enableAdminModules();
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $a = $this->show(['title' => 'FREE BOOSTER PACKS', 'gross_revenue' => 1200, 'whatnot_show_id' => 'x1']);
        $b = $this->show(['title' => 'Free Booster Packs', 'gross_revenue' => 1200]);

        Livewire::test(NeedsAttentionWidget::class)
            ->assertSee('possible duplicate shows')
            ->call('openDetail', 'duplicates')
            ->assertSee('FREE BOOSTER PACKS · $1,200.00')->assertSee('Free Booster Packs · $1,200.00 · no Whatnot ID')
            ->assertSee('Not a duplicate')
            ->call('dismissDuplicate', "{$a->id}-{$b->id}")
            ->assertNotified('Marked as two separate shows')
            ->assertDontSee('possible duplicate shows');

        Livewire::test(NeedsAttentionWidget::class)->call('dismissDuplicate', 'drop table')->assertStatus(422);
    }
}
