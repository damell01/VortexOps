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
        Pallet::create(['status' => 'staged', 'reference' => 'Receiving overview']);

        Livewire::test(PalletReceivingOverviewWidget::class)
            ->assertSuccessful()
            ->assertSee('Waiting to Receive')
            ->assertSee('Active Receipt Progress');
    }
}
