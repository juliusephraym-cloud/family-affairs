<x-layouts.app :title="__('Ujumbe wa WhatsApp')" wide>
    <x-page-header :eyebrow="__('NextSMS · WhatsApp')" :title="__('Ujumbe wa WhatsApp')" :description="__('Vikumbusho na matangazo. Kila ujumbe hutumwa na seva kwa ratiba — si kivinjari — na hali ya kila mpokeaji inahifadhiwa.')">
        <x-slot:actions>@can('notifications.manage')<a href="{{ route('notifications.create') }}" class="btn-primary"><x-icon name="plus" />{{ __('Andaa ujumbe') }}</a>@endcan</x-slot:actions>
    </x-page-header>
    <div @class(['mb-6 flex flex-wrap items-center gap-3 rounded-2xl border p-4', 'border-forest-200 bg-forest-50' => $provider['configured'] && $provider['name'] === 'nextsms', 'border-gold-200 bg-gold-50' => ! ($provider['configured'] && $provider['name'] === 'nextsms')])>
        <x-icon name="whatsapp" class="size-6 text-forest-700" />
        <p><strong>{{ __('Mtoa huduma:') }}</strong> {{ $provider['name'] }}
            @if ($provider['name'] === 'fake') — <span class="text-gold-700">{{ __('mtoa huduma wa majaribio (hakuna ujumbe halisi unaotumwa)') }}</span>
            @elseif (! $provider['configured']) — <span class="text-clay-700">{{ __('haijawekwa (NEXTSMS_TOKEN / NEXTSMS_WHATSAPP_ACCOUNT)') }}</span>
            @elseif ($provider['test_mode']) — <span class="text-gold-700">{{ __('hali ya majaribio ya NextSMS (hakuna kinachotumwa)') }}</span>@endif
        </p>
        @can('admin.settings')<a href="{{ route('admin.templates') }}" class="ml-auto text-sm font-bold text-forest-700 hover:underline">{{ __('Violezo na vikumbusho vya kiotomatiki') }}</a>@endcan
    </div>
    <div class="card overflow-hidden"><div class="table-wrap"><table class="table">
        <thead><tr><th>{{ __('Ujumbe') }}</th><th>{{ __('Muda') }}</th><th class="num">{{ __('Wapokeaji') }}</th><th class="num">{{ __('Umefika') }}</th><th class="num">{{ __('Umeshindwa') }}</th><th>{{ __('Hali') }}</th></tr></thead>
        <tbody>
            @forelse ($jobs as $j)
                <tr>
                    <td><a href="{{ route('notifications.show', $j) }}" class="font-bold text-forest-800 hover:underline">{{ $j->title }}</a><p class="text-xs text-muted">{{ $j->template->name }} · {{ $j->creator?->displayName() ?? __('Kiotomatiki') }}</p></td>
                    <td class="text-sm whitespace-nowrap">{{ $j->scheduled_at->format('d/m/Y H:i') }}</td>
                    <td class="num">{{ $j->deliveries_count ?: $j->estimated_recipients }}</td>
                    <td class="num">{{ $j->delivered_count }}</td>
                    <td class="num">@if($j->failed_count)<span class="font-bold text-clay-700">{{ $j->failed_count }}</span>@else 0 @endif</td>
                    <td><x-status :value="$j->status" /></td>
                </tr>
            @empty <tr><td colspan="6" class="py-8 text-center text-muted">{{ __('Hakuna ujumbe uliotumwa bado.') }}</td></tr> @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-4">{{ $jobs->links() }}</div>
</x-layouts.app>
