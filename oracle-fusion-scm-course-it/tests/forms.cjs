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
  const file = path.resolve(root, relative);
  if (!file.startsWith(root + path.sep) || !fs.existsSync(file)) {
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

async function completeContacts(form) {
  await form.getByLabel('Name', { exact: false }).fill('Test Fresher');
  await form.getByLabel('Email').fill('scm-test@example.invalid');
  await form.getByLabel('Mobile number').fill('9123456789');
  await form.getByRole('button', { name: 'Continue' }).click();
}

async function completeGoal(form) {
  await form.getByLabel('What’s your current status?').selectOption('recent-graduate');
  await form.getByLabel('Highest qualification').selectOption('engineering');
  await form.getByLabel('How comfortable are you with coding?').selectOption('none');
  await form.getByLabel('What are you looking for?').selectOption('first-erp-job');
  await form.getByLabel('When do you want to start learning?').selectOption('one-month');
  await form.getByRole('button', { name: 'Continue' }).click();
}

async function completeEnrollment(form) {
  await form.getByLabel('Who will make the decision about enrolling?').selectOption('with-family');
  await form.getByLabel('Are you comfortable investing').selectOption('fee-first');
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
    let failure = '';
    await page.route(/^https?:/, route => route.request().url().startsWith(base) ? route.continue() : route.abort());
    await page.route(base + '/wp-json/tlit-leads/v1/scm-registration', async route => {
      const request = route.request();
      const payload = Object.fromEntries(new URLSearchParams(request.postData()));
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
      assert.equal(payload.trainingBudget, 'fee-first');
      assert.equal(payload.countryCode + payload.mobile, '+919123456789');
      if (failure === 'unavailable') return route.fulfill({ status: 503, body: 'Unavailable' });
      return route.fulfill({
        status: 200,
        json: { success: true, receipt: 'LP-100', registrationId: failure === 'mismatch' ? 'wrong-id' : payload.registrationId, intent: payload.intent }
      });
    });

    await page.goto(base);
    assert.equal(await page.locator('main > section').count(), 10);
    assert.equal(await page.locator('.awareness + .faq-section').count(), 1);
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
    await completeGoal(heroForm);
    assert.equal(await heroForm.locator('[data-form-progress]').innerText(), 'Step 3 of 3');
    assert.equal(calls.length, 0);
    await heroForm.getByRole('button', { name: 'Back' }).click();
    assert.equal(await heroForm.locator('[name=careerObjective]').inputValue(), 'first-erp-job');
    await heroForm.getByRole('button', { name: 'Continue' }).click();
    await completeEnrollment(heroForm);
    assert.equal(await heroForm.getByRole('link', { name: 'Privacy Policy' }).getAttribute('href'), '/privacy-policy/');
    assert.equal(await heroForm.getByRole('link', { name: 'Privacy Policy' }).getAttribute('target'), '_blank');
    await heroForm.getByRole('button', { name: 'Register for the Free Demo' }).click();
    assert.equal(calls.length, 0);
    await heroForm.getByRole('checkbox').check();
    assert.equal(await heroForm.getByRole('link', { name: 'Terms and Conditions' }).getAttribute('href'), '/terms-and-conditions/');
    await heroForm.getByRole('button', { name: 'Register for the Free Demo' }).click();
    await hero.getByRole('link', { name: 'Join the Free Demo' }).waitFor();
    assert.ok((await hero.getByRole('status').textContent()).includes(demoSchedule));
    assert.equal(calls.length, 1);
    assert.equal(calls[0].intent, 'demo');
    assert.equal(calls[0].source, 'inline');
    assert.equal(await hero.getByRole('link', { name: 'Download the PDF' }).isVisible(), false);

    await page.locator('.closing [data-lead-intent=demo]').click();
    assert.ok((await dialog.locator('#form-description').textContent()).includes(demoSchedule));
    const form = page.locator('#demo-form');
    assert.equal(await form.locator('[name=name]').inputValue(), '');
    assert.equal(await form.getByRole('link', { name: 'Privacy Policy' }).count(), 0);
    await dialog.getByRole('button', { name: 'Close form' }).click();

    await page.locator('[data-lead-intent=brochure]').click();
    await completeContacts(form);
    await completeGoal(form);
    await completeEnrollment(form);
    assert.equal(await form.getByRole('link', { name: 'Privacy Policy' }).getAttribute('href'), '/privacy-policy/');
    await form.getByRole('checkbox').check();
    for (const problem of ['unavailable', 'mismatch']) {
      failure = problem;
      await form.getByRole('button', { name: 'Download the SCM Brochure' }).click();
      await dialog.getByRole('status').filter({ hasText: 'couldn’t confirm' }).waitFor();
      assert.equal(await dialog.getByRole('link', { name: 'Download the PDF' }).isVisible(), false);
    }
    failure = '';
    const downloadEvent = page.waitForEvent('download');
    await form.getByRole('button', { name: 'Download the SCM Brochure' }).click();
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
    const aiDownloadEvent = page.waitForEvent('download');
    await form.getByRole('button', { name: 'Download the AI Brochure' }).click();
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
    console.log('PASS: FAQ order, click-to-call, mobile layout, page media, all three form steps, Conversion Bridge receipts, and both brochure downloads.');
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
