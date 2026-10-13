# Course chatbot maintenance

The chatbot is a local, rule-based guide; it does not call an AI service. Answers are curated from the landing page, the supplied 13-page AI Powered Digital Marketing Brochure, and the course reference page. Edit `course-knowledge.js` to update answers; rebuild using the source project's `build-wordpress.cjs`.

WordPress: Landing Page Settings > AI Powered+ Digital Marketing > Show course chatbot / Require SMS OTP for chatbot brochure leads. Both default on. Batch date and timing are read from the existing course settings. The OTP switch affects chatbot leads only; demo and brochure popup verification stay mandatory.

With OTP disabled, the server labels the phone unverified in TeleCRM and does not issue a verification token. All submissions require a successful CRM acknowledgement before redirecting. Pain point and brochure intent are recorded in remarks. Existing attribution fields are retained.

Unknown or unconfirmed information is referred to the team. Where source facts conflict, the confirmed landing-page content takes priority (for example trainer revenue and current teaching language). Taxes, refund terms, tool licence limits and internship stipend are not invented.

Thank-you page starts the brochure download on load and retains a direct download button for browsers that block automatic downloads. Test an actual SMS and CRM delivery after the WordPress update; offline checks do not establish provider delivery.
