<?php
namespace Tests\Feature\Streamers;

use App\Livewire\ClockWidget;
use App\Models\{Show, TimeEntry, User};
use App\Services\TimeTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StreamerAssistantDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('streamer_assistant', 'web'));
        $this->actingAs($user);
    }

    public function test_clock_in_and_out_need_no_show_and_only_touch_own_time(): void
    {
        $other = User::factory()->create();
        $otherEntry = TimeEntry::create(['user_id' => $other->id, 'clocked_in_at' => now()->subHour(), 'status' => 'ok']);
        $this->travelTo(now()->startOfMinute());
        $clock = Livewire::test(ClockWidget::class)->assertSee('Clock in')->assertDontSee('Choose a show')->call('clockIn')->assertSee('Clock out');
        $entry = TimeEntry::where('user_id', auth()->id())->sole();
        $this->assertNull($entry->show_id);
        $this->assertSame('other', $entry->reason);
        $this->travel(2)->hours();
        $clock->call('clockOut')->assertSee('Clock in');
        $this->assertSame(120, $entry->fresh()->payableMinutes());
        $this->assertNull($otherEntry->fresh()->clocked_out_at);
    }

    public function test_show_information_cannot_be_loaded_or_linked_from_the_clock(): void
    {
        $show = Show::create(['title' => 'Private show', 'show_date' => today(), 'status' => 'draft']);
        $this->assertSame([], app(TimeTrackingService::class)->pickerShows(auth()->user()));
        Livewire::test(ClockWidget::class)->set('showId', (string) $show->id)->call('clockIn')
            ->assertDontSee('Private show')->assertDontSee('Change show or reason');
        $this->assertNull(TimeEntry::where('user_id', auth()->id())->sole()->show_id);
        Livewire::test(ClockWidget::class)->call('pickShow', $show->id)->assertForbidden();
        Livewire::test(ClockWidget::class)->call('startChange')->assertForbidden();
        Livewire::test(ClockWidget::class)->call('saveLink')->assertForbidden();
    }
}
