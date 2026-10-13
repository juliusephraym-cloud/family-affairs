<x-layouts.app :title="__('Rekodi malipo')">
    <x-page-header :eyebrow="__('Hazina')" :title="__('Rekodi malipo yaliyopokelewa')" :description="__('Kwa malipo uliyopokea moja kwa moja (taslimu, au uliyoyaona kwenye taarifa ya benki). Huwezi kurekodi malipo yako mwenyewe.')" />
    <form method="POST" action="{{ route('treasury.payments.store') }}" class="card card-pad max-w-3xl space-y-5">
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="member_id" type="select" :label="__('Mwanachama')" :options="$members->pluck('full_name', 'id')->all()" :value="request('member')" required class="sm:col-span-2" />
            <x-field name="amount" :label="__('Kiasi (TSh)')" inputmode="numeric" required />
            <x-field name="received_on" type="date" :label="__('Tarehe ya kupokea')" :value="now()->toDateString()" required />
            <x-field name="method" type="select" :label="__('Njia')" :options="['bank_transfer' => __('Benki'), 'mobile_money' => __('Pesa ya simu'), 'cash' => __('Taslimu'), 'other' => __('Nyingine')]" />
            <x-field name="deposit_account_id" type="select" :label="__('Akaunti iliyopokea')" :options="$accounts->pluck('name', 'id')->all()" />
            <x-field name="transaction_reference" :label="__('Kumbukumbu ya muamala')" />
            <x-field name="purpose" type="select" :label="__('Lengo')" :options="['contributions' => __('Michango / madeni / faini'), 'sadaka' => __('Sadaka'), 'welfare' => __('Jamii (hiari)'), 'event' => __('Tukio'), 'donation' => __('Mchango mwingine')]" />
        </div>
        <x-field name="notes" type="textarea" rows="2" :label="__('Maelezo')" />
        <button class="btn-primary">{{ __('Rekodi na uweke kwenye ledger') }}</button>
    </form>
</x-layouts.app>
