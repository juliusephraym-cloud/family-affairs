@props(['amount', 'muted' => false])
<span {{ $attributes->merge(['class' => 'tabular whitespace-nowrap']) }}>{{ \App\Support\Money::format($amount) }}</span>
