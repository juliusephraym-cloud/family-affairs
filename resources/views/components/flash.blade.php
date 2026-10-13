@if (session('status'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-forest-200 bg-forest-50 px-4 py-3 text-forest-900 animate-rise" role="status">
        <x-icon name="check" class="mt-0.5 size-5 shrink-0 text-forest-600" /><p>{{ session('status') }}</p>
    </div>
@endif
@if (session('error'))
    <div class="mb-6 flex items-start gap-3 rounded-2xl border border-clay-200 bg-clay-50 px-4 py-3 text-clay-700 animate-rise" role="alert">
        <x-icon name="alert" class="mt-0.5 size-5 shrink-0" /><p>{{ session('error') }}</p>
    </div>
@endif
@if ($errors->any())
    <div class="mb-6 rounded-2xl border border-clay-200 bg-clay-50 px-4 py-3 text-clay-700" role="alert">
        <p class="flex items-center gap-2 font-bold"><x-icon name="alert" class="size-5" />{{ __('Tafadhali rekebisha yafuatayo:') }}</p>
        <ul class="mt-1 list-disc pl-9 text-sm">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif
