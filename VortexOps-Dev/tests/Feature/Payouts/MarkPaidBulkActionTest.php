<?php

namespace Tests\Feature\Payouts;

use App\Filament\Resources\PayoutResource\Pages\ListPayouts;
use App\Models\Payout;
use App\Models\Streamer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarkPaidBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $u = User::factory()->create(['email' => 'admin@test.com']);
        $u->assignRole('admin');

        return $u;
    }

    private function payout(string $status): Payout
    {
        $streamer = Streamer::create(['name' => 'S', 'status' => 'active', 'payout_type' => 'flat_rate']);

        return Payout::create([
            'streamer_id'       => $streamer->id,
            'payout_type'       => 'flat_rate',
            'calculated_payout' => 100,
            'gross_show_revenue' => 500,
            'status'            => $status,
        ]);
    }

    public function test_mark_paid_only_advances_approved_payouts(): void
    {
        $approved = $this->payout('approved');
        $draft    = $this->payout('draft');
        $alreadyPaid = $this->payout('paid');

        Livewire::actingAs($this->admin());

        Livewire::test(ListPayouts::class)
            ->callTableBulkAction('mark_paid', [$approved->id, $draft->id, $alreadyPaid->id]);

        $this->assertEquals('paid', $approved->fresh()->status);   // advanced
        $this->assertEquals('draft', $draft->fresh()->status);     // skipped — not approved
        $this->assertEquals('paid', $alreadyPaid->fresh()->status); // untouched
    }
}
