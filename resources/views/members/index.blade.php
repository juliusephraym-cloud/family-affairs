<x-layouts.app :title="__('Familia')">
    <x-page-header :eyebrow="__('Saraka ya familia')" :title="__('Familia yetu')" :description="__('Wanafamilia walio kwenye mfumo. Taarifa binafsi zinaonekana kwa uongozi tu.')">
        <x-slot:actions>
            @can('members.manage')<a href="{{ route('members.create') }}" class="btn-primary"><x-icon name="plus" />{{ __('Ongeza mwanafamilia') }}</a>@endcan
            @can('members.import')<a href="{{ route('admin.imports') }}" class="btn-ghost">{{ __('Ingiza orodha') }}</a>@endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" class="mb-6 flex flex-col gap-3 sm:flex-row">
        <label class="relative flex-1"><span class="sr-only">{{ __('Tafuta') }}</span>
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-muted" />
            <input name="q" value="{{ request('q') }}" class="input pl-11" placeholder="{{ __('Tafuta kwa jina…') }}">
        </label>
        <select name="household" class="input sm:w-56" @change="$el.form.requestSubmit()" x-data aria-label="{{ __('Kaya') }}">
            <option value="">{{ __('Kaya zote') }}</option>
            @foreach ($households as $h)<option value="{{ $h->id }}" @selected(request('household') == $h->id)>{{ $h->name }}</option>@endforeach
        </select>
        @if ($private)
            <select name="status" class="input sm:w-52" aria-label="{{ __('Hali') }}">
                <option value="">{{ __('Wanachama (hai, walioalikwa)') }}</option>
                @foreach (\App\Models\Member::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ \App\Support\Labels::status($s) }} ({{ $counts[$s] ?? 0 }})</option>@endforeach
            </select>
        @endif
        <button class="btn-ghost">{{ __('Chuja') }}</button>
    </form>

    @if ($members->isEmpty())
        <x-empty icon="users" :title="__('Hakuna aliyepatikana')" :text="__('Jaribu jina lingine au ondoa vichujio.')" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($members as $m)
                <a href="{{ route('members.show', $m) }}" class="card group flex items-center gap-4 p-4 transition hover:shadow-[var(--shadow-lift)]" style="animation: rise .5s {{ min($loop->index, 12) * 30 }}ms both">
                    <x-avatar :member="$m" size="size-14" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-display text-lg font-semibold text-forest-900 group-hover:underline">{{ $m->displayName() }}</p>
                        @if ($m->preferred_name)<p class="truncate text-sm text-muted">{{ $m->full_name }}</p>@endif
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            @foreach ($m->user?->roles?->where('key', '!=', 'member') ?? [] as $r)<span class="badge-gold">{{ $r->label() }}</span>@endforeach
                            @if ($private && $m->membership_status !== 'active')<x-status :value="$m->membership_status" />@endif
                            @if ($private && $m->user && ! $m->user->phone_verified_at)<span class="badge-clay">{{ __('Hajathibitisha') }}</span>@endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
