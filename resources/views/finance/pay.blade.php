<x-layouts.app :title="__('Tuma taarifa ya malipo')">
    <x-page-header :eyebrow="__('Michango yangu')" :title="__('Tuma taarifa ya malipo')" :description="__('Baada ya kulipa benki au kwa simu, weka maelezo hapa na risiti. Mhazini atathibitisha — kabla ya hapo haitaonekana kama imelipwa.')" />
    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('finance.pay.store') }}" enctype="multipart/form-data" class="card card-pad space-y-5 lg:col-span-2">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <x-field name="amount" inputmode="numeric" :label="__('Kiasi (TSh)')" required :placeholder="'20,000'" />
                <x-field name="paid_on" type="date" :label="__('Tarehe ya kulipa')" :value="now()->toDateString()" required />
                <x-field name="method" type="select" :label="__('Njia ya malipo')" :options="['bank_transfer' => __('Benki'), 'mobile_money' => __('Pesa ya simu (M-Pesa, Tigo Pesa, Airtel Money…)'), 'cash' => __('Taslimu kwa Mhazini'), 'other' => __('Nyingine')]" required />
                <x-field name="channel_detail" :label="__('Benki / mtandao')" :placeholder="'CRDB, M-Pesa…'" />
                <x-field name="transaction_reference" :label="__('Namba ya kumbukumbu ya muamala')" :hint="__('Kama ilivyo kwenye SMS au risiti ya benki. Inazuia malipo kuhesabiwa mara mbili.')" />
                <x-field name="purpose" type="select" :label="__('Malipo haya ni ya')" :options="['contributions' => __('Michango ya mwezi / madeni / faini'), 'sadaka' => __('Sadaka ya wahitaji'), 'welfare' => __('Mchango wa jamii/msiba (wa hiari)'), 'event' => __('Tukio maalum'), 'donation' => __('Mchango mwingine')]" />
            </div>
            <x-field name="receipt" type="file" accept="image/*,application/pdf" :label="__('Risiti (picha au PDF)')" :hint="__('Hadi 5 MB. Inaonekana kwako na kwa Mhazini tu.')" />
            <x-field name="note" type="textarea" rows="2" :label="__('Maelezo (si lazima)')" :placeholder="__('Mf. mchango wa Septemba na Oktoba')" />
            <button class="btn-primary"><x-icon name="upload" />{{ __('Tuma kwa Mhazini') }}</button>
        </form>
        <div class="space-y-6">
            <x-card :title="__('Hali yako sasa')">
                <p class="text-sm text-muted">{{ __('Deni lililopitwa na muda') }}</p>
                <p class="stat"><x-money :amount="$summary['overdue']" /></p>
                <p class="mt-2 text-xs text-muted">{{ __('Malipo hutumika kwanza kwa deni la zamani zaidi, kulingana na kanuni zilizothibitishwa na uongozi.') }}</p>
            </x-card>
            <x-card :title="__('Jinsi ya kulipa')">
                @if ($instructions)
                    <p class="whitespace-pre-line text-sm">{{ $instructions }}</p>
                @else
                    <p class="text-sm text-muted">{{ __('Maelekezo ya akaunti ya familia hayajawekwa kwenye mfumo. Uliza Mhazini.') }}</p>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts.app>
