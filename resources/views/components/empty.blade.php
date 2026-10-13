@props(['icon' => 'sparkle', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center rounded-2xl border border-dashed border-sand bg-ivory/60 px-6 py-10 text-center']) }}>
    <span class="mb-3 grid size-12 place-items-center rounded-full bg-gold-100 text-gold-700"><x-icon :name="$icon" class="size-6" /></span>
    <p class="font-display text-lg font-semibold text-forest-900">{{ $title }}</p>
    @if ($text)<p class="mt-1 max-w-md text-sm text-muted">{{ $text }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-4">{{ $slot }}</div>@endif
</div>
