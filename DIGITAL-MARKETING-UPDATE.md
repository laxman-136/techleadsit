# Digital Marketing release — 16 October 2026

This package updates all three Digital Marketing variants and the bundled Conversation Bridge. Publishing from WP Pusher remains the site owner's step.

## Update in WordPress

1. Update **TechLeadsIT Landing Pages** from the GitHub `master` branch using WP Pusher, then clear any page/cache plugin cache.
2. Open **Conversation Bridge → Conversations**. The **Learning goal analysis** panel shows the number of analysed sessions. Historical conversations process in background batches; use **Process next batch** if WordPress Cron is delayed. New conversations update their goal automatically without asking the visitor to choose one.
3. The Bridge automatically uses the existing Digital Marketing TeleCRM connection. No second API key, enterprise ID, enable switch or Lead integrations setup is needed. If that existing connection is unavailable, leads remain saved in WordPress with a clear sync message.
4. Review leads in **Landing page leads**. General forms, popup forms and brochure requests from all variants are saved here first, with page version, submission URL, UTM/click tracking, contact details, verification state and form answers. Use the date, source, qualification and campaign filters, or download individual/filtered CSV files.
5. After calling the learner, choose one of the exact TeleCRM status labels and save to sync that lead. Later qualified status changes update the same lead; unchanged saves do not duplicate an update. **New** and **Junk lead** do not send. If delivery is uncertain, check whether the latest attempted qualification/notes were applied in TeleCRM before resolving it.
6. Chatbot conversations have the same counsellor qualification workflow, separate from their existing New/Reviewed/Contacted/Archived conversation-review labels. Phone is required for TeleCRM; email remains optional for chat. Qualified conversations and brochure requests share a canonical lead rather than creating separate copies.

Approved native status mapping: **Potential → 50 % Potential**, **High potential → 60-80 Potential**, **Intended to join → Potential Lead 100**, **Enrolled → Enrolled**. The Integrations tab is intentionally blank. Counsellor dropdowns include all active TeleCRM status names; New and Junk Lead remain local. Received times, date filters and downloads use IST regardless of the WordPress timezone.

Counsellor enrolment dates and payment fields remain manual in TeleCRM. Brochure requests and recorded download starts are shown separately; download tracking cannot prove that a browser saved a PDF successfully.

## Page routes after the update

- Version 1: `/ai-powered-digital-marketing/`
- Version 2: `/ai-powered-digital-marketing-course/`
- Version 3: `/ai-powered-digital-marketing-training/`

Version 3 has its own WordPress Pages entry, course-settings registry entry and thank-you route. All three remain `noindex`. The chatbot has international country selection (India by default) and resumes the pending question after phone capture.

Version **1.2.1** also creates an ordinary **AI Powered Digital Marketing (Original)** Pages entry for V1 on the next administrator visit. The exact Digital Marketing routes run before WordPress canonical and old-slug redirects, so the original URL serves V1 rather than being redirected to the similarly named V2. This early handler is restricted to the three course routes and the V2/V3 thank-you routes; other WordPress URLs retain their existing routing.

## Learning-goal interpretation

Goals are based on visitor conversation evidence. Bridge distinguishes stated goals, inferred goals, course interest and insufficient context. An introductory greeting or a fee question alone does not establish a career ambition. Previously uncaptured conversations cannot be reconstructed.

If a separately installed Conversation Bridge plugin is active, it must also use the updated `conversation-bridge` files in this release; an older standalone copy can take precedence over the bundled copy.

## Verification boundary

The earlier release was checked with offline form/browser tests and an isolated local WordPress/MariaDB database. Follow-up live verification confirmed goal analysis and V2/V3 brochure persistence. V1 returned a WordPress-issued 301 to V2 before the 1.2.1 routing repair. The routing regression uses isolated WordPress/MariaDB to check distinct page entries, distinct rendered templates, HTTP 200 without redirect, variant attribution, query strings, HEAD requests, thank-you pages and preservation of ordinary WordPress routing. Run `php digital-marketing-integration/tests/routing-wordpress-test.php /path/to/isolated-wordpress/wp-load.php`. Verify live responses after updating the plugin and clearing cached redirects.

The automatic status-sync revision is tested locally with intercepted HTTP requests. Live TeleCRM delivery and legacy-endpoint acceptance of native statuses must be verified after deployment with an authorised real qualified lead; no test contact is sent to TeleCRM by configuration checks.
