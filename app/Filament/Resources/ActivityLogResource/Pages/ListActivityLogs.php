<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    use \App\Filament\Concerns\DownloadsTableCsv;

    protected static string $resource = ActivityLogResource::class;

    /** Entries with no person behind them (jobs, imports, the scheduler) flood the log, so they start hidden. */
    #[\Livewire\Attributes\Url(as: 'system')]
    public bool $showSystem = false;

    public function updatedShowSystem(): void
    {
        $this->resetPage();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('toggleSystem')
                ->label(fn () => $this->showSystem ? 'Showing system activity' : 'Show system activity')
                ->icon(fn () => $this->showSystem ? 'heroicon-o-check-circle' : 'heroicon-o-cog-6-tooth')
                ->color(fn () => $this->showSystem ? 'primary' : 'gray')
                ->outlined(fn () => ! $this->showSystem)
                ->tooltip(fn () => $this->showSystem ? 'Click to hide system entries again' : 'Include entries made by the system, not a person')
                ->action(function (): void {
                    $this->showSystem = ! $this->showSystem;
                    $this->resetPage();
                }),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'An audit trail of who changed what, across the app — useful for tracing back an unexpected edit.';
    }
}
