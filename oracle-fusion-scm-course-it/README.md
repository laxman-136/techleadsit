# Oracle Fusion SCM course landing page

Public route: /oracle-fusion-scm-course-it (with or without a trailing slash).
The event remains at /scm-batch-80 with its original event design, December 12 schedule and n8n Sheet registration.

In WordPress, open **Landing Page Settings → Oracle Fusion SCM → SCM landing page demo details**.
Set the free demo date/time, regular batch start date/time (both IST), delivery label and HTTPS joining link, then save.
The saved values update the course page, countdown, popup description and confirmation link. They do not change the event page.
Existing saved SCM demo and batch dates are preserved.

The inline and popup forms use the existing same-origin Conversion Bridge route, tlit-leads/v1/scm-registration.
Demo confirmation and brochure downloads require a matching receipt. Leads are attributed to the new course URL.

Local checks (all registrations mocked):

- C:/xampp/php/php.exe -l techleadsit.php
- C:/xampp/php/php.exe run_test.php
- node oracle-fusion-scm-course-it/tests/routing.cjs
- node oracle-fusion-scm-course-it/tests/forms.cjs
- node oracle-fusion-scm-live-demo/tests/forms.cjs

Set SCM_WORDPRESS_RENDER=1 when running the course form test to verify the rendered WordPress settings with mocked registration responses.

Deploy the complete plugin together, including the new folder, routing and lead handler changes.
