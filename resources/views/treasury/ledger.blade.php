<x-layouts.app :title="__('Ledger')" wide>
    <x-page-header :eyebrow="__('Hazina')" :title="__('Ledger')" :description="__('Kila muamala una debit na credit zinazolingana. Miamala iliyothibitishwa haibadilishwi — marekebisho hufanywa kwa muamala wa kinyume.')" />
    @include('treasury._nav')
    <form method="GET" class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end">
        <label><span class="field-label">{{ __('Aina') }}</span><select name="category" class="input"><option value="">{{ __('Zote') }}</option>@foreach ($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></label>
        <label><span class="field-label">{{ __('Kuanzia') }}</span><input type="date" name="from" value="{{ request('from') }}" class="input"></label>
        <label><span class="field-label">{{ __('Hadi') }}</span><input type="date" name="to" value="{{ request('to') }}" class="input"></label>
        <button class="btn-ghost">{{ __('Chuja') }}</button>
    </form>
    <div class="card overflow-hidden"><div class="table-wrap"><table class="table">
        <thead><tr><th>{{ __('Tarehe') }}</th><th>{{ __('Maelezo') }}</th><th>{{ __('Mistari') }}</th><th class="num">{{ __('Kiasi') }}</th><th>{{ __('Hali') }}</th></tr></thead>
        <tbody>
            @forelse ($txns as $t)
                <tr @class(['opacity-60' => $t->status === 'reversed'])>
                    <td class="whitespace-nowrap">{{ $t->txn_date->format('d/m/Y') }}</td>
                    <td><a href="{{ route('treasury.ledger.show', $t) }}" class="font-bold text-forest-800 hover:underline">{{ $t->description }}</a><p class="text-xs text-muted">{{ $t->category }} · {{ $t->reference }} · {{ $t->poster?->member?->displayName() }}</p></td>
                    <td class="text-xs">@foreach ($t->entries as $e)<div class="whitespace-nowrap">{{ $e->debit ? 'Dr' : 'Cr' }} {{ $e->account->code }} <span class="text-muted">[{{ $e->fund->key }}]</span> {{ number_format($e->debit ?: $e->credit) }}</div>@endforeach</td>
                    <td class="num font-bold"><x-money :amount="$t->total()" /></td>
                    <td>@if($t->reverses_transaction_id)<span class="badge-grey">{{ __('Muamala wa kinyume') }}</span>@else<x-status :value="$t->status" />@endif</td>
                </tr>
            @empty <tr><td colspan="5" class="py-8 text-center text-muted">{{ __('Hakuna miamala.') }}</td></tr> @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-4">{{ $txns->links() }}</div>
</x-layouts.app>
