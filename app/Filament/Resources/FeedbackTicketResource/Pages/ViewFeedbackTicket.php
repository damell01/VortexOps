<?php

namespace App\Filament\Resources\FeedbackTicketResource\Pages;

use App\Filament\Resources\FeedbackTicketResource;
use App\Models\FeedbackTicket;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ViewFeedbackTicket extends EditRecord
{
    protected static string $resource = FeedbackTicketResource::class;

    public function form(Schema $schema): Schema
    {
        /** @var FeedbackTicket $ticket */
        $ticket = $this->record;

        return $schema->components([
            Section::make('Screenshot')->schema([
                Placeholder::make('screenshot_preview')
                    ->label('')
                    ->columnSpanFull()
                    ->content(function () use ($ticket): \Illuminate\Support\HtmlString {
                        if (! $ticket->screenshot_path) {
                            return new \Illuminate\Support\HtmlString(
                                '<p class="text-sm text-gray-400 italic">No screenshot was captured with this ticket.</p>'
                            );
                        }

                        $url = Storage::disk('public')->url($ticket->screenshot_path);

                        return new \Illuminate\Support\HtmlString(
                            '<div class="rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">'
                            . '<img src="' . e($url) . '" alt="Feedback screenshot" class="w-full block">'
                            . '</div>'
                        );
                    }),
            ]),

            Section::make('Submission Details')->columns(3)->schema([
                Placeholder::make('submitted_name_display')
                    ->label('Submitted By')
                    ->content($ticket->submitted_name ?? $ticket->submitter?->name ?? '—'),

                Placeholder::make('submitted_email_display')
                    ->label('Email')
                    ->content($ticket->submitted_email ?? '—'),

                Placeholder::make('created_at_display')
                    ->label('Submitted At')
                    ->content($ticket->created_at?->format('M j, Y g:i a') ?? '—'),

                Placeholder::make('status_display')
                    ->label('Status')
                    ->content(FeedbackTicket::statusLabels()[$ticket->status] ?? $ticket->status),

                Placeholder::make('priority_display')
                    ->label('Priority')
                    ->content(FeedbackTicket::priorityLabels()[$ticket->priority] ?? $ticket->priority),

                Placeholder::make('page_url_display')
                    ->label('Page URL')
                    ->content(function () use ($ticket): \Illuminate\Support\HtmlString {
                        if (! $ticket->page_url) {
                            return new \Illuminate\Support\HtmlString('—');
                        }
                        return new \Illuminate\Support\HtmlString(
                            '<a href="' . e($ticket->page_url) . '" target="_blank" class="text-primary-600 dark:text-primary-400 hover:underline text-sm break-all">'
                            . e($ticket->page_url)
                            . '</a>'
                        );
                    }),
            ]),

            Section::make('Description')->schema([
                Placeholder::make('description_display')
                    ->label('')
                    ->columnSpanFull()
                    ->content(function () use ($ticket): \Illuminate\Support\HtmlString {
                        if (! $ticket->description) {
                            return new \Illuminate\Support\HtmlString('<p class="text-sm text-gray-400 italic">No description provided.</p>');
                        }
                        return new \Illuminate\Support\HtmlString(
                            '<p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">' . e($ticket->description) . '</p>'
                        );
                    }),
            ]),

            Section::make('Admin')->columns(2)->schema([
                Select::make('assigned_to')
                    ->label('Assigned To')
                    ->options(User::pluck('name', 'id'))
                    ->searchable()
                    ->placeholder('Unassigned')
                    ->nullable(),

                Select::make('priority')
                    ->label('Priority')
                    ->options(FeedbackTicket::priorityLabels())
                    ->required(),

                Textarea::make('admin_notes')
                    ->label('Admin Notes')
                    ->rows(3)
                    ->placeholder('Internal notes, follow-up actions…')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    protected function getFormActions(): array
    {
        return [
            parent::getSaveFormAction()->label('Save Notes'),
        ];
    }

    protected function getHeaderActions(): array
    {
        $ticket = $this->record;

        return [
            Action::make('mark_in_progress')
                ->label('Mark In Progress')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->visible(fn () => $ticket->status === 'open')
                ->action(function () use ($ticket) {
                    $ticket->update(['status' => 'in_progress']);
                    Notification::make()->title('Ticket marked as in progress.')->warning()->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('mark_resolved')
                ->label('Mark Resolved')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => in_array($ticket->status, ['open', 'in_progress']))
                ->requiresConfirmation()
                ->action(function () use ($ticket) {
                    $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);
                    Notification::make()->title('Ticket resolved.')->success()->send();
                    $this->refreshFormData(['status', 'resolved_at']);
                }),

            Action::make('close_ticket')
                ->label('Close')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn () => ! in_array($ticket->status, ['closed']))
                ->requiresConfirmation()
                ->modalDescription('Closing this ticket marks it as done with no further action needed.')
                ->action(function () use ($ticket) {
                    $ticket->update(['status' => 'closed']);
                    Notification::make()->title('Ticket closed.')->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('reopen')
                ->label('Re-open')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->visible(fn () => in_array($ticket->status, ['resolved', 'closed']))
                ->action(function () use ($ticket) {
                    $ticket->update(['status' => 'open', 'resolved_at' => null]);
                    Notification::make()->title('Ticket re-opened.')->info()->send();
                    $this->refreshFormData(['status', 'resolved_at']);
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return FeedbackTicketResource::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Ticket updated.';
    }
}
