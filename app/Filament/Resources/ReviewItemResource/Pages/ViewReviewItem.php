<?php

namespace App\Filament\Resources\ReviewItemResource\Pages;

use App\Filament\Resources\ReviewItemResource;
use App\Models\ReviewItem;
use App\Models\ReviewItemComment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Auth;

class ViewReviewItem extends ViewRecord
{
    protected static string $resource = ReviewItemResource::class;

    protected function getHeaderActions(): array
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        $actions = [];

        if ($isSuperAdmin) {
            $actions[] = Action::make('mark_in_progress')
                ->label('Start')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'open')
                ->action(function (): void {
                    $this->record->update(['status' => 'in_progress']);
                    Notification::make()->title('Marked in progress')->success()->send();
                    $this->refreshFormData(['status']);
                });

            $actions[] = Action::make('mark_fixed')
                ->label('Fixed')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['open', 'in_progress']))
                ->action(function (): void {
                    $this->record->update(['status' => 'fixed']);
                    Notification::make()->title('Marked as fixed')->success()->send();
                    $this->refreshFormData(['status']);
                });

            $actions[] = Action::make('mark_approved')
                ->label('Approve')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['open', 'fixed']))
                ->action(function (): void {
                    $this->record->update(['status' => 'approved']);
                    Notification::make()->title('Approved')->success()->send();
                    $this->refreshFormData(['status']);
                });

            $actions[] = Action::make('mark_wont_fix')
                ->label("Won't Fix")
                ->icon('heroicon-o-no-symbol')
                ->color('gray')
                ->visible(fn () => ! in_array($this->record->status, ['approved', 'wont_fix']))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['status' => 'wont_fix']);
                    Notification::make()->title("Marked as won't fix")->success()->send();
                    $this->refreshFormData(['status']);
                });

            $actions[] = Action::make('mark_rejected')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => ! in_array($this->record->status, ['approved', 'rejected', 'wont_fix']))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['status' => 'rejected']);
                    Notification::make()->title('Rejected')->warning()->send();
                    $this->refreshFormData(['status']);
                });

            $actions[] = Action::make('edit_item')
                ->label('Edit')
                ->icon('heroicon-o-pencil')
                ->url(fn () => ReviewItemResource::getUrl('edit', ['record' => $this->record]));

            $actions[] = DeleteAction::make()
                ->after(fn () => $this->redirect(ReviewItemResource::getUrl('index')));
        }

        $actions[] = Action::make('add_comment')
            ->label('Add Comment')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('gray')
            ->form([
                Textarea::make('body')
                    ->label('Comment')
                    ->required()
                    ->rows(3)
                    ->placeholder('Leave a note, update, or question…'),
            ])
            ->action(function (array $data): void {
                ReviewItemComment::create([
                    'review_item_id' => $this->record->id,
                    'user_id'        => Auth::id(),
                    'body'           => $data['body'],
                ]);

                $this->record->refresh();
                Notification::make()->title('Comment added')->success()->send();
            });

        return $actions;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        return $infolist->schema([
            \Filament\Infolists\Components\Section::make('Details')
                ->columns(2)
                ->schema(array_filter([
                    TextEntry::make('session.title')->label('Session'),
                    TextEntry::make('page_title')->label('Page')->default('—'),
                    TextEntry::make('type')
                        ->label('Type')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ReviewItem::typeLabels()[$state] ?? $state)
                        ->color(fn ($state) => match ($state) {
                            'bug'        => 'danger',
                            'suggestion' => 'info',
                            'question'   => 'warning',
                            default      => 'gray',
                        }),
                    TextEntry::make('priority')
                        ->label('Priority')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ReviewItem::priorityLabels()[$state] ?? $state)
                        ->color(fn ($state) => match ($state) {
                            'high'   => 'danger',
                            'normal' => 'warning',
                            'low'    => 'gray',
                            default  => 'gray',
                        }),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn ($state) => ReviewItem::statusLabels()[$state] ?? $state)
                        ->color(fn ($state) => match ($state) {
                            'open'        => 'danger',
                            'in_progress' => 'warning',
                            'fixed'       => 'success',
                            'approved'    => 'success',
                            'rejected'    => 'gray',
                            'wont_fix'    => 'gray',
                            default       => 'gray',
                        }),
                    $isSuperAdmin
                        ? TextEntry::make('createdBy.name')->label('Reporter')->default('—')
                        : null,
                    $isSuperAdmin
                        ? TextEntry::make('assignedTo.name')->label('Assigned To')->default('Unassigned')
                        : null,
                    TextEntry::make('created_at')->label('Submitted')->dateTime('M j, Y g:i A'),
                ])),

            \Filament\Infolists\Components\Section::make('Comment')
                ->schema([
                    TextEntry::make('comment')
                        ->label('')
                        ->default('No comment provided.')
                        ->columnSpanFull(),
                ]),

            \Filament\Infolists\Components\Section::make('Annotation')
                ->collapsed()
                ->visible(fn () => ! empty($this->record->screenshot))
                ->schema([
                    ImageEntry::make('screenshot')
                        ->label('')
                        ->width('100%')
                        ->height(400)
                        ->extraImgAttributes(['style' => 'object-fit:contain']),
                ]),

            \Filament\Infolists\Components\Section::make('Thread')
                ->schema([
                    \Filament\Infolists\Components\RepeatableEntry::make('comments')
                        ->label('')
                        ->schema([
                            TextEntry::make('user.name')
                                ->label('')
                                ->weight(\Filament\Support\Enums\FontWeight::Bold)
                                ->inline(),
                            TextEntry::make('created_at')
                                ->label('')
                                ->since()
                                ->color('gray')
                                ->inline(),
                            TextEntry::make('body')
                                ->label('')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ]),
        ]);
    }
}
