<x-layouts.guest :title="__('Thibitisha')">
    <p class="eyebrow mb-3">{{ __('Hatua ya mwisho') }}</p>
    <h1 class="font-display text-4xl font-semibold text-forest-900">{{ __('Weka nambari ya uthibitisho') }}</h1>
    <p class="mt-3 text-muted">
        {{ __('Kama namba :phone imesajiliwa, tumeituma nambari ya tarakimu 6 kwa WhatsApp. Inadumu dakika :min.', ['phone' => \App\Support\Phone::display($phone), 'min' => config('family.otp.ttl_minutes')]) }}
    </p>

    <form x-data="otpInput" x-ref="form" method="POST" action="{{ route('verify.check') }}" class="mt-8 space-y-5">
        @csrf
        <label for="code" class="field-label">{{ __('Nambari ya uthibitisho') }}</label>
        <input id="code" name="code" @input="onInput" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus
               class="input text-center font-display text-3xl tracking-[0.6em] tabular" placeholder="••••••">
        @error('code')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn-primary w-full text-base"><x-icon name="shield" />{{ __('Thibitisha na uingie') }}</button>
    </form>

    <div class="mt-6 flex items-center justify-between text-sm" x-data="{ s: {{ (int) $wait }} }" x-init="if (s > 0) { const t = setInterval(() => { s--; if (s <= 0) clearInterval(t) }, 1000) }">
        <a href="{{ route('login') }}" class="font-bold text-forest-800 hover:underline">{{ __('Badilisha namba') }}</a>
        <form method="POST" action="{{ route('verify.resend') }}">@csrf
            <button class="font-bold text-forest-800 hover:underline disabled:text-muted disabled:no-underline" :disabled="s > 0">
                <span x-show="s > 0">{{ __('Tuma tena baada ya') }} <span x-text="s"></span>s</span>
                <span x-show="s <= 0">{{ __('Tuma nambari tena') }}</span>
            </button>
        </form>
    </div>
    @if (app()->environment('local') && config('family.messaging.driver') === 'fake')
        <p class="mt-8 rounded-xl bg-gold-50 p-3 text-sm text-gold-700"><strong>DEV:</strong> {{ __('Mtoa huduma wa majaribio yuko kazini. Soma ujumbe kwenye') }} <a class="underline" href="{{ route('dev.outbox') }}" target="_blank">/dev/outbox</a>.</p>
    @endif
</x-layouts.guest>
