<x-layouts.app :title="__('Nafasi na ruhusa')" wide>
    <x-page-header :eyebrow="__('Utawala')" :title="__('Nafasi na ruhusa')" :description="__('Mfumo unaangalia ruhusa, si jina la nafasi. Badilisha ruhusa za kila nafasi hapa; mabadiliko yote yanarekodiwa.')" />
    <div class="space-y-4">
        @foreach ($roles as $role)
            <details class="card" @if($loop->first) open @endif>
                <summary class="flex cursor-pointer items-center justify-between gap-3 p-5"><span><span class="font-display text-lg font-semibold">{{ $role->label() }}</span> <span class="text-sm text-muted">· {{ trans_choice('{0} hakuna mtu|{1} mtu :n|[2,*] watu :n', $role->users_count, ['n' => $role->users_count]) }} · {{ $role->key === 'super_admin' ? __('ruhusa zote') : __('ruhusa :n', ['n' => $role->permissions->count()]) }}</span></span><x-icon name="arrow-right" class="size-4" /></summary>
                @if ($role->key === 'super_admin')
                    <p class="px-5 pb-5 text-sm text-muted">{{ __('Msimamizi Mkuu ana ruhusa zote. Hii haibadilishwi hapa.') }}</p>
                @else
                    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="border-t border-line p-5">@csrf @method('PUT')
                        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                            @foreach ($permissions as $group => $perms)
                                <fieldset><legend class="eyebrow mb-2">{{ $group }}</legend>
                                    @foreach ($perms as $p)<label class="flex items-start gap-2 py-1 text-sm"><input type="checkbox" name="permissions[]" value="{{ $p->id }}" class="mt-0.5 size-4 rounded border-sand" @checked($role->permissions->contains('id', $p->id))><span><code class="text-xs">{{ $p->key }}</code><span class="block text-muted">{{ $p->description }}</span></span></label>@endforeach
                                </fieldset>
                            @endforeach
                        </div>
                        <button class="btn-primary mt-5">{{ __('Hifadhi ruhusa') }}</button>
                    </form>
                @endif
            </details>
        @endforeach
    </div>
</x-layouts.app>
