# Tech Leads IT Conversation Bridge

A private WordPress inbox for Lakshmi's course conversations, downloadable Word transcripts and optional outbound integrations.

## Install and open

This module is bundled with the TechLeads IT Landing Pages plugin. After updating that plugin through WP Pusher, open **WordPress Admin → Conversation Bridge**. The tables are created automatically. No API key is needed for storage or downloads; Lakshmi continues using the existing AI settings.

Alternatively, upload the current Conversation Bridge plugin ZIP in **Plugins → Add Plugin → Upload Plugin**, then activate it. WordPress 6.4+, PHP 8.0+, MySQL/MariaDB and PHP ZIP are required. OpenSSL is required for encrypted connector credentials. Installing both copies does not create two inboxes; the first loaded copy owns the shared module.

## Conversations and downloads

- Search by visitor name, phone or email. Filter by course, language, review status, mobile captured, brochure requested, and an inclusive custom start/end date. Display times, date filters and downloads use IST (Asia/Kolkata), regardless of the WordPress site timezone; database timestamps remain in UTC.
- The inbox shows each conversation's learning-goal summary. Open a row to see the transcript, visitor details, goal analysis, source and attribution. Mark a conversation New, Reviewed, Contacted or Archived.
- **Download DOCX** exports one conversation. **Download selected ZIP** exports up to 200 checked records from the current page. **Download filtered ZIP** exports every matching conversation, across pages, up to 2,000 records. Narrow the dates for larger inboxes.
- A ZIP contains one `.docx` per conversation and a CSV manifest. Exports are generated on demand in private temporary storage and cleaned up after streaming. They are not public WordPress media attachments. Bulk output is bounded to 128 MiB; individual document content to 4 MiB.

Capture starts after installation and the updated landing-page chatbot adapter is deployed. It saves accepted name/language and mobile steps, visitor questions, confirmed answers, generic failures, handoff and successful brochure-request activity. It stores the complete captured history independently of the shorter AI context. Past conversations that were never archived cannot be reconstructed. Visitors who leave before completing the initial name/language step will not yet have a record.

## Silent learning-goal analysis

Lakshmi never asks the visitor to select a learning goal. The AI's internal goal summary is saved when available. If it omits a goal, Bridge analyses the saved visitor questions locally as a fallback. Assistant replies, contact fields and language selections are not evidence of the visitor's purpose.

Digital Marketing sessions receive one of four statuses: **Stated in chat**, **Inferred from chat**, **Course interest**, or **More context needed**. Implied goals are labelled as likely. A fee-only question records interest in fees/payment options without inventing a job or business ambition; an introduction-only session records insufficient context. The summary updates as the conversation develops, with retractions respected. The detail screen shows a short redacted evidence excerpt when the transcript supports it.

On upgrade, bounded WordPress Cron batches analyse previously archived Digital Marketing sessions automatically. This backfill uses existing database transcripts; it makes no AI/API calls, sends no CRM deliveries, and does not change the conversation's activity timestamp or review status. WordPress Cron must run for historical backfill to finish. New captures are analysed immediately. The saved summary is also used in Word downloads, ZIP manifests and future integration payloads; Word documents include the analysis status and available evidence. Already sent or queued integration snapshots are unchanged.

## Counsellor qualification and TeleCRM

Digital Marketing conversations have a **Lead qualification** panel, separate from the conversation's New / Reviewed / Contacted / Archived review status. The dropdown uses exact active TeleCRM status labels, with **New** and **Junk Lead** held locally. Select a status and save. New/Junk stays in WordPress. Saving any other status uses the existing Landing Page Settings TeleCRM account through the common lead service; no second API key or TeleCRM enable switch is required in the Bridge.

A valid captured phone is required before qualification. Email remains optional for ordinary chatbot enquiries. Incomplete chats can still be marked New or Junk. The panel links to the canonical Landing Page Leads record and shows its delivery result. Later qualified status changes sync the same lead; unconfirmed responses require reconciliation instead of blind retries. Marking an already-sent lead Junk stops further automatic sending but does not delete it from TeleCRM.

The first qualification or linked chatbot brochure request establishes one canonical lead. Subsequent brochure requests and reviews use the same server-resolved conversation identity. Captured goal/contact/attribution updates refresh that local record without sending to TeleCRM. Brochure downloads keep their existing verification policy and receipt proof. Legacy manual TeleCRM queue jobs cannot bypass this workflow.

## Other integrations

The **Integrations** tab is intentionally blank, reserved for the next design. It displays no TeleCRM, API or webhook setup. Existing connection records are preserved; automatic TeleCRM sync continues through counsellor review.

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
