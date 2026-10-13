<x-layouts.app :title="$job->title" wide>
    <x-page-header :eyebrow="$job->template->name" :title="$job->title" :description="__('Imepangwa :d', ['d' => $job->scheduled_at->translatedFormat('l, j F Y · H:i')])">
        <x-slot:actions>
            <x-status :value="$job->status" />
            @if ($job->status === 'pending_approval')@can('notifications.approve')<form method="POST" action="{{ route('notifications.approve', $job) }}">@csrf<button class="btn-primary btn-sm" @disabled($job->created_by === auth()->id())>{{ __('Idhinisha') }}</button></form>@endcan @endif
            @if ($job->isCancellable())@can('notifications.manage')<x-confirm-button :action="route('notifications.cancel', $job)" :label="__('Sitisha')" variant="btn-danger btn-sm" :confirm="__('Sitisha ujumbe huu?')" />@endcan @endif
            @if (($counts['failed'] ?? 0) > 0)@can('notifications.manage')<x-confirm-button :action="route('notifications.retry', $job)" :label="__('Jaribu tena zilizoshindwa')" variant="btn-ghost btn-sm" :confirm="__('Zilizoshindwa tu ndizo zitatumwa tena. Zilizopokelewa na mtoa huduma hazitarudiwa.')" />@endcan @endif
        </x-slot:actions>
    </x-page-header>
    <div class="mb-6 grid gap-4 grid-cols-2 sm:grid-cols-3 xl:grid-cols-6">
        @foreach (['queued', 'submitted', 'delivered', 'read', 'failed', 'skipped'] as $st)
            <div class="card p-4"><p class="text-sm text-muted">{{ \App\Support\Labels::status($st) }}</p><p class="stat text-2xl">{{ $counts[$st] ?? 0 }}</p></div>
        @endforeach
    </div>
    <p class="mb-4 text-sm text-muted">{{ __('"Imetumwa" = mtoa huduma ameupokea ujumbe. "Imefika/Imesomwa" huonyeshwa tu baada ya NextSMS kuthibitisha kupitia webhook.') }}
        {{ __('Aliyeandaa: :c', ['c' => $job->creator?->displayName() ?? __('Mfumo (kiotomatiki)')]) }}@if($job->approver) · {{ __('Aliyeidhinisha: :a', ['a' => $job->approver->displayName()]) }}@endif · {{ __('Makadirio: :c', ['c' => \App\Support\Money::format($job->estimated_cost)]) }}</p>
    <div class="card overflow-hidden"><div class="table-wrap"><table class="table">
        <thead><tr><th>{{ __('Mpokeaji') }}</th><th>{{ __('Hali') }}</th><th>{{ __('Majaribio') }}</th><th>{{ __('Mtoa huduma') }}</th><th>{{ __('Kosa') }}</th></tr></thead>
        <tbody>
            @forelse ($deliveries as $d)
                <tr>
                    <td>{{ $d->member->full_name }}<p class="text-xs text-muted">{{ \App\Support\Phone::mask($d->phone_e164) }}</p></td>
                    <td><x-status :value="$d->status" />@if($d->skip_reason)<p class="text-xs text-muted">{{ $d->skip_reason }}</p>@endif @if($d->next_attempt_at && $d->status === 'queued')<p class="text-xs text-muted">{{ __('Jaribio lijalo :t', ['t' => $d->next_attempt_at->format('H:i')]) }}</p>@endif</td>
                    <td>{{ $d->attempts }}</td>
                    <td class="text-xs">@if($canLogs){{ $d->provider_status_name }} @if($d->provider_message_id)<span class="block font-mono text-muted">{{ $d->provider_message_id }}</span>@endif @endif</td>
                    <td class="text-xs text-clay-700">{{ $d->last_error }}</td>
                </tr>
            @empty <tr><td colspan="5" class="py-8 text-center text-muted">{{ $job->status === 'scheduled' || $job->status === 'pending_approval' ? __('Wapokeaji wataandaliwa wakati wa kutuma (wanakadiriwa :n).', ['n' => $job->estimated_recipients]) : __('Hakuna.') }}</td></tr> @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-4">{{ $deliveries->links() }}</div>
</x-layouts.app>
