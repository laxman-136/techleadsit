# Lead operations dashboard

Open **Conversation Bridge → Dashboard**. The dashboard combines captured Digital Marketing form leads and chatbot contacts. Anonymous chats remain in Conversations. Linked chat-to-form handoffs count once, under the CB reference; repeat independent enquiries remain separate.

## Ownership and PIN access

Add counsellor names under **Your counsellor team**, one name per line. Administrators can open **Manage lead** to assign, reassign or unassign a lead. The saved record includes the owner, assigning person and IST time. A PIN user can explicitly select their name and **Take this lead** while it is unassigned. A competing or stale claim cannot replace another owner. Administrators can reassign from the dashboard.

PIN access and ownership are independent. A successful authenticated lead-page opening records the first and latest PIN-session opening and the session count. A refresh of the same session does not add another view. Failed codes, the PIN gate and link previews do not mark a lead opened. WordPress admin views are not counted as team PIN views. Shared PIN access cannot verify which individual viewed the lead; a selected counsellor name is explicitly labelled self-reported.

Tracking starts on installation. Earlier lead records show **Earlier views unknown** until their first tracked PIN opening. Prior views cannot be reconstructed. Reassignment and opening do not alter capture timestamps, qualification or TeleCRM state.

## Reporting

Filters: IST date range, contact/reference search, V1/V2/V3, general/popup/brochure/chatbot capture source, recorded traffic attribution, exact UTM campaign, assigned counsellor/unassigned, PIN access, qualification and CRM state. Counts and source breakdowns follow these filters. Dates use Asia/Kolkata, including inclusive end dates. Missing attribution is labelled **Direct / attribution unavailable**; Meta attribution is not presented as proven paid advertising.

**Download filtered CSV** exports contact, source, ownership, PIN access and sync fields for the matching records. Exports are administrator-only and spreadsheet formulas are escaped. Activity records show captured contact, PIN session openings, assignment changes, new counsellor reviews, Power Automate delivery results and the CRM result after review. Historical records retain their capture time and existing review history; activity from before installation is not fabricated. The activity view shows the latest 100 events for one lead or 20 recent events across the filtered inbox.

Power Automate HTTP acceptance is labelled acceptance, not confirmed Teams delivery. Check the flow history for its Teams posting result.

## CRM boundary and privacy

This dashboard has no CRM-send action. Only the existing counsellor-review handlers can qualify and send a lead. New/Junk remain local. Assignment and PIN tracking make no HTTP calls. Existing qualified review status mappings and payload fields are unchanged.

New private tables: `tlit_cb_lead_ops` (ownership/view counters) and `tlit_cb_lead_activity` (audit history), using the site's table prefix. No raw PIN, bearer URL, session cookie, API key or visitor transcript is stored in these tables. Conversation privacy erasure removes its corresponding operations and activity records within the existing privacy transaction.

## Verification

Run with an isolated localhost WordPress installation with outbound HTTP blocked:

```text
php conversation-bridge/tests/dashboard-wordpress-test.php /path/to/isolated-bootstrap.php
php conversation-bridge/tests/team-access-wordpress-test.php /path/to/isolated-wordpress/wp-load.php
```

The dashboard suite checks all three form/page sources, canonical chat deduplication, IST boundaries, prepared filters, historical unknown views, PIN view deduplication, CSRF/scoped claims, competing and stale claims, explicit reassignment, audit history and the review-only TeleCRM boundary.
