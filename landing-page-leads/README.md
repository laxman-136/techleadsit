# Landing Page Leads

All Digital Marketing page forms first save a durable record in this inbox. Storage does not depend on TeleCRM availability. The page variant, submission URL, form type, consent, verification state, answers, and attribution are retained. Qualified chatbot conversations use the same lead and sync workflow.

## Counsellor workflow

1. Open **Conversation Bridge → Landing Page Leads**.
2. Review the contact and source details, call the learner, and add a note.
3. Choose one of the **18 original lead statuses** and save the review. The dropdown uses the existing status labels, including demo and contact outcomes, potential levels, next batch, course or requirement changes, trainer enquiries, Enrolled and Lost. New and Junk Lead remain local. The existing landing-page TeleCRM connection syncs eligible reviews; no second credential setup is needed.
4. Later qualification or note changes update the same TeleCRM lead. Unchanged saves do not resend the same revision. **New** and **Junk lead** never send; setting an already synced lead New or Junk does not delete its existing CRM record.
   To update just the call notes, use **Save call notes & sync Remarks** below the notes box. This saves the text first, keeps the current saved qualification, and places the complete notes **first** in both TeleCRM `Remarks` and `remarks`, above the existing lead context and attribution. This makes notes visible in the collapsed Remarks preview. It uses the same delivery checks and duplicate prevention as review sync. Empty notes are rejected. For New and Junk Lead, the button reads **Save call notes** and keeps the notes in WordPress.
5. If delivery is uncertain, check the latest attempted qualification and notes in TeleCRM before selecting the reconciliation action. The presence of an older lead is not proof that the latest update arrived. A timeout never silently retries.

The review dropdown uses the same complete status list as reporting and filters. Each lead's saved status is selected, including New for a new lead; opening the page does not reclassify or sync it. Existing records and CRM mappings are preserved. An unrecognised saved status shows a required “Choose qualification” placeholder. Existing saved keys retain their meanings:

The updated review field shows **18 lead statuses available** below the dropdown. A screen still showing only Junk, Nurture, Warm, Hot and Quality is using the earlier interface; updating the GitHub source alone does not verify that the installed WordPress plugin has been updated.

| Existing saved key | TeleCRM `status` |
| --- | --- |
| `potential` | `50 % Potential` |
| `high_potential` | `60-80 Potential` |
| `intended_to_join` | `Potential Lead 100` |
| `enrolled` | `Enrolled` |

TeleCRM enrollment dates and payment fields remain manual. Syncing the `Enrolled` status does not create a payment record.

## Sync feedback

The lead review form progressively enhances to an authenticated, nonce-protected WordPress AJAX request. It uses the same review service as the original form action; the normal form still works when JavaScript is unavailable. The clicked notes-only action is captured before submit buttons are disabled. A second click cannot send a second in-flight request.

During a qualifying sync, a shaded SVG rocket with an engine plume appears in the current light or dark theme. A drawn success mark appears only when the latest saved payload has been accepted by TeleCRM. New/Junk saves say **Saved in Bridge**. Failures, stale notes and uncertain responses have distinct messages and never show the TeleCRM success animation. Network interruptions do not automatically retry. Reduced-motion preferences remove the animation while preserving the status text.

Previously confirmed payloads using the older Remarks layout remain recognised. A later notes edit cannot inherit that old confirmation. The new notes-first layout takes effect on the next counsellor-controlled sync; enabling the interface does not resend historical leads.

## Existing connection

The sole connection source is the site's existing Digital Marketing TeleCRM integration (`tlit_dm_crm_url()` and its configured server-side authentication). The duplicate Lead integrations form and credential fields have been removed. Legacy Bridge enable/reuse flags do not prevent qualified reviews from syncing. If the existing connection is unavailable, the review remains saved and the inbox reports the connection problem.

Mandatory contact and attribution fields are included on first sync. `Remarks` and `remarks` include page version, source URL, form origin, qualification, learning goal and counsellor notes; brochure leads carry the brochure-download remark. Later status updates do not overwrite enriched CRM contact and attribution fields with blank defaults. TeleCRM's unique identifier for this account is phone; changing a previously synced phone requires manual resolution rather than silently creating a second CRM lead.

## Times and date filters

Received times, sync times, date filters and CSV downloads use **IST (Asia/Kolkata)** independently of WordPress settings. A selected date includes midnight through the end of that IST day. Existing UTC database timestamps are converted for display and are not rewritten.

## Adapter API

`TLIT_LP_Leads::ingest($payload, $context)` is server-side only. The caller validates its OTP/consent policy before invoking it. A stable `request_id` is mandatory. A successful result contains `lead_id`, `receipt` and `duplicate`; a database error returns `WP_Error` and must prevent a success screen.

After a new insert, `tlit_lp_lead_created` receives the saved ID. Teams notifications only enqueue work from this hook; failures never turn a durable enquiry into an unsuccessful form response. The original receipt ID is retained even when a listener inserts its own outbox record. An enclosing chatbot transaction can still roll back the enqueue; delivery runs later, after commit.

`TLIT_LP_Leads::update_details($lead_id, $updates)` stores verified follow-up details and brochure activity without a CRM request. The calling adapter must authorize the receipt before using it.

The existing `/tlit-leads/v1/otp` and `/submit` routes preserve V2/V3 phone verification and response contracts. Existing saved V2 receipts remain replayable after the schema upgrade.

## Verification

`php landing-page-leads/tests/status-options-test.php` verifies every rendered option, saved selection and exact TeleCRM payload label offline. GitHub runs this check on every push. It builds payloads without calling any sync or HTTP method.

For local database save/readback without attempting delivery, run:

```
php landing-page-leads/tests/status-options-wordpress-test.php /path/to/isolated-wordpress-bootstrap.php
```

This test requires localhost with external HTTP blocked, loads the current checkout, saves all 18 statuses with delivery explicitly disabled, checks their notes and history, and removes its local fixture. It rejects any network request; it does not test live TeleCRM acceptance.

Run against an isolated localhost WordPress database with `WP_HTTP_BLOCK_EXTERNAL` enabled:

```
php landing-page-leads/tests/workflow-test.php /path/to/isolated-wordpress/wp-load.php
```

The suite uses real database storage with every HTTP request intercepted. It checks durable capture, idempotency, existing-form OTP, filters, reuse of the existing connection, mandatory CRM fields, qualification gating, status revisions, repeated-save prevention, timeout reconciliation and failed-save handling. Test leads are deleted afterward. Mocked tests do not establish live TeleCRM authentication or legacy-endpoint acceptance of native statuses.
