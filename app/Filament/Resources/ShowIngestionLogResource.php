<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ShowIngestionLogResource\Pages;
use App\Models\ShowChangeLog;
use App\Models\ShowIngestionLog;
use App\Support\AdminModules;
use App\Support\StatusColor;
use Filament\Actions\Action;
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
                ->description('The historical snapshot from this particular Whatnot/import run.')
                ->columnSpanFull()
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('captured_fields')
                        ->label('Captured fields')
                        ->content(fn ($record) => static::fieldGrid($record?->capturedFields() ?? [], 'No show fields were stored in this job summary.'))
                        ->columnSpanFull(),
                ]),

            Section::make('Show History')
                ->description('A useful summary of what changed on this show. Raw sync runs stay available underneath when you need the audit trail.')
                ->visible(fn ($record) => (bool) $record?->show_id)
                ->columnSpanFull()
                ->schema([
                    \Filament\Forms\Components\Placeholder::make('show_history')
                        ->label('History')
                        ->content(fn ($record) => static::showHistory($record))
                        ->columnSpanFull(),
                ]),

            Section::make('Current Linked Show')
                ->description('What VortexOps stores on the show now, after later refreshes may have updated it.')
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
            return '<div style="border:1px solid rgb(229 231 235);border-radius:12px;padding:11px 13px;min-width:0;background:rgba(249,250,251,.5)">'
                . '<div style="font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;margin-bottom:4px;font-weight:700">' . e($label) . '</div>'
                . '<div style="font-size:14px;font-weight:700;overflow-wrap:anywhere">' . e((string) $value) . '</div>'
                . '</div>';
        })->implode('');

        return new HtmlString('<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:9px">' . $cells . '</div>');
    }

    private static function showHistory(?ShowIngestionLog $record): HtmlString
    {
        if (! $record?->show_id) {
            return new HtmlString('<div class="text-sm text-gray-500">No show is linked to this record.</div>');
        }

        $ingestions = ShowIngestionLog::query()
            ->with('channel')
            ->where('show_id', $record->show_id)
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        $changes = ShowChangeLog::query()
            ->where('show_id', $record->show_id)
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->filter(fn ($change) => static::historyValuesDiffer($change->old_value, $change->new_value));

        $showUrl = ShowResource::getUrl('view', ['record' => $record->show_id]);
        $first = $ingestions->first();
        $latest = $ingestions->last();

        $html = '<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px">'
            . '<div><div style="font-size:15px;font-weight:800">What changed on this show?</div><div style="font-size:11px;color:#6b7280;margin-top:2px">Previous values are grouped by field so repeated sync noise does not take over the page.</div></div>'
            . '<a href="' . e($showUrl) . '" style="display:inline-flex;align-items:center;min-height:36px;padding:7px 11px;border:1px solid #d1d5db;border-radius:9px;color:inherit;font-size:12px;font-weight:700;text-decoration:none">Open current show</a>'
            . '</div>';

        if ($first) {
            $firstFields = $first->capturedFields();
            $latestFields = $latest?->capturedFields() ?? [];
            $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:10px;margin-bottom:14px">';
            $html .= static::snapshotCard('First recorded snapshot', $firstFields, $first->created_at?->format('M j, Y g:i A'), '#7c3aed');
            if ($latest && $latest->id !== $first->id) {
                $html .= static::snapshotCard('Latest ingestion snapshot', $latestFields, $latest->created_at?->format('M j, Y g:i A'), '#0891b2');
            }
            $html .= '</div>';
        }

        $groups = $changes->groupBy('field_name')->map(function ($items, $field) {
            $firstChange = $items->first();
            $lastChange = $items->last();
            return [
                'field' => (string) $field,
                'old' => $firstChange?->old_value,
                'new' => $lastChange?->new_value,
                'count' => $items->count(),
                'at' => $lastChange?->created_at,
                'source' => $lastChange?->source,
            ];
        })->sortByDesc(fn ($group) => $group['at']?->timestamp ?? 0)->values();

        if ($groups->isNotEmpty()) {
            $html .= '<div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;margin:2px 0 8px">Meaningful field changes</div>';
            $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px">';

            foreach ($groups->take(16) as $group) {
                $label = static::historyFieldLabel($group['field']);
                $old = static::historyDisplayValue($group['field'], $group['old']);
                $new = static::historyDisplayValue($group['field'], $group['new']);
                $count = (int) $group['count'];
                $html .= '<div style="border:1px solid #dbeafe;background:#eff6ff55;border-radius:13px;padding:12px;min-width:0">'
                    . '<div style="display:flex;justify-content:space-between;gap:8px;align-items:start">'
                    . '<div style="font-size:12px;font-weight:800;color:#1d4ed8">' . e($label) . '</div>'
                    . '<div style="font-size:10px;color:#9ca3af;white-space:nowrap">' . e($group['at']?->format('M j, g:i A') ?? '—') . '</div>'
                    . '</div>'
                    . '<div style="display:grid;grid-template-columns:minmax(0,1fr) 18px minmax(0,1fr);gap:7px;align-items:stretch;margin-top:9px">'
                    . '<div style="border:1px solid #e5e7eb;background:#fff;border-radius:9px;padding:9px;min-width:0"><div style="font-size:9px;font-weight:800;text-transform:uppercase;color:#9ca3af">Previous</div><div style="margin-top:3px;font-size:12px;font-weight:650;overflow-wrap:anywhere">' . e($old) . '</div></div>'
                    . '<div style="display:flex;align-items:center;justify-content:center;color:#9ca3af">→</div>'
                    . '<div style="border:1px solid #bfdbfe;background:#fff;border-radius:9px;padding:9px;min-width:0"><div style="font-size:9px;font-weight:800;text-transform:uppercase;color:#3b82f6">Current</div><div style="margin-top:3px;font-size:12px;font-weight:800;overflow-wrap:anywhere">' . e($new) . '</div></div>'
                    . '</div>'
                    . '<div style="margin-top:7px;font-size:10px;color:#6b7280">' . ($count > 1 ? e($count . ' recorded updates · ') : '') . e(static::historySourceLabel((string) ($group['source'] ?: 'system'))) . '</div>'
                    . '</div>';
            }

            $html .= '</div>';
            if ($groups->count() > 16) {
                $html .= '<div style="margin-top:8px;font-size:11px;color:#6b7280">Showing the 16 most recently changed fields.</div>';
            }
        } else {
            $html .= '<div style="border:1px dashed #d1d5db;border-radius:12px;padding:18px;text-align:center;color:#6b7280;font-size:12px">No meaningful field changes were recorded for this show.</div>';
        }

        if ($ingestions->isNotEmpty()) {
            $html .= '<details style="margin-top:14px;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden">'
                . '<summary style="cursor:pointer;padding:11px 13px;background:#f9fafb;font-size:12px;font-weight:750;display:flex;justify-content:space-between;gap:8px"><span>Technical ingestion runs</span><span style="color:#6b7280;font-size:10px">' . e((string) $ingestions->count()) . ' runs</span></summary>'
                . '<div style="padding:4px 12px">';

            foreach ($ingestions->sortByDesc('created_at')->take(20) as $log) {
                $url = static::getUrl('view', ['record' => $log]);
                $html .= '<a href="' . e($url) . '" style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;padding:9px 2px;border-bottom:1px solid #f3f4f6;color:inherit;text-decoration:none">'
                    . '<div style="min-width:0"><div style="font-size:12px;font-weight:700">' . e($log->sourceLabel()) . '</div><div style="font-size:10px;color:#6b7280;margin-top:2px;overflow-wrap:anywhere">' . e($log->summary()) . ' · ' . e($log->channel?->name ?? 'All channels') . '</div></div>'
                    . '<div style="font-size:10px;color:#9ca3af;white-space:nowrap">' . e($log->created_at?->format('M j, g:i A') ?? '—') . '</div>'
                    . '</a>';
            }

            $html .= '</div></details>';
        }

        return new HtmlString($html);
    }

    private static function snapshotCard(string $title, array $fields, ?string $when, string $accent): string
    {
        $items = collect($fields)->take(6)->map(fn ($value, $label) => '<div style="min-width:0"><div style="font-size:9px;text-transform:uppercase;font-weight:800;color:#9ca3af">' . e($label) . '</div><div style="font-size:11px;font-weight:700;margin-top:2px;overflow-wrap:anywhere">' . e((string) $value) . '</div></div>')->implode('');
        if ($items === '') $items = '<div style="font-size:11px;color:#6b7280">No readable show fields captured in this run.</div>';

        return '<div style="border:1px solid #e5e7eb;border-radius:13px;padding:12px;min-width:0">'
            . '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px"><div style="display:flex;align-items:center;gap:7px"><span style="width:8px;height:8px;border-radius:999px;background:' . e($accent) . '"></span><strong style="font-size:12px">' . e($title) . '</strong></div><span style="font-size:10px;color:#9ca3af">' . e($when ?? '—') . '</span></div>'
            . '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-top:10px">' . $items . '</div>'
            . '</div>';
    }

    private static function historyFieldLabel(string $field): string
    {
        return match ($field) {
            'gross_revenue' => 'Gross Revenue',
            'whatnot_net' => 'Estimated Net',
            'completed_earnings' => 'Completed Earnings',
            'avg_order_value' => 'Average Order Value',
            'giveaway_spend' => 'Giveaway Spend',
            'giveaways_count' => 'Giveaways',
            'buyers_count' => 'Buyers',
            'first_time_buyers' => 'First-time Buyers',
            'returning_buyers' => 'Returning Buyers',
            'shares_count' => 'Shares',
            'show_duration' => 'Show Duration',
            'max_concurrent_viewers' => 'Peak Viewers',
            'total_views' => 'Total Views',
            'avg_order_rating' => 'Average Order Rating',
            'show_date' => 'Show Date',
            'start_time' => 'Start Time',
            'shipment_dimensions_json' => 'Shipment Package Dimensions',
            default => ucwords(str_replace('_', ' ', $field)),
        };
    }

    private static function historyDisplayValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') return '—';

        if (in_array($field, ['gross_revenue', 'whatnot_net', 'completed_earnings', 'avg_order_value', 'giveaway_spend'], true) && is_numeric($value)) {
            return '$' . number_format((float) $value, 2);
        }

        $decoded = static::decodeHistoryJson($value);
        if (is_array($decoded)) {
            $count = count($decoded);
            return $count . ' ' . str('record')->plural($count);
        }

        try {
            if ($field === 'show_date') return \Carbon\Carbon::parse((string) $value)->format('M j, Y');
            if ($field === 'start_time') return \Carbon\Carbon::parse((string) $value)->format('M j, Y g:i A');
        } catch (\Throwable) {
        }

        return (string) $value;
    }

    private static function historyValuesDiffer(mixed $old, mixed $new): bool
    {
        $oldJson = static::decodeHistoryJson($old);
        $newJson = static::decodeHistoryJson($new);

        if (is_array($oldJson) && is_array($newJson)) {
            return static::normalizeHistoryArray($oldJson) !== static::normalizeHistoryArray($newJson);
        }

        return (string) ($old ?? '') !== (string) ($new ?? '');
    }

    private static function decodeHistoryJson(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') return null;
        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    private static function normalizeHistoryArray(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) $value[$key] = static::normalizeHistoryArray($item);
        }
        ksort($value);
        return $value;
    }

    private static function historySourceLabel(string $source): string
    {
        return match ($source) {
            'whatnot_import' => 'Whatnot import',
            'whatnot_shipment_import' => 'Whatnot shipment sync',
            'manual' => 'Manual change',
            default => ucwords(str_replace('_', ' ', $source)),
        };
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
                TextColumn::make('created_at')
                    ->label('When')->since()
                    ->description(fn ($record) => $record->created_at?->format('M j, Y g:i A'))
                    ->tooltip(fn ($record) => $record->created_at?->toDayDateTimeString())
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Job / Pipeline')->badge()
                    ->formatStateUsing(fn ($state) => ShowIngestionLog::sourceLabels()[$state] ?? $state)
                    ->color('primary')->sortable(),
                TextColumn::make('channel.name')
                    ->label('Channel')->badge()->color('gray')->placeholder('All channels')->sortable(),
                TextColumn::make('summary')
                    ->label('What happened')
                    ->getStateUsing(fn (ShowIngestionLog $record) => $record->summary())
                    ->description(function (ShowIngestionLog $record): string {
                        $captured = $record->capturedFields();
                        if ($captured !== []) {
                            return collect($captured)->take(3)->map(fn ($v, $k) => $k . ': ' . $v)->implode(' · ');
                        }
                        $error = trim((string) $record->error_message);
                        return $error !== '' ? \Illuminate\Support\Str::limit($error, 100) : $record->sourceLabel();
                    })
                    ->color(fn (ShowIngestionLog $record) => $record->status === 'failed' ? 'danger' : ($record->status === 'partial' ? 'warning' : null))
                    ->tooltip(fn (ShowIngestionLog $record) => $record->error_message)
                    ->wrap(),
                TextColumn::make('show.title')
                    ->label('Show')
                    ->placeholder('Job summary')
                    ->searchable()
                    ->limit(40)
                    ->description(fn ($record) => $record->show ? 'Show #' . $record->show_id . ' · click for full ingestion history' : 'No individual show linked')
                    ->url(fn ($record) => $record->show ? static::getUrl('index', [
                        'tableFilters' => ['show_id' => ['value' => (string) $record->show_id]],
                    ]) : null),
                TextColumn::make('status')
                    ->label('Outcome')->badge()
                    ->formatStateUsing(fn ($state) => ShowIngestionLog::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state)),
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
                SelectFilter::make('show_id')
                    ->label('Show')
                    ->relationship('show', 'title')
                    ->searchable(),
                SelectFilter::make('source')->label('Job / Pipeline')->options(ShowIngestionLog::sourceLabels())->multiple(),
                SelectFilter::make('whatnot_channel_id')->label('Channel')->relationship('channel', 'name')->multiple()->preload(),
                SelectFilter::make('status')->label('Outcome')->options(ShowIngestionLog::statusLabels()),
                Filter::make('problems_only')->label('Problems only')->query(fn (Builder $query) => $query->where('status', '!=', 'success')),
                Filter::make('hide_old_failures')->label('Hide problems older than 24h')->query(fn (Builder $query) => $query->where(function (Builder $query): void {
                    $query->where('status', 'success')->orWhere('created_at', '>=', now()->subDay());
                })),
                Filter::make('created_at')
                    ->label('Date range')
                    ->form([DatePicker::make('from')->label('From'), DatePicker::make('until')->label('Until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(function (array $data): ?string {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;
                        return match (true) {
                            $from && $until => "From {$from} to {$until}",
                            (bool) $from => "From {$from}",
                            (bool) $until => "Until {$until}",
                            default => null,
                        };
                    }),
            ])
            ->actions([
                ViewAction::make()->tooltip('View this ingestion snapshot')->iconButton(),
                Action::make('open_show')
                    ->label('Open Show')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->tooltip('Open current show workspace')
                    ->iconButton()
                    ->visible(fn (ShowIngestionLog $record) => (bool) $record->show_id)
                    ->url(fn (ShowIngestionLog $record) => ShowResource::getUrl('view', ['record' => $record->show_id])),
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
