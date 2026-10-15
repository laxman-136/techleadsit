# Digital Marketing release — 15 October 2026

This package updates all three Digital Marketing variants and the bundled Conversation Bridge. Publishing from WP Pusher remains the site owner's step.

## Update in WordPress

1. Update **TechLeadsIT Landing Pages** from the GitHub `master` branch using WP Pusher, then clear any page/cache plugin cache.
2. Open **Conversation Bridge → Conversations**. The **Learning goal analysis** panel shows the number of analysed sessions. Historical conversations process in background batches; use **Process next batch** if WordPress Cron is delayed. New conversations update their goal automatically without asking the visitor to choose one.
3. Open **Conversation Bridge → Landing Page Leads → Lead integrations**. Enter your TeleCRM enterprise ID and bearer token and enable handoff, or explicitly select the existing landing-page connection. Saving a blank token field preserves an existing token. **Check saved configuration** does not submit a test lead and does not claim to verify API authentication.
4. Review leads in **Landing page leads**. General forms, popup forms and brochure requests from all variants are saved here first, with page version, submission URL, UTM/click tracking, contact details, verification state and form answers. Use the date, source, qualification and campaign filters, or download individual/filtered CSV files.
5. After calling the learner, save **Potential**, **High potential**, **Intended to join** or **Enrolled** to send that lead to TeleCRM. **New** and **Junk lead** do not send. Previously sent leads are not sent again when their status changes. If delivery is uncertain, check TeleCRM and record the outcome before retrying.

Counsellor enrolment dates and payment fields remain manual in TeleCRM. Brochure requests and recorded download starts are shown separately; download tracking cannot prove that a browser saved a PDF successfully.

## Page routes after the update

- Version 1: `/ai-powered-digital-marketing/`
- Version 2: `/ai-powered-digital-marketing-course/`
- Version 3: `/ai-powered-digital-marketing-training/`

Version 3 has its own WordPress Pages entry, course-settings registry entry and thank-you route. All three remain `noindex`. The chatbot has international country selection (India by default) and resumes the pending question after phone capture.

## Learning-goal interpretation

Goals are based on visitor conversation evidence. Bridge distinguishes stated goals, inferred goals, course interest and insufficient context. An introductory greeting or a fee question alone does not establish a career ambition. Previously uncaptured conversations cannot be reconstructed.

If a separately installed Conversation Bridge plugin is active, it must also use the updated `conversation-bridge` files in this release; an older standalone copy can take precedence over the bundled copy.

## Verification boundary

The release was checked with offline form/browser tests and an isolated local WordPress/MariaDB database. Tests covered historical and new goal analysis, lead capture before CRM, OTP checks, country codes, attribution, date/source filters, duplicate prevention, counsellor qualification, token storage, delivery uncertainty and brochure download receipts. No real SMS, lead or CRM request was sent during these checks. Live WordPress/TeleCRM verification remains after the owner's plugin update and connection setup.
