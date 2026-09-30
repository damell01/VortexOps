        $warnings = [];
        $run = $this->currentPayRun();

        $membersMissingStructure = Streamer::query()->where('status', 'active')->get()->filter(function (Streamer $member): bool {
            try {
                $comp = $member->effectiveCompensation();
                return blank($comp['structure'] ?? null);
            } catch (\Throwable) {
                return true;
            }
        })->count();

        if ($membersMissingStructure > 0) {
            $warnings[] = $membersMissingStructure . ' active team member(s) need a payment structure reviewed.';
        }

        foreach ($this->allCurrentWeekShows() as $show) {
            foreach ($show->getAttribute('workflow_state')['blockers'] ?? [] as $blocker) $warnings[] = $show->title . ': ' . $blocker;
            foreach ($show->getAttribute('payrun_problems') ?? [] as $problem) $warnings[] = $problem;
        }

        if (! $run) {
            $warnings[] = 'No pay run exists for the current week. Create it after the shows you intend to pay are payroll-ready.';
            return array_values(array_unique($warnings));
        }

        if ($run->status === 'draft') {
            foreach (app(PayRunReadinessService::class)->problems($run) as $problem) $warnings[] = $problem;
        }

        return array_values(array_unique($warnings));
    }

    public function currentBreakdown(): array
    {
        $run = $this->currentPayRun();
        if (! $run) return ['people' => 0, 'streamers' => 0, 'fulfillment' => 0, 'streamer_total' => 0.0, 'fulfillment_total' => 0.0];

        $payouts = Payout::query()->where('weekly_payout_batch_id', $run->id)->with('streamer:id,member_type')->get();
        $people = $payouts->pluck('streamer_id')->filter()->unique();
        $streamerIds = $payouts->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())->pluck('streamer_id')->filter()->unique();
        $fulfillmentIds = $payouts->filter(fn (Payout $p) => $p->streamer?->isFulfillment())->pluck('streamer_id')->filter()->unique();

        return [
            'people' => $people->count(),
            'streamers' => $streamerIds->count(),
            'fulfillment' => $fulfillmentIds->count(),
            'streamer_total' => (float) $payouts->filter(fn (Payout $p) => ! $p->streamer?->isFulfillment())->sum('calculated_payout'),
            'fulfillment_total' => (float) $payouts->filter(fn (Payout $p) => $p->streamer?->isFulfillment())->sum('calculated_payout'),
        ];
    }

    public function currentWeekShows(): Collection
    {
        $shows = $this->allCurrentWeekShows();
        $filter = request()->string('workflow')->toString();
        if ($filter === '' || $filter === 'all') return $shows;

        return $shows->filter(function (Show $show) use ($filter): bool {
            $key = $show->getAttribute('workflow_state')['key'] ?? '';
            $hasPayRunProblems = ($show->getAttribute('payrun_problems') ?? []) !== [];
            return match ($filter) {
                'blocked' => $this->showIsDueForPayroll($show) && ($hasPayRunProblems || ! in_array($key, ['payroll_ready', 'payroll', 'paid'], true)),
                'ready' => ! $hasPayRunProblems && $key === 'payroll_ready',
                'in_run' => ! $hasPayRunProblems && $key === 'payroll',
                'paid' => $key === 'paid',
                default => $key === $filter,
            };
        })->values();
    }

    public function workflowBreakdown(): array
    {
        $shows = $this->allCurrentWeekShows();
        return [
            'all' => $shows->count(),
            'blocked' => $shows->filter(function (Show $show): bool {
                if (! $this->showIsDueForPayroll($show)) return false;
                $key = $show->getAttribute('workflow_state')['key'] ?? '';
                return ($show->getAttribute('payrun_problems') ?? []) !== [] || ! in_array($key, ['payroll_ready', 'payroll', 'paid'], true);
            })->count(),
            'ready' => $shows->filter(fn (Show $show) => ($show->getAttribute('payrun_problems') ?? []) === [] && ($show->getAttribute('workflow_state')['key'] ?? '') === 'payroll_ready')->count(),
            'in_run' => $shows->filter(fn (Show $show) => ($show->getAttribute('payrun_problems') ?? []) === [] && ($show->getAttribute('workflow_state')['key'] ?? '') === 'payroll')->count(),
            'paid' => $shows->filter(fn (Show $show) => ($show->getAttribute('workflow_state')['key'] ?? '') === 'paid')->count(),
        ];
    }

    public function streamerOptions(): array
    {
        return Streamer::query()->inChannelContext()->streamers()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
    }

    public function assignStreamerToShow(int $showId, int $streamerId): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $show = Show::query()->inChannelContext()->findOrFail($showId);
        $streamer = Streamer::query()->inChannelContext()->streamers()->where('status', 'active')->findOrFail($streamerId);
        $show->streamers()->sync([$streamer->id => ['is_primary' => true]]);
        Notification::make()->title('Streamer assigned')->body($streamer->name . ' → ' . ($show->title ?: 'Show #' . $show->id))->success()->send();
    }

    public function approveReportInline(int $showId): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        $show = Show::query()->inChannelContext()->with('streamerLogEntry')->findOrFail($showId);
        $report = $show->streamerLogEntry;
        if (! $report || ! $report->isSubmitted() || $report->approval_status === 'approved') {
            Notification::make()->title('Report is not awaiting approval')->warning()->send();
            return;
        }
        $problems = $report->approveByAdmin();
        $notification = Notification::make()->title($problems === [] ? 'Report approved' : 'Report approved with inventory exceptions');
        if ($problems !== []) $notification->body(implode(' · ', $problems))->warning(); else $notification->success();
        $notification->send();
    }

    private function showIsDueForPayroll(Show $show): bool
    {
        if (! $show->show_date || $show->show_date->isFuture()) return false;
        if ($show->show_date->isToday() && $show->start_time && $show->start_time->isFuture()) return false;
        return true;
    }

    public function showResolution(Show $show): array
    {
        $state = $show->getAttribute('workflow_state');
        $key = $state['key'] ?? '';
        $log = $show->streamerLogEntry;
        $payRunProblems = $show->getAttribute('payrun_problems') ?? [];

        if ($payRunProblems !== [] && $this->currentPayRun()) return ['label' => 'Recalculate Run', 'url' => WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->currentPayRun()]), 'tone' => 'warning'];
        if (in_array($key, ['streamer_log', 'admin_review'], true) && $log) return ['label' => $key === 'admin_review' ? 'Review Log' : 'Open Log', 'url' => StreamerLogResource::getUrl('edit', ['record' => $log]), 'tone' => 'warning'];
        if ($key === 'payroll' && $show->payouts->first(fn (Payout $p) => $p->batch)?->batch) {
            $batch = $show->payouts->first(fn (Payout $p) => $p->batch)?->batch;
            return ['label' => 'Open Pay Run', 'url' => WeeklyPayoutBatchResource::getUrl('view', ['record' => $batch]), 'tone' => 'primary'];
        }

        if ($key === 'payroll_ready' && $this->currentPayRun()) return ['label' => 'Review Pay Run', 'url' => WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->currentPayRun()]), 'tone' => 'success'];
        return ['label' => in_array($key, ['payroll_review'], true) ? 'Fix Show Inputs' : 'Open Show', 'url' => ShowResource::getUrl('view', ['record' => $show]), 'tone' => $key === 'payroll_review' ? 'warning' : 'gray'];
    }

    public function readinessSummary(): array
    {
        $shows = $this->allCurrentWeekShows();
        return [
            'shows' => $shows->count(),
            'ready' => $shows->filter(function (Show $show): bool {
                $key = $show->getAttribute('workflow_state')['key'] ?? '';
                return ($show->getAttribute('payrun_problems') ?? []) === [] && in_array($key, ['payroll_ready', 'payroll', 'paid'], true);
            })->count(),
            'review' => $shows->filter(function (Show $show): bool {
                if (! $this->showIsDueForPayroll($show)) return false;
                $key = $show->getAttribute('workflow_state')['key'] ?? '';
                return ($show->getAttribute('payrun_problems') ?? []) !== [] || ! in_array($key, ['payroll_ready', 'payroll', 'paid'], true);
            })->count(),
            'show_payroll' => (float) $shows->sum(fn (Show $show) => (float) ($show->getAttribute('pnl_summary')['payouts'] ?? 0)),
        ];
    }

    public function recentPayRuns(): Collection
    {
        return WeeklyPayoutBatch::query()->withCount('payouts')->latest('week_start')->limit(6)->get();
    }

    private function allCurrentWeekShows(): Collection
    {
        $run = $this->currentPayRun();
        $start = $run?->week_start ?? now()->startOfWeek();
        $end = $run?->week_end ?? now()->endOfWeek();
        $workflow = app(ShowWorkflowService::class);
        $payRunProblems = $run && $run->status === 'draft' ? app(PayRunReadinessService::class)->problems($run) : [];

        return Show::query()
            ->inChannelContext()
            ->where('is_operational', true)
            ->whereBetween('show_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotIn('status', ['cancelled'])
            ->with(['streamers:id,name,payout_type,hourly_rate,package_rate,payout_percentage','streamerLogEntry.streamer','streamerLogEntry.items:id,streamer_log_entry_id,inventory_item_id,quantity,deducted_quantity','payouts.batch','latestDeductionRequest.lines:id,deduction_request_id,inventory_item_id,quantity'])
            ->withSum('payouts', 'calculated_payout')
            ->orderByDesc('show_date')
            ->get()