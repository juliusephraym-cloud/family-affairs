<x-layouts.app title="Secreto">
    <section class="relative mb-8 overflow-hidden rounded-[1.75rem] p-6 text-ivory sm:p-10" style="background: linear-gradient(135deg, #8a3f2b 0%, #143222 65%);">
        <svg class="pointer-events-none absolute -top-6 -right-6 size-56 text-gold-400/20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width=".6" aria-hidden="true"><rect x="3" y="8" width="18" height="4.5" rx="1"/><path d="M5 12.5V21h14v-8.5M12 8v13M12 8S10.5 3.5 8 3.5a2.2 2.2 0 0 0 0 4.5h4Zm0 0s1.5-4.5 4-4.5a2.2 2.2 0 0 1 0 4.5h-4Z"/></svg>
        <p class="eyebrow text-gold-200">{{ __('Utamaduni wetu') }}</p>
        <h1 class="mt-2 font-display text-4xl font-semibold sm:text-5xl">Secreto <span class="text-gold-200 italic">{{ $season?->year }}</span></h1>
        <p class="mt-3 max-w-xl text-ivory/85">{{ __('Kila mwanafamilia anamchagua mwenzake kwa siri na kumletea zawadi siku ya kukutana. Ni upendo uliofichwa mpaka siku ile.') }}</p>
        @if ($season)
            <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                @if ($season->exchange_at)<span class="flex items-center gap-1.5"><x-icon name="calendar" class="size-4" />{{ $season->exchange_at->translatedFormat('l, j F Y') }}</span>@endif
                @if ($season->venue)<span class="flex items-center gap-1.5"><x-icon name="map-pin" class="size-4" />{{ $season->venue }}</span>@endif
                @if ($season->gift_minimum)<span class="flex items-center gap-1.5"><x-icon name="gift" class="size-4" />{{ __('Zawadi kuanzia :a', ['a' => \App\Support\Money::format($season->gift_minimum)]) }}{{ $season->allow_vouchers ? ' · '.__('gift voucher inaruhusiwa') : '' }}</span>@endif
                <x-status :value="$season->status" class="bg-white/15 text-white" />
            </div>
        @endif
    </section>

    @if (! $season)
        <x-empty icon="gift" :title="__('Hakuna msimu wa Secreto kwa sasa')" :text="__('Waandaaji wataufungua wakati ukifika.')" />
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                {{-- Me --}}
                @if ($recipient)
                    <section class="card overflow-hidden" x-data="{ show: false }">
                        <div class="kanga-band"></div>
                        <div class="p-6 text-center">
                            <p class="eyebrow">{{ __('Secreto wako ni') }}</p>
                            <div class="relative mx-auto mt-4 max-w-sm">
                                <div x-show="!show" class="flex flex-col items-center gap-3">
                                    <span class="grid size-20 place-items-center rounded-full bg-clay-100 text-clay-700"><x-icon name="gift" class="size-9" /></span>
                                    <button type="button" class="btn-primary" @click="show = true"><x-icon name="eye" />{{ __('Gusa kuona (hakikisha hakuna anayeangalia)') }}</button>
                                </div>
                                <div x-show="show" x-cloak class="flex flex-col items-center gap-3">
                                    <x-avatar :member="$recipient->member" size="size-20 text-2xl" />
                                    <p class="font-display text-3xl font-semibold text-forest-900">{{ $recipient->member->displayName() }}</p>
                                    @if ($recipient->wishlist)<div class="w-full rounded-2xl bg-ivory p-4 text-left"><p class="mb-1 text-sm font-bold text-gold-700">{{ __('Anachopenda') }}</p><p class="whitespace-pre-line text-sm">{{ $recipient->wishlist }}</p></div>@else<p class="text-sm text-muted">{{ __('Hajaandika anachopenda — tumia upendo wako!') }}</p>@endif
                                    <button type="button" class="text-sm font-bold text-forest-700 hover:underline" @click="show = false">{{ __('Ficha') }}</button>
                                </div>
                            </div>
                        </div>
                    </section>
                @elseif ($me && $season->status === 'registration')
                    <x-card><p class="flex items-center gap-2"><x-icon name="check" class="size-5 text-forest-600" />{{ __('Umejiandikisha. Majina yatachaguliwa usajili ukifungwa.') }}</p></x-card>
                @endif

                @if ($me)
                    <x-card :title="__('Maandalizi yangu')">
                        <form method="POST" action="{{ route('secreto.me', $season) }}" class="space-y-4">@csrf
                            <x-field name="wishlist" type="textarea" rows="3" :label="__('Ninachopenda (atakayenipata ataona hiki tu)')" :value="$me->wishlist" />
                            @if (in_array($season->status, ['drawn']))
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-field name="gift_status" type="select" :label="__('Zawadi yangu kwa Secreto wangu')" :value="$me->gift_status" :options="\App\Support\Labels::options(['not_started', 'preparing', 'ready', 'brought', 'by_proxy'])" />
                                    <x-field name="proxy_name" :label="__('Mwakilishi (kama sitakuwepo)')" :value="$me->proxy_name" />
                                </div>
                            @endif
                            <button class="btn-primary btn-sm">{{ __('Hifadhi') }}</button>
                        </form>
                        @if ($season->registrationOpen())
                            <form method="POST" action="{{ route('secreto.leave', $season) }}" class="mt-3">@csrf<button class="text-sm font-bold text-clay-700 hover:underline">{{ __('Jiondoe msimu huu') }}</button></form>
                        @endif
                    </x-card>
                @elseif ($season->registrationOpen())
                    <x-card :title="__('Jiunge na Secreto :y', ['y' => $season->year])">
                        <form method="POST" action="{{ route('secreto.join', $season) }}" class="space-y-4">@csrf
                            <x-field name="wishlist" type="textarea" rows="3" :label="__('Unachopenda (si lazima)')" :hint="__('Rangi, ukubwa wa nguo, vitu unavyopenda… Atakayekupata tu ndiye ataona.')" />
                            <button class="btn-gold"><x-icon name="gift" />{{ __('Nijumuishe') }}</button>
                            @if ($season->registration_closes_at)<p class="text-sm text-muted">{{ __('Usajili unafungwa :d', ['d' => $season->registration_closes_at->translatedFormat('j F Y')]) }}</p>@endif
                        </form>
                    </x-card>
                @endif

                @if ($season->rules)<x-card :title="__('Taratibu za mwaka huu')"><p class="whitespace-pre-line">{{ $season->rules }}</p></x-card>@endif
                @if ($season->memories)<x-card :title="__('Kumbukumbu za siku ile')"><p class="whitespace-pre-line">{{ $season->memories }}</p></x-card>@endif
            </div>

            <div class="space-y-6">
                <x-card :title="__('Washiriki (:n)', ['n' => $participants->count()])">
                    <div class="flex flex-wrap gap-1.5">@foreach ($participants as $p)<span class="badge-grey">{{ $p->member->displayName() }}</span>@endforeach</div>
                    @if ($canManage && $season->status === 'drawn')
                        @php($counts = $participants->countBy('gift_status'))
                        <div class="mt-4 border-t border-line pt-3 text-sm"><p class="mb-1 font-bold">{{ __('Maandalizi ya zawadi') }}</p>
                            @foreach (['not_started', 'preparing', 'ready', 'brought', 'by_proxy'] as $st)<div class="flex justify-between"><span>{{ \App\Support\Labels::status($st) }}</span><span class="font-bold">{{ $counts[$st] ?? 0 }}</span></div>@endforeach
                            <p class="mt-2 text-xs text-muted">{{ __('Waandaaji wanaona idadi tu — si nani amempata nani.') }}</p></div>
                    @endif
                </x-card>

                @if ($canManage)
                    <x-card :title="__('Waandaaji')">
                        @if ($season->status === 'registration')
                            <x-confirm-button :action="route('secreto.draw', $season)" :label="__('Chagua majina sasa')" variant="btn-primary w-full" :confirm="__('Majina yatachaguliwa kwa siri na kompyuta. Hakuna atakayeona jozi zote, hata wewe. Endelea?')" />
                        @elseif ($season->status === 'drawn')
                            <x-confirm-button :action="route('secreto.reset', $season)" :label="__('Futa uchaguzi (mtu amejiondoa)')" variant="btn-ghost btn-sm w-full" :reason="true" />
                        @endif
                        <form method="POST" action="{{ route('secreto.update', $season) }}" class="mt-4 space-y-3 border-t border-line pt-4">@csrf @method('PUT')
                            <x-field name="title" :label="__('Kichwa')" :value="$season->title" required />
                            <x-field name="status" type="select" :label="__('Hali')" :value="$season->status" :options="\App\Support\Labels::options(['draft', 'registration', 'drawn', 'exchanged', 'archived'])" />
                            <x-field name="gift_minimum" :label="__('Thamani ya chini ya zawadi')" :value="$season->gift_minimum" :hint="$minimumRule ? __('Kumbukumbu: :v (:s)', ['v' => \App\Support\Money::format((int) $minimumRule->scalar()), 's' => \App\Support\Labels::status($minimumRule->status)]) : null" />
                            <x-field name="registration_closes_at" type="datetime-local" :label="__('Usajili unafungwa')" :value="$season->registration_closes_at?->format('Y-m-d\TH:i')" />
                            <x-field name="exchange_at" type="datetime-local" :label="__('Siku ya kubadilishana')" :value="$season->exchange_at?->format('Y-m-d\TH:i')" />
                            <x-field name="venue" :label="__('Mahali')" :value="$season->venue" />
                            <x-field name="rules" type="textarea" rows="3" :label="__('Taratibu')" :value="$season->rules" />
                            <x-field name="memories" type="textarea" rows="2" :label="__('Kumbukumbu (baada ya tukio)')" :value="$season->memories" />
                            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="allow_vouchers" value="0"><input type="checkbox" name="allow_vouchers" value="1" class="size-4 rounded border-sand" @checked($season->allow_vouchers)> {{ __('Gift voucher inaruhusiwa') }}</label>
                            <label class="flex items-center gap-2 text-sm"><input type="hidden" name="avoid_same_household" value="0"><input type="checkbox" name="avoid_same_household" value="1" class="size-4 rounded border-sand" @checked($season->avoid_same_household)> {{ __('Usimpe mtu wa kaya yake') }}</label>
                            <button class="btn-ghost w-full">{{ __('Hifadhi msimu') }}</button>
                        </form>
                    </x-card>
                @endif
                @can('secreto.reveal')
                    @if ($season->status === 'drawn')
                        <x-card :title="__('Dharura: onyesha jozi zote')">
                            <p class="mb-3 text-sm text-muted">{{ __('Hatua hii inarekodiwa kwenye kumbukumbu za ukaguzi. Tumia tu pale inapolazimu (k.m. mtu amepoteza Secreto wake na hawezi kuingia).') }}</p>
                            <x-confirm-button :action="route('secreto.reveal', $season)" :label="__('Onyesha jozi')" variant="btn-danger btn-sm" :reason="true" />
                            @if ($revealed)<ul class="mt-4 space-y-1 text-sm">@foreach ($revealed as $p)<li>{{ $p['giver'] }} → <strong>{{ $p['recipient'] }}</strong></li>@endforeach</ul>@endif
                        </x-card>
                    @endif
                @endcan
            </div>
        </div>
    @endif

    @if ($canManage)
        <x-card :title="__('Unda msimu mpya')" class="mt-6">
            <form method="POST" action="{{ route('secreto.store') }}" class="grid gap-4 sm:grid-cols-3">@csrf
                <x-field name="year" type="number" :label="__('Mwaka')" :value="now()->year + 1" required />
                <x-field name="title" :label="__('Kichwa')" :value="'Secreto '.(now()->year + 1)" required />
                <x-field name="gift_minimum" :label="__('Thamani ya chini')" :hint="__('Weka kiasi kilichoamuliwa na familia.')" />
                <input type="hidden" name="allow_vouchers" value="1"><input type="hidden" name="avoid_same_household" value="1">
                <div class="sm:col-span-3"><button class="btn-ghost">{{ __('Unda msimu') }}</button></div>
            </form>
        </x-card>
    @endif

    @if ($archive->isNotEmpty())
        <h2 class="mt-10 mb-3 font-display text-xl font-semibold text-forest-900">{{ __('Misimu iliyopita') }}</h2>
        <div class="grid gap-3 sm:grid-cols-3">@foreach ($archive as $a)<div class="card card-pad"><p class="font-display text-2xl font-semibold">{{ $a->year }}</p><p class="text-sm text-muted">{{ $a->title }}</p>@if($a->memories)<p class="mt-2 line-clamp-3 text-sm">{{ $a->memories }}</p>@endif</div>@endforeach</div>
    @endif
</x-layouts.app>
