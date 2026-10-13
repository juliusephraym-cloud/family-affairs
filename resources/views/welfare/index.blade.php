<x-layouts.app :title="__('Jamii na Sadaka')" wide>
    @php($types = ['bereavement' => __('Msiba'), 'illness' => __('Ugonjwa'), 'hardship' => __('Changamoto'), 'celebration' => __('Pongezi/sherehe'), 'other' => __('Nyingine')])
    <x-page-header :eyebrow="__('Tunabebana')" :title="__('Jamii na Sadaka')" :description="__('Rambirambi, msaada wa jamii na sadaka kwa wahitaji — kwa heshima na usiri unaostahili.')" />
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <section>
                <h2 class="mb-3 font-display text-xl font-semibold text-forest-900">{{ __('Kesi za jamii') }}</h2>
                <div class="space-y-3">
                    @forelse ($cases as $c)
                        <a href="{{ route('welfare.show', $c) }}" class="card flex items-start gap-4 p-4 hover:shadow-[var(--shadow-lift)]">
                            <span @class(['grid size-11 shrink-0 place-items-center rounded-full', 'bg-forest-100 text-forest-800' => $c->type === 'bereavement', 'bg-clay-100 text-clay-700' => $c->type !== 'bereavement'])><x-icon name="heart" class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2"><span class="font-display text-lg font-semibold text-forest-900">{{ $c->title }}</span>@if($c->visibility !== 'members')<span class="badge-grey"><x-icon name="lock" class="size-3" />{{ $c->visibility === 'private' ? __('Binafsi') : __('Uongozi') }}</span>@endif</span>
                                <span class="block text-sm text-muted">{{ $types[$c->type] }} · {{ $c->created_at->translatedFormat('j F Y') }}</span>
                                <span class="mt-1 line-clamp-2 block text-sm">{{ $c->summary }}</span>
                            </span>
                            <x-status :value="$c->status" />
                        </a>
                    @empty <x-empty icon="heart" :title="__('Hakuna kesi kwa sasa')" :text="__('Mungu atujalie amani.')" /> @endforelse
                </div>
            </section>
            <section>
                <h2 class="mb-3 font-display text-xl font-semibold text-forest-900">{{ __('Sadaka kwa wahitaji') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @forelse ($activities as $a)
                        <a href="{{ route('charity.show', $a) }}" class="card card-pad hover:shadow-[var(--shadow-lift)]">
                            <div class="flex justify-between gap-2"><p class="font-display text-lg font-semibold text-forest-900">{{ $a->title }}</p><x-status :value="$a->status" /></div>
                            <p class="text-sm text-muted">{{ $a->beneficiary }}</p>
                            <p class="mt-2 text-sm">{{ $a->planned_for?->translatedFormat('l j F, H:i') }}</p>
                            <p class="mt-1 text-sm text-muted">{{ trans_choice('{0} hakuna aliyejiandikisha|{1} mjitoleaji :n|[2,*] wajitoleaji :n', $a->volunteers->count(), ['n' => $a->volunteers->count()]) }}</p>
                        </a>
                    @empty <x-empty icon="gift" :title="__('Hakuna shughuli ya sadaka iliyopangwa')" class="sm:col-span-2" /> @endforelse
                </div>
            </section>
        </div>
        <div class="space-y-6">
            @can('welfare.submit')
                <x-card :title="__('Toa taarifa ya jamii')" icon="heart">
                    <form method="POST" action="{{ route('welfare.store') }}" class="space-y-3" x-data="{ type: 'bereavement' }">@csrf
                        <label class="block"><span class="field-label">{{ __('Aina') }}</span><select name="type" class="input" x-model="type">@foreach ($types as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                        <x-field name="title" :label="__('Kichwa')" required :placeholder="__('Mf. Msiba wa mama wa …')" />
                        <x-field name="beneficiary_member_id" type="select" :label="__('Mwanafamilia anayehusika')" :options="['' => '—'] + $members->pluck('full_name', 'id')->all()" />
                        <div x-show="type === 'bereavement'"><x-field name="relationship" type="select" :label="__('Uhusiano wa marehemu na mwanafamilia')" :options="['parent' => __('Mzazi (baba/mama)'), 'child' => __('Mtoto wa kuzaa'), 'spouse' => __('Mke/mume'), 'other' => __('Mwingine')]" /></div>
                        <x-field name="summary" type="textarea" rows="3" :label="__('Taarifa kwa familia')" required :hint="__('Hii ndiyo itakayoonekana kwa wanachama. Usiweke taarifa za kitabibu.')" />
                        <x-field name="private_details" type="textarea" rows="2" :label="__('Maelezo ya siri (uongozi tu)')" :hint="__('Yanahifadhiwa kwa usimbaji fiche.')" />
                        <x-field name="visibility" type="select" :label="__('Nani aone kesi hii')" :options="['members' => __('Wanafamilia wote (baada ya idhini)'), 'leaders' => __('Uongozi tu'), 'private' => __('Wahusika wa jamii tu')]" />
                        <button class="btn-primary w-full">{{ __('Wasilisha kwa uongozi') }}</button>
                    </form>
                </x-card>
            @endcan
            @can('charity.manage')
                <x-card :title="__('Panga sadaka / ziara')" icon="gift">
                    <form method="POST" action="{{ route('charity.store') }}" class="space-y-3">@csrf
                        <x-field name="title" :label="__('Kichwa')" required />
                        <x-field name="beneficiary" :label="__('Wanufaika / kituo')" />
                        <x-field name="planned_for" type="datetime-local" :label="__('Tarehe')" />
                        <x-field name="budget" :label="__('Bajeti')" inputmode="numeric" />
                        <x-field name="coordinator_member_id" type="select" :label="__('Mratibu')" :options="['' => '—'] + $members->pluck('full_name', 'id')->all()" />
                        <x-field name="description" type="textarea" rows="3" :label="__('Maelezo')" />
                        <button class="btn-primary w-full">{{ __('Unda') }}</button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.app>
