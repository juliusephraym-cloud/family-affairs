<!DOCTYPE html><html><head><meta charset="utf-8">@include('reports._pdf-style')</head><body>
<div class="head"><div class="brand">{{ \App\Support\Settings::get('family.name') }}</div><div class="band"></div>
<div style="margin-top:8px;font-size:14px;font-weight:bold">{{ mb_strtoupper(__('Kumbukumbu za kikao')) }}: {{ $meeting->title }}</div>
<div class="sub">{{ $meeting->starts_at->translatedFormat('l, j F Y · H:i') }} · {{ $meeting->location ?: $meeting->online_method }} · {{ \App\Support\Labels::status($minutes->status) }}</div></div>
<h2>{{ __('Agenda') }}</h2><ol>@foreach ($meeting->agendaItems as $i)<li>{{ $i->title }}</li>@endforeach</ol>
<h2>{{ __('Waliohudhuria') }}</h2>
<table><tr><th>#</th><th>{{ __('Jina') }}</th><th>{{ __('Cheo') }}</th><th>{{ __('Hali') }}</th></tr>
@foreach ($meeting->attendances->sortBy(fn ($a) => $a->status) as $a)<tr><td>{{ $loop->iteration }}</td><td>{{ $a->member->full_name }}</td><td>{{ $a->role_at_meeting }}</td><td>{{ \App\Support\Labels::status($a->status) }}</td></tr>@endforeach</table>
@if ($minutes->opening_notes)<h2>{{ __('Ufunguzi') }}</h2><p>{!! nl2br(e($minutes->opening_notes)) !!}</p>@endif
@foreach ($meeting->agendaItems as $item)
    <h2>{{ $loop->iteration }}. {{ $item->title }}</h2>
    <p>{!! nl2br(e($item->discussion ?: '—')) !!}</p>
    @foreach ($item->decisions as $dc)<p><strong>{{ __('Uamuzi') }}:</strong> {{ $dc->title }} — {{ $dc->body }}</p>@endforeach
@endforeach
@if ($meeting->actionItems->isNotEmpty())
<h2>{{ __('Majukumu') }}</h2><table><tr><th>{{ __('Jukumu') }}</th><th>{{ __('Mhusika') }}</th><th>{{ __('Mwisho') }}</th></tr>
@foreach ($meeting->actionItems as $a)<tr><td>{{ $a->title }}</td><td>{{ $a->assignee?->full_name }}</td><td>{{ $a->due_on?->format('d/m/Y') }}</td></tr>@endforeach</table>
@endif
@if ($minutes->next_meeting_note)<h2>{{ __('Kikao kijacho') }}</h2><p>{{ $minutes->next_meeting_note }}</p>@endif
@if ($minutes->closing_notes)<h2>{{ __('Kufunga') }}</h2><p>{!! nl2br(e($minutes->closing_notes)) !!}</p>@endif
<table style="margin-top:28px"><tr><td style="width:50%">{{ __('Imeandaliwa na') }}: {{ $minutes->preparer?->displayName() }}<br><br>{{ __('Sahihi') }}: ____________________</td>
<td>{{ __('Imeidhinishwa na') }}: {{ $minutes->approver?->displayName() ?? '—' }} {{ $minutes->approved_at?->format('d/m/Y') }}<br><br>{{ __('Sahihi') }}: ____________________</td></tr></table>
<div class="foot">{{ __('SIRI — kwa matumizi ya wanafamilia tu') }}</div>
</body></html>
