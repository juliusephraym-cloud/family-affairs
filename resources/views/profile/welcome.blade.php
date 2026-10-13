<x-layouts.guest :title="__('Karibu')">
    <p class="eyebrow mb-3">{{ __('Karibu nyumbani') }}</p>
    <h1 class="font-display text-4xl font-semibold text-forest-900">{{ __('Habari, :name!', ['name' => $member->displayName()]) }}</h1>
    <p class="mt-3 text-muted">{{ __('Namba yako imethibitishwa. Kamilisha mambo machache kisha uingie.') }}</p>

    <form method="POST" action="{{ route('welcome.store') }}" class="mt-8 space-y-5">
        @csrf
        <x-field name="preferred_name" :label="__('Jina unalopenda kuitwa')" :value="$member->preferred_name" :hint="__('Mf. Da Milly, Kaka J — litaonekana kwa familia.')" />
        <x-field name="email" type="email" :label="__('Barua pepe (si lazima)')" :value="$member->email" />
        @unless ($member->date_of_birth)
            <x-field name="date_of_birth" type="date" :label="__('Tarehe ya kuzaliwa (si lazima)')" :hint="__('Mwaka hauonyeshwi kwa familia — siku na mwezi tu, kulingana na chaguo lako hapa chini.')" />
        @endunless
        <x-field name="birthday_visibility" type="select" :label="__('Nani aone siku yangu ya kuzaliwa?')" :value="$member->birthday_visibility" :options="['members' => __('Wanafamilia wote'), 'leaders' => __('Uongozi tu'), 'private' => __('Mimi tu')]" />

        <label class="flex gap-3 rounded-2xl border border-line bg-paper p-4">
            <input type="checkbox" name="whatsapp_consent" value="1" class="mt-1 size-5 shrink-0 rounded border-sand text-forest-700" required>
            <span class="text-sm text-ink-soft">{{ __('Nakubali kupokea ujumbe wa familia kupitia WhatsApp (nambari za uthibitisho, vikumbusho vya michango, vikao na matukio). Taarifa zangu za fedha hutumwa kwangu binafsi tu. Ninaweza kubadilisha mapendeleo wakati wowote kwenye wasifu wangu.') }}</span>
        </label>
        @error('whatsapp_consent')<p class="field-error">{{ $message }}</p>@enderror

        <button class="btn-primary w-full text-base">{{ __('Ingia nyumbani') }}<x-icon name="arrow-right" /></button>
    </form>
</x-layouts.guest>
