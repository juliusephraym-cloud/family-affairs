<?php

namespace App\Http\Controllers;

use App\Models\PolicyRule;
use App\Models\SecretoParticipant;
use App\Models\SecretoSeason;
use App\Services\SecretoService;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecretoController extends Controller
{
    public function __construct(private readonly SecretoService $secreto) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $season = SecretoSeason::whereIn('status', ['draft', 'registration', 'drawn'])->orderByDesc('year')->first()
            ?? SecretoSeason::orderByDesc('year')->first();
        $me = $season ? SecretoParticipant::where('secreto_season_id', $season->id)->where('member_id', $user->member_id)->first() : null;
        $canManage = $user->hasPermission('secreto.manage');

        return view('secreto.index', [
            'season' => $season,
            'me' => $me,
            // Only the logged-in giver's recipient is ever loaded — never the full pairing.
            'recipient' => $season && $me && $season->status !== 'draft' ? $this->secreto->myAssignment($season, $user) : null,
            'participants' => $season ? $season->participants()->with('member')->get() : collect(),
            'archive' => SecretoSeason::where('status', 'exchanged')->orWhere('status', 'archived')->orderByDesc('year')->get(),
            'canManage' => $canManage,
            'minimumRule' => PolicyRule::where('key', 'secreto.gift_minimum')->first(),
            'revealed' => session('secreto_revealed'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->validated($request) + $request->validate(['year' => ['required', 'integer', 'between:2020,2100', 'unique:secreto_seasons,year']]);
        $s = SecretoSeason::create($d + ['status' => 'draft', 'created_by' => $request->user()->id]);
        Audit::record('secreto.season_created', $s, 'Secreto season created: '.$s->year);

        return back()->with('status', __('Msimu wa Secreto umeundwa. Fungua usajili ukiwa tayari.'));
    }

    public function update(Request $request, SecretoSeason $season): RedirectResponse
    {
        $d = $this->validated($request) + $request->validate(['status' => ['required', Rule::in(['draft', 'registration', 'drawn', 'exchanged', 'archived'])], 'memories' => ['nullable', 'string', 'max:5000']]);
        // "drawn" can only be reached through the draw itself.
        if ($d['status'] === 'drawn' && $season->status !== 'drawn') {
            unset($d['status']);
        }
        $season->update($d);

        return back()->with('status', __('Msimu umesasishwa.'));
    }

    public function join(Request $request, SecretoSeason $season): RedirectResponse
    {
        $this->secreto->join($season, $request->user()->member, $request->input('wishlist'));

        return back()->with('status', __('Umejiandikisha kwa Secreto! Usimwambie mtu utakayempata.'));
    }

    public function leave(Request $request, SecretoSeason $season): RedirectResponse
    {
        $this->secreto->leave($season, $request->user()->member);

        return back()->with('status', __('Umejiondoa.'));
    }

    public function updateMe(Request $request, SecretoSeason $season): RedirectResponse
    {
        $d = $request->validate(['wishlist' => ['nullable', 'string', 'max:2000'], 'gift_status' => ['nullable', Rule::in(['not_started', 'preparing', 'ready', 'brought', 'by_proxy'])], 'proxy_name' => ['nullable', 'string', 'max:120']]);
        $me = SecretoParticipant::where('secreto_season_id', $season->id)->where('member_id', $request->user()->member_id)->firstOrFail();
        $me->update(array_filter($d, fn ($v) => $v !== null));

        return back()->with('status', __('Imehifadhiwa.'));
    }

    public function draw(Request $request, SecretoSeason $season): RedirectResponse
    {
        $n = $this->secreto->draw($season, $request->user());

        return back()->with('status', __('Majina :n yamechaguliwa kwa siri. Kila mshiriki ataona Secreto wake hapa.', ['n' => $n]));
    }

    public function reset(Request $request, SecretoSeason $season): RedirectResponse
    {
        $this->secreto->resetDraw($season, (string) $request->input('reason'), $request->user());

        return back()->with('status', __('Uchaguzi umefutwa. Wajulishe washiriki waangalie upya baada ya kuchagua tena.'));
    }

    public function reveal(Request $request, SecretoSeason $season): RedirectResponse
    {
        $pairs = $this->secreto->revealAll($season, $request->user(), (string) $request->input('reason'));

        // Flashed for one request only; never stored or logged.
        return back()->with('secreto_revealed', $pairs);
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'gift_minimum' => ['nullable', 'string'],
            'allow_vouchers' => ['boolean'],
            'rules' => ['nullable', 'string', 'max:5000'],
            'registration_closes_at' => ['nullable', 'date'],
            'exchange_at' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'avoid_same_household' => ['boolean'],
        ]);
        $d['gift_minimum'] = filled($d['gift_minimum'] ?? null) ? Money::parse($d['gift_minimum']) : null;
        $d['allow_vouchers'] = $request->boolean('allow_vouchers');
        $d['avoid_same_household'] = $request->boolean('avoid_same_household');

        return $d;
    }
}
