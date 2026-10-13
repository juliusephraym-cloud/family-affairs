# NextSMS WhatsApp integration

## What was verified (13 Oct 2026)

Source: the official API documentation linked from <https://www.nextsms.co.tz/service/whatsapp> ("API Docs" → Postman collection `1679195/2sAYkDP1XN`, *Messaging Service API V2*). The authenticated dashboard documentation was **not** available to me — please re-check the points marked ⚠ against your account.

| Item | Verified behaviour | Where in code |
|---|---|---|
| Base URL | `https://messaging-service.co.tz` | `NEXTSMS_BASE_URL` |
| Auth | `Authorization: Bearer <token>` (dashboard → Customer Info → Customization → API Keys). Basic auth also exists; not used. | `NextSmsProvider::client()` |
| Send WhatsApp | `POST /api/whatsapp/v2/text/single`, JSON `{to:[255…], account, template, personalisation:[{var:value}], button, reference}`. `to` must be an array of numbers with country code. | `NextSmsProvider::sendTemplate()` |
| No-send test | `POST /api/whatsapp/v2/test/text/single` — "No message(s) will be sent" | `NEXTSMS_TEST_MODE=true` |
| Templates | Business-initiated WhatsApp messages need a template registered and approved in the dashboard (Channels → Whatsapp Channel → Whatsapp Gateway). Free text is not available through this API. | `notification_templates.provider_template` |
| OTP | Documented "OTP Template" example: `button.personalisation.copy_otp_code.parameters = ["<code>"]` | `OtpService::issue()` |
| Response | `{messages:[{to, status:{groupId, groupName, id, name, description}, messageId, message, smsCount, price}]}`. Group 18 = PENDING (accepted), 20 = DELIVERY, 22 = FAILED, 19 = REJECTED. `messageId` is a 19-digit integer → parsed as a string. | `NextSmsProvider::interpret()` |
| Delivery reports | Webhook registered under Customization → Webhooks with a *Delivery Callback Verify Token*; payload `{token, messageId, status, id}` (73 DELIVERED, 108 READ, 74–80 failures). WhatsApp replies arrive as `{token, messageId, response, payload, text}`. | `/webhooks/nextsms`, `NotificationService::handleWebhook()` |
| HTTP query reports | `GET /api/v2/reports` — marked "to be discontinued"; each report retrievable once. Not used. | — |
| Balance | `GET /api/v2/balance` → `{sms_balance, display, …}` | Admin → system health |
| Price | Public page: "TSh 12 flat rate per message" for WhatsApp ⚠ confirm for your account | `NEXTSMS_PRICE_PER_MESSAGE` |

⚠ Not documented, so **not assumed**: webhook signing beyond the shared token, retry behaviour of the webhook, rate limits, whether an Authentication-category template is supported for every account. The app treats "accepted" as *submitted*, never as *delivered*, and only marks a message delivered/read when the token-verified webhook says so.

## Owner setup checklist

1. Create / log in to the NextSMS account; top up WhatsApp credit.
2. **Channels → Whatsapp Channel → Whatsapp Gateway**: connect the family's WhatsApp Business number. Put its account name in `NEXTSMS_WHATSAPP_ACCOUNT`.
3. **Customer Info → Customization → API Keys**: copy the token into `NEXTSMS_TOKEN` (server `.env` only — never in the repo or the browser).
4. Create and submit templates. Suggested Swahili wording for each is in **Admin → Violezo vya WhatsApp** (and `database/seeders/NotificationSeeder.php`):
   - **Authentication** template with a *copy code* button for sign-in codes → `NEXTSMS_OTP_TEMPLATE`.
   - Utility templates: contribution reminder, private balance, meeting reminder, event reminder, task assignment, welfare notice, Secreto deadline, general notice, charity notice, member invitation; and birthday greeting (Meta may classify it as *marketing*, which is priced differently ⚠).
5. When Meta approves each template, enter the exact template name in Admin → Violezo and set it to **Kimeidhinishwa**. Make sure the variable order in the app matches the template.
6. **Customization → Webhooks**: Delivery Callback URL = `https://<your-domain>/webhooks/nextsms`; choose a long random Verify Token and put the same value in `NEXTSMS_WEBHOOK_TOKEN`.
7. Test safely: set `NEXTSMS_TEST_MODE=true`, sign in once, check Admin → system health, then set it back to `false`.
8. Switch on the automatic reminders you want in Admin → Violezo (they are all off by default).

## Until this is done

- Production **will not boot** with `FA_MESSAGING_DRIVER=fake`. With `nextsms` but no token/template, sign-in codes fail visibly ("Hatukuweza kutuma nambari…") — nobody can be let in without a real code.
- Locally, `FA_MESSAGING_DRIVER=fake` writes messages to the `dev_outbox` table, readable at `/dev/outbox` (route only registered in `local`).
- If the family later prefers SMS for OTP, add an `SmsProvider` implementing `WhatsAppProvider` (NextSMS `POST /api/sms/v2/text/single` with a registered sender ID) and bind it in `AppServiceProvider` — no other code changes.
