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

    <div class="mt-10 rounded-2xl border border-line bg-paper p-5">
        <p class="font-bold text-forest-900">{{ __('Bado huna akaunti?') }}</p>
        <p class="mt-1 text-sm text-muted">{{ __('Akaunti hufunguliwa kwa mwaliko pekee. Muombe Katibu akutumie kiungo cha mwaliko — kisha utathibitisha namba yako hapa.') }}</p>
    </div>
</x-layouts.guest>
