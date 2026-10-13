<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    /** Reports a holder of only finance.view_summary may see (aggregate, no individual balances). */
    private const SUMMARY_REPORTS = ['statement', 'funds', 'expenses', 'investments'];

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $available = $user->hasPermission('finance.view_all') ? array_keys(ReportService::REPORTS) : self::SUMMARY_REPORTS;
        $report = in_array($request->query('report'), $available, true) ? $request->query('report') : 'statement';
        [$from, $to, $label] = $this->reports->period($request->query('from'), $request->query('to'), $request->query('preset'));

        return view('treasury.reports', [
            'available' => collect(ReportService::REPORTS)->only($available),
            'report' => $report,
            'data' => $this->reports->build($report, $from, $to),
            'from' => $from, 'to' => $to, 'label' => $label,
        ]);
    }

    public function download(Request $request, string $report, string $format)
    {
        $user = $request->user();
        abort_unless(array_key_exists($report, ReportService::REPORTS), 404);
        abort_unless($user->hasPermission('finance.view_all') || in_array($report, self::SUMMARY_REPORTS, true), 403);
        [$from, $to, $label] = $this->reports->period($request->query('from'), $request->query('to'), $request->query('preset'));

        return $format === 'pdf' ? $this->reports->pdf($report, $from, $to, $label) : $this->reports->csv($report, $from, $to, $label);
    }
}
