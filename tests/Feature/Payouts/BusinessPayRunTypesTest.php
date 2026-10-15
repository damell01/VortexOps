<?php

namespace Tests\Feature\Payouts;

use App\Models\PayRunType;
use App\Support\BusinessPayRunTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Estill's and VCX's pay run types are there without setting them up. */
class BusinessPayRunTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_estill_and_vcx_types_are_ready(): void
    {
        $types = PayRunType::whereIn('name', array_column(BusinessPayRunTypes::PRESETS, 0))->orderBy('id')->get()
            ->map(fn (PayRunType $t) => [$t->name, $t->kind, $t->frequency, $t->schedule_enabled, $t->auto_create, $t->active])->all();

        $this->assertSame([
            ['Estill Weekly', null, 'weekly', true, false, true],
            ['Estill Monthly Tips', 'tips', 'monthly', true, false, true],
            ['VCX Weekly Wages', 'wages', 'weekly', true, false, true],
            ['VCX Monthly Profit Share', 'share', 'monthly', true, false, true],
        ], $types);
    }

    public function test_running_it_again_adds_nothing(): void
    {
        $this->assertSame(0, BusinessPayRunTypes::ensure());
        $this->assertSame(1, PayRunType::where('name', 'Estill Weekly')->count());
    }
}
