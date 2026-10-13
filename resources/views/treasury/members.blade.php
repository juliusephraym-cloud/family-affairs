<x-layouts.app :title="__('Michango ya wanachama')" wide>
    <x-page-header :eyebrow="__('Hazina')" :title="__('Michango ya wanachama')" :description="__('Taarifa hizi ni za siri — zinaonekana kwa maafisa wa fedha pekee.')">
        <x-slot:actions>
            <a href="{{ route('treasury.members', ['filter' => request('filter') === 'arrears' ? null : 'arrears']) }}" class="btn-ghost">{{ request('filter') === 'arrears' ? __('Onyesha wote') : __('Wenye madeni tu') }}</a>
        </x-slot:actions>
    </x-page-header>
    @include('treasury._nav')
    @if ($arrearsRule)
        <p class="mb-4 flex flex-wrap items-center gap-2 text-sm text-muted">
            {{ __('Kanuni ya miezi :n mfululizo (kifungu 6:02):', ['n' => $arrearsRule->scalar() ?? '—']) }} @if($arrearsRule->isConfirmed())<span class="badge-green">{{ __('Imethibitishwa') }}</span>@else<x-needs-confirmation />@endif
            · {{ __('Mfumo hauondoi mtu; tumia mchakato wa Uzingatiaji.') }} @can('governance.compliance')<a href="{{ route('governance.compliance') }}" class="font-bold text-forest-700 hover:underline">{{ __('Fungua') }}</a>@endcan
        </p>
    @endif
    <div class="card overflow-hidden"><div class="table-wrap"><table class="table">
        <thead><tr><th>{{ __('Mwanachama') }}</th><th class="num">{{ __('Miezi iliyopitwa') }}</th><th class="num">{{ __('Deni lililopitwa') }}</th><th class="num">{{ __('Jumla isiyolipwa') }}</th><th class="num">{{ __('Ziada') }}</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                @php($threshold = $arrearsRule?->scalar())
                <tr>
                    <td><a href="{{ route('treasury.member', $r['member']) }}" class="font-bold text-forest-800 hover:underline">{{ $r['member']->full_name }}</a></td>
                    <td class="num">@if($threshold && $r['summary']['months_in_arrears'] >= $threshold)<span class="badge-clay">{{ $r['summary']['months_in_arrears'] }}</span>@else{{ $r['summary']['months_in_arrears'] }}@endif</td>
                    <td class="num"><x-money :amount="$r['summary']['overdue']" @class(['font-bold text-clay-700' => $r['summary']['overdue'] > 0]) /></td>
                    <td class="num"><x-money :amount="$r['summary']['outstanding']" /></td>
                    <td class="num"><x-money :amount="$r['summary']['credit']" /></td>
                </tr>
            @empty <tr><td colspan="5" class="py-8 text-center text-muted">{{ __('Hakuna mwanachama mwenye deni.') }}</td></tr> @endforelse
        </tbody>
    </table></div></div>
</x-layouts.app>
