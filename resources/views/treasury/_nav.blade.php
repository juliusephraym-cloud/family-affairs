@php($u = auth()->user())
<nav class="-mx-1 mb-8 flex gap-1 overflow-x-auto pb-1" aria-label="{{ __('Hazina') }}">
    @foreach ([
        ['treasury.index', __('Muhtasari'), true],
        ['treasury.submissions', __('Malipo ya kuthibitisha'), $u->hasPermission('finance.verify')],
        ['treasury.members', __('Wanachama'), $u->hasPermission('finance.view_all')],
        ['treasury.expenses', __('Matumizi'), $u->hasAnyPermission('finance.view_all', 'finance.request_expense', 'finance.approve_expense')],
        ['treasury.ledger', __('Ledger'), $u->hasPermission('finance.view_all')],
        ['treasury.accounts', __('Akaunti na mifuko'), $u->hasPermission('finance.manage')],
        ['treasury.rules', __('Kanuni za michango'), $u->hasPermission('finance.view_all')],
        ['treasury.penalties', __('Faini'), $u->hasPermission('finance.manage')],
        ['treasury.reports', __('Ripoti'), $u->hasPermission('finance.view_summary')],
    ] as [$r, $l, $show])
        @if ($show)<a href="{{ route($r) }}" @class(['tab shrink-0', 'tab-active' => request()->routeIs($r)])>{{ $l }}</a>@endif
    @endforeach
</nav>
