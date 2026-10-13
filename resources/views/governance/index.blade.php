<x-layouts.app :title="__('Katiba na Kanuni')" wide>
    <x-page-header :eyebrow="__('Utawala wa familia')" :title="__('Katiba na Kanuni')" :description="__('Katiba inayotumika, historia ya marekebisho, na kanuni zinazotumiwa na mfumo. Kanuni yoyote iliyotoka kwenye kumbukumbu za zamani haitumiki hadi uongozi uithibitishe.')">
        <x-slot:actions>@can('governance.compliance')<a href="{{ route('governance.compliance') }}" class="btn-ghost">{{ __('Uzingatiaji wa michango') }}</a>@endcan</x-slot:actions>
    </x-page-header>

    <div x-data="{ tab: '{{ request('tab', 'rules') }}' }">
        <div class="mb-6 flex flex-wrap gap-1">
            @foreach (['rules' => __('Kanuni za mfumo'), 'constitution' => __('Katiba'), 'amendments' => __('Mapendekezo ya marekebisho'), 'decisions' => __('Maamuzi')] as $k => $l)
                <button type="button" class="tab" :class="tab === '{{ $k }}' && 'tab-active'" @click="tab = '{{ $k }}'">{{ $l }}</button>
            @endforeach
        </div>

        {{-- Rules --}}
        <section x-show="tab === 'rules'" class="space-y-3">
            <div class="rounded-2xl border border-gold-200 bg-gold-50 p-4 text-sm text-gold-700">
                <strong>{{ __('Muhimu:') }}</strong> {{ __('Thamani hizi zimetolewa kwenye kumbukumbu za kikao cha Juni 2026, mazungumzo ya WhatsApp na spreadsheet ya Mhazini. Hazijawahi kuandikwa upya kama sheria rasmi. Thibitisha, rekebisha au staafisha kila moja — mfumo utatumia zile zilizothibitishwa tu.') }}
            </div>
            @foreach ($rules as $r)
                <article class="card card-pad" x-data="{ edit: false }">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-display text-lg font-semibold text-forest-900">{{ $r->title() }}</p>
                            <p class="font-mono text-xs text-muted">{{ $r->key }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-display text-xl font-semibold">
                                @php($v = $r->scalar())
                                @if ($v === null)<span class="text-clay-700">{{ __('Haijulikani') }}</span>
                                @elseif ($r->value_type === 'money'){{ \App\Support\Money::format((int) $v) }}
                                @elseif (is_array($v)){{ implode(', ', $v) }}
                                @elseif (is_bool($v)){{ $v ? __('Ndiyo') : __('Hapana') }}
                                @else{{ $v }}@endif
                            </span>
                            <x-status :value="$r->status" />
                        </div>
                    </div>
                    <p class="mt-2 text-sm text-ink-soft">{{ $r->source_reference }}</p>
                    @if ($r->confirmer)<p class="mt-1 text-xs text-muted">{{ __('Imethibitishwa na :n, :d', ['n' => $r->confirmer->displayName(), 'd' => $r->confirmed_at->format('d/m/Y')]) }}</p>@endif
                    @if ($r->versions->isNotEmpty())
                        <details class="mt-2 text-xs text-muted"><summary class="cursor-pointer font-bold">{{ __('Historia (:n)', ['n' => $r->versions->count()]) }}</summary>
                            <ul class="mt-1 space-y-1">@foreach ($r->versions as $ver)<li>{{ $ver->created_at->format('d/m/Y') }} · {{ \App\Support\Labels::status($ver->status) }} · {{ json_encode($ver->value, JSON_UNESCAPED_UNICODE) }} — {{ $ver->reason }} ({{ $ver->changer?->displayName() }})</li>@endforeach</ul>
                        </details>
                    @endif
                    @if ($canConfirm)
                        <button type="button" class="mt-3 text-sm font-bold text-forest-700 hover:underline" @click="edit = !edit">{{ __('Thibitisha / rekebisha') }}</button>
                        <form x-show="edit" x-collapse method="POST" action="{{ route('governance.rules.confirm', $r) }}" class="mt-3 grid gap-3 border-t border-line pt-3 md:grid-cols-2">@csrf
                            <label><span class="field-label">{{ __('Thamani') }} <span class="font-normal text-muted">({{ $r->value_type }}{{ $r->value_type === 'list' ? ', '.__('tenganisha kwa koma') : '' }})</span></span>
                                <input name="value" class="input" value="{{ is_array($v) ? implode(', ', $v) : (is_bool($v) ? ($v ? 'true' : 'false') : $v) }}"></label>
                            <label><span class="field-label">{{ __('Inaanza kutumika') }}</span><input type="date" name="effective_from" class="input" value="{{ $r->effective_from?->toDateString() }}"></label>
                            <label class="md:col-span-2"><span class="field-label">{{ __('Uamuzi wa kikao unaohusika') }}</span><select name="decision_id" class="input"><option value="">—</option>@foreach ($decisions as $dc)<option value="{{ $dc->id }}" @selected($r->decision_id === $dc->id)>{{ $dc->title }} {{ $dc->meeting ? '('.$dc->meeting->starts_at->format('d/m/Y').')' : '' }}</option>@endforeach</select></label>
                            <label class="md:col-span-2"><span class="field-label">{{ __('Sababu / maelezo (yatahifadhiwa)') }}</span><textarea name="reason" class="input" rows="2" required minlength="5"></textarea></label>
                            <div class="flex flex-wrap gap-2 md:col-span-2">
                                <button name="action" value="confirm" class="btn-primary btn-sm">{{ __('Thibitisha') }}</button>
                                <button name="action" value="propose" class="btn-ghost btn-sm">{{ __('Weka kama pendekezo') }}</button>
                                <button name="action" value="retire" class="btn-danger btn-sm">{{ __('Staafisha') }}</button>
                            </div>
                        </form>
                    @endif
                </article>
            @endforeach
        </section>

        {{-- Constitution --}}
        <section x-show="tab === 'constitution'" x-cloak class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @if ($current)
                    <article class="card card-pad">
                        <p class="eyebrow">{{ __('Toleo linalotumika: :v', ['v' => $current->version_label]) }}</p>
                        <h2 class="mt-1 font-display text-2xl font-semibold">{{ $current->title }}</h2>
                        <p class="text-sm text-muted">{{ __('Linatumika tangu :d', ['d' => $current->effective_from?->translatedFormat('j F Y')]) }} @if($current->decision?->meeting) · {{ __('Kikao: :m', ['m' => $current->decision->meeting->title]) }}@endif</p>
                        <div class="prose-family mt-5 whitespace-pre-line">{{ $current->body }}</div>
                    </article>
                @else
                    <x-empty icon="book" :title="__('Katiba bado haijawekwa kwenye mfumo')" :text="__('Katibu aweke maandishi ya katiba iliyoidhinishwa, kisha uongozi uiidhinishe kwa kuunganisha na uamuzi wa kikao. Mfumo hauandiki katiba kwa niaba ya familia.')" />
                @endif
            </div>
            <div class="space-y-6">
                <x-card :title="__('Matoleo')">
                    @forelse ($versions as $ver)
                        <a href="{{ route('governance.version', $ver) }}" class="flex justify-between border-b border-line py-2 last:border-0 hover:underline"><span>{{ $ver->version_label }} — {{ $ver->title }}</span><x-status :value="$ver->status" /></a>
                    @empty <p class="text-muted">{{ __('Hakuna toleo.') }}</p> @endforelse
                </x-card>
                @can('governance.manage_constitution')
                    <x-card :title="__('Weka rasimu mpya')">
                        <form method="POST" action="{{ route('governance.version.store') }}" class="space-y-3">@csrf
                            <x-field name="version_label" :label="__('Toleo')" placeholder="2026.1" required />
                            <x-field name="title" :label="__('Kichwa')" :value="__('Katiba ya Family Affairs')" required />
                            <x-field name="body" type="textarea" rows="10" :label="__('Maandishi kamili')" required />
                            <x-field name="change_summary" type="textarea" rows="2" :label="__('Muhtasari wa mabadiliko')" />
                            <button class="btn-primary w-full">{{ __('Hifadhi rasimu') }}</button>
                        </form>
                    </x-card>
                @endcan
            </div>
        </section>

        {{-- Amendments --}}
        <section x-show="tab === 'amendments'" x-cloak class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-3 lg:col-span-2">
                @forelse ($amendments as $a)
                    <article class="card card-pad">
                        <div class="flex justify-between gap-3"><p class="font-display text-lg font-semibold">{{ __('Kifungu :c', ['c' => $a->clause_ref]) }}</p><x-status :value="$a->status" /></div>
                        @if ($a->current_text)<p class="mt-2 text-sm text-muted line-through decoration-clay-500/50">{{ $a->current_text }}</p>@endif
                        <p class="mt-2">{{ $a->proposed_text }}</p>
                        @if ($a->rationale)<p class="mt-2 text-sm text-muted">{{ __('Sababu:') }} {{ $a->rationale }}</p>@endif
                        <p class="mt-2 text-xs text-muted">{{ __('Limependekezwa na :n, :d', ['n' => $a->proposer?->displayName(), 'd' => $a->created_at->format('d/m/Y')]) }} @if($a->meeting) · {{ $a->meeting->title }}@endif</p>
                        @can('governance.manage_constitution')
                            @if (! in_array($a->status, ['approved', 'rejected', 'withdrawn']))
                                <form method="POST" action="{{ route('governance.amendments.review', $a) }}" class="mt-3 grid gap-2 border-t border-line pt-3 sm:grid-cols-2">@csrf
                                    <select name="status" class="input"><option value="under_review">{{ __('Inapitiwa') }}</option><option value="approved">{{ __('Imeidhinishwa na kikao') }}</option><option value="rejected">{{ __('Imekataliwa') }}</option><option value="withdrawn">{{ __('Imeondolewa') }}</option></select>
                                    <select name="meeting_id" class="input"><option value="">{{ __('Kikao') }}</option>@foreach ($meetings as $m)<option value="{{ $m->id }}">{{ $m->title }}</option>@endforeach</select>
                                    <select name="decision_id" class="input sm:col-span-2"><option value="">{{ __('Uamuzi uliorekodiwa') }}</option>@foreach ($decisions as $dc)<option value="{{ $dc->id }}">{{ $dc->title }}</option>@endforeach</select>
                                    <textarea name="review_note" class="input sm:col-span-2" rows="2" placeholder="{{ __('Maelezo') }}"></textarea>
                                    <div><button class="btn-ghost btn-sm">{{ __('Hifadhi') }}</button></div>
                                </form>
                            @endif
                        @endcan
                    </article>
                @empty <x-empty icon="book" :title="__('Hakuna pendekezo la marekebisho')" /> @endforelse
            </div>
            @can('governance.propose_amendment')
                <x-card :title="__('Pendekeza marekebisho')">
                    <form method="POST" action="{{ route('governance.amendments.store') }}" class="space-y-3">@csrf
                        <x-field name="clause_ref" :label="__('Kifungu')" placeholder="6:02" required />
                        <x-field name="current_text" type="textarea" rows="2" :label="__('Maandishi ya sasa')" />
                        <x-field name="proposed_text" type="textarea" rows="3" :label="__('Maandishi yanayopendekezwa')" required />
                        <x-field name="rationale" type="textarea" rows="2" :label="__('Sababu')" />
                        <button class="btn-primary w-full">{{ __('Wasilisha pendekezo') }}</button>
                        <p class="text-xs text-muted">{{ __('Pendekezo halianzi kutumika hadi lipitishwe na kikao kwa utaratibu wa katiba.') }}</p>
                    </form>
                </x-card>
            @endcan
        </section>

        {{-- Decisions --}}
        <section x-show="tab === 'decisions'" x-cloak>
            <div class="card overflow-hidden"><div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Uamuzi') }}</th><th>{{ __('Kikao') }}</th><th>{{ __('Chanzo') }}</th><th>{{ __('Hali') }}</th></tr></thead>
                <tbody>@forelse ($decisions as $dc)
                    <tr><td><p class="font-bold">{{ $dc->title }}</p><p class="text-sm text-muted">{{ \Illuminate\Support\Str::limit($dc->body, 180) }}</p></td><td>@if($dc->meeting)<a class="text-forest-700 hover:underline" href="{{ route('meetings.show', $dc->meeting) }}">{{ $dc->meeting->title }}</a>@endif</td><td>{{ $dc->source }}</td><td><x-status :value="$dc->status" /></td></tr>
                @empty <tr><td colspan="4" class="py-6 text-center text-muted">{{ __('Hakuna maamuzi yaliyorekodiwa.') }}</td></tr> @endforelse</tbody>
            </table></div></div>
        </section>
    </div>
</x-layouts.app>
