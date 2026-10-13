@props(['title', 'eyebrow' => null, 'description' => null])
<div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between animate-rise">
    <div class="min-w-0">
        @if ($eyebrow)<p class="eyebrow mb-2">{{ $eyebrow }}</p>@endif
        <h1 class="font-display text-3xl font-semibold text-forest-900 sm:text-4xl">{{ $title }}</h1>
        @if ($description)<p class="mt-2 max-w-2xl text-muted">{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap gap-2">{{ $actions }}</div>@endisset
</div>
