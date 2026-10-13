@props(['member' => null, 'size' => 'size-10'])
@php
    $tones = ['bg-forest-100 text-forest-800', 'bg-gold-100 text-gold-700', 'bg-clay-100 text-clay-700', 'bg-cream text-ink-soft'];
    $tone = $tones[($member?->id ?? 0) % count($tones)];
@endphp
@if ($member?->photo_path)
    <img src="{{ route('members.photo', $member) }}" alt="" {{ $attributes->merge(['class' => $size.' shrink-0 rounded-full object-cover ring-2 ring-paper']) }} loading="lazy">
@else
    <span {{ $attributes->merge(['class' => $size.' '.$tone.' inline-grid shrink-0 place-items-center rounded-full font-display font-semibold ring-2 ring-paper']) }} aria-hidden="true">{{ $member?->initials() ?? '?' }}</span>
@endif
