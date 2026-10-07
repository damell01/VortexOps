<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RespectsRoleVisibility;
use App\Models\Show;
use App\Models\Streamer;
use App\Support\NavVisibility;
use Filament\Pages\Page;

class StreamerShows extends Page
{
    use RespectsRoleVisibility;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'My Shows';
    protected static ?int $navigationSort = 1;

    private ?array $groupsMemo = null;

    public function getTitle(): string
    {
        return 'My Shows';
    }

    public function getSubheading(): ?string
    {
        return 'Your shows, submitted reports and pay links in one place.';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Streamer';
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (NavVisibility::isHiddenForUser(static::class, auth()->user())) {
            return false;
        }

        return (bool) auth()->user()?->streamer
            || \App\Support\NavVisibility::isExplicitlyGrantedTo(static::class, auth()->user());
    }

    public function mount(): void
    {
        abort_unless((bool) auth()->user()?->streamer, 403);
    }

    public function getView(): string
    {
        return 'filament.pages.streamer-shows';
    }

    public function getStreamer(): Streamer
    {
        return auth()->user()?->streamer ?? abort(403);
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth()->user()?->streamer) {
            return null;
        }

        $streamerId = auth()->user()?->streamer?->id;
        if (! $streamerId) return null;

        // Navigation badges are evaluated while building navigation on every
        // request. Do not hydrate up to 60 shows + relations just to display a
        // number in the sidebar.
        $count = Show::query()
            ->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamerId))
            ->where('status', '!=', 'cancelled')
            ->whereDate('show_date', '<=', today())
            ->where(function ($q) {
                $q->whereDoesntHave('streamerLogEntry')
                    ->orWhereHas('streamerLogEntry', fn ($entry) => $entry
                        ->whereNull('submitted_at')
                        ->orWhere('status', 'changes_requested'));
            })
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function quickSummary(): array
    {
        $groups = $this->groups();

        return [
            'needs_you' => count($groups['needs_you']),
            'upcoming' => count($groups['upcoming']),
            'submitted' => count($groups['waiting']),
            'approved' => count($groups['done']),
        ];
    }

    public function groups(): array
    {
        if ($this->groupsMemo !== null) {
            return $this->groupsMemo;
        }

        $streamer = auth()->user()?->streamer;

        if (! $streamer) {
            return $this->groupsMemo = ['needs_you' => [], 'waiting' => [], 'upcoming' => [], 'done' => []];
        }

        $shows = Show::query()
            ->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamer->id))
            ->where('status', '!=', 'cancelled')
            ->with(['channel', 'streamerLogEntry'])
            ->orderByDesc('show_date')
            ->orderByDesc('start_time')
            ->limit(60)
            ->get();

        $groups = ['needs_you' => [], 'waiting' => [], 'upcoming' => [], 'done' => []];

        foreach ($shows as $show) {
            $groups[$this->bucketFor($show)][] = $this->cardFor($show);
        }

        $groups['upcoming'] = array_reverse($groups['upcoming']);

        return $this->groupsMemo = $groups;
    }

    private function bucketFor(Show $show): string
    {
        if ($show->show_date && $show->show_date->isFuture()) {
            return 'upcoming';
        }

        $entry = $show->streamerLogEntry;

        if (! $entry || ! $entry->isSubmitted()) {
            return 'needs_you';
        }

        if ($entry->status === 'changes_requested') {
            return 'needs_you';
        }

        return $entry->status === 'admin_approved' ? 'done' : 'waiting';
    }

    private function cardFor(Show $show): array
    {
        $entry   = $show->streamerLogEntry;
        $isDraft = $entry && ! $entry->isSubmitted();

        [$state, $tone, $action] = match (true) {
            $show->show_date && $show->show_date->isFuture()
                => ['Scheduled', 'gray', null],
            $entry && $entry->status === 'changes_requested'
                => ['Changes requested', 'danger', 'Review changes'],
            ! $entry
                => ['No report yet', 'warning', 'Log show'],
            $isDraft
                => ['Draft saved', 'warning', 'Continue report'],
            $entry->hasPendingRevisionRequest()
                => ['Changes asked for', 'info', 'View report'],
            $entry->status === 'admin_approved'
                => ['Approved', 'success', 'View report'],
            default
                => ['Submitted', 'info', 'View report'],
        };

        return [
            'id'        => $show->id,
            'title'     => $show->title ?? 'Show #' . $show->id,
            'date'      => $show->show_date?->format('D, M j'),
            'time'      => $show->start_time,
            'channel'   => $show->channel?->name,
            'state'     => $state,
            'tone'      => $tone,
            'action'    => $action,
            'url'       => $action
                ? EndOfStreamForm::getUrl(['showId' => $show->id])
                : null,
            'slow_pack' => (bool) $show->is_slow_pack,
            'can_request_revision' => (bool) $entry?->canRequestRevision(),
            'revision_requested'   => (bool) $entry?->hasPendingRevisionRequest(),
            'revision_reason'      => $entry?->revision_reason,
        ];
    }

    public ?int $revisionFor = null;
    public string $revisionReason = '';

    public function askForChanges(int $showId): void
    {
        $this->revisionFor    = $showId;
        $this->revisionReason = '';
    }

    public function cancelRevisionRequest(): void
    {
        $this->revisionFor    = null;
        $this->revisionReason = '';
    }

    public function submitRevisionRequest(): void
    {
        $show = $this->revisionFor
            ? Show::with('streamerLogEntry')->find($this->revisionFor)
            : null;

        $entry = $show?->streamerLogEntry;

        if (! $entry || ! $entry->canRequestRevision() || ! $this->ownsShow($show)) {
            $this->cancelRevisionRequest();
            return;
        }

        $entry->requestRevision($this->revisionReason);
        $this->notifyAdminsOfRevisionRequest($show, $entry);

        $this->groupsMemo = null;
        $this->cancelRevisionRequest();

        \Filament\Notifications\Notification::make()
            ->title('Asked for changes')
            ->body('An admin has been told. The report stays as filed until they reopen it.')
            ->success()
            ->send();
    }

    private function ownsShow(Show $show): bool
    {
        $streamerId = auth()->user()?->streamer?->id;
        return $streamerId !== null && $show->streamers->contains('id', $streamerId);
    }

    private function notifyAdminsOfRevisionRequest(Show $show, \App\Models\StreamerLogEntry $entry): void
    {
        \App\Services\Notifier::send('report_reopen_requested', new \App\Notifications\VortexAlert(
            event: 'report_reopen_requested',
            title: 'A streamer wants to change a filed report',
            body: trim(($show->title ?? 'Show #' . $show->id) . ' — ' . ($entry->revision_reason ?: 'no reason given')),
            tone: 'warning',
            links: ['Open the report' => \App\Filament\Resources\StreamerLogResource::getUrl('index')],
        ));
    }
}
