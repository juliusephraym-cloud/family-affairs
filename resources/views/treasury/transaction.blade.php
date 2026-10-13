<x-layouts.app :title="__('Muamala')">
    <x-page-header :eyebrow="__('Muamala :id', ['id' => $txn->public_id])" :title="$txn->description" />
    @include('treasury._nav')
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Mistari')" class="lg:col-span-2">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Akaunti') }}</th><th>{{ __('Mfuko') }}</th><th>{{ __('Mwanachama') }}</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
                <tbody>
                    @foreach ($txn->entries as $e)
                        <tr><td>{{ $e->account->code }} {{ $e->account->name }}<p class="text-xs text-muted">{{ $e->memo }}</p></td><td>{{ $e->fund->label() }}</td><td>{{ $e->member?->full_name }}</td><td class="num">{{ $e->debit ? number_format($e->debit) : '' }}</td><td class="num">{{ $e->credit ? number_format($e->credit) : '' }}</td></tr>
                    @endforeach
                    <tr class="font-bold"><td colspan="3">{{ __('Jumla') }}</td><td class="num">{{ number_format($txn->entries->sum('debit')) }}</td><td class="num">{{ number_format($txn->entries->sum('credit')) }}</td></tr>
                </tbody>
            </table></div>
        </x-card>
        <x-card :title="__('Maelezo')">
            <dl class="space-y-2 text-sm">
                <div><dt class="text-muted">{{ __('Tarehe') }}</dt><dd class="font-bold">{{ $txn->txn_date->format('d/m/Y') }}</dd></div>
                <div><dt class="text-muted">{{ __('Aina') }}</dt><dd class="font-bold">{{ $txn->category }}</dd></div>
                <div><dt class="text-muted">{{ __('Kumbukumbu') }}</dt><dd class="font-bold">{{ $txn->reference ?: '—' }}</dd></div>
                <div><dt class="text-muted">{{ __('Imerekodiwa na') }}</dt><dd class="font-bold">{{ $txn->poster?->member?->displayName() }} · {{ $txn->created_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-muted">{{ __('Hali') }}</dt><dd><x-status :value="$txn->status" /></dd></div>
                @if ($txn->reverses)<div><dt class="text-muted">{{ __('Inarudisha') }}</dt><dd><a class="font-bold text-forest-700 underline" href="{{ route('treasury.ledger.show', $txn->reverses) }}">{{ $txn->reverses->description }}</a><p class="text-muted">{{ $txn->reversal_reason }}</p></dd></div>@endif
                @if ($txn->reversal)<div><dt class="text-muted">{{ __('Imerudishwa na') }}</dt><dd><a class="font-bold text-forest-700 underline" href="{{ route('treasury.ledger.show', $txn->reversal) }}">{{ $txn->reversal->public_id }}</a><p class="text-muted">{{ $txn->reversal->reversal_reason }}</p></dd></div>@endif
            </dl>
            @if ($txn->status === 'posted' && ! $txn->reverses_transaction_id)
                @can('finance.reverse')<div class="mt-4"><x-confirm-button :action="route('treasury.ledger.reverse', $txn)" :label="__('Rudisha muamala')" variant="btn-danger btn-sm" :reason="true" /></div>@endcan
            @endif
        </x-card>
    </div>
</x-layouts.app>
