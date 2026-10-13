<x-layouts.app :title="$post->title">
    <article class="card card-pad mx-auto max-w-3xl">
        <div class="flex items-center gap-3">
            <x-avatar :member="$post->author?->member" size="size-12" />
            <div><p class="font-bold">{{ $post->author?->displayName() }}</p><p class="text-sm text-muted">{{ $post->published_at?->translatedFormat('l, j F Y · H:i') }}</p></div>
            <div class="ml-auto flex gap-1">@if ($post->kind === 'official')<span class="badge-gold">{{ __('Rasmi') }}</span>@endif @if ($post->is_important)<span class="badge-clay">{{ __('Muhimu') }}</span>@endif</div>
        </div>
        <h1 class="mt-5 font-display text-3xl font-semibold text-forest-900">{{ $post->title }}</h1>
        <div class="mt-4 text-lg whitespace-pre-line">{{ $post->body }}</div>
        <div class="mt-6 flex flex-wrap gap-2 border-t border-line pt-4">
            @foreach ($reactions as $emoji)
                @php($mine = $post->reactions->where('emoji', $emoji)->contains('user_id', auth()->id()))
                <form method="POST" action="{{ route('community.react', $post) }}">@csrf<input type="hidden" name="emoji" value="{{ $emoji }}">
                    <button @class(['rounded-full border px-3 py-1.5 text-sm font-bold', 'border-gold-400 bg-gold-50' => $mine, 'border-line hover:bg-ivory' => ! $mine])>{{ $emoji }} {{ $post->reactions->where('emoji', $emoji)->count() ?: '' }}</button></form>
            @endforeach
            @can('community.moderate')
                <div class="ml-auto flex gap-2">
                    <form method="POST" action="{{ route('community.moderate', $post) }}">@csrf<input type="hidden" name="action" value="{{ $post->is_pinned ? 'unpin' : 'pin' }}"><button class="btn-ghost btn-sm">{{ $post->is_pinned ? __('Ondoa bandiko') : __('Bandika') }}</button></form>
                    <form method="POST" action="{{ route('community.moderate', $post) }}">@csrf<input type="hidden" name="action" value="{{ $post->status === 'hidden' ? 'show' : 'hide' }}"><button class="btn-ghost btn-sm">{{ $post->status === 'hidden' ? __('Onyesha') : __('Ficha') }}</button></form>
                </div>
            @endcan
        </div>
    </article>
    <section class="mx-auto mt-6 max-w-3xl space-y-3">
        <h2 class="font-display text-xl font-semibold text-forest-900">{{ __('Maoni') }}</h2>
        @foreach ($post->comments as $c)
            <div class="flex gap-3 rounded-2xl bg-paper p-4 ring-1 ring-line">
                <x-avatar :member="$c->user->member" size="size-9" />
                <div class="min-w-0 flex-1"><p class="text-sm"><strong>{{ $c->user->displayName() }}</strong> <span class="text-muted">· {{ $c->created_at->diffForHumans() }}</span></p><p class="whitespace-pre-line">{{ $c->body }}</p></div>
                @can('community.moderate')<form method="POST" action="{{ route('community.moderate', $post) }}">@csrf<input type="hidden" name="action" value="hide_comment"><input type="hidden" name="comment_id" value="{{ $c->id }}"><button class="text-xs font-bold text-clay-700" aria-label="{{ __('Ficha maoni') }}">{{ __('Ficha') }}</button></form>@endcan
            </div>
        @endforeach
        @can('community.post')
            <form method="POST" action="{{ route('community.comment', $post) }}" class="flex gap-2">@csrf<textarea name="body" rows="2" class="input" placeholder="{{ __('Andika maoni…') }}" required aria-label="{{ __('Maoni') }}"></textarea><button class="btn-primary self-end">{{ __('Tuma') }}</button></form>
        @endcan
    </section>
</x-layouts.app>
