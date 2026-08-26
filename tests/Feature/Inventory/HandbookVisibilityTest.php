<?php

namespace Tests\Feature\Inventory;

use App\Filament\Pages\Handbook;
use App\Filament\Resources\PalletResource;
use App\Models\User;
use App\Support\HandbookVisibility;
use App\Support\InventoryManual;
use App\Models\Setting;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The handbook describes the app you have.
 *
 * A walkthrough of a screen you cannot open is instructions for a button that
 * is not there, and it reads as "this app is broken for me" rather than "that
 * is not your job". So the handbook asks each screen whether this person may
 * open it — the same question the sidebar asks — and shows what is left.
 */
class HandbookVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** A role that has been given an explicit visible list without pallets on it. */
    private function userWithoutPallets(): User
    {
        Role::findOrCreate('restricted', 'web');

        // An explicit list is a grant: what is on it is visible, and everything
        // else — pallets included — is not.
        NavVisibility::setVisibleForRole('restricted', [
            \App\Filament\Resources\InventoryItemResource::class,
            Handbook::class,
        ]);

        $user = User::factory()->create(['email' => 'restricted-handbook@example.test']);
        $user->assignRole('restricted');

        return $user->fresh();
    }

    public function test_a_step_for_a_screen_you_cannot_open_is_not_shown(): void
    {
        $this->actingAs($this->userWithoutPallets());

        $titles = [];

        foreach (HandbookVisibility::sections(InventoryManual::sections()) as $section) {
            $titles = array_merge($titles, array_column($section['steps'], 'title'));
        }

        $this->assertNotContains('Every button at the receiving station', $titles);
        $this->assertContains('Add an item properly', $titles);
    }

    public function test_a_section_with_nothing_left_in_it_disappears_entirely(): void
    {
        // Otherwise the contents list keeps an entry that opens onto nothing,
        // which is a worse experience than the section never existing.
        $this->actingAs($this->userWithoutPallets());

        $titles = array_column(HandbookVisibility::sections(InventoryManual::sections()), 'title');

        $this->assertNotContains('Receiving a delivery', $titles);
    }

    public function test_the_screen_index_lists_only_screens_you_can_reach(): void
    {
        $this->actingAs($this->userWithoutPallets());

        $screens = array_column(HandbookVisibility::screens(InventoryManual::screenIndex()), 0);

        $this->assertNotContains('Pallets', $screens);
        $this->assertContains('All Inventory', $screens);
    }

    public function test_the_page_and_its_counts_agree_with_the_filtering(): void
    {
        $page = Livewire::actingAs($this->userWithoutPallets())->test(Handbook::class);

        $page->assertOk()
            ->assertDontSee('Receiving a delivery')
            ->assertSee('The catalogue');

        // The masthead counts what is left, not what exists.
        $this->assertSame(
            array_sum(array_map(fn ($s) => count($s['steps']), $page->instance()->allSections())),
            $page->instance()->totalSteps,
        );
    }

    public function test_a_search_cannot_reach_around_the_filter(): void
    {
        // The obvious hole: hide the section, then find its steps by typing.
        $page = Livewire::actingAs($this->userWithoutPallets())
            ->test(Handbook::class)
            ->set('search', 'receiving station');

        $page->assertDontSee('Every button at the receiving station');
    }

    public function test_a_symptom_about_a_screen_you_cannot_open_is_not_listed(): void
    {
        $this->actingAs($this->userWithoutPallets());

        $symptoms = array_column(
            HandbookVisibility::troubleshooting(InventoryManual::troubleshooting()),
            0,
        );

        $this->assertNotContains('A pallet line will not receive', $symptoms);

        // Advice that names no screen is advice wherever you are.
        $this->assertContains('A scan is slow or will not settle', $symptoms);
        $this->assertContains('A screen in this handbook is not in your sidebar', $symptoms);
    }

    public function test_the_owner_still_sees_the_whole_book(): void
    {
        $owner = User::firstWhere('email', config('app.owner_email'))
            ?? User::factory()->create(['email' => config('app.owner_email')]);

        $this->actingAs($owner->fresh());

        $this->assertSame(
            count(InventoryManual::sections()),
            count(HandbookVisibility::sections(InventoryManual::sections())),
        );
    }

    public function test_every_step_names_a_screen_that_exists(): void
    {
        // The filter is only as good as the mapping: a step naming a class that
        // has since been renamed would be shown to everyone for ever, and
        // nobody would notice it had stopped being filtered.
        $missing = [];

        foreach (InventoryManual::sections() as $section) {
            foreach ($section['steps'] as $step) {
                $screen = $step['screen'] ?? null;

                if ($screen === null) {
                    $missing[] = $step['title'] . ' (names no screen)';
                    continue;
                }

                foreach ((array) $screen as $class) {
                    if (! class_exists($class)) {
                        $missing[] = $step['title'] . ' → ' . $class;
                    }
                }
            }
        }

        $this->assertSame([], $missing, "steps whose screen cannot be resolved:\n" . implode("\n", $missing));
    }

    public function test_pallet_steps_come_back_when_the_screen_does(): void
    {
        $user = $this->userWithoutPallets();

        // Pallets live in the purchasing module, which is off in a fresh
        // install — so the section is hidden by the module before any role is
        // consulted. Both have to be open for the steps to come back.
        Setting::set('enabled_admin_modules', json_encode(['inventory', 'purchasing']));
        AdminModules::flushMemo();

        NavVisibility::setVisibleForRole('restricted', [
            \App\Filament\Resources\InventoryItemResource::class,
            PalletResource::class,
            Handbook::class,
        ]);

        // The visible lists are memoised per request; without this the second
        // write is invisible and the test would be checking the first one.
        NavVisibility::flushMemo();

        $this->actingAs($user->fresh());

        $titles = array_column(HandbookVisibility::sections(InventoryManual::sections()), 'title');

        $this->assertContains('Receiving a delivery', $titles);
    }
}
