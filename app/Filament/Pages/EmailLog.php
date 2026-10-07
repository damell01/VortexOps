<?php

namespace App\Filament\Pages;

use App\Models\EmailLog as EmailLogModel;
use App\Support\NotificationCatalog;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Every email the app sent: when, to whom, what it said, and whether the mail
 * server took it. Click one to read it exactly as it went out.
 */
class EmailLog extends Page
{
    use \App\Filament\Concerns\HasAdminNavVisibility;
    use WithPagination;

    protected static ?string $title = 'Email Log';
    protected static ?string $slug = 'email-log';

    #[Url(as: 'q')] public string $search = '';
    #[Url(as: 'status')] public string $status = '';
    #[Url(as: 'event')] public string $event = '';
    #[Url(as: 'tests')] public bool $showTests = true;

    public ?int $openId = null;

    public static function getNavigationGroup(): string|\UnitEnum|null { return 'Settings'; }
    public static function getNavigationSort(): ?int { return 3; }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-envelope'; }
    public function getView(): string { return 'filament.pages.email-log'; }
    public function getSubheading(): ?string { return 'Every email VortexOps sent, and exactly what it said.'; }

    public static function canAccess(): bool
    {
        if (\App\Support\RoleAccess::grants(static::class)) return true;
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function updating($name): void
    {
        if (in_array($name, ['search', 'status', 'event', 'showTests'], true)) $this->resetPage();
    }

    public function logs()
    {
        $q = EmailLogModel::query()->latest('id');
        if (($s = trim($this->search)) !== '') $q->where(fn ($x) => $x->where('to_email', 'like', "%{$s}%")->orWhere('subject', 'like', "%{$s}%"));
        if ($this->status !== '') $q->where('status', $this->status);
        if ($this->event !== '') $q->where('event', $this->event);
        if (! $this->showTests) $q->where('is_test', false);

        return $q->select(['id', 'event', 'to_email', 'to_name', 'subject', 'status', 'is_test', 'error', 'created_at', 'sent_at'])->paginate(25);
    }

    public function stats(): array
    {
        $since = now()->subDay();
        return [
            'today' => EmailLogModel::where('created_at', '>=', $since)->where('is_test', false)->count(),
            'failed' => EmailLogModel::where('created_at', '>=', $since)->whereIn('status', ['failed', 'sending'])->count(),
            'total' => EmailLogModel::count(),
        ];
    }

    public function eventLabel(?string $event): string
    {
        return $event ? NotificationCatalog::label($event) : 'Other email';
    }

    public function open(int $id): void
    {
        $this->openId = $id;
    }

    public function close(): void
    {
        $this->openId = null;
    }

    public function opened(): ?EmailLogModel
    {
        return $this->openId ? EmailLogModel::with('user:id,name')->find($this->openId) : null;
    }
}
