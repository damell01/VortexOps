<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasAdminNavVisibility;
use App\Models\AiInsight;
use App\Models\AiTask;
use App\Services\AI\Ops\AiOpsDispatcher;
use App\Support\NavVisibility;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Read-only AI operations workspace plus queue controls.
 *
 * Every property on this page is a normal database query. Opening/refreshing
 * this page never contacts Ollama. Even Run Analysis only dispatches a queued
 * job and returns immediately.
 */
class AiOperations extends Page
{
    use HasAdminNavVisibility;

    protected static ?string $title = 'AI Operations';
    protected static ?string $navigationLabel = 'AI Operations';

    public string $category = 'all';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Reports';
    }

    public static function getNavigationSort(): ?int
    {
        return 35;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-sparkles';
    }

    public static function canAccess(): bool
    {
        if (\App\Support\RoleAccess::grants(static::class)) {
            return true;
        }

        $user = auth()->user();

        // Do not depend on the old all-or-nothing AI module switch. That switch
        // can stay off (hiding chat/advanced AI surfaces) while background ops
        // intelligence remains available to admins without affecting page loads.
        return ! NavVisibility::isHiddenForUser(static::class, $user)
            && (($user?->isAdmin() || $user?->isOwner()) ?? false);
    }

    public function getView(): string
    {
        return 'filament.pages.ai-operations';
    }

    public function getSubheading(): ?string
    {
        return 'Background-only manifest intelligence, summaries, cleanup suggestions, and exception detection. Normal pages never wait on the local model.';
    }

    public function setCategory(string $category): void
    {
        $allowed = ['all', 'inventory', 'receiving', 'imports', 'shows', 'payroll', 'streamers', 'cleanup', 'exceptions', 'management', 'operations'];
        $this->category = in_array($category, $allowed, true) ? $category : 'all';
    }

    public function runAnalysis(string $scope): void
    {
        $allowed = ['operations', 'weekly', 'inventory', 'payroll', 'streamers', 'exceptions', 'cleanup'];
        if (! in_array($scope, $allowed, true)) {
            return;
        }

        $task = app(AiOpsDispatcher::class)->dispatch(
            scope: $scope,
            triggeredBy: auth()->id(),
            force: true,
        );

        if (! $task) {
            Notification::make()->title('AI operations are disabled')->warning()->send();
            return;
        }

        Notification::make()
            ->title('Background analysis queued')
            ->body("{$scope} task #{$task->id} is on the dedicated AI queue. You can leave this page; VortexOps will keep processing normally.")
            ->success()
            ->send();
    }

    public function markReviewed(int $id): void
    {
        AiInsight::find($id)?->markReviewed();
    }

    public function dismissInsight(int $id): void
    {
        AiInsight::find($id)?->dismiss();
    }

    public function getStatsProperty(): array
    {
        $open = AiInsight::open();

        return [
            'open' => (clone $open)->count(),
            'high' => (clone $open)->where('severity', 'high')->count(),
            'inventory' => (clone $open)->where('category', 'inventory')->count(),
            'payroll' => (clone $open)->where('category', 'payroll')->count(),
            'shows' => (clone $open)->whereIn('category', ['shows', 'imports'])->count(),
            'cleanup' => (clone $open)->where('category', 'cleanup')->count(),
            'pending_ai' => AiTask::query()->where('type', 'like', 'ops_%')->whereIn('status', ['pending', 'processing'])->count(),
        ];
    }

    public function getInsightsProperty(): array
    {
        return AiInsight::open()
            ->when($this->category !== 'all', fn ($q) => $q->where('category', $this->category))
            ->orderByRaw("CASE severity WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END")
            ->orderByDesc('generated_at')
            ->limit(60)
            ->get()
            ->map(fn (AiInsight $insight) => [
                'id' => $insight->id,
                'category' => $insight->category,
                'severity' => $insight->severity,
                'title' => $insight->title,
                'summary' => $insight->summary,
                'details' => $insight->details ?? [],
                'generated_at' => $insight->generated_at?->diffForHumans(),
                'source_type' => $insight->source_type,
                'source_id' => $insight->source_id,
            ])
            ->all();
    }

    public function getRecentTasksProperty(): array
    {
        return AiTask::query()
            ->where('type', 'like', 'ops_%')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AiTask $task) => [
                'id' => $task->id,
                'type' => str($task->type)->after('ops_')->replace('_', ' ')->title()->toString(),
                'status' => $task->status,
                'created_at' => $task->created_at?->diffForHumans(),
                'duration' => $task->durationSeconds(),
                'ai_available' => $task->output['ai_available'] ?? null,
                'insights_stored' => $task->output['insights_stored'] ?? null,
                'error' => $task->error_message,
            ])
            ->all();
    }
}
