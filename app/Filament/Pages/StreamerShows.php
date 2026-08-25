<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\RespectsRoleVisibility;
use App\Models\Show;
use App\Models\Streamer;
use App\Support\NavVisibility;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * A streamer's front door.
 *
 * The End of Stream report was already a three-step wizard; what was missing
 * was the thing that leads into it. A streamer had no page that answered
 * "what needs me?" — they had to know a show existed, find it, and work out
 * from its status whether they were waiting on someone or someone was
 * waiting on them.
 *
 * So this sorts by who is blocked rather than by date. Anything needing the
 * streamer comes first, whatever night it was; everything else is context.
 */
class StreamerShows extends Page
{
    use RespectsRoleVisibility;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';
    protected static ?string $navigationLabel = 'My Shows';
    protected static ?int $navigationSort = 10;

    /** Per-request memo — the view reads each group once but renders repeat. */
    private ?array $groupsMemo = null;

    public function getTitle(): string
    {
        return 'My Shows';
    }

    public function getSubheading(): ?string
    {
        return 'Everything waiting on you, then everything else.';
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

        // Whether the link appears is a different question from whether the
        // page may be opened, and only the first one is about having shows.
        // An admin reviewing everybody's work uses the Shows resource; this
        // page answers "what needs me?", which is a streamer's question. A
        // role that was handed this page explicitly still gets the link.
        return (bool) auth()->user()?->streamer
            || \App\Support\NavVisibility::isExplicitlyGrantedTo(static::class, auth()->user());
    }

    // canAccess() is deliberately left to RespectsRoleVisibility. Requiring a
    // streamer record here would override an explicit grant on the Roles &
    // Permissions screen, which is the one thing that screen must always win.

    /**
     * These pages show one person's work, and the app's other streamer pages
     * all refuse outright rather than render an empty shell to someone who
     * has none. The check is here rather than in canAccess() because that is
     * the Roles & Permissions answer, and a grant on that screen has to keep
     * winning — this only says there is nothing here for you.
     */
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

        $count = count((new static())->groups()['needs_you']);

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Shows split by who is blocked.
     *
     * @return array{needs_you: array, waiting: array, upcoming: array, done: array}
     */
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
            ->withCount('shipments')
            ->orderByDesc('show_date')
            ->orderByDesc('start_time')
            ->limit(60)
            ->get();

        $groups = ['needs_you' => [], 'waiting' => [], 'upcoming' => [], 'done' => []];

        foreach ($shows as $show) {
            $groups[$this->bucketFor($show)][] = $this->cardFor($show);
        }

        // Upcoming reads forwards — the next one you are running is the one
        // you care about, not the furthest away.
        $groups['upcoming'] = array_reverse($groups['upcoming']);

        return $this->groupsMemo = $groups;
    }

    private function bucketFor(Show $show): string
    {
        if ($show->show_date && $show->show_date->isFuture()) {
            return 'upcoming';
        }

        $entry = $show->streamerLogEntry;

        // Nothing filed for a show that has already run is the commonest
        // reason a payout stalls, so it is the loudest thing on the page.
        if (! $entry || ! $entry->isSubmitted()) {
            return 'needs_you';
        }

        if ($entry->status === 'changes_requested') {
            return 'needs_you';
        }

        return $entry->status === 'admin_approved' ? 'done' : 'waiting';
    }

    /**
     * One show, reduced to what a streamer needs to decide what to do next.
     */
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
                => ['No report yet', 'warning', 'Add items'],
            $isDraft
                => ['Draft saved', 'warning', 'Finish report'],
            $entry->status === 'admin_approved'
                => ['Approved', 'success', 'View report'],
            default
                => ['Waiting on review', 'info', 'View report'],
        };

        return [
            'id'        => $show->id,
            'title'     => $show->title ?? 'Show #' . $show->id,
            'date'      => $show->show_date?->format('D, M j'),
            'time'      => $show->start_time,
            'channel'   => $show->channel?->name,
            'shipments' => (int) ($show->shipments_count ?? 0),
            'state'     => $state,
            'tone'      => $tone,
            'action'    => $action,
            'url'       => $action
                ? EndOfStreamForm::getUrl(['showId' => $show->id])
                : null,
            'slow_pack' => (bool) $show->is_slow_pack,
        ];
    }
}
