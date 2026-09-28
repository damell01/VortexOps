<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Services\NoShowCleanupService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class NoShowCleanup extends Page
{
    protected static ?string $title = 'No-Show Cleanup';
    protected static ?string $navigationLabel = 'No-Show Cleanup';
    protected static ?string $slug = 'no-show-cleanup';
    protected string $view = 'filament.pages.no-show-cleanup';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-trash';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Super Admin';
    }

    public static function getNavigationSort(): ?int
    {
        return 22;
    }

    private function service(): NoShowCleanupService
    {
        return app(NoShowCleanupService::class);
    }

    public function getCandidates(): \Illuminate\Support\Collection
    {
        $service = $this->service();

        return $service->candidates()->get()->map(fn (Show $show) => [
            'show' => $show,
            'blocking' => $service->blockingReferences($show),
        ]);
    }

    public function showUrl(int $id): string
    {
        return ShowResource::getUrl('view', ['record' => $id]);
    }

    public function deleteShow(int $id): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $show = Show::findOrFail($id);

        try {
            $this->service()->delete($show);
            Notification::make()->title('Show permanently deleted')->success()->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title('Could not delete this show')->body($e->getMessage())->danger()->send();
        }
    }

    public function deleteAllClearAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('deleteAllClear')
            ->label('Delete All Clear Ones')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Permanently delete every confirmed no-show with no related records?')
            ->modalDescription('This cannot be undone. Shows that still have an order, payout, shipment, deduction, surcharge, streamer report, or fulfillment package attached are left alone and listed below so you can review them.')
            ->modalSubmitActionLabel('Delete them')
            ->action(function (): void {
                abort_unless(auth()->user()?->isSuperAdmin(), 403);

                $result = $this->service()->deleteAllClear();

                if ($result['skipped'] === []) {
                    Notification::make()
                        ->title("{$result['deleted']} show(s) permanently deleted")
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("{$result['deleted']} deleted · ".count($result['skipped']).' skipped')
                    ->body('Some confirmed no-shows still have related records attached and were left alone — review them individually below.')
                    ->warning()
                    ->send();
            });
    }
}
