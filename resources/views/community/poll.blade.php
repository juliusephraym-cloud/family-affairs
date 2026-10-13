<x-layouts.app :title="$poll->question">
    <div class="card card-pad mx-auto max-w-2xl">
        <p class="eyebrow">{{ $poll->isOpen() ? __('Kura iko wazi hadi :d', ['d' => $poll->closes_at->translatedFormat('j M H:i')]) : __('Kura imefungwa') }}</p>
        <h1 class="mt-2 font-display text-3xl font-semibold text-forest-900">{{ $poll->question }}</h1>
        @if ($poll->description)<p class="mt-2 text-muted">{{ $poll->description }}</p>@endif
        @if ($poll->isOpen())
            <form method="POST" action="{{ route('polls.vote', $poll) }}" class="mt-6 space-y-2">@csrf
                @foreach ($poll->options as $o)
                    <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-line p-4 hover:border-forest-300 has-[:checked]:border-forest-600 has-[:checked]:bg-forest-50">
                        <input type="{{ $poll->allow_multiple ? 'checkbox' : 'radio' }}" name="options[]" value="{{ $o->id }}" class="size-5 border-sand text-forest-700" @checked($myVotes->contains($o->id))>
                        <span class="font-bold">{{ $o->label }}</span>
                    </label>
                @endforeach
                <button class="btn-primary mt-3">{{ $myVotes->isNotEmpty() ? __('Badilisha kura yangu') : __('Piga kura') }}</button>
            </form>
        @endif
        @if ($showResults)
            @php($total = max(1, $poll->votes->unique('user_id')->count()))
            <div class="mt-8 space-y-3">
                <p class="font-bold">{{ __('Matokeo (:n wamepiga kura)', ['n' => $poll->votes->unique('user_id')->count()]) }}</p>
                @foreach ($poll->options->sortByDesc(fn ($o) => $o->votes->count()) as $o)
                    @php($pct = round($o->votes->count() / $total * 100))
                    <div>
                        <div class="flex justify-between text-sm"><span class="font-bold">{{ $o->label }}</span><span>{{ $o->votes->count() }} · {{ $pct }}%</span></div>
                        <div class="mt-1 h-3 overflow-hidden rounded-full bg-cream"><div class="h-full rounded-full bg-gold-500" style="width: {{ $pct }}%"></div></div>
                        @unless ($poll->is_anonymous)<p class="mt-1 text-xs text-muted">{{ $o->votes->map(fn ($v) => $v->user->displayName())->join(', ') }}</p>@endunless
                    </div>
                @endforeach
            </div>
        @elseif (! $poll->isOpen() || $myVotes->isNotEmpty())
            <p class="mt-6 text-sm text-muted">{{ __('Matokeo yataonekana kulingana na utaratibu wa kura hii.') }}</p>
        @endif
    </div>
</x-layouts.app>
