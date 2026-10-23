# Oracle Fusion SCM live demo

Standalone page for 31 October 2026, 8:00 AM IST. Open index.html or serve this folder with a static HTTP server. It is separate from the digital marketing pages.

All page CTAs open a native accessible dialog with name, email, mobile number, the requested country-code list (India default, plus a custom field for Other), and the five exact career-objective choices. Escape, close button, outside click, focus restoration, and native required-field validation are supported.

Both the inline and popup forms submit to the dedicated n8n workflow `OJ6rWvTcsaH2SNVg` at `https://n8n.techleadsit.com/webhook/scm-batch-80-registration`. The sole spreadsheet destination is the user-provided Google Sheet `1wSL-AyX-nsetyp3SzLnJ8JxQnF7k7qyUN1njU1ENR-o`, tab `Sheet1`, columns Name, Email, Mobile, Career objective. The workflow validates fields, combines the country code and national number, writes RAW strings to preserve the plus sign and avoid formula interpretation, and upserts by normalized email to avoid duplicate retry rows. It returns success only after the save succeeds.

No Conversion/Conversation Bridge, TeleCRM, WordPress lead storage, analytics, or browser storage is used by this page. The isolated route in techleadsit.php bypasses shared landing-page scripts. n8n processes requests using its Google connection; no credentials are exposed in the frontend. Do not activate this integration until the destination write and both forms have been verified. The backend workflow remains managed in n8n.

Client validation, a honeypot, disabled submit while saving, a 30-second timeout and a retry message cover submission errors. The form does not claim success on a network or storage failure. No confirmation email or WhatsApp message is sent by this workflow.

The three user-supplied images are under assets. The logo is reused from the existing SCM page. Demo topics are conservative introductory copy based on the supplied poster; confirm the detailed agenda before launch.
