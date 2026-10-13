<x-layouts.guest :title="__('Ingia')">
    <p class="eyebrow mb-3">{{ __('Karibu tena') }}</p>
    <h1 class="font-display text-4xl font-semibold text-forest-900">{{ __('Ingia kwenye familia') }}</h1>
    <p class="mt-3 text-muted">{{ __('Weka namba yako ya simu. Tutakutumia nambari ya uthibitisho kupitia WhatsApp — hakuna nenosiri la kukumbuka.') }}</p>

    <form method="POST" action="{{ route('login.send') }}" class="mt-8 space-y-5">
        @csrf
        <x-field name="phone" :label="__('Namba ya simu (WhatsApp)')" type="tel" inputmode="tel" autocomplete="tel" placeholder="0712 345 678 au +255 712 345 678" required autofocus
                 :hint="__('Namba ya Tanzania au ya nje kwa muundo wa kimataifa (+…).')" />
        <label class="flex items-center gap-2.5 text-sm text-ink-soft">
            <input type="checkbox" name="remember" value="1" class="size-5 rounded border-sand text-forest-700 focus:ring-forest-200">
            {{ __('Nikumbuke kwenye kifaa hiki (simu yangu binafsi)') }}
        </label>
        <button class="btn-primary w-full text-base"><x-icon name="whatsapp" />{{ __('Nitumie nambari kwa WhatsApp') }}</button>
    </form>

    @if (config('family.demo_mode'))
        <div class="mt-8 rounded-2xl border border-gold-200 bg-gold-50 p-5">
            <p class="font-bold text-gold-700">{{ __('Hii ni DEMO — majina yote ni ya kubuni') }}</p>
            <p class="mt-1 text-sm text-gold-700">{{ __('Ingia kwa mojawapo ya namba hizi. Nambari ya uthibitisho itaonekana kwenye skrini (hakuna WhatsApp inayotumwa). Data hurudishwa kila usiku.') }}</p>
            <ul class="mt-3 space-y-1 text-sm">
                @foreach (['0700 000 001' => __('Mwenyekiti + Msimamizi wa mfumo'), '0700 000 003' => __('Katibu'), '0700 000 005' => __('Mhazini'), '0700 000 009' => __('Mwanachama wa kawaida (ana deni)')] as $n => $r)
                    <li class="flex justify-between gap-3"><span class="font-mono font-bold">{{ $n }}</span><span class="text-ink-soft">{{ $r }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-10 rounded-2xl border border-line bg-paper p-5">
        <p class="font-bold text-forest-900">{{ __('Bado huna akaunti?') }}</p>
        <p class="mt-1 text-sm text-muted">{{ __('Akaunti hufunguliwa kwa mwaliko pekee. Muombe Katibu akutumie kiungo cha mwaliko — kisha utathibitisha namba yako hapa.') }}</p>
    </div>
</x-layouts.guest>
