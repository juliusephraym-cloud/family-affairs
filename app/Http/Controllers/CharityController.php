<?php

namespace App\Http\Controllers;

use App\Models\CharityActivity;
use App\Models\Member;
use App\Services\LedgerService;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CharityController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $d = $this->validated($request);
        $a = CharityActivity::create($d + ['status' => 'proposed', 'created_by' => $request->user()->id]);
        Audit::record('charity.created', $a, 'Charity activity proposed: '.$a->title);

        return redirect()->route('charity.show', $a)->with('status', __('Shughuli imeundwa.'));
    }

    public function show(CharityActivity $activity): View
    {
        $activity->load('coordinator', 'volunteers', 'expenses');
        // Sadaka is pooled in the charity fund; show what the fund holds right now.
        $fund = app(LedgerService::class)->fundBalances()->first(fn ($r) => $r['fund']->key === 'charity');

        return view('welfare.charity', ['activity' => $activity, 'charityBalance' => (int) ($fund['cash'] ?? 0), 'members' => Member::contributing()->orderBy('full_name')->get(['id', 'full_name'])]);
    }

    public function update(Request $request, CharityActivity $activity): RedirectResponse
    {
        $d = $this->validated($request) + $request->validate(['status' => ['required', Rule::in(['proposed', 'approved', 'done', 'cancelled'])], 'report' => ['nullable', 'string', 'max:10000']]);
        if ($d['status'] === 'approved' && $activity->status !== 'approved') {
            $d['approved_by'] = $request->user()->id;
        }
        if ($request->has('attended')) {
            foreach ($activity->volunteers as $v) {
                $activity->volunteers()->updateExistingPivot($v->id, ['attended' => in_array($v->id, (array) $request->input('attended'))]);
            }
        }
        $activity->update($d);
        Audit::record('charity.updated', $activity, 'Charity activity updated → '.$d['status']);

        return back()->with('status', __('Imehifadhiwa.'));
    }

    public function volunteer(Request $request, CharityActivity $activity): RedirectResponse
    {
        abort_unless(in_array($activity->status, ['proposed', 'approved'], true), 422);
        $mid = $request->user()->member_id;
        $activity->volunteers()->toggle([$mid]);

        return back()->with('status', __('Asante! Usajili wako umesasishwa.'));
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'description' => ['nullable', 'string', 'max:5000'], 'beneficiary' => ['nullable', 'string', 'max:255'],
            'planned_for' => ['nullable', 'date'], 'budget' => ['nullable', 'string'], 'coordinator_member_id' => ['nullable', 'exists:members,id'], 'logistics' => ['nullable', 'string', 'max:5000'],
        ]);
        $d['budget'] = (int) (Money::parse($d['budget'] ?? '0') ?? 0);

        return $d;
    }
}
