# Microsoft Teams lead and form drop-off alerts

Available in TechLeadsIT Landing Pages 1.3.0 and Conversation Bridge 1.2.0. Update the WordPress plugin, create your Power Automate flow, then configure and test its webhook below. Notifications remain off until enabled; TeleCRM review stays separate.

## What the integration does

Website submission → WordPress Bridge saves the enquiry → notification queue → Power Automate → your Microsoft Teams group.

- Covers general forms, popup forms and brochure requests on Digital Marketing V1, V2 and V3.
- A form drop-off alert is queued once when the visitor saved both a name and valid mobile number, then made no progress for 3 minutes. Completing the form before that deadline cancels the alert.
- Chatbot alerts begin after a name and valid mobile number are saved. Email is optional.
- There are three operational notification types: qualified form drop-off (`FD-`), successful landing-page submission (`LP-`) and chatbot contact (`CB-`). The synthetic connection test is not a lead notification.
- One alert identity per form journey, form receipt or chatbot conversation. Repeated checks, submission retries, chat turns and a linked chatbot brochure request do not create extra alerts.
- A Teams alert does not qualify a lead. Your counsellor still reviews the lead in WordPress; New/Junk remain there and the other statuses sync to TeleCRM when saved.
- Existing historical leads are not forwarded when you enable the feature. New accepted captures start the queue.

## 1. Create the Power Automate workflow

Use **Microsoft Teams → Workflows** or [Power Automate](https://make.powerautomate.com/). Sign in with an account that belongs to the destination Teams group.

You can start with a webhook-to-chat template if your tenant offers one. For a flow you build yourself:

1. Create a cloud flow and select the Microsoft Teams trigger **When a Teams webhook request is received**.
2. Set **Who can trigger the flow?** to **Anyone**. WordPress calls this signed endpoint without a Microsoft OAuth bearer token. Keep the resulting URL private; treat it as an access secret. Tenant-only authentication requires an additional OAuth integration, which this feature does not implement.
3. Add an **Apply to each** control. In its input, use the **Expression** tab and enter:

   ```text
   triggerBody()?['attachments']
   ```

4. Inside the loop, add the Microsoft Teams action **Post card in a chat or channel**. Choose:
   - **Post as:** Flow bot, if available for your group and tenant.
   - **Post in:** Group chat.
   - **Group chat:** Your existing lead-response group. If the picker omits it, use its conversation ID as a custom value or follow Microsoft's group-chat setup instructions.
5. For the **Adaptive Card** content, use the Expression tab:

   ```text
   string(item()?['content'])
   ```

   If a template already has this attachment loop and posting action, configure those existing steps instead of adding another posting action.
6. Save the flow. Open the webhook trigger and copy its **HTTP POST URL** (the generated URL label can vary by designer).
7. Confirm the flow is enabled. Keep a suitable flow owner/co-owner so it can continue operating when an individual account changes.

Use **Post card**, rather than an action that waits for a card response. These alerts provide an ordinary link to the Bridge; they do not need an acknowledgement to complete the flow.

Microsoft's references:

- [Teams webhook trigger, request schema and authentication](https://learn.microsoft.com/en-us/connectors/teams/#when-a-teams-webhook-request-is-received)
- [Posting to a Teams channel or group chat](https://learn.microsoft.com/en-us/power-automate/teams/send-a-message-in-teams)

## 2. Configure WordPress locally

The settings require the PHP plugin running in local WordPress. A static HTML landing-page preview alone cannot display the Bridge admin screen.

1. Open **Conversation Bridge → Integrations** in your local WordPress admin.
2. Paste the HTTP POST URL into **Power Automate webhook URL**.
3. Leave automatic notifications off and select **Save notification settings**.
4. Click **Send test notification**. The alert contains only clearly labelled sample details, with an `example.invalid` email. It does not submit a visitor or call TeleCRM.
5. Confirm the test appears in your Teams group. If WordPress says **accepted**, also check the flow's run history: accepting the webhook and posting the Teams card are separate stages.
6. Enable **Teams notifications** and the sources you want: forms, chatbot, or both. Save again.
7. Submit a synthetic form, leave a second form incomplete after saving a name and mobile number, and capture a synthetic chatbot mobile number locally. Confirm the three alert types show the correct page version, enquiry source, campaign and IST time.

The signed URL is encrypted at rest and never displayed after saving. Leave the input blank to retain it. Microsoft cloud endpoints using `*.logic.azure.com` and `*.environment.api.powerplatform.com` are supported; localhost receivers, generic webhooks, embedded credentials and unrelated domains are rejected.

The Bridge link in a local test lead points to localhost and only works on the computer serving that WordPress instance. After deployment, new alerts link to the live private WordPress admin. The connection-test card omits a Bridge link.

## 3. Understand delivery status

| Status | Meaning / action |
| --- | --- |
| Queued | Waiting for WordPress Cron. |
| Sending | A worker has claimed the alert. Overlapping workers cannot send it again. |
| Accepted by Power Automate | HTTP 2xx from the webhook. Check Teams or the flow run history for actual posting. Accepted alerts are not resent. |
| Retry scheduled | HTTP 429 throttling. Automatic retries use delays of at least 1, 5 and 15 minutes and respect a bounded Retry-After value. |
| Action needed | A rejected request or exhausted throttle retries. Correct the flow/settings and use Retry alert. |
| Check flow before retrying | A timeout, 5xx or interrupted worker could have reached the flow. Check the notification ID in Teams and run history; retry only after confirming it was not posted. |
| Held | Notifications were disabled or the destination URL changed after this alert was queued. A reviewed manual retry authorizes the current saved endpoint. |
| Contact unavailable | The underlying record was erased or no longer has valid contact details. |

Power Automate does not automatically deduplicate requests using the `Idempotency-Key` header. The Bridge prevents duplicate local jobs and avoids blind retries after uncertain responses. The notification ID is also included in the card so a counsellor can check a specific delivery.

## 4. Keep the queue running

The plugin schedules an immediate asynchronous wake-up and a one-minute repair/sweep. WordPress Cron still needs to run. On the live host, configure a real one-minute cron for `wp-cron.php` if traffic is low or `DISABLE_WP_CRON` is enabled. The repair step can recover missed queue inserts for captures made after the feature was enabled; it does not run a historical-lead or historical-drop-off broadcast.

Notification failures do not turn a successfully saved lead into a failed enquiry. Outbox records store identifiers and delivery metadata, not conversation transcripts or copied contact fields. Chatbot personal-data erasure also removes its notification references; messages already posted to Teams must be managed in Teams separately.

## Troubleshooting

- **401/403:** Check that the trigger is set to Anyone, the whole generated URL was copied and the flow is enabled. This integration intentionally sends no Authorization header for an Anyone trigger.
- **Accepted, but no Teams message:** Inspect the flow run history, Teams connection, group membership, attachment loop and Adaptive Card expression.
- **Trigger/action missing:** Check that the Workflows app is allowed by your Teams administrator and the connector is available for your tenant.
- **Queued for too long:** Check WordPress Cron and the one-minute server schedule.
- **Webhook URL rotated:** Save the new URL. Existing queued alerts for the old URL are held for review before retrying.
- **Salt changed:** Re-enter the URL; encrypted settings use the WordPress authentication salt.

## Local verification

```text
php conversation-bridge/tests/teams-wordpress-test.php /path/to/isolated-wordpress/wp-load.php
```

Requires an isolated localhost WordPress/MariaDB installation with `WP_HTTP_BLOCK_EXTERNAL` enabled and the updated runtime plugin copies. The suite mocks every Teams and CRM HTTP request, covers all three notification types and all three page variants, and removes its disposable test records. A real Teams post still requires your saved workflow URL and an explicit connection test.
