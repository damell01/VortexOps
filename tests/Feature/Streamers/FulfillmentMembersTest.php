<?php

namespace Tests\Feature\Streamers;

use App\Filament\Resources\StreamerResource\Pages\ListStreamers;
use App\Models\Payout;
use App\Models\Streamer;
use App\Models\User;
use App\Support\AdminModules;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Fulfillment staff are paid the same way streamers are.
 *
 * Every rate column — payout type, percentage, package, hourly, PWE, label,
 * burden, cadence, ADP id — already lives on the streamers row, and the whole
 * payout pipeline is keyed on streamer_id. A separate table would have meant a
 * polymorphic payee and a second copy of every payout rule, which is how two
 * ways of computing the same pay end up disagreeing.
 *
 * So the record is the same record. Only what the person does is new.
 */
class FulfillmentMembersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('enabled_admin_modules', json_encode(['operations', 'payouts', 'streams']));
        AdminModules::flushMemo();
    }

    private function member(string $type, array $attributes = []): Streamer
    {
        return Streamer::create(array_merge([
            'name'        => ucfirst($type) . ' Person',
            'status'      => 'active',
            'member_type' => $type,
            'payout_type' => 'hourly',
            'hourly_rate' => 22.50,
        ], $attributes));
    }

    private function owner(): User
    {
        return (User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh();
    }

    public function test_a_fulfillment_member_carries_the_same_pay_terms(): void
    {
        $packer = $this->member('fulfillment', ['payout_type' => 'pwe_labels', 'pwe_rate' => 1.25, 'label_rate' => 0.35]);

        $this->assertSame('pwe_labels', $packer->payout_type);
        $this->assertSame(1.25, (float) $packer->pwe_rate);
        $this->assertSame(0.35, (float) $packer->label_rate);
    }

    public function test_the_payout_pipeline_accepts_one_without_changes(): void
    {
        // The point of keeping one table: nothing about paying somebody had to
        // learn what a fulfillment member is.
        $packer = $this->member('fulfillment');

        $payout = Payout::create([
            'streamer_id' => $packer->id,
            'payout_type' => 'hourly',
            'amount'      => 180.00,
            'status'      => 'pending',
        ]);

        $this->assertSame($packer->id, $payout->fresh()->streamer_id);
    }

    public function test_each_list_shows_the_people_who_belong_in_it(): void
    {
        $streamer = $this->member('streamer');
        $packer   = $this->member('fulfillment');

        $this->assertEqualsCanonicalizing(
            [$streamer->id],
            Streamer::query()->streamers()->pluck('id')->all(),
        );

        $this->assertEqualsCanonicalizing(
            [$packer->id],
            Streamer::query()->fulfillment()->pluck('id')->all(),
        );
    }

    public function test_somebody_who_does_both_appears_under_both(): void
    {
        $both = $this->member('both');

        $this->assertContains($both->id, Streamer::query()->streamers()->pluck('id')->all());
        $this->assertContains($both->id, Streamer::query()->fulfillment()->pluck('id')->all());
        $this->assertTrue($both->isStreamer());
        $this->assertTrue($both->isFulfillment());
    }

    public function test_existing_people_default_to_streamers(): void
    {
        // The table held nothing but streamers before this column existed, so
        // the default has to keep every one of them in the list they have
        // always appeared in rather than stranding them under no role at all.
        $existing = Streamer::create(['name' => 'Already Here', 'status' => 'active', 'payout_type' => 'profit_share']);

        $this->assertSame('streamer', $existing->fresh()->member_type);
        $this->assertContains($existing->id, Streamer::query()->streamers()->pluck('id')->all());
    }

    public function test_the_roster_page_groups_them(): void
    {
        $this->member('streamer');
        $this->member('fulfillment');

        Livewire::actingAs($this->owner())
            ->test(ListStreamers::class)
            ->assertOk()
            ->assertSee('Fulfillment');
    }

    public function test_the_fulfillment_tab_lists_only_fulfillment_people(): void
    {
        $streamer = $this->member('streamer', ['name' => 'On Camera']);
        $packer   = $this->member('fulfillment', ['name' => 'At The Bench']);

        Livewire::actingAs($this->owner())
            ->test(ListStreamers::class)
            ->set('activeTab', 'fulfillment')
            // The roster table defers loading, so its records are not rendered
            // until something asks for them.
            ->loadTable()
            ->assertCanSeeTableRecords([$packer])
            ->assertCanNotSeeTableRecords([$streamer]);
    }
}
