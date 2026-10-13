<x-layouts.app :title="__('Kanuni za michango')" wide>
    <x-page-header :eyebrow="__('Hazina')" :title="__('Kanuni za michango')" :description="__('Kiasi cha mchango wa mwezi na mgawanyo wake kwenye mifuko. Kanuni hutumika tu baada ya kuidhinishwa na uongozi — hakuna kiasi kilichoandikwa ndani ya programu.')" />
    @include('treasury._nav')
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            @forelse ($rules as $r)
                <article class="card card-pad">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="font-display text-lg font-semibold">{{ $r->name }}</p><p class="text-sm text-muted">{{ __('Kuanzia :f', ['f' => $r->effective_from->translatedFormat('j F Y')]) }}@if($r->effective_to) {{ __('hadi :t', ['t' => $r->effective_to->translatedFormat('j F Y')]) }}@endif · {{ __('mwisho tarehe :d kila mwezi', ['d' => $r->due_day]) }}</p></div>
                        <div class="text-right"><p class="font-display text-xl font-semibold"><x-money :amount="$r->amount" /></p><x-status :value="$r->status" /></div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">@foreach ($r->fund_splits as $s)<span class="badge-grey">{{ $s['fund'] }}: {{ number_format($s['amount']) }}</span>@endforeach</div>
                    @if ($r->source_reference)<p class="mt-2 text-sm text-muted">{{ $r->source_reference }}</p>@endif
                    @if ($r->approver)<p class="mt-1 text-xs text-muted">{{ __('Imeidhinishwa na :n, :d', ['n' => $r->approver->displayName(), 'd' => $r->approved_at->format('d/m/Y')]) }}</p>@endif
                    <div class="mt-3 flex gap-2">
                        @if ($r->status === 'proposed')@can('governance.confirm_rules')<x-confirm-button :action="route('treasury.rules.approve', $r)" :label="__('Idhinisha kanuni')" variant="btn-primary btn-sm" :confirm="__('Kanuni hii itatumika kuweka michango ya kila mwanachama kuanzia tarehe iliyotajwa. Endelea?')" />@endcan @endif
                        @if ($r->status === 'approved')@can('governance.confirm_rules')<x-confirm-button :action="route('treasury.rules.retire', $r)" :label="__('Staafisha')" variant="btn-ghost btn-sm" :reason="true" />@endcan @endif
                    </div>
                </article>
            @empty
                <x-empty icon="book" :title="__('Hakuna kanuni ya michango bado')" :text="__('Bila kanuni iliyoidhinishwa, mfumo hautaweka michango ya mwezi.')" />
            @endforelse
        </div>
        <div class="space-y-6">
            <x-card :title="__('Kutoka kwenye kumbukumbu za zamani')">
                @foreach ($sourceRules as $sr)
                    <div class="border-b border-line py-2 text-sm last:border-0"><p class="font-bold">{{ $sr->title() }}: {{ $sr->scalar() !== null ? number_format((int) $sr->scalar()) : __('HAIJULIKANI') }} @if(! $sr->isConfirmed())<x-needs-confirmation />@endif</p><p class="text-muted">{{ $sr->source_reference }}</p></div>
                @endforeach
            </x-card>
            @can('finance.rules')
                <x-card :title="__('Pendekeza kanuni')">
                    <form method="POST" action="{{ route('treasury.rules.store') }}" class="space-y-4">@csrf
                        <x-field name="name" :label="__('Jina')" required :placeholder="__('Mf. Mchango wa mwezi 2027')" />
                        <x-field name="effective_from" type="date" :label="__('Inaanza')" required />
                        <x-field name="due_day" type="number" min="1" max="31" :label="__('Tarehe ya mwisho kila mwezi')" required />
                        <fieldset class="space-y-2"><legend class="field-label">{{ __('Kiasi kwa kila mfuko') }}</legend>
                            @foreach ($funds as $f)<label class="flex items-center gap-3"><span class="w-36 text-sm">{{ $f->label() }}</span><input name="splits[{{ $f->key }}]" class="input" inputmode="numeric" placeholder="0"></label>@endforeach
                        </fieldset>
                        <x-field name="decision_id" type="select" :label="__('Uamuzi wa kikao (kama upo)')" :options="['' => '—'] + $decisions->pluck('title', 'id')->all()" />
                        <x-field name="source_reference" type="textarea" rows="2" :label="__('Chanzo / maelezo')" />
                        <button class="btn-primary w-full">{{ __('Pendekeza') }}</button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.app>
