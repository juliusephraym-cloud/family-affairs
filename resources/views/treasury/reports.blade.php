<x-layouts.app :title="__('Ripoti')" wide>
    <x-page-header :eyebrow="__('Hazina')" :title="__('Ripoti za fedha')" :description="__('Kila takwimu inatokana na ledger. Pakua PDF au CSV kwa vikao na ukaguzi.')" />
    @include('treasury._nav')
    <form method="GET" class="card card-pad mb-6 grid gap-4 md:grid-cols-[2fr_1fr_1fr_1fr_auto] md:items-end">
        <label><span class="field-label">{{ __('Ripoti') }}</span><select name="report" class="input">@foreach ($available as $k => $l)<option value="{{ $k }}" @selected($report === $k)>{{ __($l) }}</option>@endforeach</select></label>
        <label><span class="field-label">{{ __('Kipindi') }}</span><select name="preset" class="input"><option value="">{{ __('Tarehe maalum') }}</option><option value="month" @selected(request('preset') === 'month')>{{ __('Mwezi huu') }}</option><option value="quarter" @selected(request('preset') === 'quarter')>{{ __('Robo hii') }}</option><option value="year" @selected(request('preset') === 'year')>{{ __('Mwaka huu') }}</option></select></label>
        <label><span class="field-label">{{ __('Kuanzia') }}</span><input type="date" name="from" value="{{ $from->toDateString() }}" class="input"></label>
        <label><span class="field-label">{{ __('Hadi') }}</span><input type="date" name="to" value="{{ $to->toDateString() }}" class="input"></label>
        <button class="btn-primary">{{ __('Onyesha') }}</button>
    </form>
    <section class="card card-pad">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div><h2 class="card-title">{{ __($available[$report]) }}</h2><p class="text-sm text-muted">{{ $label }}</p></div>
            @can('finance.export')
                <div class="flex gap-2">
                    <a href="{{ route('treasury.reports.download', ['report' => $report, 'format' => 'pdf', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn-ghost btn-sm"><x-icon name="download" class="size-4" />PDF</a>
                    <a href="{{ route('treasury.reports.download', ['report' => $report, 'format' => 'csv', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn-ghost btn-sm"><x-icon name="download" class="size-4" />CSV</a>
                </div>
            @endcan
        </div>
        @if (isset($data['sections']))
            <p class="mb-3 text-sm">{{ __('Salio la mwanzo:') }} <x-money :amount="$data['opening']" class="font-bold" /></p>
            @foreach ($data['sections'] as $s)
                <h3 class="mt-4 mb-2 font-display text-lg font-semibold text-forest-900">{{ __($s['title']) }}</h3>
                <div class="table-wrap"><table class="table"><tbody>
                    @forelse ($s['rows'] as $r)<tr><td>{{ $r[0] }}</td><td class="text-muted">{{ $r[1] }}</td><td class="num"><x-money :amount="$r[2]" /></td></tr>@empty<tr><td class="text-muted">—</td></tr>@endforelse
                    <tr class="font-bold"><td colspan="2">{{ __('Jumla') }}</td><td class="num"><x-money :amount="$s['total']" /></td></tr>
                </tbody></table></div>
            @endforeach
            <div class="mt-4 flex flex-wrap gap-6 border-t border-line pt-4"><p>{{ __('Ziada/upungufu:') }} <x-money :amount="$data['net']" class="font-bold" /></p><p>{{ __('Salio la mwisho:') }} <x-money :amount="$data['closing']" class="font-bold" /></p></div>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>@foreach ($data['columns'] as $c)<th>{{ __($c) }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($data['rows'] as $r)<tr>@foreach ($r as $v)<td @class(['num' => is_int($v)])>{{ is_int($v) ? number_format($v) : $v }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($data['columns']) }}" class="py-6 text-center text-muted">{{ __('Hakuna taarifa kwa kipindi hiki.') }}</td></tr>@endforelse
                    @isset($data['totals'])<tr class="font-bold">@foreach ($data['totals'] as $v)<td @class(['num' => is_int($v)])>{{ $v === null ? ($loop->first ? __('Jumla') : '') : number_format((int) $v) }}</td>@endforeach</tr>@endisset
                </tbody>
            </table></div>
            @isset($data['note'])<p class="mt-3 text-sm text-muted">{{ $data['note'] }}</p>@endisset
        @endif
    </section>
</x-layouts.app>
