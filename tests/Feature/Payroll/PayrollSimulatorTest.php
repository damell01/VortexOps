<?php

namespace Tests\Feature\Payroll;

use App\Filament\Pages\PayrollSimulator;
use App\Models\Business;
use App\Models\Payout;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Models\WhatnotChannel;
use App\Services\PayoutService;
use App\Services\PayrollSimulation;
use App\Support\PaymentStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The simulator works on the real shows, prefilled from real data, and pays
 * them with the real calculation — edits stay in memory.
 */
class PayrollSimulatorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private WhatnotChannel $channel;
    private Business $estill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        $this->actingAs($this->owner);
        $this->channel = WhatnotChannel::create(['name' => 'Estill Cards', 'status' => 'active']);
        $this->estill = Business::where('short_name', 'Estill')->firstOrFail();

        PaymentStructure::saveDefaults('streamer', [
            'payout_type' => 'sales_split', 'payout_cadence' => 'weekly', 'house_cut_percentage' => 20,
            'sales_split_basis' => 'net', 'include_tips' => true,
        ], $this->estill->id);
    }

    private function member(string $name, array $overrides = []): Streamer
    {
        $member = Streamer::create([
            'name' => $name, 'member_type' => 'streamer', 'streamer_type' => 'in_house', 'status' => 'active',
            'business_id' => $this->estill->id, 'compensation_override_fields' => [],
        ]);
        if ($overrides) {
            PaymentStructure::saveOverrides($member, $overrides);
        }

        return $member->fresh();
    }

    private function show(Streamer $streamer, array $extra = [], ?array $report = null): Show
    {
        $show = Show::create($extra + [
            'whatnot_channel_id' => $this->channel->id, 'title' => "Show with {$streamer->name}",
            'show_date' => now()->subWeek()->toDateString(), 'gross_revenue' => 1200, 'whatnot_net' => 1000, 'tips' => 40,
            'status' => 'reconciled', 'created_by' => $this->owner->id,
        ]);
        $show->streamers()->attach($streamer->id, ['is_primary' => true]);
        if ($report !== null) {
            StreamerLogEntry::create($report + ['show_id' => $show->id, 'streamer_id' => $streamer->id, 'status' => 'admin_approved']);
        }

        return $show;
    }

    /** A sales-split person and a shift-share person, each with a show last week. */
    private function scenario(): array
    {
        $split = $this->member('Split Streamer');
        $shift = $this->member('Shift Streamer', [
            'payout_type' => 'shift_share', 'hourly_rate' => 20, 'pwe_rate' => 1, 'label_rate' => 2,
            'payout_percentage' => 10, 'burden_rate_type' => 'flat', 'burden_rate_value' => 50, 'include_tips' => false,
        ]);
        $a = $this->show($split, [], ['hours_streamed' => 3, 'product_cost' => 200]);
        $b = $this->show($shift, ['whatnot_net' => 1500, 'gross_revenue' => 1800], [
            'hours_streamed' => 4, 'product_cost' => 300, 'pwe_count' => 10, 'label_count' => 5, 'number_of_shipments' => 15,
        ]);

        return [$split, $shift, $a, $b];
    }

    private function counts(): array
    {
        return [
            'shows' => Show::count(), 'payouts' => Payout::count(), 'runs' => WeeklyPayoutBatch::count(),
            'reports' => StreamerLogEntry::count(),
            'show_rows' => Show::orderBy('id')->get(['id', 'whatnot_net', 'gross_revenue', 'tips', 'house_cut_override'])->toArray(),
            'report_rows' => StreamerLogEntry::orderBy('id')->get(['id', 'product_cost', 'hours_streamed', 'pwe_count', 'label_count'])->toArray(),
            'people' => Streamer::orderBy('id')->get()->map(fn ($s) => $s->getAttributes())->toArray(),
        ];
    }

    public function test_it_lists_the_real_shows_and_prefills_their_figures_and_rates(): void
    {
        [$split, $shift, $a, $b] = $this->scenario();
        // A show outside the period is not listed.
        $this->show($split, ['show_date' => now()->subMonths(3)->toDateString(), 'title' => 'Old show']);

        $page = Livewire::test(PayrollSimulator::class)
            ->set('from', now()->subWeeks(2)->toDateString())
            ->set('to', now()->toDateString());

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $page->get('showIds'));

        $inputs = $page->get('showInputs');
        $this->assertEqualsWithDelta(1500.0, $inputs[$b->id]['whatnot_net'], 0.001);
        $this->assertEqualsWithDelta(300.0, $inputs[$b->id]['product_cost'], 0.001);
        $this->assertEqualsWithDelta(4.0, $inputs[$b->id]['hours_streamed'], 0.001);
        $this->assertSame(10, $inputs[$b->id]['pwe_count']);
        $this->assertSame(5, $inputs[$b->id]['label_count']);
        $this->assertEqualsWithDelta(40.0, $inputs[$a->id]['tips'], 0.001);
        $this->assertNull($inputs[$a->id]['house_cut_override']);

        $rates = $page->get('rateInputs');
        // The team default for the split streamer, the override for the shift streamer.
        $this->assertSame('sales_split', $rates[$split->id]['payout_type']);
        $this->assertEqualsWithDelta(20.0, $rates[$split->id]['house_cut_percentage'], 0.001);
        $this->assertTrue($rates[$split->id]['include_tips']);
        $this->assertSame('shift_share', $rates[$shift->id]['payout_type']);
        $this->assertEqualsWithDelta(20.0, $rates[$shift->id]['hourly_rate'], 0.001);
        $this->assertEqualsWithDelta(10.0, $rates[$shift->id]['payout_percentage'], 0.001);

        $page->assertOk()->assertSee('Show with Split Streamer')->assertSee('Shift Streamer')->assertDontSee('Old show');
    }

    public function test_unedited_pay_equals_what_payroll_calculates(): void
    {
        [$split, $shift, $a, $b] = $this->scenario();
        // A collab: profit share primary, hybrid with a % burden alongside.
        $lead = $this->member('Collab Lead', ['payout_type' => 'profit_share', 'payout_percentage' => 12, 'hourly_rate' => 15, 'include_tips' => true]);
        $guest = $this->member('Collab Guest', ['payout_type' => 'hybrid', 'hourly_rate' => 18, 'payout_percentage' => 5,
            'burden_rate_type' => 'percentage', 'burden_rate_value' => 3, 'include_tips' => true]);
        $c = $this->show($lead, ['tips' => 30], ['hours_streamed' => 2.5, 'product_cost' => 150, 'number_of_shipments' => 22]);
        $c->streamers()->attach($guest->id, ['is_primary' => false]);
        $shows = [$a, $b, $c];
        $before = $this->counts();

        $sim = app(PayrollSimulation::class);
        $result = $sim->run($sim->showsById([$a->id, $b->id, $c->id]));

        $this->assertSame($before, $this->counts(), 'simulating must not write anything');

        // Now let payroll do it for real and compare, line for line.
        $service = app(PayoutService::class);
        $real = collect($shows)->flatMap(fn (Show $s) => $service->calculateForShow($s->fresh(['streamers'])));
        $rows = collect($result['rows'])->keyBy('id');

        foreach ($shows as $show) {
            foreach ($rows[$show->id]['streamers'] as $person) {
                $simulated = collect($person['lines'])->pluck('amount', 'type')->sortKeys()->all();
                $calculated = $real->where('show_id', $show->id)->where('streamer_id', $person['id'])
                    ->mapWithKeys(fn (Payout $p) => [$p->payout_type => round((float) $p->calculated_payout, 2)])->sortKeys()->all();
                $this->assertNotEmpty($calculated);
                $this->assertEquals($calculated, $simulated, "show {$show->id}, {$person['name']}");
            }
            $this->assertSame(0.0, $rows[$show->id]['delta']);
        }
        $this->assertCount(2, $rows[$c->id]['streamers']);

        // Sales split: $1,000 × 80% + $40 tips = $840. Shift share: ($1,500 − $300 − $50 burden − $80 − $20) × 10% = $105 share + $100 wages.
        $this->assertEqualsWithDelta(840.0, $rows[$a->id]['total'], 0.001);
        $this->assertEqualsWithDelta(205.0, $rows[$b->id]['total'], 0.001);
        $this->assertEqualsWithDelta(round((float) $real->sum('calculated_payout'), 2), $result['total'], 0.001);
        $this->assertSame(0.0, $result['delta']);
    }

    public function test_edits_change_the_pay_without_touching_the_database(): void
    {
        [$split, $shift, $a, $b] = $this->scenario();
        $before = $this->counts();

        $page = Livewire::test(PayrollSimulator::class)
            ->set('from', now()->subWeeks(2)->toDateString())
            ->set('to', now()->toDateString())
            ->set("showInputs.{$a->id}.whatnot_net", '2000')
            ->set("showInputs.{$b->id}.product_cost", '400')
            ->set("rateInputs.{$split->id}.house_cut_percentage", '50')
            ->set("rateInputs.{$shift->id}.payout_type", 'hourly');

        $this->assertSame(['whatnot_net' => 2000.0], $page->instance()->showEdits($a->id));
        $this->assertArrayHasKey('payout_type', $page->instance()->rateEdits($shift->id));

        $result = collect($page->instance()->simulation()['rows'])->keyBy('id');
        // $2,000 × 50% + $40 tips.
        $this->assertEqualsWithDelta(1040.0, $result[$a->id]['total'], 0.001);
        $this->assertEqualsWithDelta(840.0, $result[$a->id]['real_total'], 0.001);
        $this->assertEqualsWithDelta(200.0, $result[$a->id]['delta'], 0.001);
        // Hourly: 4 hrs × $20.
        $this->assertEqualsWithDelta(80.0, $result[$b->id]['total'], 0.001);
        $this->assertEqualsWithDelta(205.0, $result[$b->id]['real_total'], 0.001);

        $page->assertOk()->assertSee('edited');
        $page->call('editShow', $a->id)->assertOk()->call('editStreamer', $shift->id)->assertOk();

        $this->assertSame($before, $this->counts(), 'the simulator must not write anything');

        // Payroll itself still sees the real people and numbers.
        $this->assertSame('shift_share', Streamer::find($shift->id)->payout_type);
        $this->assertEqualsWithDelta(20.0, (float) Streamer::find($split->id)->house_cut_percentage, 0.001);

        // Reset per row and globally.
        $page->call('resetShow', $a->id);
        $this->assertSame([], $page->instance()->showEdits($a->id));
        $page->call('resetAll');
        $this->assertSame([], $page->instance()->showEdits($b->id));
        $this->assertSame([], $page->instance()->rateEdits($shift->id));
        $this->assertSame(0.0, $page->instance()->simulation()['delta']);
    }

    public function test_a_what_if_copy_of_a_person_can_never_be_saved(): void
    {
        $person = $this->member('Real Person');
        $copy = clone $person;
        $copy->whatIfCompensation = ['payout_type' => 'hourly', 'hourly_rate' => 99];

        $this->assertSame('hourly', $copy->payout_type);
        $this->assertSame('sales_split', $person->payout_type);

        $copy->name = 'Changed';
        $this->assertFalse($copy->save());
        $this->assertSame('Real Person', Streamer::find($person->id)->name);
    }

    public function test_a_report_can_be_supplied_for_a_show_that_has_none(): void
    {
        $person = $this->member('No Report', ['payout_type' => 'profit_share', 'payout_percentage' => 10, 'include_tips' => false]);
        $show = $this->show($person, ['tips' => 0]);

        $sim = app(PayrollSimulation::class);
        $shows = $sim->showsById([$show->id]);
        $real = $sim->showInputs($shows->first());
        $this->assertNull($real['product_cost']);

        $result = $sim->run($shows, [$show->id => ['product_cost' => 500] + $real]);
        $row = $result['rows'][0];
        $this->assertSame(['product_cost' => 500.0], $row['edits']);
        $this->assertLessThan($row['real_total'], $row['total']);
        $this->assertSame(0, StreamerLogEntry::count());
    }

    public function test_non_admin_cannot_access_payroll_simulator(): void
    {
        $this->actingAs(User::factory()->create());
        $this->assertFalse(PayrollSimulator::canAccess());
    }
}
