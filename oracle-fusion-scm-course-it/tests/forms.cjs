const assert = require('node:assert/strict');
const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

const root = path.resolve(__dirname, '..');
const wordpress = process.env.SCM_WORDPRESS_RENDER === '1';
const mediaTypes = new Map([
  ['.html', 'text/html'], ['.css', 'text/css'], ['.js', 'application/javascript'],
  ['.png', 'image/png'], ['.jpg', 'image/jpeg'], ['.svg', 'image/svg+xml'],
  ['.webp', 'image/webp'], ['.mp4', 'video/mp4'], ['.pdf', 'application/pdf']
]);

function serve(req, res) {
  const route = new URL(req.url, 'http://localhost').pathname;
  if (wordpress && route === '/') {
    const base = 'http://' + req.headers.host;
    const html = execFileSync(process.env.SCM_PHP_PATH || 'C:/xampp/php/php.exe',
      ['run_test.php', '--route', '/oracle-fusion-scm-course-it'], {
        cwd: path.resolve(root, '..'),
        env: { ...process.env, TLIT_TEST_SITE_URL: base, TLIT_TEST_PLUGIN_URL: base + '/' }
      });
    res.writeHead(200, { 'Content-Type': 'text/html' }).end(html);
    return;
  }
  // The WordPress renderer prefixes assets with their dedicated course folder.
  const coursePrefix = '/oracle-fusion-scm-course-it/';
  if (wordpress && route.startsWith(coursePrefix)) {
    req.url = req.url.replace(coursePrefix, '/');
    return serve(req, res);
  }
  const relative = route === '/' ? 'index.html' : route.slice(1);
  const shared = path.resolve(root, '../lead-attribution.js');
  const file = relative === 'lead-attribution.js' ? shared : path.resolve(root, relative);
  if ((!file.startsWith(root + path.sep) && file !== shared) || !fs.existsSync(file)) {
    res.writeHead(404).end();
    return;
  }
  const length = fs.statSync(file).size;
  const headers = { 'Content-Type': mediaTypes.get(path.extname(file)) ?? 'application/octet-stream' };
  const range = req.headers.range?.match(/^bytes=(\d+)-(\d*)$/);
  if (range) {
    const start = Number(range[1]);
    const end = range[2] ? Math.min(Number(range[2]), length - 1) : length - 1;
    res.writeHead(206, { ...headers, 'Content-Range': 'bytes ' + start + '-' + end + '/' + length, 'Content-Length': end - start + 1, 'Accept-Ranges': 'bytes' });
    fs.createReadStream(file, { start, end }).pipe(res);
  } else {
    res.writeHead(200, { ...headers, 'Content-Length': length, 'Accept-Ranges': 'bytes' });
    fs.createReadStream(file).pipe(res);
  }
}

async function choose(form, name, value) {
  const field = form.locator('[data-select-name="' + name + '"]');
  const text = await form.locator('select[name="' + name + '"] option[value="' + value + '"]').textContent();
  await field.getByRole('combobox').click();
  await field.getByRole('option', { name: text.trim(), exact: true }).click();
  assert.equal(await form.locator('select[name="' + name + '"]').inputValue(), value);
}

async function completeContacts(form, country = '+91', mobile = '9123456789') {
  await form.getByLabel('Name', { exact: false }).fill('Test Fresher');
  await form.getByLabel('Email').fill('scm-test@example.invalid');
  await choose(form, 'countryCode', country);
  await form.getByLabel('Mobile number').fill(mobile);
  await choose(form, 'targetPackage', '4-8');
  await form.getByRole('button', { name: 'Continue' }).click();
}

async function completeGoal(form) {
  await choose(form, 'currentStatus', 'recent-graduate');
  await choose(form, 'highestQualification', 'engineering');
  await choose(form, 'codingComfort', 'none');
  await choose(form, 'careerObjective', 'first-erp-job');
  await choose(form, 'startTimeline', 'one-month');
  await form.getByRole('button', { name: 'Continue' }).click();
}

async function completeEnrollment(form) {
  await choose(form, 'enrollmentDecision', 'with-family');
  await choose(form, 'trainingBudget', 'has-budget');
}

async function requestCode(form) {
  await form.getByRole('button', { name: 'Send verification code', exact: true }).click();
  await form.locator('[data-form-step="4"]').waitFor({ state: 'visible' });
  await fillOTP(form, '1234');
}

async function fillOTP(form, code) {
  await form.getByRole('textbox', { name: 'Digit 1 of 4', exact: true }).fill(code);
  assert.equal(await form.locator('[name=otp]').inputValue(), code);
  assert.equal(await form.locator('[data-otp-digit]').evaluateAll(inputs => inputs.map(input => input.value).join('')), code);
}

async function main() {
  const server = http.createServer(serve);
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const browser = await chromium.launch(
    process.env.SCM_CHROME_PATH ? { executablePath: process.env.SCM_CHROME_PATH } : { channel: 'chrome' }
  );
  try {
    const base = 'http://127.0.0.1:' + server.address().port;
    const page = await browser.newPage({ viewport: { width: 1440, height: 900 }, acceptDownloads: true });
    const calls = [];
    const otpCalls = [];
    const challenges = new Map();
    let otpUnavailable = false;
    let failure = '';
    await page.route(/^https?:/, route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    await page.route(base + '/wp-json/tlit-leads/v1/scm-otp', async route => {
      const payload = route.request().postDataJSON();
      otpCalls.push(payload);
      assert.equal(payload.phone, '+919123456789');
      if (otpUnavailable) return route.fulfill({ status: 502, json: { message: 'We could not send the SMS code. Please try again.' } });
      const challenge = otpCalls.length.toString(16).padStart(48, '0');
      challenges.set(payload.registrationId, challenge);
      return route.fulfill({ json: { success: true, challenge, expiresIn: 300, resendAfter: 30 } });
    });
    await page.route(base + '/wp-json/tlit-leads/v1/scm-registration', async route => {
      const request = route.request();
      const payload = Object.fromEntries(new URLSearchParams(request.postData()));
      if (wordpress) {
        assert.equal(payload.utm_source, 'facebook');
        assert.equal(payload.campaign_name, 'SCM Campaign QA');
        assert.equal(payload.ad_name, 'SCM Video QA');
        assert.equal(payload.adset_name, 'SCM Freshers QA');
        for (const key of 'fbp fbc gclid gbraid wbraid fbclid ga_client_id session_id utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content landing_page referrer'.split(' ')) assert.ok(Object.hasOwn(payload,key), 'Missing hidden field ' + key);
      }
      if ((payload.countryCode + payload.mobile).startsWith('+91') &&
          (payload.otp !== '1234' || payload.challenge !== challenges.get(payload.registrationId))) {
        return route.fulfill({ status: 400, json: { code: 'tlit_landing_lead', message: 'Incorrect code. Please check the SMS and try again.' } });
      }
      if (payload.countryCode !== '+91') {
        assert.equal(payload.challenge, undefined);
        assert.equal(payload.otp, undefined);
      }
      calls.push(payload);
      assert.equal(request.method(), 'POST');
      assert.equal(request.headers().origin, base);
      assert.equal(payload.currentStatus, 'recent-graduate');
      assert.equal(payload.highestQualification, 'engineering');
      assert.equal(payload.codingComfort, 'none');
      assert.equal(payload.termsAccepted, 'true');
      assert.equal(payload.careerObjective, 'first-erp-job');
      assert.equal(payload.startTimeline, 'one-month');
      assert.equal(payload.enrollmentDecision, 'with-family');
      assert.equal(payload.trainingBudget, 'has-budget');
      assert.equal(payload.targetPackage, '4-8');
      assert.equal(payload.countryCode + payload.mobile, payload.countryCode === '+91' ? '+919123456789' : '+12025550123');
      if (failure === 'unavailable') return route.fulfill({ status: 503, body: 'Unavailable' });
      return route.fulfill({
        status: 200,
        json: { success: true, receipt: 'LP-100', registrationId: failure === 'mismatch' ? 'wrong-id' : payload.registrationId, intent: payload.intent }
      });
    });

    await page.goto(base + '?utm_source=facebook&campaign_name=SCM+Campaign+QA&ad_name=SCM+Video+QA&adset_name=SCM+Freshers+QA');
    assert.equal(await page.locator('main > section').count(), 10);
    assert.equal(await page.locator('.awareness + .faq-section').count(), 1);
    assert.equal(await page.locator('#awareness-title').textContent(), 'What Does an Oracle Fusion SCM Consultant Do?');
    assert.equal(await page.locator('.consultant-duties > li').count(), 5);
    assert.ok((await page.locator('.consultant-example').textContent()).includes('purchase request gets stuck'));
    assert.equal(await page.locator('.career-chart > li').count(), 6);
    assert.deepEqual(await page.locator('.career-salary').allTextContents(), [
      '₹40–65+ LPA', '₹30–45 LPA', '₹20–30 LPA', '₹12–20 LPA', '₹8–12 LPA', '₹5–8 LPA'
    ]);
    assert.equal(await page.locator('.career-growth + .brochure-card [data-lead-intent=brochure]').count(), 1);
    assert.ok((await page.locator('#career-note').textContent()).includes('not guaranteed'));
    assert.equal(await page.getByText('Salary references and how to read this chart', { exact: true }).count(), 1);
    assert.equal(await page.locator('.hero').getByRole('link', { name: 'Contact Us', exact: true }).getAttribute('href'), 'tel:+918125323232');
    assert.equal(await page.locator('.hero a[href="#scm-curriculum"]').count(), 0);
    const footerLinks = page.getByRole('navigation', { name: 'Policies and contact' }).getByRole('link');
    assert.deepEqual(await footerLinks.evaluateAll(links => links.map(link => link.getAttribute('href'))), [
      '/privacy-policy/', '/terms-and-conditions/', '/refund-cancellation-policy/', '/cookie-policy/',
      'https://www.techleadsit.com/contactus'
    ]);
    assert.match((await page.locator('h1').innerText()).replace(/\s+/g, ' '), /want an IT career but don’t know coding\? start with Oracle Fusion SCM/i);
    const course = JSON.parse(await page.locator('script[type="application/ld+json"]').textContent());
    assert.equal(course['@type'], 'Course');
    assert.equal(course.url, 'https://lp.techleadsit.com/oracle-fusion-scm-course-it');
    assert.equal(await page.locator('link[rel=canonical]').getAttribute('href'), course.url);
    assert.equal(await page.locator('.event-list time').last().getAttribute('datetime'), wordpress ? '2027-01-25T20:30:00+05:30' : '2026-12-14T07:00:00+05:30');
    assert.equal(await page.locator('.hero-event time').getAttribute('datetime'), wordpress ? '2027-01-21T19:15:00+05:30' : '2026-12-12T08:00:00+05:30');
    if (wordpress) {
      assert.equal(await page.locator('.hero-event [data-scm-platform]').textContent(), 'Live on Google Meet');
      assert.equal(await page.locator('[data-result-intent=demo]').first().getAttribute('href'), 'https://meet.google.com/abc-defg-hij');
    }
    const demoSchedule = await page.locator('.hero-event time').textContent();

    if (process.env.SCM_REVIEW_DIR && process.env.SCM_REVIEW_FULL === '1') {
      fs.mkdirSync(process.env.SCM_REVIEW_DIR, { recursive: true });
      for (const width of [1440, 390, 320]) {
        await page.setViewportSize({ width, height: 900 });
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
        for (const section of ['awareness', 'brochure-section']) {
          await page.locator('.' + section).screenshot({ path: path.join(process.env.SCM_REVIEW_DIR, section + '-' + width + '.png') });
        }
      }
      await page.setViewportSize({ width: 1440, height: 900 });
    }

    const track = page.locator('.logo-track');
    assert.equal(await track.locator('ul').count(), 2);
    await page.waitForFunction(() => Array.from(document.querySelectorAll('.logo-track img')).every(img => img.complete && img.naturalWidth));
    assert.equal(await track.evaluate(el => getComputedStyle(el).animationName), 'scroll-strip');
    await page.locator('#logo-carousel').hover();
    assert.equal(await track.evaluate(el => getComputedStyle(el).animationPlayState), 'paused');
    const logo = track.locator('li:hover img');
    if (await logo.count()) {
      await page.waitForFunction(() => getComputedStyle(document.querySelector('.logo-track li:hover img')).filter.includes('grayscale(0)'));
    }
    await page.getByRole('button', { name: 'Pause logo scroll' }).click();
    assert.equal(await track.evaluate(el => getComputedStyle(el).animationPlayState), 'paused');
    await page.getByRole('button', { name: 'Resume logo scroll' }).click();
    assert.equal(await track.evaluate(el => getComputedStyle(el).animationPlayState), 'running');

    const reviewTrack = page.locator('.reviews-track');
    const reviewCards = reviewTrack.locator('.reviews-grid:not([aria-hidden]) .review-card');
    assert.deepEqual(await reviewCards.locator('figcaption').allTextContents(), [
      'Dhanush Racheti', 'Manikanta Gupta Pennam', 'Sandeep Ganta',
      'Murali Kondeti', 'Rama Devi', 'A Google User'
    ]);
    assert.equal(await reviewTrack.locator('.reviews-grid[aria-hidden] .review-card').count(), 6);
    assert.equal(await reviewTrack.evaluate(el => getComputedStyle(el).animationIterationCount), 'infinite');
    await page.locator('#review-carousel').hover();
    assert.equal(await reviewTrack.evaluate(el => getComputedStyle(el).animationPlayState), 'paused');
    await page.getByRole('button', { name: 'Pause review scroll' }).click();
    assert.equal(await reviewTrack.evaluate(el => getComputedStyle(el).animationPlayState), 'paused');
    await page.getByRole('button', { name: 'Resume review scroll' }).click();
    assert.equal(await reviewTrack.evaluate(el => getComputedStyle(el).animationPlayState), 'running');

    const video = page.locator('video');
    assert.equal(await video.evaluate(el => el.paused && el.currentTime === 0), true);
    await video.scrollIntoViewIfNeeded();
    await page.waitForFunction(() => {
      const el = document.querySelector('video');
      return !el.paused && el.muted && el.currentTime > 0;
    });
    const metadata = await video.evaluate(el => ({ width: el.videoWidth, height: el.videoHeight, duration: el.duration }));
    assert.equal(metadata.width, 720);
    assert.equal(metadata.height, 1280);
    assert.ok(metadata.duration >= 42 && metadata.duration < 46, 'Expected a trainer clip cut before the old event date: ' + metadata.duration);
    await page.locator('.closing').scrollIntoViewIfNeeded();
    await page.waitForFunction(() => document.querySelector('video').paused);
    await video.scrollIntoViewIfNeeded();
    await page.waitForFunction(() => !document.querySelector('video').paused);
    await video.evaluate(el => el.pause());
    await page.locator('.closing').scrollIntoViewIfNeeded();
    await video.scrollIntoViewIfNeeded();
    assert.equal(await video.evaluate(el => el.paused), true);

    const dialog = page.getByRole('dialog');
    const hero = page.locator('#hero-register');
    const heroForm = hero.locator('form');
    async function reviewForm(name, menuName) {
      if (!process.env.SCM_REVIEW_DIR) return;
      fs.mkdirSync(process.env.SCM_REVIEW_DIR, { recursive: true });
      const panel = await dialog.isVisible() ? dialog : hero;
      const targetForm = await dialog.isVisible() ? page.locator('#demo-form') : heroForm;
      for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        if (menuName) await targetForm.locator('[data-select-name="' + menuName + '"]').getByRole('combobox').click();
        await panel.screenshot({ path: path.join(process.env.SCM_REVIEW_DIR, name + '-' + width + '.png'), animations: 'disabled' });
        if (menuName) await page.keyboard.press('Escape');
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      }
      await page.setViewportSize({ width: 1440, height: 900 });
    }
    await reviewForm('contact');
    await reviewForm('country-menu', 'countryCode');
    const countryPicker = heroForm.locator('[data-select-name=countryCode]').getByRole('combobox');
    await countryPicker.focus(); await countryPicker.press('ArrowDown');
    assert.equal(await countryPicker.getAttribute('aria-expanded'), 'true');
    await countryPicker.press('End'); await countryPicker.press('Escape');
    assert.equal(await heroForm.locator('select[name=countryCode]').inputValue(), '+91');
    assert.equal(await heroForm.getByRole('link', { name: 'Privacy Policy' }).count(), 0);
    assert.equal(await page.locator('.hero-portrait').count(), 0);
    await heroForm.getByRole('button', { name: 'Continue' }).click();
    assert.equal(await heroForm.locator('[data-form-step="1"]').isVisible(), true);
    await completeContacts(heroForm);
    assert.equal(calls.length, 0);
    assert.equal(await heroForm.locator('[data-form-step="2"]').isVisible(), true);
    await heroForm.getByRole('button', { name: 'Back' }).click();
    assert.equal(await heroForm.locator('[name=email]').inputValue(), 'scm-test@example.invalid');
    await heroForm.getByRole('button', { name: 'Continue' }).click();
    await heroForm.getByRole('button', { name: 'Continue' }).click();
    assert.equal(await heroForm.locator('[data-form-step="2"]').isVisible(), true);
    assert.equal(await heroForm.locator('.select-control[aria-invalid=true]').count(), 1);
    await reviewForm('background-menu', 'currentStatus');
    const statusPicker = heroForm.locator('[data-select-name=currentStatus]').getByRole('combobox');
    await statusPicker.focus(); await statusPicker.press('ArrowDown'); await statusPicker.press('Home');
    await statusPicker.press('ArrowDown'); await statusPicker.press('Enter');
    assert.equal(await heroForm.locator('select[name=currentStatus]').inputValue(), 'recent-graduate');
    await completeGoal(heroForm);
    assert.equal(await heroForm.locator('[data-form-progress]').innerText(), 'Step 3 of 4');
    assert.equal(calls.length, 0);
    await heroForm.getByRole('button', { name: 'Back' }).click();
    assert.equal(await heroForm.locator('[name=careerObjective]').inputValue(), 'first-erp-job');
    await heroForm.getByRole('button', { name: 'Continue' }).click();
    await completeEnrollment(heroForm);
    await reviewForm('plans-menu', 'trainingBudget');
    assert.equal(await heroForm.getByRole('link', { name: 'Privacy Policy' }).getAttribute('href'), '/privacy-policy/');
    assert.equal(await heroForm.getByRole('link', { name: 'Privacy Policy' }).getAttribute('target'), '_blank');
    await heroForm.getByRole('button', { name: 'Send verification code' }).click();
    assert.equal(calls.length, 0);
    assert.equal(otpCalls.length, 0);
    await heroForm.getByRole('checkbox').check();
    assert.equal(await heroForm.getByRole('link', { name: 'Terms and Conditions' }).getAttribute('href'), '/terms-and-conditions/');
    otpUnavailable = true;
    await heroForm.getByRole('button', { name: 'Send verification code' }).click();
    await hero.getByRole('status').filter({ hasText: 'could not send the SMS code' }).waitFor();
    assert.equal(await heroForm.locator('[data-form-step="3"]').isVisible(), true);
    otpUnavailable = false;
    await requestCode(heroForm);
    assert.equal(await heroForm.locator('[data-form-progress]').innerText(), 'Step 4 of 4');
    assert.equal(await heroForm.getByRole('button', { name: 'Resend code', exact: false }).isDisabled(), true);
    const digits = heroForm.locator('[data-otp-digit]');
    assert.equal(await digits.count(), 4);
    await digits.nth(3).press('Backspace');
    await digits.nth(3).press('Backspace');
    assert.equal(await heroForm.locator('[name=otp]').inputValue(), '12');
    assert.equal(await digits.nth(2).evaluate(input => input === document.activeElement), true);
    await digits.nth(2).press('3');
    assert.equal(await digits.nth(3).evaluate(input => input === document.activeElement), true);
    await digits.nth(3).press('4');
    await digits.nth(3).press('ArrowLeft');
    assert.equal(await digits.nth(2).evaluate(input => input === document.activeElement), true);
    await digits.nth(2).press('ArrowRight');
    assert.equal(await digits.nth(3).evaluate(input => input === document.activeElement), true);
    await digits.nth(3).fill('');
    await heroForm.getByRole('button', { name: 'Verify & register', exact: true }).click();
    assert.equal(calls.length, 0);
    assert.equal(await digits.nth(3).evaluate(input => input === document.activeElement && !input.validity.valid), true);
    await digits.nth(3).fill('x');
    assert.equal(await digits.nth(3).inputValue(), '');
    await digits.nth(2).evaluate(input => {
      const clipboardData = new DataTransfer(); clipboardData.setData('text', '12 34');
      input.dispatchEvent(new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData }));
    });
    assert.equal(await heroForm.locator('[name=otp]').inputValue(), '1234');
    if (process.env.SCM_REVIEW_DIR) {
      for (const width of [1440, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await hero.screenshot({ path: path.join(process.env.SCM_REVIEW_DIR, 'otp-' + width + '.png'), animations: 'disabled' });
      }
      await page.setViewportSize({ width: 1440, height: 900 });
    }
    await fillOTP(heroForm, '0000');
    await heroForm.getByRole('button', { name: 'Verify & register', exact: true }).click();
    await hero.getByRole('status').filter({ hasText: 'Incorrect code' }).waitFor();
    assert.equal(await hero.getByRole('link', { name: 'Join the Free Demo' }).isVisible(), false);
    assert.equal(calls.length, 0);
    const beforeResend = otpCalls.length;
    await page.evaluate(() => { window.scmRealNow = Date.now; Date.now = () => window.scmRealNow() + 31000; });
    await page.waitForFunction(() => !document.querySelector('#hero-demo-form [data-resend-otp]').disabled);
    await heroForm.getByRole('button', { name: 'Resend code', exact: true }).click();
    await heroForm.getByRole('textbox', { name: 'Digit 1 of 4', exact: true }).waitFor({ state: 'visible' });
    await page.waitForFunction(() => document.querySelector('#hero-demo-form [name=otp]').value === '' && !document.querySelector('#hero-demo-form [type=submit]').disabled);
    await page.evaluate(() => { Date.now = window.scmRealNow; delete window.scmRealNow; });
    assert.equal(otpCalls.length, beforeResend + 1);
    assert.deepEqual(await digits.evaluateAll(inputs => inputs.map(input => input.value)), ['', '', '', '']);
    await digits.nth(0).pressSequentially('1234');
    assert.equal(await heroForm.locator('[name=otp]').inputValue(), '1234');
    await heroForm.getByRole('button', { name: 'Verify & register', exact: true }).click();
    await hero.getByRole('link', { name: 'Join the Free Demo' }).waitFor();
    assert.ok((await hero.getByRole('status').textContent()).includes(demoSchedule));
    assert.equal(calls.length, 1);
    assert.equal(calls[0].intent, 'demo');
    assert.equal(calls[0].source, 'inline');
    await reviewForm('success');
    assert.equal(await hero.getByRole('link', { name: 'Download the PDF' }).isVisible(), false);

    await page.locator('.closing [data-lead-intent=demo]').click();
    assert.ok((await dialog.locator('#form-description').textContent()).includes(demoSchedule));
    const form = page.locator('#demo-form');
    await reviewForm('popup-contact');
    assert.equal(await form.locator('[name=name]').inputValue(), '');
    assert.equal(await form.getByRole('link', { name: 'Privacy Policy' }).count(), 0);
    await dialog.getByRole('button', { name: 'Close form' }).click();

    await page.locator('[data-lead-intent=brochure]').click();
    await completeContacts(form);
    await reviewForm('popup-background', 'currentStatus');
    await completeGoal(form);
    await completeEnrollment(form);
    assert.equal(await form.getByRole('link', { name: 'Privacy Policy' }).getAttribute('href'), '/privacy-policy/');
    await form.getByRole('checkbox').check();
    await requestCode(form);
    for (const problem of ['unavailable', 'mismatch']) {
      failure = problem;
      await form.getByRole('button', { name: 'Verify & download', exact: true }).click();
      await dialog.getByRole('status').filter({ hasText: 'couldn’t confirm' }).waitFor();
      assert.equal(await dialog.getByRole('link', { name: 'Download the PDF' }).isVisible(), false);
    }
    failure = '';
    const downloadEvent = page.waitForEvent('download');
    await form.getByRole('button', { name: 'Verify & download', exact: true }).click();
    const download = await downloadEvent;
    assert.equal(download.suggestedFilename(), 'Oracle-Fusion-SCM-Brochure-and-AI-Overview.pdf');
    assert.equal(await download.failure(), null);
    assert.equal(fs.readFileSync(await download.path()).subarray(0, 5).toString(), '%PDF-');
    assert.equal(calls[3].intent, 'brochure');
    assert.equal(calls[3].source, 'popup');
    assert.equal(calls[3].brochureType, 'scm');
    assert.equal(calls[1].registrationId, calls[2].registrationId);
    assert.equal(calls[2].registrationId, calls[3].registrationId);
    assert.notEqual(calls[0].registrationId, calls[3].registrationId);
    assert.equal(await dialog.getByRole('link', { name: 'Download the PDF' }).isVisible(), true);
    assert.equal(await dialog.getByRole('link', { name: 'Join the Free Demo' }).isVisible(), false);
    await dialog.getByRole('button', { name: 'Close form' }).click();

    await page.locator('[data-lead-intent=ai-brochure]').click();
    await completeContacts(form);
    await completeGoal(form);
    await completeEnrollment(form);
    await form.getByRole('checkbox').check();
    await requestCode(form);
    const aiDownloadEvent = page.waitForEvent('download');
    await form.getByRole('button', { name: 'Verify & download', exact: true }).click();
    const aiDownload = await aiDownloadEvent;
    assert.equal(aiDownload.suggestedFilename(), 'Tech-Leads-IT-AI-Agents-Overview.pdf');
    assert.equal(await aiDownload.failure(), null);
    assert.equal(fs.readFileSync(await aiDownload.path()).subarray(0, 5).toString(), '%PDF-');
    assert.equal(calls[4].intent, 'brochure');
    assert.equal(calls[4].source, 'popup');
    assert.equal(calls[4].brochureType, 'ai');
    assert.equal(await dialog.getByRole('link', { name: 'Download the AI PDF' }).isVisible(), true);
    assert.equal(await dialog.getByRole('link', { name: 'Download the PDF' }).isVisible(), false);
    await dialog.getByRole('button', { name: 'Close form' }).click();

    // Changing a verified-flow phone to an international code returns to three
    // steps and sends no SMS or verification data with the final submission.
    await page.locator('.closing [data-lead-intent=demo]').click();
    await completeContacts(form);
    await completeGoal(form);
    await completeEnrollment(form);
    await form.getByRole('checkbox').check();
    await requestCode(form);
    const beforeInternational = otpCalls.length;
    await form.getByRole('button', { name: 'Change mobile number' }).click();
    await choose(form, 'countryCode', '+1');
    await form.getByLabel('Mobile number').fill('2025550123');
    assert.equal(await form.locator('[data-form-progress]').innerText(), 'Step 1 of 3');
    await form.getByRole('button', { name: 'Continue', exact: true }).click();
    await form.getByRole('button', { name: 'Continue', exact: true }).click();
    assert.equal(await form.locator('[data-form-progress]').innerText(), 'Step 3 of 3');
    await form.getByRole('button', { name: 'Register for demo', exact: true }).click();
    await dialog.getByRole('link', { name: 'Join the Free Demo' }).waitFor();
    assert.equal(otpCalls.length, beforeInternational);
    assert.equal(calls.at(-1).countryCode, '+1');
    assert.equal(calls.at(-1).otp, undefined);
    await dialog.getByRole('button', { name: 'Close form' }).click();

    await page.goto(base);
    for (const width of [390, 320]) {
      await page.setViewportSize({ width, height: 700 });
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
      await page.locator('.hero-jump').click();
      assert.equal(await heroForm.locator('[name=name]').isVisible(), true);
      assert.equal(await dialog.isVisible(), false);
    }
    await page.emulateMedia({ reducedMotion: 'reduce' });
    assert.equal(await track.evaluate(el => getComputedStyle(el).animationName), 'none');
    await track.locator('ul[aria-hidden]').waitFor({ state: 'detached' });
    assert.equal(await track.locator('ul').count(), 1);
    await reviewTrack.locator('.reviews-grid[aria-hidden]').waitFor({ state: 'detached' });
    assert.equal(await reviewCards.count(), 6);
    assert.equal(await reviewTrack.evaluate(el => getComputedStyle(el).animationName), 'none');
    await video.scrollIntoViewIfNeeded();
    assert.equal(await video.evaluate(el => el.paused && el.currentTime === 0), true);
    console.log('PASS: India-only SMS OTP, wrong-code rejection, international direct submission, country changes, mobile layout, Conversion Bridge receipts and both brochure downloads.');
  } finally {
    await browser.close();
    server.close();
  }
}

main().catch(error => {
  console.error(error);
  process.exitCode = 1;
  // Handles startup failures before a browser session exists.
  process.exit(1);
});
