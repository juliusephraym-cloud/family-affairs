# Deployment, operations, backup & restore

Target: the existing Ubuntu 24.04 VPS (nginx, PHP 8.3-FPM, MariaDB 10.11) that already hosts other sites. Family Affairs gets its own nginx server block, database, system user context (`www-data`), queue worker and data directory, so it cannot disturb the other sites.

## Layout on the server

```
/var/www/family-affairs/releases/<stamp>/   code (read-only after deploy; 5 kept)
/var/www/family-affairs/current → releases/<stamp>
/var/lib/family-affairs/.env                 secrets (root:www-data 640)
/var/lib/family-affairs/storage/             logs, sessions cache, app/private (receipts, photos)
/var/lib/family-affairs/backups/             nightly db + files, 14 days
```

## First install (once)

1. **Database** (MariaDB):
   ```sql
   CREATE DATABASE family_affairs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'family_affairs'@'localhost' IDENTIFIED BY '<long random password>';
   GRANT ALL PRIVILEGES ON family_affairs.* TO 'family_affairs'@'localhost';
   ```
2. **PHP extensions**: `php8.3-{mysql,mbstring,xml,zip,gd,intl,curl,bcmath}` (gd/zip are needed for photos and spreadsheet import).
3. On the Mac: copy `deploy/deploy.conf.example` → `deploy/deploy.conf`, fill `DOMAIN` and `SSH_TARGET`, then `bash deploy/package.sh --ship`.
4. On the server, run the printed `fa-deploy.sh` command. The first run creates `/var/lib/family-affairs/.env` and stops. Edit it:
   - `APP_URL=https://<domain>`, `DB_DATABASE/USERNAME/PASSWORD`
   - `FA_MESSAGING_DRIVER=nextsms`, `NEXTSMS_TOKEN`, `NEXTSMS_WHATSAPP_ACCOUNT`, `NEXTSMS_OTP_TEMPLATE`, `NEXTSMS_WEBHOOK_TOKEN` (see `NEXTSMS.md`)
   - leave `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`
   Run `fa-deploy.sh` again — it migrates, seeds the production-safe structure (roles, funds, chart of accounts, *unconfirmed* rules, template drafts — **never demo data**), caches config and switches `current`.
5. **nginx**: install `deploy/server/nginx-family-affairs.conf` (replace `DOMAIN`), `nginx -t && systemctl reload nginx`, then `certbot --nginx -d <domain>`.
6. **Queue worker**: copy `deploy/server/family-affairs-queue.service` to `/etc/systemd/system/`, `systemctl enable --now family-affairs-queue`.
   (Shared hosting without systemd: set `FA_QUEUE_FROM_SCHEDULER=true` instead.)
7. **Cron**: add the two lines from `deploy/server/crontab.txt` (scheduler every minute as `www-data`; backup nightly as root).
8. **First administrator** (no passwords anywhere):
   ```bash
   cd /var/www/family-affairs/current && sudo -u www-data php8.3 artisan fa:bootstrap-admin "+2557XXXXXXXX" "Full Name"
   ```
   Open the printed link on that phone, verify the WhatsApp code, accept consent. Then use Familia → *Ongeza mwanafamilia* → *Tuma mwaliko* for everyone else, and assign leadership roles on each profile.
9. **Go-live checklist** (Admin → system health must be all green):
   - [ ] scheduler heartbeat < 5 minutes
   - [ ] provider = nextsms, token + account set, OTP template set, webhook token set
   - [ ] `APP_DEBUG` off, env = production
   - [ ] leadership has confirmed (or corrected) the rules in *Katiba na Kanuni*
   - [ ] Treasurer approved a contribution rule and entered opening balances from statements
   - [ ] payment instructions entered in Admin → Settings

## Updates

`bash deploy/package.sh --ship` → run the printed command. Every deploy takes a backup before migrating. Rollback of code: `bash /tmp/fa-deploy.sh --rollback` (migrations are additive; restore a backup if a migration must be undone).

## Backups

`fa-backup.sh` writes `db-<stamp>.sql.gz` (consistent `--single-transaction` dump) and `files-<stamp>.tar.gz` (receipts, photos, emblem) to `/var/lib/family-affairs/backups`, keeping 14 days. **Copy them off the server** regularly (e.g. weekly `scp` to the Mac or an encrypted cloud folder) — a backup on the same disk does not survive the disk.

## Restore

```bash
cd /var/www/family-affairs/current && sudo -u www-data php8.3 artisan down
gunzip -c /var/lib/family-affairs/backups/db-<stamp>.sql.gz | mysql -u family_affairs -p family_affairs
tar -xzf /var/lib/family-affairs/backups/files-<stamp>.tar.gz -C /var/lib/family-affairs/storage/app
chown -R www-data:www-data /var/lib/family-affairs/storage
sudo -u www-data php8.3 artisan optimize:clear && sudo -u www-data php8.3 artisan up
```
Restore the **same** `APP_KEY` as when the backup was made — encrypted columns (welfare private details, leader notes, Secreto pairs, sessions) cannot be read with a different key. Keep a copy of the `.env` (APP_KEY) in a password manager.

## Staging / testing

- Local: `.env` with `APP_ENV=local`, `FA_MESSAGING_DRIVER=fake` (messages appear at `/dev/outbox`), `php artisan migrate:fresh --seed` loads demo data.
- Staging on the VPS: a second copy with its own database, `APP_ENV=staging`, `FA_MESSAGING_DRIVER=nextsms`, `NEXTSMS_TEST_MODE=true` (NextSMS's documented no-send endpoint). The fake driver is refused outside local/testing.
- Tests: `php artisan test` uses the `family_affairs_test` MySQL database (see `phpunit.xml`).

## Monitoring

- Admin → *Utawala*: DB, scheduler heartbeat, queue backlog, failed jobs, storage, provider config and balance, 7-day delivery summary, 24 h security counters (OTP failures, failed sends, invitation misuse, rejected webhooks).
- Logs: `/var/lib/family-affairs/storage/logs/laravel-YYYY-MM-DD.log` (daily, phone numbers masked, OTP codes never logged) and `queue.log`.
- `fa:prune` (daily) deletes OTP challenges after 30 days and webhook payloads after 180 days.

## Data protection notes (Tanzania PDPA 2022)

- Purpose: running a private family association. Data minimised to what that needs; children's records hidden from ordinary members; birth year never shown to members.
- Consent is recorded before any WhatsApp message (`whatsapp_opt_in_at`); members can opt out per category or entirely.
- Members can request correction or deletion from their profile; requests go to the Secretary's action list and are audited. Financial records are retained per the family's policy.
- Leadership should designate who acts as data controller contact and agree a retention period for photos and financial records; record that decision in the minutes.

## Public demo instance

A separate instance runs at `https://family-affairs.168.231.115.143.sslip.io` (free sslip.io name pointing at the VPS IP):
`/var/www/family-affairs-demo`, data in `/var/lib/family-affairs-demo`, database `family_affairs_demo`, nginx `sites-available/family-affairs-demo` (certbot-managed).
Its `.env` has `APP_ENV=demo`, `FA_DEMO_MODE=true`, `FA_MESSAGING_DRIVER=fake`, `FA_QUEUE_FROM_SCHEDULER=true` and relaxed OTP limits (many visitors share one number).
Demo mode shows the code on screen, blocks uploads and reloads the fictional data at 03:00 (`fa:demo-reset`). Update it with:
`APP_DIR=/var/www/family-affairs-demo DATA_DIR=/var/lib/family-affairs-demo bash /tmp/fa-deploy.sh /tmp/<package>.tar.gz`.
