<x-layouts.app :title="$album->title" wide>
    <x-page-header :eyebrow="$album->year ? __('Mwaka :y', ['y' => $album->year]) : __('Albamu')" :title="$album->title" :description="$album->description" />
    <div x-data="{ open: null }" @keydown.escape.window="open = null">
        @if ($media->isEmpty())
            <x-empty icon="image" :title="__('Albamu hii bado haina picha')" />
        @else
            <div class="columns-2 gap-3 sm:columns-3 lg:columns-4 [&>*]:mb-3">
                @foreach ($media as $m)
                    <figure class="group relative break-inside-avoid overflow-hidden rounded-2xl bg-cream">
                        <button type="button" class="block w-full" @click="open = {{ $loop->index }}"><img src="{{ route('memories.file', [$m, 'thumb']) }}" alt="{{ $m->caption }}" loading="lazy" class="w-full transition duration-500 group-hover:scale-[1.03]" @if($m->width) width="{{ $m->width }}" height="{{ $m->height }}" @endif></button>
                        @if ($m->caption)<figcaption class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-forest-950/80 to-transparent p-3 text-sm text-white">{{ $m->caption }}</figcaption>@endif
                    </figure>
                @endforeach
            </div>
            {{-- Lightbox --}}
            @foreach ($media as $m)
                <div x-cloak x-show="open === {{ $loop->index }}" class="fixed inset-0 z-50 flex flex-col bg-forest-950/95 p-4" role="dialog" aria-modal="true">
                    <div class="flex items-center justify-between text-ivory">
                        <p class="text-sm">{{ $m->caption }} <span class="text-ivory/60">· {{ $m->uploader?->displayName() }}</span></p>
                        <div class="flex items-center gap-2">
                            @if ($m->allow_download)<a href="{{ route('memories.file', [$m, 'download']) }}" class="rounded-full p-2 hover:bg-white/10" aria-label="{{ __('Pakua') }}"><x-icon name="download" /></a>@endif
                            <button type="button" class="rounded-full p-2 hover:bg-white/10" @click="open = null" aria-label="{{ __('Funga') }}"><x-icon name="x" /></button>
                        </div>
                    </div>
                    <div class="flex min-h-0 flex-1 items-center justify-center gap-2 py-4">
                        <button type="button" class="rounded-full p-3 text-ivory hover:bg-white/10" @click="open = Math.max(0, open - 1)" aria-label="{{ __('Iliyotangulia') }}">‹</button>
                        <img :src="open === {{ $loop->index }} ? '{{ route('memories.file', [$m, 'full']) }}' : ''" alt="{{ $m->caption }}" class="max-h-full max-w-full rounded-lg object-contain">
                        <button type="button" class="rounded-full p-3 text-ivory hover:bg-white/10" @click="open = Math.min({{ $media->count() - 1 }}, open + 1)" aria-label="{{ __('Inayofuata') }}">›</button>
                    </div>
                    <details class="mx-auto w-full max-w-xl rounded-2xl bg-white/5 p-3 text-sm text-ivory">
                        <summary class="cursor-pointer font-bold">{{ __('Niko kwenye picha hii — iondolewe') }}</summary>
                        <form method="POST" action="{{ route('memories.takedown', $m) }}" class="mt-2 flex gap-2">@csrf<input name="reason" class="input text-ink" placeholder="{{ __('Sababu') }}" required><button class="btn-gold btn-sm">{{ __('Tuma') }}</button></form>
                    </details>
                </div>
            @endforeach
        @endif
    </div>
    @can('memories.upload')
        <x-card :title="__('Pakia picha')" class="mt-8">
            <form method="POST" action="{{ route('memories.upload', $album) }}" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-3 sm:items-end">@csrf
                <x-field name="photos[]" type="file" multiple accept="image/jpeg,image/png,image/webp" :label="__('Picha (hadi 20 kwa wakati)')" required class="sm:col-span-2" />
                <x-field name="caption" :label="__('Maelezo')" />
                <label class="flex items-center gap-2 text-sm"><input type="hidden" name="allow_download" value="0"><input type="checkbox" name="allow_download" value="1" class="size-5 rounded border-sand" checked> {{ __('Ruhusu kupakua') }}</label>
                <p class="text-xs text-muted sm:col-span-2">{{ __('Picha huhifadhiwa kwa siri; taarifa za mahali (GPS) huondolewa kiotomatiki. Pakia tu picha ambazo walio ndani wangeridhia.') }}</p>
                <div><button class="btn-primary"><x-icon name="upload" />{{ __('Pakia') }}</button></div>
            </form>
        </x-card>
    @endcan
</x-layouts.app>
