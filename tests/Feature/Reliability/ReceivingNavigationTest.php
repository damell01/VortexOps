<?php

namespace Tests\Feature\Reliability;

use App\Filament\Pages\PalletReceivingHistory;
use App\Filament\Resources\VendorResource\Pages\ViewVendor;
use App\Filament\Resources\VendorResource\RelationManagers\ReceivingHistoryRelationManager;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\User;
use App\Models\Vendor;
use App\Support\DashboardShortcuts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReceivingNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        Queue::fake();
        $owner = User::firstWhere('email', config('app.owner_email')) ?? User::factory()->create(['email'=>config('app.owner_email')]);
        $this->actingAs($owner);
    }

    public function test_history_includes_open_receipts_and_vendor_filter_is_scoped(): void
    {
        $a = Vendor::create(['name'=>'Vendor A', 'status'=>'active']);
        $b = Vendor::create(['name'=>'Vendor B', 'status'=>'active']);
        $open = Pallet::create(['vendor_id'=>$a->id, 'name'=>'Partial delivery', 'status'=>'receiving']);
        $complete = Pallet::create(['vendor_id'=>$a->id, 'name'=>'Completed delivery', 'status'=>'received']);
        $other = Pallet::create(['vendor_id'=>$b->id, 'name'=>'Other delivery', 'status'=>'received']);
        $staged = Pallet::create(['vendor_id'=>$a->id, 'name'=>'Not started', 'status'=>'staged']);
        PalletLine::create(['pallet_id'=>$open->id, 'description'=>'Boxes', 'case_count'=>4, 'quantity_per_case'=>2, 'unit_cost'=>5]);
        Livewire::test(PalletReceivingHistory::class)
            ->assertCanSeeTableRecords([$open,$complete,$other])->assertCanNotSeeTableRecords([$staged])
            ->set('vendorId',$a->id)->assertCanSeeTableRecords([$open,$complete])->assertCanNotSeeTableRecords([$other])
            ->searchTable('Partial')->assertCanSeeTableRecords([$open])->assertCanNotSeeTableRecords([$complete])
            ->searchTable('Vendor A')->assertCanSeeTableRecords([$open,$complete]);
        $record = PalletReceivingHistory::historyQuery()->findOrFail($open->id);
        $this->assertEquals(4,$record->expected_cases_sum);
        $this->assertEquals(0,$record->received_cases_count);
        $this->assertSame('receiving',$open->fresh()->status);
        Livewire::test(ReceivingHistoryRelationManager::class, ['ownerRecord'=>$a,'pageClass'=>ViewVendor::class])
            ->assertCanSeeTableRecords([$open,$complete])->assertCanNotSeeTableRecords([$other,$staged]);
    }

    public function test_history_summary_drawer_and_filtered_export_are_read_only(): void
    {
        $a=Vendor::create(['name'=>'Drawer vendor','status'=>'active']);
        $b=Vendor::create(['name'=>'Excluded vendor','status'=>'active']);
        $open=Pallet::create(['vendor_id'=>$a->id,'name'=>'Partial drawer delivery','status'=>'receiving']);
        PalletLine::create(['pallet_id'=>$open->id,'description'=>'Boxes','case_count'=>4,'quantity_per_case'=>2,'unit_cost'=>5]);
        Pallet::create(['vendor_id'=>$a->id,'name'=>'Finished delivery','status'=>'received']);
        Pallet::create(['vendor_id'=>$b->id,'name'=>'Outside scope','status'=>'received']);
        $component=Livewire::test(PalletReceivingHistory::class)->set('vendorId',$a->id);
        $this->assertSame(['Total deliveries'=>2,'Completed'=>1,'In progress'=>1,'Discrepancies'=>0],$component->instance()->historySummary());
        $record=PalletReceivingHistory::historyQuery()->findOrFail($open->id)->loadMissing(['attachments','createdBy']);
        $html=view('filament.tables.receiving-details',['record'=>$record])->render();
        $this->assertStringContainsString('Partial',view('filament.tables.receiving-card',['getRecord'=>fn()=>$record])->render());
        $this->assertStringContainsString('Not recorded',$html);
        $this->assertStringContainsString('Resume receiving',$html);
        $component->filterTable('status','receiving');
        $response=$component->instance()->exportHistory();
        ob_start(); $response->sendContent(); $csv=ob_get_clean();
        $this->assertStringContainsString('Partial drawer delivery',$csv);
        $this->assertStringNotContainsString('Finished delivery',$csv);
        $this->assertStringNotContainsString('Outside scope',$csv);
        $this->assertSame('receiving',$open->fresh()->status);
        $this->assertSame(0,$open->cases()->count());
    }

    public function test_admin_and_fulfillment_admin_have_same_business_shortcuts(): void
    {
        foreach (['admin','fulfillment_admin'] as $role) {
            Role::findOrCreate($role,'web');
            \App\Support\NavVisibility::setVisibleForRole('admin', [\App\Filament\Resources\PalletResource::class, \App\Filament\Resources\StreamerLogResource::class]);
            $user=User::factory()->create(); $user->assignRole($role); $this->actingAs($user);
            $this->assertTrue(PalletReceivingHistory::canAccess());
            $this->assertTrue(\App\Filament\Pages\InventoryGuide::canAccess());
            $labels=array_column(DashboardShortcuts::forCurrentUser(),'label');
            $this->assertContains('Receive inventory',$labels);
            $this->assertContains('Find inventory',$labels);
            $this->assertContains('Review show reports',$labels);
        }
    }
}
