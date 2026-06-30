<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\ProjectUpdate;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('project_header')
                ->label('')
                ->columnSpanFull()
                ->content(function (): HtmlString {
                    $project  = $this->record;
                    $progress = $project->milestoneProgress();
                    $color    = $project->color ?? '#6366f1';

                    $statusStyle   = self::statusStyle($project->status);
                    $priorityStyle = self::priorityStyle($project->priority);

                    $ownerName  = $project->owner?->name ?? 'Unassigned';
                    $targetDate = $project->target_date ? $project->target_date->format('M j, Y') : null;
                    $isOverdue  = $project->target_date?->isPast() && $project->status !== 'completed';

                    $pct         = $progress['pct'];
                    $circumf     = 2 * M_PI * 28;
                    $dashOffset  = $circumf - ($pct / 100) * $circumf;

                    $targetHtml = $targetDate
                        ? '<span class="text-sm ' . ($isOverdue ? 'text-red-500 font-semibold' : 'text-gray-500 dark:text-gray-400') . '">'
                            . ($isOverdue ? '⚠ ' : '')
                            . 'Due ' . $targetDate
                            . '</span>'
                        : '';

                    return new HtmlString(<<<HTML
                        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
                            <div class="h-1.5 w-full" style="background:{$color}"></div>
                            <div class="p-6 flex items-start gap-6">
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {$statusStyle['badge']}">
                                            <span class="h-1.5 w-1.5 rounded-full {$statusStyle['dot']}"></span>
                                            {$statusStyle['label']}
                                        </span>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {$priorityStyle['badge']}">
                                            {$priorityStyle['label']}
                                        </span>
                                    </div>
                                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white truncate">{$project->name}</h1>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{$project->description}</p>
                                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                        <span>👤 {$ownerName}</span>
                                        {$targetHtml}
                                    </div>
                                </div>
                                <div class="flex-shrink-0 flex flex-col items-center gap-1">
                                    <svg width="72" height="72" viewBox="0 0 72 72">
                                        <circle cx="36" cy="36" r="28" fill="none" stroke="#e5e7eb" stroke-width="7"/>
                                        <circle cx="36" cy="36" r="28" fill="none" stroke="{$color}" stroke-width="7"
                                            stroke-linecap="round"
                                            stroke-dasharray="{$circumf}"
                                            stroke-dashoffset="{$dashOffset}"
                                            transform="rotate(-90 36 36)"/>
                                        <text x="36" y="41" text-anchor="middle" font-size="14" font-weight="700" fill="{$color}">{$pct}%</text>
                                    </svg>
                                    <span class="text-xs text-gray-400">{$progress['done']}/{$progress['total']} milestones</span>
                                </div>
                            </div>
                        </div>
                    HTML);
                }),

            Placeholder::make('stats_strip')
                ->label('')
                ->columnSpanFull()
                ->content(function (): HtmlString {
                    $project      = $this->record->load(['milestones', 'updates']);
                    $milestoneDone = $project->milestones->where('status', 'completed')->count();
                    $activeFeatures = $project->updates->where('type', 'feature')->where('is_resolved', false)->count();
                    $openBlockers   = $project->updates->where('type', 'blocker')->where('is_resolved', false)->count();
                    $totalUpdates   = $project->updates->count();

                    $stats = [
                        ['value' => $milestoneDone,   'label' => 'Milestones Done',   'color' => 'text-emerald-600 dark:text-emerald-400'],
                        ['value' => $activeFeatures,  'label' => 'Active Features',   'color' => 'text-indigo-600 dark:text-indigo-400'],
                        ['value' => $openBlockers,    'label' => 'Open Blockers',     'color' => $openBlockers > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-500'],
                        ['value' => $totalUpdates,    'label' => 'Total Updates',     'color' => 'text-gray-600 dark:text-gray-400'],
                    ];

                    $cards = collect($stats)->map(fn ($s) => <<<HTML
                        <div class="flex flex-col items-center justify-center p-4 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700">
                            <span class="text-3xl font-bold {$s['color']}">{$s['value']}</span>
                            <span class="mt-1 text-xs text-gray-500 dark:text-gray-400 text-center">{$s['label']}</span>
                        </div>
                    HTML)->implode('');

                    return new HtmlString(<<<HTML
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">{$cards}</div>
                    HTML);
                }),

            Placeholder::make('milestones_section')
                ->label('Milestones')
                ->columnSpan(['default' => 1, 'lg' => 2])
                ->content(function (): HtmlString {
                    $milestones = $this->record->milestones;

                    if ($milestones->isEmpty()) {
                        return new HtmlString('<p class="text-sm text-gray-400 italic">No milestones yet.</p>');
                    }

                    $progress = $this->record->milestoneProgress();
                    $pct      = $progress['pct'];
                    $color    = $this->record->color ?? '#6366f1';

                    $items = $milestones->values()->map(function ($m, $i) use ($color): string {
                        $isOverdue = $m->due_date?->isPast() && $m->status !== 'completed';
                        $dueBadge  = $m->due_date
                            ? '<span class="text-xs ' . ($isOverdue ? 'text-red-500 font-semibold' : 'text-gray-400') . '">'
                                . ($isOverdue ? '⚠ ' : '') . $m->due_date->format('M j') . '</span>'
                            : '';

                        if ($m->status === 'completed') {
                            $icon = '<span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-400 text-sm font-bold">✓</span>';
                            $titleClass = 'line-through text-gray-400';
                        } elseif ($m->status === 'in_progress') {
                            $icon = '<span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900 text-amber-600 dark:text-amber-400 text-sm font-bold">▶</span>';
                            $titleClass = 'font-semibold text-gray-900 dark:text-white';
                        } else {
                            $num = $i + 1;
                            $icon = '<span class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 text-sm font-bold">' . $num . '</span>';
                            $titleClass = 'text-gray-700 dark:text-gray-300';
                        }

                        $desc = $m->description
                            ? '<p class="text-xs text-gray-400 mt-0.5">' . e($m->description) . '</p>'
                            : '';

                        return <<<HTML
                            <div class="flex items-start gap-3 py-3 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                {$icon}
                                <div class="flex-1 min-w-0">
                                    <span class="text-sm {$titleClass}">{$m->title}</span>
                                    {$desc}
                                </div>
                                {$dueBadge}
                            </div>
                        HTML;
                    })->implode('');

                    return new HtmlString(<<<HTML
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800 flex items-center justify-between border-b border-gray-200 dark:border-gray-700">
                                <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Progress</span>
                                <span class="text-xs font-semibold" style="color:{$color}">{$pct}%</span>
                            </div>
                            <div class="h-1.5 w-full bg-gray-100 dark:bg-gray-700">
                                <div class="h-full transition-all" style="width:{$pct}%;background:{$color}"></div>
                            </div>
                            <div class="px-4 divide-y-0">{$items}</div>
                        </div>
                    HTML);
                }),

            Placeholder::make('sidebar')
                ->label('Sidebar')
                ->columnSpan(1)
                ->content(function (): HtmlString {
                    $project  = $this->record->load('updates');
                    $features = $project->updates->where('type', 'feature')->where('is_resolved', false)->values();
                    $blockers = $project->updates->where('type', 'blocker')->where('is_resolved', false)->values();

                    $featureHtml = $features->isEmpty()
                        ? '<p class="text-xs text-gray-400 italic px-4 pb-3">No active features.</p>'
                        : $features->map(fn ($f) => <<<HTML
                            <div class="flex items-start gap-2 px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <span class="mt-0.5 h-2 w-2 flex-shrink-0 rounded-full bg-indigo-400"></span>
                                <span class="text-sm text-gray-700 dark:text-gray-300">{$f->body}</span>
                            </div>
                        HTML)->implode('');

                    $blockerHtml = $blockers->isEmpty()
                        ? '<p class="text-xs text-gray-400 italic px-4 pb-3">No open blockers.</p>'
                        : $blockers->map(fn ($b) => <<<HTML
                            <div class="flex items-start gap-2 px-4 py-2.5 border-b border-red-100 dark:border-red-900 last:border-0 bg-red-50 dark:bg-red-950/30">
                                <span class="mt-0.5 text-red-500 text-xs font-bold flex-shrink-0">!</span>
                                <span class="text-sm text-red-700 dark:text-red-300">{$b->body}</span>
                            </div>
                        HTML)->implode('');

                    $statusLabels   = \App\Models\Project::statusLabels();
                    $priorityLabels = \App\Models\Project::priorityLabels();

                    return new HtmlString(<<<HTML
                        <div class="space-y-4">
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                                <div class="px-4 py-2.5 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Being Worked On</span>
                                </div>
                                {$featureHtml}
                            </div>
                            <div class="rounded-xl border border-red-200 dark:border-red-800 overflow-hidden">
                                <div class="px-4 py-2.5 bg-red-50 dark:bg-red-950/40 border-b border-red-200 dark:border-red-800">
                                    <span class="text-xs font-semibold text-red-600 dark:text-red-400 uppercase tracking-wide">Blockers</span>
                                </div>
                                {$blockerHtml}
                            </div>
                        </div>
                    HTML);
                }),

            Placeholder::make('activity_feed')
                ->label('Activity Feed')
                ->columnSpanFull()
                ->content(function (): HtmlString {
                    $updates = $this->record->updates()->with('author')->limit(30)->get();

                    if ($updates->isEmpty()) {
                        return new HtmlString('<p class="text-sm text-gray-400 italic">No activity yet.</p>');
                    }

                    $typeConfig = [
                        'update'   => ['icon' => '💬', 'bg' => 'bg-blue-50 dark:bg-blue-950/30',   'border' => 'border-blue-200 dark:border-blue-800',   'label' => 'Update'],
                        'feature'  => ['icon' => '✨', 'bg' => 'bg-indigo-50 dark:bg-indigo-950/30', 'border' => 'border-indigo-200 dark:border-indigo-800', 'label' => 'Feature'],
                        'blocker'  => ['icon' => '🚧', 'bg' => 'bg-red-50 dark:bg-red-950/30',     'border' => 'border-red-200 dark:border-red-800',       'label' => 'Blocker'],
                        'decision' => ['icon' => '✅', 'bg' => 'bg-amber-50 dark:bg-amber-950/30', 'border' => 'border-amber-200 dark:border-amber-800',   'label' => 'Decision'],
                    ];

                    $items = $updates->map(function ($u) use ($typeConfig): string {
                        $cfg       = $typeConfig[$u->type] ?? $typeConfig['update'];
                        $author    = $u->author?->name ?? 'System';
                        $timestamp = $u->created_at->diffForHumans();
                        $resolved  = $u->is_resolved
                            ? '<span class="ml-2 text-xs text-gray-400 line-through">resolved</span>'
                            : '';

                        return <<<HTML
                            <div class="flex gap-3 p-3 rounded-lg border {$cfg['bg']} {$cfg['border']}">
                                <span class="text-lg flex-shrink-0 mt-0.5">{$cfg['icon']}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wide">{$cfg['label']}</span>
                                        {$resolved}
                                        <span class="ml-auto text-xs text-gray-400">{$author} · {$timestamp}</span>
                                    </div>
                                    <p class="text-sm text-gray-700 dark:text-gray-200">{$u->body}</p>
                                </div>
                            </div>
                        HTML;
                    })->implode('');

                    return new HtmlString(<<<HTML
                        <div class="space-y-2">{$items}</div>
                    HTML);
                }),
        ]);
    }

    private static function statusStyle(string $status): array
    {
        return match ($status) {
            'active'    => ['label' => 'Active',    'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300', 'dot' => 'bg-emerald-500'],
            'on_hold'   => ['label' => 'On Hold',   'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300',         'dot' => 'bg-amber-500'],
            'completed' => ['label' => 'Completed', 'badge' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',             'dot' => 'bg-blue-500'],
            'cancelled' => ['label' => 'Cancelled', 'badge' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',                 'dot' => 'bg-red-500'],
            default     => ['label' => 'Planning',  'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',             'dot' => 'bg-gray-400'],
        };
    }

    private static function priorityStyle(string $priority): array
    {
        return match ($priority) {
            'critical' => ['label' => '🔴 Critical', 'badge' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'],
            'high'     => ['label' => '🟠 High',     'badge' => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300'],
            'medium'   => ['label' => '🟡 Medium',   'badge' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300'],
            default    => ['label' => '🟢 Low',      'badge' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300'],
        };
    }
}
