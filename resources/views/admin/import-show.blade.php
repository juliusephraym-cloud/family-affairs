<x-layouts.app :title="$batch->filename" wide>
    <x-page-header :eyebrow="__('Ukaguzi wa uingizaji')" :title="$batch->filename" :description="__('Kwa kila jina: liunganishe na mwanafamilia aliyepo, unda rekodi mpya (kama si mwanachama — mwaliko unahitajika baadaye), au likatae. Takwimu za fedha zinaonyeshwa kwa kumbukumbu tu; Mhazini aziingize kama madeni ya nyuma baada ya kuzithibitisha.')" />
    <div class="space-y-3">
        @foreach ($candidates as $c)
            <article class="card card-pad" x-data="{ open: false }">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div><p class="font-display text-lg font-semibold">{{ $c->raw_name }}</p>
                        @if ($c->proposedMember)<p class="text-sm text-forest-700">{{ $c->status === 'pending' ? __('Inafanana na:') : __('Imeunganishwa na:') }} <strong>{{ $c->proposedMember->full_name }}</strong></p>@endif</div>
                    <div class="flex items-center gap-2"><x-status :value="$c->status === 'pending' ? 'pending' : ($c->status === 'rejected' ? 'rejected' : 'confirmed')" /><button type="button" class="text-sm font-bold text-forest-700 hover:underline" @click="open = !open">{{ __('Takwimu') }}</button></div>
                </div>
                <div x-show="open" x-cloak class="mt-3 overflow-x-auto rounded-xl bg-ivory p-3 text-xs">
                    @foreach ($c->data ?? [] as $sheet => $vals)<p class="font-bold">{{ $sheet }}</p><p class="mb-2 text-muted">@foreach ($vals as $k => $v){{ $k }}: {{ is_int($v) ? number_format($v) : $v }}@if(! $loop->last) · @endif @endforeach</p>@endforeach
                </div>
                @if ($c->status === 'pending')
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-line pt-3">
                        <form method="POST" action="{{ route('admin.imports.decide', $c) }}" class="flex gap-2">@csrf<input type="hidden" name="decision" value="link">
                            <select name="member_id" class="input min-h-9 py-1 text-sm">@foreach ($members as $m)<option value="{{ $m->id }}" @selected($c->proposed_member_id === $m->id)>{{ $m->full_name }}</option>@endforeach</select>
                            <button class="btn-ghost btn-sm">{{ __('Unganisha') }}</button></form>
                        <form method="POST" action="{{ route('admin.imports.decide', $c) }}" class="flex gap-2">@csrf<input type="hidden" name="decision" value="create"><input name="full_name" value="{{ $c->raw_name }}" class="input min-h-9 py-1 text-sm"><button class="btn-ghost btn-sm">{{ __('Unda rekodi') }}</button></form>
                        <form method="POST" action="{{ route('admin.imports.decide', $c) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn-danger btn-sm">{{ __('Kataa') }}</button></form>
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</x-layouts.app>
