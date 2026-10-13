<x-layouts.app :title="__('Malipo ya kuthibitisha')" wide>
    <x-page-header :eyebrow="__('Hazina')" :title="__('Malipo ya kuthibitisha')" :description="__('Linganisha kila taarifa na taarifa ya benki/simu kabla ya kuthibitisha. Kuthibitisha ndiko kunakoweka malipo kwenye ledger.')" />
    @include('treasury._nav')
    <div class="mb-5 flex flex-wrap gap-1">
        @foreach (['pending' => __('Zinasubiri'), 'verified' => __('Zimethibitishwa'), 'rejected' => __('Zimekataliwa'), 'all' => __('Zote')] as $k => $l)
            <a href="{{ route('treasury.submissions', ['status' => $k]) }}" @class(['tab', 'tab-active' => $status === $k])>{{ $l }}</a>
        @endforeach
    </div>

    @if ($submissions->isEmpty())
        <x-empty icon="check" :title="__('Hakuna taarifa hapa')" :text="__('Taarifa mpya za malipo kutoka kwa wanachama zitaonekana hapa.')" />
    @endif

    <div class="space-y-4">
        @foreach ($submissions as $s)
            <article class="card overflow-hidden">
                <div class="grid gap-0 md:grid-cols-[1fr_auto]">
                    <div class="p-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('treasury.member', $s->member) }}" class="font-display text-xl font-semibold text-forest-900 hover:underline">{{ $s->member->full_name }}</a>
                            <x-status :value="$s->status" />
                            <span class="badge-grey">{{ $s->purpose === 'contributions' ? __('Michango') : $s->purpose }}</span>
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                            <div><dt class="text-muted">{{ __('Kiasi') }}</dt><dd class="font-display text-lg font-semibold"><x-money :amount="$s->amount" /></dd></div>
                            <div><dt class="text-muted">{{ __('Tarehe') }}</dt><dd class="font-bold">{{ $s->paid_on->format('d/m/Y') }}</dd></div>
                            <div><dt class="text-muted">{{ __('Njia') }}</dt><dd class="font-bold">{{ $s->method }} {{ $s->channel_detail }}</dd></div>
                            <div><dt class="text-muted">{{ __('Kumbukumbu') }}</dt><dd class="font-mono font-bold break-all">{{ $s->transaction_reference ?: '—' }}</dd></div>
                        </dl>
                        @if ($s->note)<p class="mt-2 text-sm"><span class="text-muted">{{ __('Maelezo:') }}</span> {{ $s->note }}</p>@endif
                        @foreach ($warnings[$s->id] ?? [] as $w)<p class="mt-2 flex items-center gap-2 text-sm font-bold text-clay-700"><x-icon name="alert" class="size-4" />{{ $w }}</p>@endforeach
                        @isset($summaries[$s->id])<p class="mt-2 text-sm text-muted">{{ __('Deni la sasa la mwanachama:') }} <x-money :amount="$summaries[$s->id]['outstanding']" class="font-bold text-ink" /></p>@endisset
                        @if ($s->status === 'rejected')<p class="mt-2 text-sm text-clay-700">{{ __('Sababu:') }} {{ $s->rejection_reason }}</p>@endif
                        @if ($s->reviewer)<p class="mt-2 text-xs text-muted">{{ __('Imeshughulikiwa na :n, :d', ['n' => $s->reviewer->displayName(), 'd' => $s->reviewed_at->format('d/m/Y H:i')]) }}</p>@endif
                    </div>
                    @if ($s->receipt_path)
                        <a href="{{ route('finance.receipt', $s) }}" target="_blank" class="flex items-center justify-center border-t border-line bg-ivory p-3 md:w-48 md:border-t-0 md:border-l">
                            @if (\Illuminate\Support\Str::endsWith($s->receipt_path, '.pdf'))
                                <span class="flex flex-col items-center gap-1 text-sm font-bold text-forest-700"><x-icon name="file" class="size-8" />{{ __('Fungua PDF') }}</span>
                            @else
                                <img src="{{ route('finance.receipt', $s) }}" alt="{{ __('Risiti') }}" class="max-h-40 rounded-lg object-contain" loading="lazy">
                            @endif
                        </a>
                    @endif
                </div>
                @if ($s->status === 'pending')
                    <div class="flex flex-col gap-3 border-t border-line bg-ivory/60 p-4 lg:flex-row lg:items-end">
                        @if ($s->member_id === auth()->user()->member_id)
                            <p class="text-sm font-bold text-clay-700">{{ __('finance.no_self_verify') }}</p>
                        @else
                            <form method="POST" action="{{ route('treasury.submissions.verify', $s) }}" class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
                                @csrf
                                <label class="flex-1"><span class="field-label">{{ __('Fedha zimeingia akaunti gani?') }}</span>
                                    <select name="deposit_account_id" class="input" required>@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></label>
                                <label class="sm:w-40"><span class="field-label">{{ __('Kiasi halisi') }}</span><input name="amount" class="input" placeholder="{{ number_format($s->amount) }}" inputmode="numeric"></label>
                                <button class="btn-primary"><x-icon name="check" />{{ __('Thibitisha') }}</button>
                            </form>
                            <x-confirm-button :action="route('treasury.submissions.reject', $s)" :label="__('Kataa')" variant="btn-danger" :reason="true" :reasonLabel="__('Sababu ya kukataa (mwanachama ataiona)')" />
                        @endif
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</x-layouts.app>
