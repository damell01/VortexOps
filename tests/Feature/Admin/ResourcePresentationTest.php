<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the presentation layer that has no other test: the columns a list
 * page renders, and the details a resource contributes to global search.
 *
 * Both are places where a callback reaches for a relation or a count. Lazy
 * loading is disabled outside production, so anything the query forgot to
 * eager-load throws rather than silently issuing an extra query — which is
 * what makes rendering these a real assertion.
 *
 * That guard only arms itself on multi-row results: Builder::hydrate() sets
 * the per-model flag under `count($items) > 1`, so a ->first() would sail past
 * a missing eager load. Hence walking the whole collection below.
 */
class ResourcePresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);
    }

    private function admin(): User
    {
        return User::firstWhere('email', 'dbellcreations@gmail.com') ?? User::firstOrFail();
    }

    public static function listPages(): array
    {
        return [
            'payouts'   => [\App\Filament\Resources\PayoutResource\Pages\ListPayouts::class],
            'streamers' => [\App\Filament\Resources\StreamerResource\Pages\ListStreamers::class],
            'locations' => [\App\Filament\Resources\InventoryLocationResource\Pages\ListInventoryLocations::class],
            'inventory' => [\App\Filament\Resources\InventoryItemResource\Pages\ListInventoryItems::class],
        ];
    }

    #[DataProvider('listPages')]
    public function test_list_page_renders_with_demo_data(string $page): void
    {
        Livewire::actingAs($this->admin())->test($page)->assertOk();
    }

    public static function searchableResources(): array
    {
        return [
            'shows'     => [\App\Filament\Resources\ShowResource::class],
            'streamers' => [\App\Filament\Resources\StreamerResource::class],
            'payouts'   => [\App\Filament\Resources\PayoutResource::class],
            'locations' => [\App\Filament\Resources\InventoryLocationResource::class],
            'vendors'   => [\App\Filament\Resources\VendorResource::class],
            'inventory' => [\App\Filament\Resources\InventoryItemResource::class],
        ];
    }

    #[DataProvider('searchableResources')]
    public function test_global_search_details_resolve(string $resource): void
    {
        $this->actingAs($this->admin());

        $records = $resource::getGlobalSearchEloquentQuery()->get();

        $this->assertGreaterThan(
            1,
            $records->count(),
            "{$resource} needs more than one demo record — the lazy-loading guard does not arm on a single row.",
        );

        $sawFigure = false;

        foreach ($records as $record) {
            $details = $resource::getGlobalSearchResultDetails($record);

            $sawFigure = $sawFigure || isset($details['figure']);

            $this->assertNotEmpty($resource::getGlobalSearchResultTitle($record));

            // The override reads these as layout slots rather than printing
            // them as labels, so a stray capitalised key would quietly render
            // as a plain label/value pair instead of the intended tile.
            foreach (array_keys($details) as $key) {
                $this->assertContains(
                    $key,
                    ['subtitle', 'status', 'tone', 'figure'],
                    "{$resource} returned an unrecognised global-search key: {$key}",
                );
            }

            if (isset($details['status'])) {
                $this->assertArrayHasKey('tone', $details, "{$resource} sets a status pill with no tone to colour it.");
            }

            if (isset($details['tone'])) {
                $this->assertContains(
                    $details['tone'],
                    ['success', 'warning', 'danger', 'info', 'neutral'],
                    "{$resource} returned a tone with no matching pill style.",
                );
            }
        }

        // Every resource here means to pin a figure. A count that was never
        // selected yields null rather than throwing, so the slot just goes
        // quiet — this is what notices that.
        $this->assertTrue($sawFigure, "{$resource} produced no figure for any demo record.");
    }
}
