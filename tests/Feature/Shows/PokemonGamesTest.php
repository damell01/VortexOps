<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\MyPokedex;
use App\Models\Pallet;
use App\Models\PokemonCatch;
use App\Models\PokemonEgg;
use App\Models\PokemonEvolution;
use App\Models\PokemonTrade;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Support\Pokedex;
use App\Support\PokemonData;
use App\Support\PokemonGames;
use App\Support\Trainer;
use App\Support\VisitorCast;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The Pokémon games beyond a report's own catch (App\Support\PokemonGames). */
class PokemonGamesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Streamer $streamer;
    private Trainer $trainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-18 12:00', 'America/Chicago')); // a Wednesday, outside any catch event
        Setting::set('show_inventory_posting_policy', 'never');
        $this->user = User::factory()->create(['email' => 'trainer@example.test', 'name' => 'Ash Ketchum']);
        $this->streamer = Streamer::create(['user_id' => $this->user->id, 'name' => 'Ash', 'status' => 'active', 'member_type' => 'streamer']);
        $this->trainer = Trainer::forStreamer($this->streamer);
    }

    private function give(Trainer $t, string $species, int $n = 1, string $source = 'test'): void
    {
        for ($i = 0; $i < $n; $i++) {
            PokemonCatch::create($t->owner() + ['species' => $species, 'source' => $source, 'source_key' => $source . ':' . uniqid('', true), 'shiny' => false, 'caught_at' => now()->subDays(30)]);
        }
    }

    /** An approved report for a show on $date, filed on time unless $late. */
    private function approvedReport(string $date, bool $late = false): StreamerLogEntry
    {
        $show = Show::create(['title' => 'Show ' . uniqid(), 'show_date' => $date, 'status' => 'draft']);
        $report = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'status' => 'pending']);
        $report->forceFill(['submitted_at' => CarbonImmutable::parse($date)->addDays($late ? 5 : 0)->setTime(23, 0)])->save();
        $report->update(['status' => 'admin_approved']);

        return $report->refresh();
    }

    public function test_every_species_the_games_use_has_a_sprite_a_shiny_and_types(): void
    {
        $all = array_unique(array_merge(array_keys(Pokedex::DEX), array_keys(PokemonData::EXTRA), PokemonData::DELIVERY,
            PokemonData::NIGHT, PokemonData::MORNING, PokemonData::SUNNY, PokemonData::RAINY, PokemonData::STARTERS,
            array_keys(PokemonData::EVOLUTIONS), array_merge(...array_values(PokemonData::EVOLUTIONS))));
        foreach ($all as $species) {
            $this->assertTrue(Pokedex::exists($species), "$species is a known species");
            $this->assertFileExists(public_path("img/pokemon/{$species}.gif"));
            $this->assertFileExists(public_path("img/pokemon/shiny/{$species}.gif"));
            $this->assertArrayHasKey($species, PokemonData::TYPES, "$species has types");
        }
        $this->assertSame([1, 2, 3], [PokemonData::stage('charmander'), PokemonData::stage('charmeleon'), PokemonData::stage('charizard')]);
        $this->assertEmpty(array_intersect_key(PokemonData::EXTRA, Pokedex::DEX), 'earned-only species are not in the report roll');
    }

    public function test_a_new_trainer_catches_in_kanto_and_opens_johto_by_catching_most_of_it(): void
    {
        $this->assertSame([1], PokemonGames::unlockedRegions($this->trainer));
        $this->assertTrue(collect(PokemonGames::rollPool($this->trainer))->keys()->every(fn ($s) => (Pokedex::DEX[$s][1] ?? 1) === 1), 'Pokédex species from Kanto only (event species join any running event)');

        $kanto = collect(Pokedex::DEX)->filter(fn ($e) => $e[1] === 1)->keys();
        $kanto->take((int) ceil($kanto->count() * PokemonData::REGION_UNLOCK_SHARE))->each(fn ($s) => $this->give($this->trainer, $s));

        $this->assertSame([1, 2], PokemonGames::unlockedRegions($this->trainer));
    }

    public function test_five_on_time_reports_in_a_row_evolve_the_oldest_first_form(): void
    {
        $this->give($this->trainer, 'charmander');
        $first = PokemonCatch::sole();
        foreach (['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'] as $d) {
            $this->approvedReport($d);
        }
        $this->assertSame(0, PokemonEvolution::count(), 'four is not enough');

        $this->approvedReport('2026-11-14');

        $this->assertSame(1, PokemonEvolution::count());
        $this->assertSame(['charmeleon', 'charmander'], [$first->refresh()->species, $first->evolved_from], 'the oldest first form evolves');
    }

    public function test_a_late_report_breaks_the_streak(): void
    {
        $this->give($this->trainer, 'charmander');
        foreach (['2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'] as $d) $this->approvedReport($d);
        $this->approvedReport('2026-11-14', late: true);

        $this->assertSame(0, PokemonEvolution::count());
        $this->assertSame(0, PokemonGames::streak($this->streamer->id));
    }

    public function test_a_perfect_week_earns_an_egg_that_hatches_after_three_more_catches(): void
    {
        // Last week (Nov 16–22): three shows, each report filed the same night and approved.
        foreach (['2026-11-16', '2026-11-17', '2026-11-18'] as $d) $this->approvedReport($d);
        $this->assertSame(0, PokemonEgg::count(), 'nothing until a report lands the week after');

        $this->travelTo(CarbonImmutable::parse('2026-11-23 12:00', 'America/Chicago'));
        $this->approvedReport('2026-11-23');

        $egg = PokemonEgg::sole();
        $this->assertSame('perfect_week', $egg->reason);
        foreach (['2026-11-23', '2026-11-23', '2026-11-23'] as $d) $this->approvedReport($d);
        $egg->refresh();
        $this->assertNotNull($egg->hatched_at, 'hatched after three more catches');
        $this->assertSame('egg', PokemonCatch::find($egg->hatched_catch_id)->source);
    }

    public function test_one_late_report_spoils_a_perfect_week(): void
    {
        $this->approvedReport('2026-11-16');
        $this->approvedReport('2026-11-17', late: true);
        $this->approvedReport('2026-11-18');
        $this->travelTo(CarbonImmutable::parse('2026-11-23 12:00', 'America/Chicago'));
        $this->approvedReport('2026-11-23');

        $this->assertSame(0, PokemonEgg::count());
    }

    public function test_releasing_needs_a_duplicate_and_five_releases_earn_an_egg(): void
    {
        $this->give($this->trainer, 'psyduck', 2);
        $this->give($this->trainer, 'mew');

        $this->assertFalse(PokemonGames::release($this->trainer, 'mew'), 'never the last of a kind');
        $this->assertTrue(PokemonGames::release($this->trainer, 'psyduck'));
        $this->assertFalse(PokemonGames::release($this->trainer, 'psyduck'));
        $this->assertSame(1, PokemonGames::counts($this->trainer)['psyduck']);

        $this->give($this->trainer, 'pidgey', 5);
        foreach (range(1, 4) as $i) PokemonGames::release($this->trainer, 'pidgey');
        $this->assertSame('releases', PokemonEgg::sole()->reason);
    }

    public function test_a_trade_swaps_two_spares_and_never_a_last_one(): void
    {
        $other = User::factory()->create(['email' => 'misty@example.test', 'name' => 'Misty']);
        $misty = Trainer::forUser($other);
        $this->give($this->trainer, 'psyduck', 2);
        $this->give($misty, 'staryu', 2);

        $this->assertIsString(PokemonGames::proposeTrade($this->user, 'mew', $other, 'staryu'), 'no spare to give');
        $trade = PokemonGames::proposeTrade($this->user, 'psyduck', $other, 'staryu');
        $this->assertInstanceOf(PokemonTrade::class, $trade);
        $this->assertSame('Only the person it was offered to can answer it.', PokemonGames::answerTrade($trade, $this->user, true));

        PokemonGames::answerTrade($trade->refresh(), $other, true);

        $this->assertSame('accepted', $trade->refresh()->status);
        $this->assertSame([1, 1], [PokemonGames::counts($this->trainer)['psyduck'], PokemonGames::counts($this->trainer)['staryu']]);
        $this->assertSame([1, 1], [PokemonGames::counts($misty)['psyduck'], PokemonGames::counts($misty)['staryu']]);

        // A spare that stopped being spare calls the trade off.
        $this->give($this->trainer, 'psyduck');
        $this->give($misty, 'staryu');
        $late = PokemonGames::proposeTrade($this->user, 'psyduck', $other, 'staryu');
        PokemonCatch::ownedBy($misty)->where('species', 'staryu')->latest('id')->first()->update(['released_at' => now()]);
        PokemonGames::answerTrade($late, $other, true);
        $this->assertSame('void', $late->refresh()->status);
    }

    public function test_the_raid_legendary_goes_to_everyone_who_caught_this_month_once_last_months_sales_are_beaten(): void
    {
        // The goal sets itself: last month's Whatnot gross.
        Show::create(['title' => 'October', 'show_date' => '2026-10-20', 'status' => 'draft', 'gross_revenue' => 1000]);
        Show::create(['title' => 'Big one', 'show_date' => '2026-11-05', 'status' => 'draft', 'gross_revenue' => 900]);
        $this->assertSame(1000.0, PokemonGames::raidGoal());
        $this->give($this->trainer, 'pidgey');
        PokemonCatch::query()->update(['caught_at' => now()]);
        $this->assertNull(PokemonGames::claimRaid($this->trainer), 'not beaten yet');
        Show::create(['title' => 'Bigger one', 'show_date' => '2026-11-06', 'status' => 'draft', 'gross_revenue' => 600]);
        \Illuminate\Support\Facades\Cache::flush();
        $idle = Trainer::forUser(User::factory()->create(['email' => 'idle@example.test']));

        $won = PokemonGames::claimRaid($this->trainer);

        $this->assertSame(PokemonGames::raidBoss(), $won->species);
        $this->assertSame('legendary', Pokedex::rarity($won->species));
        $this->assertSame($won->id, PokemonGames::claimRaid($this->trainer)?->id ?? $won->id, 'once a month');
        $this->assertSame(1, PokemonCatch::where('source', 'raid')->count());
        $this->assertNull(PokemonGames::claimRaid($idle), 'only trainers who caught something this month');
    }

    public function test_work_catches_pokemon_for_whoever_did_it_once(): void
    {
        $admin = User::factory()->create(['email' => 'boss@example.test']);
        $this->actingAs($admin);

        $batch = WeeklyPayoutBatch::create(['week_start' => '2026-11-09', 'week_end' => '2026-11-15', 'status' => 'draft']);
        $batch->update(['status' => 'paid']);
        $batch->update(['status' => 'draft']);
        $batch->update(['status' => 'paid']);
        $pallet = Pallet::create(['status' => 'pending', 'created_by' => $admin->id]);
        $pallet->update(['status' => 'received']);

        $mine = PokemonCatch::where('user_id', $admin->id)->pluck('source')->sort()->values()->all();
        $this->assertSame(['payrun', 'safari'], $mine, 'one for the pay run, one for the pallet');
        $this->assertNull(PokemonCatch::where('user_id', $admin->id)->first()->streamer_id);
    }

    public function test_every_tenth_approval_catches_the_approver_one(): void
    {
        $admin = User::factory()->create(['email' => 'approver@example.test']);
        foreach (range(1, 10) as $i) {
            $show = Show::create(['title' => "Approve $i", 'show_date' => '2026-11-10', 'status' => 'draft']);
            $r = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'status' => 'pending']);
            $r->forceFill(['reviewed_by' => $admin->id, 'status' => 'admin_approved'])->save();
            $this->assertSame($i === 10 ? 1 : 0, PokemonCatch::where('source', 'approvals')->count(), "after $i");
        }
    }

    public function test_the_pokedex_page_works_for_streamers_and_staff_and_badges_count_types(): void
    {
        $this->give($this->trainer, 'charmander', 10);
        $this->actingAs($this->user);
        Livewire::test(MyPokedex::class)->assertOk()->assertSee('Team raid')->assertSee('Kanto')->assertSee('is-gold', false)->assertSee('Release one');

        $staff = User::factory()->create(['email' => 'packer@example.test']);
        $this->actingAs($staff);
        Livewire::test(MyPokedex::class)->assertOk()->assertSee('Mystery Eggs');

        $badges = collect(PokemonGames::badges(collect(['charmander' => 1])))->keyBy('key');
        $this->assertSame(1, $badges['fire']['have']);
        $this->assertFalse($badges['fire']['earned']);
    }

    public function test_visitors_change_with_the_hour_and_the_week(): void
    {
        $night = VisitorCast::ambient(['top' => ['gengar'], 'bottom' => ['snorlax']], CarbonImmutable::parse('2026-11-18 23:00'), 'rain');
        $this->assertCount(3, $night['bottom']);
        $this->assertContains($night['bottom'][0], PokemonData::NIGHT);
        $this->assertContains($night['bottom'][1], PokemonData::RAINY);
        $this->assertSame(['gengar'], $night['top']);

        $noon = VisitorCast::ambient(['top' => [], 'bottom' => ['snorlax']], CarbonImmutable::parse('2026-11-18 13:00'), null);
        $this->assertSame(['snorlax'], $noon['bottom']);
    }
}
