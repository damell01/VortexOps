<?php

namespace Tests\Feature\Reliability;

use App\Filament\Resources\ShowResource;
use App\Filament\Resources\ShowResource\Pages\ReviewShowStreamers;
use App\Models\Show;
use App\Models\ShowChangeLog;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use App\Services\ShowStreamerSuggester;
use App\Support\ChannelContext;
use App\Support\NavVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StreamReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableAdminModules();
        ChannelContext::setActive(null);
        $this->role('admin');
    }

    protected function tearDown(): void
    {
        ChannelContext::setActive(null);
        NavVisibility::flushMemo();
        parent::tearDown();
    }

    private function role(string $role): User
    {
        Role::findOrCreate($role,'web');
        $user=User::factory()->create(); $user->assignRole($role);
        $this->actingAs($user); NavVisibility::flushMemo();
        return $user;
    }

    private function show(array $data=[]): Show
    {
        return Show::withoutEvents(fn()=>Show::create(array_merge([
            'title'=>'Review fixture', 'show_date'=>today(), 'status'=>'draft',
        ],$data)));
    }

    private function streamer(string $name,array $data=[]): Streamer
    {
        return Streamer::create(array_merge(['name'=>$name,'member_type'=>'streamer','status'=>'active'],$data));
    }

    public function test_queue_contains_all_unassigned_history_across_channels_without_month_or_100_row_limit(): void
    {
        $a=WhatnotChannel::create(['name'=>'Cards','status'=>'active']);
        $b=WhatnotChannel::create(['name'=>'Breaks','status'=>'active']);
        $old=$this->show(['show_date'=>'2025-01-01','status'=>'closed','whatnot_channel_id'=>$b->id]);
        $old->forceFill(['is_operational'=>false])->saveQuietly();
        $cancelled=$this->show(['status'=>'cancelled']);
        $assigned=$this->show(); $assigned->streamers()->attach($this->streamer('Assigned host')->id);
        for ($i=0;$i<110;$i++) $this->show(['title'=>'Archive '.$i]);
        ChannelContext::setActive($a->id);
        Livewire::test(ReviewShowStreamers::class)->assertCountTableRecords(112)
            ->sortTable('show_date','asc')->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$assigned]);
        $this->assertNull($old->fresh()->ai_streamer_suggestion);
        $this->assertSame(0,$old->streamers()->count());
        $this->get(ShowResource::getUrl('stream-review'))->assertOk();
    }

    public function test_search_channel_and_date_filters_narrow_the_queue(): void
    {
        $a=WhatnotChannel::create(['name'=>'Cards','status'=>'active']);
        $b=WhatnotChannel::create(['name'=>'Breaks','status'=>'active']);
        $june=$this->show(['title'=>'June target','show_date'=>'2026-06-04','whatnot_channel_id'=>$a->id]);
        $july=$this->show(['title'=>'July target','show_date'=>'2026-07-04','whatnot_channel_id'=>$b->id]);
        Livewire::test(ReviewShowStreamers::class)->searchTable('June')
            ->assertCanSeeTableRecords([$june])->assertCanNotSeeTableRecords([$july]);
        Livewire::test(ReviewShowStreamers::class)->filterTable('whatnot_channel_id',$b->id)
            ->assertCanSeeTableRecords([$july])->assertCanNotSeeTableRecords([$june]);
        Livewire::test(ReviewShowStreamers::class)->filterTable('dates',['from'=>'2026-06-01','until'=>'2026-06-30'])
            ->assertCanSeeTableRecords([$june])->assertCanNotSeeTableRecords([$july]);
    }

    public function test_name_alias_and_short_name_suggestions_are_read_only_and_unambiguous(): void
    {
        $niko=$this->streamer('Nicholas'); $niko->aliases()->create(['alias'=>'Niko','source'=>'manual']);
        $ty=$this->streamer('Ty'); $tyler=$this->streamer('Tyler');
        $alias=$this->show(['title'=>'Boxes w/Niko']);
        $long=$this->show(['title'=>'Singles with Tyler']);
        $service=app(ShowStreamerSuggester::class);
        $this->assertSame($niko->id,$service->suggest($alias)['suggestion']['id']);
        $this->assertSame('high',$service->suggest($alias)['suggestion']['confidence']);
        $this->assertSame([$tyler->id],array_column($service->suggest($long)['candidates'],'id'));
        Livewire::test(ReviewShowStreamers::class)->assertSee('Suggested: Nicholas')->assertSee('Suggested: Tyler');
        $this->assertSame(0,$alias->streamers()->count());
        $this->assertNull($alias->fresh()->ai_streamer_suggestion);
        $this->assertSame(0,ShowChangeLog::count());
    }

    public function test_multiple_or_duplicate_matches_stay_manual_and_fulfillment_profiles_are_not_suggested(): void
    {
        $this->streamer('Ty'); $this->streamer('Luna');
        $a=$this->streamer('Nicholas'); $b=$this->streamer('Other Niko');
        foreach ([$a,$b] as $profile) $profile->aliases()->create(['alias'=>'Niko','source'=>'manual']);
        $this->streamer('Packing Person',['member_type'=>'fulfillment']);
        $service=app(ShowStreamerSuggester::class);
        foreach (['Boxes w/Ty & Luna','Boxes w/Niko','Boxes with Packing Person','Friday sale'] as $title) {
            $show=$this->show(['title'=>$title]);
            $this->assertNull($service->suggest($show)['suggestion']);
            $this->assertSame(0,$show->streamers()->count());
        }
        Livewire::test(ReviewShowStreamers::class)->assertSee('Multiple possible hosts')->assertSee('Choose a streamer manually');
    }

    public function test_manual_assignment_supports_multiple_hosts_and_inactive_historical_profiles(): void
    {
        $a=$this->streamer('Historical host',['status'=>'inactive']);
        $b=$this->streamer('Co-host');
        $show=$this->show(['show_date'=>'2025-01-01','status'=>'closed','gross_revenue'=>123.45]);
        Livewire::test(ReviewShowStreamers::class)
            ->callTableAction('assign_streamers',$show,['streamer_ids'=>[$a->id,$b->id]])
            ->assertHasNoTableActionErrors()->assertCanNotSeeTableRecords([$show]);
        $this->assertTrue((bool)$show->streamers()->find($a->id)->pivot->is_primary);
        $this->assertFalse((bool)$show->streamers()->find($b->id)->pivot->is_primary);
        $this->assertSame('closed',$show->fresh()->status);
        $this->assertEquals(123.45,$show->fresh()->gross_revenue);
        $this->assertDatabaseHas('show_change_logs',['show_id'=>$show->id,'field_name'=>'streamers','source'=>'stream-review']);
    }

    public function test_suggestion_action_assigns_the_existing_alias_profile(): void
    {
        $niko=$this->streamer('Nicholas'); $niko->aliases()->create(['alias'=>'Niko','source'=>'manual']);
        $show=$this->show(['title'=>'Boxes w/Niko']);
        Livewire::test(ReviewShowStreamers::class)->callTableAction('accept_suggestion',$show)
            ->assertHasNoTableActionErrors()->assertCanNotSeeTableRecords([$show]);
        $this->assertSame([$niko->id],$show->streamers()->pluck('streamers.id')->all());
        $this->assertSame(1,Streamer::count());
    }

    public function test_bulk_assignment_changes_only_the_selected_unassigned_shows(): void
    {
        $host=$this->streamer('Host');
        $a=$this->show(); $b=$this->show(); $other=$this->show();
        Livewire::test(ReviewShowStreamers::class)
            ->callTableBulkAction('assign_streamers',[$a->id,$b->id],['streamer_ids'=>[$host->id]])
            ->assertHasNoTableBulkActionErrors()->assertCanNotSeeTableRecords([$a,$b]);
        foreach ([$a,$b] as $show) $this->assertSame([$host->id],$show->streamers()->pluck('streamers.id')->all());
        $this->assertSame(0,$other->streamers()->count());
    }

    public function test_stale_or_replayed_assignments_never_overwrite_an_existing_host(): void
    {
        $first=$this->streamer('First'); $second=$this->streamer('Second');
        $show=$this->show();
        $page=app(ReviewShowStreamers::class);
        $this->assertSame(1,$page->assignUnassigned([$show->id],[$first->id])['assigned']);
        $this->assertSame(1,$page->assignUnassigned([$show->id],[$second->id])['skipped']);
        $this->assertSame([$first->id],$show->streamers()->pluck('streamers.id')->all());
        $this->assertSame(1,ShowChangeLog::where('show_id',$show->id)->count());
    }

    public function test_invalid_or_fulfillment_profile_is_rejected_without_partial_assignment(): void
    {
        $packer=$this->streamer('Packer',['member_type'=>'fulfillment']);
        $show=$this->show();
        Livewire::test(ReviewShowStreamers::class)
            ->callTableAction('assign_streamers',$show,['streamer_ids'=>[$packer->id]])->assertHasTableActionErrors();
        $this->assertSame(0,$show->streamers()->count());
    }

    public function test_admin_and_fulfillment_admin_get_desktop_and_mobile_entry_and_streamers_do_not(): void
    {
        foreach (['admin','fulfillment_admin'] as $role) {
            $this->role($role);
            $this->assertTrue(ReviewShowStreamers::canAccess());
            $menu=view('filament.components.desktop-mega-nav')->render();
            $this->assertStringContainsString(ShowResource::getUrl('stream-review'),$menu);
            $this->assertGreaterThanOrEqual(2,substr_count($menu,'Stream Review'));
        }
        $this->role('streamer');
        $this->assertFalse(ReviewShowStreamers::canAccess());
        $this->get(ShowResource::getUrl('stream-review'))->assertForbidden();
    }

    public function test_readonly_admin_cannot_assign_and_empty_queue_is_clear(): void
    {
        Livewire::test(ReviewShowStreamers::class)->assertSee('No streams need review');
        \App\Models\Setting::set('role_readonly_nav',json_encode(['admin'=>[ShowResource::class]]));
        NavVisibility::flushMemo();
        $host=$this->streamer('Host'); $show=$this->show();
        $this->assertFalse(ReviewShowStreamers::canAssign());
        Livewire::test(ReviewShowStreamers::class)->call('assignUnassigned',[$show->id],[$host->id])->assertForbidden();
        $this->assertSame(0,$show->streamers()->count());
    }
}
