<x-layouts.app :title="__('Uzingatiaji wa michango')" wide>
    <x-page-header :eyebrow="__('Katiba na Kanuni')" :title="__('Uzingatiaji wa michango')"
        :description="__('Hatua tano kabla ya uamuzi wowote kuhusu uanachama: onyo → ukumbusho → mapitio ya uongozi → uamuzi uliorekodiwa → mabadiliko ya hali (kama yameamuliwa). Mfumo haufanyi hatua yoyote kiotomatiki.')" />
    <div class="mb-6 rounded-2xl border p-4 text-sm {{ $rule?->isConfirmed() ? 'border-forest-200 bg-forest-50' : 'border-clay-200 bg-clay-50' }}">
        <strong>{{ $rule?->title() }}: {{ $threshold }}</strong> @unless($rule?->isConfirmed())<x-needs-confirmation />@endunless
        <p class="mt-1 text-muted">{{ $rule?->source_reference }}</p>
    </div>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <h2 class="font-display text-xl font-semibold">{{ __('Kesi zinazoendelea') }}</h2>
            @forelse ($open as $c)
                <article class="card card-pad">
                    <div class="flex flex-wrap items-center justify-between gap-2"><p class="font-display text-lg font-semibold">{{ $c->member->full_name }}</p><x-status :value="$c->stage" /></div>
                    <ol class="my-4 flex items-center gap-1 text-xs font-bold" aria-label="{{ __('Hatua') }}">
                        @foreach (['warning', 'reminder', 'review', 'decided', 'closed'] as $i => $st)
                            @php($reached = array_search($c->stage, \App\Models\ComplianceCase::STAGES) >= $i)
                            <li class="flex flex-1 flex-col items-center gap-1"><span @class(['h-1.5 w-full rounded-full', 'bg-forest-600' => $reached, 'bg-cream' => ! $reached])></span><span @class(['text-forest-800' => $reached, 'text-muted' => ! $reached])>{{ \App\Support\Labels::status($st) }}</span></li>
                        @endforeach
                    </ol>
                    <p class="text-sm">{{ __('Wakati wa kufungua: miezi :m, deni :a', ['m' => $c->months_in_arrears, 'a' => \App\Support\Money::format($c->arrears_snapshot)]) }}</p>
                    @if ($c->notes)<p class="mt-2 text-sm whitespace-pre-line text-muted">{{ $c->notes }}</p>@endif
                    @if ($c->decision_text)<p class="mt-2 text-sm"><strong>{{ __('Uamuzi:') }}</strong> {{ $c->decision_text }}</p>@endif
                    @php($next = ['warning' => 'reminder', 'reminder' => 'review', 'review' => 'decided', 'decided' => 'closed'][$c->stage] ?? null)
                    @if ($next)
                        <form method="POST" action="{{ route('governance.compliance.advance', $c) }}" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-2" x-data="{ outcome: '' }">@csrf
                            <input type="hidden" name="stage" value="{{ $next }}">
                            <textarea name="notes" class="input sm:col-span-2" rows="2" placeholder="{{ __('Maelezo ya hatua hii (k.m. tarehe aliyojulishwa, majibu yake)') }}"></textarea>
                            @if ($next === 'decided')
                                <select name="outcome" class="input" x-model="outcome" required><option value="">{{ __('Matokeo') }}</option><option value="no_action">{{ __('Hakuna hatua (amelipa / sababu za msingi)') }}</option><option value="payment_plan">{{ __('Mpango wa malipo') }}</option><option value="status_change">{{ __('Kubadilisha hali ya uanachama') }}</option></select>
                                <select name="outcome_status" class="input" x-show="outcome === 'status_change'"><option value="suspended">{{ \App\Support\Labels::status('suspended') }}</option><option value="resigned">{{ \App\Support\Labels::status('resigned') }}</option><option value="archived">{{ \App\Support\Labels::status('archived') }}</option></select>
                                <textarea name="decision_text" class="input sm:col-span-2" rows="2" required placeholder="{{ __('Uamuzi kama ulivyofanywa na uongozi/kikao') }}"></textarea>
                                <select name="decision_id" class="input sm:col-span-2"><option value="">{{ __('Unganisha na uamuzi wa kikao (inashauriwa)') }}</option>@foreach ($decisions as $dc)<option value="{{ $dc->id }}">{{ $dc->title }}</option>@endforeach</select>
                            @endif
                            <div><button class="btn-primary btn-sm">{{ __('Endelea: :s', ['s' => \App\Support\Labels::status($next)]) }}</button></div>
                        </form>
                    @endif
                </article>
            @empty <x-empty icon="shield" :title="__('Hakuna kesi inayoendelea')" /> @endforelse
        </div>
        <x-card :title="__('Wanaokaribia kiwango')">
            <p class="mb-3 text-sm text-muted">{{ __('Wanachama wenye miezi :n au zaidi iliyopitwa. Kuanzisha kesi ni hatua ya kwanza tu — onyo la upole.', ['n' => max(1, $threshold - 1)]) }}</p>
            @forelse ($candidates as $r)
                <form method="POST" action="{{ route('governance.compliance.open') }}" class="flex items-center justify-between gap-2 border-b border-line py-2.5 last:border-0">@csrf
                    <input type="hidden" name="member_id" value="{{ $r['member']->id }}">
                    <span><span class="font-bold">{{ $r['member']->full_name }}</span><span class="block text-sm text-muted">{{ __('miezi :m · :a', ['m' => $r['summary']['months_in_arrears'], 'a' => \App\Support\Money::format($r['summary']['overdue'])]) }}</span></span>
                    <button class="btn-ghost btn-sm">{{ __('Anzisha onyo') }}</button>
                </form>
            @empty <p class="text-muted">{{ __('Hakuna.') }}</p> @endforelse
        </x-card>
    </div>
</x-layouts.app>
