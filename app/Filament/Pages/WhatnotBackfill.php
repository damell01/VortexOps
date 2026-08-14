<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use App\Support\NavVisibility;

class WhatnotBackfill extends Page implements HasForms
{
    use InteractsWithForms;

    public function getView(): string
    {
        return 'filament.pages.whatnot-backfill';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Nav visibility is configured per role in Settings; without this
        // check an override here silently ignored that setting and the link
        // stayed in the sidebar regardless.
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return false;
    }

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-arrow-down-tray';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Admin';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public string $command = 'sync-all';
    public int $limit = 500;
    public int $days = 8;
    public bool $skipOrders = false;
    public bool $skipShipments = false;
    public bool $skipLedger = false;
    public bool $noOrders = false;
    public string $output = '';
    public bool $isRunning = false;

    public function mount(): void
    {
        if (! auth()->user()?->isOwner()) {
            abort(403, 'Only the owner can access this page.');
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('command')
                    ->label('Command')
                    ->options([
                        'sync-all' => 'Full Sync (Shows → Orders → Shipments → Ledger)',
                        'import' => 'Import Shows Only',
                        'import-orders' => 'Import Orders Only',
                        'sync-shipments' => 'Sync Shipments Only',
                        'import-ledger' => 'Import Ledger Only',
                    ])
                    ->default('sync-all')
                    ->live(),

                TextInput::make('limit')
                    ->label('Show Limit (for import/sync-all)')
                    ->numeric()
                    ->default(500)
                    ->minValue(1)
                    ->maxValue(5000)
                    ->visible(fn ($get) => in_array($get('command'), ['sync-all', 'import'])),

                TextInput::make('days')
                    ->label('Days Back (for ledger)')
                    ->numeric()
                    ->default(8)
                    ->minValue(1)
                    ->maxValue(365)
                    ->visible(fn ($get) => in_array($get('command'), ['import-ledger', 'sync-all'])),

                Toggle::make('noOrders')
                    ->label('Skip Orders (faster show import)')
                    ->default(false)
                    ->visible(fn ($get) => $get('command') === 'import'),

                Toggle::make('skipOrders')
                    ->label('Skip Orders')
                    ->default(false)
                    ->visible(fn ($get) => $get('command') === 'sync-all'),

                Toggle::make('skipShipments')
                    ->label('Skip Shipments')
                    ->default(false)
                    ->visible(fn ($get) => $get('command') === 'sync-all'),

                Toggle::make('skipLedger')
                    ->label('Skip Ledger')
                    ->default(false)
                    ->visible(fn ($get) => $get('command') === 'sync-all'),

                Textarea::make('output')
                    ->label('Output Log')
                    ->disabled()
                    ->rows(15)
                    ->default('Ready to run. Click "Start Import" to begin.'),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('runImport')
                ->label('▶ Start Import')
                ->color('success')
                ->disabled(fn () => $this->isRunning)
                ->action('runImport'),

            Action::make('clear')
                ->label('Clear Log')
                ->color('gray')
                ->action(fn () => $this->output = ''),
        ];
    }

    public function runImport(): void
    {
        $this->isRunning = true;
        $this->output = "Starting {$this->command} at " . now()->format('Y-m-d H:i:s') . "...\n\n";

        try {
            $config = match ($this->command) {
                'sync-all' => [
                    'args' => [
                        '--limit' => $this->limit,
                        $this->skipOrders ? '--skip-orders' : null,
                        $this->skipShipments ? '--skip-shipments' : null,
                        $this->skipLedger ? '--skip-ledger' : null,
                    ],
                    'command' => 'whatnot:sync-all',
                ],
                'import' => [
                    'args' => [
                        '--limit' => $this->limit,
                        $this->noOrders ? '--no-orders' : null,
                    ],
                    'command' => 'whatnot:import',
                ],
                'import-orders' => [
                    'args' => ['--new-only'],
                    'command' => 'whatnot:import-orders',
                ],
                'sync-shipments' => [
                    'args' => [],
                    'command' => 'whatnot:sync-shipments',
                ],
                'import-ledger' => [
                    'args' => ['--days' => $this->days],
                    'command' => 'whatnot:import-ledger',
                ],
                default => throw new \Exception("Unknown command: {$this->command}"),
            };

            $args = array_filter($config['args']);
            $command = $config['command'];

            $this->output .= "Command: {$command}\n";
            $this->output .= "Args: " . json_encode($args) . "\n\n";
            $this->output .= "─────────────────────────────────────────\n\n";

            $exitCode = Artisan::call($command, $args, outputBuffer: $output = '');

            $this->output .= $output;
            $this->output .= "\n─────────────────────────────────────────\n";
            $this->output .= "Completed at " . now()->format('Y-m-d H:i:s') . "\n";
            $this->output .= "Exit code: {$exitCode}\n";

            if ($exitCode === 0) {
                $this->output .= "✅ Success!";
            } else {
                $this->output .= "❌ Failed with exit code {$exitCode}";
            }
        } catch (\Throwable $e) {
            $this->output .= "\n❌ Error: " . $e->getMessage() . "\n";
            $this->output .= $e->getTraceAsString();
        } finally {
            $this->isRunning = false;
        }
    }
}
