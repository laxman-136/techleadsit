# Digital Marketing AI Guide — OpenRouter / DeepSeek

## Activate
1. Update the existing plugin through WP Pusher and purge the page cache.
2. Open **Landing Page Settings → AI Powered+ Digital Marketing** in WordPress.
3. Enter an OpenRouter API key in the course chatbot password field and save the course settings. This automatically enables chatbot visibility and AI answers. The field is write-only; blank preserves the saved key. The server encrypts the saved key with the WordPress authentication salt. Alternatively set `TLIT_DM_OPENROUTER_KEY` in wp-config.php. Never commit a real key.
4. Optionally follow **Advanced AI settings** and click **Test saved AI connection**. This sends one billable sample question but creates no lead.
5. Ask paraphrases and follow-ups on the live widget before sending paid traffic. Test fees → instalments, beginner eligibility, missed classes, brochure, an unknown refund question, spelling mistakes and an unrelated request. Do not claim that offline mocks establish model accuracy.

Default model: `deepseek/deepseek-v3.2`, editable to another DeepSeek model ID supported by OpenRouter. Structured JSON output is required; routing requires supporting parameters. API destination is fixed to `https://openrouter.ai/api/v1/chat/completions`; no browser receives the key. Reference: https://openrouter.ai/docs/guides/features/structured-outputs

## Knowledge and answers
The compact, approved knowledge pack comes from the existing course landing page, source course material and the supplied brochure. All short records are supplied to the model so semantic matching can handle paraphrases without a brittle keyword filter or a separate vector service. The model selects and cites relevant record IDs internally. This is context-grounded generation, not model fine-tuning. No webpage crawling or autonomous learning occurs at runtime.

Fees come from the AI Guide settings and update both the WordPress-rendered landing page and bot. Batch date/timing come from existing course settings. Purge caches after changes. The static PDF retains its published content. Current settings override older material. Confirmed landing-page facts resolve source conflicts (trainer revenue and language). Review unresolved questions and approve new factual answers in WordPress; unknown payment/refund/stipend facts must not be invented.

The server validates output shape, completion state and source IDs. These checks reduce unsupported answers but cannot guarantee semantic correctness. Invalid, unavailable or ungrounded responses offer a team handoff. Provider errors and secrets are never returned to visitors. No model tool can submit a lead or change settings.

## Leads and OTP
This update switches **chatbot OTP off once**, preserving other settings. Later admin changes are respected. Chatbot brochure requests ask for name, email and mobile, plus course-contact consent. A learning goal is optional and can be captured from the conversation. The phone is labelled unverified in TeleCRM. Normal demo and brochure-popup forms keep their existing OTP requirements. Re-enable chatbot OTP under Landing Page Settings → AI Powered+ Digital Marketing if needed.

CRM acknowledgement is required before redirecting to the thank-you page. The existing automatic PDF download and tracking contract remain intact. AI requests never receive separate form contact fields. The widget discloses that questions are processed through OpenRouter.

## Limits and retention
Default cap: 300 attempted model requests per UTC day, and 30 per IP per rolling hour. Configure an OpenRouter key spending limit as a separate monetary cap. Conversation state uses a random 192-bit bearer token, stored server-side for 30 minutes, limited to the last four exchanges. Emails, phone-like numbers and common key patterns in question text are redacted before sending to the provider. This is not a complete detector of all personal information; names or volunteered personal text may remain.

Unresolved questions are stored for admin review: at most 100, retained 30 days, cleaned by WP-Cron. Ensure WordPress cron runs in production. No automatic knowledge approval. Provider routing opts out of data collection; provider handling is still governed by OpenRouter and the selected provider. No real API call was made during implementation because a key was not supplied.

## Validation
Source project: `php wordpress-bridge/check-ai.php`, `node check-preview.cjs`, `php check-wp-integration.php`. These mock AI/SMS/CRM and validate contracts, failure paths, origin checks, encrypted-key handling, bounded usage, conversation continuity, review redaction, no-OTP leads and settings migration. Rebuild source with `node build-wordpress.cjs` before publishing.
