# Oracle Fusion SCM live demo

Standalone page for 31 October 2026, 8:00 AM IST. Open index.html or serve this folder with a static HTTP server. It is separate from the digital marketing pages.

All page CTAs open a native accessible dialog with name, email, mobile number, the requested country-code list (India default, plus a custom field for Other), and the five exact career-objective choices. Escape, close button, outside click, focus restoration, and native required-field validation are supported.

Both forms use `/wp-json/techleadsit-scm/v1/register`. The sole spreadsheet destination is `1wSL-AyX-nsetyp3SzLnJ8JxQnF7k7qyUN1njU1ENR-o`, tab `Sheet1`, columns Name, Email, Mobile, Career objective. The replacement is WordPress → signed Apps Script request → private Sheet. Public registration is paused by default. The former n8n workflow `OJ6rWvTcsaH2SNVg` was unpublished on 23 October 2026; do not reactivate it.

No Conversion/Conversation Bridge, TeleCRM, WordPress lead storage, analytics, or browser storage is used by this page. The isolated route bypasses shared landing-page scripts. WordPress stores only salted rate-limit hashes and temporary counters, plus server configuration. No contact values or credentials are exposed in the frontend. Do not activate until private access and both live forms have been verified.

Client/server validation, Turnstile server verification, a honeypot, rate limits, disabled submit while saving, a 50-second timeout and a retry message cover errors. Success and the Zoom link appear only after a confirmed save. No confirmation email or WhatsApp message is sent.

The three user-supplied images are under assets. The logo is reused from the existing SCM page. Demo topics are conservative introductory copy based on the supplied poster; confirm the detailed agenda before launch.

## Owner setup (use your own signed-in browser)

1. Open the existing Sheet as `techleadsit@gmail.com`. Set Share → General access → Restricted. Keep only named team accounts that need access. Stop any File → Share → Publish to web publication. No n8n access is needed.
2. Open Extensions → Apps Script. Paste `apps-script/Code.gs`. In project settings, show the manifest and use `apps-script/appsscript.json`. Enable Google Sheets API and Google Drive API for the project's Google Cloud project if needed. The script needs Sheets, Drive and external request permissions; its source is fixed to this one Sheet and tab ID 0.
3. In WordPress → Settings → SCM Registration Security, copy the generated shared secret into the Apps Script project's Script Properties under `SCM_SHARED_SECRET`. Do not put this value in chat, frontend code, or Git. The admin-only password field can be selected and copied.
4. Run `setupPrivateSheet` in the Apps Script editor as owner and authorize it. This restricts link sharing and checks the existing headers; it does not modify rows. If the Sheet is published, stop publishing and rerun setup.
5. Deploy → New deployment → Web app. Execute as **Me** (Sheet owner); access **Anyone**. The endpoint is reachable by the website server but rejects unsigned or stale requests before reading or writing. The Sheet stays private. Save the `/exec` deployment URL in WordPress settings, not the `/dev` test URL.
6. Create a Cloudflare Turnstile widget restricted to `lp.techleadsit.com`. Save its site key and secret key in WordPress settings. Never use public test keys on the live site. Save with public registration disabled.
7. Open admin test mode from those settings. Submit a labeled synthetic registration through each form and complete the real security check. Verify all four fields in Sheet1 and the Zoom link after success. Two different requests with the same test email must append separate rows, preserving the first; retrying an identical request ID must not append again. Invalid CAPTCHA, unsigned Apps Script requests, and upstream failures must not show success or save records.
8. Check the Sheet while signed out: access must be denied. Remove only identified synthetic test rows with owner approval. Then check the verification acknowledgment and enable public registration.

## Security details

- Server-only HMAC-SHA256 signs the exact payload and timestamp. Apps Script accepts signatures for 120 seconds.
- Both layers validate fields. Apps Script uses explicit `stringValue` cells, preserving international phones and preventing spreadsheet formula execution.
- Apps Script checks Drive permissions, including published permissions, on every save. Any `anyone`, `domain`, or published permission causes rejection.
- ScriptLock plus an atomic append and column-A note provide durable request-ID idempotency. The note contains only a random ID and payload digest. Email is never an update selector.
- WordPress verifies Turnstile success, hostname and action; caps the body at 8 KiB; limits each direct client address to 10 attempts/10 minutes and each salted email hash to 3/hour. Arbitrary forwarded-IP headers are not trusted. If a reverse proxy masks addresses, configure trusted server-level real-IP mapping before enabling or the limit applies to the proxy.
- Errors do not echo contact values, credentials or Google responses. Infrastructure log retention still needs the owner's normal review. Existing n8n execution history is untouched.
- Admin test mode uses an administrator's REST nonce and uncached responses. CAPTCHA remains mandatory. Missing settings fail closed.

Run PHP syntax checks for `techleadsit.php` and `registration.php`, `php run_test.php`, and the tests in `tests/`. Mock tests do not replace Google deployment and both live-form tests.

References: [Apps Script web apps](https://developers.google.com/apps-script/guides/web), [Drive permissions](https://developers.google.com/workspace/drive/api/reference/rest/v3/permissions), [Turnstile server validation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/).
