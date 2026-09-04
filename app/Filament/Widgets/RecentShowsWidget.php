<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Support\AdminModules;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentShowsWidget extends BaseWidget
{
    protected static bool $isLazy = true;
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Recent Shows';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return AdminModules::isEnabled('streams');
    }

    /** Streamer id to scope by, or null for admins/owner (who see all shows). */
    private function streamerScopeId(): ?int
    {
        $user = auth()->user();

        if ($user && $user->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            return $user->streamer?->id ?? 0;
        }

        return null;
    }

    public function table(Table $table): Table
    {
        $query = Show::query()->with('channel')->inChannelContext();

        $streamerId = $this->streamerScopeId();
        if ($streamerId !== null) {
            $query->whereHas('streamers', fn ($s) => $s->where('streamers.id', $streamerId));
        }

        return $table
            ->query($query->orderByDesc('show_date')->limit(10))
            ->columns([
                TextColumn::make('show_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Show::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'draft'            => 'gray',
                        'pending_review'   => 'warning',
                        'mapping'          => 'info',
                        'pending_approval' => 'warning',
                        'reconciled'       => 'success',
                        'closed'           => 'gray',
                        'cancelled'        => 'danger',
                        default            => 'gray',
                    }),
                TextColumn::make('whatnot_net')
                    ->label('Estimated Net Earnings')
                    ->description('Whatnot Total Estimated Earnings')
                    ->money('USD')
                    ->placeholder('—'),
                TextColumn::make('units_sold')
                    ->label('Units')
                    ->numeric()
                    ->placeholder('—'),
            ])
            ->deferLoading()
            ->recordUrl(fn ($record) => ShowResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}
