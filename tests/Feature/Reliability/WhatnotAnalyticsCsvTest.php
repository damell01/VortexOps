<?php

namespace Tests\Feature\Reliability;

use App\Filament\Pages\ImportStatus;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\WhatnotAnalyticsCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatnotAnalyticsCsvTest extends TestCase
{
    use RefreshDatabase;
    private array $paths = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-09 12:00:00');
    }

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) @unlink($path);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function channel(string $name = 'Cards'): WhatnotChannel
    {
        return WhatnotChannel::create(['name' => $name, 'status' => 'active']);
    }

    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'whatnot-csv-'); $this->paths[] = $path;
        $file = fopen($path, 'wb'); fwrite($file, "\xEF\xBB\xBF");
        fputcsv($file, ['Show','Date','Thumbnail Impressions','Thumbnail CTR','Est. Sales','Est. Earnings','Orders','AOV','Giveaway Spend','Giveaways','Buyers','First Time Buyers','Returning Buyers','Show Shares','Show Duration (mins)','Max Concurrent Viewers','Total Views','Unique Viewers','Average Order Rating'], ',', '"', '');
        foreach ($rows as $row) fputcsv($file, $row, ',', '"', '');
        fclose($file); return $path;
    }

    private function row(string $title = 'CSV show', string $date = '10/08/2026', string $duration = '212'): array
    {
        return [$title,$date,'2,234','0.31%','$7,394','$4,864','187','$40','-$45','20','46','15','31','40',$duration,'194','1,154','629','—'];
    }

    public function test_export_bom_money_and_metrics_preview_then_idempotent_import(): void
    {
        $channel = $this->channel();
        $show = Show::withoutEvents(fn () => Show::create(['title'=>'CSV show','show_date'=>'2026-10-08','status'=>'draft','whatnot_channel_id'=>$channel->id,'units_sold'=>999]));
        $file = $this->csv([$this->row()]);
        $service = app(WhatnotAnalyticsCsvImporter::class);
        $preview = $service->import($file, $channel->id, true);
        $this->assertSame(1, $preview['updated']);
        $this->assertNull($show->fresh()->gross_revenue);
        $this->assertSame(0, ShowIngestionLog::count());
        $service->import($file, $channel->id);
        $fresh = $show->fresh();
        $this->assertSame('7394.00', $fresh->gross_revenue);
        $this->assertSame('4864.00', $fresh->whatnot_net);
        $this->assertSame(999, $fresh->units_sold);
        $this->assertSame(212, $fresh->show_duration);
        $this->assertEquals(187, $fresh->raw_import_payload['_analytics_csv_metrics']['orders_count']);
        $this->assertEquals(0.0031, $fresh->raw_import_payload['_analytics_csv_metrics']['thumbnail_ctr']);
        $this->assertSame('partial', $fresh->analytics_sync_status); // settled earnings are not in this CSV
        $repeat = $service->import($file, $channel->id);
        $this->assertSame(0, $repeat['updated']);
        $this->assertSame(1, $repeat['already_complete']);
        $this->assertSame(1, ShowIngestionLog::count());
    }

    public function test_zero_duration_positive_sales_never_cancels_show_and_blanks_never_erase_metrics(): void
    {
        $channel = $this->channel();
        $service = app(WhatnotAnalyticsCsvImporter::class);
        $service->import($this->csv([$this->row('Zero duration sale','10/05/2026','0')]), $channel->id);
        $show = Show::where('title','Zero duration sale')->sole();
        $this->assertNotSame('cancelled', $show->status);
        $this->assertSame('partial', $show->analytics_sync_status);
        $blank = ['Zero duration sale','10/05/2026',...array_fill(0,17,'—')];
        $stats = $service->import($this->csv([$blank]), $channel->id);
        $this->assertSame(1, $stats['blank_metrics']);
        $this->assertSame('7394.00', $show->fresh()->gross_revenue);
    }

    public function test_channel_conflicts_and_duplicate_title_dates_require_review(): void
    {
        $a = $this->channel('A'); $b = $this->channel('B');
        Show::withoutEvents(fn () => Show::create(['title'=>'CSV show','show_date'=>'2026-10-08','status'=>'draft','whatnot_channel_id'=>$a->id]));
        $stats = app(WhatnotAnalyticsCsvImporter::class)->import($this->csv([$this->row()]), $b->id);
        $this->assertSame(1, $stats['ambiguous']);
        $this->assertSame(1, Show::count());
        $this->assertSame(0, ShowIngestionLog::count());
    }

    public function test_new_history_keeps_precise_existing_values_and_enforces_date_range(): void
    {
        $channel = $this->channel(); $service = app(WhatnotAnalyticsCsvImporter::class);
        $file = $this->csv([$this->row('New history','07/05/2026'),$this->row('Too old','06/30/2026'),$this->row('Future','10/10/2026')]);
        $stats = $service->import($file,$channel->id);
        $this->assertSame(1,$stats['created']);
        $this->assertSame(1,$stats['before_start']);
        $this->assertSame(1,$stats['ignored_current_future']);
        $show = Show::where('title','New history')->sole();
        $show->forceFill(['gross_revenue'=>7394.23,'show_duration'=>250])->saveQuietly();
        $service->import($this->csv([$this->row('New history','07/05/2026','0')]),$channel->id);
        $this->assertSame('7394.23',$show->fresh()->gross_revenue);
        $this->assertSame(250,$show->fresh()->show_duration);
        $this->assertSame(1,Show::count());
    }

    public function test_upload_is_available_only_on_owner_import_status(): void
    {
        $owner = User::firstWhere('email',config('app.owner_email')) ?? User::factory()->create(['email'=>config('app.owner_email')]);
        $this->actingAs($owner);
        Livewire::test(ImportStatus::class)->assertSee('Upload analytics CSV');
        Role::findOrCreate('admin','web');
        $admin=User::factory()->create(); $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->assertFalse(ImportStatus::canAccess());
        $this->get(ImportStatus::getUrl())->assertForbidden();
    }
}
