<x-layouts.app :title="__('Utawala')" wide>
    <x-page-header :eyebrow="__('Msimamizi wa mfumo')" :title="__('Utawala')" :description="__('Afya ya mfumo, WhatsApp, usalama, nafasi na mipangilio.')">
        <x-slot:actions>
            @can('roles.manage')<a href="{{ route('admin.roles') }}" class="btn-ghost">{{ __('Nafasi na ruhusa') }}</a>@endcan
            @can('admin.settings')<a href="{{ route('admin.templates') }}" class="btn-ghost">{{ __('Violezo vya WhatsApp') }}</a>@endcan
            @can('admin.audit')<a href="{{ route('admin.audit') }}" class="btn-ghost">{{ __('Kumbukumbu za ukaguzi') }}</a>@endcan
            @can('members.import')<a href="{{ route('admin.imports') }}" class="btn-ghost">{{ __('Uingizaji wa orodha') }}</a>@endcan
        </x-slot:actions>
    </x-page-header>

    @php($ok = fn ($b) => $b ? 'badge-green' : 'badge-clay')
    <div class="grid gap-6 lg:grid-cols-3">
        <x-card :title="__('Afya ya mfumo')" icon="shield">
            <ul class="space-y-2 text-sm">
                <li class="flex justify-between"><span>{{ __('Database') }}</span><span class="{{ $ok($health['database']) }}">{{ $health['database'] ? 'OK' : __('Tatizo') }}</span></li>
                <li class="flex justify-between"><span>{{ __('Mpangilio wa ratiba (scheduler)') }}</span><span class="{{ $ok($health['scheduler_last_run'] && \Illuminate\Support\Carbon::parse($health['scheduler_last_run'])->gt(now()->subMinutes(5))) }}">{{ $health['scheduler_last_run'] ? \Illuminate\Support\Carbon::parse($health['scheduler_last_run'])->diffForHumans() : __('Haijawahi kuendeshwa') }}</span></li>
                <li class="flex justify-between"><span>{{ __('Foleni ya kazi') }}</span><span class="{{ $ok($health['queue_backlog'] < 50) }}">{{ $health['queue_backlog'] }}</span></li>
                <li class="flex justify-between"><span>{{ __('Kazi zilizoshindwa') }}</span><span class="{{ $ok($health['failed_jobs'] === 0) }}">{{ $health['failed_jobs'] }}</span></li>
                <li class="flex justify-between"><span>{{ __('Hifadhi ya faili') }}</span><span class="{{ $ok($health['storage_writable']) }}">{{ $health['storage_writable'] ? 'OK' : __('Haiandikiki') }}</span></li>
                <li class="flex justify-between"><span>{{ __('Mazingira') }}</span><span class="{{ $ok($health['env'] === 'production' && ! $health['debug']) }}">{{ $health['env'] }}{{ $health['debug'] ? ' · debug' : '' }}</span></li>
            </ul>
        </x-card>
        <x-card :title="__('WhatsApp (NextSMS)')" icon="whatsapp">
            <ul class="space-y-2 text-sm">
                <li class="flex justify-between"><span>{{ __('Mtoa huduma') }}</span><span class="{{ $ok($provider['name'] === 'nextsms') }}">{{ $provider['name'] }}</span></li>
                <li class="flex justify-between"><span>{{ __('Token na akaunti') }}</span><span class="{{ $ok($provider['configured']) }}">{{ $provider['configured'] ? __('Zimewekwa') : __('Hazijawekwa') }}</span></li>
                <li class="flex justify-between"><span>{{ __('Kiolezo cha OTP') }}</span><span class="{{ $ok(filled($provider['otp_template'])) }}">{{ $provider['otp_template'] ?: __('Hakijawekwa') }}</span></li>
                <li class="flex justify-between"><span>{{ __('Webhook token') }}</span><span class="{{ $ok($provider['webhook']) }}">{{ $provider['webhook'] ? __('Imewekwa') : __('Haijawekwa') }}</span></li>
                <li class="flex justify-between"><span>{{ __('Hali ya majaribio') }}</span><span class="badge-grey">{{ $provider['test_mode'] ? __('Ndiyo') : __('Hapana') }}</span></li>
                @if ($provider['balance'])<li class="flex justify-between"><span>{{ __('Salio la NextSMS') }}</span><span class="font-bold">{{ $provider['balance']['display'] ?? '—' }}</span></li>@endif
            </ul>
            <p class="mt-3 text-xs text-muted">{{ __('Webhook URL ya kuweka kwenye NextSMS:') }} <code class="break-all">{{ route('webhooks.nextsms') }}</code></p>
        </x-card>
        <x-card :title="__('Usalama (saa 24)')" icon="lock">
            <ul class="space-y-2 text-sm">
                <li class="flex justify-between"><span>{{ __('Nambari za OTP zilizotolewa') }}</span><span class="font-bold">{{ $security['otp_issued'] }}</span></li>
                <li class="flex justify-between"><span>{{ __('OTP zilizoshindwa kutumwa') }}</span><span class="{{ $ok($security['otp_send_failed'] === 0) }}">{{ $security['otp_send_failed'] }}</span></li>
                <li class="flex justify-between"><span>{{ __('Nambari zisizo sahihi') }}</span><span class="{{ $ok($security['otp_failures'] < 10) }}">{{ $security['otp_failures'] }}</span></li>
                <li class="flex justify-between"><span>{{ __('Mialiko kwa namba isiyo sahihi') }}</span><span class="{{ $ok($security['invite_mismatch'] === 0) }}">{{ $security['invite_mismatch'] }}</span></li>
                <li class="flex justify-between"><span>{{ __('Webhook zilizokataliwa') }}</span><span class="{{ $ok($security['rejected_webhooks'] === 0) }}">{{ $security['rejected_webhooks'] }}</span></li>
            </ul>
        </x-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card :title="__('Ujumbe — siku 7')" icon="chart">
            <div class="flex flex-wrap gap-2">@forelse ($deliveries as $st => $c)<span class="{{ \App\Support\Labels::badge($st) }}">{{ \App\Support\Labels::status($st) }}: {{ $c }}</span>@empty<p class="text-muted">{{ __('Hakuna ujumbe.') }}</p>@endforelse</div>
            @foreach ($failedJobs as $j)<a href="{{ route('notifications.show', $j) }}" class="mt-2 block text-sm font-bold text-clay-700 hover:underline">{{ $j->title }}</a>@endforeach
        </x-card>
        <x-card :title="__('Uongozi na mialiko')" icon="users">
            <p class="text-sm text-muted">{{ __('Wanafamilia :n bado hawajathibitisha namba zao.', ['n' => $unverified]) }}</p>
            <ul class="mt-3 space-y-1 text-sm">@foreach ($users as $u)<li class="flex justify-between"><a href="{{ route('members.show', $u->member) }}" class="font-bold hover:underline">{{ $u->displayName() }}</a><span class="text-muted">{{ $u->roles->where('key', '!=', 'member')->map->label()->join(', ') }}</span></li>@endforeach</ul>
            @if ($pendingInvites->isNotEmpty())<p class="mt-4 text-sm font-bold">{{ __('Mialiko inayosubiri') }}</p><ul class="text-sm text-muted">@foreach ($pendingInvites as $i)<li>{{ $i->member->full_name }} — {{ __('inaisha :d', ['d' => $i->expires_at->format('d/m/Y')]) }}</li>@endforeach</ul>@endif
        </x-card>
    </div>

    @can('admin.settings')
        <x-card :title="__('Mipangilio ya familia')" class="mt-6">
            <form method="POST" action="{{ route('admin.settings') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">@csrf @method('PUT')
                <x-field name="family_name" :label="__('Jina la familia/kikundi')" :value="$settings['family.name']" required />
                <x-field name="family_motto" :label="__('Kauli mbiu (iachwe wazi hadi familia iidhinishe)')" :value="$settings['family.motto']" />
                <x-field name="family_welcome" type="textarea" rows="2" :label="__('Ujumbe wa kukaribisha')" :value="$settings['family.welcome']" class="md:col-span-2" />
                <x-field name="payments_instructions" type="textarea" rows="3" :label="__('Maelekezo ya kulipa (akaunti ya pamoja, namba za simu)')" :value="$settings['payments.instructions']" class="md:col-span-2" :hint="__('Yanaonekana kwa wanachama walioingia tu, kwenye ukurasa wa kutuma malipo.')" />
                <x-field name="quiet_start" type="time" :label="__('Saa za utulivu zinaanza')" :value="$settings['notifications.quiet_hours']['start'] ?? ''" />
                <x-field name="quiet_end" type="time" :label="__('Saa za utulivu zinaisha')" :value="$settings['notifications.quiet_hours']['end'] ?? ''" />
                <x-field name="emblem" type="file" accept="image/png,image/jpeg,image/webp" :label="__('Nembo ya familia (ikishaidhinishwa)')" />
                <label class="flex items-center gap-2 self-end text-sm"><input type="checkbox" name="remove_emblem" value="1" class="size-5 rounded border-sand"> {{ __('Rudisha nembo ya muda') }}</label>
                <div class="md:col-span-2"><button class="btn-primary">{{ __('Hifadhi mipangilio') }}</button></div>
            </form>
        </x-card>
    @endcan

    @if ($recentAudit->isNotEmpty())
        <x-card :title="__('Matukio ya karibuni ya ukaguzi')" :href="route('admin.audit')" class="mt-6">
            <ul class="divide-y divide-line text-sm">@foreach ($recentAudit as $a)<li class="flex flex-wrap justify-between gap-2 py-2"><span><code class="text-xs text-muted">{{ $a->action }}</code> {{ $a->description }}</span><span class="text-muted">{{ $a->actor?->displayName() ?? __('Mfumo') }} · {{ $a->created_at->diffForHumans() }}</span></li>@endforeach</ul>
        </x-card>
    @endif
</x-layouts.app>
