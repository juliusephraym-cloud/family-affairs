<x-layouts.app :title="__('Matukio')" wide>
    @php($types = ['gathering' => __('Mkusanyiko wa familia'), 'easter' => __('Pasaka'), 'birthday' => __('Siku ya kuzaliwa'), 'anniversary' => __('Kumbukumbu ya ndoa'), 'milestone' => __('Hatua ya mtoto (ubarikio, kipaimara…)'), 'celebration' => __('Sherehe'), 'bereavement' => __('Msiba'), 'charity' => __('Hisani/sadaka'), 'other' => __('Nyingine')])
    <x-page-header :eyebrow="__('Kalenda ya familia')" :title="__('Matukio')" :description="__('Mikusanyiko, sherehe, hatua za watoto, na siku za kuzaliwa — mahali pamoja.')" />

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <section class="card overflow-hidden">
            <header class="flex items-center justify-between border-b border-line px-5 py-4">
                <a href="{{ route('events.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn-ghost btn-sm" aria-label="{{ __('Mwezi uliopita') }}">‹</a>
                <h2 class="font-display text-2xl font-semibold text-forest-900">{{ $month->translatedFormat('F Y') }}</h2>
                <a href="{{ route('events.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn-ghost btn-sm" aria-label="{{ __('Mwezi ujao') }}">›</a>
            </header>
            {{-- Desktop grid --}}
            <div class="hidden md:block">
                <div class="grid grid-cols-7 border-b border-line bg-ivory text-center text-xs font-bold tracking-wider text-muted uppercase">
                    @foreach (range(0, 6) as $i)<div class="py-2">{{ now()->startOfWeek()->addDays($i)->translatedFormat('D') }}</div>@endforeach
                </div>
                <div class="grid grid-cols-7">
                    @php($day = $month->copy()->startOfWeek())
                    @while ($day->lte($month->copy()->endOfMonth()->endOfWeek()))
                        @php($dayEvents = $events->filter(fn ($e) => $e->starts_at->isSameDay($day)))
                        @php($dayBirthdays = $day->month === $month->month ? $birthdays->filter(fn ($b) => $b->date_of_birth->day === $day->day) : collect())
                        <div @class(['min-h-28 border-r border-b border-line p-1.5 text-sm', 'bg-ivory/60 text-muted' => $day->month !== $month->month, 'bg-gold-50' => $day->isToday()])>
                            <p @class(['mb-1 text-right text-xs font-bold', 'text-clay-600' => $day->isToday()])>{{ $day->day }}</p>
                            @foreach ($dayEvents as $e)
                                <a href="{{ route('events.show', $e) }}" class="mb-1 block truncate rounded-md bg-forest-800 px-1.5 py-0.5 text-xs font-bold text-ivory hover:bg-forest-700">{{ $e->starts_at->format('H:i') }} {{ $e->title }}</a>
                            @endforeach
                            @foreach ($dayBirthdays as $b)
                                <a href="{{ route('members.show', $b) }}" class="mb-1 flex items-center gap-1 truncate rounded-md bg-gold-100 px-1.5 py-0.5 text-xs font-bold text-gold-700"><x-icon name="cake" class="size-3 shrink-0" />{{ $b->displayName() }}</a>
                            @endforeach
                        </div>
                        @php($day->addDay())
                    @endwhile
                </div>
            </div>
            {{-- Mobile agenda --}}
            <ul class="divide-y divide-line md:hidden">
                @php($items = $events->map(fn ($e) => ['d' => $e->starts_at, 'e' => $e])->concat($birthdays->map(fn ($b) => ['d' => $month->copy()->day($b->date_of_birth->day), 'b' => $b]))->sortBy(fn ($i) => $i['d']->timestamp))
                @forelse ($items as $i)
                    <li class="flex items-center gap-3 px-5 py-3">
                        <span class="w-10 text-center"><span class="block font-display text-xl font-semibold">{{ $i['d']->day }}</span><span class="text-xs text-muted">{{ $i['d']->translatedFormat('D') }}</span></span>
                        @isset($i['e'])<a href="{{ route('events.show', $i['e']) }}" class="font-bold text-forest-800">{{ $i['e']->title }}</a>@else<span class="flex items-center gap-1.5 font-bold text-gold-700"><x-icon name="cake" class="size-4" />{{ $i['b']->displayName() }}</span>@endisset
                    </li>
                @empty <li class="px-5 py-8 text-center text-muted">{{ __('Hakuna kitu mwezi huu.') }}</li> @endforelse
            </ul>
        </section>

        <div class="space-y-6">
            <x-card :title="__('Yajayo')">
                @forelse ($upcoming as $e)
                    <a href="{{ route('events.show', $e) }}" class="flex items-center gap-3 border-b border-line py-2.5 last:border-0 hover:bg-ivory">
                        <span class="w-12 shrink-0 rounded-lg bg-clay-50 py-1 text-center"><span class="block text-[0.65rem] font-bold text-clay-700 uppercase">{{ $e->starts_at->translatedFormat('M') }}</span><span class="font-display text-lg font-semibold">{{ $e->starts_at->format('j') }}</span></span>
                        <span class="min-w-0"><span class="block truncate font-bold">{{ $e->title }}</span><span class="text-sm text-muted">{{ $types[$e->type] ?? $e->type }}</span></span>
                    </a>
                @empty <p class="text-muted">{{ __('Hakuna.') }}</p> @endforelse
            </x-card>
            @can('events.manage')
                <x-card :title="__('Unda tukio')" icon="plus">
                    <form method="POST" action="{{ route('events.store') }}" class="space-y-3" x-data="{ vis: 'members' }">@csrf
                        <x-field name="title" :label="__('Jina la tukio')" required />
                        <x-field name="type" type="select" :label="__('Aina')" :options="$types" />
                        <x-field name="starts_at" type="datetime-local" :label="__('Linaanza')" required />
                        <x-field name="venue" :label="__('Ukumbi / mahali')" />
                        <x-field name="organizer_member_id" type="select" :label="__('Mwenyekiti wa shughuli')" :options="['' => '—'] + $members->pluck('full_name', 'id')->all()" />
                        <x-field name="budget_amount" :label="__('Bajeti (TSh)')" inputmode="numeric" />
                        <x-field name="fund_id" type="select" :label="__('Mfuko wa gharama')" :options="['' => '—'] + $funds->mapWithKeys(fn ($f) => [$f->id => $f->label()])->all()" />
                        <label class="block"><span class="field-label">{{ __('Nani anaona tukio hili') }}</span>
                            <select name="visibility" class="input" x-model="vis"><option value="members">{{ __('Wanafamilia wote') }}</option><option value="leaders">{{ __('Uongozi tu') }}</option><option value="invited">{{ __('Walioalikwa tu') }}</option></select></label>
                        <div x-show="vis === 'invited'" x-cloak class="max-h-40 space-y-1 overflow-y-auto rounded-xl border border-line p-2">
                            @foreach ($members as $m)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="invitees[]" value="{{ $m->id }}" class="size-4 rounded border-sand"> {{ $m->full_name }}</label>@endforeach
                        </div>
                        <fieldset><legend class="field-label">{{ __('Kamati za kuanza nazo') }}</legend>
                            <div class="grid grid-cols-2 gap-1 text-sm">
                                @foreach ([__('Chakula'), __('Mapambo'), __('Vinywaji'), __('Mapokezi'), __('Picha na Video'), __('Watoto'), __('Keki na ubarikio'), __('Muziki na MC'), __('Maombi na ufunguzi')] as $i => $c)
                                    <label class="flex items-center gap-2"><input type="checkbox" name="committees[{{ $i }}]" value="{{ $c }}" class="size-4 rounded border-sand"> {{ $c }}</label>
                                @endforeach
                            </div></fieldset>
                        <x-field name="description" type="textarea" rows="3" :label="__('Maelezo')" />
                        <button class="btn-primary w-full">{{ __('Unda tukio') }}</button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.app>
