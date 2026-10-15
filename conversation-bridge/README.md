# Tech Leads IT Conversation Bridge

A private WordPress inbox for Lakshmi's course conversations, downloadable Word transcripts and optional outbound integrations.

## Install and open

This module is bundled with the TechLeads IT Landing Pages plugin. After updating that plugin through WP Pusher, open **WordPress Admin → Conversation Bridge**. The tables are created automatically. No API key is needed for storage or downloads; Lakshmi continues using the existing AI settings.

Alternatively, upload the current Conversation Bridge plugin ZIP in **Plugins → Add Plugin → Upload Plugin**, then activate it. WordPress 6.4+, PHP 8.0+, MySQL/MariaDB and PHP ZIP are required. OpenSSL is required for encrypted connector credentials. Installing both copies does not create two inboxes; the first loaded copy owns the shared module.

## Conversations and downloads

- Search by visitor name, phone or email. Filter by course, language, review status, mobile captured, brochure requested, and an inclusive custom start/end date. Dates use the WordPress site timezone; timestamps are stored in UTC.
- The inbox shows each conversation's learning-goal summary. Open a row to see the transcript, visitor details, goal analysis, source and attribution. Mark a conversation New, Reviewed, Contacted or Archived.
- **Download DOCX** exports one conversation. **Download selected ZIP** exports up to 200 checked records from the current page. **Download filtered ZIP** exports every matching conversation, across pages, up to 2,000 records. Narrow the dates for larger inboxes.
- A ZIP contains one `.docx` per conversation and a CSV manifest. Exports are generated on demand in private temporary storage and cleaned up after streaming. They are not public WordPress media attachments. Bulk output is bounded to 128 MiB; individual document content to 4 MiB.

Capture starts after installation and the updated landing-page chatbot adapter is deployed. It saves accepted name/language and mobile steps, visitor questions, confirmed answers, generic failures, handoff and successful brochure-request activity. It stores the complete captured history independently of the shorter AI context. Past conversations that were never archived cannot be reconstructed. Visitors who leave before completing the initial name/language step will not yet have a record.

## Silent learning-goal analysis

Lakshmi never asks the visitor to select a learning goal. The AI's internal goal summary is saved when available. If it omits a goal, Bridge analyses the saved visitor questions locally as a fallback. Assistant replies, contact fields and language selections are not evidence of the visitor's purpose.

Digital Marketing sessions receive one of four statuses: **Stated in chat**, **Inferred from chat**, **Course interest**, or **More context needed**. Implied goals are labelled as likely. A fee-only question records interest in fees/payment options without inventing a job or business ambition; an introduction-only session records insufficient context. The summary updates as the conversation develops, with retractions respected. The detail screen shows a short redacted evidence excerpt when the transcript supports it.

On upgrade, bounded WordPress Cron batches analyse previously archived Digital Marketing sessions automatically. This backfill uses existing database transcripts; it makes no AI/API calls, sends no CRM deliveries, and does not change the conversation's activity timestamp or review status. WordPress Cron must run for historical backfill to finish. New captures are analysed immediately. The saved summary is also used in Word downloads, ZIP manifests and future integration payloads; Word documents include the analysis status and available evidence. Already sent or queued integration snapshots are unchanged.

## Integrations

Open **Conversation Bridge → Integrations**. Connections start disabled. Saving a connection does not forward existing records or create automatic deliveries.

- **TeleCRM:** reuses the Landing Pages plugin's existing account and mapping for Digital Marketing records. With the updated Landing Pages phone helper, it accepts supported international numbers in E.164 format and preserves their country code. Older Landing Pages versions retain the Indian-only mapping. An email-only record cannot use this adapter. Captured chat contact details are not treated as OTP-verified. Existing brochure submission behavior remains separate; avoid resending a brochure lead already submitted through that flow.
- **Custom API / webhook:** accepts a public HTTPS URL and optional Bearer token. Tokens are encrypted at rest using the site's authentication salt and never shown in the interface. Re-enter the token if WordPress authentication salts change. Redirects and private/local endpoints are rejected.
- Open a conversation and **Queue delivery**. Contact, course, language, goal, attribution and brochure status are sent. Full transcripts are included only when enabled in settings.
- WP-Cron processes queued jobs. Configure a server cron if the site has low traffic or WordPress Cron is disabled. The delivery status shows pending, sending, sent or failed. Sent jobs are not sent again. Failed jobs reuse the original payload snapshot and reference when manually retried. Check the receiving platform before retrying an unconfirmed response; external systems must implement idempotency to guarantee duplicate-free handling.
- Webhooks receive JSON `schema_version: 1` and an `Idempotency-Key` header. No AI keys, OTPs or session credentials are included. The AI model cannot enable connectors or dispatch data.

## Extend the bridge

Trusted server-side code can capture other chatbot/course events using:

```php
do_action('tlit_cb_capture', array(
    'session' => $private_session_id,
    'event_id' => $stable_unique_event_id,
    'course' => 'digital_marketing',
    'source_url' => home_url('/ai-powered-digital-marketing/'),
    'contact' => array('name' => $name, 'phone' => $phone, 'email' => $email),
    'language' => 'English',
    'goal' => $volunteered_goal,
    'stage' => 'question',
    'answer_count' => 1,
    'messages' => array(
        array('role' => 'visitor', 'type' => 'question', 'content' => $question),
        array('role' => 'assistant', 'type' => 'answer', 'content' => $answer),
    ),
));
```

Use a stable event identifier for retries. Supply a 48-character lowercase hexadecimal session identifier, which is hashed before storage. Raw transcripts are never accepted from an unauthenticated browser endpoint. Course keys, URLs, contact fields and supported attribution values are sanitized; blank later contact values do not overwrite captured details.

Register another manual destination with the `tlit_cb_connections` filter. Return an entry keyed by a short slug containing `label`, `enabled`, and a callable `send($payload, $idempotency_key)`. Return `array('success' => true, 'code' => 200)` only after confirmed acceptance. `tlit_cb_delivery_payload` lets trusted adapters adjust the payload. Keep authorization, retries and destination-specific mappings inside server code. There is no public export or public integration configuration API.

## Storage and administration

Four prefixed database tables hold conversations, messages, idempotency events and delivery snapshots. Administrator capability (`manage_options`) and nonces protect inbox changes, downloads and connector actions. Contact data is private database data, not separately encrypted; hosting/database backups should follow the site's access and retention policy. Connector secrets are encrypted separately. Labelled credentials and OTPs are redacted from transcripts.

Deactivation or removing the plugin preserves records. No automatic retention purge is configured. WordPress **Tools → Export/Erase Personal Data** supports records with a matching email address and removes pending delivery snapshots on erasure. Email is optional in chat, so email-only privacy requests cannot locate phone-only records; an administrator must identify those records separately. Already delivered data or previously downloaded files must be managed in the destination separately.

## Verification

PHP lint, the main Landing Pages mock runtime, provider/CRM contract checks, storage and export tests, actual WordPress/MariaDB transactions, timezone boundaries and private download access were tested during implementation. Tests use synthetic contacts and mocked provider/delivery calls; no real lead, SMS or live CRM request is needed.
