<?php

namespace Tests\Feature\Inventory;

use App\Models\User;
use App\Support\InventoryManual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The printed handbook.
 *
 * A PDF is not inspectable the way a page is, so what is checked here is the
 * things that make one useless: a step with no picture, a picture that is not
 * on disk, and a document that does not actually build.
 */
class InventoryManualPdfTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return (User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]))->fresh();
    }

    public function test_every_screenshot_the_manual_names_is_on_disk(): void
    {
        // A missing picture in a printed manual is not recoverable by the
        // reader the way a broken image on a page is.
        $missing = [];

        foreach (InventoryManual::sections() as $section) {
            foreach ($section['steps'] as $step) {
                if (! $step['shot']) {
                    continue;
                }

                $path = public_path(InventoryManual::IMAGE_DIR . '/' . $step['shot']);

                if (! is_file($path)) {
                    $missing[] = $step['shot'] . ' (' . $step['title'] . ')';
                }
            }
        }

        $this->assertSame([], $missing, "screenshots referenced but not on disk:\n" . implode("\n", $missing));
    }

    public function test_every_step_shows_the_screen_it_describes(): void
    {
        foreach (InventoryManual::sections() as $section) {
            foreach ($section['steps'] as $step) {
                $this->assertNotNull(
                    $step['shot'],
                    "\"{$step['title']}\" in \"{$section['title']}\" has no screenshot",
                );
                $this->assertNotEmpty($step['body'], "\"{$step['title']}\" has no text");
            }
        }
    }

    public function test_it_covers_the_jobs_the_handbook_is_for(): void
    {
        $text = json_encode(InventoryManual::sections());

        foreach ([
            'Add an item properly',
            'Edit an item',
            'Transfer between locations',
            'Stage the pallet',
            'Receive it',
            'Getting a UPC onto an item after the fact',
        ] as $job) {
            $this->assertStringContainsString($job, $text, "the handbook does not cover: {$job}");
        }
    }

    public function test_bulk_receive_and_scanning_each_box_are_both_explained(): void
    {
        // Two ways to work a pallet, and people pick between them per line —
        // a manual describing only one of them sends someone hunting.
        $text = json_encode(InventoryManual::sections());

        $this->assertStringContainsString('Receive all', $text);
        $this->assertStringContainsString('Mark short', $text);
        $this->assertStringContainsString('Receive Scan', $text);
    }

    public function test_the_pdf_builds_and_is_a_pdf(): void
    {
        $response = $this->actingAs($this->owner())
            ->get(route('export.inventory-manual-pdf'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        // Big enough to be carrying its screenshots rather than a blank shell.
        $this->assertGreaterThan(200_000, strlen($response->getContent()));
    }

    public function test_it_is_not_public(): void
    {
        // Screenshots of this install show real vendors, real costs and real
        // stock, so the document is behind auth like every other export.
        $this->get(route('export.inventory-manual-pdf'))->assertRedirect();
    }
}
