@props(['title' => null, 'wide' => false])
@php
    $u = auth()->user();
    $nav = [
        ['dashboard', 'home', __('Nyumbani'), true],
        ['members.index', 'users', __('Familia'), $u->hasPermission('members.view')],
        ['finance.mine', 'wallet', __('Michango Yangu'), $u->hasPermission('finance.view_own')],
        ['events.index', 'calendar', __('Matukio'), true],
        ['meetings.index', 'gavel', __('Vikao'), true],
        ['welfare.index', 'heart', __('Jamii na Sadaka'), true],
        ['secreto.index', 'gift', 'Secreto', true],
        ['memories.index', 'image', __('Kumbukumbu'), true],
        ['community.index', 'megaphone', __('Matangazo'), true],
    ];
    $lead = [
        ['treasury.index', 'vault', __('Hazina'), $u->hasAnyPermission('finance.view_all', 'finance.verify', 'finance.view_summary')],
        ['governance.index', 'book', __('Katiba na Kanuni'), true],
        ['notifications.index', 'whatsapp', __('Ujumbe wa WhatsApp'), $u->hasAnyPermission('notifications.manage', 'notifications.view_logs')],
        ['admin.index', 'settings', __('Utawala'), $u->hasAnyPermission('admin.settings', 'roles.manage', 'admin.audit', 'members.import')],
    ];
    $isActive = fn (string $route) => request()->routeIs(\Illuminate\Support\Str::before($route, '.').'.*') || request()->routeIs($route);
    $familyName = \App\Support\Settings::get('family.name');
    $mobile = [
        ['dashboard', 'home', __('Nyumbani')],
        ['finance.mine', 'wallet', __('Michango')],
        ['events.index', 'calendar', __('Matukio')],
        ['community.index', 'megaphone', __('Habari')],
    ];
@endphp
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
<body class="paper min-h-dvh" x-data="{ menu: false }" @keydown.escape="menu = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-paper focus:px-4 focus:py-2">{{ __('Ruka hadi maudhui') }}</a>

    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-72 flex-col bg-forest-900 text-forest-100 lg:flex">
        <div class="flex items-center gap-3 px-6 pt-7 pb-6">
            <x-emblem />
            <div class="min-w-0">
                <p class="truncate font-display text-xl font-semibold text-white">{{ $familyName }}</p>
                <p class="text-xs tracking-wide text-forest-200/80">{{ __('Nyumbani kwa familia') }}</p>
            </div>
        </div>
        <div class="kanga-band mx-6 rounded-full opacity-60"></div>
        <nav class="flex-1 space-y-1 overflow-y-auto px-4 py-5" aria-label="{{ __('Menyu kuu') }}">
            @foreach ($nav as [$route, $icon, $label, $show])
                @if ($show)
                    <a href="{{ route($route) }}" class="nav-link" @if($isActive($route)) aria-current="page" @endif>
                        <x-icon :name="$icon" /> {{ $label }}
                    </a>
                @endif
            @endforeach
            <p class="px-3 pt-6 pb-2 text-[0.7rem] font-bold tracking-[0.16em] text-gold-400/80 uppercase">{{ __('Uongozi na Kumbukumbu') }}</p>
            @foreach ($lead as [$route, $icon, $label, $show])
                @if ($show)
                    <a href="{{ route($route) }}" class="nav-link" @if($isActive($route)) aria-current="page" @endif>
                        <x-icon :name="$icon" /> {{ $label }}
                    </a>
                @endif
            @endforeach
        </nav>
        <div class="border-t border-white/10 p-4">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-xl p-2 hover:bg-white/5">
                <x-avatar :member="$u->member" size="size-10" />
                <span class="min-w-0">
                    <span class="block truncate font-bold text-white">{{ $u->displayName() }}</span>
                    <span class="block truncate text-xs text-forest-200/80">{{ $u->activeRoles()->sortBy(fn ($r) => $r->key === 'super_admin' ? 999 : $r->sort)->first()?->label() }}</span>
                </span>
            </a>
        </div>
    </aside>

    {{-- Mobile top bar --}}
    <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-line/80 bg-ivory/90 px-4 py-3 backdrop-blur lg:hidden">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5">
            <x-emblem size="size-9" />
            <span class="truncate font-display text-lg font-semibold text-forest-900">{{ $familyName }}</span>
        </a>
        <a href="{{ route('profile.edit') }}" aria-label="{{ __('Wasifu wangu') }}"><x-avatar :member="$u->member" size="size-9" /></a>
    </header>

    <div class="lg:pl-72">
        <x-demo-banner />
        <main id="main" @class(['mx-auto px-4 pt-6 pb-28 sm:px-6 lg:px-10 lg:pt-10 lg:pb-16', 'max-w-7xl' => $wide, 'max-w-6xl' => ! $wide])>
            <x-flash />
            {{ $slot }}
        </main>
        <footer class="mx-auto hidden max-w-6xl items-center justify-between px-10 pb-10 text-sm text-muted lg:flex">
            <span>{{ $familyName }} · {{ __('Taarifa za familia ni siri — usizisambaze nje.') }}</span>
            <div class="flex items-center gap-4">
                <form method="POST" action="{{ route('locale') }}" class="inline">@csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'sw' ? 'en' : 'sw' }}">
                    <button class="underline-offset-4 hover:underline">{{ app()->getLocale() === 'sw' ? 'English' : 'Kiswahili' }}</button>
                </form>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="font-bold text-forest-800 hover:underline">{{ __('Toka') }}</button></form>
            </div>
        </footer>
    </div>

    {{-- Mobile bottom navigation --}}
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden" aria-label="{{ __('Menyu ya simu') }}">
        <div class="grid grid-cols-5">
            @foreach ($mobile as [$route, $icon, $label])
                <a href="{{ route($route) }}" @class(['flex flex-col items-center gap-0.5 py-2.5 text-[0.72rem] font-bold', 'text-forest-800' => $isActive($route), 'text-muted' => ! $isActive($route)]) @if($isActive($route)) aria-current="page" @endif>
                    <x-icon :name="$icon" class="size-6" />{{ $label }}
                </a>
            @endforeach
            <button type="button" @click="menu = true" class="flex flex-col items-center gap-0.5 py-2.5 text-[0.72rem] font-bold text-muted" aria-haspopup="dialog">
                <x-icon name="menu" class="size-6" />{{ __('Zaidi') }}
            </button>
        </div>
    </nav>

    {{-- Mobile "more" sheet --}}
    <div x-cloak x-show="menu" class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('Menyu') }}">
        <div class="absolute inset-0 bg-forest-950/50" @click="menu = false" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 max-h-[85dvh] overflow-y-auto rounded-t-3xl bg-forest-900 p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] text-forest-100"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
            <div class="mb-4 flex items-center justify-between">
                <p class="font-display text-xl text-white">{{ __('Menyu') }}</p>
                <button @click="menu = false" class="rounded-full p-2 hover:bg-white/10" aria-label="{{ __('Funga') }}"><x-icon name="x" /></button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                @foreach (array_merge($nav, $lead) as [$route, $icon, $label, $show])
                    @if ($show)
                        <a href="{{ route($route) }}" class="flex items-center gap-2.5 rounded-2xl bg-white/5 px-3 py-3 font-bold hover:bg-white/10"><x-icon :name="$icon" class="size-5 text-gold-400" /> {{ $label }}</a>
                    @endif
                @endforeach
            </div>
            <div class="mt-5 flex items-center justify-between border-t border-white/10 pt-4">
                <form method="POST" action="{{ route('locale') }}">@csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'sw' ? 'en' : 'sw' }}">
                    <button class="font-bold">{{ app()->getLocale() === 'sw' ? 'English' : 'Kiswahili' }}</button>
                </form>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-gold btn-sm"><x-icon name="logout" class="size-4" />{{ __('Toka') }}</button></form>
            </div>
        </div>
    </div>
</body>
</html>
