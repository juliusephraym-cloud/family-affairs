<x-layouts.guest :title="__('Mwaliko')">
    <p class="eyebrow mb-3">{{ __('Mwaliko binafsi') }}</p>
    <h1 class="font-display text-4xl font-semibold text-forest-900">{{ __('Karibu, :name', ['name' => $invitation->member->displayName()]) }}</h1>
    <p class="mt-3 text-muted">{{ __('Uongozi wa familia umekualika kujiunga na mfumo. Thibitisha namba yako ya WhatsApp ili kufungua akaunti yako.') }}</p>
    <form method="POST" action="{{ route('invitation.send', $token) }}" class="mt-8 space-y-5">
        @csrf
        <x-field name="phone" type="tel" inputmode="tel" autocomplete="tel" required autofocus :label="__('Namba yako ya simu (WhatsApp)')"
                 :hint="__('Lazima iwe namba ileile ambayo uongozi uliiandikisha (inaishia :end).', ['end' => substr($invitation->phone_e164, -3)])" />
        <button class="btn-primary w-full text-base"><x-icon name="whatsapp" />{{ __('Nitumie nambari ya uthibitisho') }}</button>
    </form>
    <p class="mt-6 text-sm text-muted">{{ __('Kiungo hiki ni chako binafsi na kinaisha :date. Usimtumie mtu mwingine.', ['date' => $invitation->expires_at->translatedFormat('j F Y')]) }}</p>
</x-layouts.guest>
