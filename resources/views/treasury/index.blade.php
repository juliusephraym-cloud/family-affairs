<x-layouts.app :title="__('Hazina')" wide>
    <x-page-header :eyebrow="__('Hazina ya familia')" :title="__('Hazina')" :description="__('Salio linalotokana na miamala iliyothibitishwa pekee. Thamani za uwekezaji zinaonyeshwa kando.')">
        <x-slot:actions>
            @can('finance.verify')<a href="{{ route('treasury.payments.create') }}" class="btn-primary"><x-icon name="plus" />{{ __('Rekodi malipo') }}</a>@endcan
            @can('finance.view_summary')<a href="{{ route('treasury.reports') }}" class="btn-ghost"><x-icon name="chart" />{{ __('Ripoti') }}</a>@endcan
        </x-slot:actions>
    </x-page-header>
    @include('treasury._nav')

    @if ($full)
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('treasury.submissions') }}" class="card card-pad hover:shadow-[var(--shadow-lift)]"><p class="text-sm text-muted">{{ __('Malipo yanayosubiri') }}</p><p @class(['stat', 'text-clay-700' => $pendingSubmissions > 0])>{{ $pendingSubmissions }}</p></a>
            <a href="{{ route('treasury.members', ['filter' => 'arrears']) }}" class="card card-pad hover:shadow-[var(--shadow-lift)]"><p class="text-sm text-muted">{{ __('Madeni yaliyopitwa na muda') }}</p><p class="stat"><x-money :amount="$outstanding" /></p></a>
            <div class="card card-pad"><p class="text-sm text-muted">{{ __('Benki na taslimu (mifuko yote)') }}</p><p class="stat"><x-money :amount="$funds->sum('cash')" /></p></div>
            <div class="card card-pad"><p class="text-sm text-muted">{{ __('Uwekezaji (kwa gharama)') }}</p><p class="stat"><x-money :amount="$funds->sum('invested')" /></p></div>
        </div>
        @can('finance.manage')
            @unless ($periodGenerated)
                <form method="POST" action="{{ route('treasury.obligations.generate') }}" class="mb-6 flex flex-col gap-3 rounded-2xl border border-gold-200 bg-gold-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    @csrf <input type="hidden" name="period" value="{{ $currentPeriod }}">
                    <p class="text-gold-700"><strong>{{ __('Michango ya mwezi huu bado haijawekwa.') }}</strong> {{ __('Weka sasa ili kila mwanachama aone mchango wa :m.', ['m' => now()->translatedFormat('F')]) }}</p>
                    <button class="btn-gold btn-sm shrink-0">{{ __('Weka michango ya :m', ['m' => now()->translatedFormat('F')]) }}</button>
                </form>
            @endunless
        @endcan
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        <x-card :title="__('Salio kwa mfuko')" icon="vault" class="lg:col-span-3">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Mfuko') }}</th><th class="num">{{ __('Benki/taslimu') }}</th><th class="num">{{ __('Uwekezaji') }}</th><th class="num">{{ __('Jumla') }}</th></tr></thead>
                <tbody>
                    @foreach ($funds as $f)
                        <tr><td><p class="font-bold">{{ $f['fund']->label() }}</p><p class="text-xs text-muted">{{ $f['fund']->description }}</p></td>
                            <td class="num"><x-money :amount="$f['cash']" /></td><td class="num"><x-money :amount="$f['invested']" /></td><td class="num font-bold"><x-money :amount="$f['total']" /></td></tr>
                    @endforeach
                    <tr class="bg-ivory font-bold"><td>{{ __('Jumla') }}</td><td class="num"><x-money :amount="$funds->sum('cash')" /></td><td class="num"><x-money :amount="$funds->sum('invested')" /></td><td class="num"><x-money :amount="$funds->sum('total')" /></td></tr>
                </tbody>
            </table></div>
        </x-card>

        <x-card :title="__('Miezi sita iliyopita')" icon="chart" class="lg:col-span-2">
            @php($max = max(1, $trend->max('income'), $trend->max('expense')))
            <div class="flex h-48 items-end gap-3" role="img" aria-label="{{ __('Mapato na matumizi kwa mwezi') }}">
                @foreach ($trend as $m)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <div class="flex h-40 w-full items-end justify-center gap-1">
                            <div class="w-1/2 max-w-5 rounded-t-md bg-forest-600" style="height: {{ max(2, $m['income'] / $max * 100) }}%" title="{{ __('Mapato') }}: {{ \App\Support\Money::format($m['income']) }}"></div>
                            <div class="w-1/2 max-w-5 rounded-t-md bg-clay-500" style="height: {{ max(2, $m['expense'] / $max * 100) }}%" title="{{ __('Matumizi') }}: {{ \App\Support\Money::format($m['expense']) }}"></div>
                        </div>
                        <span class="text-xs font-bold text-muted">{{ $m['label'] }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 flex gap-4 text-xs text-muted"><span class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-forest-600"></span>{{ __('Mapato') }}</span><span class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-clay-500"></span>{{ __('Matumizi') }}</span></div>
            <details class="mt-3 text-sm"><summary class="cursor-pointer font-bold text-forest-700">{{ __('Ona kama jedwali') }}</summary>
                <table class="mt-2 w-full text-sm"><tr class="text-muted"><td></td><td class="num">{{ __('Mapato') }}</td><td class="num">{{ __('Matumizi') }}</td><td class="num">{{ __('Ukusanyaji') }}</td></tr>
                @foreach ($trend as $m)<tr><td>{{ $m['label'] }}</td><td class="num">{{ number_format($m['income']) }}</td><td class="num">{{ number_format($m['expense']) }}</td><td class="num">{{ $m['rate'] === null ? '—' : $m['rate'].'%' }}</td></tr>@endforeach</table>
            </details>
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Uwekezaji')" icon="sparkle">
            @forelse ($investments as $i)
                <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line py-3 last:border-0">
                    <p class="font-bold">{{ $i['account']->name }}</p>
                    <div class="text-right">
                        <p><span class="text-sm text-muted">{{ __('Gharama:') }}</span> <x-money :amount="$i['cost']" class="font-bold" /></p>
                        @if ($i['valuation'])
                            <p class="text-sm"><span class="text-muted">{{ __('Thamani (:d):', ['d' => $i['valuation']->valued_on->format('d/m/Y')]) }}</span> <x-money :amount="$i['valuation']->value" /> <span class="badge-grey">{{ __('si fedha taslimu') }}</span></p>
                        @else
                            <p class="text-sm text-muted">{{ __('Thamani ya soko haijawekwa.') }}</p>
                        @endif
                    </div>
                </div>
            @empty <p class="text-muted">{{ __('Hakuna uwekezaji uliorekodiwa.') }}</p> @endforelse
        </x-card>
        <x-card :title="__('Akaunti')" icon="wallet">
            @foreach ($accounts as $a)
                <div class="flex justify-between border-b border-line py-2.5 last:border-0"><span>{{ $a['account']->name }}</span><x-money :amount="$a['balance']" class="font-bold" /></div>
            @endforeach
        </x-card>
    </div>

    @if ($full)
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-card :title="__('Matumizi yanayosubiri')" :href="route('treasury.expenses')">
                @forelse ($expenses as $e)
                    <div class="flex items-center justify-between gap-3 border-b border-line py-2.5 last:border-0">
                        <div class="min-w-0"><p class="truncate font-bold">{{ $e->title }}</p><p class="text-sm text-muted">{{ $e->fund->label() }} · {{ $e->requester?->member?->displayName() }}</p></div>
                        <div class="text-right"><x-money :amount="$e->amount" class="font-bold" /><div><x-status :value="$e->status" /></div></div>
                    </div>
                @empty <p class="text-muted">{{ __('Hakuna.') }}</p> @endforelse
            </x-card>
            <x-card :title="__('Miamala ya karibuni')" :href="route('treasury.ledger')">
                @foreach ($recent as $t)
                    <a href="{{ route('treasury.ledger.show', $t) }}" class="flex items-center justify-between gap-3 border-b border-line py-2.5 last:border-0 hover:bg-ivory">
                        <div class="min-w-0"><p class="truncate font-bold">{{ $t->description }}</p><p class="text-sm text-muted">{{ $t->txn_date->format('d/m/Y') }} · {{ $t->category }}</p></div>
                        <span class="flex items-center gap-2"><x-money :amount="$t->total()" />@if($t->status === 'reversed')<x-status value="reversed" />@endif</span>
                    </a>
                @endforeach
            </x-card>
        </div>
    @endif
</x-layouts.app>
