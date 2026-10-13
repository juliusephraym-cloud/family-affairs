<x-layouts.app :title="$member->full_name" wide>
    <x-page-header :eyebrow="__('Taarifa ya fedha ya mwanachama')" :title="$member->full_name">
        <x-slot:actions>
            @can('finance.export')<a href="{{ route('treasury.member.pdf', $member) }}" class="btn-ghost"><x-icon name="download" />PDF</a>@endcan
            @can('finance.verify')<a href="{{ route('treasury.payments.create', ['member' => $member->id]) }}" class="btn-primary"><x-icon name="plus" />{{ __('Rekodi malipo') }}</a>@endcan
        </x-slot:actions>
    </x-page-header>
    @include('treasury._nav')
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card card-pad"><p class="text-sm text-muted">{{ __('Deni lililopitwa') }}</p><p class="stat"><x-money :amount="$summary['overdue']" /></p><p class="text-sm text-muted">{{ __('miezi :n', ['n' => $summary['months_in_arrears']]) }}</p></div>
        <div class="card card-pad"><p class="text-sm text-muted">{{ __('Jumla isiyolipwa') }}</p><p class="stat"><x-money :amount="$summary['outstanding']" /></p></div>
        <div class="card card-pad"><p class="text-sm text-muted">{{ __('Ziada') }}</p><p class="stat"><x-money :amount="$summary['credit']" /></p></div>
    </div>
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <x-card :title="__('Michango na madeni')">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Maelezo') }}</th><th>{{ __('Mwisho') }}</th><th class="num">{{ __('Kiasi') }}</th><th class="num">{{ __('Kimelipwa') }}</th><th>{{ __('Hali') }}</th><th></th></tr></thead>
                <tbody>@foreach ($obligations as $o)
                    <tr><td>{{ $o->description }}@if($o->is_historical) <span class="badge-grey">{{ __('ya zamani') }}</span>@endif</td><td>{{ $o->due_date->format('d/m/Y') }}</td><td class="num"><x-money :amount="$o->amount" /></td><td class="num"><x-money :amount="$o->allocations->sum('amount')" /></td><td><x-status :value="$o->status" />@if($o->status_reason)<p class="text-xs text-muted">{{ $o->status_reason }}</p>@endif</td>
                        <td>@if(in_array($o->status, ['open', 'partially_paid']))@can('finance.manage')<x-confirm-button :action="route('treasury.obligations.waive', $o)" :label="__('Samehe')" variant="text-sm font-bold text-forest-700 hover:underline" :reason="true" />@endcan @endif</td></tr>
                @endforeach</tbody>
            </table></div>
        </x-card>
        <x-card :title="__('Malipo')">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Tarehe') }}</th><th>{{ __('Kumbukumbu') }}</th><th class="num">{{ __('Kiasi') }}</th><th>{{ __('Imethibitishwa na') }}</th><th></th></tr></thead>
                <tbody>@foreach ($payments as $p)
                    <tr @class(['opacity-60' => $p->status === 'reversed'])>
                        <td>{{ $p->received_on->format('d/m/Y') }}</td><td class="font-mono text-sm">{{ $p->transaction_reference }}<p class="text-xs text-muted">{{ $p->depositAccount->code }} · {{ $p->purpose }}</p></td>
                        <td class="num"><x-money :amount="$p->amount" /></td><td class="text-sm">{{ $p->verifier?->member?->displayName() }}<p class="text-xs text-muted">{{ $p->verified_at?->format('d/m/Y') }}</p></td>
                        <td>@if($p->status === 'reversed')<x-status value="reversed" /><p class="text-xs text-muted">{{ $p->reversal_reason }}</p>@else @can('finance.reverse')<x-confirm-button :action="route('treasury.payments.reverse', $p)" :label="__('Rudisha')" variant="text-sm font-bold text-clay-700 hover:underline" :reason="true" :confirm="__('Kurudisha kunaondoa malipo haya kwenye michango na kuweka muamala wa kinyume kwenye ledger. Rekodi ya awali haitafutwa.')" />@endcan @endif</td>
                    </tr>
                @endforeach</tbody>
            </table></div>
        </x-card>
    </div>
    @can('finance.manage')
        <x-card :title="__('Ongeza deni la mara moja')" class="mt-6">
            <form method="POST" action="{{ route('treasury.obligations.store') }}" class="grid gap-4 sm:grid-cols-3">
                @csrf <input type="hidden" name="member_id" value="{{ $member->id }}">
                <x-field name="kind" type="select" :label="__('Aina')" :options="['entry_fee' => __('Kiingilio'), 'other' => __('Nyingine (k.m. deni la nyuma)')]" />
                <x-field name="amount" :label="__('Kiasi')" inputmode="numeric" required />
                <x-field name="fund" type="select" :label="__('Mfuko')" :options="\App\Models\Fund::orderBy('sort')->pluck('name_sw', 'key')->all()" />
                <x-field name="description" :label="__('Maelezo')" required class="sm:col-span-2" :placeholder="__('Mf. Deni la nyuma hadi 30/06/2026 (kutoka spreadsheet)')" />
                <x-field name="due_date" type="date" :label="__('Mwisho')" :value="now()->toDateString()" />
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="hidden" name="is_historical" value="0"><input type="checkbox" name="is_historical" value="1" class="size-5 rounded border-sand"> {{ __('Ni deni la kipindi kabla ya mfumo (cut-over)') }}</label>
                <div><button class="btn-ghost">{{ __('Ongeza') }}</button></div>
            </form>
        </x-card>
    @endcan
</x-layouts.app>
