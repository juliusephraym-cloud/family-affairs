<x-layouts.app :title="__('Matumizi')" wide>
    <x-page-header :eyebrow="__('Hazina')" :title="__('Matumizi')" :description="__('Ombi → idhini ya mtu mwingine → malipo. Anayeomba hawezi kuidhinisha ombi lake.')" />
    @include('treasury._nav')
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <div class="flex flex-wrap gap-1">@foreach (['' => __('Yote'), 'submitted' => __('Yanasubiri idhini'), 'approved' => __('Ya kulipa'), 'paid' => __('Yamelipwa'), 'rejected' => __('Yamekataliwa')] as $k => $l)<a href="{{ route('treasury.expenses', ['status' => $k ?: null]) }}" @class(['tab', 'tab-active' => request('status', '') === $k])>{{ $l }}</a>@endforeach</div>
            @forelse ($expenses as $e)
                <article class="card card-pad">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0"><p class="font-display text-lg font-semibold text-forest-900">{{ $e->title }}</p>
                            <p class="text-sm text-muted">{{ $e->category }} · {{ $e->fund->label() }} · {{ $e->expense_date->format('d/m/Y') }} @if($e->event)· {{ $e->event->title }}@endif</p></div>
                        <div class="text-right"><p class="font-display text-xl font-semibold"><x-money :amount="$e->amount" /></p><x-status :value="$e->status" /></div>
                    </div>
                    <p class="mt-2 text-sm">{{ $e->purpose }}</p>
                    <p class="mt-2 text-xs text-muted">{{ __('Ameomba: :n', ['n' => $e->requester?->displayName()]) }} @if($e->approver)· {{ __('Ameamua: :n', ['n' => $e->approver->displayName()]) }}@endif @if($e->payment_reference)· {{ __('Kumbukumbu: :r', ['r' => $e->payment_reference]) }}@endif</p>
                    @if ($e->rejection_reason)<p class="mt-1 text-sm text-clay-700">{{ $e->rejection_reason }}</p>@endif
                    @if ($e->document_path)<a href="{{ route('treasury.expenses.document', $e) }}" target="_blank" class="mt-2 inline-flex items-center gap-1 text-sm font-bold text-forest-700 hover:underline"><x-icon name="file" class="size-4" />{{ __('Hati') }}</a>@endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($e->status === 'submitted' && auth()->user()->hasPermission('finance.approve_expense') && $e->requested_by !== auth()->id())
                            <form method="POST" action="{{ route('treasury.expenses.decide', $e) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn-primary btn-sm">{{ __('Idhinisha') }}</button></form>
                            <x-confirm-button :action="route('treasury.expenses.decide', $e)" :label="__('Kataa')" variant="btn-danger btn-sm" :reason="true"><input type="hidden" name="decision" value="reject"></x-confirm-button>
                        @endif
                        @if ($e->status === 'approved' && auth()->user()->hasPermission('finance.pay_expense'))
                            <form method="POST" action="{{ route('treasury.expenses.pay', $e) }}" class="flex flex-wrap items-end gap-2">@csrf
                                <select name="account" class="input w-auto" aria-label="{{ __('Imelipwa kutoka') }}">@foreach ($accounts as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
                                <input type="date" name="paid_on" value="{{ now()->toDateString() }}" class="input w-auto" aria-label="{{ __('Tarehe') }}">
                                <input name="reference" class="input w-40" placeholder="{{ __('Kumbukumbu') }}">
                                <button class="btn-primary btn-sm">{{ __('Rekodi malipo') }}</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty <x-empty icon="file" :title="__('Hakuna matumizi')" /> @endforelse
            {{ $expenses->links() }}
        </div>
        @can('finance.request_expense')
            <x-card :title="__('Omba matumizi')">
                <form method="POST" action="{{ route('treasury.expenses.store') }}" enctype="multipart/form-data" class="space-y-4">@csrf
                    <x-field name="title" :label="__('Kichwa')" required />
                    <x-field name="category" type="select" :label="__('Aina')" :options="['event' => __('Tukio'), 'welfare' => __('Jamii'), 'bereavement' => __('Msiba'), 'charity' => __('Sadaka/hisani'), 'gift' => __('Zawadi/pongezi'), 'admin' => __('Uendeshaji'), 'refund' => __('Marejesho kwa mwanachama'), 'other' => __('Nyingine')]" />
                    <x-field name="amount" :label="__('Kiasi')" inputmode="numeric" required />
                    <x-field name="expense_date" type="date" :label="__('Tarehe')" :value="now()->toDateString()" />
                    <x-field name="fund_id" type="select" :label="__('Mfuko')" :options="$funds->mapWithKeys(fn ($f) => [$f->id => $f->label()])->all()" />
                    <x-field name="event_id" type="select" :label="__('Tukio (kama lipo)')" :options="['' => '—'] + $events->pluck('title', 'id')->all()" />
                    <x-field name="charity_activity_id" type="select" :label="__('Shughuli ya hisani (kama ipo)')" :options="['' => '—'] + $activities->pluck('title', 'id')->all()" />
                    <x-field name="purpose" type="textarea" rows="3" :label="__('Lengo')" required />
                    <x-field name="document" type="file" accept="image/*,application/pdf" :label="__('Hati/ankara (si lazima)')" />
                    <button class="btn-primary w-full">{{ __('Wasilisha ombi') }}</button>
                </form>
            </x-card>
        @endcan
    </div>
</x-layouts.app>
