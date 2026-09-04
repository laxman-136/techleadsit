# Project Rules & Safeguards for TechLeadsIT Landing Pages

To prevent any service disruptions or critical errors on the live landing pages, all AI agents working on this codebase must strictly adhere to the following rules:

---

## 1. Mandatory WordPress Sync for ANY New Landing Page
Whenever you create or clone a new landing page or slug for ANY course (e.g. HCM, SCM, SQL, Financials, OIC, or new subjects):
1. **Register in Multi-Course Registry (`techleadsit_get_courses_registry()`)**:
   - Add the new slug and template path under its parent Course Category (`hcm`, `scm`, `sql`, `financials`, etc.).
   - If introducing a brand new course topic, create a new course category entry with icons, badge colors, and defaults.
2. **Register in `$landing_pages` Routing Table**:
   - Ensure the slug is mapped to its template file in `techleadsit_route_landing_pages()`.
3. **Add City/Ad-Group Dynamic Replacements (if applicable)**:
   - If targeting specific locations or Google Ads keywords, add the headline/subheading overrides into `$slug_replacements`.
4. **Inherit Standard Classes for Live WordPress Control**:
   - Ensure the page HTML uses standard element IDs (`#countdownBar`, `.countdown-text`, `#seats-left-counter`, `#cohort-schedule`, `.cohort-title`, `.cohort-date-val`, etc.) and `script.js` so it automatically syncs with the WordPress Admin dashboard settings.
5. **Always Verify WordPress Admin Controls**:
   - Confirm that the new landing page appears under the connected URLs list in the WP Admin settings tab.

---

## 2. Mandatory Pre-Commit PHP Syntax Validation
You must check the PHP syntax of `techleadsit.php` using the local XAMPP PHP binary before making any git commit.
* **Command**:
  ```powershell
  C:\xampp\php\php.exe -l techleadsit.php
  ```
* **Constraint**: Do not commit if any syntax warnings or errors are returned.

---

## 3. Mandatory Pre-Commit Runtime Dry-Run
Run the mock WordPress runner to check for execution/runtime errors, registry integrity, and array balance before pushing.
* **Command**:
  ```powershell
  C:\xampp\php\php.exe run_test.php
  ```

---

## 4. No Unapproved GitHub Pushes
* Always wait for the user's explicit request before pushing to GitHub `master` so the user has full control over when live deployments occur.

---

## 5. Mandatory TeleCRM Lead Capture & Tracking Schema Safeguard
Every landing page update and CRM route modification must preserve and guarantee the full lead capture contract:
1. **Mandatory Fields Sent to TeleCRM**:
   - `name`, `phone` (with `+91`), `email`
   - `Course Name`: Always populated with current course (e.g. `Oracle Fusion HCM`)
   - `Lead date`: Auto-populated with current IST timestamp across `Lead date`, `lead_date`, `leaddate`, `date`
   - `Your preferred time to call`: Human-readable slot (e.g. `Afternoon (12:00 PM – 4:00 PM)`)
   - `City Name` & `State Name`: Auto-detected visitor city/state
   - `Lead Source`: Normalized attribution (`Google Ads`, `Facebook Ads`, `Website - Direct`)
   - `Mode of Training`: `Online Live Interactive Batch`
   - `Exp Level`: Human-readable profile (e.g. `Working Professional`)
   - `Remarks`: Formatted multi-line summary
   - **Hidden Tracking Data**: `gclid`, `gbraid`, `wbraid`, `fbclid`, `fbp`, `fbc`, `gaclientid`, `sessionid`, `landingpage`, `referrer`, `utmsource`, `utmmedium`, `utmcampaign`, `utmadgroup`, `utmterm`, `utmcontent`.
2. **Never Auto-Fill Counselor Fields**:
   - Do NOT populate `Course Enrollment date`, `Course 2 Enrollment Date`, or fee payment fields on lead submission (these must remain empty for manual counselor entry).
3. **Pre-Commit Verification**:
   - Run end-to-end payload dry-run simulating Google Ads and Facebook Ads parameters to verify 100% field population before any live deployment.
