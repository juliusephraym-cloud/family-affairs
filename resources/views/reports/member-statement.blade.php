<!DOCTYPE html><html><head><meta charset="utf-8">@include('reports._pdf-style')</head><body>
<div class="head">
    <div class="brand">{{ \App\Support\Settings::get('family.name') }}</div><div class="band"></div>
    <div style="margin-top:8px;font-size:13px;font-weight:bold">{{ __('Taarifa ya michango ya mwanachama') }}</div>
    <div class="sub">{{ $member->full_name }} · {{ __('Imetolewa') }} {{ now()->format('d/m/Y H:i') }} · TSh</div>
</div>
<table><tr><td>{{ __('Deni lililopitwa na muda') }}</td><td class="num">{{ number_format($summary['overdue']) }}</td></tr>
<tr><td>{{ __('Jumla isiyolipwa') }}</td><td class="num">{{ number_format($summary['outstanding']) }}</td></tr>
<tr><td>{{ __('Malipo ya ziada') }}</td><td class="num">{{ number_format($summary['credit']) }}</td></tr></table>
<h2>{{ __('Michango na madeni') }}</h2>
<table><tr><th>{{ __('Mwisho') }}</th><th>{{ __('Maelezo') }}</th><th class="num">{{ __('Kiasi') }}</th><th class="num">{{ __('Kilicholipwa') }}</th><th>{{ __('Hali') }}</th></tr>
@foreach ($obligations as $o)<tr><td>{{ $o->due_date->format('d/m/Y') }}</td><td>{{ $o->description }}</td><td class="num">{{ number_format($o->amount) }}</td><td class="num">{{ number_format($o->allocations->sum('amount')) }}</td><td>{{ \App\Support\Labels::status($o->status) }}</td></tr>@endforeach
</table>
<h2>{{ __('Malipo yaliyothibitishwa') }}</h2>
<table><tr><th>{{ __('Tarehe') }}</th><th>{{ __('Kumbukumbu') }}</th><th>{{ __('Lengo') }}</th><th class="num">{{ __('Kiasi') }}</th><th>{{ __('Hali') }}</th></tr>
@foreach ($payments as $p)<tr><td>{{ $p->received_on->format('d/m/Y') }}</td><td>{{ $p->transaction_reference }}</td><td>{{ $p->purpose }}</td><td class="num">{{ number_format($p->amount) }}</td><td>{{ $p->status === 'active' ? __('Imethibitishwa') : \App\Support\Labels::status($p->status) }}</td></tr>@endforeach
</table>
<div class="foot">{{ __('SIRI — taarifa binafsi ya mwanachama') }}</div>
</body></html>
