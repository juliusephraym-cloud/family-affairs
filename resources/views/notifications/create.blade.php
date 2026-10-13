<x-layouts.app :title="__('Andaa ujumbe')" wide>
    <x-page-header :eyebrow="__('Ujumbe wa WhatsApp')" :title="__('Andaa ujumbe')" :description="__('WhatsApp inaruhusu ujumbe wa kuanzisha mazungumzo kwa kutumia violezo vilivyoidhinishwa na Meta tu. Chagua kiolezo, wapokeaji, kisha hakiki.')" />
    <form method="POST" action="{{ route('notifications.store') }}" class="grid gap-6 xl:grid-cols-[1fr_24rem]"
          x-data="composer(@js($templates->map(fn ($t) => ['id' => $t->id, 'vars' => $t->variables, 'sensitive' => $t->is_sensitive, 'sendable' => $t->isSendable(), 'body' => $t->body_preview])->values()), @js(route('notifications.preview')))" @change.debounce.300ms="refresh" @input.debounce.500ms="refresh">
        @csrf
        <div class="space-y-6">
            <x-card :title="__('1. Kiolezo')">
                <select name="template_id" x-model.number="templateId" class="input" required>
                    @foreach ($templates as $t)<option value="{{ $t->id }}">{{ $t->name }} — {{ $t->category }}{{ $t->isSendable() ? '' : ' ('.__('hakijaidhinishwa').')' }}</option>@endforeach
                </select>
                <template x-if="current && !current.sendable"><p class="mt-2 text-sm font-bold text-clay-700">{{ __('Kiolezo hiki hakijaidhinishwa au hakina jina la NextSMS. Ujumbe utashindwa hadi kiidhinishwe (Utawala → Violezo).') }}</p></template>
                <template x-if="current && current.sensitive"><p class="mt-2 text-sm font-bold text-gold-700">{{ __('Kiolezo cha siri: kila mpokeaji atapata salio lake binafsi tu. Kinaweza kutumwa kwa "Wenye madeni" au mtu mmoja.') }}</p></template>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <template x-for="v in (current ? current.vars : [])" :key="v">
                        <label x-show="!['name','amount','months','portal_link'].includes(v)"><span class="field-label" x-text="v"></span><input class="input" :name="'vars[' + v + ']'" :placeholder="v"></label>
                    </template>
                </div>
                <p class="mt-2 text-xs text-muted">{{ __('{name}, {amount}, {months} na {portal_link} hujazwa na mfumo kwa kila mpokeaji.') }}</p>
            </x-card>
            <x-card :title="__('2. Wapokeaji')">
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="sm:col-span-2"><span class="field-label">{{ __('Kundi') }}</span>
                        <select name="audience" x-model="audience" class="input">
                            <option value="all_active">{{ __('Wanachama wote hai') }}</option><option value="leaders">{{ __('Uongozi') }}</option><option value="role">{{ __('Nafasi fulani') }}</option>
                            <option value="event">{{ __('Wahusika wa tukio') }}</option><option value="members">{{ __('Watu maalum') }}</option><option value="arrears">{{ __('Wenye madeni yaliyopitwa (binafsi)') }}</option>
                        </select></label>
                    <label x-show="audience === 'role'" x-cloak><span class="field-label">{{ __('Nafasi') }}</span><select name="role" class="input">@foreach ($roles as $r)<option value="{{ $r->key }}">{{ $r->label() }}</option>@endforeach</select></label>
                    <label x-show="audience === 'event'" x-cloak><span class="field-label">{{ __('Tukio') }}</span><select name="event_id" class="input">@foreach ($events as $e)<option value="{{ $e->id }}">{{ $e->title }}</option>@endforeach</select></label>
                    <div x-show="audience === 'members'" x-cloak class="max-h-56 overflow-y-auto rounded-xl border border-line p-2 sm:col-span-2">@foreach ($members as $m)<label class="flex items-center gap-2 py-0.5 text-sm"><input type="checkbox" name="member_ids[]" value="{{ $m->id }}" class="size-4 rounded border-sand"> {{ $m->full_name }}</label>@endforeach</div>
                </div>
                <p class="mt-3 text-xs text-muted">{{ __('Waliojiondoa kwenye aina hii ya ujumbe hawatatumiwa (isipokuwa ujumbe wa usalama na wa kikatiba).') }}</p>
            </x-card>
            <x-card :title="__('3. Lini')">
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="title" :label="__('Jina la ndani la ujumbe')" required :placeholder="__('Mf. Ukumbusho wa mchango Oktoba')" class="sm:col-span-2" />
                    <label><span class="field-label">{{ __('Tuma') }}</span><select name="when" x-model="when" class="input"><option value="later">{{ __('Kwa ratiba') }}</option><option value="now">{{ __('Sasa hivi') }}</option></select></label>
                    <label x-show="when === 'later'"><span class="field-label">{{ __('Tarehe na saa') }}</span><input type="datetime-local" name="scheduled_at" class="input" value="{{ now()->addHour()->startOfHour()->format('Y-m-d\TH:i') }}"></label>
                </div>
                <p class="mt-2 text-xs text-muted">{{ __('Saa za utulivu (usiku) zikiwa zimewekwa, ujumbe utasubiri hadi asubuhi.') }}</p>
            </x-card>
        </div>
        <div class="space-y-4 xl:sticky xl:top-6 xl:self-start">
            <div class="rounded-[1.75rem] bg-[#0b141a] p-4 shadow-xl">
                <p class="mb-3 text-center text-xs font-bold tracking-wider text-white/50 uppercase">{{ __('Hakiki') }}</p>
                <div class="ml-auto max-w-[90%] rounded-2xl rounded-tr-sm bg-[#005c4b] px-3.5 py-2.5 text-[0.95rem] whitespace-pre-line text-white" x-text="preview || '…'"></div>
            </div>
            <div class="card card-pad space-y-2">
                <div class="flex justify-between"><span>{{ __('Wapokeaji') }}</span><strong x-text="recipients ?? '—'"></strong></div>
                <div class="flex justify-between"><span>{{ __('Makadirio ya gharama') }}</span><strong x-text="cost !== null ? 'TSh ' + cost.toLocaleString() : '—'"></strong></div>
                <p class="text-xs text-muted">{{ __('Kwa bei ya TSh :p kwa ujumbe (NEXTSMS_PRICE_PER_MESSAGE). Thibitisha bei kwenye akaunti yako ya NextSMS.', ['p' => $price]) }}</p>
                <p x-show="needsApproval" class="text-sm font-bold text-gold-700">{{ __('Unahitaji idhini ya kiongozi mwingine kabla ya kutumwa.') }}</p>
                <button class="btn-primary mt-2 w-full"><x-icon name="whatsapp" />{{ __('Panga ujumbe') }}</button>
            </div>
        </div>
    </form>
</x-layouts.app>
