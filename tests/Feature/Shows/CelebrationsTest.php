<?php

namespace Tests\Feature\Shows;

use App\Filament\Pages\EditProfile;
use App\Models\Payout;
use App\Models\PokemonCatch;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WeeklyPayoutBatch;
use App\Support\Celebrations;
use App\Support\Pokedex;
use App\Support\Season;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Celebrations: each person's level, the milestones, and the seasonal catch events. */
class CelebrationsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Streamer $streamer;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.season' => '', 'app.display_timezone' => 'America/Chicago']);
        $this->user = User::factory()->create(['email' => 'fx@example.test']);
        $this->streamer = Streamer::create(['user_id' => $this->user->id, 'name' => 'Misty', 'status' => 'active', 'member_type' => 'streamer']);
    }

    private function filed(array $show = []): array
    {
        $show = Show::create($show + ['title' => 'Show ' . uniqid(), 'show_date' => today()->toDateString(), 'status' => 'draft']);
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'status' => 'streamer_reviewed']);
        $entry->forceFill(['submitted_at' => now()])->save();

        return [$entry, $show];
    }

    public function test_everyone_starts_on_all_and_can_choose_milestones_only_or_off_on_their_profile(): void
    {
        $this->assertSame('all', Celebrations::level($this->user->fresh()));
        $this->assertSame('all', Celebrations::level(null));

        $this->actingAs($this->user->fresh());
        Livewire::test(EditProfile::class)->set('data.celebrations', 'milestones')->call('save')->assertHasNoErrors();
        $this->assertSame('milestones', Celebrations::level($this->user->fresh()));

        $this->user->forceFill(['celebrations' => 'nonsense'])->save();
        $this->assertSame('all', Celebrations::level($this->user->fresh()), 'an unknown value falls back to all');
    }

    public function test_the_page_carries_the_level_and_the_running_event_without_an_extra_request(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'America/Chicago'));
        $this->user->forceFill(['celebrations' => 'milestones'])->save();
        $payload = Celebrations::payload($this->user->fresh());

        $this->assertSame('milestones', $payload['level']);
        $this->assertSame('halloween', $payload['event']['key']);
        $this->assertSame('gengar', $payload['event']['swap']['machamp']);
        $this->assertNotEmpty($payload['event']['pool']);
    }

    public function test_a_first_report_is_its_own_milestone(): void
    {
        [$entry, $show] = $this->filed();

        $this->assertSame(['kind' => 'first_report'], Celebrations::forReport($entry, $show));
    }

    public function test_the_best_whatnot_net_yet_is_a_record_show(): void
    {
        foreach ([300, 450, 380] as $net) $this->filed(['whatnot_net' => $net]);
        [$entry, $show] = $this->filed(['whatnot_net' => 920]);

        $this->assertSame(['kind' => 'record_show', 'amount' => '$920.00', 'key' => (string) $show->id], Celebrations::forReport($entry, $show));

        [$entry, $show] = $this->filed(['whatnot_net' => 500]);
        $this->assertNull(Celebrations::forReport($entry, $show), 'not a record, and no end time to be on time against');
    }

    public function test_a_report_within_two_hours_of_the_show_ending_is_on_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 21:30', 'America/Chicago'));
        $this->filed();
        [$entry, $show] = $this->filed(['show_date' => '2026-10-20', 'start_time' => '18:00', 'end_time' => '20:00']);
        $this->assertSame(['kind' => 'on_time'], Celebrations::forReport($entry, $show));

        $this->travelTo(CarbonImmutable::parse('2026-10-20 23:30', 'America/Chicago'));
        $this->assertNull(Celebrations::forReport($entry, $show));
    }

    public function test_payday_is_the_latest_paid_run_holding_their_pay(): void
    {
        $this->assertNull(Celebrations::payday($this->user));
        \Illuminate\Support\Facades\Cache::flush(); // it's remembered for five minutes
        [, $show] = $this->filed();
        $batch = WeeklyPayoutBatch::create(['week_start' => '2026-10-05', 'week_end' => '2026-10-11', 'status' => 'paid']);
        Payout::create(['show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'weekly_payout_batch_id' => $batch->id, 'payout_type' => 'hourly', 'calculated_payout' => 412.5, 'status' => 'paid']);

        $this->assertSame(['kind' => 'payday', 'key' => (string) $batch->id, 'amount' => '$412.50'], Celebrations::payday($this->user->fresh()));
    }

    public function test_everyone_paid_only_when_every_run_for_those_dates_is_paid(): void
    {
        $a = WeeklyPayoutBatch::create(['week_start' => '2026-10-05', 'week_end' => '2026-10-11', 'status' => 'paid']);
        $this->assertFalse(Celebrations::allPaidFor($a), 'a run on its own gets the usual cheer');
        $b = WeeklyPayoutBatch::create(['week_start' => '2026-10-05', 'week_end' => '2026-10-11', 'status' => 'finalized', 'kind' => 'tips']);
        $this->assertFalse(Celebrations::allPaidFor($a));
        $b->forceFill(['status' => 'paid'])->save();
        $this->assertTrue(Celebrations::allPaidFor($a));
    }

    public function test_catch_events_follow_the_calendar(): void
    {
        $at = fn (string $d) => Season::event(CarbonImmutable::parse($d));
        $this->assertSame('halloween', $at('2026-10-01'));
        $this->assertSame('harvest', $at('2026-11-15'));
        $this->assertSame('winter', $at('2026-12-24'));
        $this->assertSame('winter', $at('2027-01-05'));
        $this->assertNull($at('2027-01-06'));
        $this->assertSame('valentines', $at('2027-02-14'));
        $this->assertNull($at('2027-02-15'));
        $this->assertSame('spring', $at('2027-04-02'));
        $this->assertNull($at('2027-05-10'));
        $this->assertSame('summer', $at('2027-07-04'));
        $this->assertNull($at('2027-09-10'));

        config(['app.season' => 'harvest']);
        $this->assertSame('harvest', Season::event(CarbonImmutable::parse('2026-06-01')), 'APP_SEASON previews one');
    }

    public function test_every_event_pokemon_and_stand_in_has_a_sprite_and_a_shiny(): void
    {
        foreach (Season::EVENTS as $key => $event) {
            $names = [...array_keys($event['species']), ...$event['boost'], ...array_values($event['swap']), ...$event['pool']];
            foreach (array_unique($names) as $species) {
                $this->assertFileExists(public_path("img/pokemon3d/{$species}.gif"), "{$key}: {$species}");
                $this->assertFileExists(public_path("img/pokemon3d/shiny/{$species}.gif"), "{$key}: shiny {$species}");
            }
            foreach ($event['boost'] as $species) $this->assertArrayHasKey($species, Pokedex::DEX, "{$key} boosts {$species}, which must be in the Pokédex");
            foreach (array_keys($event['species']) as $species) $this->assertArrayNotHasKey($species, Pokedex::DEX, "{$species} is event-only");
        }
        foreach (['chansey', 'machamp', 'kangaskhan', 'porygon', 'bronzong', 'smeargle', 'hoothoot', 'noctowl', 'alakazam', 'kadabra', 'slowbro', 'slowking', 'swablu', 'unown', 'bidoof', 'pelipper', 'wingull', 'voltorb', 'sudowoodo', 'chatot', 'sableye', 'machoke'] as $species) {
            $this->assertFileExists(public_path("img/pokemon3d/{$species}.gif"), "celebration sprite {$species}");
        }
    }

    public function test_during_an_event_its_pokemon_turn_up_more_and_its_own_can_be_caught(): void
    {
        $n = 4000;
        $plain = collect(range(1, $n))->map(fn ($id) => Pokedex::roll($id)[0]);
        $spooky = collect(range(1, $n))->map(fn ($id) => Pokedex::roll($id, 'halloween')[0]);
        $ghosts = Season::EVENTS['halloween']['boost'];
        $eventOnly = array_keys(Season::EVENTS['halloween']['species']);

        $this->assertSame(0, $plain->intersect($eventOnly)->count(), 'event-only Pokémon never turn up outside their event');
        $this->assertGreaterThan(0, $spooky->intersect($eventOnly)->count());
        $this->assertGreaterThan($plain->intersect($ghosts)->count() * 2, $spooky->intersect($ghosts)->count(), 'ghosts are far more likely in October');
        $this->assertSame(Pokedex::roll(7, 'halloween'), Pokedex::roll(7, 'halloween'), 'still the same catch for the same report');
        $this->assertTrue($spooky->every(fn ($s) => Pokedex::exists($s)));
        $this->assertSame('uncommon', Pokedex::rarity('haunter'));
        $this->assertSame(93, Pokedex::number('haunter'));
        $this->assertSame('Farfetch’d', Pokedex::name('farfetchd'));
    }

    public function test_a_report_approved_in_october_rolls_from_the_halloween_draw(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'America/Chicago'));
        \App\Models\Setting::set('show_inventory_posting_policy', 'never');
        $show = Show::create(['title' => 'Spooky', 'show_date' => today()->toDateString(), 'status' => 'draft']);
        $entry = StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'status' => 'pending']);
        // The draw is the regions this trainer has opened, plus the running event's species.
        $pool = \App\Support\PokemonGames::rollPool(\App\Support\Trainer::forStreamer($this->streamer));
        $entry->update(['status' => 'admin_approved']);

        $this->assertArrayHasKey('pumpkaboo', $pool, 'Halloween species join the draw');
        $this->assertSame(Pokedex::roll($entry->id, 'halloween', $pool)[0], PokemonCatch::sole()->species);
    }

    public function test_the_pokedex_lists_the_running_event_and_any_event_catches(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'America/Chicago'));
        [$entry] = $this->filed();
        PokemonCatch::create(['streamer_id' => $this->streamer->id, 'streamer_log_entry_id' => $entry->id, 'species' => 'cubchoo', 'shiny' => false, 'caught_at' => now()]);
        $dex = Pokedex::forStreamer($this->streamer);

        $this->assertSame(['halloween', 'winter'], $dex['events']->pluck('key')->all(), "the running event, and winter because Cubchoo's been caught");
        $this->assertTrue($dex['events']->first()['running']);
        $this->assertSame(1, $dex['events']->last()['entries']->firstWhere('species', 'cubchoo')['count']);
        $this->assertSame(1, $dex['catches']);
        $this->assertSame(0, $dex['caught'], 'the main Pokédex still counts its own 100');

        $html = view('components.vx.pokedex', ['streamer' => $this->streamer])->render();
        $this->assertStringContainsString('data-vx-dex-event="halloween"', $html);
        $this->assertStringContainsString('Spooky season event', $html);
    }

    public function test_streamers_open_their_pokedex_from_the_dashboard_and_staff_have_one_too(): void
    {
        $this->actingAs($this->user->fresh());
        $this->assertTrue(\App\Filament\Pages\MyPokedex::canAccess());
        Livewire::test(\App\Filament\Pages\MyPokedex::class)->assertOk()->assertSee('Your Pokédex')->assertSee('See all 100 Pokémon');
        $this->assertStringContainsString('MyPokedex::getUrl()', file_get_contents(resource_path('views/livewire/streamer-home.blade.php')), 'the dashboard links to it');

        // Staff catch Pokémon for their work (App\Support\PokemonGames), so they have one too.
        $this->actingAs(User::factory()->create());
        $this->assertTrue(\App\Filament\Pages\MyPokedex::canAccess(), 'everyone signed in is a trainer');
        Livewire::test(\App\Filament\Pages\MyPokedex::class)->assertOk()->assertSee('Mystery Eggs');
        auth()->logout();
        $this->assertFalse(\App\Filament\Pages\MyPokedex::canAccess(), 'signed out, no Pokédex');
    }

    public function test_visitors_drop_in_all_year_with_the_seasons_cast(): void
    {
        $sep = Season::visitors(CarbonImmutable::parse('2026-09-10'));
        $this->assertSame(Season::EVERYDAY_VISITORS, $sep, 'no season: the regulars');

        $oct = Season::visitors(CarbonImmutable::parse('2026-10-20'));
        $this->assertSame('gengar', $oct['top'][0], 'Gengar hangs from the top bar in October');
        $this->assertContains('gastly', $oct['bottom']);

        $xmas = Season::visitors(CarbonImmutable::parse('2026-12-24'));
        $this->assertSame('delibird', $xmas['bottom'][0], "the holiday's own visitor leads");

        foreach (['2026-09-10', '2026-10-20', '2026-11-12', '2026-12-24', '2027-02-14', '2027-04-02', '2027-07-04'] as $day) {
            foreach (Season::visitors(CarbonImmutable::parse($day)) as $cast) {
                foreach ($cast as $mon) $this->assertFileExists(public_path("img/pokemon3d/{$mon}.gif"), "{$day}: {$mon}");
            }
        }
    }

    public function test_visitors_render_unless_celebrations_are_off(): void
    {
        $this->actingAs($this->user->fresh());
        $html = view('filament.components.season')->render();
        $this->assertSame(2, substr_count($html, 'vx-season-peek'));
        $this->assertStringContainsString('from-top', $html);

        $this->user->forceFill(['celebrations' => 'off'])->save();
        $this->actingAs($this->user->fresh());
        $this->assertStringNotContainsString('vx-season-peek', view('filament.components.season')->render());
    }
}
