# Oracle Fusion Financials event

Preview: http://localhost:8768/ — run `node preview-server.cjs` in this folder.
Demo: Monday, 30 November 2026, 8:00 PM IST. Batch: 1 December 2026, 8:00 PM IST.
Trainer: Kaleem Basha, 22 years. Zoom ID: 8125668899. Password: techleads.

UI rebuilt from the supplied starter HTML, CSS tokens and JSON report after design feedback. The supplied token file is loaded unchanged. The old styles.css and theme-ng.css are no longer loaded. Fira Sans/Figtree, a 1120px container, oversized centered headline, three-column cards, black/white surfaces and the report's #B8EF43 accent define the design. Financials copy, portrait, poster, brochure and registration behavior are retained. financials.css is the active stylesheet; reference-design.css is its source snapshot. Do not rerun build-financials-event.py: it generates the superseded design.

All marketing buttons open the registration dialog. Brochure buttons use the same form and download the supplied PDF after a successful save. The inline form also works. FAQ disclosures and dialog close remain normal controls.

Local submissions save to `D:/wordpress/artifacts/financials-event-preview/` with UUID filenames and retry deduplication. They are not sent to production, email, Sheets or a CRM. Test submissions use example.invalid addresses. The server binds only to loopback and does not serve saved records.

The local WordPress registry and route include `/oracle-fusion-financials-live-demo/`. Production submission remains disabled until a dedicated endpoint is supplied via the `techleadsit_financials_event_registration_endpoint` filter (or `window.FINANCIALS_REGISTRATION_ENDPOINT` for a standalone installation). The endpoint must validate and persist the submission, then return `{success:true, registrationId:<submitted UUID>}`. No deployment has been performed. Event dates are page-specific, matching the standalone event architecture; they do not inherit the evergreen course countdown.

Source precedence: current event poster for dates, trainer, experience, meeting details and displayed Google rating; brochure for curriculum and attributed learner-feedback summaries. The brochure names an earlier trainer and includes older commercial terms, so the page does not advertise its fee, duration or salary figures. The PDF is provided unchanged.

Validation: all CTA dialog openings and Escape dismissal; desktop/tablet/mobile widths 1440, 768, 390, 320; image loading and horizontal overflow; inline and popup saves; failed-submit recovery; gated PDF download. PHP syntax and local WordPress registry runtime checks pass. Admin UI and production lead routing are not verified or deployed.

Copy rationale: lead with the reader’s existing finance knowledge, explain tangible module connections, then reduce decision friction with a free demo and direct trainer access. No placement guarantee or fabricated scarcity.
Alternative headline: “Take your finance knowledge into Oracle Fusion.”
Alternative headline: “Your finance experience. A new direction in Oracle Fusion.”
Alternative CTA: “Join the Free Financials Demo”.
