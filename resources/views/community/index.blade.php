<x-layouts.app :title="__('Matangazo')" wide>
    <x-page-header :eyebrow="__('Habari za familia')" :title="__('Matangazo na mazungumzo')" :description="__('Mambo muhimu yasipotee kwenye WhatsApp: matangazo rasmi, habari za familia na kura.')" />
    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
        <div>
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex gap-1">@foreach (['all' => __('Yote'), 'official' => __('Rasmi tu')] as $k => $l)<a href="{{ route('community.index', ['show' => $k]) }}" @class(['tab', 'tab-active' => $filter === $k])>{{ $l }}</a>@endforeach</div>
                <form method="GET" class="relative"><input type="hidden" name="show" value="{{ $filter }}"><x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" /><input name="q" value="{{ request('q') }}" class="input min-h-10 pl-9" placeholder="{{ __('Tafuta…') }}" aria-label="{{ __('Tafuta') }}"></form>
            </div>
            <div class="space-y-4">
                @forelse ($posts as $p)
                    <article @class(['card card-pad', 'border-l-4 border-l-clay-500' => $p->is_important, 'border-l-4 border-l-gold-500' => $p->kind === 'official' && ! $p->is_important])>
                        <div class="flex items-start gap-3">
                            <x-avatar :member="$p->author?->member" size="size-10" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($p->kind === 'official')<span class="badge-gold">{{ __('Rasmi') }}</span>@endif
                                    @if ($p->is_important)<span class="badge-clay">{{ __('Muhimu') }}</span>@endif
                                    @if ($p->is_pinned)<x-icon name="pin" class="size-4 text-gold-600" label="{{ __('Limebandikwa') }}" />@endif
                                    @if ($p->status === 'hidden')<x-status value="hidden" />@endif
                                    <span class="text-sm text-muted">{{ $p->author?->displayName() }} · {{ $p->published_at?->diffForHumans() }}</span>
                                </div>
                                <a href="{{ route('community.show', $p) }}" class="mt-1 block font-display text-xl font-semibold text-forest-900 hover:underline">{{ $p->title }}</a>
                                <p class="mt-1 line-clamp-3 whitespace-pre-line text-ink-soft">{{ $p->body }}</p>
                                <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-muted">
                                    @foreach ($p->reactions->groupBy('emoji') as $emoji => $rs)<span>{{ $emoji }} {{ $rs->count() }}</span>@endforeach
                                    <a href="{{ route('community.show', $p) }}" class="flex items-center gap-1 font-bold text-forest-700 hover:underline"><x-icon name="chat" class="size-4" />{{ trans_choice('{0} toa maoni|{1} maoni :n|[2,*] maoni :n', $p->comments_count, ['n' => $p->comments_count]) }}</a>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty <x-empty icon="megaphone" :title="__('Hakuna chapisho bado')" /> @endforelse
                {{ $posts->links() }}
            </div>
        </div>
        <div class="space-y-6">
            @can('community.post')
                <x-card :title="__('Andika')" icon="plus">
                    <form method="POST" action="{{ route('community.store') }}" class="space-y-3" x-data="{ kind: 'post' }">@csrf
                        @can('community.announce_official')
                            <div class="flex gap-1"><button type="button" class="tab" :class="kind === 'post' && 'tab-active'" @click="kind = 'post'">{{ __('Habari') }}</button><button type="button" class="tab" :class="kind === 'official' && 'tab-active'" @click="kind = 'official'">{{ __('Tangazo rasmi') }}</button></div>
                        @endcan
                        <input type="hidden" name="kind" :value="kind" value="post">
                        <x-field name="title" :label="__('Kichwa')" required />
                        <x-field name="body" type="textarea" rows="4" :label="__('Ujumbe')" required />
                        <div x-show="kind === 'official'" x-cloak class="space-y-2 text-sm">
                            <label class="flex items-center gap-2"><input type="checkbox" name="is_important" value="1" class="size-4 rounded border-sand"> {{ __('Muhimu') }}</label>
                            <label class="flex items-center gap-2"><input type="checkbox" name="is_pinned" value="1" class="size-4 rounded border-sand"> {{ __('Bandika juu') }}</label>
                        </div>
                        @if (auth()->user()->isLeader())<x-field name="visibility" type="select" :label="__('Wanaoona')" :options="['members' => __('Wanafamilia wote'), 'leaders' => __('Uongozi tu')]" />@else<input type="hidden" name="visibility" value="members">@endif
                        <button class="btn-primary w-full">{{ __('Chapisha') }}</button>
                    </form>
                </x-card>
            @endcan
            <x-card :title="__('Kura')" icon="check">
                @forelse ($polls as $poll)
                    <a href="{{ route('polls.show', $poll) }}" class="block border-b border-line py-3 last:border-0 hover:underline">
                        <p class="font-bold">{{ $poll->question }}</p>
                        <p class="text-sm text-muted">{{ $poll->isOpen() ? __('Inafungwa :d', ['d' => $poll->closes_at->diffForHumans()]) : __('Imefungwa') }} · {{ trans_choice('{0} hakuna kura|{1} kura :n|[2,*] kura :n', $poll->votes_count, ['n' => $poll->votes_count]) }}</p>
                    </a>
                @empty <p class="text-muted">{{ __('Hakuna kura kwa sasa.') }}</p> @endforelse
                @can('community.create_poll')
                    <details class="mt-4 border-t border-line pt-3"><summary class="cursor-pointer font-bold text-forest-700">{{ __('Fungua kura mpya') }}</summary>
                        <form method="POST" action="{{ route('polls.store') }}" class="mt-3 space-y-3">@csrf
                            <x-field name="question" :label="__('Swali')" required />
                            <x-field name="options" type="textarea" rows="3" :label="__('Machaguo (moja kila mstari)')" required />
                            <x-field name="opens_at" type="datetime-local" :label="__('Inafunguliwa')" :value="now()->format('Y-m-d\TH:i')" required />
                            <x-field name="closes_at" type="datetime-local" :label="__('Inafungwa')" :value="now()->addDays(3)->format('Y-m-d\TH:i')" required />
                            <x-field name="eligibility" type="select" :label="__('Wanaopiga kura')" :options="['members' => __('Wanafamilia wote'), 'leaders' => __('Uongozi tu')]" />
                            <x-field name="result_visibility" type="select" :label="__('Matokeo yaonekane')" :options="['after_vote' => __('Baada ya kupiga kura'), 'always' => __('Wakati wote'), 'after_close' => __('Baada ya kufungwa'), 'leaders_only' => __('Kwa uongozi tu')]" />
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_multiple" value="1" class="size-4 rounded border-sand"> {{ __('Ruhusu kuchagua zaidi ya moja') }}</label>
                            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_anonymous" value="1" class="size-4 rounded border-sand"> {{ __('Kura za siri (majina hayaonyeshwi)') }}</label>
                            <button class="btn-primary w-full">{{ __('Fungua kura') }}</button>
                        </form>
                    </details>
                @endcan
            </x-card>
        </div>
    </div>
</x-layouts.app>
