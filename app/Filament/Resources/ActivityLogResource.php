<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Forms\Components\Placeholder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use App\Filament\Concerns\HasAdminNavVisibility;

class ActivityLogResource extends Resource
{
    use HasAdminNavVisibility;

    protected static ?string $model = Activity::class;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Admin';
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

    public static function canAccess(): bool
    {
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();
        return $user?->isAdmin() ?? false;
    }

    // Diagnostic tool — only surface it in the owner's menu (still URL-reachable).
    // No shouldRegisterNavigation() override: it said owner-only while
    // canAccess() above admits admins too, so an admin could open the page but
    // never saw a link to it. The trait derives the link from canAccess(), and
    // Roles & Permissions can still hide it per role.

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['causer']);
    }

    public static function canView(\Illuminate\Database\Eloquent\Model $record): bool { return static::canAccess(); }

    public static function canCreate(): bool  { return false; }
    public static function canEdit($r): bool  { return false; }
    public static function canDelete($r): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Event')->columns(2)->columnSpanFull()->schema([
                Placeholder::make('description')
                    ->content(fn (Activity $record): string => ucfirst($record->description)),

                Placeholder::make('event')
                    ->content(fn (Activity $record): string => $record->event ?? '—'),

                Placeholder::make('subject')
                    ->label('Record')
                    ->content(function (Activity $record): string {
                        if (! $record->subject_type) {
                            return '—';
                        }
                        $label = class_basename($record->subject_type).' #'.$record->subject_id;
                        // Name the record when it still exists ("Streamer #22 — Micha").
                        $subject = rescue(fn () => $record->loadMissing('subject')->subject, null, false);
                        $name = $subject?->name ?? $subject?->title ?? null;

                        return is_string($name) && $name !== '' ? $label.' — '.$name : $label;
                    }),

                Placeholder::make('causer')
                    ->label('By')
                    ->content(fn (Activity $record): string => $record->causer?->name ?? 'System'),

                Placeholder::make('created_at')
                    ->label('When')
                    ->content(fn (Activity $record): string => $record->created_at->format('M j, Y g:i:s A')),
            ]),

            Section::make('Changes')->columnSpanFull()->schema([
                Placeholder::make('changes_display')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->content(fn (Activity $record): HtmlString => static::changesHtml($record))
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('User and system actions are logged here as people work.')
            ->emptyStateIcon('heroicon-o-clock')
            ->extraAttributes(['data-sticky-header' => 'true'])
            ->deferLoading()
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'auth'    => 'info',
                        'user'    => 'warning',
                        'payout'  => 'success',
                        'pay_run' => 'success',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'auth'    => 'Auth',
                        'user'    => 'User',
                        'payout'  => 'Payout',
                        'pay_run' => 'Pay Run',
                        default   => ucfirst($state ?? 'general'),
                    }),

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

                TextColumn::make('changed_fields')
                    ->label('Changed')
                    ->state(fn (Activity $record): ?string => static::changedFieldsSummary($record))
                    ->placeholder('—')
                    ->wrap()
                    ->color('gray'),

                TextColumn::make('causer.name')
                    ->label('By')
                    ->default('System'),

                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('log_name')
                    ->label('Log')
                    ->options([
                        'auth'     => 'Auth (login/logout)',
                        'user'     => 'Users',
                        'payout'   => 'Payouts',
                        'pay_run'  => 'Pay Runs',
                        'default'  => 'General',
                    ]),

                SelectFilter::make('description')
                    ->label('Event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),

                SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id')),

                SelectFilter::make('subject_type')
                    ->label('Model')
                    ->options(fn () => Cache::remember('filter:activity_subject_types', 300, fn () => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($v) => [$v => class_basename($v)])
                        ->toArray())),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions(\App\Support\MobileTablePagination::options([10, 25, 50]))
            ->defaultPaginationPageOption(\App\Support\MobileTablePagination::defaultPageSize(25))
            ->defaultSort('id', 'desc')
            ->recordAction(fn ($record) => 'view')
            ->actions([ViewAction::make()->iconButton()]);
    }

    /** Fields never shown, even when they changed. */
    private const HIDDEN_FIELDS = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /**
     * The before and after values of an entry.
     *
     * Model updates are written by AuditsUpdates into `properties`; entries
     * Spatie writes itself (created, deleted, and models without
     * AuditsUpdates) land in `attribute_changes`. Each starts as an empty
     * collection, so take whichever one actually holds old/new values.
     *
     * @return array{old: array<string, mixed>, new: array<string, mixed>, extra: array<string, mixed>}
     */
    public static function changesFor(Activity $record): array
    {
        $extra = [];
        foreach ([$record->attribute_changes, $record->properties] as $source) {
            $data = $source?->toArray() ?? [];
            $old = (array) ($data['old'] ?? []);
            $new = (array) ($data['attributes'] ?? $data['new'] ?? []);
            if ($old !== [] || $new !== []) {
                return ['old' => $old, 'new' => $new, 'extra' => []];
            }
            $extra += $data;
        }

        return ['old' => [], 'new' => [], 'extra' => $extra];
    }

    /** "Status, Hourly rate +2 more" for the list. */
    public static function changedFieldsSummary(Activity $record): ?string
    {
        ['old' => $old, 'new' => $new] = static::changesFor($record);
        $fields = collect(array_keys($new + $old))
            ->reject(fn ($k) => in_array($k, self::HIDDEN_FIELDS, true) || in_array($k, ['created_at', 'updated_at'], true))
            ->map(fn ($k) => Str::of($k)->replace('_', ' ')->ucfirst()->toString())
            ->values();

        if ($fields->isEmpty()) {
            return null;
        }

        return $fields->take(3)->implode(', ').($fields->count() > 3 ? ' +'.($fields->count() - 3).' more' : '');
    }

    private static function displayValue(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    public static function changesHtml(Activity $record): HtmlString
    {
        ['old' => $old, 'new' => $new, 'extra' => $extra] = static::changesFor($record);

        if ($old === [] && $new === []) {
            if ($extra === []) {
                return new HtmlString('<span class="text-gray-500 dark:text-gray-400 text-sm">No field changes were recorded for this entry.</span>');
            }

            return new HtmlString('<pre class="text-xs text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-900 p-3 rounded-lg overflow-auto">'
                .e(json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)).'</pre>');
        }

        // A created entry has only "after" values; a deleted one only "before".
        $showBefore = $old !== [];
        $showAfter = $new !== [];

        $rows = '';
        foreach (array_keys($new + $old) as $key) {
            if (in_array($key, self::HIDDEN_FIELDS, true) || in_array($key, ['created_at', 'updated_at'], true)) {
                continue;
            }
            $label = Str::of($key)->replace('_', ' ')->ucfirst()->toString();
            $rows .= '<tr>'
                .'<td class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 whitespace-nowrap align-top">'.e($label)
                .'<div class="font-mono text-[11px] font-normal text-gray-400">'.e($key).'</div></td>'
                .($showBefore ? '<td class="px-3 py-2 text-sm text-red-700 dark:text-red-300 align-top break-all">'.e(static::displayValue($old[$key] ?? null)).'</td>' : '')
                .($showAfter ? '<td class="px-3 py-2 text-sm text-green-700 dark:text-green-300 align-top break-all">'.e(static::displayValue($new[$key] ?? null)).'</td>' : '')
                .'</tr>';
        }

        return new HtmlString('<div class="overflow-auto rounded-lg border border-gray-200 dark:border-gray-700">'
            .'<table class="w-full text-left">'
            .'<thead class="bg-gray-50 dark:bg-gray-800"><tr>'
            .'<th class="px-3 py-2 text-xs font-semibold text-gray-600 dark:text-gray-300">Field</th>'
            .($showBefore ? '<th class="px-3 py-2 text-xs font-semibold text-red-700 dark:text-red-300">Before</th>' : '')
            .($showAfter ? '<th class="px-3 py-2 text-xs font-semibold text-green-700 dark:text-green-300">'.($showBefore ? 'After' : 'Value').'</th>' : '')
            .'</tr></thead>'
            .'<tbody class="divide-y divide-gray-100 dark:divide-gray-800">'.$rows.'</tbody>'
            .'</table></div>');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view'  => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
