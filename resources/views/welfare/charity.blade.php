<x-layouts.app :title="$activity->title">
    <x-page-header :eyebrow="__('Sadaka kwa wahitaji')" :title="$activity->title" :description="$activity->beneficiary">
        <x-slot:actions><x-status :value="$activity->status" /></x-slot:actions>
    </x-page-header>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                @if ($activity->planned_for)<p class="flex items-center gap-2 font-bold"><x-icon name="calendar" class="size-5 text-gold-600" />{{ $activity->planned_for->translatedFormat('l, j F Y · H:i') }}</p>@endif
                @if ($activity->description)<p class="mt-3 whitespace-pre-line">{{ $activity->description }}</p>@endif
                @if ($activity->logistics)<p class="mt-3 text-sm whitespace-pre-line text-muted">{{ $activity->logistics }}</p>@endif
                @if ($activity->report)<div class="mt-4 rounded-xl bg-forest-50 p-4"><p class="mb-1 font-bold text-forest-800">{{ __('Ripoti') }}</p><p class="whitespace-pre-line text-sm">{{ $activity->report }}</p></div>@endif
            </x-card>
            <x-card :title="__('Wajitoleaji')">
                <div class="flex flex-wrap gap-2">@forelse ($activity->volunteers as $v)<span class="badge-grey">{{ $v->displayName() }}@if($v->pivot->attended) ✓@endif</span>@empty<p class="text-muted">{{ __('Bado hakuna.') }}</p>@endforelse</div>
                @if (in_array($activity->status, ['proposed', 'approved']))
                    <form method="POST" action="{{ route('charity.volunteer', $activity) }}" class="mt-4">@csrf
                        <button class="btn-primary btn-sm">{{ $activity->volunteers->contains('id', auth()->user()->member_id) ? __('Jiondoe') : __('Nitashiriki') }}</button>
                    </form>
                @endif
            </x-card>
        </div>
        <div class="space-y-6">
            <x-card :title="__('Fedha')">
                <p class="text-sm text-muted">{{ __('Bajeti') }}</p><p class="stat"><x-money :amount="$activity->budget" /></p>
                <p class="mt-3 text-sm text-muted">{{ __('Salio la mfuko wa sadaka sasa') }}</p><p class="font-display text-xl font-semibold"><x-money :amount="$charityBalance" /></p>
                <p class="mt-3 text-sm text-muted">{{ __('Matumizi yaliyolipwa') }}</p><p class="font-display text-xl font-semibold"><x-money :amount="$activity->expenses->where('status', 'paid')->sum('amount')" /></p>
                <p class="mt-3 text-xs text-muted">{{ __('Sadaka hutumwa kupitia "Tuma taarifa ya malipo" → Sadaka. Malipo ya hospitali/kituo yanapitia Hazina → Matumizi.') }}</p>
            </x-card>
            @can('charity.manage')
                <x-card :title="__('Hariri')">
                    <form method="POST" action="{{ route('charity.update', $activity) }}" class="space-y-3">@csrf @method('PUT')
                        <x-field name="title" :label="__('Kichwa')" :value="$activity->title" required />
                        <x-field name="beneficiary" :label="__('Wanufaika')" :value="$activity->beneficiary" />
                        <x-field name="planned_for" type="datetime-local" :label="__('Tarehe')" :value="$activity->planned_for?->format('Y-m-d\TH:i')" />
                        <x-field name="budget" :label="__('Bajeti')" :value="$activity->budget" />
                        <x-field name="status" type="select" :label="__('Hali')" :value="$activity->status" :options="\App\Support\Labels::options(['proposed', 'approved', 'done', 'cancelled'])" />
                        <x-field name="logistics" type="textarea" rows="2" :label="__('Mipango (usafiri, barua, n.k.)')" :value="$activity->logistics" />
                        <x-field name="report" type="textarea" rows="3" :label="__('Ripoti baada ya shughuli')" :value="$activity->report" />
                        @if ($activity->volunteers->isNotEmpty())
                            <fieldset><legend class="field-label">{{ __('Walioshiriki') }}</legend>
                                <input type="hidden" name="attended[]" value="0">
                                @foreach ($activity->volunteers as $v)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="attended[]" value="{{ $v->id }}" class="size-4 rounded border-sand" @checked($v->pivot->attended)> {{ $v->full_name }}</label>@endforeach
                            </fieldset>
                        @endif
                        <input type="hidden" name="description" value="{{ $activity->description }}">
                        <input type="hidden" name="coordinator_member_id" value="{{ $activity->coordinator_member_id }}">
                        <button class="btn-ghost w-full">{{ __('Hifadhi') }}</button>
                    </form>
                </x-card>
            @endcan
        </div>
    </div>
</x-layouts.app>
