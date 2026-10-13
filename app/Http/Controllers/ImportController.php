<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\ImportCandidate;
use App\Models\Member;
use App\Models\MembershipStatusHistory;
use App\Services\ImportService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class ImportController extends Controller
{
    public function index(): View
    {
        return view('admin.imports', ['batches' => ImportBatch::with('uploader.member')->withCount(['candidates', 'candidates as pending_count' => fn ($q) => $q->where('status', 'pending')])->latest()->get()]);
    }

    public function upload(Request $request, ImportService $imports): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240']]);
        $file = $request->file('file');
        try {
            $batch = $imports->stageSpreadsheet($file->getRealPath(), $file->getClientOriginalName(), $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', __('Faili halikusomeka: :e', ['e' => $e->getMessage()]));
        }

        return redirect()->route('admin.imports.show', $batch)->with('status', __('Majina :n yamepangwa kwa ukaguzi. Hakuna kilichoongezwa bado.', ['n' => $batch->summary['people'] ?? 0]));
    }

    public function show(ImportBatch $batch): View
    {
        return view('admin.import-show', ['batch' => $batch, 'candidates' => $batch->candidates()->with('proposedMember')->orderBy('status')->orderBy('normalized_name')->get(), 'members' => Member::orderBy('full_name')->get(['id', 'full_name'])]);
    }

    public function decide(Request $request, ImportCandidate $candidate): RedirectResponse
    {
        $d = $request->validate([
            'decision' => ['required', Rule::in(['link', 'create', 'reject'])],
            'member_id' => ['nullable', 'required_if:decision,link', 'exists:members,id'],
            'full_name' => ['nullable', 'required_if:decision,create', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        abort_unless($candidate->status === 'pending', 422);
        if ($d['decision'] === 'create') {
            // Created as a non-member record; becoming a member requires an invitation and phone verification.
            $m = Member::create(['full_name' => $d['full_name'], 'membership_status' => 'non_member', 'birthday_visibility' => 'leaders']);
            MembershipStatusHistory::create(['member_id' => $m->id, 'to_status' => 'non_member', 'reason' => 'Created from reviewed import: '.$candidate->raw_name, 'changed_by' => $request->user()->id]);
            $candidate->update(['status' => 'created', 'proposed_member_id' => $m->id]);
        } elseif ($d['decision'] === 'link') {
            $candidate->update(['status' => 'linked', 'proposed_member_id' => $d['member_id']]);
        } else {
            $candidate->update(['status' => 'rejected']);
        }
        $candidate->update(['decided_by' => $request->user()->id, 'decided_at' => now(), 'note' => $d['note'] ?? null]);
        Audit::record('members.import_decision', $candidate->batch, "Import candidate {$candidate->raw_name}: {$d['decision']}");

        return back();
    }
}
