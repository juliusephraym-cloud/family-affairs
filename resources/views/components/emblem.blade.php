@props(['size' => 'size-11'])
@php($path = \App\Support\Settings::get('family.emblem_path'))
@if ($path)
    <img src="{{ route('branding.emblem') }}" alt="{{ \App\Support\Settings::get('family.name') }}" {{ $attributes->merge(['class' => $size.' rounded-full object-cover']) }}>
@else
    {{-- Placeholder mark until the family approves an emblem (Admin → Settings). --}}
    <span {{ $attributes->merge(['class' => $size.' relative inline-grid shrink-0 place-items-center rounded-full bg-forest-800 text-gold-200 ring-2 ring-gold-500/70 ring-offset-2 ring-offset-forest-900']) }} aria-hidden="true">
        <x-icon name="tree" class="size-[55%]" />
    </span>
@endif
