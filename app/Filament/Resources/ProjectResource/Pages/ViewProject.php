<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()->requiresConfirmation(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        /** @var Project $project */
        $project    = $this->record->load(['owner', 'milestones', 'updates.author']);
        $milestones = $project->milestones;
        $updates    = $project->updates;
        $progress   = $project->milestoneProgress();
        $blockers   = $updates->where('type', 'blocker')->where('is_resolved', false);
        $features   = $updates->where('type', 'feature')->where('is_resolved', false);
        $feed       = $updates->whereNotIn('type', ['feature'])->take(20);

        return $schema->components([

            // ── Overview header ──────────────────────────────────────────────
            Section::make()->schema([
                Placeholder::make('project_header')
                    ->label('')
                    ->columnSpanFull()
                    ->content(function () use ($project, $progress): HtmlString {
                        $sc = self::statusStyle($project->status);
                        $pc = self::priorityStyle($project->priority);
                        $statusLabel   = Project::statusLabels()[$project->status]   ?? $project->status;
                        $priorityLabel = Project::priorityLabels()[$project->priority] ?? $project->priority;

                        $progressRing = '';
                        if ($progress['total'] > 0) {
                            $r = 22;
                            $circ = 2 * M_PI * $r;
                            $offset = $circ * (1 - $progress['pct'] / 100);
                            $progressRing = <<<HTML
                            <div class="flex items-center gap-3 shrink-0">
                                <div class="relative w-14 h-14">
                                    <svg class="w-14 h-14 -rotate-90" viewBox="0 0 56 56">
                                        <circle cx="28" cy="28" r="{$r}" fill="none" stroke="currentColor"
                                                class="text-gray-100 dark:text-gray-800" stroke-width="5"/>
                                        <circle cx="28" cy="28" r="{$r}" fill="none"
                                                stroke="{$project->color}" stroke-width="5"
                                                stroke-linecap="round"
                                                stroke-dasharray="{$circ}"
                                                stroke-dashoffset="{$offset}"/>
                                    </svg>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200">{$progress['pct']}%</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{$progress['done']}/{$progress['total']}</p>
                                    <p class="text-xs text-gray-400">milestones</p>
                                </div>
                            </div>
                            HTML;
                        }

                        $targetHtml = '';
                        if ($project->target_date) {
                            $isOverdue = $project->target_date->isPast() && $project->status !== 'completed';
                            $cls = $isOverdue ? 'text-rose-500' : 'text-gray-400 dark:text-gray-500';
                            $suffix = $isOverdue ? ' <span class="font-medium">(overdue)</span>' : '';
                            $targetHtml = "<span class=\"text-xs {$cls}\">· Due {$project->target_date->format('M j, Y')}{$suffix}</span>";
                        }

                        $ownerHtml = $project->owner
                            ? "<span class=\"text-xs text-gray-400 dark:text-gray-500\">· {$project->owner->name}</span>"
                            : '';

                        $descHtml = $project->description
                            ? '<div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800"><p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed whitespace-pre-wrap">'
                              . e($project->description)
                              . '</p></div>'
                            : '';

                        return new HtmlString(<<<HTML
                        <div class="overflow-hidden -mx-6 -mt-6 rounded-t-xl">
                            <div class="h-1.5 w-full" style="background:{$project->color}"></div>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-start gap-4 mt-2">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center shadow-sm"
                                     style="background:{$project->color}1a;border:1.5px solid {$project->color}40">
                                    <div class="w-3.5 h-3.5 rounded-full" style="background:{$project->color}"></div>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {$sc['bg']} {$sc['text']}">
                                            <span class="w-1.5 h-1.5 rounded-full {$sc['dot']}"></span>
                                            {$statusLabel}
                                        </span>
                                        <span class="text-xs font-medium {$pc}">{$priorityLabel} priority</span>
                                        {$ownerHtml}
                                        {$targetHtml}
                                    </div>
                                </div>
                            </div>
                            {$progressRing}
                        </div>
                        {$descHtml}
                        HTML);
                    }),
            ])->columnSpanFull(),

            // ── Stats strip ──────────────────────────────────────────────────
            Placeholder::make('stats')
                ->label('')
                ->columnSpanFull()
                ->content(function () use ($progress, $blockers, $features, $updates): HtmlString {
                    $openB = $blockers->count();
                    $activeF = $features->count();
                    $total = $updates->count();
                    $done = $progress['done'];
                    $ttl = $progress['total'];
                    $bcls = $openB > 0
                        ? 'border-rose-200 dark:border-rose-800 bg-rose-50 dark:bg-rose-900/10'
                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900';
                    $btxt = $openB > 0 ? 'text-xs text-rose-500 mb-0.5' : 'text-xs text-gray-500 dark:text-gray-400 mb-0.5';
                    $bval = $openB > 0 ? 'text-2xl font-bold text-rose-600 dark:text-rose-400' : 'text-2xl font-bold text-gray-900 dark:text-white';
                    return new HtmlString(<<<HTML
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Milestones Done</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{$done}<span class="text-sm font-normal text-gray-400">/{$ttl}</span></p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Active Features</p>
                            <p class="text-2xl font-bold text-sky-600 dark:text-sky-400">{$activeF}</p>
                        </div>
                        <div class="rounded-xl border {$bcls} px-4 py-3">
                            <p class="{$btxt}">Open Blockers</p>
                            <p class="{$bval}">{$openB}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Total Updates</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{$total}</p>
                        </div>
                    </div>
                    HTML);
                }),

            // ── Left/right 2-col layout: milestones + sidebar ────────────────
            Placeholder::make('milestones_section')
                ->label('')
                ->columnSpan(2)
                ->content(function () use ($milestones, $progress, $project): HtmlString {
                    if ($milestones->isEmpty()) {
                        return new HtmlString(<<<HTML
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-8 text-center">
                            <p class="text-sm text-gray-400 dark:text-gray-500 italic">No milestones yet — add them in the Milestones table below.</p>
                        </div>
                        HTML);
                    }

                    $pct = $progress['pct'];
                    $progressBar = <<<HTML
                    <div class="px-5 pt-4 pb-0">
                        <div class="w-full h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                 style="width:{$pct}%;background:{$project->color}"></div>
                        </div>
                    </div>
                    HTML;

                    $items = '';
                    $count = $milestones->count();
                    foreach ($milestones as $i => $milestone) {
                        $isLast    = $i === $count - 1;
                        $isDone    = $milestone->status === 'completed';
                        $isActive  = $milestone->status === 'in_progress';
                        $isOverdue = $milestone->due_date?->isPast() && ! $isDone;

                        $iconBg    = $isDone   ? 'bg-emerald-100 dark:bg-emerald-900/30'
                            : ($isActive ? 'bg-amber-100 dark:bg-amber-900/30 ring-2 ring-amber-400 ring-offset-2 dark:ring-offset-gray-900'
                            : 'bg-gray-100 dark:bg-gray-800');

                        $icon = $isDone
                            ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>'
                            : ($isActive
                                ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                                : '<span class="text-xs font-semibold text-gray-400">' . ($i + 1) . '</span>');

                        $connector = ! $isLast
                            ? '<div class="absolute top-7 left-3.5 w-px bg-gray-200 dark:bg-gray-700" style="height:calc(100% + 0px);min-height:20px;transform:translateX(-50%)"></div>'
                            : '';

                        $titleCls = $isDone ? 'text-gray-400 line-through' : 'text-gray-900 dark:text-gray-100';
                        $opacity  = $isDone ? 'opacity-60' : '';

                        $badges = '';
                        if ($isActive) {
                            $badges .= '<span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/30 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400">In Progress</span>';
                        }
                        if ($isOverdue) {
                            $badges .= '<span class="inline-flex items-center rounded-full bg-rose-100 dark:bg-rose-900/30 px-2 py-0.5 text-[11px] font-medium text-rose-700 dark:text-rose-400">Overdue</span>';
                        }

                        $dateLine = '';
                        if ($milestone->due_date) {
                            $cls = $isOverdue ? 'text-rose-500' : 'text-gray-400 dark:text-gray-500';
                            $txt = $isDone && $milestone->completed_at
                                ? 'Completed ' . $milestone->completed_at->format('M j')
                                : 'Due ' . $milestone->due_date->format('M j, Y');
                            $dateLine = "<p class=\"mt-0.5 text-xs {$cls}\">{$txt}</p>";
                        }

                        $descLine = $milestone->description
                            ? '<p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">' . e(Str::limit($milestone->description, 80)) . '</p>'
                            : '';

                        $items .= <<<HTML
                        <div class="flex items-start gap-4 px-5 py-3.5 {$opacity}">
                            <div class="relative flex flex-col items-center shrink-0 mt-0.5">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 {$iconBg}">
                                    {$icon}
                                </div>
                                {$connector}
                            </div>
                            <div class="flex-1 min-w-0 pb-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-medium {$titleCls}">{$milestone->title}</span>
                                    {$badges}
                                </div>
                                {$descLine}
                                {$dateLine}
                            </div>
                        </div>
                        HTML;
                    }

                    return new HtmlString(<<<HTML
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden h-full">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Milestones</h2>
                            <span class="text-xs text-gray-400">{$pct}% complete</span>
                        </div>
                        {$progressBar}
                        <div class="divide-y divide-gray-50 dark:divide-gray-800/60">
                            {$items}
                        </div>
                    </div>
                    HTML);
                }),

            // ── Sidebar: features + blockers + meta ──────────────────────────
            Placeholder::make('sidebar')
                ->label('')
                ->columnSpan(1)
                ->content(function () use ($features, $blockers, $project): HtmlString {
                    // Features
                    if ($features->isEmpty()) {
                        $featuresList = '<div class="px-4 py-5 text-center"><p class="text-xs text-gray-400 dark:text-gray-500 italic">Nothing in active development</p></div>';
                    } else {
                        $featuresList = '<ul class="divide-y divide-gray-50 dark:divide-gray-800/60">';
                        foreach ($features as $f) {
                            $by = $f->author ? e($f->author->name) . ' · ' : '';
                            $ago = $f->created_at->diffForHumans();
                            $featuresList .= '<li class="flex items-start gap-2.5 px-4 py-3">'
                                . '<div class="mt-1.5 w-2 h-2 rounded-full bg-sky-500 shrink-0"></div>'
                                . '<div><p class="text-sm text-gray-800 dark:text-gray-200">' . e($f->body) . '</p>'
                                . '<p class="text-xs text-gray-400 mt-0.5">' . $by . $ago . '</p></div>'
                                . '</li>';
                        }
                        $featuresList .= '</ul>';
                    }
                    $featureCount = $features->count() > 0
                        ? '<span class="ml-auto text-xs font-bold rounded-full bg-sky-100 dark:bg-sky-900/30 text-sky-600 dark:text-sky-400 px-2 py-0.5">' . $features->count() . '</span>'
                        : '';

                    // Blockers
                    $blockerCount = $blockers->count();
                    $blockerBorder = $blockerCount > 0 ? 'border-rose-200 dark:border-rose-800' : 'border-gray-200 dark:border-gray-700';
                    $blockerHdrBorder = $blockerCount > 0 ? 'border-rose-100 dark:border-rose-900' : 'border-gray-100 dark:border-gray-800';
                    $blockerTitleCls = $blockerCount > 0 ? 'text-sm font-semibold text-rose-700 dark:text-rose-400' : 'text-sm font-semibold text-gray-900 dark:text-gray-100';
                    $blockerIconCls  = $blockerCount > 0 ? 'text-rose-500' : 'text-gray-400';
                    $blockerBadge = $blockerCount > 0
                        ? '<span class="ml-auto text-xs font-bold rounded-full bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 px-2 py-0.5">' . $blockerCount . '</span>'
                        : '';

                    if ($blockers->isEmpty()) {
                        $blockerList = '<div class="px-4 py-5 text-center"><p class="text-xs text-gray-400 dark:text-gray-500 italic">No open blockers</p></div>';
                    } else {
                        $blockerList = '<ul class="divide-y divide-rose-50 dark:divide-rose-900/20">';
                        foreach ($blockers as $b) {
                            $ago = $b->created_at->diffForHumans();
                            $blockerList .= '<li class="flex items-start gap-2.5 px-4 py-3">'
                                . '<div class="mt-1.5 w-2 h-2 rounded-full bg-rose-500 shrink-0"></div>'
                                . '<div><p class="text-sm text-gray-800 dark:text-gray-200">' . e($b->body) . '</p>'
                                . '<p class="text-xs text-gray-400 mt-0.5">' . $ago . '</p></div>'
                                . '</li>';
                        }
                        $blockerList .= '</ul>';
                    }

                    // Meta
                    $created = $project->created_at->format('M j, Y');
                    $updated = $project->updated_at->diffForHumans();
                    $targetRow = $project->target_date
                        ? '<div class="flex items-center justify-between text-sm"><span class="text-gray-500 dark:text-gray-400">Target</span><span class="text-gray-700 dark:text-gray-300 font-medium">' . $project->target_date->format('M j, Y') . '</span></div>'
                        : '';
                    $ownerRow = $project->owner
                        ? '<div class="flex items-center justify-between text-sm"><span class="text-gray-500 dark:text-gray-400">Owner</span><span class="text-gray-700 dark:text-gray-300 font-medium">' . e($project->owner->name) . '</span></div>'
                        : '';

                    return new HtmlString(<<<HTML
                    <div class="space-y-4">
                        <!-- Active features -->
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                            <div class="px-4 py-3.5 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Being Worked On</h3>
                                {$featureCount}
                            </div>
                            {$featuresList}
                        </div>

                        <!-- Blockers -->
                        <div class="rounded-xl border {$blockerBorder} bg-white dark:bg-gray-900 overflow-hidden">
                            <div class="px-4 py-3.5 border-b {$blockerHdrBorder} flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 {$blockerIconCls}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <h3 class="{$blockerTitleCls}">Blockers</h3>
                                {$blockerBadge}
                            </div>
                            {$blockerList}
                        </div>

                        <!-- Details -->
                        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-4 py-4 space-y-3">
                            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Details</h3>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Created</span>
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">{$created}</span>
                                </div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500 dark:text-gray-400">Last updated</span>
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">{$updated}</span>
                                </div>
                                {$targetRow}
                                {$ownerRow}
                            </div>
                        </div>
                    </div>
                    HTML);
                }),

            // ── Activity feed ────────────────────────────────────────────────
            Placeholder::make('activity_feed')
                ->label('')
                ->columnSpanFull()
                ->content(function () use ($feed): HtmlString {
                    if ($feed->isEmpty()) {
                        return new HtmlString(
                            '<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-8 text-center">'
                            . '<p class="text-sm text-gray-400 dark:text-gray-500 italic">No activity yet — post updates, decisions, or blockers in the Activity table below.</p>'
                            . '</div>'
                        );
                    }

                    $typeMap = [
                        'update'   => ['path' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'bg' => 'bg-sky-100 dark:bg-sky-900/30', 'text' => 'text-sky-600 dark:text-sky-400',  'label' => 'Update'],
                        'blocker'  => ['path' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', 'bg' => 'bg-rose-100 dark:bg-rose-900/30', 'text' => 'text-rose-600 dark:text-rose-400', 'label' => 'Blocker'],
                        'decision' => ['path' => 'M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z', 'bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-600 dark:text-amber-400', 'label' => 'Decision'],
                    ];

                    $items = '';
                    foreach ($feed as $entry) {
                        $tc       = $typeMap[$entry->type] ?? $typeMap['update'];
                        $resolved = $entry->is_resolved
                            ? '<span class="text-[10px] font-medium text-gray-400 bg-gray-100 dark:bg-gray-800 rounded-full px-1.5 py-0.5">Resolved</span>'
                            : '';
                        $by  = e($entry->author?->name ?? 'Unknown');
                        $ago = $entry->created_at->diffForHumans();
                        $opacity = $entry->is_resolved ? 'opacity-50' : '';
                        $items .= <<<HTML
                        <div class="flex items-start gap-3.5 px-5 py-4 {$opacity}">
                            <div class="shrink-0 mt-0.5 w-7 h-7 rounded-full {$tc['bg']} flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 {$tc['text']}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{$tc['path']}"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                                    <span class="text-xs font-semibold {$tc['text']}">{$tc['label']}</span>
                                    {$resolved}
                                    <span class="text-xs text-gray-400 dark:text-gray-500">{$by} · {$ago}</span>
                                </div>
                                <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{$entry->body}</p>
                            </div>
                        </div>
                        HTML;
                    }

                    return new HtmlString(<<<HTML
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Activity Feed</h2>
                        </div>
                        <div class="divide-y divide-gray-50 dark:divide-gray-800/60">{$items}</div>
                    </div>
                    HTML);
                }),

        ])->columns(3);
    }

    private static function statusStyle(string $status): array
    {
        return [
            'planning'  => ['bg' => 'bg-gray-100 dark:bg-gray-800',          'text' => 'text-gray-600 dark:text-gray-400',    'dot' => 'bg-gray-400'],
            'active'    => ['bg' => 'bg-emerald-50 dark:bg-emerald-900/20',   'text' => 'text-emerald-700 dark:text-emerald-400', 'dot' => 'bg-emerald-500'],
            'on_hold'   => ['bg' => 'bg-amber-50 dark:bg-amber-900/20',       'text' => 'text-amber-700 dark:text-amber-400',  'dot' => 'bg-amber-500'],
            'completed' => ['bg' => 'bg-sky-50 dark:bg-sky-900/20',           'text' => 'text-sky-700 dark:text-sky-400',     'dot' => 'bg-sky-500'],
            'cancelled' => ['bg' => 'bg-rose-50 dark:bg-rose-900/20',         'text' => 'text-rose-700 dark:text-rose-400',   'dot' => 'bg-rose-500'],
        ][$status] ?? ['bg' => 'bg-gray-100 dark:bg-gray-800', 'text' => 'text-gray-600 dark:text-gray-400', 'dot' => 'bg-gray-400'];
    }

    private static function priorityStyle(string $priority): string
    {
        return [
            'critical' => 'text-rose-600 dark:text-rose-400',
            'high'     => 'text-amber-600 dark:text-amber-400',
            'medium'   => 'text-sky-600 dark:text-sky-400',
            'low'      => 'text-gray-500 dark:text-gray-400',
        ][$priority] ?? 'text-gray-500 dark:text-gray-400';
    }
}
