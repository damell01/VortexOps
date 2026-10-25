<?php

namespace Tests\Feature\Payroll;

use App\Filament\Resources\WeeklyPayoutBatchResource\Pages\ViewWeeklyPayoutBatch;
use App\Models\Payout;
use App\Models\Streamer;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Support\PayrollRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A pay run lists everyone in one place, one section per role, rather than
 * streamers in the list and assistants in a box of their own above it.
 */
class PayRunRoleSectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_are_grouped_by_role_in_order_with_assistant_hours_alongside(): void
    {
        $this->enableAdminModules();
        $owner = User::factory()->create(['email' => config('app.owner_email')]);
        $this->actingAs($owner);
        $run = WeeklyPayoutBatch::create(['week_start' => '2026-10-19', 'week_end' => '2026-10-25', 'status' => 'draft', 'created_by' => $owner->id]);

        $role = Role::findOrCreate('streamer_assistant', 'web');
        $paidAssistant = User::factory()->create(['name' => 'Avery Assistant']);
        $paidAssistant->assignRole($role);
        $unpaidAssistant = User::factory()->create(['name' => 'Blake Helper']);
        $unpaidAssistant->assignRole($role);

        foreach ([
            ['Sam Streamer', 'streamer', 300, null],
            ['Avery Assistant', 'assistant', 90, $paidAssistant->id],
            ['Fran Fulfillment', 'fulfillment', 120, null],
            ['Olly Other', 'manager', 40, null], // an unknown role still gets a section
        ] as [$name, $type, $amount, $userId]) {
            $person = Streamer::create(['name' => $name, 'member_type' => $type, 'status' => 'active', 'user_id' => $userId]);
            Payout::create(['weekly_payout_batch_id' => $run->id, 'streamer_id' => $person->id, 'payout_type' => 'hourly', 'calculated_payout' => $amount, 'status' => 'draft']);
        }
        foreach ([$paidAssistant, $unpaidAssistant] as $user) {
            TimeEntry::create(['user_id' => $user->id, 'clocked_in_at' => Carbon::parse('2026-10-21 09:00'), 'clocked_out_at' => Carbon::parse('2026-10-21 12:00')]);
        }

        $html = Livewire::test(ViewWeeklyPayoutBatch::class, ['record' => $run->id])
            ->assertDontSee('Assistant hours') // the separate box is gone
            ->assertSeeInOrder(['Streamers', 'Sam Streamer', 'Streamer assistants', 'Avery Assistant', 'Fulfillment', 'Fran Fulfillment', 'Other team members', 'Olly Other'])
            ->assertSee('3.00 h clocked')
            ->assertSee('Streamer assistants with hours but no pay line')
            ->assertSee('Blake Helper')
            ->html();

        $this->assertSame(1, substr_count($html, 'data-payroll-role="assistant"'));
        $this->assertSame('other', PayrollRoles::of(Streamer::firstWhere('name', 'Olly Other')));
        $this->assertSame('streamer', PayrollRoles::of(new Streamer()));
    }
}
