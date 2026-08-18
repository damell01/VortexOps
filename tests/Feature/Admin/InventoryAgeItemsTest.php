<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\InventoryAge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A bucket has to be able to say which products are in it.
 *
 * The page reported four totals and nothing else, so "$5,455 is over 90 days
 * old" told you there was a problem without telling you which stock to go and
 * look at. The query already read every row it needed to answer that — it just
 * discarded the identity while summing.
 */
class InventoryAgeItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DemoDataSeeder::class);

        $this->actingAs(
            User::firstWhere('email', config('app.owner_email')) ?? User::firstOrFail()
        );
    }

    private function buckets(): array
    {
        return (new InventoryAge())->getBuckets()['buckets'];
    }

    public function test_each_bucket_lists_the_products_behind_its_total(): void
    {
        $withStock = array_values(array_filter($this->buckets(), fn ($b) => $b['units'] > 0));

        $this->assertNotEmpty($withStock, 'Demo data should put stock in at least one bucket.');

        foreach ($withStock as $bucket) {
            $this->assertNotEmpty($bucket['items'], "{$bucket['label']} has units but lists no products.");

            foreach ($bucket['items'] as $item) {
                $this->assertNotEmpty($item['name']);
                $this->assertNotEmpty($item['location']);
                $this->assertGreaterThan(0, $item['units']);
            }
        }
    }

    public function test_an_empty_bucket_lists_nothing(): void
    {
        // Collected rather than asserted in the loop: with demo data every
        // bucket may hold stock, and a loop of skips would pass having
        // asserted nothing at all.
        $emptyBucketsListingItems = [];

        foreach ($this->buckets() as $bucket) {
            if ($bucket['units'] <= 0 && $bucket['items'] !== []) {
                $emptyBucketsListingItems[] = $bucket['label'];
            }
        }

        $this->assertSame([], $emptyBucketsListingItems, 'Buckets with no units still listed products.');
    }

    public function test_the_listed_units_add_up_to_the_bucket_total(): void
    {
        foreach ($this->buckets() as $bucket) {
            // Only checkable while nothing was trimmed off the end.
            if ($bucket['hidden_items'] > 0) {
                continue;
            }

            $this->assertEqualsWithDelta(
                $bucket['units'],
                array_sum(array_column($bucket['items'], 'units')),
                0.01,
                "{$bucket['label']}: the products listed do not add up to the total shown.",
            );
        }
    }

    public function test_items_are_ordered_by_value_so_the_costly_stock_is_first(): void
    {
        foreach ($this->buckets() as $bucket) {
            $values = array_column($bucket['items'], 'value');
            $sorted = $values;
            rsort($sorted);

            $this->assertSame($sorted, $values, "{$bucket['label']} is not ordered by value.");
        }
    }

    public function test_a_bucket_opens_and_closes(): void
    {
        $key = 'fresh';

        Livewire::test(InventoryAge::class)
            ->assertSet('openBucket', null)
            ->call('toggleBucket', $key)
            ->assertSet('openBucket', $key)
            // Same bucket again closes it rather than reopening.
            ->call('toggleBucket', $key)
            ->assertSet('openBucket', null);
    }
}
