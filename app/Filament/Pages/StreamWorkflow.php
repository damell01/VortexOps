<?php

namespace App\Filament\Pages;

use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Support\AdminModules;
use Filament\Pages\Page;

class StreamWorkflow extends Page
{
    protected static string $moduleSlug = 'streams';
    protected static ?string $title = 'Stream Workflow';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    public function getView(): string
    {
        return 'filament.pages.stream-workflow';
    }

    public static function getNavigationGroup(): string | null
    {
        return 'Streams';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public function getSubheading(): ?string
    {
        return 'Overview of the stream-to-payout workflow and current status.';
    }

    public function getWorkflowStatsProperty(): array
    {
        return [
            'pending_end_of_stream' => Show::whereDoesntHave('streamerLogEntry')
                ->where('status', '!=', 'closed')
                ->count(),
            'pending_admin_review' => StreamerLogEntry::where('status', 'pending')->count(),
            'awaiting_streamer' => StreamerLogEntry::where('status', 'streamer_reviewed')
                ->where('status', '!=', 'admin_approved')
                ->count(),
            'approved' => StreamerLogEntry::where('status', 'admin_approved')->count(),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return AdminModules::isEnabled('streams') && ($user?->isAdmin() || $user?->isStreamer());
    }
}
