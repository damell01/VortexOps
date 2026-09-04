<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShipmentResource;
use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Models\Streamer;
use App\Support\AdminModules;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class Shows extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;
    protected static string $moduleSlug='streams'; protected static ?string $title='Shows';

    public string $filterStatus='all', $filterTimeframe='all', $filterStreamer='', $searchQuery='', $sortBy='date';
    #[Url(as:'range')] public string $datePreset='this_month';
    #[Url(as:'from')] public string $dateFrom='';
    #[Url(as:'to')] public string $dateTo='';
    #[Url(as:'view')] public string $viewMode='list';

    public function mount(): void { if($this->dateFrom===''||$this->dateTo==='') $this->applyDatePreset($this->datePreset); }
    public function updatedDatePreset(string $value): void { if($value!=='custom') $this->applyDatePreset($value); }
    public function applyDatePreset(string $preset): void { $t=today(); [$f,$to]=match($preset){'this_week'=>[$t->copy()->startOfWeek(),$t->copy()->endOfWeek()],'last_week'=>[$t->copy()->subWeek()->startOfWeek(),$t->copy()->subWeek()->endOfWeek()],'last_month'=>[$t->copy()->subMonthNoOverflow()->startOfMonth(),$t->copy()->subMonthNoOverflow()->endOfMonth()],'last_30'=>[$t->copy()->subDays(29),$t],default=>[$t->copy()->startOfMonth(),$t->copy()->endOfMonth()]}; $this->datePreset=$preset;$this->dateFrom=$f->toDateString();$this->dateTo=$to->toDateString();unset($this->shows); }
    public function dateRange(): array { try{$f=Carbon::parse($this->dateFrom)->startOfDay();}catch(\Throwable){$f=today()->startOfMonth();} try{$t=Carbon::parse($this->dateTo)->startOfDay();}catch(\Throwable){$t=today()->endOfMonth();} if($f->gt($t))[$f,$t]=[$t,$f]; return[$f,$t]; }
    public function dateRangeLabel(): string { [$f,$t]=$this->dateRange();return $f->isSameYear($t)?$f->format('M j').' – '.$t->format('M j, Y'):$f->format('M j, Y').' – '.$t->format('M j, Y'); }
    public function previousPeriod():void{[$f,$t]=$this->dateRange();$d=$f->diffInDays($t)+1;$this->datePreset='custom';$this->dateFrom=$f->copy()->subDays($d)->toDateString();$this->dateTo=$t->copy()->subDays($d)->toDateString();unset($this->shows);}
    public function nextPeriod():void{[$f,$t]=$this->dateRange();$d=$f->diffInDays($t)+1;$this->datePreset='custom';$this->dateFrom=$f->copy()->addDays($d)->toDateString();$this->dateTo=$t->copy()->addDays($d)->toDateString();unset($this->shows);}

    public function getSubheading():?string{return 'Whatnot shows, streamer assignments, analytics, shipments, and end-of-stream workflow in one place.';}
    public function getView():string{return 'filament.pages.shows';}
    public static function getNavigationIcon():string{return 'heroicon-o-presentation-chart-line';}
    public static function getNavigationGroup():?string{return AdminModules::navigationGroupFor('streams');}
    public static function getNavigationSort():?int{return 20;}
    public static function getNavigationLabel():string{return 'Shows';}
    public static function getSlug(?Panel $panel=null):string{return 'shows-overview';}
    protected function getHeaderActions():array{return[Action::make('show_shipments')->label('Show Shipments')->icon('heroicon-o-truck')->color('gray')->url(fn()=>ShowShipments::getUrl()),Action::make('create_show')->label('Add Show Manually')->icon('heroicon-o-plus')->color('success')->visible(fn()=>auth()->user()?->isAdmin()??false)->url(fn()=>ShowResource::getUrl('create'))];}
    public static function canAccess():bool{if(\App\Support\RoleAccess::grants(static::class))return true;$u=auth()->user();return AdminModules::isEnabled('streams')&&($u?->isAdmin()||$u?->isStreamer());}

    #[Computed] public function streamers():Collection{$u=auth()->user();if($u?->isAdmin())return Streamer::orderBy('name')->get();return $u?->streamer?collect([$u->streamer]):collect();}

    #[Computed] public function shows():Collection
    {
        $u=auth()->user();$q=Show::query()->inChannelContext();[$from,$to]=$this->dateRange();$q->whereBetween('show_date',[$from->toDateString(),$to->toDateString()]);
        if($this->filterStatus!=='all')$q->where('status',$this->filterStatus);
        match($this->filterTimeframe){'upcoming'=>$q->whereDate('show_date','>',today()),'past'=>$q->whereDate('show_date','<=',today()),'attention'=>$q->whereDate('show_date','<=',today())->whereDoesntHave('streamerLogEntry')->whereNotIn('status',['closed','cancelled']),default=>null};
        if($this->filterStreamer&&$u?->isAdmin())$q->whereHas('streamers',fn($x)=>$x->where('streamers.id',$this->filterStreamer));elseif($u?->isStreamer())$q->whereHas('streamers',fn($x)=>$x->where('streamers.id',$u->streamer->id));
        if($this->searchQuery){$n=trim($this->searchQuery);$q->where(fn($x)=>$x->where('title','like',"%{$n}%")->orWhere('notes','like',"%{$n}%")->orWhere('whatnot_show_id','like',"%{$n}%"));}
        if($this->sortBy==='revenue')$q->orderByDesc('gross_revenue')->orderByDesc('show_date');elseif($this->sortBy==='oldest')$q->orderBy('show_date')->orderBy('start_time');else$q->orderByDesc('show_date')->orderByDesc('start_time');
        return $q->with(['streamers','streamerLogEntry'])
            ->withCount([
                'shipments',
                'shipments as delivered_shipments_count'=>fn($x)=>$x->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'"),
                'shipments as pending_shipments_count'=>fn($x)=>$x->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
            ])
            ->limit(500)->get();
    }

    #[Computed] public function calendarWeeks():Collection
    {
        [$from,$to]=$this->dateRange();$start=$from->copy()->startOfWeek();$end=$to->copy()->endOfWeek();$byDate=$this->shows->groupBy(fn($s)=>$s->show_date?->toDateString());$weeks=collect();
        for($cursor=$start->copy();$cursor->lte($end);$cursor->addWeek()){$days=collect();for($i=0;$i<7;$i++){$day=$cursor->copy()->addDays($i);$days->push(['date'=>$day,'in_range'=>$day->betweenIncluded($from,$to),'shows'=>$byDate->get($day->toDateString(),collect())]);}$weeks->push($days);}return $weeks;
    }

    public function clearFilters():void{$this->filterStatus='all';$this->filterTimeframe='all';$this->filterStreamer='';$this->searchQuery='';$this->sortBy='date';$this->applyDatePreset('this_month');}
    public function showUrl(int $id):string{return ShowResource::getUrl('view',['record'=>$id]);} public function editUrl(int $id):string{return ShowResource::getUrl('edit',['record'=>$id]);} public function shipmentsUrl(int $id):string{return ShipmentResource::getUrl('index',['show'=>$id]);}
    public function isShowDue(Show $s):bool{if($s->show_date?->isFuture())return false;if($s->start_time&&$s->start_time->isFuture())return false;return true;}
    public function requestFormSubmission($id):void{$s=Show::with('streamers.user')->findOrFail((int)$id);if(!$this->isShowDue($s)){Notification::make()->title('Show has not happened yet')->warning()->send();return;}foreach($s->streamers as $st)if($st->user)Notification::make()->title('Form Submission Requested')->body("Admin is requesting you submit the end-of-stream form for \"{$s->title}\"")->info()->sendToDatabase($st->user);Notification::make()->title('Submission request sent')->success()->send();unset($this->shows);}
    public function requestFormResubmission($id):void{$s=Show::findOrFail((int)$id);$l=$s->streamerLogEntry;if(!$l){Notification::make()->title('Error')->body('No log entry found for this show')->danger()->send();return;}$l->sendBackToStreamer('Admin requested changes to your submission.');Notification::make()->title('Change request sent')->success()->send();unset($this->shows);}
    public function deleteShow(int $id):void{abort_unless(auth()->user()?->isAdmin(),403);$s=Show::findOrFail($id);DB::transaction(function()use($s){foreach(['shipments','whatnot_show_orders','show_ingestion_logs','show_change_logs','deduction_requests','payouts','shipping_surcharges','show_reopening_requests','streamer_log_entries']as$t)if(Schema::hasTable($t)&&Schema::hasColumn($t,'show_id'))DB::table($t)->where('show_id',$s->id)->delete();foreach(['show_streamer','show_fulfillment_user']as$t)if(Schema::hasTable($t)&&Schema::hasColumn($t,'show_id'))DB::table($t)->where('show_id',$s->id)->delete();$s->delete();});Notification::make()->title('Show deleted')->success()->send();unset($this->shows);}
}
