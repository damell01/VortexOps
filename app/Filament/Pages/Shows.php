<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShipmentResource;
use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Models\Streamer;
use App\Support\AdminModules;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Computed;

class Shows extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;

    protected static string $moduleSlug = 'streams';
    protected static ?string $title = 'Shows';

    public string $filterStatus = 'all';
    public string $filterTimeframe = 'all';
    public string $filterStreamer = '';
    public string $searchQuery = '';
    public string $sortBy = 'date';

    public function getSubheading(): ?string
    {
        return 'Whatnot shows, streamer assignments, analytics, orders, shipments, and end-of-stream workflow in one place.';
    }

    public function getView(): string
    {
        return 'filament.pages.shows';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-presentation-chart-line';
    }

    public static function getNavigationGroup(): ?string
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

    public static function getNavigationLabel(): string
    {
        return 'Shows';
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'shows-overview';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('show_shipments')
                ->label('Show Shipments')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->url(fn () => ShowShipments::getUrl()),

            Action::make('create_show')
                ->label('Add Show Manually')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                ->url(fn () => ShowResource::getUrl('create')),
        ];
    }

    public static function canAccess(): bool
    {
        // An explicit grant on Roles & Permissions is the answer; the rules
        // below are the fallback for roles that have no explicit list.
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();
        return AdminModules::isEnabled('streams')
            && ($user?->isAdmin() || $user?->isStreamer());
    }

    #[Computed]
    public function streamers(): Collection
    {
        $user = auth()->user();

        if ($user?->isAdmin()) {
            return Streamer::orderBy('name')->get();
        }

        return $user?->streamer ? collect([$user->streamer]) : collect();
    }

    #[Computed]
    public function shows(): Collection
    {
        $user = auth()->user();
        $query = Show::query()->inChannelContext();

        if ($this->filterStatus !== 'all') {
            $query->where('status', $this->filterStatus);
        }

        match ($this->filterTimeframe) {
            'upcoming' => $query->whereDate('show_date', '>', today()),
            'past' => $query->whereDate('show_date', '<=', today()),
            'attention' => $query
                ->whereDate('show_date', '<=', today())
                ->whereDoesntHave('streamerLogEntry')
                ->whereNotIn('status', ['closed', 'cancelled']),
            default => null,
        };

        if ($this->filterStreamer && $user?->isAdmin()) {
            $query->whereHas('streamers', fn ($q) => $q->where('streamers.id', $this->filterStreamer));
        } elseif ($user?->isStreamer()) {
            $query->whereHas('streamers', fn ($q) => $q->where('streamers.id', $user->streamer->id));
        }

        if ($this->searchQuery) {
            $needle = trim($this->searchQuery);
            $query->where(function ($q) use ($needle) {
                $q->where('title', 'like', "%{$needle}%")
                    ->orWhere('notes', 'like', "%{$needle}%")
                    ->orWhere('whatnot_show_id', 'like', "%{$needle}%");
            });
        }

        if ($this->sortBy === 'revenue') {
            $query->orderByDesc('gross_revenue')->orderByDesc('show_date');
        } elseif ($this->sortBy === 'oldest') {
            $query->orderBy('show_date')->orderBy('start_time');
        } else {
            $query->orderByDesc('show_date')->orderByDesc('start_time');
        }

        return $query
            ->with(['streamers', 'streamerLogEntry'])
            ->withCount(['orders', 'shipments'])
            ->withCount([
                'shipments as pending_shipments_count' => fn ($q) => $q
                    ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
            ])
            ->limit(250)
            ->get();
    }

    public function clearFilters(): void
    {
        $this->filterStatus = 'all';
        $this->filterTimeframe = 'all';
        $this->filterStreamer = '';
        $this->searchQuery = '';
        $this->sortBy = 'date';
    }

    public function showUrl(int $showId): string
    {
        return ShowResource::getUrl('view', ['record' => $showId]);
    }

    public function editUrl(int $showId): string
    {
        return ShowResource::getUrl('edit', ['record' => $showId]);
    }

    public function shipmentsUrl(int $showId): string
    {
        return ShipmentResource::getUrl('index', [
            'tableFilters[show_id][value]' => $showId,
        ]);
    }

    public function isShowDue(Show $show): bool
    {
        if ($show->show_date?->isFuture()) {
            return false;
        }

        if ($show->start_time && $show->start_time->isFuture()) {
            return false;
        }

        return true;
    }

    public function requestFormSubmission($showId): void
    {
        $show = Show::with('streamers.user')->findOrFail((int) $showId);

        if (! $this->isShowDue($show)) {
            Notification::make()
                ->title('Show has not happened yet')
                ->body('Submission requests become available once the scheduled show is due.')
                ->warning()
                ->send();
            return;
        }

        foreach ($show->streamers as $streamer) {
            if ($streamer->user) {
                Notification::make()
                    ->title('Form Submission Requested')
                    ->body("Admin is requesting you submit the end-of-stream form for \"{$show->title}\"")
                    ->info()
                    ->sendToDatabase($streamer->user);
            }
        }

        Notification::make()
            ->title('Submission request sent')
            ->body('Notification sent to ' . $show->streamers->count() . ' streamer(s)')
            ->success()
            ->send();

        unset($this->shows);
    }

    public function requestFormResubmission($showId): void
    {
        $show = Show::findOrFail((int) $showId);
        $logEntry = $show->streamerLogEntry;

        if (! $logEntry) {
            Notification::make()->title('Error')->body('No log entry found for this show')->danger()->send();
            return;
        }

        $logEntry->update([
            'status' => 'changes_requested',
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'locked_at' => null,
        ]);
        $logEntry->rejectByAdmin('Admin requested changes to your submission.');

        Notification::make()
            ->title('Change request sent')
            ->body('Notification sent to ' . $show->streamers()->count() . ' streamer(s)')
            ->success()
            ->send();

        unset($this->shows);
    }

    /**
     * Explicit admin cleanup for duplicate/test rows. This is deliberately
     * separate from ShowResource's conservative normal delete policy.
     */
    public function deleteShow(int $showId): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $show = Show::findOrFail($showId);

        DB::transaction(function () use ($show): void {
            foreach ([
                'shipments',
                'whatnot_show_orders',
                'show_ingestion_logs',
                'show_change_logs',
                'deduction_requests',
                'payouts',
                'shipping_surcharges',
                'show_reopening_requests',
                'streamer_log_entries',
            ] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'show_id')) {
                    DB::table($table)->where('show_id', $show->id)->delete();
                }
            }

            foreach (['show_streamer', 'show_fulfillment_user'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'show_id')) {
                    DB::table($table)->where('show_id', $show->id)->delete();
                }
            }

            $show->delete();
        });

        Notification::make()->title('Show deleted')->body('The show and its related imported data were removed.')->success()->send();
        unset($this->shows);
    }
}
