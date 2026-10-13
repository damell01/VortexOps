<?php

namespace App\Filament\Pages;

use App\Models\WhatnotChannel;
use App\Services\WhatnotSheetReconciler;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Upload each channel's Whatnot Shows export and line the app up with it:
 * shows in the wrong channel are moved, missing ones created, figures filled
 * in, and the totals compared. See WhatnotSheetReconciler.
 */
class WhatnotSheetCheck extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Whatnot Sheet Check';
    protected static ?string $navigationLabel = 'Whatnot Sheet Check';
    protected static ?string $slug = 'whatnot-sheet-check';

    /** channel id => list of ['path' => storage path, 'hash' => sha256, 'name' => original name] */
    #[Locked]
    public array $files = [];

    #[Locked]
    public string $since = '2026-06-01';

    #[Locked]
    public ?string $until = null;

    #[Locked]
    public ?string $planKey = null;

    #[Locked]
    public ?array $result = null;

    public string $filter = 'changes';

    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Shows'; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-table-cells'; }
    public static function canAccess(): bool { return (bool) auth()->user()?->isOwner(); }
    public function getView(): string { return 'filament.pages.whatnot-sheet-check'; }

    public function getSubheading(): ?string
    {
        return 'Download each channel\'s Shows list from Whatnot Seller Hub (Analytics → Shows → Download) and upload it here. Nothing changes until you apply.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label(fn () => $this->files ? 'Add or replace exports' : 'Upload exports')
                ->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('Upload Whatnot Shows exports')
                ->modalDescription('One box per channel. Upload every page of each channel\'s export (Show 100 per page, so 176 shows is two files). Upload all channels together so a show can be moved to the right one.')
                ->modalSubmitActionLabel('Check')
                ->schema(fn () => [
                    \Filament\Schemas\Components\Grid::make(2)->schema([
                        DatePicker::make('since')->label('Exports start')->default($this->since)->required(),
                        DatePicker::make('until')->label('Exports end')->default($this->until)->required()
                            ->helperText('The date range you chose in Seller Hub. Every channel is checked across all of it.'),
                    ]),
                    Section::make('Exports by channel')->schema(
                        WhatnotChannel::where('status', 'active')->orderBy('name')->get()->map(fn (WhatnotChannel $c) => FileUpload::make('files_'.$c->id)
                            ->label($c->name)
                            ->disk('local')->directory('imports/whatnot-sheet-check')->visibility('private')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->multiple()->maxFiles(10)->maxSize(10240)
                            ->storeFileNamesIn('names_'.$c->id)
                            ->helperText(isset($this->files[$c->id]) ? count($this->files[$c->id]).' file(s) already uploaded — new files replace them.' : null)
                        )->all()
                    ),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);
                    foreach (WhatnotChannel::pluck('id') as $id) {
                        if (! empty($data['files_'.$id])) {
                            $this->files[$id] = collect($data['files_'.$id])->map(fn ($path) => [
                                'path' => $path,
                                'hash' => hash_file('sha256', Storage::disk('local')->path($path)),
                                'name' => $data['names_'.$id][$path] ?? basename($path),
                            ])->values()->all();
                        }
                    }
                    $this->since = \Carbon\Carbon::parse($data['since'])->toDateString();
                    $this->until = filled($data['until'] ?? null) ? \Carbon\Carbon::parse($data['until'])->toDateString() : null;
                    $this->result = null;
                    $this->check();
                }),

            Action::make('clear')
                ->label('Start over')->color('gray')->icon('heroicon-o-x-mark')
                ->visible(fn () => $this->files !== [])
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->files = [];
                    $this->planKey = null;
                    $this->result = null;
                }),
        ];
    }

    public function applyAction(): Action
    {
        return Action::make('apply')
            ->label('Apply changes')->icon('heroicon-o-check-circle')->color('primary')
            ->visible(fn () => $this->plan() !== null && $this->result === null && collect($this->plan()['items'])->whereIn('action', ['move', 'create', 'update', 'extra'])->isNotEmpty())
            ->requiresConfirmation()
            ->modalHeading('Apply these changes?')
            ->schema(fn () => [
                \Filament\Forms\Components\Toggle::make('delete_extras')
                    ->label('Also delete the '.$this->deletableCount().' show(s) no Whatnot export lists')
                    ->helperText(($this->plan()['all_channels'] ?? false)
                        ? 'Shows with finalized or paid pay, or an approved report, are kept and listed for you to check.'
                        : 'Upload every channel\'s export to allow this — missing: '.implode(', ', $this->plan()['missing_channels'] ?? []).'.')
                    ->default(($this->plan()['all_channels'] ?? false) && $this->deletableCount() > 0)
                    ->disabled(! ($this->plan()['all_channels'] ?? false) || $this->deletableCount() === 0),
                \Filament\Forms\Components\Toggle::make('include_paid')
                    ->label('Also delete the '.$this->blockedCount().' with pay or an approved report')
                    ->helperText('Only while payroll is not live: their pay lines are removed with them.')
                    ->default(false)
                    ->visible(fn () => ($this->plan()['all_channels'] ?? false) && $this->blockedCount() > 0),
            ])
            ->modalDescription(function () {
                $items = collect($this->plan()['items'] ?? []);

                return 'Move '.$items->where('action', 'move')->count().' show(s) to the channel Whatnot lists them under, create '
                    .$items->where('action', 'create')->count().' missing show(s), and update figures on '
                    .$items->where('action', 'update')->count().'. Shows the app has that Whatnot does not list are left alone for you to review. Every change is recorded on the show.';
            })
            ->action(function (array $data): void {
                abort_unless(static::canAccess(), 403);
                // Re-check against the files as they are now, not a stale preview.
                $plan = $this->check(notify: false);
                if (! $plan) {
                    return;
                }
                $this->result = app(WhatnotSheetReconciler::class)->apply($plan, (bool) ($data['delete_extras'] ?? false), (bool) ($data['include_paid'] ?? false));
                $this->check(notify: false);
                $failed = count($this->result['failed']);
                Notification::make()
                    ->title('Sheet changes applied')
                    ->body("{$this->result['moved']} moved · {$this->result['created']} created · {$this->result['updated']} updated · {$this->result['deleted']} deleted".($failed ? " · {$failed} failed" : ''))
                    ->{$failed ? 'warning' : 'success'}()
                    ->send();
            });
    }

    /** Build (and cache) the plan from the uploaded files. */
    public function check(bool $notify = true): ?array
    {
        try {
            $paths = [];
            foreach ($this->files as $channelId => $files) {
                foreach ($files as $file) {
                    $path = Storage::disk('local')->path($file['path']);
                    if (! is_file($path) || ! hash_equals($file['hash'], hash_file('sha256', $path))) {
                        throw new \RuntimeException("{$file['name']} has expired or changed. Upload it again.");
                    }
                    $paths[$channelId][] = $path;
                }
            }

            $plan = app(WhatnotSheetReconciler::class)->plan($paths, $this->since, $this->until);
            $this->planKey = 'whatnot-sheet-check:'.auth()->id().':'.Str::uuid();
            Cache::put($this->planKey, $plan, now()->addHours(2));

            if ($notify) {
                Notification::make()->title('Check ready')->body('Review the results below, then Apply changes.')->success()->send();
            }

            return $plan;
        } catch (\Throwable $e) {
            $this->planKey = null;
            Notification::make()->title('Could not check the exports')->body($e->getMessage())->danger()->send();

            return null;
        }
    }

    public function deletableCount(): int
    {
        return collect($this->plan()['items'] ?? [])->where('action', 'extra')->filter(fn ($it) => empty($it['blocked']) && empty($it['aired']) && ($it['app_orders'] ?? 0) === 0)->count();
    }

    public function blockedCount(): int
    {
        return collect($this->plan()['items'] ?? [])->where('action', 'extra')->filter(fn ($it) => ! empty($it['blocked']) && empty($it['aired']) && ($it['app_orders'] ?? 0) === 0)->count();
    }

    public function plan(): ?array
    {
        return $this->planKey ? Cache::get($this->planKey) : null;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    /** @return list<array<string, mixed>> */
    public function visibleItems(): array
    {
        $items = collect($this->plan()['items'] ?? []);

        return match ($this->filter) {
            'changes' => $items->whereIn('action', ['move', 'create', 'update', 'extra'])->values()->all(),
            'all' => $items->all(),
            'orders_short' => $items->filter(fn ($it) => $it['sheet_orders'] !== null && $it['app_orders'] < $it['sheet_orders'])->values()->all(),
            default => $items->where('action', $this->filter)->values()->all(),
        };
    }
}
