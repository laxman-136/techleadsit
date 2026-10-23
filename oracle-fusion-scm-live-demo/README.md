# SCM Batch 80 — direct Apps Script registration

Both existing forms submit directly to the Google Apps Script web-app URL. No n8n workflow, WordPress REST relay, or WordPress registration settings are used. The landing page itself is still hosted by the existing WordPress plugin.

## Current state

Public forms remain paused until the owner supplies the deployed `/exec` URL and public Turnstile site key, and live checks pass. The previous n8n workflow remains disabled. The replacement Apps Script is in `apps-script/Code.gs` and is fixed to the owner's existing Sheet `1wSL-AyX-nsetyp3SzLnJ8JxQnF7k7qyUN1njU1ENR-o`, tab ID 0. WordPress no longer registers the former SCM registration API or settings page. Its old inactive option is not read, transmitted, or required.

## Simple owner setup

1. Open the existing Sheet as its owner (`techleadsit@gmail.com`). Set Share → General access → Restricted, and stop any File → Share → Publish to web publication. Keep A1:D1 exactly: `Name`, `Email`, `Mobile`, `Career objective`. Preserve existing rows.
2. In Cloudflare → Turnstile, create a Managed widget allowing only `lp.techleadsit.com`. Keep the secret key private. The site key is public.
3. In the Sheet → Extensions → Apps Script, replace ALL of Code.gs with `apps-script/Code.gs`. The old `SCM_SHARED_SECRET` is no longer used. There is no WordPress-side secret to copy.
4. In the Apps Script editor, next to Services, click + and add Google Drive API (v3) and Google Sheets API (v4). For a default Apps Script Cloud project this also enables those APIs. For a manually selected standard Cloud project, enable both APIs in that Cloud project's API Library as well. No manifest editing is needed. The included appsscript.json is an optional equivalent configuration.
5. Apps Script → Project Settings → Script Properties: add `TURNSTILE_SECRET_KEY` with the real widget secret key. Save. Do not put this secret in the source code, website, Git, or chat. No other manually generated secret is needed.
6. Select `setupPrivateSheet` from the editor function dropdown and click Run. Authorize as owner. It restricts general link access, validates the headers and privacy, creates a rate-limit salt internally, and sets REGISTRATION_ENABLED=true. It does not modify existing registration rows. Wait for Execution completed. Website forms are still paused separately.
7. Deploy → New deployment → Web app. Execute as Me (the Sheet owner); Who has access: Anyone. Copy the resulting URL ending in `/exec`. If updating an existing deployment, use Deploy → Manage deployments → Edit → New version → Deploy. Saving the source alone does not update a deployed version.
8. Send the deployment `/exec` URL and the PUBLIC Turnstile SITE key to the developer/assistant. Keep the Turnstile SECRET in Apps Script only. The public endpoint requires a real CAPTCHA token verified for the exact website hostname and action before accessing any Sheet data.
9. The developer connects both forms, tests them while the public UI is paused, verifies the Sheet is inaccessible signed out, then enables the public UI only after confirmation of successful saves. Both forms must show the Zoom link only after a readable JSON success response with the matching registration ID.

## Developer connection and tests

Set only the public `endpoint` and `sitekey` in app.js's `registrationConfig`, retaining `enabled:false` while testing. Bump the app.js asset version consistently in index.html and techleadsit.php. The site's administrator can open `/scm-batch-80?scm_test=1` for a UI preview while public forms are paused. This only shows the forms; it is not a server security bypass. Apps Script still verifies CAPTCHA, privacy, validation and limits for every submission. Once the two forms pass real Google/CAPTCHA tests, set `enabled:true`, bump the version, deploy and verify the live page.

The direct fetch is URL-encoded, uses `credentials:omit`, follows Google's ContentService redirect and requires a readable response. Never use `mode:no-cors`, opaque-response success, JSONP, or an iframe-load event as proof of a save. If the actual deployment's response cannot be read by the browser, keep forms paused and investigate the Google deployment/access/redirect behavior before going live.

Test inline and popup separately with clearly labeled synthetic records. Verify all four fields and the `+` country code. A second request with the same email must append, not replace the first row. Identical request-ID retries with fresh CAPTCHA tokens must not add another row. Invalid/reused CAPTCHA, conflicting request IDs, invalid fields, storage failures, and an unreadable response must not show success. Do not temporarily expose the production Sheet to test privacy rejection; use the mock tests or a separate nonproduction fixture. Remove only identified synthetic rows with owner approval.

Run:

- `node oracle-fusion-scm-live-demo/tests/apps-script.cjs`
- `node oracle-fusion-scm-live-demo/tests/forms.cjs` (local-only browser integration with mocked services)
- `C:/xampp/php/php.exe -l techleadsit.php`
- `C:/xampp/php/php.exe run_test.php`

Mock tests are not proof that the real Google deployment is connected.

## Security behavior

- No public read endpoint; GET returns only `post_required`.
- Rejects bad fields, duplicate parameters, oversized payloads and the honeypot before CAPTCHA verification.
- Server-side Turnstile validation requires success, hostname `lp.techleadsit.com`, and action `scm_registration`. A public URL or Origin header is not caller authentication. This is an anonymous registration service protected against automated submissions, not verified email/phone identity.
- Checks the Sheet's Drive permissions for anyone/domain/published access on every valid submission and refuses storage if broad access is found.
- Uses ScriptLock and durable request IDs in a column-A note. The four cells plus the note are appended atomically. Existing email addresses are never update selectors.
- Explicit Sheets string values preserve phone prefixes and prevent formula execution. The note contains a random ID and digest, not additional contact fields.
- Limits new registrations to 30/minute globally and 3/hour per salted email hash. Expired rate counters are cleaned on subsequent accepted attempts. Apps Script does not expose a trustworthy client IP; no fake IP-based limit is claimed. Public invalid traffic can still consume Apps Script execution quota.
- No request bodies, Google error details, or secrets are logged by this script or returned in public errors. No contact details are stored in WordPress, browser storage, or Conversion/Conversation Bridge.
- To stop ALL collection, run `pauseRegistration` in the Apps Script editor (sets REGISTRATION_ENABLED=false immediately). Running setupPrivateSheet again re-enables the endpoint after checks. The frontend enabled flag only pauses the UI; the script property is the server-side switch.

References: https://developers.google.com/apps-script/guides/web ; https://developers.google.com/apps-script/guides/content ; https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
