<x-layouts.app :title="__('Violezo vya WhatsApp')" wide>
    <x-page-header :eyebrow="__('Utawala')" :title="__('Violezo na vikumbusho')" :description="__('Kila kiolezo lazima kiundwe na kuidhinishwa na Meta kupitia dashibodi ya NextSMS (Channels → Whatsapp Channel → Whatsapp Gateway). Kisha weka jina lake halisi hapa na ukiweke "Kimeidhinishwa".')" />
    <div class="grid gap-6 xl:grid-cols-[1fr_24rem]">
        <div class="space-y-4">
            @foreach ($templates as $t)
                <form method="POST" action="{{ route('admin.templates.update', $t) }}" class="card card-pad">@csrf @method('PUT')
                    <div class="flex flex-wrap items-center justify-between gap-2"><p class="font-display text-lg font-semibold">{{ $t->name }}</p><span class="flex gap-1"><span class="badge-grey">{{ $t->category }}</span>@if($t->is_sensitive)<span class="badge-gold">{{ __('Binafsi') }}</span>@endif<span class="{{ $t->isSendable() ? 'badge-green' : 'badge-clay' }}">{{ $t->isSendable() ? __('Tayari kutumwa') : __('Haiko tayari') }}</span></span></div>
                    <p class="font-mono text-xs text-muted">{{ $t->key }}</p>
                    <div class="mt-3 grid gap-3 md:grid-cols-3">
                        <label><span class="field-label">{{ __('Jina la kiolezo NextSMS') }}</span><input name="provider_template" class="input" value="{{ $t->provider_template }}" placeholder="family_contribution_reminder"></label>
                        <label><span class="field-label">{{ __('Idhini ya Meta') }}</span><select name="provider_approval" class="input">@foreach (['pending' => __('Inasubiri'), 'approved' => __('Kimeidhinishwa'), 'rejected' => __('Kimekataliwa')] as $k => $l)<option value="{{ $k }}" @selected($t->provider_approval === $k)>{{ $l }}</option>@endforeach</select></label>
                        <label><span class="field-label">{{ __('Vigezo kwa mpangilio') }}</span><input name="variables" class="input font-mono text-sm" value="{{ implode(', ', $t->variables) }}"></label>
                        <label class="md:col-span-3"><span class="field-label">{{ __('Maandishi (kama yalivyowasilishwa kwa Meta)') }}</span><textarea name="body_preview" class="input" rows="2">{{ $t->body_preview }}</textarea></label>
                    </div>
                    <div class="mt-3 flex items-center justify-between"><label class="flex items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="size-4 rounded border-sand" @checked($t->is_active)> {{ __('Kinatumika') }}</label><button class="btn-ghost btn-sm">{{ __('Hifadhi') }}</button></div>
                    @if ($t->key === 'otp')<p class="mt-2 text-xs text-muted">{{ __('OTP hutumia NEXTSMS_OTP_TEMPLATE kwenye .env (kiolezo cha aina ya Authentication chenye kitufe cha "copy code").') }}</p>@endif
                </form>
            @endforeach
        </div>
        <div class="space-y-4">
            <h2 class="font-display text-xl font-semibold">{{ __('Vikumbusho vya kiotomatiki') }}</h2>
            <p class="text-sm text-muted">{{ __('Vimezimwa mwanzoni. Kuwasha ni idhini ya kudumu ya kutuma — kila kimoja kinatumwa mara moja tu kwa kipindi.') }}</p>
            @foreach ($rules as $r)
                <form method="POST" action="{{ route('admin.reminders.update', $r) }}" class="card card-pad space-y-2">@csrf @method('PUT')
                    <div class="flex items-center justify-between"><p class="font-bold">{{ $r->key }}</p><span class="{{ $r->template?->isSendable() ? 'badge-green' : 'badge-clay' }}">{{ $r->template?->isSendable() ? __('kiolezo tayari') : __('kiolezo hakijawa tayari') }}</span></div>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" class="size-5 rounded border-sand" @checked($r->enabled)> {{ __('Washa') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        @isset($r->config['day'])<label class="text-sm">{{ __('Tarehe ya mwezi') }}<input type="number" name="day" min="1" max="28" class="input" value="{{ $r->config['day'] }}"></label>@endisset
                        @isset($r->config['time'])<label class="text-sm">{{ __('Saa') }}<input type="time" name="time" class="input" value="{{ $r->config['time'] }}"></label>@endisset
                        @isset($r->config['hours_before'])<label class="text-sm">{{ __('Saa kabla') }}<input type="number" name="hours_before" class="input" value="{{ $r->config['hours_before'] }}"></label>@endisset
                        @isset($r->config['days_before'])<label class="text-sm">{{ __('Siku kabla') }}<input type="number" name="days_before" class="input" value="{{ $r->config['days_before'] }}"></label>@endisset
                    </div>
                    @if ($r->last_run_at)<p class="text-xs text-muted">{{ __('Mara ya mwisho: :t', ['t' => $r->last_run_at->diffForHumans()]) }}</p>@endif
                    <button class="btn-ghost btn-sm">{{ __('Hifadhi') }}</button>
                </form>
            @endforeach
        </div>
    </div>
</x-layouts.app>
