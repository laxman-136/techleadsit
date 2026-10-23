'use strict';
const dialog = document.querySelector('#registration');
const form = document.querySelector('#demo-form');
const registrationEndpoint = '/wp-json/techleadsit-scm/v1/register';
let registrationPaused = true;
let registrationConfig;
const securityWidgets = new Map();
const requestHeaders = window.scmTestNonce ? {'X-WP-Nonce': window.scmTestNonce} : {};
function prepareSecurityWidget(currentForm) {
  if (registrationPaused || !window.turnstile || securityWidgets.has(currentForm)) return;
  if (currentForm.closest('dialog') && !dialog.open) return;
  const container = document.createElement('div');
  container.className = 'registration-security-check';
  currentForm.querySelector('[type="submit"]').before(container);
  const submit = currentForm.querySelector('[type="submit"]');
  const widget = window.turnstile.render(container, {
    sitekey: registrationConfig.sitekey, action: 'scm_registration', size: 'flexible',
    callback: () => { submit.disabled = false; },
    'expired-callback': () => { submit.disabled = true; },
    'error-callback': () => {
      submit.disabled = true;
      currentForm.querySelector('[role="status"]').textContent = 'Security check unavailable. Please reload to try again.';
    }
  });
  securityWidgets.set(currentForm, widget);
}
window.scmTurnstileReady = () => document.querySelectorAll('form').forEach(prepareSecurityWidget);
async function loadRegistration() {
  try {
    const response = await fetch('/wp-json/techleadsit-scm/v1/config', {headers:requestHeaders, credentials:'same-origin', cache:'no-store'});
    registrationConfig = await response.json();
    if (!response.ok || registrationConfig.enabled !== true || !registrationConfig.sitekey) return;
    registrationPaused = false;
    document.querySelectorAll('form').forEach(currentForm => {
      currentForm.querySelector('[type="submit"]').textContent = 'Reserve My Free Seat';
      currentForm.querySelector('[role="status"]').textContent = registrationConfig.testing ? 'Admin test mode. Public registration remains paused.' : 'Complete the security check to reserve your seat.';
    });
    const script = document.createElement('script');
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=scmTurnstileReady&render=explicit';
    script.async = true;
    document.head.append(script);
  } catch { /* Keep both forms paused when configuration is unavailable. */ }
}

const demoTime = Date.parse('2026-10-31T08:00:00+05:30');
function updateCountdown() {
  const remaining = Math.max(0, Math.floor((demoTime - Date.now()) / 1000));
  const parts = {
    days: Math.floor(remaining / 86400),
    hours: Math.floor(remaining / 3600) % 24,
    minutes: Math.floor(remaining / 60) % 60,
    seconds: remaining % 60
  };
  Object.entries(parts).forEach(([unit, value]) => {
    document.querySelector(`[data-countdown="${unit}"]`).textContent = String(value).padStart(2, '0');
  });
  if (remaining === 0) {
    document.querySelector('#countdown-label').textContent = 'THE DEMO START TIME HAS ARRIVED';
  }
  return remaining;
}
if (updateCountdown() > 0) {
  const countdownInterval = setInterval(() => {
    if (updateCountdown() === 0) clearInterval(countdownInterval);
  }, 1000);
}

let opener;
document.querySelectorAll('[data-register]').forEach(button => {
  button.addEventListener('click', () => {
    opener = button;
    dialog.showModal();
    prepareSecurityWidget(form);
    document.body.classList.add('modal-open');
    form.elements.name.focus();
  });
});
document.querySelector('.close').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', event => {
  const rect = dialog.getBoundingClientRect();
  if (event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
});
dialog.addEventListener('close', () => {
  document.body.classList.remove('modal-open');
  opener?.focus();
});
document.querySelectorAll('form').forEach(currentForm => {
  const countryCode = currentForm.elements.countryCode;
  const otherField = currentForm.elements.otherCountryCode.closest('label');
  const message = currentForm.querySelector('[role="status"]');
  const submit = currentForm.querySelector('[type="submit"]');
  const originalLabel = 'Reserve My Free Seat';
  let registrationId;
  let submitting = false;
  let registered = false;
  const resetValidity = () => {
    currentForm.elements.mobile.setCustomValidity('');
    currentForm.elements.name.setCustomValidity('');
  };
  currentForm.addEventListener('input', resetValidity);
  countryCode.addEventListener('change', () => {
    resetValidity();
    const other = countryCode.value === 'other';
    otherField.hidden = !other;
    currentForm.elements.otherCountryCode.disabled = !other;
    currentForm.elements.otherCountryCode.required = other;
  });
  currentForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (registrationPaused) { message.textContent = 'Registration is temporarily paused. Please check back shortly.'; return; }
    if (submitting || registered) return;
    resetValidity();
    const name = currentForm.elements.name.value.trim();
    const code = countryCode.value === 'other' ? currentForm.elements.otherCountryCode.value.trim() : countryCode.value;
    const mobile = currentForm.elements.mobile.value.replace(/[\s()-]/g, '');
    const fullMobile = code + mobile.replace(/^0+/, '');
    if (!name) currentForm.elements.name.setCustomValidity('Please enter your name.');
    if (!/^\d{6,14}$/.test(mobile) || !/^\+[1-9]\d{7,14}$/.test(fullMobile) || (code === '+91' && !/^[6-9]\d{9}$/.test(mobile))) {
      currentForm.elements.mobile.setCustomValidity('Enter a valid mobile number without the country code.');
    }
    if (!currentForm.reportValidity()) return;
    const widget = securityWidgets.get(currentForm);
    const captchaToken = widget === undefined ? '' : window.turnstile.getResponse(widget);
    if (!captchaToken) { message.textContent = 'Please complete the security check.'; return; }
    registrationId ||= crypto.randomUUID();
    const payload = new URLSearchParams({
      name,
      email: currentForm.elements.email.value.trim().toLowerCase(),
      countryCode: code,
      mobile,
      careerObjective: currentForm.elements.careerObjective.value,
      source: currentForm.id === 'inline-demo-form' ? 'inline' : 'popup',
      registrationId,
      website: currentForm.elements.website.value,
      captchaToken
    });
    submitting = true;
    submit.disabled = true;
    submit.textContent = 'Reserving your seat…';
    currentForm.setAttribute('aria-busy', 'true');
    message.textContent = 'Saving your registration…';
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 50000);
    try {
      const response = await fetch(registrationEndpoint, { method: 'POST', body: payload, signal: controller.signal, credentials: 'same-origin', headers:requestHeaders });
      const result = await response.json();
      if (!response.ok || result.success !== true) throw new Error(result.message || 'Registration not confirmed');
      registered = true;
      submit.textContent = 'You’re registered ✓';
      message.textContent = 'Your free seat is reserved! Join the Oracle Fusion SCM live demo on 31 October 2026 at 8:00 AM IST. Zoom meeting ID: 8125 32 32 32.';
      const zoomLink = document.createElement('a');
      zoomLink.href = 'https://zoom.us/j/8125323232';
      zoomLink.target = '_blank';
      zoomLink.rel = 'noopener noreferrer';
      zoomLink.className = 'cta zoom-link';
      zoomLink.textContent = 'Join Zoom Demo ↗';
      message.append(document.createElement('br'), zoomLink);
      currentForm.reset();
      otherField.hidden = true;
      currentForm.elements.otherCountryCode.disabled = true;
      currentForm.elements.otherCountryCode.required = false;
    } catch (error) {
      message.textContent = error.name === 'AbortError' ? 'We couldn’t confirm your registration. Please try again, or call +91 8125323232 for help.' : error.message;
      submit.textContent = originalLabel;
      submit.disabled = true;
      window.turnstile.reset(widget);
    } finally {
      clearTimeout(timeout);
      submitting = false;
      currentForm.removeAttribute('aria-busy');
      message.focus();
    }
  });
});
loadRegistration();
