# Landing Page Leads

All Digital Marketing page forms first save a durable record in this inbox. Storage does not depend on TeleCRM availability. The page variant, submission URL, form type, consent, verification state, answers, and attribution are retained. Qualified chatbot conversations use the same lead and sync workflow.

## Counsellor workflow

1. Open **Conversation Bridge → Landing Page Leads**.
2. Review the contact and source details, call the learner, and add a note.
3. Choose from the exact TeleCRM status labels and save the review. The existing landing-page TeleCRM connection automatically syncs the lead after the review is saved; there is no second credential setup or enable switch.
4. Later qualification or note changes update the same TeleCRM lead. Unchanged saves do not resend the same revision. **New** and **Junk lead** never send; setting an already synced lead New or Junk does not delete its existing CRM record.
5. If delivery is uncertain, check the latest attempted qualification and notes in TeleCRM before selecting the reconciliation action. The presence of an older lead is not proof that the latest update arrived. A timeout never silently retries.

All active TeleCRM statuses are available in the counsellor dropdown, except Fresh is represented by the local New state. New and Junk Lead remain local; every other status syncs when saved. Existing saved keys retain their meanings:

| Existing saved key | TeleCRM `status` |
| --- | --- |
| `potential` | `50 % Potential` |
| `high_potential` | `60-80 Potential` |
| `intended_to_join` | `Potential Lead 100` |
| `enrolled` | `Enrolled` |

TeleCRM enrollment dates and payment fields remain manual. Syncing the `Enrolled` status does not create a payment record.

## Existing connection

The sole connection source is the site's existing Digital Marketing TeleCRM integration (`tlit_dm_crm_url()` and its configured server-side authentication). The duplicate Lead integrations form and credential fields have been removed. Legacy Bridge enable/reuse flags do not prevent qualified reviews from syncing. If the existing connection is unavailable, the review remains saved and the inbox reports the connection problem.

Mandatory contact and attribution fields are included on first sync. `Remarks` and `remarks` include page version, source URL, form origin, qualification, learning goal and counsellor notes; brochure leads carry the brochure-download remark. Later status updates do not overwrite enriched CRM contact and attribution fields with blank defaults. TeleCRM's unique identifier for this account is phone; changing a previously synced phone requires manual resolution rather than silently creating a second CRM lead.

## Times and date filters

Received times, sync times, date filters and CSV downloads use **IST (Asia/Kolkata)** independently of WordPress settings. A selected date includes midnight through the end of that IST day. Existing UTC database timestamps are converted for display and are not rewritten.

## Adapter API

`TLIT_LP_Leads::ingest($payload, $context)` is server-side only. The caller validates its OTP/consent policy before invoking it. A stable `request_id` is mandatory. A successful result contains `lead_id`, `receipt` and `duplicate`; a database error returns `WP_Error` and must prevent a success screen.

`TLIT_LP_Leads::update_details($lead_id, $updates)` stores verified follow-up details and brochure activity without a CRM request. The calling adapter must authorize the receipt before using it.

The existing `/tlit-leads/v1/otp` and `/submit` routes preserve V2/V3 phone verification and response contracts. Existing saved V2 receipts remain replayable after the schema upgrade.

## Verification

Run against an isolated localhost WordPress database with `WP_HTTP_BLOCK_EXTERNAL` enabled:

```
php landing-page-leads/tests/workflow-test.php /path/to/isolated-wordpress/wp-load.php
```

The suite uses real database storage with every HTTP request intercepted. It checks durable capture, idempotency, existing-form OTP, filters, reuse of the existing connection, mandatory CRM fields, qualification gating, status revisions, repeated-save prevention, timeout reconciliation and failed-save handling. Test leads are deleted afterward. Mocked tests do not establish live TeleCRM authentication or legacy-endpoint acceptance of native statuses.
