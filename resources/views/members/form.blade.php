<x-layouts.app :title="$member->exists ? __('Hariri') : __('Ongeza mwanafamilia')">
    <x-page-header :eyebrow="__('Familia')" :title="$member->exists ? $member->full_name : __('Ongeza mwanafamilia')"
        :description="__('Kuongeza mtu hapa hakumfanyi mwanachama moja kwa moja wala hakumpa akaunti — mwaliko na uthibitisho wa WhatsApp vinahitajika.')" />
    <form method="POST" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}" class="card card-pad max-w-3xl space-y-5">
        @csrf @if($member->exists) @method('PUT') @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="full_name" :label="__('Jina kamili')" :value="$member->full_name" required />
            <x-field name="preferred_name" :label="__('Jina la kuitwa')" :value="$member->preferred_name" />
            <x-field name="phone" type="tel" :label="__('Simu (WhatsApp)')" :value="$member->phone_e164 ? '+'.$member->phone_e164 : ''" :hint="__('Itatumika kwa mwaliko na uthibitisho.')" />
            <x-field name="email" type="email" :label="__('Barua pepe')" :value="$member->email" />
            <x-field name="date_of_birth" type="date" :label="__('Tarehe ya kuzaliwa')" :value="$member->date_of_birth?->format('Y-m-d')" />
            <x-field name="birthday_visibility" type="select" :label="__('Siku ya kuzaliwa ionekane kwa')" :value="$member->birthday_visibility" :options="['members' => __('Wanafamilia wote'), 'leaders' => __('Uongozi tu'), 'private' => __('Yeye tu')]" />
            <x-field name="household_id" type="select" :label="__('Kaya')" :value="$member->household_id" :options="['' => '—'] + $households->pluck('name', 'id')->all()" />
            <x-field name="relationship_note" :label="__('Uhusiano na familia')" :value="$member->relationship_note" :hint="__('Mf. mtoto wa …, mke wa …')" />
            @unless ($member->exists)
                <x-field name="membership_status" type="select" :label="__('Hali ya uanachama')" :value="$member->membership_status" :options="\App\Support\Labels::options(\App\Models\Member::STATUSES)" />
            @endunless
            <x-field name="joined_on" type="date" :label="__('Alijiunga lini')" :value="$member->joined_on?->format('Y-m-d')" />
            <x-field name="emergency_contact_name" :label="__('Mtu wa dharura')" :value="$member->emergency_contact_name" />
            <x-field name="emergency_contact_phone" :label="__('Simu ya mtu wa dharura')" :value="$member->emergency_contact_phone" />
        </div>
        <label class="flex items-center gap-2.5"><input type="hidden" name="is_minor" value="0"><input type="checkbox" name="is_minor" value="1" class="size-5 rounded border-sand text-forest-700" @checked(old('is_minor', $member->is_minor))> {{ __('Ni mtoto (chini ya miaka 18) — taarifa zake zitalindwa zaidi') }}</label>
        <x-field name="leader_notes" type="textarea" :label="__('Maelezo ya uongozi (siri)')" :value="$member->leader_notes" :hint="__('Yanaonekana kwa wenye ruhusa ya taarifa binafsi tu. Yamehifadhiwa kwa usimbaji fiche.')" />
        <div class="flex gap-2"><button class="btn-primary">{{ __('Hifadhi') }}</button><a href="{{ $member->exists ? route('members.show', $member) : route('members.index') }}" class="btn-ghost">{{ __('Ghairi') }}</a></div>
    </form>
    @unless ($member->exists)
        <form method="POST" action="{{ route('households.store') }}" class="card card-pad mt-6 flex max-w-3xl flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <x-field name="name" :label="__('Kaya mpya')" class="flex-1" :hint="__('Mf. Familia ya Bw. na Bi. …')" />
            <button class="btn-ghost">{{ __('Ongeza kaya') }}</button>
        </form>
    @endunless
</x-layouts.app>
