<x-layouts.app :title="__('Faini')">
    <x-page-header :eyebrow="__('Hazina')" :title="__('Faini za kuchelewa mchango')" :description="__('Faini haziwekwi kiotomatiki. Angalia orodha, kisha weka kwa wale tu wanaostahili kwa mujibu wa kanuni iliyothibitishwa.')" />
    @include('treasury._nav')
    <form method="GET" class="mb-5 flex items-end gap-3"><label><span class="field-label">{{ __('Mwezi') }}</span><input type="month" name="period" value="{{ $period }}" class="input"></label><button class="btn-ghost">{{ __('Angalia') }}</button></form>
    <div class="mb-5 rounded-2xl border p-4 {{ $rule?->isConfirmed() && $rule->scalar() ? 'border-forest-200 bg-forest-50' : 'border-clay-200 bg-clay-50' }}">
        <p class="font-bold">{{ __('Kanuni:') }} {{ $rule?->title() }} — {{ $rule?->scalar() ? \App\Support\Money::format((int) $rule->scalar()) : __('KIASI HAKIJULIKANI') }}</p>
        @if (! $rule?->isConfirmed() || ! $rule->scalar())
            <p class="mt-1 text-sm text-clay-700">{{ __('Kumbukumbu za kikao zinasema faini zitaanza, lakini kiasi hakikuandikwa. Uongozi uthibitishe kiasi kwenye Katiba na Kanuni kabla ya kuweka faini.') }}</p>
        @endif
        <p class="mt-1 text-xs text-muted">{{ $rule?->source_reference }}</p>
    </div>
    <form method="POST" action="{{ route('treasury.penalties.apply') }}" class="card card-pad">@csrf <input type="hidden" name="period" value="{{ $period }}">
        @forelse ($late as $o)
            <label class="flex items-center justify-between gap-3 border-b border-line py-2.5 last:border-0">
                <span class="flex items-center gap-3"><input type="checkbox" name="members[]" value="{{ $o->member_id }}" class="size-5 rounded border-sand" checked> {{ $o->member->full_name }}</span>
                <span class="text-sm text-muted">{{ __('Mwisho :d · bado :b', ['d' => $o->due_date->format('d/m/Y'), 'b' => \App\Support\Money::format($o->balance())]) }}</span>
            </label>
        @empty <p class="text-muted">{{ __('Hakuna aliyechelewa kwa mwezi huu (au faini tayari zimewekwa).') }}</p> @endforelse
        @if ($late->isNotEmpty())<button class="btn-primary mt-4" @disabled(! $rule?->isConfirmed() || ! $rule->scalar())>{{ __('Weka faini kwa waliochaguliwa') }}</button>@endif
    </form>
</x-layouts.app>
