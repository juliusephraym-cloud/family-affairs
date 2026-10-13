<x-layouts.app :title="__('Akaunti na mifuko')" wide>
    <x-page-header :eyebrow="__('Hazina')" :title="__('Akaunti na mifuko')" :description="__('Uhamisho (k.m. benki → UTT), salio la kuanzia, faida na thamani za uwekezaji.')" />
    @include('treasury._nav')
    @php($assetOptions = $assets->pluck('name', 'id')->all())
    @php($fundOptions = $funds->mapWithKeys(fn ($f) => [$f->id => $f->label()])->all())
    <div class="grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Hamisha fedha kati ya akaunti')">
            <form method="POST" action="{{ route('treasury.transfer') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                <x-field name="from" type="select" :label="__('Kutoka')" :options="$assetOptions" /><x-field name="to" type="select" :label="__('Kwenda')" :options="$assetOptions" />
                <x-field name="fund" type="select" :label="__('Mfuko')" :options="$fundOptions" /><x-field name="amount" :label="__('Kiasi')" inputmode="numeric" required />
                <x-field name="date" type="date" :label="__('Tarehe')" :value="now()->toDateString()" /><x-field name="reference" :label="__('Kumbukumbu')" />
                <x-field name="description" :label="__('Maelezo')" required class="sm:col-span-2" :placeholder="__('Mf. Kuweka fedha UTT kwa uamuzi wa kikao')" />
                <div><button class="btn-primary">{{ __('Hamisha') }}</button></div>
            </form>
        </x-card>
        <x-card :title="__('Salio la kuanzia (cut-over)')">
            <p class="mb-3 text-sm text-muted">{{ __('Weka salio halisi lililokuwepo siku mfumo ulipoanza kutumika, kwa kila akaunti na mfuko. Usiweke kiasi ambacho hakijathibitishwa na taarifa ya benki/UTT.') }}</p>
            <form method="POST" action="{{ route('treasury.opening') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                <x-field name="account" type="select" :label="__('Akaunti')" :options="$assetOptions" /><x-field name="fund" type="select" :label="__('Mfuko')" :options="$fundOptions" />
                <x-field name="amount" :label="__('Kiasi')" inputmode="numeric" required /><x-field name="date" type="date" :label="__('Tarehe')" :value="now()->toDateString()" />
                <x-field name="note" :label="__('Chanzo')" required class="sm:col-span-2" :placeholder="__('Mf. Taarifa ya benki ya 30/09/2026')" />
                <div><button class="btn-ghost">{{ __('Rekodi salio') }}</button></div>
            </form>
        </x-card>
        <x-card :title="__('Hamisha kati ya mifuko')">
            <form method="POST" action="{{ route('treasury.interfund') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                <x-field name="account" type="select" :label="__('Akaunti')" :options="$assetOptions" class="sm:col-span-2" />
                <x-field name="from_fund" type="select" :label="__('Kutoka mfuko')" :options="$fundOptions" /><x-field name="to_fund" type="select" :label="__('Kwenda mfuko')" :options="$fundOptions" />
                <x-field name="amount" :label="__('Kiasi')" inputmode="numeric" required /><x-field name="date" type="date" :label="__('Tarehe')" :value="now()->toDateString()" />
                <x-field name="reason" :label="__('Sababu / uamuzi')" required class="sm:col-span-2" />
                <div><button class="btn-ghost">{{ __('Hamisha') }}</button></div>
            </form>
        </x-card>
        <x-card :title="__('Uwekezaji: faida na thamani')">
            @php($inv = $assets->where('subtype', 'investment')->pluck('name', 'id')->all())
            <form method="POST" action="{{ route('treasury.return') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                <x-field name="account" type="select" :label="__('Uwekezaji')" :options="$inv" /><x-field name="fund" type="select" :label="__('Mfuko')" :options="$fundOptions" />
                <x-field name="amount" :label="__('Faida iliyopatikana')" inputmode="numeric" required /><x-field name="date" type="date" :label="__('Tarehe')" :value="now()->toDateString()" />
                <x-field name="note" :label="__('Maelezo')" required class="sm:col-span-2" />
                <div><button class="btn-ghost">{{ __('Rekodi faida') }}</button></div>
            </form>
            <hr class="my-5 border-line">
            <form method="POST" action="{{ route('treasury.valuation') }}" class="grid gap-4 sm:grid-cols-2">@csrf
                <x-field name="account" type="select" :label="__('Uwekezaji')" :options="$inv" /><x-field name="value" :label="__('Thamani ya soko')" inputmode="numeric" required />
                <x-field name="date" type="date" :label="__('Tarehe ya taarifa')" :value="now()->toDateString()" /><x-field name="source" :label="__('Chanzo')" :placeholder="__('Mf. Taarifa ya UTT AMIS')" />
                <div><button class="btn-ghost">{{ __('Hifadhi thamani') }}</button></div>
            </form>
            <p class="mt-3 text-xs text-muted">{{ __('Thamani ya soko haiingii kwenye ledger wala salio la fedha taslimu.') }}</p>
        </x-card>
    </div>
    <x-card :title="__('Chati ya akaunti')" class="mt-6">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('Namba') }}</th><th>{{ __('Akaunti') }}</th><th>{{ __('Aina') }}</th><th class="num">{{ __('Salio') }}</th></tr></thead>
            <tbody>@foreach ($accounts as $a)<tr><td class="font-mono">{{ $a['account']->code }}</td><td>{{ $a['account']->name }}</td><td>{{ $a['account']->type }}</td><td class="num"><x-money :amount="$a['balance']" /></td></tr>@endforeach</tbody>
        </table></div>
    </x-card>
</x-layouts.app>
