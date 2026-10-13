@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null, 'required' => false, 'options' => null, 'rows' => 4])
@php($id = 'f_'.str_replace(['[', ']', '.'], '_', $name))
@php($old = old(str_replace(['[', ']'], ['.', ''], $name), $value))
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="field-label">{{ $label }}@if($required) <span class="text-clay-600" aria-hidden="true">*</span>@endif</label>
    @if ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" class="input" @required($required) {{ $attributes->except('class') }}>{{ $old }}</textarea>
    @elseif ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="input" @required($required) {{ $attributes->except('class') }}>
            {{ $slot }}
            @foreach ($options ?? [] as $v => $l)
                <option value="{{ $v }}" @selected((string) $old === (string) $v)>{{ $l }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'file' ? '' : $old }}" class="input" @required($required) {{ $attributes->except('class') }}>
    @endif
    @if ($hint)<p class="field-hint">{{ $hint }}</p>@endif
    @error(str_replace(['[', ']'], ['.', ''], $name))<p class="field-error">{{ $message }}</p>@enderror
</div>
