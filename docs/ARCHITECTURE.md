# Family Affairs — Architecture

## 1. Stack and why

| Layer | Choice | Reason |
|---|---|---|
| Server | **Laravel 13** (PHP 8.3) | The family's VPS already runs PHP 8.3-FPM + nginx + MariaDB for other sites; Laravel ships queues, a scheduler, policies, CSRF, encryption and testing out of the box. The brief's suggested Next.js/PostgreSQL stack would have added a Node process and a second database server to maintain. |
| UI | Blade + **Tailwind CSS v4** + **Alpine.js** | Server-rendered pages work on slow phones and connections; Alpine adds small interactions without a SPA. |
| Database | **MariaDB 10.11** (prod) / MySQL 8 (local, tests) | Same engine family everywhere; tests run against MySQL, not SQLite, so behaviour matches production. |
| Fonts | Fraunces (headings) + Atkinson Hyperlegible (body), self-hosted via Fontsource | Atkinson Hyperlegible was designed for low-vision readers (older relatives). No third-party font CDN, so no visitor tracking. |
| PDF | barryvdh/laravel-dompdf | Pure PHP, no headless browser on the server. |
| Messaging | NextSMS **Messaging Service API v2** behind a `WhatsAppProvider` interface | Vendor-neutral; a `FakeProvider` exists only for local/testing and the app refuses to boot with it in production. |

## 2. Layers

```
Browser (Blade + Alpine)            ← no secrets, no business rules
   │
Routes + middleware                  auth → EnsureActiveMember (verified, consented, active) → can:<permission>
   │
Controllers (thin)                   validate input, call a service, redirect
   │
Services (all business rules)        OtpService · ContributionService · LedgerService · TreasuryService
   │                                 WelfareService · SecretoService · NotificationService · ReminderEngine
   │                                 InvitationService · ReportService · ImportService
   │
Eloquent models                      immutability guards (LedgerTransaction, AuditLog), encrypted casts
   │
MariaDB  +  private disk (storage/app/private — never web-served)
   │
Scheduler (cron, every minute) → database queue → SendNotificationDelivery → NextSMS
                                                   NextSMS webhook → /webhooks/nextsms (token-verified)
```

## 3. Sitemap

```
/login, /verify, /invite/{token}          public (no family data)
/welcome                                   first-run consent + profile
/dashboard                                 member + leadership + treasurer panels (permission-gated)
/members, /members/{id}                    directory, profile, relationships, roles, status history
/finance, /finance/pay                     my statement, payment notice + receipt, my PDF
/treasury/…                                overview · submissions · members · expenses · ledger · accounts · rules · penalties · reports
/governance, /governance/compliance        rules (confirm/correct/retire) · constitution versions · amendments · decisions · arrears process
/meetings, /meetings/{id}, …/minutes       agenda · attendance · decisions · actions · minutes editor · PDF
/events, /events/{id}                      calendar · committees · Kanban/list/timeline · budget · vendors · RSVP
/welfare, /welfare/{id}, /charity/{id}     cases · levies · disbursements · charity visits · volunteers
/secreto                                   season · registration · my recipient · organiser tools
/memories, /memories/albums/{id}           albums · uploads · lightbox · takedown requests
/community, /community/polls/{id}          official notices · posts · comments · reactions · polls
/notifications, …/compose, …/{job}         WhatsApp composer · approvals · delivery log · retry
/admin, /admin/roles, /admin/audit, /admin/templates, /admin/imports
```

## 4. Key user journeys

1. **Join**: Secretary adds person → *Tuma mwaliko* → member opens personal link → enters the invited number (must match) → WhatsApp OTP → consent screen → dashboard.
2. **Sign in**: phone → OTP on WhatsApp → dashboard. Identical response for unknown numbers.
3. **Pay**: member pays bank/M-Pesa → *Tuma taarifa ya malipo* with reference + receipt → Treasurer verifies against the statement → payment allocated (oldest first) and posted to the ledger.
4. **Bereavement**: anyone reports → Chair/Vice approves (amounts only from *confirmed* rules) → levies created for everyone except the bereaved → members pay → Treasurer records the payout → case closed with summary.
5. **Meeting**: Secretary schedules + agenda → reminders go out → attendance, decisions, actions recorded → minutes drafted → submitted → approved by someone else → visible to members, PDF.
6. **Event**: create with starter committees → assign tasks (optional WhatsApp to owner) → Kanban → budget lines → expenses via Treasury → report & lessons.
7. **Secreto**: organiser opens registration → members join + wishlist → draw (derangement, optional household rule) → each sees only their own recipient.

## 5. Role–permission matrix (default; editable in Admin → Roles)

| Permission | Admin | Chair | Vice | Sec | A.Sec | Treas | A.Treas | Cttee | Audit | Member |
|---|---|---|---|---|---|---|---|---|---|---|
| `members.view` — See the family directory | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `members.view_private` — See private fields: birth year, phone, emergency contact, children, leader notes | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |
| `members.manage` — Add/edit members, invite, change membership status, record relationships | ✓ | ✓ |  | ✓ |  |  |  |  |  |  |
| `members.import` — Review and confirm imported member lists | ✓ |  |  | ✓ |  | ✓ |  |  |  |  |
| `roles.manage` — Assign roles and edit role permissions | ✓ |  |  |  |  |  |  |  |  |  |
| `finance.view_own` — See own contribution statement | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `finance.submit_payment` — Submit a payment notification with receipt | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `finance.view_summary` — See aggregate fund balances and approved family statements | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `finance.view_all` — See every member statement, the ledger and reconciliation | ✓ | ✓ |  |  |  | ✓ | ✓ |  | ✓ |  |
| `finance.verify` — Verify/reject payment submissions and record received payments | ✓ |  |  |  |  | ✓ | ✓ |  |  |  |
| `finance.manage` — Generate obligations, waive, transfers, opening balances, valuations, penalties | ✓ |  |  |  |  | ✓ |  |  |  |  |
| `finance.reverse` — Reverse a confirmed payment or transaction (with reason) | ✓ | ✓ |  |  |  | ✓ |  |  |  |  |
| `finance.request_expense` — Request an expense | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |
| `finance.approve_expense` — Approve or reject expenses (never own requests) | ✓ | ✓ | ✓ |  |  |  |  |  |  |  |
| `finance.pay_expense` — Record payment of an approved expense | ✓ |  |  |  |  | ✓ |  |  |  |  |
| `finance.export` — Download financial reports (PDF/CSV) | ✓ | ✓ |  |  |  | ✓ | ✓ |  | ✓ |  |
| `finance.rules` — Draft contribution rules (activation needs governance.confirm_rules) | ✓ |  |  |  |  | ✓ |  |  |  |  |
| `governance.manage_meetings` — Create meetings, agendas, attendance, minutes, decisions, action items | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |
| `governance.approve_minutes` — Approve submitted minutes (never minutes you prepared) | ✓ | ✓ | ✓ |  |  |  |  |  |  |  |
| `governance.confirm_rules` — Confirm or change family rules, approve contribution rules and constitution versions | ✓ | ✓ | ✓ |  |  |  |  |  |  |  |
| `governance.propose_amendment` — Propose a constitution amendment | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `governance.manage_constitution` — Edit constitution drafts and review amendments | ✓ |  |  | ✓ |  |  |  |  |  |  |
| `governance.compliance` — Run the arrears warning/review process | ✓ | ✓ |  | ✓ |  | ✓ |  |  |  |  |
| `events.manage` — Create and manage events, committees, budgets, vendors | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |
| `events.manage_tasks` — Update committee tasks and checklists | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |
| `welfare.submit` — Report a bereavement or welfare need | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `welfare.approve` — Approve or reject welfare cases | ✓ | ✓ | ✓ |  |  |  |  |  |  |  |
| `welfare.view_private` — See private case details and private cases | ✓ | ✓ | ✓ | ✓ |  | ✓ |  |  |  |  |
| `welfare.disburse` — Record welfare support payments | ✓ |  |  |  |  | ✓ |  |  |  |  |
| `charity.manage` — Plan charity activities and record outcomes | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `secreto.manage` — Run a Secreto season (open registration, draw) — cannot see pairs | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `secreto.reveal` — Break-glass: reveal all Secreto pairs (audited) | ✓ |  |  |  |  |  |  |  |  |  |
| `community.post` — Write family posts, comments and reactions | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `community.announce_official` — Publish official announcements | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |
| `community.moderate` — Hide posts/comments, pin notices | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `community.create_poll` — Create polls | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |
| `memories.upload` — Upload photos | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `memories.manage` — Create albums, handle takedown requests | ✓ | ✓ | ✓ | ✓ |  |  |  |  |  |  |
| `notifications.manage` — Compose and schedule WhatsApp notifications | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |  |
| `notifications.approve` — Approve large broadcasts (never own) | ✓ | ✓ | ✓ |  |  |  |  |  |  |  |
| `notifications.view_logs` — See delivery logs and provider responses | ✓ | ✓ |  | ✓ |  |  |  |  | ✓ |  |
| `admin.settings` — System settings, templates, reminder rules, integration status | ✓ |  |  |  |  |  |  |  |  |  |
| `admin.audit` — Read the audit log | ✓ | ✓ |  |  |  |  |  |  | ✓ |  |


Authorisation is checked **by permission key** everywhere (`Gate::before` maps `can:finance.verify` to `User::hasPermission`). Role names are labels only.

Segregation-of-duties rules enforced in services (not just hidden buttons):
- nobody verifies or records a payment for **their own** member record;
- nobody approves an **expense**, **contribution rule**, **welfare case**, **broadcast**, **constitution version** or **minutes** they created;
- only a super admin can grant/remove super admin, and cannot remove their own.

## 6. Data model (main tables)

Identity: `members` (people, incl. children and non-members) · `users` (login accounts, 1:1 member, passwordless) · `households` · `family_relationships` · `membership_status_histories` · `roles` / `permissions` / `role_user` / `permission_role` · `invitations` (SHA-256 token only) · `otp_challenges` (HMAC of code only) · `sessions`.

Governance: `meetings` · `meeting_agenda_items` · `meeting_attendances` · `meeting_minutes` · `decisions` · `action_items` · `constitution_versions` · `constitution_amendments` · `policy_rules` (+ `policy_rule_versions`) · `compliance_cases` · `approvals` · `documents`.

Finance: `funds` · `ledger_accounts` · `ledger_transactions` · `ledger_entries` · `contribution_rules` · `obligations` · `payment_submissions` · `verified_payments` · `payment_allocations` · `expenses` · `investment_valuations`.

Welfare & traditions: `welfare_cases` · `welfare_disbursements` · `charity_activities` · `charity_volunteers` · `secreto_seasons` · `secreto_participants` · `secreto_assignments`.

Community & memories: `events` (+ `event_invitees`, `event_committees`, `event_committee_member`, `event_tasks`, `event_budget_lines`, `event_rsvps`) · `vendors` · `announcements` · `comments` · `reactions` · `polls` · `poll_options` · `poll_votes` · `albums` · `media_assets` · `takedown_requests`.

Messaging & ops: `notification_templates` · `notification_preferences` · `notification_jobs` · `notification_deliveries` · `reminder_rules` · `provider_webhook_events` · `audit_logs` · `system_settings` · `import_batches` · `import_candidates` · `jobs` / `failed_jobs` · `dev_outbox` (local only).

Public URLs use random ULIDs (`public_id`), never sequential ids.

## 7. Ledger design

Double-entry with a **fund dimension**. `LedgerService::post()` refuses any transaction whose debits ≠ credits **overall or within any fund**, so "balance by fund" is always derivable and exact. Amounts are whole shillings (`BIGINT`).

| Event | Entries |
|---|---|
| Opening balance | Dr Bank[fund] / Cr 3000 Opening[fund] |
| Contribution paid (20k: 10k general + 10k welfare) | Dr Bank[general] 10k, Dr Bank[welfare] 10k / Cr 4000 Contributions[general] 10k, Cr 4000[welfare] 10k |
| Overpayment | Dr Bank[general] / Cr 2100 Member credit[general] — later applied: Dr 2100 / Cr Bank[general] + Dr Bank[target fund] / Cr income[target fund] |
| Penalty paid | … Cr 4200 Penalties[general] |
| Welfare payout | Dr 5100 Welfare payouts[welfare] / Cr Bank[welfare] |
| Expense paid | Dr 5xxx[fund] / Cr Bank[fund] (refused if the fund lacks money in that account) |
| Bank → UTT | Dr 1100 UTT[investment] / Cr Bank[investment] |
| Re-designate money | Dr 3100 Inter-fund[from] / Cr Bank[from] + Dr Bank[to] / Cr 3100[to] |
| Investment return | Dr 1100[investment] / Cr 4500 Returns[investment] |

Posted transactions are immutable (model guard). Corrections are **reversals**: the mirror transaction is posted and linked, the original is marked `reversed`; payment reversals also unwind allocations and reopen obligations. Market valuations live in `investment_valuations` and never enter the ledger.

## 8. Notification engine

`schedule()` → `notification_jobs` row (approval needed above `config/family.php → messaging.approval_threshold` = 10 recipients, never by its creator) → every minute `fa:notifications-dispatch` atomically claims due jobs, creates one `notification_deliveries` row per recipient (unique `(job, member)` = idempotent) and queues `SendNotificationDelivery` (unique per delivery). `send()` re-checks under a row lock, honours opt-outs and quiet hours, calls the provider, and records **submitted** — never *delivered*. Delivery/read status only comes from the token-verified webhook. Temporary failures back off 5 / 30 / 120 minutes; permanent ones are marked failed and can be retried manually (only failed rows are retried).

Private-balance templates are refused for any group audience; each recipient's amount is computed for that recipient only and stored encrypted.

## 9. Security controls (summary)

- Passwordless WhatsApp OTP: CSPRNG codes, HMAC-stored, 5-minute TTL, 5 attempts, 60 s resend cooldown, per-phone and per-IP hourly caps, newest code invalidates older ones, row-locked verification (no double use), no enumeration (decoy challenge), codes never logged or returned.
- Every request re-checks: verified phone, enabled account, active/suspended membership, consent.
- Server-side permission checks on every route; private records scoped in queries (`visibleTo` scopes) and 404 when not visible.
- Encrypted at rest: welfare private details, leader notes, Secreto pairs, per-recipient message variables, sessions.
- Files: private disk only, served through authorised controllers; images re-encoded (strips EXIF/GPS and non-pixel payloads); size/type limits; CSV exports neutralise formula injection.
- Headers: CSP (self-only), HSTS, X-Frame-Options DENY, nosniff, no-store for signed-in pages, `noindex`.
- Audit log is append-only (model refuses update/delete) and never stores secrets.
