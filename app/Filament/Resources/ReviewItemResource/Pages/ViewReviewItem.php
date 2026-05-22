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
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ViewReviewItem extends EditRecord
{
    protected static string $resource = ReviewItemResource::class;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function authorizeAccess(): void
    {
        static::authorizeResourceAccess();

        abort_unless(
            static::getResource()::canView($this->getRecord()),
            403
        );
    }

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
                });

            $actions[] = Action::make('mark_fixed')
                ->label('Fixed')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['open', 'in_progress'], true))
                ->action(function (): void {
                    $this->record->update(['status' => 'fixed']);
                    Notification::make()->title('Marked as fixed')->success()->send();
                });

            $actions[] = Action::make('mark_approved')
                ->label('Approve')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['open', 'fixed'], true))
                ->action(function (): void {
                    $this->record->update(['status' => 'approved']);
                    Notification::make()->title('Approved')->success()->send();
                });

            $actions[] = Action::make('mark_wont_fix')
                ->label("Won't Fix")
                ->icon('heroicon-o-no-symbol')
                ->color('gray')
                ->visible(fn () => ! in_array($this->record->status, ['approved', 'wont_fix'], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['status' => 'wont_fix']);
                    Notification::make()->title("Marked as won't fix")->success()->send();
                });

            $actions[] = Action::make('mark_rejected')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => ! in_array($this->record->status, ['approved', 'rejected', 'wont_fix'], true))
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update(['status' => 'rejected']);
                    Notification::make()->title('Rejected')->warning()->send();
                });

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
                    ->placeholder('Leave a note, update, or question...'),
            ])
            ->action(function (array $data): void {
                ReviewItemComment::create([
                    'review_item_id' => $this->record->id,
                    'user_id' => Auth::id(),
                    'body' => $data['body'],
                ]);

                $this->record->refresh();
                Notification::make()->title('Comment added')->success()->send();
            });

        return $actions;
    }

    public function form(Schema $schema): Schema
    {
        $record = $this->record;
        $isSuperAdmin = auth()->user()?->isSuperAdmin() ?? false;

        return $schema->components([
            Section::make('Review Brief')
                ->schema([
                    Placeholder::make('review_brief')
                        ->label('')
                        ->content(new HtmlString($this->renderReviewBrief($record, $isSuperAdmin))),
                ]),

            Section::make('Comment')
                ->schema([
                    Placeholder::make('comment_text')
                        ->label('')
                        ->content(new HtmlString(
                            '<div style="border:1px solid #e2e8f0;background:#f8fafc;border-radius:20px;padding:18px 20px;font-size:14px;line-height:1.8;color:#334155;">'
                            . nl2br(e($record->comment ?: 'No comment provided.'))
                            . '</div>'
                        )),
                ]),

            Section::make('Annotated Screenshot')
                ->visible(! empty($record->screenshot))
                ->schema([
                    Placeholder::make('screenshot_img')
                        ->label('')
                        ->content(
                            $record->screenshot
                                ? new HtmlString(
                                    '<div style="display:flex;flex-direction:column;gap:12px;">'
                                    . '<img src="' . e($record->screenshot) . '" style="display:block;width:100%;max-height:620px;object-fit:contain;border-radius:22px;border:1px solid #e2e8f0;background:#f8fafc;box-shadow:0 12px 30px rgba(15,23,42,0.06);" loading="lazy">'
                                    . '<div style="font-size:11px;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:#94a3b8;">Saved visual context from the annotated page section.</div>'
                                    . '</div>'
                                )
                                : new HtmlString('<span style="font-size:14px;color:#94a3b8;">No screenshot.</span>')
                        ),
                ]),

            Section::make('Thread')
                ->schema([
                    Placeholder::make('comments_thread')
                        ->label('')
                        ->content($this->renderThread($record)),
                ]),
        ]);
    }

    private function renderReviewBrief(ReviewItem $record, bool $isSuperAdmin): string
    {
        $type = ReviewItem::typeLabels()[$record->type] ?? ucfirst((string) $record->type);
        $priority = ReviewItem::priorityLabels()[$record->priority] ?? ucfirst((string) $record->priority);
        $status = ReviewItem::statusLabels()[$record->status] ?? ucfirst((string) $record->status);

        $typeClass = match ($record->type) {
            'bug' => 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;',
            'suggestion' => 'background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;',
            'question' => 'background:#fffbeb;color:#b45309;border:1px solid #fde68a;',
            default => 'background:#f5f3ff;color:#6d28d9;border:1px solid #ddd6fe;',
        };

        $priorityClass = match ($record->priority) {
            'high' => 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;',
            'low' => 'background:#f8fafc;color:#475569;border:1px solid #cbd5e1;',
            default => 'background:#fffbeb;color:#b45309;border:1px solid #fde68a;',
        };

        $statusClass = match ($record->status) {
            'open' => 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;',
            'in_progress' => 'background:#fff7ed;color:#c2410c;border:1px solid #fdba74;',
            'fixed', 'approved' => 'background:#ecfdf5;color:#047857;border:1px solid #a7f3d0;',
            default => 'background:#f8fafc;color:#475569;border:1px solid #cbd5e1;',
        };

        $meta = [
            ['label' => 'Session', 'value' => $record->session?->title ?? '—'],
            ['label' => 'Page', 'value' => $record->page_title ?: $record->page_url],
            ['label' => 'Submitted', 'value' => $record->created_at?->format('M j, Y g:i A') ?? '—'],
        ];

        if ($isSuperAdmin) {
            $meta[] = ['label' => 'Reporter', 'value' => $record->createdBy?->name ?? '—'];
            $meta[] = ['label' => 'Assigned', 'value' => $record->assignedTo?->name ?? 'Unassigned'];
        }

        $metaHtml = '';
        foreach ($meta as $row) {
            $metaHtml .= '<div style="padding:14px 16px;border-radius:18px;background:#f8fafc;border:1px solid #e2e8f0;">'
                . '<div style="font-size:11px;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:#94a3b8;">' . e($row['label']) . '</div>'
                . '<div style="margin-top:8px;font-size:14px;font-weight:600;color:#0f172a;line-height:1.6;">' . e($row['value']) . '</div>'
                . '</div>';
        }

        return '<div style="overflow:hidden;border-radius:26px;border:1px solid #dbeafe;background:linear-gradient(135deg,#0f172a 0%,#111827 55%,#312e81 100%);padding:24px;box-shadow:0 24px 60px rgba(15,23,42,0.12);">'
            . '<div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">'
            . '<div style="max-width:720px;">'
            . '<div style="font-size:11px;font-weight:700;letter-spacing:0.24em;text-transform:uppercase;color:#67e8f9;">Review Item</div>'
            . '<div style="margin-top:10px;font-size:28px;font-weight:700;line-height:1.15;color:#ffffff;">' . e($record->page_title ?: 'Ticket #' . $record->id) . '</div>'
            . '<div style="margin-top:12px;font-size:14px;line-height:1.7;color:#cbd5e1;">' . e($record->page_url) . '</div>'
            . '</div>'
            . '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
            . '<span style="border-radius:999px;padding:8px 12px;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;' . $statusClass . '">' . e($status) . '</span>'
            . '<span style="border-radius:999px;padding:8px 12px;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;' . $typeClass . '">' . e($type) . '</span>'
            . '<span style="border-radius:999px;padding:8px 12px;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;' . $priorityClass . '">' . e($priority) . '</span>'
            . '</div>'
            . '</div>'
            . '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:22px;">'
            . $metaHtml
            . '</div>'
            . '</div>';
    }

    private function renderThread(ReviewItem $record): HtmlString
    {
        $record->load('comments.user');

        if ($record->comments->isEmpty()) {
            return new HtmlString(
                '<div style="border:1px dashed #cbd5e1;border-radius:20px;padding:18px 20px;color:#94a3b8;font-size:14px;">No comments yet. Use Add Comment above.</div>'
            );
        }

        $html = '<div style="display:flex;flex-direction:column;gap:12px;">';

        foreach ($record->comments as $comment) {
            $name = e($comment->user?->name ?? 'Unknown');
            $body = nl2br(e($comment->body));
            $time = e($comment->created_at->diffForHumans());

            $html .= '<div style="border:1px solid #e2e8f0;background:#f8fafc;border-radius:20px;padding:16px 18px;">'
                . '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:8px;">'
                . '<span style="font-size:12px;font-weight:700;color:#0f172a;">' . $name . '</span>'
                . '<span style="font-size:11px;color:#94a3b8;">' . $time . '</span>'
                . '</div>'
                . '<div style="font-size:14px;line-height:1.8;color:#334155;">' . $body . '</div>'
                . '</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
    }
}
