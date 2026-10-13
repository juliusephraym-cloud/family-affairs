<x-layouts.app :title="__('Vikao')">
    <x-page-header :eyebrow="__('Vikao vya familia')" :title="__('Vikao')" :description="__('Ratiba, agenda, mahudhurio, maamuzi na kumbukumbu zilizoidhinishwa.')" />
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <section>
                <h2 class="mb-3 font-display text-xl font-semibold text-forest-900">{{ __('Vijavyo') }}</h2>
                @forelse ($upcoming as $m)
                    <a href="{{ route('meetings.show', $m) }}" class="card mb-3 flex items-center gap-4 p-4 hover:shadow-[var(--shadow-lift)]">
                        <div class="flex w-16 shrink-0 flex-col items-center rounded-xl bg-forest-800 py-2 text-ivory"><span class="text-xs font-bold uppercase">{{ $m->starts_at->translatedFormat('M') }}</span><span class="font-display text-2xl font-semibold">{{ $m->starts_at->format('j') }}</span></div>
                        <div class="min-w-0 flex-1"><p class="font-display text-lg font-semibold text-forest-900">{{ $m->title }}</p><p class="text-sm text-muted">{{ $m->starts_at->translatedFormat('l, H:i') }} · {{ $m->location ?: $m->online_method }}</p></div>
                        @if ($m->type === 'online')<span class="badge-gold">{{ __('Mtandaoni') }}</span>@endif
                    </a>
                @empty <x-empty icon="calendar" :title="__('Hakuna kikao kilichopangwa')" /> @endforelse
            </section>
            <section>
                <h2 class="mb-3 font-display text-xl font-semibold text-forest-900">{{ __('Kumbukumbu za vikao vilivyopita') }}</h2>
                <div class="card divide-y divide-line">
                    @forelse ($past as $m)
                        <a href="{{ route('meetings.show', $m) }}" class="flex items-center justify-between gap-3 p-4 hover:bg-ivory">
                            <span><span class="block font-bold">{{ $m->title }}</span><span class="text-sm text-muted">{{ $m->starts_at->translatedFormat('j F Y') }}</span></span>
                            @if ($m->minutes)<x-status :value="$m->minutes->status" />@else<span class="badge-grey">{{ __('Hakuna kumbukumbu') }}</span>@endif
                        </a>
                    @empty <p class="p-4 text-muted">{{ __('Hakuna bado.') }}</p> @endforelse
                </div>
                <div class="mt-3">{{ $past->links() }}</div>
            </section>
        </div>
        <div class="space-y-6">
            <x-card :title="__('Majukumu yaliyo wazi')" icon="check">
                @forelse ($openActions as $a)
                    <div class="border-b border-line py-2 text-sm last:border-0"><p class="font-bold">{{ $a->title }}</p><p class="text-muted">{{ $a->assignee?->displayName() ?? '—' }} @if($a->due_on)· <span @class(['font-bold text-clay-700' => $a->isOverdue()])>{{ $a->due_on->format('d/m/Y') }}</span>@endif</p></div>
                @empty <p class="text-muted">{{ __('Hakuna.') }}</p> @endforelse
            </x-card>
            @can('governance.manage_meetings')
                <x-card :title="__('Panga kikao')" icon="plus">
                    <form method="POST" action="{{ route('meetings.store') }}" class="space-y-3">@csrf
                        <x-field name="title" :label="__('Kichwa')" required :placeholder="__('Mf. Kikao cha mtandaoni — Desemba')" />
                        <x-field name="type" type="select" :label="__('Aina')" :options="['general' => __('Kikao kikuu'), 'online' => __('Mtandaoni'), 'leadership' => __('Uongozi'), 'special' => __('Maalum')]" />
                        <x-field name="starts_at" type="datetime-local" :label="__('Tarehe na saa')" required />
                        <x-field name="location" :label="__('Mahali')" />
                        <x-field name="online_method" :label="__('Njia ya mtandaoni')" :placeholder="__('Mf. WhatsApp video call')" />
                        <x-field name="organizer_member_id" type="select" :label="__('Mratibu')" :options="['' => '—'] + $members->pluck('full_name', 'id')->all()" />
                        <x-field name="agenda" type="textarea" rows="5" :label="__('Agenda (moja kila mstari)')" :value="implode(PHP_EOL, [__('Sala fupi'), __('Yatokanayo na kikao kilichopita'), __('Taarifa ya fedha'), __('Mambo mengineyo (AOB)'), __('Tarehe ya kikao kijacho'), __('Kufunga kwa sala')])" />
                        <input type="hidden" name="attendance_required" value="1">
                        <button class="btn-primary w-full">{{ __('Panga kikao') }}</button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.app>
