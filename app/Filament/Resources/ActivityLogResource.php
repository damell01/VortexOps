<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getModelLabel(): string
    {
        return 'Activity';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Activity Log';
    }

    // Only admins can view the activity log
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer']);
    }

    public static function canCreate(): bool  { return false; }
    public static function canEdit($r): bool  { return false; }
    public static function canDelete($r): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event')->columns(2)->schema([
                Placeholder::make('description')
                    ->content(fn (Activity $record): string => ucfirst($record->description)),

                Placeholder::make('event')
                    ->content(fn (Activity $record): string => $record->event ?? '—'),

                Placeholder::make('subject')
                    ->label('Record')
                    ->content(fn (Activity $record): string => $record->subject_type
                        ? class_basename($record->subject_type) . ' #' . $record->subject_id
                        : '—'),

                Placeholder::make('causer')
                    ->label('By')
                    ->content(fn (Activity $record): string => $record->causer?->name ?? 'System'),

                Placeholder::make('created_at')
                    ->label('When')
                    ->content(fn (Activity $record): string => $record->created_at->format('M j, Y H:i:s')),
            ]),

            Section::make('Changes')->schema([
                Placeholder::make('changes_display')
                    ->label('')
                    ->columnSpanFull()
                    ->content(function (Activity $record): \Illuminate\Support\HtmlString {
                        $changes = $record->attribute_changes ?? $record->properties?->toArray() ?? [];

                        if (empty($changes)) {
                            return new \Illuminate\Support\HtmlString('<span class="text-gray-400 text-sm">No attribute changes recorded.</span>');
                        }

                        $old = $changes['old'] ?? [];
                        $new = $changes['attributes'] ?? $changes['new'] ?? [];

                        if (empty($old) && empty($new)) {
                            $html = '<pre class="text-xs text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900 p-3 rounded-lg overflow-auto">' . e(json_encode($changes, JSON_PRETTY_PRINT)) . '</pre>';
                            return new \Illuminate\Support\HtmlString($html);
                        }

                        $keys   = array_unique(array_merge(array_keys($old), array_keys($new)));
                        $rows   = '';

                        foreach ($keys as $key) {
                            if ($key === 'password') {
                                continue;
                            }
                            $oldVal = isset($old[$key]) ? (is_array($old[$key]) ? json_encode($old[$key]) : $old[$key]) : null;
                            $newVal = isset($new[$key]) ? (is_array($new[$key]) ? json_encode($new[$key]) : $new[$key]) : null;

                            $changed = $oldVal !== $newVal;
                            $rowClass = $changed ? 'bg-amber-50 dark:bg-amber-950' : '';

                            $rows .= '<tr class="' . $rowClass . '">'
                                . '<td class="px-3 py-1.5 font-mono text-xs text-gray-600 dark:text-gray-400 whitespace-nowrap">' . e($key) . '</td>'
                                . '<td class="px-3 py-1.5 text-xs text-red-600 dark:text-red-400 line-through">' . e($oldVal ?? '') . '</td>'
                                . '<td class="px-3 py-1.5 text-xs text-green-600 dark:text-green-400">' . e($newVal ?? '') . '</td>'
                                . '</tr>';
                        }

                        $html = '<div class="overflow-auto rounded-lg border border-gray-200 dark:border-gray-700">'
                            . '<table class="w-full text-left">'
                            . '<thead class="bg-gray-100 dark:bg-gray-800">'
                            . '<tr>'
                            . '<th class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300">Field</th>'
                            . '<th class="px-3 py-2 text-xs font-semibold text-red-500">Before</th>'
                            . '<th class="px-3 py-2 text-xs font-semibold text-green-500">After</th>'
                            . '</tr></thead>'
                            . '<tbody class="divide-y divide-gray-100 dark:divide-gray-800">' . $rows . '</tbody>'
                            . '</table></div>';

                        return new \Illuminate\Support\HtmlString($html);
                    }),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('description')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('subject_type')
                    ->label('Model')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('subject_id')
                    ->label('ID')
                    ->width('60px'),

                TextColumn::make('causer.name')
                    ->label('By')
                    ->default('System'),

                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('description')
                    ->label('Event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),

                SelectFilter::make('subject_type')
                    ->label('Model')
                    ->options(
                        Activity::query()
                            ->whereNotNull('subject_type')
                            ->distinct()
                            ->pluck('subject_type', 'subject_type')
                            ->mapWithKeys(fn ($v) => [$v => class_basename($v)])
                            ->toArray()
                    ),
            ])
            ->striped()
            ->defaultSort('id', 'desc')
            ->recordAction(fn ($record) => 'view')
            ->actions([ViewAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view'  => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
