<x-layouts.app :title="$member->displayName()">
    <div class="mb-8 flex flex-col gap-6 sm:flex-row sm:items-center animate-rise">
        <x-avatar :member="$member" size="size-24 text-3xl" />
        <div class="min-w-0 flex-1">
            <p class="eyebrow">{{ $member->household?->name ?? __('Familia') }}</p>
            <h1 class="font-display text-4xl font-semibold text-forest-900">{{ $member->displayName() }}</h1>
            @if ($member->preferred_name)<p class="text-muted">{{ $member->full_name }}</p>@endif
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($member->user?->roles ?? [] as $r)@if($r->key !== 'member')<span class="badge-gold">{{ $r->label() }}</span>@endif @endforeach
                <x-status :value="$member->membership_status" />
                @if ($member->user?->phone_verified_at)<span class="badge-green"><x-icon name="whatsapp" class="size-3.5" />{{ __('WhatsApp imethibitishwa') }}</span>@elseif($private)<span class="badge-clay">{{ __('WhatsApp haijathibitishwa') }}</span>@endif
            </div>
        </div>
        @if ($canManage)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('members.edit', $member) }}" class="btn-ghost btn-sm">{{ __('Hariri') }}</a>
                @unless ($member->user?->phone_verified_at)
                    <x-confirm-button :action="route('members.invite', $member)" :label="__('Tuma mwaliko')" variant="btn-primary btn-sm" />
                @endunless
            </div>
        @endif
    </div>

    @if (session('invite_url'))
        <div class="mb-6 rounded-2xl border border-gold-200 bg-gold-50 p-4" x-data="copy(@js(session('invite_url')))">
            <p class="text-sm font-bold text-gold-700">{{ __('Kiungo cha mwaliko (kinaonyeshwa mara moja tu):') }}</p>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row"><input readonly value="{{ session('invite_url') }}" class="input font-mono text-sm"><button type="button" class="btn-gold btn-sm" @click="go"><span x-show="!copied">{{ __('Nakili') }}</span><span x-show="copied">{{ __('Imenakiliwa!') }}</span></button></div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card :title="__('Kuhusu')">
                @if ($member->bio)<p class="whitespace-pre-line">{{ $member->bio }}</p>@else<p class="text-muted">{{ __('Bado hajaandika chochote kuhusu yeye.') }}</p>@endif
                @if ($member->interests)<p class="mt-3 text-sm"><span class="font-bold">{{ __('Mambo anayopenda:') }}</span> {{ $member->interests }}</p>@endif
                <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2">
                    @if ($member->birthdayVisibleTo(auth()->user()))
                        <div><dt class="text-muted">{{ __('Siku ya kuzaliwa') }}</dt><dd class="font-bold">{{ $private ? $member->date_of_birth->translatedFormat('j F Y') : $member->date_of_birth->translatedFormat('j F') }}</dd></div>
                    @endif
                    @if ($member->joined_on)<div><dt class="text-muted">{{ __('Alijiunga') }}</dt><dd class="font-bold">{{ $member->joined_on->translatedFormat('F Y') }}</dd></div>@endif
                    @if ($private)
                        <div><dt class="text-muted">{{ __('Simu') }}</dt><dd class="font-bold">{{ \App\Support\Phone::display($member->phone_e164) }}</dd></div>
                        @if ($member->email)<div><dt class="text-muted">{{ __('Barua pepe') }}</dt><dd class="font-bold">{{ $member->email }}</dd></div>@endif
                        @if ($member->emergency_contact_name)<div><dt class="text-muted">{{ __('Mtu wa dharura') }}</dt><dd class="font-bold">{{ $member->emergency_contact_name }} · {{ $member->emergency_contact_phone }}</dd></div>@endif
                        @if ($member->user?->last_active_at)<div><dt class="text-muted">{{ __('Mara ya mwisho kwenye mfumo') }}</dt><dd class="font-bold">{{ $member->user->last_active_at->diffForHumans() }}</dd></div>@endif
                    @endif
                </dl>
            </x-card>

            <x-card :title="__('Ndugu na kaya')" icon="tree">
                @if ($member->household)
                    <p class="mb-3 text-sm text-muted">{{ __('Kaya: :h', ['h' => $member->household->name]) }}</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($member->household->members->where('id', '!=', $member->id) as $hm)
                            @if (! $hm->is_minor || $private)
                                <a href="{{ route('members.show', $hm) }}" class="flex items-center gap-2 rounded-full border border-line bg-ivory py-1 pr-3 pl-1 text-sm font-bold hover:border-forest-300"><x-avatar :member="$hm" size="size-7 text-xs" />{{ $hm->displayName() }}</a>
                            @endif
                        @endforeach
                    </div>
                @endif
                @if ($private && $member->relationships->isNotEmpty())
                    <ul class="mt-4 divide-y divide-line text-sm">
                        @foreach ($member->relationships as $rel)
                            <li class="flex items-center justify-between py-2">
                                <span>{{ __(['parent_of' => 'Mzazi wa', 'spouse_of' => 'Mwenzi wa', 'sibling_of' => 'Ndugu wa', 'guardian_of' => 'Mlezi wa', 'other' => 'Uhusiano na'][$rel->type]) }} <a class="font-bold hover:underline" href="{{ route('members.show', $rel->related) }}">{{ $rel->related->displayName() }}</a> @if($rel->notes)<span class="text-muted">({{ $rel->notes }})</span>@endif</span>
                                @if ($canManage)<x-confirm-button :action="route('members.relationships.destroy', [$member, $rel])" method="DELETE" :label="__('Ondoa')" variant="text-sm font-bold text-clay-700 hover:underline" :confirm="__('Ondoa uhusiano huu?')" />@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($canManage)
                    <form method="POST" action="{{ route('members.relationships.store', $member) }}" class="mt-5 grid gap-3 border-t border-line pt-4 sm:grid-cols-[1fr_1fr_auto]">
                        @csrf
                        <select name="type" class="input" aria-label="{{ __('Aina ya uhusiano') }}">
                            <option value="parent_of">{{ __('ni mzazi wa') }}</option><option value="spouse_of">{{ __('ni mwenzi wa') }}</option><option value="sibling_of">{{ __('ni ndugu wa') }}</option><option value="guardian_of">{{ __('ni mlezi wa') }}</option><option value="other">{{ __('uhusiano mwingine na') }}</option>
                        </select>
                        <select name="related_member_id" class="input" aria-label="{{ __('Mwanafamilia') }}">@foreach ($others as $o)<option value="{{ $o->id }}">{{ $o->full_name }}</option>@endforeach</select>
                        <button class="btn-ghost">{{ __('Ongeza') }}</button>
                    </form>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            @if ($canManage)
                <x-card :title="__('Hali ya uanachama')">
                    <form method="POST" action="{{ route('members.status', $member) }}" class="space-y-3">
                        @csrf
                        <select name="status" class="input" aria-label="{{ __('Hali mpya') }}">@foreach (\App\Models\Member::STATUSES as $s)<option value="{{ $s }}" @selected($member->membership_status === $s)>{{ \App\Support\Labels::status($s) }}</option>@endforeach</select>
                        <textarea name="reason" class="input" required minlength="5" placeholder="{{ __('Sababu na uamuzi uliofanyika (k.m. kikao cha …)') }}"></textarea>
                        <button class="btn-ghost w-full">{{ __('Badilisha hali') }}</button>
                    </form>
                    <p class="mt-3 text-xs text-muted">{{ __('Mfumo haumwondoi mtu kiotomatiki kwa sababu ya deni. Tumia mchakato wa Uzingatiaji kwenye Katiba na Kanuni.') }}</p>
                </x-card>
            @endif
            @if ($canRoles && $member->user)
                <x-card :title="__('Nafasi na ruhusa')">
                    <form method="POST" action="{{ route('members.roles', $member) }}" class="space-y-2">
                        @csrf
                        @foreach ($roles as $r)
                            <label class="flex items-center gap-2.5"><input type="checkbox" name="roles[]" value="{{ $r->id }}" class="size-5 rounded border-sand text-forest-700" @checked($member->user->roles->contains('id', $r->id)) @disabled($r->key === 'super_admin' && ! auth()->user()->hasRole('super_admin'))> {{ $r->label() }}</label>
                        @endforeach
                        <button class="btn-ghost mt-2 w-full">{{ __('Hifadhi nafasi') }}</button>
                    </form>
                </x-card>
            @endif
            @if ($history->isNotEmpty())
                <x-card :title="__('Historia ya uanachama')">
                    <ol class="space-y-3 border-l-2 border-gold-200 pl-4 text-sm">
                        @foreach ($history as $h)
                            <li><p class="font-bold">{{ $h->from_status ? \App\Support\Labels::status($h->from_status).' → ' : '' }}{{ \App\Support\Labels::status($h->to_status) }}</p><p class="text-muted">{{ $h->reason }}</p><p class="text-xs text-muted">{{ $h->created_at->translatedFormat('j M Y') }} · {{ $h->changer?->member?->displayName() }}</p></li>
                        @endforeach
                    </ol>
                </x-card>
            @endif
        </div>
    </div>
</x-layouts.app>
