<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;

class FulfillmentPreview extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Fulfillment Center Preview';

    public string $mode = 'admin';
    public string $filter = 'all';
    public string $query = '';
    public ?int $opened = null;
    public array $stages = [];
    public array $assignees = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return \App\Support\AdminModules::isEnabled('fulfillment') && ($user?->isAdmin() || $user?->isFulfillment());
    }

    public function getView(): string { return 'filament.pages.fulfillment-preview'; }
    public function getSubheading(): ?string { return 'Interactive workflow preview · sample data'; }

    public function isAdminView(): bool
    {
        return (bool) auth()->user()?->isAdmin() && $this->mode !== 'staff';
    }

    public function work(): array
    {
        $rows = [
            ['id'=>1, 'title'=>'Friday Night Breaks', 'channel'=>'Vortex Breaks', 'host'=>'Niko', 'packages'=>24, 'items'=>68, 'assigned'=>'Morgan', 'stage'=>'ready', 'priority'=>'Normal', 'location'=>'Main Storage · A-04'],
            ['id'=>2, 'title'=>'Collector Showcase', 'channel'=>'Vortex Collects', 'host'=>'Ashenway', 'packages'=>18, 'items'=>43, 'assigned'=>'Avery', 'stage'=>'picking', 'priority'=>'Due today', 'location'=>'Main Storage · B-12'],
            ['id'=>3, 'title'=>'Booster Box Night', 'channel'=>'Vortex Cards', 'host'=>'Luna', 'packages'=>32, 'items'=>81, 'assigned'=>'Avery', 'stage'=>'packing', 'priority'=>'Due today', 'location'=>'Main Storage · C-03'],
            ['id'=>4, 'title'=>'Weekend Card Deals', 'channel'=>'Vortex Shop', 'host'=>'B$', 'packages'=>12, 'items'=>29, 'assigned'=>'Unassigned', 'stage'=>'blocked', 'priority'=>'Missing stock', 'location'=>'Main Storage · A-08'],
        ];
        foreach ($rows as &$row) {
            $row['stage'] = $this->stages[$row['id']] ?? $row['stage'];
            $row['assigned'] = $this->assignees[$row['id']] ?? $row['assigned'];
        }
        unset($row);
        // Regular fulfillment staff never gain the admin preview through a crafted mode value.
        return $this->isAdminView() ? $rows : array_values(array_filter($rows, fn ($row) => $row['assigned'] === 'Avery'));
    }

    public function filteredWork(): array
    {
        return array_values(array_filter($this->work(), fn ($row) =>
            ($this->filter === 'all' || $row['stage'] === $this->filter)
            && ($this->query === '' || str_contains(mb_strtolower($row['title'].' '.$row['channel'].' '.$row['host']), mb_strtolower($this->query)))));
    }

    public function setMode(string $mode): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $this->mode = $mode === 'staff' ? 'staff' : 'admin';
        $this->opened = null; $this->filter = 'all';
    }

    public function openWork(int $id): void
    {
        abort_unless(collect($this->work())->contains('id', $id), 403);
        $this->opened = $id;
    }

    public function selectedWork(): ?array { return collect($this->work())->firstWhere('id', $this->opened); }

    public function assignDemo(int $id, string $name): void
    {
        abort_unless($this->isAdminView(), 403);
        abort_unless(in_array($name, ['Avery','Morgan','Unassigned'], true) && collect($this->work())->contains('id', $id), 422);
        $this->assignees[$id] = $name;
        Notification::make()->title('Preview assignment updated')->success()->send();
    }

    public function advanceDemo(int $id): void
    {
        $row = collect($this->work())->firstWhere('id', $id);
        abort_unless($row, 403);
        if ($row['stage'] === 'blocked') {
            Notification::make()->title('Resolve the stock exception before continuing')->warning()->send();
            return;
        }
        $this->stages[$id] = ['ready'=>'picking','picking'=>'packing','packing'=>'complete','complete'=>'complete'][$row['stage']] ?? 'ready';
        Notification::make()->title('Preview progress updated')->success()->send();
    }

    public function resetDemo(): void { $this->stages = []; $this->assignees = []; $this->opened = null; }
}
