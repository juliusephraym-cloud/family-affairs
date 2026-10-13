<x-layouts.app :title="__('Kumbukumbu za ukaguzi')" wide>
    <x-page-header :eyebrow="__('Utawala')" :title="__('Kumbukumbu za ukaguzi')" :description="__('Kila hatua nyeti: fedha, nafasi, uanachama, ujumbe, usiri. Haziwezi kubadilishwa wala kufutwa.')" />
    <form method="GET" class="mb-5 flex flex-wrap items-end gap-3">
        <label><span class="field-label">{{ __('Aina') }}</span><select name="action" class="input"><option value="">{{ __('Zote') }}</option>@foreach ($actions->map(fn ($a) => \Illuminate\Support\Str::before($a, '.'))->unique() as $g)<option value="{{ $g }}" @selected(request('action') === $g)>{{ $g }}</option>@endforeach</select></label>
        <label><span class="field-label">{{ __('Kuanzia') }}</span><input type="date" name="from" value="{{ request('from') }}" class="input"></label>
        <button class="btn-ghost">{{ __('Chuja') }}</button>
    </form>
    <div class="card overflow-hidden"><div class="table-wrap"><table class="table">
        <thead><tr><th>{{ __('Wakati') }}</th><th>{{ __('Aliyefanya') }}</th><th>{{ __('Hatua') }}</th><th>{{ __('Maelezo') }}</th></tr></thead>
        <tbody>@foreach ($logs as $l)
            <tr><td class="text-sm whitespace-nowrap">{{ $l->created_at->format('d/m/Y H:i') }}</td><td class="text-sm">{{ $l->actor?->displayName() ?? __('Mfumo') }}<p class="text-xs text-muted">{{ $l->ip_address }}</p></td><td><code class="text-xs">{{ $l->action }}</code></td>
                <td class="text-sm">{{ $l->description }}@if($l->properties)<p class="mt-1 font-mono text-xs break-all text-muted">{{ json_encode($l->properties, JSON_UNESCAPED_UNICODE) }}</p>@endif</td></tr>
        @endforeach</tbody>
    </table></div></div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
