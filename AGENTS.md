# Family Affairs — notes for coding agents

Laravel 13 (PHP 8.3 platform pin — the VPS runs 8.3), Blade + Tailwind v4 + Alpine, MySQL/MariaDB. Swahili is the source language in views (`__('Swahili text')`); English lives in `lang/en.json`. Domain messages use `lang/{sw,en}/{auth,finance,welfare,secreto,notifications}.php`. Never wrap a single word that equals a lang group file name (e.g. `__('Secreto')`) — macOS resolves it case-insensitively to the group array.

Rules of the codebase:
- Business rules live in `app/Services/*`; controllers stay thin. Ledger writes only through `LedgerService::post()/reverse()`.
- Family rule values come from `policy_rules` and are only used when `status = confirmed` (`PolicyRule::confirmedValue`). Never hard-code amounts.
- Authorisation by permission key (`can:finance.verify`, `$user->hasPermission()`), defined in `app/Support/Permissions.php`.
- No inline `<script>` or `on*=` handlers (production CSP). Alpine components go in `resources/js/app.js`.
- Demo data only via `DemoSeeder` (fictional names, +255 700 range). Real family data never goes in the repo.
- Tests: `php artisan test` (MySQL `family_affairs_test`).
