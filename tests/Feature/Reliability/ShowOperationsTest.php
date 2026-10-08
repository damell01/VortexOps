<?php
namespace Tests\Feature\Reliability;

use App\Filament\Pages\Shows;
use App\Jobs\RetryShowAnalytics;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\User;
use App\Models\WhatnotChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShowOperationsTest extends TestCase
{
    use RefreshDatabase;
    private function admin(): User
    {
        Role::findOrCreate('admin','web');
        $user = User::factory()->create(); $user->assignRole('admin');
        $this->actingAs($user); return $user;
    }
    private function show(array $data=[]): Show
    {
        return Show::withoutEvents(fn()=>Show::create(array_merge(['title'=>'Test show','show_date'=>today(),'status'=>'draft'], $data)));
    }
    public function test_bulk_assignment_assigns_only_selected_unassigned_shows(): void
    {
        $this->admin();
        $streamer = Streamer::create(['name'=>'Test streamer','status'=>'active']);
        $a=$this->show(); $b=$this->show(); $other=$this->show();
        Livewire::test(Shows::class)->set('selectedUnassignedShows',[$a->id,$b->id])->set('bulkStreamerId',(string)$streamer->id)->call('assignSelectedShows')->assertHasNoErrors();
        $this->assertSame([$streamer->id], $a->streamers()->pluck('streamers.id')->all());
        $this->assertSame([$streamer->id], $b->streamers()->pluck('streamers.id')->all());
        $this->assertSame(0,$other->streamers()->count());
    }
    public function test_admin_cannot_retry_analytics_or_see_sync_details(): void
    {
        $this->admin(); Queue::fake();
        $show=$this->show(['end_time'=>now()->subHour()]);
        Livewire::test(Shows::class)->assertDontSee('Last checked')->assertDontSee('Retry analytics')->call('retryShowAnalytics',$show->id)->assertForbidden();
        Queue::assertNothingPushed();
    }
    public function test_owner_can_queue_exact_show_retry(): void
    {
        Queue::fake();
        $owner = User::firstWhere('email',config('app.owner_email')) ?? User::factory()->create(['email'=>config('app.owner_email')]);
        $this->actingAs($owner);
        $channel=WhatnotChannel::create(['name'=>'Cards','whatnot_username'=>'cards','include_in_import'=>true,'status'=>'active']);
        $show=$this->show(['whatnot_channel_id'=>$channel->id,'whatnot_show_id'=>'729c7c5c-f4d9-4401-9e06-4c053b72e99d','end_time'=>now()->subHour()]);
        Livewire::test(Shows::class)->assertSee('Last checked')->call('retryShowAnalytics',$show->id)->assertHasNoErrors();
        Queue::assertPushed(RetryShowAnalytics::class,fn($job)=>$job->showId===$show->id);
    }
    public function test_detected_group_creates_profile_alias_and_assigns_only_reviewed_shows(): void
    {
        $this->admin();
        $a = $this->show(['title' => 'Boxes w/Niko🍀']);
        $b = $this->show(['title' => '$1 starts WITH NIKO🔥']);
        $other = $this->show(['title' => 'Boxes w/Luna']);
        Livewire::test(Shows::class)->call('saveDetectedHost', 'niko', ['name' => 'Niko', 'aliases' => "Niko\nNIKO", 'show_ids' => [$a->id, $b->id]])->assertHasNoErrors();
        $profile = Streamer::where('name', 'Niko')->sole();
        $this->assertSame(1, $profile->aliases()->count());
        $this->assertSame([$profile->id], $a->streamers()->pluck('streamers.id')->all());
        $this->assertSame([$profile->id], $b->streamers()->pluck('streamers.id')->all());
        $this->assertSame(0, $other->streamers()->count());
    }

    public function test_existing_alias_blocks_duplicate_profile_creation(): void
    {
        $this->admin();
        $profile = Streamer::create(['name' => 'Nicholas', 'status' => 'active']);
        $profile->aliases()->create(['alias' => 'Niko', 'source' => 'manual']);
        $show = $this->show(['title' => 'Boxes w/Niko']);
        Livewire::test(Shows::class)->call('saveDetectedHost', 'niko', ['name' => 'Niko', 'aliases' => 'Niko', 'show_ids' => [$show->id]])->assertHasErrors(['name']);
        $this->assertSame(1, Streamer::count());
        $this->assertSame(0, $show->streamers()->count());
        Livewire::test(Shows::class)->call('saveDetectedHost', 'niko', ['name' => 'Niko', 'streamer_id' => $profile->id, 'aliases' => 'Niko', 'show_ids' => [$show->id]])->assertHasNoErrors();
        $this->assertSame([$profile->id], $show->streamers()->pluck('streamers.id')->all());
    }

    public function test_detection_keeps_multiple_hosts_and_unmarked_titles_manual(): void
    {
        $detector = app(\App\Services\ShowHostDetection::class);
        $this->assertSame('Lil Twang', $detector->name('Boxes w/Lil Twang🤠'));
        $this->assertNull($detector->name('Boxes w/Ty & Luna'));
        $this->assertNull($detector->name('Boxes with Ty and Luna'));
        $this->assertNull($detector->name('Big Friday sale'));
    }

}
