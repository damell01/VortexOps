<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\PalletReceivingOverviewWidget;
use App\Models\Pallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PalletReceivingOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_pallet_receiving_overview_renders_without_a_server_error(): void
    {
        Pallet::factory()->create(['status' => 'staged']);

        Livewire::test(PalletReceivingOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Waiting to Receive')
            ->assertSee('Active Receipt Progress');
    }
}
