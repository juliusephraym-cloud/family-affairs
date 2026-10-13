@props(['title' => null])
@php($familyName = \App\Support\Settings::get('family.name'))
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#143222">
    <title>{{ $title ? $title.' · ' : '' }}{{ $familyName }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-forest-950 text-ivory">
    <div class="relative grid min-h-dvh lg:grid-cols-[1.05fr_1fr]">
        {{-- Story side --}}
        <section class="relative hidden overflow-hidden lg:block" aria-hidden="true">
            <div class="absolute inset-0" style="background:
                radial-gradient(60% 50% at 20% 15%, rgb(201 154 46 / .28), transparent 70%),
                radial-gradient(50% 60% at 85% 90%, rgb(192 103 77 / .22), transparent 70%),
                linear-gradient(160deg, #1b4430 0%, #0d2217 70%);"></div>
            <svg class="absolute -right-24 bottom-0 h-[78%] text-gold-400/15" viewBox="0 0 400 520" fill="none" stroke="currentColor" stroke-width="1.2">
                {{-- A quiet family-tree linework: roots, trunk and branching households --}}
                <path d="M200 520V300M200 300c0-60-90-70-90-140M200 300c0-60 90-70 90-140M110 160c0-40-50-50-50-95M110 160c0-40 40-55 40-95M290 160c0-40-40-55-40-95M290 160c0-40 50-50 50-95M200 300c0-90 0-130 0-200"/>
                @foreach ([[60,65],[150,65],[250,65],[340,65],[200,100],[110,160],[290,160],[200,300]] as [$x,$y])
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $y === 300 ? 16 : 11 }}" />
                @endforeach
                <path d="M120 520c20-40 50-60 80-60s60 20 80 60"/>
            </svg>
            <div class="relative flex h-full flex-col justify-between p-12 xl:p-16">
                <div class="flex items-center gap-3"><x-emblem size="size-12" /><span class="font-display text-2xl text-white">{{ $familyName }}</span></div>
                <div class="max-w-lg">
                    <p class="eyebrow mb-4 text-gold-400">{{ __('Nyumbani kwa familia') }}</p>
                    <p class="font-display text-5xl leading-[1.08] font-semibold text-white xl:text-6xl">{{ __('Pamoja tunakumbuka,') }}<br><span class="text-gold-200 italic">{{ __('pamoja tunasaidiana.') }}</span></p>
                    <p class="mt-6 max-w-md text-lg text-forest-100/80">{{ \App\Support\Settings::get('family.welcome') }}</p>
                    @if ($motto = \App\Support\Settings::get('family.motto'))<p class="mt-4 font-display text-xl text-gold-200 italic">“{{ $motto }}”</p>@endif
                </div>
                <div class="kanga-band w-48 rounded-full"></div>
            </div>
        </section>

        {{-- Form side --}}
        <section class="flex flex-col bg-ivory text-ink">
            <div class="flex items-center justify-between px-6 pt-6 lg:hidden">
                <div class="flex items-center gap-2.5"><x-emblem size="size-10" /><span class="font-display text-xl text-forest-900">{{ $familyName }}</span></div>
            </div>
            <div class="flex flex-1 items-center justify-center px-6 py-10">
                <div class="w-full max-w-md animate-rise">
                    <x-flash />
                    {{ $slot }}
                </div>
            </div>
            <div class="flex items-center justify-between px-6 pb-6 text-sm text-muted">
                <span>{{ __('Mfumo binafsi wa familia. Hakuna taarifa za familia zinazoonekana kabla ya kuingia.') }}</span>
                <form method="POST" action="{{ route('locale') }}">@csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'sw' ? 'en' : 'sw' }}">
                    <button class="font-bold text-forest-800 hover:underline">{{ app()->getLocale() === 'sw' ? 'English' : 'Kiswahili' }}</button>
                </form>
            </div>
        </section>
    </div>
</body>
</html>
