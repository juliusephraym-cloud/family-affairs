<x-layouts.app :title="$event->title" wide>
    @php($memberOpts = $members->pluck('full_name', 'id')->all())
    <section class="relative mb-8 overflow-hidden rounded-[1.75rem] bg-forest-900 p-6 text-ivory sm:p-10">
        <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(45% 90% at 100% 0%, rgb(201 154 46 / .3), transparent 70%)"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="eyebrow text-gold-400">{{ $event->starts_at->translatedFormat('l, j F Y · H:i') }}</p>
                <h1 class="mt-2 font-display text-3xl font-semibold sm:text-5xl">{{ $event->title }}</h1>
                <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-forest-100/85">
                    @if ($event->venue)<span class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-4" />{{ $event->venue }}</span>@endif
                    @if ($event->organizer)<span class="flex items-center gap-1.5"><x-icon name="users" class="size-4" />{{ __('Mratibu: :n', ['n' => $event->organizer->displayName()]) }}</span>@endif
                    @if ($event->visibility !== 'members')<span class="badge-gold">{{ $event->visibility === 'leaders' ? __('Uongozi tu') : __('Walioalikwa tu') }}</span>@endif
                </div>
                @if ($event->description)<p class="mt-4 max-w-2xl text-forest-100/90">{{ $event->description }}</p>@endif
            </div>
            <form method="POST" action="{{ route('events.rsvp', $event) }}" class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15" x-data="{ r: '{{ $myRsvp?->response }}' }">@csrf
                <p class="mb-2 text-sm font-bold">{{ __('Utahudhuria?') }}</p>
                <div class="flex gap-2">
                    @foreach (['yes' => __('Ndiyo'), 'maybe' => __('Labda'), 'no' => __('Hapana')] as $k => $l)
                        <button name="response" value="{{ $k }}" class="rounded-full px-4 py-2 text-sm font-bold ring-1 ring-white/25" :class="r === '{{ $k }}' ? 'bg-gold-400 text-forest-950' : 'hover:bg-white/10'">{{ $l }}</button>
                    @endforeach
                </div>
                <label class="mt-2 flex items-center gap-2 text-sm">{{ __('Wageni wa ziada') }} <input type="number" name="guests" min="0" max="20" value="{{ $myRsvp?->guests ?? 0 }}" class="w-16 rounded-lg border-0 bg-white/90 px-2 py-1 text-ink"></label>
            </form>
        </div>
    </section>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php($r = $event->readiness())
        <div class="card card-pad"><p class="text-sm text-muted">{{ __('Utayari') }}</p><p class="stat">{{ $r === null ? '—' : $r.'%' }}</p><div class="mt-2 h-2 overflow-hidden rounded-full bg-cream"><div class="h-full bg-forest-600" style="width: {{ $r ?? 0 }}%"></div></div></div>
        <div class="card card-pad"><p class="text-sm text-muted">{{ __('Watahudhuria') }}</p><p class="stat">{{ $event->rsvps->where('response', 'yes')->count() + $event->rsvps->where('response', 'yes')->sum('guests') }}</p><p class="text-sm text-muted">{{ __('labda :m · hapana :n', ['m' => $event->rsvps->where('response', 'maybe')->count(), 'n' => $event->rsvps->where('response', 'no')->count()]) }}</p></div>
        <div class="card card-pad"><p class="text-sm text-muted">{{ __('Bajeti') }}</p><p class="stat text-2xl"><x-money :amount="$event->budget_amount ?? $event->budgetLines->sum('estimated_amount')" /></p></div>
        <div class="card card-pad"><p class="text-sm text-muted">{{ __('Matumizi halisi') }}</p><p class="stat text-2xl"><x-money :amount="$event->budgetLines->sum('actual_amount')" /></p><p class="text-xs text-muted">{{ __('Yaliyolipwa kupitia Hazina: :a', ['a' => \App\Support\Money::format($event->expenses->where('status', 'paid')->sum('amount'))]) }}</p></div>
    </div>

    {{-- Committees --}}
    <x-card :title="__('Kamati')" icon="users" class="mb-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($event->committees as $c)
                <div class="rounded-2xl border border-line bg-ivory/60 p-4" x-data="{ edit: false }">
                    <div class="flex items-center justify-between"><p class="font-display text-lg font-semibold text-forest-900">{{ $c->name }}</p>@if($canManage)<button type="button" class="text-xs font-bold text-forest-700 hover:underline" @click="edit = !edit">{{ __('Hariri') }}</button>@endif</div>
                    <p class="text-sm text-muted">{{ __('Kiongozi: :n', ['n' => $c->lead?->displayName() ?? '—']) }}</p>
                    <div class="mt-2 flex flex-wrap gap-1">@foreach ($c->members as $m)<span class="badge-grey">{{ $m->displayName() }}</span>@endforeach</div>
                    @if ($canManage)
                        <form x-show="edit" x-cloak method="POST" action="{{ route('events.committees.members', [$event, $c]) }}" class="mt-3 space-y-2">@csrf
                            <select name="lead_member_id" class="input"><option value="">{{ __('Kiongozi') }}</option>@foreach ($members as $m)<option value="{{ $m->id }}" @selected($c->lead_member_id === $m->id)>{{ $m->full_name }}</option>@endforeach</select>
                            <div class="max-h-40 overflow-y-auto rounded-xl border border-line bg-paper p-2">@foreach ($members as $m)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="members[]" value="{{ $m->id }}" class="size-4 rounded border-sand" @checked($c->members->contains('id', $m->id))> {{ $m->full_name }}</label>@endforeach</div>
                            <button class="btn-ghost btn-sm">{{ __('Hifadhi') }}</button>
                        </form>
                    @endif
                </div>
            @endforeach
            @if ($canManage)
                <form method="POST" action="{{ route('events.committees.store', $event) }}" class="flex flex-col justify-center gap-2 rounded-2xl border border-dashed border-sand p-4">@csrf
                    <input name="name" class="input" placeholder="{{ __('Kamati mpya (mf. Usafiri)') }}" required>
                    <button class="btn-ghost btn-sm">{{ __('Ongeza kamati') }}</button>
                </form>
            @endif
        </div>
    </x-card>

    {{-- Tasks --}}
    <section class="mb-6">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-2xl font-semibold text-forest-900">{{ __('Majukumu') }}</h2>
            <div class="flex gap-1">
                @foreach (['board' => ['columns', __('Ubao')], 'list' => ['list', __('Orodha')], 'timeline' => ['clock', __('Mfuatano')]] as $k => [$ic, $l])
                    <a href="{{ route('events.show', [$event, 'view' => $k]) }}" @class(['tab flex items-center gap-1.5', 'tab-active' => $view === $k])><x-icon :name="$ic" class="size-4" />{{ $l }}</a>
                @endforeach
            </div>
        </div>
        @php($statusLabels = \App\Support\Labels::options(\App\Models\EventTask::STATUSES))
        @php($canEditTask = fn ($t) => $canTasks || $t->owner_member_id === auth()->user()->member_id || $t->committee?->lead_member_id === auth()->user()->member_id)
        @if ($view === 'board')
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach (\App\Models\EventTask::STATUSES as $st)
                    <div class="rounded-2xl bg-cream/60 p-3">
                        <p class="mb-3 flex items-center justify-between px-1 text-sm font-bold">{{ $statusLabels[$st] }} <span class="badge-grey">{{ $event->tasks->where('status', $st)->count() }}</span></p>
                        <div class="space-y-2">
                            @foreach ($event->tasks->where('status', $st) as $t)
                                <div class="card p-3">
                                    <p class="font-bold">{{ $t->title }}</p>
                                    <p class="text-xs text-muted">{{ $t->committee?->name }} · {{ $t->owner?->displayName() ?? __('Hajapangiwa') }}</p>
                                    @if ($t->due_at)<p @class(['mt-1 text-xs', 'font-bold text-clay-700' => $t->isOverdue(), 'text-muted' => ! $t->isOverdue()])>{{ __('Mwisho :d', ['d' => $t->due_at->translatedFormat('j M H:i')]) }}</p>@endif
                                    @if ($canEditTask($t))
                                        <form method="POST" action="{{ route('events.tasks.status', [$event, $t]) }}" class="mt-2 flex flex-wrap gap-1">@csrf
                                            @foreach (\App\Models\EventTask::STATUSES as $to)@if($to !== $st)<button name="status" value="{{ $to }}" class="rounded-full border border-line px-2 py-0.5 text-xs font-bold hover:bg-forest-50">→ {{ $statusLabels[$to] }}</button>@endif @endforeach
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($view === 'list')
            <div class="card overflow-hidden"><div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Jukumu') }}</th><th>{{ __('Kamati') }}</th><th>{{ __('Mhusika') }}</th><th>{{ __('Mwisho') }}</th><th>{{ __('Hali') }}</th></tr></thead>
                <tbody>@forelse ($event->tasks as $t)
                    <tr><td class="font-bold">{{ $t->title }}</td><td>{{ $t->committee?->name }}</td><td>{{ $t->owner?->displayName() }}</td><td @class(['font-bold text-clay-700' => $t->isOverdue()])>{{ $t->due_at?->format('d/m H:i') }}</td>
                        <td>@if($canEditTask($t))<form method="POST" action="{{ route('events.tasks.status', [$event, $t]) }}" x-data>@csrf<select name="status" class="input min-h-9 py-1 text-sm" @change="$el.form.requestSubmit()">@foreach ($statusLabels as $k => $l)<option value="{{ $k }}" @selected($t->status === $k)>{{ $l }}</option>@endforeach</select></form>@else<x-status :value="$t->status" />@endif</td></tr>
                @empty <tr><td colspan="5" class="py-6 text-center text-muted">{{ __('Hakuna majukumu.') }}</td></tr> @endforelse</tbody>
            </table></div></div>
        @else
            <ol class="relative ml-3 space-y-4 border-l-2 border-gold-200 pl-6">
                @forelse ($event->tasks->sortBy(fn ($t) => $t->due_at?->timestamp ?? PHP_INT_MAX) as $t)
                    <li class="relative"><span @class(['absolute -left-[2.05rem] top-1 size-4 rounded-full ring-4 ring-ivory', 'bg-forest-600' => $t->status === 'done', 'bg-clay-500' => $t->isOverdue(), 'bg-gold-400' => $t->status !== 'done' && ! $t->isOverdue()])></span>
                        <p class="text-xs font-bold text-muted">{{ $t->due_at?->translatedFormat('l j M · H:i') ?? __('Bila tarehe') }}</p><p class="font-bold">{{ $t->title }}</p><p class="text-sm text-muted">{{ $t->committee?->name }} · {{ $t->owner?->displayName() }}</p></li>
                @empty <li class="text-muted">{{ __('Hakuna majukumu.') }}</li> @endforelse
            </ol>
        @endif
        @if ($canTasks)
            <form method="POST" action="{{ route('events.tasks.store', $event) }}" class="card card-pad mt-4 grid gap-3 md:grid-cols-[2fr_1fr_1fr_1fr_auto] md:items-end">@csrf
                <x-field name="title" :label="__('Jukumu jipya')" required />
                <x-field name="event_committee_id" type="select" :label="__('Kamati')" :options="['' => '—'] + $event->committees->pluck('name', 'id')->all()" />
                <x-field name="owner_member_id" type="select" :label="__('Mhusika')" :options="['' => '—'] + $memberOpts" />
                <x-field name="due_at" type="datetime-local" :label="__('Mwisho')" />
                <div class="space-y-2"><label class="flex items-center gap-2 text-xs"><input type="checkbox" name="notify" value="1" class="size-4 rounded border-sand"> WhatsApp</label><button class="btn-primary btn-sm">{{ __('Ongeza') }}</button></div>
            </form>
        @endif
    </section>

    {{-- Budget --}}
    <x-card :title="__('Bajeti na watoa huduma')" icon="wallet" class="mb-6">
        <div class="table-wrap"><table class="table">
            <thead><tr><th>{{ __('Kipengele') }}</th><th>{{ __('Kamati / mhusika') }}</th><th>{{ __('Mtoa huduma') }}</th><th class="num">{{ __('Makadirio') }}</th><th class="num">{{ __('Halisi') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($event->budgetLines as $l)
                    <tr @class(['opacity-70' => $l->is_done])>
                        <td class="font-bold">@if($l->is_done)<x-icon name="check" class="mr-1 inline size-4 text-forest-600" />@endif{{ $l->label }}</td>
                        <td class="text-sm">{{ $l->committee?->name }} {{ $l->responsible ? '· '.$l->responsible->displayName() : '' }}</td>
                        <td class="text-sm">{{ $l->vendor?->name }}@if($canManage && $l->vendor?->phone)<span class="block text-xs text-muted">{{ \App\Support\Phone::display($l->vendor->phone) }}</span>@endif</td>
                        <td class="num"><x-money :amount="$l->estimated_amount" /></td>
                        <td class="num">@if($canManage)<form method="POST" action="{{ route('events.budget.update', [$event, $l]) }}" class="flex items-center justify-end gap-2">@csrf<input name="actual_amount" value="{{ $l->actual_amount }}" class="input min-h-9 w-28 py-1 text-right text-sm" inputmode="numeric"><input type="hidden" name="is_done" value="0"><input type="checkbox" name="is_done" value="1" class="size-4 rounded border-sand" @checked($l->is_done) title="{{ __('Imekamilika') }}"><button class="text-xs font-bold text-forest-700">{{ __('Hifadhi') }}</button></form>@else<x-money :amount="$l->actual_amount" />@endif</td>
                        <td></td>
                    </tr>
                @empty <tr><td colspan="6" class="py-6 text-center text-muted">{{ __('Bajeti haijawekwa.') }}</td></tr> @endforelse
                @if ($event->budgetLines->isNotEmpty())<tr class="font-bold"><td colspan="3">{{ __('Jumla') }}</td><td class="num"><x-money :amount="$event->budgetLines->sum('estimated_amount')" /></td><td class="num"><x-money :amount="$event->budgetLines->sum('actual_amount')" /></td><td></td></tr>@endif
            </tbody>
        </table></div>
        @if ($canManage)
            <div class="mt-4 grid gap-4 border-t border-line pt-4 lg:grid-cols-2">
                <form method="POST" action="{{ route('events.budget.store', $event) }}" class="grid gap-2 sm:grid-cols-2">@csrf
                    <input name="label" class="input sm:col-span-2" placeholder="{{ __('Kipengele (mf. Mbuzi 1)') }}" required>
                    <input name="estimated_amount" class="input" placeholder="{{ __('Makadirio') }}" inputmode="numeric" required>
                    <select name="event_committee_id" class="input"><option value="">{{ __('Kamati') }}</option>@foreach ($event->committees as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                    <select name="responsible_member_id" class="input"><option value="">{{ __('Mhusika') }}</option>@foreach ($members as $m)<option value="{{ $m->id }}">{{ $m->full_name }}</option>@endforeach</select>
                    <select name="vendor_id" class="input"><option value="">{{ __('Mtoa huduma') }}</option>@foreach ($vendors as $v)<option value="{{ $v->id }}">{{ $v->service }} — {{ $v->name }}</option>@endforeach</select>
                    <div><button class="btn-ghost btn-sm">{{ __('Ongeza kwenye bajeti') }}</button></div>
                </form>
                <form method="POST" action="{{ route('vendors.store') }}" class="grid gap-2 sm:grid-cols-2">@csrf
                    <input name="name" class="input" placeholder="{{ __('Jina la mtoa huduma') }}" required><input name="service" class="input" placeholder="{{ __('Huduma (chakula, keki…)') }}" required>
                    <input name="phone" class="input" placeholder="{{ __('Simu') }}"><div><button class="btn-ghost btn-sm">{{ __('Ongeza mtoa huduma') }}</button></div>
                </form>
            </div>
            <p class="mt-3 text-xs text-muted">{{ __('Malipo halisi yanapitia Hazina → Matumizi (yanaunganishwa na tukio hili) ili yaingie kwenye ledger.') }}</p>
        @endif
    </x-card>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Wanaohudhuria')">
            <div class="flex flex-wrap gap-2">@forelse ($event->rsvps->where('response', 'yes') as $rv)<span class="flex items-center gap-1.5 rounded-full border border-line bg-ivory py-1 pr-3 pl-1 text-sm"><x-avatar :member="$rv->member" size="size-6 text-[0.6rem]" />{{ $rv->member->displayName() }}@if($rv->guests) <span class="text-muted">+{{ $rv->guests }}</span>@endif</span>@empty<p class="text-muted">{{ __('Bado hakuna.') }}</p>@endforelse</div>
        </x-card>
        @if ($canManage)
            <x-card :title="__('Hariri tukio, ripoti na mafunzo')">
                <form method="POST" action="{{ route('events.update', $event) }}" class="space-y-3">@csrf @method('PUT')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="title" :label="__('Jina')" :value="$event->title" required />
                        <x-field name="type" type="select" :label="__('Aina')" :value="$event->type" :options="array_combine(\App\Models\Event::TYPES, \App\Models\Event::TYPES)" />
                        <x-field name="starts_at" type="datetime-local" :label="__('Linaanza')" :value="$event->starts_at->format('Y-m-d\TH:i')" required />
                        <x-field name="venue" :label="__('Ukumbi')" :value="$event->venue" />
                        <x-field name="visibility" type="select" :label="__('Wanaoona')" :value="$event->visibility" :options="['members' => __('Wote'), 'leaders' => __('Uongozi'), 'invited' => __('Walioalikwa')]" />
                        <x-field name="status" type="select" :label="__('Hali')" :value="$event->status" :options="\App\Support\Labels::options(['planned', 'confirmed', 'done', 'cancelled'])" />
                        <x-field name="budget_amount" :label="__('Bajeti')" :value="$event->budget_amount" />
                        <x-field name="organizer_member_id" type="select" :label="__('Mratibu')" :value="$event->organizer_member_id" :options="['' => '—'] + $memberOpts" />
                    </div>
                    <x-field name="description" type="textarea" rows="2" :label="__('Maelezo')" :value="$event->description" />
                    <x-field name="report" type="textarea" rows="3" :label="__('Ripoti ya tukio')" :value="$event->report" />
                    <x-field name="lessons" type="textarea" rows="2" :label="__('Tulichojifunza')" :value="$event->lessons" />
                    @foreach ($event->invitees as $inv)<input type="hidden" name="invitees[]" value="{{ $inv->id }}">@endforeach
                    <button class="btn-ghost">{{ __('Hifadhi') }}</button>
                </form>
            </x-card>
        @elseif ($event->report)
            <x-card :title="__('Ripoti ya tukio')"><p class="whitespace-pre-line">{{ $event->report }}</p>@if($event->lessons)<p class="mt-3 text-sm text-muted">{{ $event->lessons }}</p>@endif</x-card>
        @endif
    </div>
</x-layouts.app>
