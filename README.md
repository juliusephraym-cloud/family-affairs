# Family Affairs

A private, Swahili-first digital home for the Family Affairs association: member directory, transparent treasury (double-entry ledger), contributions and payment verification, meetings and minutes, constitution and rules, events and committees, welfare/bereavement and charity, Secreto, family memories, announcements and polls, and WhatsApp OTP sign-in and reminders through NextSMS.

> Demo data uses **fictional names** and the unallocated +255 700 number range. No real family names, phone numbers, account numbers or balances are in this repository.

## Quick start (local)

Requirements: PHP 8.3+, Composer, Node 20+, MySQL 8 / MariaDB 10.11 (DBngin works).

```bash
composer install
npm ci && npm run build
cp .env.example .env && php artisan key:generate
mysql -uroot -e "CREATE DATABASE family_affairs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE family_affairs_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate:fresh --seed        # structure + DEMO data (local only)
php artisan serve --port=8040
```

Sign in at <http://localhost:8040> with a demo number. The code appears at <http://localhost:8040/dev/outbox> (the fake provider only exists in `local`/`testing`):

| Number | Who (fictional) | Roles |
|---|---|---|
| 0700 000 001 | Baraka Mwakyusa | Chairperson + Super Admin |
| 0700 000 002 | Neema Lyimo | Vice Chairperson |
| 0700 000 003 | Rehema Massawe | Secretary |
| 0700 000 005 | Upendo Temba | Treasurer |
| 0700 000 006 | Grace Minja | Assistant Treasurer |
| 0700 000 009 | Peter Swai | General member (in arrears) |

Run the scheduler and queue locally when testing reminders:

```bash
php artisan schedule:work
php artisan queue:work
```

## Tests

```bash
php artisan test
```

50 tests / 458 assertions covering OTP security (hashing, lockout, replay, expiry, enumeration, provider failure, invitation phone binding, phone change), permissions and page rendering for four roles, contribution allocation and credit, duplicate references, self-verification and self-approval refusal, reversals and ledger balance per fund, expenses and funds sufficiency, penalties requiring confirmed rules, welfare levies and privacy, Secreto derangement and secrecy, minutes approval and freezing, rule confirmation history, the compliance process, notification idempotency, webhooks, retries, quiet hours, opt-outs, approvals, and the NextSMS request/response contract (HTTP faked).

## Documentation

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — stack decision, layers, sitemap, journeys, role–permission matrix, data model, ledger and notification design, security controls.
- [`docs/SOURCE_FINDINGS.md`](docs/SOURCE_FINDINGS.md) — what the WhatsApp export, the minutes and the spreadsheet establish, and the **open questions leadership must answer**.
- [`docs/NEXTSMS.md`](docs/NEXTSMS.md) — the verified NextSMS API contract and the owner's setup checklist.
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — VPS install, updates, backups, restore, staging, monitoring.

## Useful commands

| Command | Purpose |
|---|---|
| `php artisan fa:bootstrap-admin "+2557…" "Name"` | First super admin + one-time invitation link |
| `php artisan fa:notifications-dispatch` | Queue due WhatsApp messages (cron runs it every minute) |
| `php artisan fa:reminders-run` | Create automatic reminder jobs (idempotent) |
| `php artisan fa:import-minutes file.docx --date=YYYY-MM-DD` | Import minutes as an unapproved draft |
| `php artisan fa:prune` | Retention clean-up |
| `bash deploy/package.sh --ship` | Build and upload a release |

## Principles built in

- **Nothing invented.** Every amount, deadline and penalty is a policy rule that stays inactive until leadership confirms it. Missing values (penalty amount, due day) are shown as missing.
- **Money is traceable.** Member payment notices change nothing until a different finance officer verifies them; the ledger balances per fund; confirmed entries are corrected only by reversal.
- **Private by default.** Individual balances only to the member and finance officers; children's data restricted; welfare details encrypted; Secreto pairs encrypted and never listed; photos only through authorised URLs.
- **Honest messaging.** "Accepted by the provider" is never shown as "delivered"; production cannot run with the fake provider.
