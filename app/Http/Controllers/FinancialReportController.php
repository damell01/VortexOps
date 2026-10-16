<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\WeeklyPayoutBatch;
use App\Models\PayRunReportSnapshot;
use App\Services\PayRunReport;
use App\Support\AdminModules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinancialReportController extends Controller
{
    private function authorizeReports(): void
    {
        abort_unless(auth()->user()?->isAdmin() || auth()->user()?->isOwner(), 403);
        abort_unless(AdminModules::isEnabled('reporting'), 403);
    }

    public function run(Request $request, WeeklyPayoutBatch $batch, PayRunReport $reports)
    {
        $this->authorizeReports();
        $request->validate(['stage' => 'nullable|in:finalized,paid', 'format' => 'nullable|in:pdf', 'download' => 'nullable|boolean']);
        $snapshots = PayRunReportSnapshot::where('weekly_payout_batch_id', $batch->id)->get()->keyBy('stage');
        $stage = $request->input('stage') ?: ($snapshots->has('paid') ? 'paid' : ($snapshots->has('finalized') ? 'finalized' : null));
        abort_if($request->filled('stage') && ! $snapshots->has($stage), 404);
        $report = $stage ? $snapshots[$stage]->payload : $reports->run($batch);
        $report['historical_unavailable'] = ! $stage && $batch->status !== 'draft';
        $report['back_url'] = \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $batch]);
        $report['snapshots'] = $snapshots->keys()->all();

        return $this->render($request, 'reports.pay-run', $report, 'pay-run-'.$batch->id.($stage ? '-'.$stage : '-current'));
    }

    public function statement(Request $request, PayRunReport $reports)
    {
        $this->authorizeReports();
        $input = $request->validate(['business' => 'nullable|integer|exists:businesses,id', 'month' => 'nullable|date_format:Y-m', 'format' => 'nullable|in:pdf', 'download' => 'nullable|boolean']);
        $businesses = Business::ordered();
        $business = isset($input['business']) ? $businesses->firstWhere('id', (int) $input['business']) : $businesses->first();
        $month = Carbon::createFromFormat('!Y-m', $input['month'] ?? now()->subMonthNoOverflow()->format('Y-m'));
        $report = $business ? $reports->month($business, $month) : null;
        if (! $business) {
            abort_if($request->input('format') === 'pdf', 422, 'Create a business before generating a statement.');
            return response()->view('reports.business-statement', compact('report', 'businesses', 'month'))->header('Cache-Control', 'private, no-store');
        }
        $report['business_id'] = $business->id;
        return $this->render($request, 'reports.business-statement', $report, 'business-'.$business->id.'-'.$month->format('Y-m'), compact('businesses', 'month'));
    }

    private function render(Request $request, string $view, array $report, string $filename, array $extra = [])
    {
        if ($request->input('format') === 'pdf') {
            $pdf = Pdf::loadView($view, ['report' => $report, 'pdf' => true] + $extra)->setPaper('a4', 'landscape');
            $response = $request->boolean('download') ? $pdf->download($filename.'.pdf') : $pdf->stream($filename.'.pdf');
            return $response->header('Cache-Control', 'private, no-store');
        }
        return response()->view($view, ['report' => $report, 'pdf' => false] + $extra)->header('Cache-Control', 'private, no-store');
    }
}
