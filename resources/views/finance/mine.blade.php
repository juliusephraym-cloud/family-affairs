<x-layouts.app :title="__('Michango Yangu')">
    <x-page-header :eyebrow="__('Binafsi — wewe na Mhazini tu')" :title="__('Michango yangu')" :description="__('Kila kiasi hapa kinatokana na malipo yaliyothibitishwa na Mhazini.')">
        <x-slot:actions>
            <a href="{{ route('finance.pay') }}" class="btn-primary"><x-icon name="upload" />{{ __('Tuma taarifa ya malipo') }}</a>
            <a href="{{ route('finance.statement') }}" class="btn-ghost"><x-icon name="download" />{{ __('Taarifa (PDF)') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card card-pad"><p class="text-sm text-muted">{{ __('Deni lililopitwa na muda') }}</p><p @class(['stat', 'text-clay-700' => $summary['overdue'] > 0])><x-money :amount="$summary['overdue']" /></p><p class="text-sm text-muted">{{ trans_choice('{0} hakuna mwezi uliopitwa|{1} mwezi :n|[2,*] miezi :n', $summary['months_in_arrears'], ['n' => $summary['months_in_arrears']]) }}</p></div>
        <div class="card card-pad"><p class="text-sm text-muted">{{ __('Jumla isiyolipwa (pamoja na isiyofika muda)') }}</p><p class="stat"><x-money :amount="$summary['outstanding']" /></p></div>
        <div class="card card-pad"><p class="text-sm text-muted">{{ __('Malipo ya ziada (yatatumika kwa michango ijayo)') }}</p><p class="stat text-forest-700"><x-money :amount="$summary['credit']" /></p></div>
    </div>

    @if ($submissions->where('status', 'pending')->isNotEmpty() || $submissions->where('status', 'rejected')->isNotEmpty())
        <x-card :title="__('Taarifa zangu za malipo')" class="mt-6">
            <ul class="divide-y divide-line">
                @foreach ($submissions->whereIn('status', ['pending', 'rejected']) as $s)
                    <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="font-bold"><x-money :amount="$s->amount" /> · {{ $s->paid_on->translatedFormat('j M Y') }} <span class="font-normal text-muted">{{ $s->transaction_reference }}</span></p>
                            @if ($s->status === 'rejected')<p class="text-sm text-clay-700">{{ __('Sababu:') }} {{ $s->rejection_reason }}</p>@endif</div>
                        <div class="flex items-center gap-2"><x-status :value="$s->status" />
                            @if ($s->status === 'pending')<x-confirm-button :action="route('finance.withdraw', $s)" :label="__('Ondoa')" variant="text-sm font-bold text-clay-700 hover:underline" :confirm="__('Ondoa taarifa hii ya malipo?')" />@endif</div>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Michango na madeni')">
            @if ($obligations->isEmpty())
                <p class="text-muted">{{ __('Bado hakuna michango iliyowekwa kwenye mfumo.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($obligations as $o)
                        @php($paid = $o->allocations->sum('amount'))
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0"><p class="truncate font-bold">{{ $o->description }}</p><p class="text-sm text-muted">{{ __('Mwisho :d', ['d' => $o->due_date->translatedFormat('j M Y')]) }}@if($paid && $paid < $o->amount) · {{ __('imelipwa') }} <x-money :amount="$paid" />@endif</p></div>
                            <div class="text-right"><x-money :amount="$o->amount" class="font-bold" /><div><x-status :value="$o->status" /></div></div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
        <x-card :title="__('Malipo yaliyothibitishwa')">
            @if ($payments->isEmpty())
                <p class="text-muted">{{ __('Hakuna malipo yaliyothibitishwa bado.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($payments as $p)
                        <li class="py-2.5">
                            <div class="flex items-center justify-between gap-3"><p class="font-bold"><x-money :amount="$p->amount" /> <span class="font-normal text-muted">· {{ $p->received_on->translatedFormat('j M Y') }}</span></p>@if($p->status === 'reversed')<x-status value="reversed" />@else<span class="badge-green">{{ __('Imethibitishwa') }}</span>@endif</div>
                            <p class="text-sm text-muted">{{ $p->transaction_reference }} · {{ $p->purpose === 'contributions' ? __('Michango') : ucfirst($p->purpose) }}</p>
                            @if ($p->allocations->where('status', 'active')->isNotEmpty())<p class="mt-1 text-xs text-muted">{{ __('Imelipa:') }} {{ $p->allocations->where('status', 'active')->map(fn ($a) => $a->obligation->description)->join(', ') }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts.app>
