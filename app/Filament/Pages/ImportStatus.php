<?php

namespace App\Filament\Pages;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use App\Models\WhatnotSync;
use App\Support\ChannelContext;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class ImportStatus extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static ?string $title = 'Import Status';
    protected static ?string $slug = 'import-status';
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Admin'; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-arrow-path'; }
    public static function canAccess(): bool { return (bool) auth()->user()?->isOwner(); }
    public function getView(): string { return 'filament.pages.import-status'; }


    #[\Livewire\Attributes\Locked]
    public ?array $csvPreview = null;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('previewAnalyticsCsv')
                ->label('Upload analytics CSV')->icon('heroicon-o-arrow-up-tray')
                ->modalHeading('Preview Whatnot Shows CSV')
                ->modalSubmitActionLabel('Preview files')
                ->schema([
                    \Filament\Forms\Components\Select::make('channel_id')->label('Channel these exports belong to')
                        ->options(fn () => WhatnotChannel::orderBy('name')->pluck('name', 'id'))->required()->exists('whatnot_channels', 'id')
                        ->helperText('The CSV has no channel column. Upload exports from one channel/account at a time.'),
                    \Filament\Forms\Components\DatePicker::make('since')->label('Import shows from')->default('2026-06-01')->required(),
                    \Filament\Forms\Components\FileUpload::make('files')->label('Whatnot Shows CSV files')
                        ->disk('local')->directory('imports/whatnot-analytics')->visibility('private')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                        ->multiple()->maxFiles(10)->maxSize(10240)->required()
                        ->helperText('Preview does not change records. Files can overlap; repeated imports keep the existing show.'),
                ])
                ->action(function (array $data): void {
                    abort_unless(static::canAccess(), 403);
                    try {
                        $summaries = []; $files = [];
                        $blocked = app(\App\Services\WhatnotAnalyticsCsvImporter::class)->conflicts(array_map(fn ($file) => \Illuminate\Support\Facades\Storage::disk('local')->path($file), $data['files']));
                        foreach ($data['files'] as $file) {
                            $path = \Illuminate\Support\Facades\Storage::disk('local')->path($file);
                            $summaries[] = app(\App\Services\WhatnotAnalyticsCsvImporter::class)->import($path, (int) $data['channel_id'], true, $data['since'], $blocked);
                            $files[] = ['path' => $file, 'hash' => hash_file('sha256', $path)];
                        }
                        $this->csvPreview = [
                            'channel_id' => (int) $data['channel_id'], 'channel_name' => WhatnotChannel::findOrFail($data['channel_id'])->name,
                            'since' => $data['since'], 'blocked' => $blocked, 'files' => $files, 'summaries' => $summaries, 'applied' => false,
                        ];
                        \Filament\Notifications\Notification::make()->title('CSV preview ready')->body('Review the results below, then choose Import reviewed files.')->success()->send();
                    } catch (\Throwable $e) {
                        $this->csvPreview = null;
                        \Filament\Notifications\Notification::make()->title('CSV preview failed')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    public function applyAnalyticsCsvAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('applyAnalyticsCsv')
            ->label('Import reviewed files')->icon('heroicon-o-check-circle')
            ->visible(fn () => $this->csvPreview !== null && ! $this->csvPreview['applied'])
            ->requiresConfirmation()->modalHeading('Import these analytics files?')
            ->modalDescription('Save metrics to uniquely matched shows and create missing historical shows for the selected channel. Ambiguous matches and blank metrics are skipped. Existing payroll, assignments, costs, and shipments are preserved.')
            ->modalSubmitActionLabel('Import files')
            ->action(function (): void {
                abort_unless(static::canAccess(), 403);
                if (! $this->csvPreview || $this->csvPreview['applied']) return;
                $lock = \App\Support\WhatnotPipelineLock::acquire('Whatnot CSV analytics import');
                if (! $lock) {
                    \Filament\Notifications\Notification::make()->title('Whatnot sync is busy')->body('Wait for the current run to finish, then retry this import.')->warning()->send();
                    return;
                }
                $summaries = []; $error = null;
                try {
                    // Check every file before the first write.
                    foreach ($this->csvPreview['files'] as $file) {
                        $path = \Illuminate\Support\Facades\Storage::disk('local')->path($file['path']);
                        if (! is_file($path) || ! hash_equals($file['hash'], hash_file('sha256', $path))) {
                            throw new \RuntimeException('A previewed file changed or expired. Upload and preview it again.');
                        }
                    }
                    foreach ($this->csvPreview['files'] as $file) {
                        $summaries[] = app(\App\Services\WhatnotAnalyticsCsvImporter::class)->import(
                            \Illuminate\Support\Facades\Storage::disk('local')->path($file['path']),
                            $this->csvPreview['channel_id'], false, $this->csvPreview['since'], $this->csvPreview['blocked']
                        );
                    }
                } catch (\Throwable $e) { $error = $e->getMessage(); }
                finally { \App\Support\WhatnotPipelineLock::release($lock); }
                $this->csvPreview['applied'] = true;
                $this->csvPreview['summaries'] = $summaries;
                $this->csvPreview['error'] = $error;
                unset($this->channels);
                $message = \Filament\Notifications\Notification::make()->title($error ? 'Import stopped — review results' : 'CSV analytics imported');
                if ($error) $message->body($error.' Completed files are shown below; re-uploading safely retries the rest.')->danger();
                else $message->body('Results below show what was saved and which rows still need attention.')->success();
                $message->send();
            });
    }

    #[Computed]
    public function channels(): array
    {
        $channels = WhatnotChannel::query()
            ->when(ChannelContext::isScoped(), fn ($q) => $q->whereKey(ChannelContext::currentId()))
            ->orderBy('name')->get();
        $ids = $channels->modelKeys();
        $successIds = ShowIngestionLog::whereIn('whatnot_channel_id', $ids)->where('status', 'success')
            ->selectRaw('MAX(id) as id')->groupBy('whatnot_channel_id')->pluck('id');
        $successes = ShowIngestionLog::whereIn('id', $successIds)->get()->keyBy('whatnot_channel_id');
        $runIds = WhatnotSync::whereIn('whatnot_channel_id', $ids)->where('status', 'completed')->where('error_count', 0)
            ->selectRaw('MAX(id) as id')->groupBy('whatnot_channel_id')->pluck('id');
        $runs = WhatnotSync::whereIn('id', $runIds)->get()->keyBy('whatnot_channel_id');
        $counts = ShowIngestionLog::whereIn('whatnot_channel_id', $ids)->where('created_at', '>=', now()->subDay())
            ->selectRaw("whatnot_channel_id, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as updates, SUM(CASE WHEN status IN ('failed','partial') THEN 1 ELSE 0 END) as failures")
            ->groupBy('whatnot_channel_id')->get()->keyBy('whatnot_channel_id');

        return $channels->map(function ($channel) use ($successes, $runs, $counts) {
            $recent = ShowIngestionLog::with('show:id,title')->where('whatnot_channel_id', $channel->id)
                ->where('created_at', '>=', now()->subDay())->latest('id')->limit(8)->get();
            $missing = Show::where('whatnot_channel_id', $channel->id)->whereDate('show_date', '<', today())
                ->whereNotIn('status', ['cancelled'])->missingAnalytics();
            return [
                'channel' => $channel,
                'success' => $successes->get($channel->id),
                'run' => $runs->get($channel->id),
                'updates' => (int) ($counts->get($channel->id)?->updates ?? 0),
                'failures' => (int) ($counts->get($channel->id)?->failures ?? 0),
                'missing_count' => (clone $missing)->count(),
                'missing' => (clone $missing)->orderByDesc('show_date')->limit(5)->get(['id','title','show_date','analytics_sync_status','analytics_sync_note']),
                'events' => $recent->take(8),
            ];
        })->all();
    }
}
