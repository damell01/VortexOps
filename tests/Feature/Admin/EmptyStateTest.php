<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ShowResource\Pages\ListShows;
use App\Filament\Resources\StreamerResource\Pages\ListStreamers;
use App\Filament\Resources\VendorResource\Pages\ListVendors;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Core resource lists show a helpful empty state with a call-to-action on a
 * fresh install instead of a bare "No records".
 */
class EmptyStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminModules::flushMemo();
        config(['app.owner_email' => 'dbellcreations@gmail.com']);
        // Owner has full access, so the empty state itself is what's under test
        // rather than per-resource access gating.
        $owner = User::factory()->create(['email' => 'dbellcreations@gmail.com']);
        Livewire::actingAs($owner);
    }

    public function test_shows_list_shows_import_cta_when_empty(): void
    {
        Livewire::test(ListShows::class)
            ->loadTable()
            ->assertSee('No shows yet')
            ->assertSee('Import from Whatnot');
    }

    public function test_streamers_list_shows_create_cta_when_empty(): void
    {
        Livewire::test(ListStreamers::class)
            ->loadTable()
            ->assertSee('No streamers yet')
            ->assertSee('Add your first streamer');
    }

    public function test_vendors_list_shows_create_cta_when_empty(): void
    {
        Livewire::test(ListVendors::class)
            ->assertSee('No vendors yet')
            ->assertSee('Add a vendor');
    }
}
