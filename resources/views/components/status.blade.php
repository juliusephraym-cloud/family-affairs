@props(['value'])
<span {{ $attributes->merge(['class' => \App\Support\Labels::badge($value)]) }}>{{ \App\Support\Labels::status($value) }}</span>
