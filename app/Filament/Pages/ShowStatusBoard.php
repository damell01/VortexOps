<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Support\AdminModules;
use Filament\Pages\Page;

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

    public function getTitle(): string
    {
        return 'Show Pipeline';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() && AdminModules::isEnabled('streams');
    }

    public static function getNavigationGroup(): ?string
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public function getColumns(): array
    {
        $statuses = [
            'pending_review'   => ['label' => 'Pending Review',   'color' => '#d97706', 'icon' => '⏳'],
            'mapping'          => ['label' => 'Mapping',           'color' => '#7c3aed', 'icon' => '📦'],
            'pending_approval' => ['label' => 'Pending Approval',  'color' => '#0284c7', 'icon' => '📋'],
            'reconciled'       => ['label' => 'Reconciled',        'color' => '#059669', 'icon' => '✅'],
        ];

        $shows = Show::with(['streamers', 'latestDeductionRequest'])
            ->whereIn('status', array_keys($statuses))
            ->inChannelContext()
            ->orderBy('show_date', 'desc')
            ->get();

        $this->attachAging($shows);

        $columns = [];
        foreach ($statuses as $status => $meta) {
            $columns[] = [
                'status' => $status,
                'label'  => $meta['label'],
                'color'  => $meta['color'],
                'icon'   => $meta['icon'],
                'shows'  => $shows->where('status', $status)->values(),
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
}
