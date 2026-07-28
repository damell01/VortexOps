<?php

namespace App\Filament\Pages;

use App\AI\Services\AiUsageReport;
use App\AI\Services\LearningService;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Support\AdminModules;
use App\Support\NavVisibility;
use Filament\Pages\Page;

/**
 * Read-only observability for the AI stack: call volume, success rate, latency
 * by task and model, recent failures, and what the matcher has learned. Reads
 * the AiUsageReport / LearningService services — no AI logic here.
 */
class AiMonitoring extends Page
{
    use HasAdminNavVisibility;

    protected static ?string $title = 'AI Monitoring';

    public int $days = 7;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 11;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-chart-bar';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return AdminModules::isEnabled('ai')
            && ! NavVisibility::isHiddenForUser(static::class, $user)
            && (($user?->isAdmin() || $user?->isOwner()) ?? false);
    }

    // Owner-facing diagnostic; keep it out of admins' nav but URL-reachable.
    public static function shouldRegisterNavigation(): bool
    {
        return (auth()->user()?->isOwner() ?? false) && AdminModules::isEnabled('ai');
    }

    public function getView(): string
    {
        return 'filament.pages.ai-monitoring';
    }

    public function setDays(int $days): void
    {
        $this->days = in_array($days, [1, 7, 30], true) ? $days : 7;
    }

    public function getSummaryProperty(): array
    {
        return app(AiUsageReport::class)->summary($this->days);
    }

    public function getByTaskProperty(): array
    {
        return app(AiUsageReport::class)->byTask($this->days);
    }

    public function getByModelProperty(): array
    {
        return app(AiUsageReport::class)->byModel($this->days);
    }

    public function getRecentFailuresProperty(): array
    {
        return app(AiUsageReport::class)->recentFailures(10);
    }

    public function getLearningProperty(): array
    {
        return app(LearningService::class)->stats();
    }
}
