# Oracle Fusion Financials live event

Live: https://lp.techleadsit.com/oracle-fusion-financials-live-demo/
Demo: 30 November 2026, 8:00 PM IST. Batch: 1 December 2026, 8:00 PM IST.
Trainer: Kaleem Basha, 22 years. Zoom: 8125668899, password techleads.

Design follows the supplied starter HTML, CSS tokens and report: Fira Sans/Figtree, 1120px container, centered large hero, black/white sections, #B8EF43 accent. Old event theme stylesheets are not loaded.

Both inline and popup forms post to https://n8n.techleadsit.com/webhook/financials-demo-registration . The workflow validates the submission and saves to the Financials tab (gid 20261130) in spreadsheet 1wSL-AyX-nsetyp3SzLnJ8JxQnF7k7qyUN1njU1ENR-o. Columns: Name, Email, Mobile, Career objective, Timestamp (IST). It uses the same spreadsheet as SCM, HCM and Technical. No CRM or email notification is configured.

Workflow: UtX7nNfCvqa9z6hd, in Subramanyam Aki personal project. Confirmation and brochure download follow a successful Sheet save with matching registrationId. Values use RAW formatting. Ambiguous network retries can append duplicates, as in the existing event flows.

The WordPress registry and early route include the live slug. Production assets and schema URLs are rewritten to the installed main plugin directory. Plugin release 1.7.17. Event schedule is specific to this page and does not inherit evergreen course countdown dates.

Local preview: http://localhost:8768/ . Run preview-server.cjs in the source workspace; its proxy now saves to the same Financials tab, not local storage. The production GitHub package excludes preview scripts and local test records.

Source precedence: current event poster for trainer and event details; supplied brochure for curriculum and attributed learner feedback. The brochure is unchanged and includes older trainer/commercial terms; current inclusions should be confirmed with the team.

Verification: responsive layouts 1440/768/390/320, every CTA, modal dismissal, failure recovery, local and live form saves, brochure SHA256, PHP lint, registry runtime, route rendering, WordPress connected URL. Four clearly labelled test records remain in Financials rows 2-5.
