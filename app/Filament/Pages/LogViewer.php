<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;
use App\Filament\Concerns\HasAdminNavVisibility;

class LogViewer extends Page
{
    use HasAdminNavVisibility;

    protected static ?int $navigationSort = 95;

    protected static ?string $title = 'Log Viewer';

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-bug-ant'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Settings'; }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    // Diagnostic tool — only surface it in the owner's menu (still URL-reachable).
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public string $selectedFile = '';
    public string $levelFilter  = '';
    public string $search       = '';
    public int    $perPage      = 100;

    public function mount(): void
    {
        $files = $this->logFiles();
        $this->selectedFile = $files[0] ?? '';
    }

    public function getView(): string { return 'filament.pages.log-viewer'; }

    public function getSubheading(): ?string
    {
        return 'Raw Laravel log files — useful for tracing down an error a user reported.';
    }

    // ── Log file discovery ────────────────────────────────────────────────────

    public function logFiles(): array
    {
        return collect(File::glob(storage_path('logs/*.log')))
            ->sortByDesc(fn ($f) => filemtime($f))
            ->map(fn ($f) => basename($f))
            ->values()
            ->all();
    }

    // ── Parsed entries ────────────────────────────────────────────────────────

    // Only the newest entries are ever shown, so cap how much of the file we
    // read into memory — a multi-hundred-MB log otherwise exhausts PHP's
    // memory_limit just to parse and discard everything but the last page.
    private const MAX_TAIL_BYTES = 5 * 1024 * 1024;

    public function getEntriesProperty(): array
    {
        if (! $this->selectedFile) return [];

        $path = storage_path('logs/' . basename($this->selectedFile));
        if (! File::exists($path)) return [];

        $size = File::size($path);

        if ($size > self::MAX_TAIL_BYTES) {
            $handle = fopen($path, 'r');
            fseek($handle, -self::MAX_TAIL_BYTES, SEEK_END);
            $contents = fread($handle, self::MAX_TAIL_BYTES);
            fclose($handle);
        } else {
            $contents = File::get($path);
        }

        // Split on log entry boundaries: [YYYY-MM-DD HH:MM:SS]
        $pattern = '/\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2}|Z)?)\] (\w+)\.(\w+): (.*?)(?=\[\d{4}-\d{2}-\d{2}|\z)/s';
        preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER);

        $entries = array_reverse(array_map(fn ($m) => [
            'timestamp' => $m[1],
            'env'       => $m[2],
            'level'     => strtolower($m[3]),
            'message'   => trim($m[4]),
        ], $matches));

        if ($this->levelFilter) {
            $entries = array_values(array_filter($entries, fn ($e) => $e['level'] === $this->levelFilter));
        }

        if ($this->search) {
            $s = strtolower($this->search);
            $entries = array_values(array_filter($entries, fn ($e) => str_contains(strtolower($e['message']), $s)));
        }

        return array_slice($entries, 0, $this->perPage);
    }

    public function getLevelCountsProperty(): array
    {
        $counts = ['error' => 0, 'warning' => 0, 'info' => 0, 'debug' => 0, 'critical' => 0];
        foreach ($this->entries as $e) {
            $counts[$e['level']] = ($counts[$e['level']] ?? 0) + 1;
        }
        return $counts;
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear')
                ->label('Clear Log')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn () => auth()->user()?->isOwner())
                ->requiresConfirmation()
                ->modalHeading('Clear this log file?')
                ->modalDescription('This permanently deletes all entries in the selected log file.')
                ->action(function () {
                    $path = storage_path('logs/' . basename($this->selectedFile));
                    if (File::exists($path)) {
                        File::put($path, '');
                    }
                    Notification::make()->title('Log file cleared.')->warning()->send();
                }),
        ];
    }
}
