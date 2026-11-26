# Mandatory lead capture and attribution

Every new or changed lead form, chatbot, landing-page route and CRM adapter must preserve this contract. Read [docs/lead-attribution.md](docs/lead-attribution.md) before changing a lead flow.

- Keep all 16 required hidden fields: `fbp`, `fbc`, `gclid`, `gbraid`, `wbraid`, `fbclid`, `ga_client_id`, `session_id`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_adgroup`, `utm_term`, `utm_content`, `landing_page`, `referrer`.
- Also preserve campaign/ad/ad set names and IDs: `campaign_name`, `ad_name`, `adset_name`, `campaign_id`, `ad_id`, `adset_id`, `utm_id`, `fb_campaign`, `fb_ad`, `fb_adset_name`, `fb_adset_id`, `fb_lead_id`.
- Save all captured tracking fields and human form answers in the main Conversion Bridge record before handoff. Keep them visible in the administrator and private consultant views, exports, TeleCRM fields and Remarks.
- Use `lead-attribution.js` and `TLIT_Attribution` for the shared contract. Custom JSON or URL-encoded submissions must explicitly include the collected tracking data; adding hidden HTML alone is insufficient.
- Preserve the original acquisition URL/referrer through navigation, popup creation, OTP and retries. Do not mix two campaigns, fabricate IDs/names, infer a name from an ID, or overwrite existing values with blanks. Missing attribution is allowed for direct traffic and must read “Not recorded”.
- Every form, chatbot and drop-off Teams alert must show campaign name, ad name and ad set / ad group name using the same captured values. Do not send test messages to real channels or create real test leads.
- Preserve OTP, receipt validation, deduplication, permissions and the existing counsellor-controlled TeleCRM handoff. Never auto-fill enrolment dates, fee/payment fields or authentication secrets.
- Required checks before commit/push: PHP syntax on changed PHP files; `C:/xampp/php/php.exe run_test.php` (or `php run_test.php`); `php tests/lead-attribution-test.php`; `node tests/lead-attribution-test.cjs`; affected form and Bridge tests. GitHub runs the offline attribution checks on every push and pull request.

Existing routing and CRM safeguards in [.agents/rules.md](.agents/rules.md) also apply.
