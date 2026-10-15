# AI Powered Digital Marketing Course — V2 release

After updating the existing TechLeadsIT Landing Pages plugin through WP Pusher:

1. Visit WordPress admin once. This registers the ordinary Pages entry **AI Powered Digital Marketing Course** if the slug is not already used.
2. Open `/ai-powered-digital-marketing-course/` and `/ai-powered-digital-marketing-course/thank-you/`.
3. The page appears under **Landing Page Settings → Digital Marketing**, alongside the original page. The existing `/ai-powered-digital-marketing/` route is unchanged.
4. Open **Conversation Bridge → Landing Page Leads** for the new form inbox. Add your TeleCRM enterprise ID and bearer API token in its integration panel; add account-specific field mappings if needed. Nothing is sent to TeleCRM automatically. Review the lead, save counsellor notes, and choose **Push lead to TeleCRM** when ready.
5. Test real OTP delivery with a phone number you control. The main and popup forms call the existing `techleadsit_send_vispl_sms` service. The brochure form does not request OTP. No local test SMS adapter or test database is included in this release.

New forms save first to the WordPress lead inbox. They retain source (`general`, `popup`, `brochure`), UTM/click attribution, consent, answers and verification state. The main and popup flows enforce OTP server-side. The inbox includes date/source/status/search filters and individual/filtered CSV downloads.

Page assets include the V2 design, FAQ and final CTA motion, compact footer disclaimer, new step forms and thank-you page. Production endpoint configuration is injected by WordPress. Existing course AI/chat and analytics settings are reused; no API keys are included in frontend code.

Verified before push: PHP lint, WordPress mock runtime and course registry, rendered production asset/config rewriting, Google/Facebook CRM schema dry-runs. The form, OTP, storage and manual CRM workflows were tested locally; no live SMS or TeleCRM delivery has been verified by this release operation.
