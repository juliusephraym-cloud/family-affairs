@props(['action', 'method' => 'POST', 'label', 'confirm' => null, 'variant' => 'btn-ghost btn-sm', 'reason' => false, 'reasonLabel' => null])
{{-- A button that posts a form; optional confirm dialog and optional mandatory reason. --}}
<div x-data="{ open: false }" class="inline">
    @if ($reason)
        <button type="button" class="{{ $variant }}" @click="open = true">{{ $label }}</button>
        <div x-cloak x-show="open" class="fixed inset-0 z-50 grid place-items-center bg-forest-950/50 p-4" @keydown.escape.window="open = false">
            <form method="POST" action="{{ $action }}" class="card card-pad w-full max-w-md" @click.outside="open = false">
                @csrf @if($method !== 'POST') @method($method) @endif
                {{ $slot }}
                <p class="card-title mb-2">{{ $label }}</p>
                @if ($confirm)<p class="mb-3 text-sm text-muted">{{ $confirm }}</p>@endif
                <label class="field-label" for="reason_{{ md5($action) }}">{{ $reasonLabel ?? __('Sababu (itahifadhiwa kwenye kumbukumbu)') }}</label>
                <textarea id="reason_{{ md5($action) }}" name="reason" class="input" required minlength="3"></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn-ghost btn-sm" @click="open = false">{{ __('Ghairi') }}</button>
                    <button class="btn-primary btn-sm">{{ __('Thibitisha') }}</button>
                </div>
            </form>
        </div>
    @else
        <form method="POST" action="{{ $action }}" class="inline" @if($confirm) x-data @submit="if (! confirm(@js($confirm))) $event.preventDefault()" @endif>
            @csrf @if($method !== 'POST') @method($method) @endif
            {{ $slot }}
            <button class="{{ $variant }}">{{ $label }}</button>
        </form>
    @endif
</div>
