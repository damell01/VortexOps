<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Support\AdminModules;
use Filament\Pages\Page;
use App\Support\NavVisibility;

class ShowStatusBoard extends Page
{
    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-view-columns';
    }

    public static function getNavigationLabel(): string
    {
        return 'Status Board';
    }

    public function getView(): string
    {
        return 'filament.pages.show-status-board';
    }

    public function getSubheading(): ?string
    {
        return 'Every show moving through the pipeline — Pending Review → Mapping → Pending Approval → Reconciled — with how long each has sat there.';
    }

    public function getTitle(): string
    {
        return 'Show Pipeline';
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

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('streams');
    }

    public static function getNavigationGroup(): ?string
    {
        return AdminModules::navigationGroupFor('streams');
    }

    /**
     * "Reconciled" is a terminal status — every show that's ever finished
     * lands there and stays forever, so it grows without bound while the
     * other (active/in-progress) columns naturally stay small. A live
     * pipeline board should show recent completions, not the full archive —
     * anything older belongs in the Shows list, not this view.
     */
    private const RECONCILED_WINDOW_DAYS = 14;

    public function getColumns(): array
    {
        $statuses = [
            'pending_review'   => ['label' => 'Pending Review',   'color' => '#d97706', 'icon' => '⏳'],
            'mapping'          => ['label' => 'Mapping',           'color' => '#7c3aed', 'icon' => '📦'],
            'pending_approval' => ['label' => 'Pending Approval',  'color' => '#0284c7', 'icon' => '📋'],
            'reconciled'       => ['label' => 'Reconciled',        'color' => '#059669', 'icon' => '✅'],
        ];

        $reconciledCutoff = now()->subDays(self::RECONCILED_WINDOW_DAYS);

        $shows = Show::with(['streamers', 'latestDeductionRequest'])
            ->whereIn('status', array_keys($statuses))
            ->where(fn ($q) => $q
                ->where('status', '!=', 'reconciled')
                ->orWhere('status_changed_at', '>=', $reconciledCutoff)
                ->orWhereNull('status_changed_at'))
            ->inChannelContext()
            ->orderBy('show_date', 'desc')
            ->get();

        $this->attachAging($shows);

        $reconciledTotal = Show::where('status', 'reconciled')->inChannelContext()->count();

        $columns = [];
        foreach ($statuses as $status => $meta) {
            $columnShows = $shows->where('status', $status)->values();

            $columns[] = [
                'status'        => $status,
                'label'         => $meta['label'],
                'color'         => $meta['color'],
                'icon'          => $meta['icon'],
                'shows'         => $columnShows,
                'hiddenOlder'   => $status === 'reconciled' ? max(0, $reconciledTotal - $columnShows->count()) : 0,
                'windowDays'    => $status === 'reconciled' ? self::RECONCILED_WINDOW_DAYS : null,
            ];
        }

        return $columns;
    }

    /**
     * Attach `days_in_status` to each show: how long it has sat in its current
     * status, from the status_changed_at stamp the model maintains (falling back
     * to updated_at, then created_at, for rows predating that column).
     *
     * @param  \Illuminate\Support\Collection<int, Show>  $shows
     */
    protected function attachAging($shows): void
    {
        foreach ($shows as $show) {
            $entered = $show->status_changed_at ?? $show->updated_at ?? $show->created_at;
            $show->setAttribute('entered_status_at', $entered);
            $show->setAttribute('days_in_status', $entered ? (int) $entered->diffInDays(now()) : null);
        }
    }

    public function getShowUrl(Show $show): string
    {
        return ShowResource::getUrl('view', ['record' => $show]);
    }

    public function getApprovalUrl(Show $show): string
    {
        return DeductionRequestResource::getUrl('index', [
            'tableFilters[show_id][value]' => $show->id,
        ]);
    }

    public function getReconciledShowsUrl(): string
    {
        return ShowResource::getUrl('index', [
            'tableFilters[status][values][0]' => 'reconciled',
        ]);
    }
}
