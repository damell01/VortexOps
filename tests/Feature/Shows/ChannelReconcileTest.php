<?php

namespace Tests\Feature\Shows;

use App\Filament\Resources\ShowResource\Pages\ListShows;
use App\Models\Show;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Reconciling a channel-attribution-suspect show: the row action lets an admin
 * confirm/correct the channel and clears the review flag; the bulk action clears
 * the flag across a selection.
 */
class ChannelReconcileTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create(['email' => 'admin@test.com']);
        $this->adminUser->assignRole('admin');
    }

    private function suspectShow(int $channelId): Show
    {
        return Show::create([
            'whatnot_channel_id'          => $channelId,
            'channel_attribution_suspect' => true,
            'title'                       => 'Suspect Show',
            'show_date'                   => '2026-06-15',
            'import_source'               => 'auto_whatnot',
            'status'                      => 'draft',
            'created_by'                  => $this->adminUser->id,
        ]);
    }

    public function test_confirm_channel_action_reassigns_and_clears_flag(): void
    {
        $a = WhatnotChannel::create(['name' => 'Chan A', 'status' => 'active']);
        $b = WhatnotChannel::create(['name' => 'Chan B', 'status' => 'active']);
        $show = $this->suspectShow($a->id);

        Livewire::actingAs($this->adminUser);

        Livewire::test(ListShows::class)
            ->callTableAction('confirm_channel', $show, data: [
                'whatnot_channel_id' => $b->id,
            ]);

        $show->refresh();
        $this->assertFalse((bool) $show->channel_attribution_suspect);
        $this->assertEquals($b->id, $show->whatnot_channel_id);
    }

    public function test_bulk_action_clears_flag_but_keeps_channel(): void
    {
        $a = WhatnotChannel::create(['name' => 'Chan A', 'status' => 'active']);
        $show1 = $this->suspectShow($a->id);
        $show2 = $this->suspectShow($a->id);

        Livewire::actingAs($this->adminUser);

        Livewire::test(ListShows::class)
            ->callTableBulkAction('clear_attribution_flag', [$show1->getKey(), $show2->getKey()]);

        foreach ([$show1, $show2] as $show) {
            $show->refresh();
            $this->assertFalse((bool) $show->channel_attribution_suspect);
            $this->assertEquals($a->id, $show->whatnot_channel_id);
        }
    }
}
