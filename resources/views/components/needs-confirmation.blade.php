@props(['text' => null])
<span {{ $attributes->merge(['class' => 'badge-clay']) }} title="{{ $text ?? __('Taarifa hii imetoka kwenye kumbukumbu za zamani na bado haijathibitishwa na uongozi.') }}">
    <x-icon name="alert" class="size-3.5" />{{ __('Inahitaji uthibitisho') }}
</span>
