<!DOCTYPE html><html><head><meta charset="utf-8">@include('reports._pdf-style')</head><body>
@php($fmt = fn ($v) => is_int($v) ? number_format($v) : $v)
<div class="head">
    <div class="brand">{{ \App\Support\Settings::get('family.name') }}</div>
    <div class="band"></div>
    <div style="margin-top:8px;font-size:13px;font-weight:bold">{{ $title }}</div>
    <div class="sub">{{ __('Kipindi') }}: {{ $period }} · {{ __('Imetolewa') }}: {{ now()->format('d/m/Y H:i') }} · {{ __('na') }} {{ auth()->user()?->displayName() }} · TSh</div>
</div>
@if (isset($data['sections']))
    <table><tr><td>{{ __('Salio la mwanzo (mifuko yote)') }}</td><td class="num">{{ number_format($data['opening']) }}</td></tr></table>
    @foreach ($data['sections'] as $s)
        <h2>{{ $s['title'] }}</h2>
        <table><tr>@foreach ($data['columns'] as $c)<th @class(['num' => $loop->last])>{{ $c }}</th>@endforeach</tr>
            @forelse ($s['rows'] as $r)<tr>@foreach ($r as $i => $v)<td @class(['num' => is_int($v)])>{{ $fmt($v) }}</td>@endforeach</tr>@empty<tr><td colspan="3">—</td></tr>@endforelse
            <tr class="total"><td colspan="2">{{ __('Jumla') }}</td><td class="num">{{ number_format($s['total']) }}</td></tr></table>
    @endforeach
    <table style="margin-top:12px"><tr class="total"><td>{{ __('Ziada / (upungufu) wa kipindi') }}</td><td class="num">{{ number_format($data['net']) }}</td></tr>
        <tr><td>{{ __('Salio la mwisho (mifuko yote, pamoja na uwekezaji kwa gharama)') }}</td><td class="num">{{ number_format($data['closing']) }}</td></tr></table>
@else
    <table><tr>@foreach ($data['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr>
        @forelse ($data['rows'] as $r)<tr>@foreach ($r as $v)<td @class(['num' => is_int($v)])>{{ $fmt($v) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($data['columns']) }}">{{ __('Hakuna taarifa kwa kipindi hiki.') }}</td></tr>@endforelse
        @isset($data['totals'])<tr class="total">@foreach ($data['totals'] as $v)<td @class(['num' => is_int($v)])>{{ $v === null ? ($loop->first ? __('Jumla') : '') : $fmt((int) $v) }}</td>@endforeach</tr>@endisset
    </table>
@endif
@isset($data['note'])<p class="note">{{ $data['note'] }}</p>@endisset
<p class="note">{{ __('Takwimu zote zimetokana na miamala iliyothibitishwa kwenye ledger ya mfumo. Thamani za uwekezaji (kama zipo) zimeonyeshwa tofauti na fedha taslimu.') }}</p>
<div class="foot">{{ __('SIRI — kwa matumizi ya wanafamilia tu') }}</div>
</body></html>
