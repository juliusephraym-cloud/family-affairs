<x-layouts.app :title="__('Nyumbani')">
    {{-- Greeting --}}
    <section class="relative mb-8 overflow-hidden rounded-[1.75rem] bg-forest-900 px-6 py-8 text-ivory sm:px-10 sm:py-10 animate-rise">
        <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(50% 80% at 100% 0%, rgb(201 154 46 / .25), transparent 70%), radial-gradient(40% 60% at 0% 100%, rgb(192 103 77 / .18), transparent 70%);"></div>
        <svg class="pointer-events-none absolute -right-6 -bottom-10 h-56 text-gold-400/10" viewBox="0 0 200 200" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="100" cy="100" r="90"/><circle cx="100" cy="100" r="66"/><circle cx="100" cy="100" r="42"/><circle cx="100" cy="100" r="18"/></svg>
        <div class="relative">
            <p class="text-sm font-bold tracking-wide text-gold-200">{{ now()->translatedFormat('l, j F Y') }}</p>
            <h1 class="mt-2 font-display text-3xl font-semibold sm:text-5xl">{{ $greeting }}, <span class="text-gold-200 italic">{{ $member->displayName() }}</span></h1>
            <p class="mt-3 max-w-xl text-forest-100/85">{{ \App\Support\Settings::get('family.welcome') }}</p>
            @if ($pending->isNotEmpty())
                <div class="mt-6 flex flex-wrap gap-2">
                    @foreach ($pending as $p)
                        <a href="{{ $p['url'] }}" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-bold text-white ring-1 ring-white/15 hover:bg-white/15">
                            <span class="size-2 rounded-full bg-gold-400"></span>{{ $p['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Next event --}}
        <div class="lg:col-span-2">
            @if ($nextEvent)
                <a href="{{ route('events.show', $nextEvent) }}" class="card group flex h-full flex-col gap-5 p-5 transition hover:shadow-[var(--shadow-lift)] sm:flex-row sm:p-6">
                    <div class="flex w-24 shrink-0 flex-col items-center self-start overflow-hidden rounded-2xl border border-line bg-ivory text-center">
                        <span class="w-full bg-clay-600 py-1 text-xs font-bold tracking-widest text-white uppercase">{{ $nextEvent->starts_at->translatedFormat('M') }}</span>
                        <span class="py-1 font-display text-4xl font-semibold text-forest-900">{{ $nextEvent->starts_at->format('j') }}</span>
                        <span class="pb-2 text-xs text-muted">{{ $nextEvent->starts_at->translatedFormat('D') }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="eyebrow">{{ __('Tukio lijalo la familia') }}</p>
                        <h2 class="mt-1 font-display text-2xl font-semibold text-forest-900 group-hover:underline">{{ $nextEvent->title }}</h2>
                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-ink-soft">
                            <span class="flex items-center gap-1.5"><x-icon name="clock" class="size-4 text-gold-600" />{{ $nextEvent->starts_at->format('H:i') }} · {{ $nextEvent->starts_at->diffForHumans() }}</span>
                            @if ($nextEvent->venue)<span class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-4 text-gold-600" />{{ $nextEvent->venue }}</span>@endif
                        </div>
                        @if ($nextEvent->description)<p class="mt-3 line-clamp-2 text-muted">{{ $nextEvent->description }}</p>@endif
                    </div>
                </a>
            @else
                <x-empty icon="calendar" :title="__('Hakuna tukio lililopangwa bado')" :text="__('Matukio ya familia yatakapopangwa yataonekana hapa.')" class="h-full" />
            @endif
        </div>

        {{-- My contribution status: private --}}
        @if ($summary)
            <section class="card card-pad relative">
                <div class="flex items-center justify-between">
                    <h2 class="card-title flex items-center gap-2"><x-icon name="wallet" class="size-5 text-gold-600" />{{ __('Michango yangu') }}</h2>
                    <span class="flex items-center gap-1 text-xs text-muted" title="{{ __('Wewe na Mhazini tu ndio mnaona hii') }}"><x-icon name="lock" class="size-3.5" />{{ __('Binafsi') }}</span>
                </div>
                @if ($summary['overdue'] > 0)
                    <p class="mt-4 text-sm text-muted">{{ __('Salio linalodaiwa') }}</p>
                    <p class="stat text-clay-700">{{ \App\Support\Money::format($summary['overdue']) }}</p>
                    <p class="mt-1 text-sm text-muted">{{ trans_choice('{1} mwezi :n umepita|[2,*] miezi :n imepita', $summary['months_in_arrears'], ['n' => $summary['months_in_arrears']]) }}</p>
                @else
                    <p class="mt-4 flex items-center gap-2 font-display text-2xl font-semibold text-forest-800"><x-icon name="check" class="size-7 text-forest-600" />{{ __('Uko safi!') }}</p>
                    <p class="mt-1 text-sm text-muted">{{ __('Hakuna mchango uliopitwa na muda. Asante kwa uaminifu wako.') }}</p>
                @endif
                @if ($summary['credit'] > 0)<p class="mt-2 text-sm text-forest-700">{{ __('Malipo ya ziada:') }} <x-money :amount="$summary['credit']" class="font-bold" /></p>@endif
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ route('finance.pay') }}" class="btn-primary btn-sm"><x-icon name="upload" class="size-4" />{{ __('Tuma risiti') }}</a>
                    <a href="{{ route('finance.mine') }}" class="btn-ghost btn-sm">{{ __('Taarifa kamili') }}</a>
                </div>
            </section>
        @endif
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Matangazo rasmi')" icon="megaphone" :href="route('community.index')" class="lg:col-span-2">
            @forelse ($announcements as $a)
                <a href="{{ route('community.show', $a) }}" class="-mx-2 flex gap-3 rounded-xl p-2 hover:bg-ivory">
                    <span @class(['mt-1.5 size-2.5 shrink-0 rounded-full', 'bg-clay-500' => $a->is_important, 'bg-gold-500' => ! $a->is_important])></span>
                    <span class="min-w-0">
                        <span class="flex items-center gap-2 font-bold text-forest-900">@if($a->is_pinned)<x-icon name="pin" class="size-4 text-gold-600" />@endif{{ $a->title }}</span>
                        <span class="line-clamp-2 text-sm text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($a->body), 160) }}</span>
                        <span class="text-xs text-muted">{{ $a->published_at?->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <p class="text-muted">{{ __('Hakuna tangazo rasmi kwa sasa.') }}</p>
            @endforelse
        </x-card>

        <x-card :title="__('Siku za kuzaliwa')" icon="cake">
            @forelse ($birthdays as $b)
                <div class="flex items-center gap-3 py-1.5">
                    <x-avatar :member="$b['member']" size="size-10" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold">{{ $b['member']->displayName() }}</p>
                        <p class="text-sm text-muted">{{ $b['date']->translatedFormat('j F') }}</p>
                    </div>
                    @if ($b['date']->isToday())<span class="badge-gold">{{ __('Leo!') }}</span>@else<span class="text-sm text-muted">{{ trans_choice('{1} kesho|[2,*] siku :n', (int) now()->startOfDay()->diffInDays($b['date']), ['n' => (int) now()->startOfDay()->diffInDays($b['date'])]) }}</span>@endif
                </div>
            @empty
                <p class="text-muted">{{ __('Hakuna siku ya kuzaliwa ndani ya siku 30 zijazo.') }}</p>
            @endforelse
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Kikao kijacho')" icon="gavel" :href="route('meetings.index')">
            @if ($nextMeeting)
                <a href="{{ route('meetings.show', $nextMeeting) }}" class="block hover:underline"><p class="font-display text-xl font-semibold text-forest-900">{{ $nextMeeting->title }}</p></a>
                <p class="mt-1 text-sm text-ink-soft">{{ $nextMeeting->starts_at->translatedFormat('l, j F · H:i') }}</p>
                <p class="text-sm text-muted">{{ $nextMeeting->location ?: $nextMeeting->online_method }}</p>
            @else
                <p class="text-muted">{{ __('Hakuna kikao kilichopangwa.') }}</p>
            @endif
        </x-card>

        <x-card :title="__('Majukumu yangu')" icon="check" class="lg:col-span-2">
            @if ($myTasks->isEmpty() && $myActions->isEmpty())
                <p class="text-muted">{{ __('Huna jukumu lililo wazi. Ahsante!') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($myTasks as $t)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <a href="{{ route('events.show', $t->event) }}" class="min-w-0"><span class="block truncate font-bold">{{ $t->title }}</span><span class="text-sm text-muted">{{ $t->event->title }}</span></a>
                            <span class="flex items-center gap-2">@if($t->isOverdue())<span class="badge-clay">{{ __('Imechelewa') }}</span>@endif<x-status :value="$t->status" /></span>
                        </li>
                    @endforeach
                    @foreach ($myActions as $a)
                        <li class="flex items-center justify-between gap-3 py-2.5">
                            <span class="min-w-0"><span class="block truncate font-bold">{{ $a->title }}</span><span class="text-sm text-muted">{{ $a->due_on ? __('Mwisho :d', ['d' => $a->due_on->translatedFormat('j M')]) : '' }}</span></span>
                            <x-status :value="$a->status" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    @if ($memories->isNotEmpty())
        <x-card :title="__('Kumbukumbu za karibuni')" icon="image" :href="route('memories.index')" class="mt-6">
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                @foreach ($memories as $m)
                    <a href="{{ route('memories.show', $m->album) }}" class="group relative aspect-square overflow-hidden rounded-xl bg-cream">
                        <img src="{{ route('memories.file', [$m, 'thumb']) }}" alt="{{ $m->caption }}" loading="lazy" class="size-full object-cover transition duration-500 group-hover:scale-105">
                    </a>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Leadership --}}
    @isset($leadership)
        <div class="mt-12 mb-5 flex items-center gap-4"><h2 class="font-display text-2xl font-semibold text-forest-900">{{ __('Kwa uongozi') }}</h2><div class="kanga-band h-1.5 flex-1 rounded-full opacity-40"></div></div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php($cards = [
                [__('Wanachama hai'), $leadership['members']['active'] ?? 0, route('members.index'), 'users'],
                [__('Mialiko inayosubiri'), $leadership['invitesOpen'], route('members.index', ['status' => 'invited']), 'whatsapp'],
                [__('Kanuni za kuthibitisha'), $leadership['rulesToConfirm'], route('governance.index'), 'book'],
                [__('Kesi za jamii zinazosubiri'), $leadership['welfarePending'], route('welfare.index'), 'heart'],
            ])
            @foreach ($cards as [$label, $value, $url, $icon])
                <a href="{{ $url }}" class="card card-pad hover:shadow-[var(--shadow-lift)]">
                    <x-icon :name="$icon" class="size-5 text-gold-600" />
                    <p class="stat mt-2">{{ $value }}</p>
                    <p class="text-sm text-muted">{{ $label }}</p>
                </a>
            @endforeach
        </div>
        <div class="mt-4 grid gap-6 lg:grid-cols-2">
            <x-card :title="__('Utayari wa matukio')" icon="calendar">
                @forelse ($leadership['upcomingEvents'] as $e)
                    @php($r = $e->readiness())
                    <a href="{{ route('events.show', $e) }}" class="block py-2">
                        <div class="flex justify-between text-sm"><span class="font-bold">{{ $e->title }}</span><span class="text-muted">{{ $r === null ? __('Hakuna majukumu') : $r.'%' }}</span></div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-cream"><div class="h-full rounded-full bg-forest-600" style="width: {{ $r ?? 0 }}%"></div></div>
                    </a>
                @empty <p class="text-muted">{{ __('Hakuna tukio lijalo.') }}</p> @endforelse
            </x-card>
            <x-card :title="__('Yanayohitaji hatua')" icon="alert">
                <ul class="space-y-2 text-sm">
                    @foreach ($leadership['minutesToApprove'] as $m)
                        <li><a class="font-bold text-forest-800 hover:underline" href="{{ route('meetings.show', $m->meeting) }}">{{ __('Kumbukumbu za kuidhinisha: :t', ['t' => $m->meeting->title]) }}</a></li>
                    @endforeach
                    @foreach ($leadership['overdueActions'] as $a)
                        <li class="flex justify-between gap-2"><span>{{ $a->title }} <span class="text-muted">— {{ $a->assignee?->displayName() }}</span></span><span class="badge-clay">{{ $a->due_on->translatedFormat('j M') }}</span></li>
                    @endforeach
                    @if ($leadership['minutesToApprove']->isEmpty() && $leadership['overdueActions']->isEmpty())<li class="text-muted">{{ __('Hakuna jambo lililochelewa.') }}</li>@endif
                </ul>
            </x-card>
        </div>
    @endisset

    {{-- Treasurer --}}
    @isset($treasury)
        <div class="mt-12 mb-5 flex items-center gap-4"><h2 class="font-display text-2xl font-semibold text-forest-900">{{ __('Hazina kwa ufupi') }}</h2><div class="kanga-band h-1.5 flex-1 rounded-full opacity-40"></div><a href="{{ route('treasury.index') }}" class="text-sm font-bold text-forest-700 hover:underline">{{ __('Fungua Hazina') }}</a></div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('treasury.submissions') }}" class="card card-pad hover:shadow-[var(--shadow-lift)]"><p class="stat">{{ $treasury['pendingSubmissions'] }}</p><p class="text-sm text-muted">{{ __('Malipo yanayosubiri uthibitisho') }}</p></a>
            <a href="{{ route('treasury.expenses') }}" class="card card-pad hover:shadow-[var(--shadow-lift)]"><p class="stat">{{ $treasury['expensesToApprove'] }} · {{ $treasury['expensesToPay'] }}</p><p class="text-sm text-muted">{{ __('Matumizi: ya kuidhinisha · ya kulipa') }}</p></a>
            <div class="card card-pad"><p class="stat">{{ $treasury['collectionRate'] === null ? '—' : $treasury['collectionRate'].'%' }}</p><p class="text-sm text-muted">{{ __('Ukusanyaji :p', ['p' => $treasury['period']]) }}</p></div>
            <div class="card card-pad"><p class="stat text-2xl"><x-money :amount="$treasury['funds']->sum('cash')" /></p><p class="text-sm text-muted">{{ __('Fedha taslimu/benki (zote)') }}</p></div>
        </div>
    @endisset
</x-layouts.app>
