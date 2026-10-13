<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dev outbox</title>@vite(['resources/css/app.css'])</head>
<body class="bg-ivory p-6"><div class="mx-auto max-w-3xl">
<h1 class="font-display text-3xl text-forest-900">Dev outbox <span class="badge-clay">LOCAL ONLY</span></h1>
<p class="mt-1 text-muted">Messages the fake WhatsApp provider would have sent. This page does not exist in production.</p>
<div class="mt-6 space-y-3">
@forelse ($rows as $r)
    @php($p = json_decode($r->payload, true))
    <div class="card card-pad"><div class="flex justify-between text-sm text-muted"><span>To {{ $r->to }} · template <strong>{{ $r->template }}</strong></span><span>{{ $r->created_at }}</span></div>
    @if ($code = data_get($p, 'button.personalisation.copy_otp_code.parameters.0'))<p class="mt-2 font-display text-4xl tracking-[0.3em] text-forest-900">{{ $code }}</p>@endif
    @if (! empty($p['personalisation']))<pre class="mt-2 overflow-x-auto rounded-lg bg-cream p-3 text-xs">{{ json_encode($p['personalisation'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>@endif
    </div>
@empty <p class="text-muted">Nothing sent yet.</p> @endforelse
</div></div></body></html>
