<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\InvestmentValuation;
use App\Models\LedgerAccount;
use App\Models\LedgerTransaction;
use App\Models\Member;
use App\Models\Obligation;
use App\Models\PaymentSubmission;
use App\Models\VerifiedPayment;
use App\Models\WelfareCase;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every figure in a report is derived from ledger_entries / obligations at
 * request time, and each report lists the transaction ids it summarises, so
 * any number can be traced to its source rows.
 */
class ReportService
{
    public const REPORTS = [
        'statement' => 'Taarifa ya fedha ya kipindi (mapato na matumizi)',
        'funds' => 'Salio kwa kila mfuko',
        'outstanding' => 'Madeni ya wanachama',
        'contributions' => 'Michango kwa kila mwanachama',
        'expenses' => 'Matumizi',
        'transactions' => 'Miamala yote (ledger)',
        'welfare' => 'Michango ya jamii na rambirambi',
        'investments' => 'Uwekezaji',
        'reconciliation' => 'Ulinganisho (taarifa za malipo dhidi ya yaliyothibitishwa)',
    ];

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ContributionService $contributions,
    ) {}

    /** @return array{0: Carbon, 1: Carbon, 2: string} */
    public function period(?string $from, ?string $to, ?string $preset = null): array
    {
        $now = now();
        [$f, $t] = match ($preset) {
            'month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$from ? Carbon::parse($from)->startOfDay() : $now->copy()->startOfYear(), $to ? Carbon::parse($to)->endOfDay() : $now->copy()->endOfDay()],
        };

        return [$f, $t, $f->translatedFormat('j M Y').' – '.$t->translatedFormat('j M Y')];
    }

    public function build(string $report, Carbon $from, Carbon $to): array
    {
        return match ($report) {
            'statement' => $this->statement($from, $to),
            'funds' => ['rows' => $this->ledger->fundBalances($to->toDateString())->map(fn ($r) => [$r['fund']->label(), $r['cash'], $r['invested'], $r['total']])->all(), 'columns' => ['Mfuko', 'Benki/taslimu', 'Uwekezaji (gharama)', 'Jumla']],
            'outstanding' => $this->outstanding(),
            'contributions' => $this->contributionsByMember($from, $to),
            'expenses' => $this->expenses($from, $to),
            'transactions' => $this->transactions($from, $to),
            'welfare' => $this->welfare($from, $to),
            'investments' => $this->investments($to),
            'reconciliation' => $this->reconciliation($from, $to),
            default => abort(404),
        };
    }

    private function statement(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('ledger_entries')
            ->join('ledger_transactions as t', 't.id', '=', 'ledger_entries.ledger_transaction_id')
            ->join('ledger_accounts as a', 'a.id', '=', 'ledger_entries.ledger_account_id')
            ->join('funds as f', 'f.id', '=', 'ledger_entries.fund_id')
            ->whereIn('a.type', ['income', 'expense'])
            ->whereBetween('t.txn_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('a.code', 'a.name', 'a.type', 'f.name_sw')
            ->orderBy('a.code')
            ->selectRaw('a.code, a.name, a.type, f.name_sw fund, SUM(credit) - SUM(debit) net')
            ->get();
        $income = $rows->where('type', 'income');
        $expense = $rows->where('type', 'expense');

        return [
            'columns' => ['Akaunti', 'Mfuko', 'Kiasi'],
            'sections' => [
                ['title' => 'Mapato', 'rows' => $income->map(fn ($r) => ["{$r->code} {$r->name}", $r->fund, (int) $r->net])->values()->all(), 'total' => (int) $income->sum('net')],
                ['title' => 'Matumizi', 'rows' => $expense->map(fn ($r) => ["{$r->code} {$r->name}", $r->fund, -(int) $r->net])->values()->all(), 'total' => -(int) $expense->sum('net')],
            ],
            'net' => (int) $income->sum('net') + (int) $expense->sum('net'),
            'opening' => $this->ledger->fundBalances($from->copy()->subDay()->toDateString())->sum('total'),
            'closing' => $this->ledger->fundBalances($to->toDateString())->sum('total'),
        ];
    }

    private function outstanding(): array
    {
        $rows = Member::contributing()->orderBy('full_name')->get()->map(function (Member $m) {
            $s = $this->contributions->summary($m);

            return [$m->full_name, $s['months_in_arrears'], $s['overdue'], $s['outstanding'], $s['credit']];
        })->filter(fn ($r) => $r[3] > 0 || $r[4] > 0)->values();

        return ['columns' => ['Mwanachama', 'Miezi iliyopitwa', 'Deni lililopitwa', 'Jumla isiyolipwa', 'Malipo ya ziada'], 'rows' => $rows->all(), 'totals' => [null, null, $rows->sum(2), $rows->sum(3), $rows->sum(4)]];
    }

    private function contributionsByMember(Carbon $from, Carbon $to): array
    {
        $rows = Member::contributing()->orderBy('full_name')->get()->map(function (Member $m) use ($from, $to) {
            $paid = (int) VerifiedPayment::where('member_id', $m->id)->where('status', 'active')->whereBetween('received_on', [$from->toDateString(), $to->toDateString()])->sum('amount');
            $due = (int) Obligation::where('member_id', $m->id)->whereNotIn('status', ['waived', 'cancelled'])->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->sum('amount');

            return [$m->full_name, $due, $paid];
        });

        return ['columns' => ['Mwanachama', 'Kinachodaiwa kipindi hiki', 'Kilicholipwa kipindi hiki'], 'rows' => $rows->all(), 'totals' => [null, $rows->sum(1), $rows->sum(2)]];
    }

    private function expenses(Carbon $from, Carbon $to): array
    {
        $rows = Expense::with('fund')->where('status', 'paid')->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])->orderBy('paid_on')->get()
            ->map(fn (Expense $e) => [$e->paid_on->format('d/m/Y'), $e->title, $e->category, $e->fund->label(), $e->payment_reference, $e->amount]);

        return ['columns' => ['Tarehe', 'Matumizi', 'Aina', 'Mfuko', 'Kumbukumbu', 'Kiasi'], 'rows' => $rows->all(), 'totals' => [null, null, null, null, null, $rows->sum(5)]];
    }

    private function transactions(Carbon $from, Carbon $to): array
    {
        $rows = LedgerTransaction::with('entries.account', 'entries.fund')
            ->whereBetween('txn_date', [$from->toDateString(), $to->toDateString()])->orderBy('txn_date')->orderBy('id')->get()
            ->flatMap(fn (LedgerTransaction $t) => $t->entries->map(fn ($e) => [
                $t->txn_date->format('d/m/Y'), $t->public_id, $t->category, $t->description, $t->reference, $e->account->code, $e->fund->key, $e->debit, $e->credit, $t->status,
            ]));

        return ['columns' => ['Tarehe', 'Muamala', 'Aina', 'Maelezo', 'Kumbukumbu', 'Akaunti', 'Mfuko', 'Debit', 'Credit', 'Hali'], 'rows' => $rows->all()];
    }

    private function welfare(Carbon $from, Carbon $to): array
    {
        $rows = WelfareCase::whereNotIn('status', ['submitted', 'rejected'])->whereBetween('approved_at', [$from, $to])->with(['obligations.allocations', 'disbursements'])->get()
            ->map(fn (WelfareCase $c) => [
                $c->approved_at->format('d/m/Y'), $c->visibility === 'members' ? $c->title : 'Kesi binafsi #'.$c->id,
                (int) $c->obligations->sum('amount'), (int) $c->obligations->sum(fn ($o) => $o->allocations->sum('amount')), (int) $c->disbursements->sum('amount'),
            ]);

        return ['columns' => ['Tarehe', 'Kesi', 'Michango iliyotakiwa', 'Iliyokusanywa', 'Msaada uliotolewa'], 'rows' => $rows->all(), 'totals' => [null, null, $rows->sum(2), $rows->sum(3), $rows->sum(4)]];
    }

    private function investments(Carbon $to): array
    {
        $rows = LedgerAccount::where('subtype', 'investment')->get()->map(function (LedgerAccount $a) use ($to) {
            $val = InvestmentValuation::where('ledger_account_id', $a->id)->where('valued_on', '<=', $to)->latest('valued_on')->first();

            return [$a->name, $this->ledger->accountBalance($a, null, $to->toDateString()), $val?->value, $val?->valued_on?->format('d/m/Y'), $val?->source];
        });

        return ['columns' => ['Uwekezaji', 'Gharama (kutoka ledger)', 'Thamani ya soko (haijakaguliwa)', 'Tarehe ya thamani', 'Chanzo'], 'rows' => $rows->all(),
            'note' => 'Thamani ya soko ni taarifa iliyowekwa na Mhazini kutoka kwa taasisi ya uwekezaji; si fedha taslimu na haiko kwenye ledger.'];
    }

    private function reconciliation(Carbon $from, Carbon $to): array
    {
        $subs = PaymentSubmission::with('member')->whereBetween('created_at', [$from, $to])->orderBy('created_at')->get()
            ->map(fn ($s) => [$s->created_at->format('d/m/Y'), $s->member->full_name, $s->amount, $s->transaction_reference, $s->status, $s->verifiedPayment?->amount, $s->rejection_reason]);

        return ['columns' => ['Tarehe', 'Mwanachama', 'Kiasi kilichotajwa', 'Kumbukumbu', 'Hali', 'Kiasi kilichothibitishwa', 'Sababu ya kukataliwa'], 'rows' => $subs->all()];
    }

    // ------------------------------------------------------------------ output

    public function pdf(string $report, Carbon $from, Carbon $to, string $label)
    {
        $data = $this->build($report, $from, $to);
        Audit::record('finance.report_exported', null, "Report {$report} exported (PDF)", ['period' => $label]);

        return Pdf::loadView('reports.pdf', ['report' => $report, 'title' => self::REPORTS[$report], 'period' => $label, 'data' => $data])
            ->setPaper('a4', in_array($report, ['transactions', 'reconciliation'], true) ? 'landscape' : 'portrait')
            ->download("family-affairs-{$report}-{$from->format('Ymd')}-{$to->format('Ymd')}.pdf");
    }

    public function csv(string $report, Carbon $from, Carbon $to, string $label): StreamedResponse
    {
        $data = $this->build($report, $from, $to);
        Audit::record('finance.report_exported', null, "Report {$report} exported (CSV)", ['period' => $label]);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Swahili characters correctly
            fputcsv($out, $data['columns'] ?? []);
            foreach ($data['sections'] ?? [['rows' => $data['rows'] ?? []]] as $section) {
                if (isset($section['title'])) {
                    fputcsv($out, [$section['title']]);
                }
                foreach ($section['rows'] as $row) {
                    // Neutralise spreadsheet formula injection from user-entered text.
                    fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, $row));
                }
            }
            fclose($out);
        }, "family-affairs-{$report}-{$from->format('Ymd')}-{$to->format('Ymd')}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function memberStatementPdf(Member $member)
    {
        $obligations = Obligation::where('member_id', $member->id)->with('allocations')->orderBy('due_date')->get();
        $payments = VerifiedPayment::where('member_id', $member->id)->orderBy('received_on')->get();
        Audit::record('finance.statement_exported', $member, 'Member statement exported');

        return Pdf::loadView('reports.member-statement', [
            'member' => $member, 'obligations' => $obligations, 'payments' => $payments, 'summary' => $this->contributions->summary($member),
        ])->download('taarifa-'.Str::slug($member->full_name).'-'.now()->format('Ymd').'.pdf');
    }
}
