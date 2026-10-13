<x-layouts.app :title="__('Wasifu wangu')">
    @php($catLabels = ['contributions' => __('Vikumbusho vya michango'), 'balances' => __('Salio langu binafsi'), 'penalties' => __('Taarifa za faini'), 'meetings' => __('Vikao'), 'birthdays' => __('Siku za kuzaliwa'), 'celebrations' => __('Sherehe na kumbukumbu za ndoa'), 'events' => __('Matukio'), 'committee_tasks' => __('Majukumu ya kamati'), 'welfare' => __('Taarifa za jamii na misiba'), 'charity' => __('Sadaka na hisani'), 'secreto' => 'Secreto', 'governance' => __('Matangazo rasmi ya katiba/uanachama'), 'security' => __('Usalama wa akaunti')])
    <div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-center">
        <x-avatar :member="$member" size="size-24 text-3xl" />
        <div><p class="eyebrow">{{ __('Wasifu wangu') }}</p><h1 class="font-display text-4xl font-semibold text-forest-900">{{ $member->displayName() }}</h1>
            <p class="flex items-center gap-2 text-muted"><x-icon name="whatsapp" class="size-4" />{{ \App\Support\Phone::display($user->phone_e164) }} <span class="badge-green">{{ __('Imethibitishwa') }}</span></p></div>
    </div>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="__('Taarifa zangu')">
                <form method="POST" action="{{ route('profile.update') }}" class="grid gap-4 sm:grid-cols-2">@csrf @method('PUT')
                    <x-field name="preferred_name" :label="__('Jina la kuitwa')" :value="$member->preferred_name" />
                    <x-field name="email" type="email" :label="__('Barua pepe')" :value="$member->email" />
                    <x-field name="birthday_visibility" type="select" :label="__('Siku yangu ya kuzaliwa ionekane kwa')" :value="$member->birthday_visibility" :options="['members' => __('Wanafamilia wote'), 'leaders' => __('Uongozi tu'), 'private' => __('Mimi tu')]" />
                    <x-field name="locale" type="select" :label="__('Lugha')" :value="$user->locale" :options="['sw' => 'Kiswahili', 'en' => 'English']" />
                    <x-field name="interests" :label="__('Mambo ninayopenda')" :value="$member->interests" class="sm:col-span-2" />
                    <x-field name="bio" type="textarea" rows="3" :label="__('Kuhusu mimi')" :value="$member->bio" class="sm:col-span-2" />
                    <x-field name="emergency_contact_name" :label="__('Mtu wa dharura')" :value="$member->emergency_contact_name" />
                    <x-field name="emergency_contact_phone" :label="__('Simu ya mtu wa dharura')" :value="$member->emergency_contact_phone" />
                    <div class="sm:col-span-2"><button class="btn-primary">{{ __('Hifadhi') }}</button></div>
                </form>
                <p class="mt-4 text-sm text-muted">{{ __('Jina kamili, tarehe ya kuzaliwa na kaya hubadilishwa na Katibu. Tumia "Omba marekebisho" hapa chini.') }}</p>
            </x-card>

            <x-card :title="__('Ujumbe wa WhatsApp')" icon="whatsapp">
                <form method="POST" action="{{ route('profile.notifications') }}" class="space-y-2">@csrf @method('PUT')
                    <div class="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                        @foreach ($categories as $cat)
                            @php($isMandatory = in_array($cat, $mandatory))
                            @php($pref = $member->notificationPreferences->firstWhere('category', $cat))
                            <label class="flex items-center gap-2.5 py-1"><input type="checkbox" name="categories[]" value="{{ $cat }}" class="size-5 rounded border-sand text-forest-700" @checked($isMandatory || ! $pref || $pref->enabled) @disabled($isMandatory)> {{ $catLabels[$cat] ?? $cat }} @if($isMandatory)<span class="text-xs text-muted">({{ __('lazima') }})</span>@endif</label>
                        @endforeach
                    </div>
                    <label class="mt-3 flex items-center gap-2.5 border-t border-line pt-3 text-clay-700"><input type="checkbox" name="opt_out_all" value="1" class="size-5 rounded border-sand" @checked($member->whatsapp_opt_out_at)> {{ __('Sitaki ujumbe wowote wa WhatsApp (isipokuwa nambari za kuingia)') }}</label>
                    <button class="btn-ghost mt-2">{{ __('Hifadhi mapendeleo') }}</button>
                </form>
            </x-card>
        </div>
        <div class="space-y-6">
            <x-card :title="__('Picha yangu')">
                <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" class="space-y-3">@csrf
                    <x-field name="photo" type="file" accept="image/jpeg,image/png,image/webp" :label="__('Chagua picha')" required />
                    <button class="btn-ghost w-full">{{ __('Weka picha') }}</button>
                </form>
            </x-card>
            <x-card :title="__('Badilisha namba ya simu')">
                <form method="POST" action="{{ route('profile.phone') }}" class="space-y-3">@csrf
                    <x-field name="new_phone" type="tel" :label="__('Namba mpya ya WhatsApp')" required />
                    <button class="btn-ghost w-full">{{ __('Thibitisha kwa WhatsApp') }}</button>
                    <p class="text-xs text-muted">{{ __('Nambari ya uthibitisho itatumwa kwa namba MPYA. Namba ya zamani itaendelea kutumika hadi uthibitishe.') }}</p>
                </form>
            </x-card>
            <x-card :title="__('Omba marekebisho au kufutwa kwa taarifa')">
                <form method="POST" action="{{ route('profile.request') }}" class="space-y-3">@csrf
                    <x-field name="type" type="select" :label="__('Aina ya ombi')" :options="['correction' => __('Rekebisha taarifa'), 'deletion' => __('Futa taarifa zangu')]" />
                    <x-field name="details" type="textarea" rows="3" :label="__('Maelezo')" required />
                    <button class="btn-ghost w-full">{{ __('Tuma kwa Katibu') }}</button>
                    <p class="text-xs text-muted">{{ __('Kumbukumbu za fedha zinahifadhiwa kwa mujibu wa sera ya familia na sheria; ombi la kufuta litapitiwa na uongozi.') }}</p>
                </form>
            </x-card>
        </div>
    </div>
</x-layouts.app>
