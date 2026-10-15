# Landing Page Leads

All Digital Marketing page forms first save a durable record in this inbox. Storage is independent of TeleCRM settings. The page variant, submission URL, form type, consent, verification state, answers, and attribution are retained.

## Counsellor workflow

1. Open **Conversation Bridge → Landing Page Leads**.
2. Review the contact and source details, call the learner, and add a note.
3. Save a qualification: **Potential**, **High potential**, **Intended to join**, or **Enrolled**. An enabled TeleCRM connection sends this lead once after the review is saved.
4. **New** and **Junk lead** never send. Changing the status of a successfully sent lead does not create another delivery.
5. If delivery is uncertain, check TeleCRM before selecting the reconciliation action. A timeout never silently retries. Confirming the lead is absent allows an explicit subsequent qualification save to retry.

TeleCRM enrollment dates and payment fields are not populated by this workflow. The `Enrolled` inbox qualification is a counsellor label, not a payment record.

## Connection settings

Enter the TeleCRM enterprise ID and bearer token and enable the connection, or explicitly reuse the site's existing Digital Marketing connection. Tokens are encrypted with the site's WordPress salt, never returned to the browser, and preserved when the password field is left blank. The configuration check validates local setup only; it does not send a synthetic lead or claim to verify provider authentication.

Custom fields may map to listed capture attributes. Mandatory contact and attribution fields are protected. `Remarks` includes page version, source URL, form origin, qualification, learning goal and counsellor notes; brochure leads carry the brochure-download remark.

## Adapter API

`TLIT_LP_Leads::ingest($payload, $context)` is server-side only. The caller validates its OTP/consent policy before invoking it. A stable `request_id` is mandatory. A successful result contains `lead_id`, `receipt` and `duplicate`; a database error returns `WP_Error` and must prevent a success screen.

`TLIT_LP_Leads::update_details($lead_id, $updates)` stores verified follow-up details and brochure activity without a CRM request. The calling adapter must authorize the receipt before using it.

The existing `/tlit-leads/v1/otp` and `/submit` routes preserve V2/V3 phone verification and response contracts. Existing saved V2 receipts remain replayable after the schema upgrade.

## Verification

Run against an isolated localhost WordPress database with `WP_HTTP_BLOCK_EXTERNAL` enabled:

```
php landing-page-leads/tests/workflow-test.php /path/to/isolated-wordpress/wp-load.php
```

The suite uses real database storage with every HTTP request intercepted. It checks durable capture, idempotency, existing-form OTP, filters, encrypted settings, mandatory CRM fields, qualification gating, repeated delivery prevention, timeout reconciliation and failed-save handling. Test leads are deleted afterward.
