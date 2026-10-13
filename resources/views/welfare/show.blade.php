<x-layouts.app :title="$case->title">
    <x-page-header :eyebrow="__('Kesi ya jamii')" :title="$case->title" :description="$case->created_at->translatedFormat('j F Y')">
        <x-slot:actions><x-status :value="$case->status" /></x-slot:actions>
    </x-page-header>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <p class="whitespace-pre-line text-lg">{{ $case->summary }}</p>
                @if ($canPrivate && $case->private_details)
                    <div class="mt-4 rounded-xl border border-gold-200 bg-gold-50 p-4"><p class="mb-1 flex items-center gap-1.5 text-sm font-bold text-gold-700"><x-icon name="lock" class="size-4" />{{ __('Maelezo ya siri') }}</p><p class="whitespace-pre-line text-sm">{{ $case->private_details }}</p></div>
                @endif
                @if ($case->closing_summary)<p class="mt-4 text-sm"><strong>{{ __('Muhtasari wa kufunga:') }}</strong> {{ $case->closing_summary }}</p>@endif
            </x-card>

            @if ($case->per_member_amount > 0)
                <x-card :title="__('Mchango wa kila mwanachama')">
                    <p class="font-display text-3xl font-semibold"><x-money :amount="$case->per_member_amount" /></p>
                    @if ($case->contribution_deadline)<p class="text-muted">{{ __('Mwisho: :d', ['d' => $case->contribution_deadline->translatedFormat('l, j F Y')]) }}</p>@endif
                    @if ($myObligation)
                        <p class="mt-3">{{ __('Hali yako:') }} <x-status :value="$myObligation->status" /></p>
                        @if ($myObligation->status !== 'paid')<a href="{{ route('finance.pay') }}" class="btn-primary btn-sm mt-3">{{ __('Tuma taarifa ya malipo') }}</a>@endif
                    @endif
                    @if ($collection)
                        <div class="mt-5 border-t border-line pt-4">
                            <div class="flex justify-between text-sm"><span>{{ __('Imekusanywa') }}</span><span class="font-bold"><x-money :amount="$collection['collected']" /> / <x-money :amount="$collection['expected']" /></span></div>
                            <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-cream"><div class="h-full bg-forest-600" style="width: {{ $collection['expected'] ? min(100, $collection['collected'] / $collection['expected'] * 100) : 0 }}%"></div></div>
                            <p class="mt-1 text-sm text-muted">{{ __(':a kati ya :b wamelipa kikamilifu', ['a' => $collection['members_paid'], 'b' => $collection['members_total']]) }}</p>
                            <details class="mt-3 text-sm"><summary class="cursor-pointer font-bold text-forest-700">{{ __('Orodha ya wachangiaji') }}</summary>
                                <ul class="mt-2 divide-y divide-line">@foreach ($contributors as $o)<li class="flex justify-between py-1.5"><span>{{ $o->member->full_name }}</span><x-status :value="$o->status" /></li>@endforeach</ul></details>
                        </div>
                    @endif
                </x-card>
            @endif

            @if ($case->disbursements->isNotEmpty())
                <x-card :title="__('Msaada uliotolewa')">
                    @foreach ($case->disbursements as $d)
                        <div class="flex justify-between border-b border-line py-2 last:border-0"><span>{{ $d->paid_on->format('d/m/Y') }} · {{ $d->recipient_name }} <span class="text-sm text-muted">{{ $d->reference }}</span></span><x-money :amount="$d->amount" class="font-bold" /></div>
                    @endforeach
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($case->status === 'submitted')
                @can('welfare.approve')
                    <x-card :title="__('Idhinisha kesi')">
                        @if ($case->type === 'bereavement')
                            @if ($proposal['missing'])
                                <div class="rounded-xl border border-clay-200 bg-clay-50 p-3 text-sm text-clay-700">{{ $proposal['reason'] }}
                                    <ul class="mt-1 list-disc pl-5 font-mono text-xs">@foreach ($proposal['missing'] as $k)<li>{{ $k }}</li>@endforeach</ul>
                                    <a href="{{ route('governance.index') }}" class="mt-2 inline-block font-bold underline">{{ __('Nenda Katiba na Kanuni') }}</a></div>
                            @else
                                <dl class="space-y-1 text-sm">
                                    <div class="flex justify-between"><dt>{{ __('Kutoka mfuko wa jamii') }}</dt><dd class="font-bold"><x-money :amount="$proposal['fund_payout']" /></dd></div>
                                    <div class="flex justify-between"><dt>{{ __('Kila mwanachama') }}</dt><dd class="font-bold"><x-money :amount="$proposal['per_member']" /></dd></div>
                                    <div class="flex justify-between"><dt>{{ __('Muda') }}</dt><dd class="font-bold">{{ __('siku :d', ['d' => $proposal['days']]) }}</dd></div>
                                </dl>
                                @unless ($proposal['eligible'])<p class="mt-2 text-sm font-bold text-clay-700">{{ __('welfare.not_eligible') }}</p>@endunless
                                <p class="mt-2 text-xs text-muted">{{ __('Kiasi kimetokana na kanuni zilizothibitishwa. Mwanafamilia aliyefiwa hatadaiwa mchango huu.') }}</p>
                            @endif
                        @endif
                        @if ($case->type !== 'bereavement' || (! $proposal['missing'] && $proposal['eligible']))
                            <form method="POST" action="{{ route('welfare.approve', $case) }}" class="mt-4 space-y-3">@csrf
                                @if ($case->type !== 'bereavement')
                                    <x-field name="fund_payout" :label="__('Kiasi kutoka mfuko (kumbukumbu)')" inputmode="numeric" />
                                    <x-field name="per_member" :label="__('Mchango wa kila mwanachama (0 = hakuna)')" inputmode="numeric" />
                                    <x-field name="deadline" type="date" :label="__('Mwisho wa michango')" />
                                @endif
                                @if ($case->visibility === 'members')<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="announce" value="1" class="size-5 rounded border-sand" @disabled(! $noticeTemplate?->isSendable())> {{ __('Tangaza kwa WhatsApp') }} @unless($noticeTemplate?->isSendable())<span class="text-xs text-muted">({{ __('kiolezo hakijaidhinishwa') }})</span>@endunless</label>@endif
                                <button class="btn-primary w-full" @disabled($case->submitted_by === auth()->id())>{{ __('Idhinisha') }}</button>
                                @if ($case->submitted_by === auth()->id())<p class="text-xs text-clay-700">{{ __('welfare.no_self_approve') }}</p>@endif
                            </form>
                        @endif
                        <div class="mt-3"><x-confirm-button :action="route('welfare.reject', $case)" :label="__('Kataa')" variant="btn-danger btn-sm" :reason="true" /></div>
                    </x-card>
                @endcan
            @endif
            @if (in_array($case->status, ['approved', 'collecting', 'disbursed']))
                @can('welfare.disburse')
                    <x-card :title="__('Rekodi msaada uliotolewa')">
                        <form method="POST" action="{{ route('welfare.disburse', $case) }}" class="space-y-3">@csrf
                            <x-field name="amount" :label="__('Kiasi')" inputmode="numeric" :value="$case->fund_payout_amount ?: null" required />
                            <x-field name="recipient_name" :label="__('Aliyepokea')" :value="$case->beneficiary?->full_name" required />
                            <x-field name="paid_on" type="date" :label="__('Tarehe')" :value="now()->toDateString()" required />
                            <x-field name="account" type="select" :label="__('Kutoka akaunti')" :options="$accounts->pluck('name', 'id')->all()" />
                            <x-field name="reference" :label="__('Kumbukumbu ya muamala')" />
                            <button class="btn-primary w-full">{{ __('Rekodi') }}</button>
                        </form>
                    </x-card>
                @endcan
                @can('welfare.approve')
                    <x-card :title="__('Funga kesi')"><x-confirm-button :action="route('welfare.close', $case)" :label="__('Funga kwa muhtasari')" variant="btn-ghost w-full" :reason="true" :reasonLabel="__('Muhtasari wa kufunga (kilichotolewa, kilichokusanywa)')" /></x-card>
                @endcan
            @endif
            <x-card :title="__('Taarifa')">
                <dl class="space-y-1 text-sm">
                    @if ($case->beneficiary)<div><dt class="text-muted">{{ __('Mwanafamilia') }}</dt><dd class="font-bold">{{ $case->beneficiary->displayName() }}</dd></div>@endif
                    <div><dt class="text-muted">{{ __('Ameripoti') }}</dt><dd class="font-bold">{{ $case->submitter?->displayName() }}</dd></div>
                    <div><dt class="text-muted">{{ __('Wanaoona') }}</dt><dd class="font-bold">{{ ['members' => __('Wanafamilia wote'), 'leaders' => __('Uongozi'), 'private' => __('Wahusika tu')][$case->visibility] }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts.app>
