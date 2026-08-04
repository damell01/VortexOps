<?php

namespace App\Filament\Pages;

use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Support\AdminModules;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class EndOfStreamForm extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $moduleSlug = 'streams';

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $title = 'End of Stream';

    public ?Show $show = null;
    public ?StreamerLogEntry $entry = null;

    public ?array $data = [];

    public function mount(?string $showId = null): void
    {
        if ($showId) {
            $this->show = Show::findOrFail($showId);
            $this->entry = $this->show->streamerLogEntry()->first();

            if (! $this->entry) {
                $streamer = auth()->user()?->streamer;
                if ($streamer && $this->show->streamers()->where('streamers.id', $streamer->id)->exists()) {
                    $this->entry = StreamerLogEntry::create([
                        'show_id' => $this->show->id,
                        'streamer_id' => $streamer->id,
                        'status' => 'pending',
                    ]);
                }
            }

            if ($this->entry) {
                $this->form->fill([
                    'hours_streamed' => $this->entry->hours_streamed,
                    'number_of_shipments' => $this->entry->number_of_shipments,
                    'number_of_packages_over_500' => $this->entry->number_of_packages_over_500,
                    'pwe_count' => $this->entry->pwe_count,
                    'label_count' => $this->entry->label_count,
                    'product_cost' => $this->entry->product_cost,
                    'hard_copy' => $this->entry->hard_copy,
                    'notes' => $this->entry->notes,
                ]);
            }
        }
    }

    public function getTitle(): string | Htmlable
    {
        return $this->show ? "End of Stream: {$this->show->title}" : 'End of Stream';
    }

    public function getSubheading(): ?string
    {
        return 'Quickly log your show metrics before wrapping up. You\'ll review and submit your full data in the next step.';
    }

    protected function getFormSchema(): array
    {
        $streamer = auth()->user()?->streamer;
        $isPweLabels = $streamer && $streamer->payout_type === 'pwe_labels';

        return [
            Section::make('Show Quick-Log')
                ->columnSpanFull()
                ->description('Enter your show data here. Don\'t worry about perfection — you can review and edit everything in detail later.')
                ->schema([
                    Placeholder::make('show_label')
                        ->visible(fn () => $this->show !== null)
                        ->label('')
                        ->content(fn () => $this->show
                            ? new \Illuminate\Support\HtmlString(
                                '<span style="font-weight:600">' . e($this->show->title ?: 'Untitled show') . '</span>'
                                . ($this->show->show_date ? ' <span style="color:#6b7280">· ' . e($this->show->show_date->format('M j, Y')) . '</span>' : '')
                            )
                            : '—'),

                    Grid::make(2)->schema([
                        TextInput::make('hours_streamed')
                            ->label('Hours Streamed')
                            ->numeric()
                            ->step(0.25)
                            ->default(0)
                            ->required()
                            ->helperText('How long were you live? (e.g., 2.5 hours)'),

                        TextInput::make('number_of_shipments')
                            ->label('Shipments Sent')
                            ->integer()
                            ->default(0)
                            ->required()
                            ->helperText('Total packages shipped from this show'),

                        TextInput::make('number_of_packages_over_500')
                            ->label('High-Value Packages ($500+)')
                            ->integer()
                            ->default(0)
                            ->helperText('Packages over $500 (affects shipping surcharge)'),

                        TextInput::make('product_cost')
                            ->label('Total Product Cost')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->helperText('Cost of inventory you sold (from your records)'),

                        Toggle::make('hard_copy')
                            ->label('Hard Copy Log Filed')
                            ->default(false)
                            ->helperText('Did you print and file a physical log sheet?'),

                        TextInput::make('pwe_count')
                            ->label('PWE Count')
                            ->integer()
                            ->default(0)
                            ->visible(fn () => $isPweLabels)
                            ->helperText('Packages with PostagePaidEnvelopes'),

                        TextInput::make('label_count')
                            ->label('Label-Only Count')
                            ->integer()
                            ->default(0)
                            ->visible(fn () => $isPweLabels)
                            ->helperText('Packages with labels (no PWE)'),
                    ]),

                    Textarea::make('notes')
                        ->label('Quick Notes')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('Anything the team should know? (connection issues, special items, etc.)'),
                ]),
        ];
    }

    public function submit(): void
    {
        if (! $this->entry) {
            Notification::make()
                ->title('No show selected')
                ->body('Unable to log data without a show. Please try again.')
                ->danger()
                ->send();
            return;
        }

        $data = $this->form->getState();

        $this->entry->update([
            'hours_streamed' => $data['hours_streamed'] ?? 0,
            'number_of_shipments' => $data['number_of_shipments'] ?? 0,
            'number_of_packages_over_500' => $data['number_of_packages_over_500'] ?? 0,
            'pwe_count' => $data['pwe_count'] ?? null,
            'label_count' => $data['label_count'] ?? null,
            'product_cost' => $data['product_cost'] ?? 0,
            'hard_copy' => $data['hard_copy'] ?? false,
            'notes' => $data['notes'] ?? null,
        ]);

        Notification::make()
            ->title('✓ Show data saved')
            ->body('Your quick log has been saved. You can now review the full details in your Streamer Log.')
            ->success()
            ->send();

        $this->redirect(route('filament.admin.resources.streamer-logs.edit', $this->entry), navigate: true);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return AdminModules::isEnabled('streams') && $user?->isStreamer();
    }

    public static function getSlug(): string
    {
        return 'end-of-stream';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return 'End of Stream';
    }

    public static function getNavigationGroup(): string | null
    {
        return 'Streams';
    }

    public static function getNavigationSort(): ?int
    {
        return 38;
    }
}
