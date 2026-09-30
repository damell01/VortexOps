<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Concerns\RespectsRoleVisibility;
use App\Support\FulfillmentManual;
use App\Support\HandbookVisibility;
use App\Support\InventoryManual;
use App\Support\PayrollManual;
use App\Support\ShowsManual;
use Filament\Pages\Page;

class Handbook extends Page
{
    use HasAdminNavVisibility;
    use RespectsRoleVisibility;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Handbook';
    protected static ?int $navigationSort = -10;

    public const TROUBLESHOOTING = -1;
    public const SCREEN_INDEX = -2;

    public string $module = 'inventory';
    public ?int $section = null;
    public string $search = '';

    public function getTitle(): string { return 'Handbook'; }

    public function getSubheading(): ?string
    {
        return 'How VortexOps works, module by module — aligned to the current operational workflow.';
    }

    public static function getNavigationGroup(): ?string { return null; }
    public function getView(): string { return 'filament.pages.handbook'; }

    protected function queryString(): array
    {
        return ['module' => ['except' => 'inventory'], 'section' => ['except' => null]];
    }

    /** @return array<string, array{label:string,icon:string,source:class-string,ready:bool}> */
    public function modules(): array
    {
        return [
            'inventory' => ['label'=>'Inventory','icon'=>'heroicon-o-cube','source'=>InventoryManual::class,'ready'=>true],
            'shows' => ['label'=>'Shows & Streams','icon'=>'heroicon-o-video-camera','source'=>ShowsManual::class,'ready'=>true],
            'fulfillment' => ['label'=>'Fulfillment','icon'=>'heroicon-o-truck','source'=>FulfillmentManual::class,'ready'=>true],
            'payouts' => ['label'=>'Payroll & Pay Runs','icon'=>'heroicon-o-banknotes','source'=>PayrollManual::class,'ready'=>true],
        ];
    }

    public function selectModule(string $module): void
    {
        if (($this->modules()[$module]['ready'] ?? false) === true) {
            $this->module=$module; $this->section=null; $this->search='';
        }
    }

    public function openSection(?int $index): void { $this->section=$index; $this->search=''; }

    public function allSections(): array
    {
        $source=$this->modules()[$this->module]['source'] ?? null;
        return $source ? HandbookVisibility::sections($source::sections()) : [];
    }

    public function visibleSections(): array
    {
        $sections=$this->allSections(); $needle=trim(mb_strtolower($this->search));
        if ($needle !== '') {
            $matched=[];
            foreach ($sections as $i=>$section) {
                $steps=array_values(array_filter($section['steps'],fn(array $step)=>str_contains(mb_strtolower($this->haystackFor($step)),$needle)));
                if ($steps!==[]) $matched[]=['index'=>$i,'section'=>['title'=>$section['title'],'blurb'=>$section['blurb'],'steps'=>$steps]];
            }
            return $matched;
        }
        if ($this->section!==null && isset($sections[$this->section])) return [['index'=>$this->section,'section'=>$sections[$this->section]]];
        return [];
    }

    private function haystackFor(array $step): string
    {
        $parts=[$step['title'],$step['where']??'',$step['note']??'']; $parts=array_merge($parts,$step['body']);
        foreach ($step['fields']??[] as [$field,$meaning]) $parts[]=$field.' '.$meaning;
        return implode(' ',$parts);
    }

    public function matchedTroubleshooting(): array { return $this->matchingRows($this->troubleshooting()); }
    public function matchedScreens(): array { return $this->matchingRows($this->screenIndex()); }

    private function matchingRows(array $rows): array
    {
        $needle=trim(mb_strtolower($this->search));
        if ($needle==='') return [];
        return array_values(array_filter($rows,fn(array $row)=>str_contains(mb_strtolower($row[0].' '.$row[1]),$needle)));
    }

    public function getSearchStepCountProperty(): int
    {
        return array_sum(array_map(fn($entry)=>count($entry['section']['steps']),$this->visibleSections()));
    }

    public function getSearchResultCountProperty(): int
    {
        return $this->searchStepCount + count($this->matchedTroubleshooting()) + count($this->matchedScreens());
    }

    public function getTotalStepsProperty(): int
    {
        return array_sum(array_map(fn($s)=>count($s['steps']),$this->allSections()));
    }

    public function troubleshooting(): array
    {
        $source=$this->modules()[$this->module]['source'] ?? null;
        return $source ? HandbookVisibility::troubleshooting($source::troubleshooting()) : [];
    }

    public function screenIndex(): array
    {
        $source=$this->modules()[$this->module]['source'] ?? null;
        return $source ? HandbookVisibility::screens($source::screenIndex()) : [];
    }

    public function imageUrl(string $shot): string
    {
        return asset(InventoryManual::IMAGE_DIR.'/'.$shot);
    }

    public function onExtraPage(): bool
    {
        return in_array($this->section,[self::TROUBLESHOOTING,self::SCREEN_INDEX],true);
    }

    public function neighbour(int $direction): ?array
    {
        if ($this->section===null || $this->search!=='') return null;
        $sections=$this->allSections(); $next=$this->section+$direction;
        if (isset($sections[$next])) return [$next,$sections[$next]['title']];
        if ($direction>0 && $this->section===count($sections)-1) return [self::TROUBLESHOOTING,'When something looks wrong'];
        if ($direction>0 && $this->section===self::TROUBLESHOOTING) return [self::SCREEN_INDEX,'Every screen, and what it is for'];
        if ($direction<0 && $this->section===self::TROUBLESHOOTING) return [count($sections)-1,$sections[count($sections)-1]['title']];
        if ($direction<0 && $this->section===self::SCREEN_INDEX) return [self::TROUBLESHOOTING,'When something looks wrong'];
        return null;
    }
}
