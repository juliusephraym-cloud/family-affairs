<x-layouts.app :title="$meeting->title" wide>
    <x-page-header :eyebrow="$meeting->starts_at->translatedFormat('l, j F Y · H:i')" :title="$meeting->title" :description="($meeting->location ?: $meeting->online_method)">
        <x-slot:actions>
            <x-status :value="$meeting->status" />
            @if ($minutesVisible && $meeting->minutes)<a href="{{ route('minutes.pdf', $meeting) }}" class="btn-ghost btn-sm"><x-icon name="download" class="size-4" />{{ __('Kumbukumbu PDF') }}</a>@endif
            @if ($canManage && ! in_array($meeting->minutes?->status, ['approved', 'archived']))<a href="{{ route('minutes.edit', $meeting) }}" class="btn-primary btn-sm"><x-icon name="file" class="size-4" />{{ __('Andika kumbukumbu') }}</a>@endif
        </x-slot:actions>
    </x-page-header>

    @if ($meeting->minutes)
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-paper p-4">
            <span class="font-bold">{{ __('Kumbukumbu:') }}</span><x-status :value="$meeting->minutes->status" />
            @if ($meeting->minutes->preparer)<span class="text-sm text-muted">{{ __('Zimeandaliwa na :n', ['n' => $meeting->minutes->preparer->displayName()]) }}</span>@endif
            @if ($meeting->minutes->approver)<span class="text-sm text-muted">· {{ __('zimeidhinishwa na :n, :d', ['n' => $meeting->minutes->approver->displayName(), 'd' => $meeting->minutes->approved_at->format('d/m/Y')]) }}</span>@endif
            @if ($meeting->minutes->review_note)<span class="text-sm text-clay-700">· {{ $meeting->minutes->review_note }}</span>@endif
            @if ($meeting->minutes->status === 'submitted')
                @can('governance.approve_minutes')
                    <div class="ml-auto flex gap-2">
                        <form method="POST" action="{{ route('minutes.decide', $meeting) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn-primary btn-sm">{{ __('Idhinisha') }}</button></form>
                        <x-confirm-button :action="route('minutes.decide', $meeting)" :label="__('Rudisha kwa marekebisho')" variant="btn-ghost btn-sm" :reason="true"><input type="hidden" name="decision" value="return"></x-confirm-button>
                    </div>
                @endcan
            @endif
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card :title="__('Agenda')" icon="list">
                <ol class="space-y-4">
                    @forelse ($meeting->agendaItems as $item)
                        <li class="flex gap-4">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-gold-100 font-display font-semibold text-gold-700">{{ $loop->iteration }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">{{ $item->title }}</p>
                                @if ($item->description)<p class="text-sm text-muted">{{ $item->description }}</p>@endif
                                @if ($minutesVisible && $item->discussion)<div class="mt-2 rounded-xl bg-ivory p-3 text-sm whitespace-pre-line">{{ $item->discussion }}</div>@endif
                                @foreach ($item->decisions as $dc)<p class="mt-2 flex items-start gap-2 text-sm"><x-icon name="gavel" class="mt-0.5 size-4 text-forest-600" /><span><strong>{{ $dc->title }}:</strong> {{ $dc->body }} <x-status :value="$dc->status" /></span></p>@endforeach
                            </div>
                        </li>
                    @empty <p class="text-muted">{{ __('Agenda haijawekwa.') }}</p> @endforelse
                </ol>
                @if ($canManage && ! in_array($meeting->minutes?->status, ['approved', 'archived']))
                    <form method="POST" action="{{ route('meetings.agenda.store', $meeting) }}" class="mt-5 flex gap-2 border-t border-line pt-4">@csrf
                        <input name="title" class="input" placeholder="{{ __('Ongeza kipengele cha agenda') }}" required><button class="btn-ghost">{{ __('Ongeza') }}</button>
                    </form>
                @endif
            </x-card>

            <x-card :title="__('Maamuzi na majukumu')" icon="gavel">
                @forelse ($meeting->decisions as $dc)
                    <div class="border-b border-line py-3 last:border-0">
                        <div class="flex justify-between gap-2"><p class="font-bold">{{ $dc->title }}</p><x-status :value="$dc->status" /></div>
                        <p class="text-sm">{{ $dc->body }}</p>
                        @if ($dc->votes_for !== null)<p class="text-xs text-muted">{{ __('Kura: ndiyo :a · hapana :b · hawakupiga :c', ['a' => $dc->votes_for, 'b' => $dc->votes_against ?? 0, 'c' => $dc->votes_abstain ?? 0]) }}</p>@endif
                    </div>
                @empty <p class="text-muted">{{ __('Hakuna uamuzi uliorekodiwa.') }}</p> @endforelse
                @if ($meeting->actionItems->isNotEmpty())
                    <h3 class="mt-5 mb-2 font-bold">{{ __('Majukumu') }}</h3>
                    @foreach ($meeting->actionItems as $a)
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2 text-sm last:border-0">
                            <span><strong>{{ $a->title }}</strong> — {{ $a->assignee?->displayName() ?? '—' }} @if($a->due_on)<span @class(['text-clay-700 font-bold' => $a->isOverdue()])>({{ $a->due_on->format('d/m/Y') }})</span>@endif</span>
                            @if ($canManage || $a->assignee_member_id === auth()->user()->member_id)
                                <form method="POST" action="{{ route('actions.status', $a) }}" x-data>@csrf
                                    <select name="status" class="input min-h-9 py-1 text-sm" @change="$el.form.requestSubmit()">@foreach (['open', 'in_progress', 'done', 'cancelled'] as $s)<option value="{{ $s }}" @selected($a->status === $s)>{{ \App\Support\Labels::status($s) }}</option>@endforeach</select>
                                </form>
                            @else <x-status :value="$a->status" /> @endif
                        </div>
                    @endforeach
                @endif
                @if ($canManage)
                    <div class="mt-5 grid gap-6 border-t border-line pt-5 lg:grid-cols-2">
                        <form method="POST" action="{{ route('meetings.decisions.store', $meeting) }}" class="space-y-2">@csrf
                            <p class="font-bold">{{ __('Rekodi uamuzi') }}</p>
                            <input name="title" class="input" placeholder="{{ __('Kichwa') }}" required>
                            <textarea name="body" class="input" rows="2" placeholder="{{ __('Uamuzi kama ulivyopitishwa') }}" required></textarea>
                            <div class="grid grid-cols-2 gap-2">
                                <select name="type" class="input"><option value="resolution">{{ __('Azimio') }}</option><option value="amendment">{{ __('Marekebisho ya katiba') }}</option><option value="financial">{{ __('Fedha') }}</option><option value="membership">{{ __('Uanachama') }}</option><option value="other">{{ __('Nyingine') }}</option></select>
                                <select name="agenda_item_id" class="input"><option value="">{{ __('Kipengele') }}</option>@foreach ($meeting->agendaItems as $it)<option value="{{ $it->id }}">{{ $loop->iteration }}. {{ \Illuminate\Support\Str::limit($it->title, 30) }}</option>@endforeach</select>
                            </div>
                            <div class="grid grid-cols-3 gap-2"><input name="votes_for" type="number" min="0" class="input" placeholder="{{ __('Ndiyo') }}"><input name="votes_against" type="number" min="0" class="input" placeholder="{{ __('Hapana') }}"><input name="votes_abstain" type="number" min="0" class="input" placeholder="{{ __('Hawakupiga') }}"></div>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="needs_confirmation" value="1" class="size-5 rounded border-sand"> {{ __('Haijakamilika / inahitaji uthibitisho') }}</label>
                            <button class="btn-ghost btn-sm">{{ __('Hifadhi uamuzi') }}</button>
                        </form>
                        <form method="POST" action="{{ route('meetings.actions.store', $meeting) }}" class="space-y-2">@csrf
                            <p class="font-bold">{{ __('Ongeza jukumu') }}</p>
                            <input name="title" class="input" placeholder="{{ __('Jukumu') }}" required>
                            <select name="assignee_member_id" class="input"><option value="">{{ __('Mhusika') }}</option>@foreach ($members as $mm)<option value="{{ $mm->id }}">{{ $mm->full_name }}</option>@endforeach</select>
                            <input type="date" name="due_on" class="input" aria-label="{{ __('Mwisho') }}">
                            <button class="btn-ghost btn-sm">{{ __('Ongeza jukumu') }}</button>
                        </form>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card :title="__('Mahudhurio')" icon="users">
                @if ($canManage)
                    <form method="POST" action="{{ route('meetings.attendance', $meeting) }}">@csrf
                        <div class="max-h-[32rem] space-y-1 overflow-y-auto pr-1">
                            @foreach ($members as $mm)
                                @php($att = $meeting->attendances->firstWhere('member_id', $mm->id))
                                <div class="flex items-center justify-between gap-2 py-1"><span class="truncate text-sm">{{ $mm->full_name }}</span>
                                    <select name="attendance[{{ $mm->id }}]" class="input min-h-9 w-36 py-1 text-sm"><option value="">—</option>@foreach (['present', 'apology', 'absent', 'excused'] as $s)<option value="{{ $s }}" @selected($att?->status === $s)>{{ \App\Support\Labels::status($s) }}</option>@endforeach</select></div>
                            @endforeach
                        </div>
                        <button class="btn-ghost btn-sm mt-3 w-full">{{ __('Hifadhi mahudhurio') }}</button>
                    </form>
                    @can('finance.manage')
                        @if ($meeting->attendances->where('status', 'absent')->isNotEmpty())
                            <div class="mt-4 border-t border-line pt-3 text-sm">
                                <p>{{ $absenceRule?->title() }}: <strong>{{ $absenceRule?->scalar() ? \App\Support\Money::format((int) $absenceRule->scalar()) : '—' }}</strong> @unless($absenceRule?->isConfirmed())<x-needs-confirmation />@endunless</p>
                                <x-confirm-button :action="route('meetings.fines', $meeting)" :label="__('Weka faini kwa wasiohudhuria bila udhuru')" variant="btn-ghost btn-sm mt-2" :confirm="__('Faini zitawekwa kwa waliowekwa \'Hakuhudhuria\' tu. Endelea?')" />
                            </div>
                        @endif
                    @endcan
                @else
                    <p class="text-sm text-muted">{{ __(':n walihudhuria', ['n' => $meeting->attendances->where('status', 'present')->count()]) }}</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">@foreach ($meeting->attendances->where('status', 'present') as $a)<span class="badge-grey">{{ $a->member->displayName() }}</span>@endforeach</div>
                @endif
            </x-card>
            @if ($canManage)
                <x-card :title="__('Hariri kikao')">
                    <form method="POST" action="{{ route('meetings.update', $meeting) }}" class="space-y-3">@csrf @method('PUT')
                        <x-field name="title" :label="__('Kichwa')" :value="$meeting->title" required />
                        <x-field name="type" type="select" :label="__('Aina')" :value="$meeting->type" :options="['general' => __('Kikao kikuu'), 'online' => __('Mtandaoni'), 'leadership' => __('Uongozi'), 'special' => __('Maalum')]" />
                        <x-field name="starts_at" type="datetime-local" :label="__('Tarehe na saa')" :value="$meeting->starts_at->format('Y-m-d\TH:i')" required />
                        <x-field name="location" :label="__('Mahali')" :value="$meeting->location" />
                        <x-field name="online_method" :label="__('Njia ya mtandaoni')" :value="$meeting->online_method" />
                        <x-field name="status" type="select" :label="__('Hali')" :value="$meeting->status" :options="\App\Support\Labels::options(['planned', 'held', 'cancelled'])" />
                        <input type="hidden" name="attendance_required" value="1">
                        <button class="btn-ghost w-full">{{ __('Hifadhi') }}</button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
