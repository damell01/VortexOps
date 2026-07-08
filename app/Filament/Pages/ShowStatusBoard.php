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
            'mapping'          => ['label' => 'AI Mapping',        'color' => '#7c3aed', 'icon' => '✨'],
            'pending_approval' => ['label' => 'Pending Approval',  'color' => '#0284c7', 'icon' => '📋'],
            'reconciled'       => ['label' => 'Reconciled',        'color' => '#059669', 'icon' => '✅'],
        ];

        $shows = Show::with(['streamers', 'latestDeductionRequest'])
            ->whereIn('status', array_keys($statuses))
            ->orderBy('show_date', 'desc')
            ->get();

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
