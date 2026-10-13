<x-layouts.app :title="__('Kumbukumbu za kikao')" wide>
    <x-page-header :eyebrow="__('Kumbukumbu — :s', ['s' => \App\Support\Labels::status($minutes->status)])" :title="$meeting->title" :description="$meeting->starts_at->translatedFormat('l, j F Y')" />
    @if ($minutes->review_note)<div class="mb-6 rounded-2xl border border-clay-200 bg-clay-50 p-4 text-clay-700"><strong>{{ __('Maoni ya mpitiaji:') }}</strong> {{ $minutes->review_note }}</div>@endif
    <form method="POST" action="{{ route('minutes.update', $meeting) }}" class="space-y-6">@csrf @method('PUT')
        <x-card :title="__('Ufunguzi')"><x-field name="opening_notes" type="textarea" rows="3" :label="__('Sala ya ufunguzi, shukrani, utambulisho')" :value="$minutes->opening_notes" /></x-card>
        <x-card :title="__('Mahudhurio')">
            <p class="text-sm">{{ $meeting->attendances->where('status', 'present')->map(fn ($a) => $a->member->full_name.($a->role_at_meeting ? ' ('.$a->role_at_meeting.')' : ''))->join(', ') ?: __('Mahudhurio hayajarekodiwa — yarekodi kwenye ukurasa wa kikao.') }}</p>
            @if ($meeting->attendances->where('status', 'apology')->isNotEmpty())<p class="mt-2 text-sm text-muted">{{ __('Udhuru:') }} {{ $meeting->attendances->where('status', 'apology')->map(fn ($a) => $a->member->full_name)->join(', ') }}</p>@endif
        </x-card>
        @foreach ($meeting->agendaItems as $item)
            <x-card :title="$loop->iteration.'. '.$item->title">
                <label class="sr-only" for="d{{ $item->id }}">{{ $item->title }}</label>
                <textarea id="d{{ $item->id }}" name="discussion[{{ $item->id }}]" rows="5" class="input" placeholder="{{ __('Yaliyojadiliwa. Usiweke kiasi ambacho hakijathibitishwa — andika \"kinahitaji uthibitisho\".') }}">{{ old('discussion.'.$item->id, $item->discussion) }}</textarea>
                @foreach ($item->decisions as $dc)<p class="mt-2 text-sm"><strong>{{ __('Uamuzi:') }}</strong> {{ $dc->title }} — {{ $dc->body }}</p>@endforeach
            </x-card>
        @endforeach
        <x-card :title="__('Kufunga')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-field name="next_meeting_note" :label="__('Kikao kijacho')" :value="$minutes->next_meeting_note" />
                <x-field name="closing_notes" type="textarea" rows="2" :label="__('Kufunga kikao')" :value="$minutes->closing_notes" />
            </div>
        </x-card>
        <div class="flex flex-wrap gap-2"><button class="btn-primary">{{ __('Hifadhi rasimu') }}</button><a href="{{ route('meetings.show', $meeting) }}" class="btn-ghost">{{ __('Rudi kwenye kikao') }}</a></div>
    </form>
    @if ($minutes->status === 'draft')
        <form method="POST" action="{{ route('minutes.submit', $meeting) }}" class="mt-4">@csrf<button class="btn-gold">{{ __('Tuma kwa idhini') }}</button></form>
    @endif
</x-layouts.app>
