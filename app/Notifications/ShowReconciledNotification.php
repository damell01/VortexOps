<?php

namespace App\Notifications;

use App\Models\Show;
use Illuminate\Notifications\Notification;

class ShowReconciledNotification extends Notification
{
    public function __construct(public readonly Show $show) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title('Show Reconciled')
            ->body("Show \"{$this->show->title}\" has been reconciled and inventory deductions processed.")
            ->icon('heroicon-o-check-circle')
            ->success()
            ->actions([
                \Filament\Actions\Action::make('open')
                    ->label('Open show')
                    ->url(\App\Support\NotificationLinks::forShow($this->show->id, $notifiable))
                    ->button()
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
