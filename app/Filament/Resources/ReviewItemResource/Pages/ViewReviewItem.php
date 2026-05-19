<?php

namespace App\Filament\Resources\ReviewItemResource\Pages;

use App\Filament\Resources\ReviewItemResource;
use App\Models\ReviewItem;
use App\Models\ReviewItemComment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

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

    public function infolist(Schema $schema): Schema
    {
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        return $schema->components([
            Section::make('Details')
                ->columns(2)
                ->schema(array_filter([
                    Placeholder::make('session_title')
                        ->label('Session')
                        ->content(fn (ReviewItem $record): string => $record->session?->title ?? '—'),

                    Placeholder::make('page_title_display')
                        ->label('Page')
                        ->content(fn (ReviewItem $record): string => $record->page_title ?? '—'),

                    Placeholder::make('type_label')
                        ->label('Type')
                        ->content(fn (ReviewItem $record): string => ReviewItem::typeLabels()[$record->type] ?? $record->type),

                    Placeholder::make('priority_label')
                        ->label('Priority')
                        ->content(fn (ReviewItem $record): string => ReviewItem::priorityLabels()[$record->priority] ?? $record->priority),

                    Placeholder::make('status_label')
                        ->label('Status')
                        ->content(fn (ReviewItem $record): string => ReviewItem::statusLabels()[$record->status] ?? $record->status),

                    $isSuperAdmin
                        ? Placeholder::make('reporter_name')
                            ->label('Reporter')
                            ->content(fn (ReviewItem $record): string => $record->createdBy?->name ?? '—')
                        : null,

                    $isSuperAdmin
                        ? Placeholder::make('assigned_to_name')
                            ->label('Assigned To')
                            ->content(fn (ReviewItem $record): string => $record->assignedTo?->name ?? 'Unassigned')
                        : null,

                    Placeholder::make('submitted_at')
                        ->label('Submitted')
                        ->content(fn (ReviewItem $record): string => $record->created_at->format('M j, Y g:i A')),
                ])),

            Section::make('Comment')
                ->schema([
                    Placeholder::make('comment_text')
                        ->label('')
                        ->content(fn (ReviewItem $record): string => $record->comment ?: 'No comment provided.')
                        ->columnSpanFull(),
                ]),

            Section::make('Annotation')
                ->collapsed()
                ->visible(fn (): bool => ! empty($this->record->screenshot))
                ->schema([
                    Placeholder::make('screenshot_img')
                        ->label('')
                        ->content(fn (ReviewItem $record): HtmlString =>
                            $record->screenshot
                                ? new HtmlString('<img src="' . e($record->screenshot) . '" class="block w-full rounded-lg" style="object-fit:contain;max-height:500px" loading="lazy">')
                                : new HtmlString('<span class="text-sm text-gray-400">No screenshot.</span>')
                        )
                        ->columnSpanFull(),
                ]),

            Section::make('Thread')
                ->schema([
                    Placeholder::make('comments_thread')
                        ->label('')
                        ->content(fn (ReviewItem $record): HtmlString => static::renderCommentThread($record))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function renderCommentThread(ReviewItem $record): HtmlString
    {
        if ($record->comments->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-400 py-2">No comments yet.</p>');
        }

        $html = '<div class="space-y-3">';
        foreach ($record->comments as $comment) {
            $name = e($comment->user?->name ?? 'Unknown');
            $body = nl2br(e($comment->body));
            $time = $comment->created_at->diffForHumans();
            $html .= '<div class="rounded-xl bg-gray-50 px-4 py-3">'
                   . '<div class="flex items-center justify-between mb-1">'
                   . '<span class="text-xs font-semibold text-gray-700">' . $name . '</span>'
                   . '<span class="text-xs text-gray-400">' . $time . '</span>'
                   . '</div>'
                   . '<p class="text-sm text-gray-700">' . $body . '</p>'
                   . '</div>';
        }
        $html .= '</div>';

        return new HtmlString($html);
    }
}
