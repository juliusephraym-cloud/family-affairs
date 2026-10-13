<x-layouts.app :title="__('Uingizaji wa orodha')">
    <x-page-header :eyebrow="__('Utawala')" :title="__('Uingizaji wa orodha')" :description="__('Pakia spreadsheet ya Mhazini (.xlsx) au CSV. Majina yanapangwa kwa ukaguzi tu — hakuna mwanachama, namba ya simu wala kiasi cha fedha kinachoongezwa bila uamuzi wako.')" />
    <form method="POST" action="{{ route('admin.imports.upload') }}" enctype="multipart/form-data" class="card card-pad mb-6 flex flex-col gap-3 sm:flex-row sm:items-end">@csrf
        <x-field name="file" type="file" accept=".xlsx,.csv" :label="__('Faili')" required class="flex-1" />
        <button class="btn-primary"><x-icon name="upload" />{{ __('Pakia kwa ukaguzi') }}</button>
    </form>
    <div class="card divide-y divide-line">
        @forelse ($batches as $b)
            <a href="{{ route('admin.imports.show', $b) }}" class="flex items-center justify-between p-4 hover:bg-ivory"><span><span class="font-bold">{{ $b->filename }}</span><span class="block text-sm text-muted">{{ $b->created_at->format('d/m/Y H:i') }} · {{ $b->uploader?->displayName() }}</span></span><span class="text-sm">{{ __(':p kati ya :t yanasubiri', ['p' => $b->pending_count, 't' => $b->candidates_count]) }}</span></a>
        @empty <p class="p-4 text-muted">{{ __('Hakuna faili lililopakiwa.') }}</p> @endforelse
    </div>
</x-layouts.app>
