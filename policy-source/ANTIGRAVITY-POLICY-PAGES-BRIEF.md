# Antigravity Brief — Tech Leads IT Landing-Page Policy Pages

## Objective

Create three fast, mobile-friendly, crawlable policy pages on `lp.techleadsit.com` and link them from every landing-page footer and every lead form. These pages support user trust, Google Ads destination transparency and clear handling of personal data, course terms and the seven-day refund policy.

## Source content files

Use the copy exactly as supplied in:

1. `privacy-policy.md`
2. `terms-and-conditions.md`
3. `refund-cancellation-policy.md`

Before publishing, replace every bracketed placeholder.

## Required routes

Create these canonical URLs:

- `https://lp.techleadsit.com/privacy-policy/`
- `https://lp.techleadsit.com/terms-and-conditions/`
- `https://lp.techleadsit.com/refund-cancellation-policy/`

Each page must return HTTP 200 and be accessible to normal browsers, `AdsBot-Google` and `AdsBot-Google-Mobile`.

## Required business details

Use these confirmed details:

- Legal name: **Tech Leads IT Solutions Private Limited**
- Email: **info@techleadsit.com**
- Phone: **+91 81253 23232**

Obtain and insert before publishing:

- Registered-office address
- Privacy/grievance contact name or job designation

Do not publish `[INSERT ...]` placeholders.

## Page layout

Use the existing landing-page brand system, but keep policy pages restrained and readable:

- Existing Tech Leads IT logo linked to the appropriate landing-page home.
- H1 followed by effective and last-updated dates.
- Maximum readable content width around 800–900 px.
- Clear H2 section hierarchy.
- Body text at least 16 px with comfortable line height.
- Sticky navigation is unnecessary.
- Provide a compact table of contents on longer pages.
- Avoid promotional pop-ups, countdowns, fake activity notifications and distracting animations.
- Include the standard footer described below.

## Footer required on every landing page

Add visible text links:

- Privacy Policy
- Terms & Conditions
- Refund & Cancellation Policy
- Contact Us

Recommended footer order:

`Privacy Policy | Terms & Conditions | Refund & Cancellation Policy | Contact Us`

Also display:

- `© 2026 Tech Leads IT Solutions Private Limited. All rights reserved.`
- `info@techleadsit.com`
- `+91 81253 23232`

Use standard HTML anchor elements. Do not render policy links through JavaScript-only click handlers.

## Form disclosure

Place this directly below the Submit button or immediately above it:

> By submitting this form, you agree to our Privacy Policy and consent to being contacted by Tech Leads IT by phone, WhatsApp or email regarding this enquiry.

Link `Privacy Policy` to the landing-page privacy-policy route.

If promotional messages unrelated to the specific enquiry will be sent, add a separate optional unchecked checkbox:

> I would like to receive course updates and promotional messages. I can opt out at any time.

Do not bundle optional marketing consent into a required checkbox.

## Cross-links

- Privacy Policy should link to Terms & Conditions and Refund & Cancellation Policy in the footer.
- Terms & Conditions should link to Privacy Policy and Refund & Cancellation Policy where referenced.
- Refund & Cancellation Policy should link to Terms & Conditions and Privacy Policy in the footer.
- External legal/policy links should not replace the three local policy routes.

## SEO and crawler requirements

For each policy page:

- Return a stable HTTP 200 response.
- Include a self-referencing canonical URL.
- Include a unique title and meta description.
- Permit crawling in `robots.txt`.
- Do not block Google AdsBot by firewall, CDN, country rule, bot-protection challenge or WordPress security plug-in.
- Do not require login, cookies or JavaScript to read the policy text.
- Include the pages in the XML sitemap.

Suggested titles:

- `Privacy Policy | Tech Leads IT`
- `Terms and Conditions | Tech Leads IT`
- `Refund and Cancellation Policy | Tech Leads IT`

Suggested meta descriptions:

- `Learn how Tech Leads IT collects, uses and protects information submitted through its websites, course enquiries and learning services.`
- `Read the terms governing Tech Leads IT course enquiries, enrolment, training access, learner responsibilities and service use.`
- `Read the Tech Leads IT seven-day course refund and cancellation process, eligibility requirements and processing timelines.`

## Offer consistency requirements

Use the same terms across policy pages, landing pages, checkout and enrolment confirmation:

- Practice-instance access: **6 months from course commencement**.
- LMS and course-recording access: **2 years from activation**.
- Additional live batches for the same course: **up to 1 year, subject to availability**.
- Refund request window: **7 calendar days from enrolment date**.
- Refund review: **within 5 business days**.
- Approved refund initiation: **within 10 business days**.

Remove or correct conflicting statements elsewhere, including any older Terms page saying video access is only 12 months if the actual current offer is two years.

## Tracking guardrails

- Policy-page creation must not change the lead-form success logic.
- Continue firing `form_submitted` only after successful CRM acceptance.
- Continue using the canonical `generate_lead` event.
- Do not fire `generate_lead` merely because a policy link was clicked.
- Policy-page visits are ordinary page views, not conversions.
- Preserve UTM and Google click identifiers when a user visits a policy page and returns to the form.

## QA checklist

- [ ] All three URLs return HTTP 200.
- [ ] No bracketed placeholders remain.
- [ ] Legal name, email and phone are consistent.
- [ ] Registered-office address is present.
- [ ] Privacy/grievance contact is present.
- [ ] Seven-day refund wording is identical across relevant pages.
- [ ] Policy links are visible in every landing-page footer.
- [ ] Privacy Policy is linked beside every lead form.
- [ ] Links work on mobile and desktop.
- [ ] Pages render without JavaScript.
- [ ] `robots.txt` permits crawling.
- [ ] Google AdsBot user agents receive HTTP 200 without a challenge.
- [ ] Pages are included in the sitemap.
- [ ] Noindex is not required; use normal indexable pages unless there is a documented SEO reason otherwise.
- [ ] No analytics event is counted as a lead merely from viewing a policy page.

## Legal review note

The supplied content is a practical website draft, not a substitute for advice from qualified Indian legal counsel. Before publishing, the business should verify the registered-office details, refund-operation rules, consumer-law obligations, data-retention practices and third-party processors against actual operations.