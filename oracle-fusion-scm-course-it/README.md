# Oracle Fusion SCM course landing page

Public route: /oracle-fusion-scm-course-it (with or without a trailing slash).
The event remains at /scm-batch-80 with its original event design, December 12 schedule and n8n Sheet registration.

In WordPress, open **Landing Page Settings → Oracle Fusion SCM → SCM landing page demo details**.
Set the free demo date/time, regular batch start date/time (both IST), delivery label and HTTPS joining link, then save.
The saved values update the course page, countdown, popup description and confirmation link. They do not change the event page.
Existing saved SCM demo and batch dates are preserved.

## Consultant responsibilities and career chart

The section above the modules explains five functional consulting responsibilities and a purchase-approval example, based on the course brochure.
The brochure section includes a salary-range graph from fresher/associate to practice head/director, on a shared 0–70 lakh annual CTC scale.
The ladder rises visually from the fresher role at the bottom-left to practice leadership at the top-right; the accessible list follows the same displayed order.
The six planning bands are ₹5–8, ₹8–12, ₹12–20, ₹20–30, ₹30–45 and ₹40–65+ LPA. They are explicitly illustrative targets, not measured averages or guaranteed salaries. Fresher offers may be lower; roles and experience bands vary by employer.

Public references reviewed on 24 November 2026 are linked in the chart's expandable reference note:

- Glassdoor reported Oracle SCM consultant salaries, which include wide variation by company and experience.
- WEN recruiter listings, including Oracle Fusion SCM Procurement/Inventory/PIM/Costing roles at ₹15–18 lakh for 4–8 years.
- Cutshort advertised Oracle Fusion SCM consulting roles, including a ₹30–35 lakh role for 8–16 years.
- Glassdoor Oracle consulting practice director reports, used only as a wider Oracle leadership benchmark, not an SCM-specific average.

The requested ₹5–8 LPA fresher band is an entry-level target. The chart does not present it as a minimum market offer or a placement outcome.

The inline and popup forms use the existing same-origin Conversion Bridge route, tlit-leads/v1/scm-registration.
Demo confirmation and brochure downloads require a matching receipt. Leads are attributed to the new course URL.

## India-only SMS verification

Only this course page uses the dedicated `tlit-leads/v1/scm-otp` adapter. Indian +91 numbers must complete a fourth SMS verification step before demo or either brochure submission. International numbers retain three steps and submit directly with `otp_verified=false` and an explicit international verification note.

The server derives the policy from the canonical phone prefix; client flags and legacy course OTP switches cannot bypass verification for +91 numbers. The existing VISPL sender is reused. Codes expire after five minutes, allow five failed attempts, and have a 30-second resend cooldown plus the existing shared SMS limits. Challenges are bound to this page, phone, registration ID, intent and inline/popup placement. A resend invalidates the old challenge. Exact retries of the same request remain idempotent after a lost response or storage failure.

The SCM event page and other course forms retain their own existing verification policies. Tests mock SMS dispatch; they never send a real code.

## Form presentation

The inline and popup enquiries share a restrained form layout, responsive phone row, named progress stages, inline validation and explicit loading/error/success states. Popup actions remain visible while scrolling longer screens.

`form-controls.js` enhances native selects into accessible select-only comboboxes. Arrow keys, Home/End, Enter/Space, Escape, Tab and typeahead are supported; selected values and validation remain attached to the original form controls. Menus open above or below according to available room. Native selects remain available if the enhancement fails to load. The question values and India-only OTP policy are unchanged.

Local checks (all registrations mocked):

- C:/xampp/php/php.exe -l techleadsit.php
- C:/xampp/php/php.exe run_test.php
- C:/xampp/php/php.exe landing-page-leads/tests/scm-otp-test.php
- node oracle-fusion-scm-course-it/tests/routing.cjs
- node oracle-fusion-scm-course-it/tests/forms.cjs
- node oracle-fusion-scm-live-demo/tests/forms.cjs

Set SCM_WORDPRESS_RENDER=1 when running the course form test to verify the rendered WordPress settings with mocked registration responses.

Deploy the complete plugin together, including the new folder, routing and lead handler changes.
