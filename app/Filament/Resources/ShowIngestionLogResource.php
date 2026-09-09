<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ShowIngestionLogResource\Pages;
use App\Models\ShowIngestionLog;
use App\Support\AdminModules;
use App\Support\StatusColor;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ShowIngestionLogResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'streams';
    protected static ?string $model = ShowIngestionLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-arrow-down-tray'; }
    public static function getNavigationGroup(): string|\UnitEnum|null { return AdminModules::navigationGroupFor('streams'); }
    public static function getNavigationSort(): ?int { return 4; }
    public static function getModelLabel(): string { return 'Ingestion Record'; }
    public static function getPluralModelLabel(): string { return 'Ingestion Records'; }
    public static function getNavigationLabel(): string { return 'Ingestion'; }

    public static function getNavigationBadge(): ?string
    {
        $count = ShowIngestionLog::whereIn('status', ['failed', 'partial'])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'danger'; }
    public static function canCreate(): bool { return false; }
    public static function canEdit($r): bool { return false; }
    public static function canDelete($r): bool { return auth()->user()?->isOwner() ?? false; }
    public static function canDeleteAny(): bool { return auth()->user()?->isOwner() ?? false; }

    protected static function passesModuleAccessCheck(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['show', 'channel']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ingestion Details')->columnSpanFull()->schema([
                \Filament\Forms\Components\Placeholder::make('summary')->label('What happened')->content(fn ($record) => $record?->summary() ?? '—'),
                \Filament\Forms\Components\Placeholder::make('source')->label('Job / Pipeline')->content(fn ($record) => $record?->sourceLabel() ?? '—'),
                \Filament\Forms\Components\Placeholder::make('channel')->label('Channel')->content(fn ($record) => $record?->channel?->name ?? 'All channels / job summary'),
                \Filament\Forms\Components\Placeholder::make('status')->label('Outcome')->content(fn ($record) => ShowIngestionLog::statusLabels()[$record?->status ?? ''] ?? ($record?->status ?? '—')),
                \Filament\Forms\Components\Placeholder::make('when')->label('Recorded')->content(fn ($record) => $record?->created_at?->format('M j, Y g:i:s A') ?? '—'),
                \Filament\Forms\Components\Placeholder::make('show_title')->label('Linked Show')->content(fn ($record) => $record?->show ? ($record->show->title . ' · Show #' . $record->show_id) : 'No show linked — this is a pipeline/job summary'),
                \Filament\Forms\Components\Placeholder::make('failure_type')->label('Failure type')->content(fn ($record) => $record?->failureTypeLabel() ?? '—'),
                \Filament\Forms\Components\Placeholder::make('error_message')->label('Error')->content(fn ($record) => $record?->error_message ?? '—')->columnSpanFull(),
            ]),

            Section::make('Data Captured In This Run')
                ->description('The useful show/analytics fields saved in this ingestion record. This is the historical snapshot for this run.')
                ->columnSpanFull()
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('captured_fields')
                        ->label('Captured fields')
                        ->content(fn ($record) => static::fieldGrid($record?->capturedFields() ?? [], 'No show fields were stored in this job summary.'))
                        ->columnSpanFull(),
                ]),

            Section::make('Current Linked Show')
                ->description('Current values on the show now, after later refreshes may have updated it.')
                ->visible(fn ($record) => (bool) $record?->show)
                ->columnSpanFull()
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('current_show_fields')
                        ->label('Current values')
                        ->content(fn ($record) => static::fieldGrid($record?->currentShowFields() ?? [], 'No current show values available.'))
                        ->columnSpanFull(),
                ]),

            Section::make('Raw Diagnostic Payload')
                ->description('Full payload retained for troubleshooting. The sections above are the normal readable view.')
                ->collapsed()
                ->columnSpanFull()
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('raw_payload')
                        ->label('Raw JSON')
                        ->content(fn ($record) => new HtmlString('<pre style="white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;line-height:1.55">' . e($record?->raw_payload ? json_encode($record->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—') . '</pre>'))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function fieldGrid(array $fields, string $empty): HtmlString
    {
        if ($fields === []) {
            return new HtmlString('<div class="text-sm text-gray-500">' . e($empty) . '</div>');
        }

        $cells = collect($fields)->map(function ($value, $label) {
            return '<div style="border:1px solid rgb(229 231 235);border-radius:10px;padding:10px 12px;min-width:0">'
                . '<div style="font-size:11px;color:#6b7280;margin-bottom:3px">' . e($label) . '</div>'
                . '<div style="font-size:14px;font-weight:650;overflow-wrap:anywhere">' . e((string) $value) . '</div>'
                . '</div>';
        })->implode('');

        return new HtmlString('<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px">' . $cells . '</div>');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->deferLoading()
            ->emptyStateHeading('No ingestion logs')
            ->emptyStateDescription('Whatnot jobs are logged here with their results.')
            ->emptyStateIcon('heroicon-o-arrow-down-tray')
            ->columns([
                TextColumn::make('created_at')->label('When')->since()->description(fn ($record) => $record->created_at?->format('M j, Y g:i A'))->tooltip(fn ($record) => $record->created_at?->toDayDateTimeString())->sortable(),
                TextColumn::make('source')->label('Job / Pipeline')->badge()->formatStateUsing(fn ($state) => ShowIngestionLog::sourceLabels()[$state] ?? $state)->color('primary')->sortable(),
                TextColumn::make('channel.name')->label('Channel')->badge()->color('gray')->placeholder('All channels')->sortable(),
                TextColumn::make('summary')->label('What happened')->getStateUsing(fn (ShowIngestionLog $record) => $record->summary())->description(function (ShowIngestionLog $record): string {
                    $captured = $record->capturedFields();
                    if ($captured !== []) {
                        return collect($captured)->take(3)->map(fn ($v, $k) => $k . ': ' . $v)->implode(' · ');
                    }
                    $error = trim((string) $record->error_message);
                    return $error !== '' ? \Illuminate\Support\Str::limit($error, 100) : $record->sourceLabel();
                })->color(fn (ShowIngestionLog $record) => $record->status === 'failed' ? 'danger' : ($record->status === 'partial' ? 'warning' : null))->tooltip(fn (ShowIngestionLog $record) => $record->error_message)->wrap(),
                TextColumn::make('show.title')->label('Show')->placeholder('Job summary')->searchable()->limit(40)->description(fn ($record) => $record->show ? 'Show #' . $record->show_id . ' · ' . ($record->show->show_date?->format('M j, Y') ?? 'date unknown') : 'No individual show linked')->url(fn ($record) => $record->show ? ShowResource::getUrl('view', ['record' => $record->show_id]) : null),
                TextColumn::make('status')->label('Outcome')->badge()->formatStateUsing(fn ($state) => ShowIngestionLog::statusLabels()[$state] ?? $state)->color(fn ($state) => StatusColor::for($state)),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([15, 25, 50])
            ->groups([
                Group::make('source')->label('Job / Pipeline')->getTitleFromRecordUsing(fn (ShowIngestionLog $record) => $record->sourceLabel()),
                Group::make('channel.name')->label('Channel')->getTitleFromRecordUsing(fn ($record) => $record->channel?->name ?? 'All channels'),
                Group::make('show_id')->label('Show')->getTitleFromRecordUsing(fn ($record) => $record->show ? $record->show->title . ' · Show #' . $record->show_id : 'Pipeline / job summaries'),
                Group::make('created_at')->label('Day')->date(),
            ])
            ->filters([
                SelectFilter::make('source')->label('Job / Pipeline')->options(ShowIngestionLog::sourceLabels())->multiple(),
                SelectFilter::make('whatnot_channel_id')->label('Channel')->relationship('channel', 'name')->multiple()->preload(),
                SelectFilter::make('status')->label('Outcome')->options(ShowIngestionLog::statusLabels()),
                Filter::make('problems_only')->label('Problems only')->query(fn (Builder $query) => $query->where('status', '!=', 'success')),
                Filter::make('hide_old_failures')->label('Hide problems older than 24h')->query(fn (Builder $query) => $query->where(function (Builder $query): void { $query->where('status', 'success')->orWhere('created_at', '>=', now()->subDay()); })),
                Filter::make('created_at')->label('Date range')->form([DatePicker::make('from')->label('From'), DatePicker::make('until')->label('Until')])->query(fn (Builder $query, array $data) => $query->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))->indicateUsing(function (array $data): ?string {
                    $from = $data['from'] ?? null; $until = $data['until'] ?? null;
                    return match (true) { $from && $until => "From {$from} to {$until}", (bool) $from => "From {$from}", (bool) $until => "Until {$until}", default => null };
                }),
            ])
            ->actions([
                ViewAction::make()->iconButton(),
                \Filament\Actions\DeleteAction::make()->iconButton()->visible(fn (ShowIngestionLog $record) => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShowIngestionLogs::route('/'),
            'view' => Pages\ViewShowIngestionLog::route('/{record}'),
        ];
    }
}
