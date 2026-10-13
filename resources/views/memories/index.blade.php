<x-layouts.app :title="__('Kumbukumbu')" wide>
    <x-page-header :eyebrow="__('Urithi wa familia')" :title="__('Kumbukumbu')" :description="__('Picha na hadithi zetu. Hazionekani nje ya familia, na yeyote aliye kwenye picha anaweza kuomba iondolewe.')" />
    <form method="GET" class="mb-6 flex flex-wrap gap-2" x-data>
        <select name="year" class="input w-auto" @change="$el.form.requestSubmit()" aria-label="{{ __('Mwaka') }}"><option value="">{{ __('Miaka yote') }}</option>@foreach ($years as $y)<option @selected(request('year') == $y)>{{ $y }}</option>@endforeach</select>
        <select name="household" class="input w-auto" @change="$el.form.requestSubmit()" aria-label="{{ __('Kaya') }}"><option value="">{{ __('Kaya zote') }}</option>@foreach ($households as $h)<option value="{{ $h->id }}" @selected(request('household') == $h->id)>{{ $h->name }}</option>@endforeach</select>
    </form>

    @if ($takedowns->isNotEmpty())
        <x-card :title="__('Maombi ya kuondoa picha')" class="mb-6">
            @foreach ($takedowns as $t)
                <div class="flex flex-col gap-3 border-b border-line py-3 last:border-0 sm:flex-row sm:items-center">
                    <img src="{{ route('memories.file', [$t->media, 'thumb']) }}" class="size-16 rounded-lg object-cover" alt="">
                    <div class="flex-1 text-sm"><p><strong>{{ $t->requester->displayName() }}</strong>: {{ $t->reason }}</p><p class="text-muted">{{ $t->media->album->title }}</p></div>
                    <form method="POST" action="{{ route('memories.takedown.resolve', $t) }}" class="flex gap-2">@csrf
                        <button name="decision" value="upheld" class="btn-danger btn-sm">{{ __('Ondoa kabisa') }}</button>
                        <button name="decision" value="declined" class="btn-ghost btn-sm">{{ __('Rudisha') }}</button>
                    </form>
                </div>
            @endforeach
        </x-card>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($albums as $a)
            <a href="{{ route('memories.show', $a) }}" class="group card overflow-hidden transition hover:shadow-[var(--shadow-lift)]">
                <div class="relative aspect-[4/3] overflow-hidden bg-cream">
                    @if ($a->cover)
                        <img src="{{ route('memories.file', [$a->cover, 'thumb']) }}" alt="" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-105">
                    @else
                        <div class="grid size-full place-items-center text-sand"><x-icon name="image" class="size-14" /></div>
                    @endif
                    @if ($a->is_annual_portrait)<span class="absolute top-3 left-3 badge-gold">{{ __('Picha ya pamoja ya mwaka') }}</span>@endif
                    @if ($a->year)<span class="absolute right-3 bottom-3 rounded-full bg-forest-950/70 px-3 py-1 font-display text-lg text-white">{{ $a->year }}</span>@endif
                </div>
                <div class="p-4"><p class="font-display text-lg font-semibold text-forest-900">{{ $a->title }}</p><p class="text-sm text-muted">{{ trans_choice('{0} hakuna picha|{1} picha :n|[2,*] picha :n', $a->visible_media_count, ['n' => $a->visible_media_count]) }}</p></div>
            </a>
        @empty
            <x-empty icon="image" :title="__('Bado hakuna albamu')" :text="__('Anza na picha ya pamoja ya mkusanyiko wa mwaka.')" class="sm:col-span-2 lg:col-span-3" />
        @endforelse
    </div>

    @can('memories.manage')
        <x-card :title="__('Unda albamu')" class="mt-8">
            <form method="POST" action="{{ route('memories.albums.store') }}" class="grid gap-4 sm:grid-cols-3">@csrf
                <x-field name="title" :label="__('Jina')" required class="sm:col-span-2" />
                <x-field name="year" type="number" :label="__('Mwaka')" :value="now()->year" />
                <x-field name="event_id" type="select" :label="__('Tukio')" :options="['' => '—'] + $events->pluck('title', 'id')->all()" />
                <x-field name="household_id" type="select" :label="__('Kaya')" :options="['' => '—'] + $households->pluck('name', 'id')->all()" />
                <x-field name="visibility" type="select" :label="__('Wanaoona')" :options="['members' => __('Wanafamilia wote'), 'leaders' => __('Uongozi tu')]" />
                <x-field name="description" type="textarea" rows="2" :label="__('Maelezo / hadithi')" class="sm:col-span-2" />
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_annual_portrait" value="1" class="size-5 rounded border-sand"> {{ __('Picha ya pamoja ya mwaka') }}</label>
                <div><button class="btn-primary">{{ __('Unda albamu') }}</button></div>
            </form>
        </x-card>
    @endcan
</x-layouts.app>
