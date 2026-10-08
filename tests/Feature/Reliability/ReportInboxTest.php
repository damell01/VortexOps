<?php
namespace Tests\Feature\Reliability;

use App\Filament\Resources\StreamerLogResource;
use App\Filament\Resources\StreamerLogResource\Pages\EditStreamerLogEntry;
use App\Filament\Resources\StreamerLogResource\Pages\ListStreamerLogEntries;
use App\Models\{Setting, Show, Streamer, StreamerLogEntry, StreamerLogItem, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ReportInboxTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->enableAdminModules();
        $user = User::firstWhere('email', config('app.owner_email')) ?? User::factory()->create(['email' => config('app.owner_email')]);
        $this->actingAs($user);
        return $user;
    }

    public function test_counts_and_clickable_filters_use_show_reporting_start_date(): void
    {
        $this->owner();
        Setting::set('show_reports_required_from', '2026-10-01');
        $streamer = Streamer::create(['name'=>'Inbox reader','status'=>'active','payout_type'=>'flat_rate']);
        $old = Show::create(['title'=>'Legacy show','show_date'=>'2026-09-30']);
        $current = Show::create(['title'=>'Current show','show_date'=>'2026-10-01']);
        StreamerLogEntry::create(['show_id'=>$old->id,'streamer_id'=>$streamer->id,'status'=>'streamer_reviewed','submitted_at'=>now(),'revision_requested_at'=>now()]);
        $report = StreamerLogEntry::create(['show_id'=>$current->id,'streamer_id'=>$streamer->id,'status'=>'admin_approved','approval_status'=>'approved','locked_at'=>now(),'submitted_at'=>now(),'revision_requested_at'=>now(),'revision_reason'=>'Correct giveaway quantity']);
        $line = StreamerLogItem::create(['streamer_log_entry_id'=>$report->id,'item_name'=>'Posted item','quantity'=>2,'deducted_quantity'=>2,'disposition'=>'sold','unit_cost'=>5]);
        $this->assertSame([$report->id], StreamerLogResource::getEloquentQuery()->pluck('id')->all());
        $page = new ListStreamerLogEntries;
        $stats = $page->getStats();
        $this->assertSame(1, $stats['submissions']['count']);
        $this->assertSame(1, $stats['edit_requested']['count']);
        Livewire::test(ListStreamerLogEntries::class)->assertOk()->assertSee('Requests to reopen')->assertSee('Correct giveaway quantity')
            ->call('selectInboxStatus','edit_requested')->assertSet('activeTab','edit_requested')->assertOk();
        Notification::fake();
        Livewire::test(ListStreamerLogEntries::class)->call('reopenRequestedReport',$report->id)->assertOk();
        $this->assertNull($report->fresh()->revision_requested_at);
        $this->assertSame('pending_approval', $report->fresh()->approval_status);
        $this->assertNotNull($report->fresh()->submitted_at);
        $this->assertSame('changes_requested', $report->fresh()->status);
        $this->assertTrue($report->fresh()->canStreamerEdit());
        $this->assertSame(2, $line->fresh()->deducted_quantity);
    }

    public function test_report_detail_shows_reported_items_and_orphaned_reports_are_not_blank(): void
    {
        $this->owner();
        $streamer = Streamer::create(['name'=>'Report reader','status'=>'active','payout_type'=>'flat_rate']);
        $show = Show::create(['title'=>'Logged item show','show_date'=>now()->toDateString()]);
        $report = StreamerLogEntry::create(['show_id'=>$show->id,'streamer_id'=>$streamer->id,'status'=>'pending','notes'=>'Check special packaging']);
        StreamerLogItem::create(['streamer_log_entry_id'=>$report->id,'item_name'=>'Reported giveaway','quantity'=>2,'disposition'=>'giveaway','unit_cost'=>5]);
        Livewire::test(EditStreamerLogEntry::class,['record'=>$report->id])->assertOk()->assertSee('Reported giveaway')->assertSee('Check special packaging')->assertSee('Items reported by the streamer');
        \Illuminate\Support\Facades\DB::statement('PRAGMA defer_foreign_keys = ON');
        $orphan = StreamerLogEntry::create(['show_id'=>999999,'streamer_id'=>$streamer->id,'status'=>'pending','notes'=>'Legacy saved notes']);
        Livewire::test(EditStreamerLogEntry::class,['record'=>$orphan->id])->assertOk()->assertSee('This legacy report is not linked to a show')->assertSee('Legacy saved notes');
    }

    public function test_non_admin_cannot_reopen_a_report(): void
    {
        $this->actingAs(User::factory()->create());
        $page = new ListStreamerLogEntries;
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $page->reopenRequestedReport(999);
    }
}
