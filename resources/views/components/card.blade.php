@props(['title' => null, 'icon' => null, 'href' => null, 'linkLabel' => null])
<section {{ $attributes->merge(['class' => 'card card-pad']) }}>
    @if ($title)
        <header class="mb-4 flex items-center justify-between gap-3">
            <h2 class="card-title flex items-center gap-2">@if($icon)<x-icon :name="$icon" class="size-5 text-gold-600" />@endif{{ $title }}</h2>
            @if ($href)<a href="{{ $href }}" class="inline-flex items-center gap-1 text-sm font-bold text-forest-700 hover:underline">{{ $linkLabel ?? __('Ona yote') }}<x-icon name="arrow-right" class="size-4" /></a>@endif
        </header>
    @endif
    {{ $slot }}
</section>
