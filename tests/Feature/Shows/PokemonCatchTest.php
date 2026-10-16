<?php

namespace Tests\Feature\Shows;

use App\Filament\Widgets\StreamerPokedexWidget;
use App\Models\PokemonCatch;
use App\Models\Setting;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Notifications\VortexAlert;
use App\Support\Pokedex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Every approved show report catches its streamer a Pokémon, once. */
class PokemonCatchTest extends TestCase
{
    use RefreshDatabase;

    private Streamer $streamer;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('show_inventory_posting_policy', 'never');
        $this->user = User::factory()->create(['email' => 'dex@example.test']);
        $this->streamer = Streamer::create(['user_id' => $this->user->id, 'name' => 'Ash', 'status' => 'active', 'member_type' => 'streamer']);
    }

    private function report(): StreamerLogEntry
    {
        $show = Show::create(['title' => 'Show ' . uniqid(), 'show_date' => today()->toDateString(), 'status' => 'draft']);

        return StreamerLogEntry::create(['show_id' => $show->id, 'streamer_id' => $this->streamer->id, 'status' => 'pending']);
    }

    public function test_the_same_report_always_rolls_the_same_pokemon_and_rare_ones_are_rare(): void
    {
        $this->assertSame(Pokedex::roll(42), Pokedex::roll(42));

        $n = 20000;
        $rolls = collect(range(1, $n))->map(fn ($id) => Pokedex::roll($id));
        $this->assertTrue($rolls->every(fn ($r) => Pokedex::exists($r[0])));
        $share = fn (string $rarity) => $rolls->filter(fn ($r) => Pokedex::rarity($r[0]) === $rarity)->count() / $n;
        $total = array_sum(array_map(fn ($e) => Pokedex::WEIGHTS[$e[2]], Pokedex::DEX));
        $expected = fn (string $rarity) => collect(Pokedex::DEX)->filter(fn ($e) => $e[2] === $rarity)->count() * Pokedex::WEIGHTS[$rarity] / $total;
        foreach (['common', 'uncommon', 'rare', 'legendary'] as $rarity) {
            $this->assertEqualsWithDelta($expected($rarity), $share($rarity), .01, $rarity);
        }
        $this->assertLessThan($share('rare'), $share('legendary'));
        $this->assertEqualsWithDelta(1 / Pokedex::SHINY_ODDS, $rolls->filter(fn ($r) => $r[1])->count() / $n, .006);
        $this->assertCount(count(Pokedex::DEX), $rolls->pluck(0)->unique(), 'every species can be caught');
    }

    public function test_every_species_has_a_sprite_and_a_shiny(): void
    {
        foreach (array_keys(Pokedex::DEX) as $species) {
            $this->assertFileExists(public_path("img/pokemon/{$species}.gif"), $species);
            $this->assertFileExists(public_path("img/pokemon/shiny/{$species}.gif"));
        }
    }

    public function test_the_pokedex_has_100_pokemon_from_all_nine_generations(): void
    {
        $this->assertCount(100, Pokedex::DEX);
        $this->assertSame(range(1, 9), collect(Pokedex::DEX)->pluck(1)->unique()->sort()->values()->all());
        $numbers = collect(Pokedex::DEX)->pluck(0);
        $this->assertSame($numbers->sort()->values()->all(), $numbers->values()->all(), 'in Pokédex order');
        $this->assertSame('Ho-Oh', Pokedex::name('ho-oh'));
    }

    public function test_an_auto_approved_report_catches_one_and_only_one(): void
    {
        Setting::set('show_report_review_policy', 'auto');
        $report = $this->report();

        $report->submitReport();
        $report->refresh()->update(['notes' => 'edited']);
        $report->update(['status' => 'admin_approved']); // re-approval

        $this->assertSame(1, PokemonCatch::where('streamer_log_entry_id', $report->id)->count());
        $caught = PokemonCatch::sole();
        $this->assertSame(Pokedex::roll($report->id, \App\Support\Season::event())[0], $caught->species);
        $this->assertSame($this->streamer->id, $caught->streamer_id);
    }

    public function test_nothing_is_caught_until_the_report_is_approved_and_an_admin_approval_says_what_was_caught(): void
    {
        Setting::set('show_report_review_policy', 'required');
        $report = $this->report();
        $report->submitReport();
        $this->assertSame(0, PokemonCatch::count());

        $this->actingAs(User::factory()->create(['email' => 'admin-dex@example.test']));
        $report->refresh()->approveByAdmin();

        $this->assertSame(1, PokemonCatch::count());
        $this->assertStringContainsString(Pokedex::name(PokemonCatch::sole()->species), $this->user->notifications()->latest()->first()?->data['body'] ?? '');
    }

    public function test_the_pokedex_counts_species_shinies_and_the_latest_catch(): void
    {
        foreach ([['psyduck', false], ['psyduck', true], ['mew', false]] as [$species, $shiny]) {
            $r = $this->report();
            PokemonCatch::create(['streamer_id' => $this->streamer->id, 'streamer_log_entry_id' => $r->id, 'species' => $species, 'shiny' => $shiny, 'caught_at' => now()]);
        }

        $dex = Pokedex::forStreamer($this->streamer);
        $psyduck = $dex['entries']->firstWhere('species', 'psyduck');

        $this->assertSame([2, 3, 2, 1], [$dex['caught'], $dex['catches'], $psyduck['count'], $psyduck['shiny']]);
        $this->assertSame('mew', $dex['latest']->species);
        $this->assertSame(count(Pokedex::DEX), $dex['total']);
    }

    public function test_the_profile_widget_shows_their_pokedex(): void
    {
        $this->actingAs(User::factory()->create(['email' => config('app.owner_email')]));
        $r = $this->report();
        PokemonCatch::create(['streamer_id' => $this->streamer->id, 'streamer_log_entry_id' => $r->id, 'species' => 'snorlax', 'shiny' => false, 'caught_at' => now()]);

        Livewire::test(StreamerPokedexWidget::class, ['record' => $this->streamer])
            ->assertSee('Ash’s Pokédex')->assertSee('1 of ' . count(Pokedex::DEX) . ' caught')->assertSee('Snorlax')->assertSee('???');
    }

    public function test_the_wild_report_alert_carries_the_pokemon_and_the_toast_finds_it(): void
    {
        $this->user->notify(new VortexAlert(event: 'report_filed', title: 'A wild report appeared!', body: 'Ash filed Show and caught a Mew!',
            tone: 'success', links: ['Open report' => 'https://example.test/r/1'], extra: ['species' => 'mew', 'shiny' => true]));
        $this->user->notify(new VortexAlert(event: 'low_stock', title: 'Low stock', body: 'x'));

        $this->actingAs($this->user)->getJson('/admin/wild-report.json')
            ->assertOk()->assertJson(['title' => 'A wild report appeared!', 'species' => 'mew', 'shiny' => true, 'url' => 'https://example.test/r/1']);
    }

    public function test_there_is_no_toast_without_a_recent_wild_report(): void
    {
        $this->actingAs($this->user)->getJson('/admin/wild-report.json')->assertOk()->assertExactJson([]);
    }
}
