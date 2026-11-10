'use strict';
const dialog = document.querySelector('#registration');
const form = document.querySelector('#demo-form');
const isLocalPreview = ['localhost', '127.0.0.1'].includes(location.hostname);
const registrationEndpoint = isLocalPreview ? '/api/financials-registration' : (window.FINANCIALS_REGISTRATION_ENDPOINT || '');
// Refresh the side-view preview when source files change, without interrupting a form.
if (isLocalPreview) {
  let previewVersion;
  setInterval(async () => {
    try {
      const response = await fetch('/__version', {cache: 'no-store'});
      const {version} = await response.json();
      if (previewVersion && version !== previewVersion && !dialog.open && !document.querySelector('form:focus-within, form[aria-busy="true"]')) location.reload();
      previewVersion ||= version;
    } catch { /* Keep the page usable while the local server restarts. */ }
  }, 2000);
  document.querySelectorAll('.preview-notice').forEach(note => {
    note.textContent = 'Local preview: registration details are saved on this computer only. No CRM or email is contacted.';
  });
}

const brochureUrl = new URL('assets/oracle-fusion-financials-brochure.pdf', document.currentScript.src).href;
function downloadBrochure(message) {
  const link = document.createElement('a');
  link.href = brochureUrl;
  link.download = 'Tech-Leads-IT-Oracle-Fusion-Financials-Brochure.pdf';
  link.className = 'brochure-download';
  link.textContent = 'Download the brochure again';
  const note = document.createElement('p');
  note.textContent = 'Your brochure download is starting. If it does not start, use the link below.';
  message.append(note, link);
  link.click();
}

const demoTime = Date.parse('2026-11-30T20:00:00+05:30');
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
    if (form.getAttribute('aria-busy') === 'true') {
      dialog.showModal();
      document.body.classList.add('modal-open');
      return;
    }
    opener = button;
    const brochure = button.hasAttribute('data-brochure');
    form.dataset.intent = brochure ? 'brochure' : 'demo';
    document.querySelector('#form-title').textContent = brochure ? 'Get the course brochure.' : 'Take your next step.';
    document.querySelector('#form-description').textContent = brochure ? 'Register for the free Financials demo and download the course brochure automatically.' : 'Oracle Fusion Financials · 30 November · 8:00 PM IST';
    form.dispatchEvent(new Event('registration-intent'));
    dialog.showModal();
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
  let originalLabel = 'Reserve My Free Seat';
  submit.disabled = !registrationEndpoint;
  submit.textContent = registrationEndpoint ? originalLabel : 'Registration opens soon';
  message.textContent = registrationEndpoint ? '' : 'Call +91 8125323232 to reserve your free demo seat.';
  let registrationId;
  let submitting = false;
  let registered = false;
  currentForm.addEventListener('registration-intent', () => {
    if (submitting) return;
    originalLabel = currentForm.dataset.intent === 'brochure' ? 'Submit & Download Brochure' : 'Reserve My Free Seat';
    if (registered) {
      registered = false;
      registrationId = undefined;
      message.replaceChildren();
    }
    submit.disabled = !registrationEndpoint;
    submit.textContent = originalLabel;
  });
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
    if (!registrationEndpoint || submitting || registered) return;
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
    registrationId ||= crypto.randomUUID();
    const payload = new URLSearchParams({
      name,
      email: currentForm.elements.email.value.trim().toLowerCase(),
      countryCode: code,
      mobile,
      careerObjective: currentForm.elements.careerObjective.value,
      source: currentForm.id === 'inline-demo-form' ? 'inline' : 'popup',
      registrationId,
      website: currentForm.elements.website.value
    });
    const brochureRequested = currentForm.dataset.intent === 'brochure';
    submitting = true;
    submit.disabled = true;
    submit.textContent = 'Reserving your seat…';
    currentForm.setAttribute('aria-busy', 'true');
    message.textContent = 'Saving your registration…';
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(registrationEndpoint, { method: 'POST', body: payload, signal: controller.signal, credentials: 'omit' });
      const result = await response.json();
      if (!response.ok || result.success !== true || result.registrationId !== registrationId) throw new Error('Registration not confirmed');
      registered = true;
      submit.textContent = 'You’re registered ✓';
      message.textContent = (isLocalPreview ? 'Preview registration saved locally. ' : '') + 'Your free seat is reserved! Join the Oracle Fusion Financials live demo on 30 November 2026 at 8:00 PM IST. Zoom meeting ID: 8125668899. Password: techleads.';
      const zoomLink = document.createElement('a');
      zoomLink.href = 'https://zoom.us/j/8125668899';
      zoomLink.target = '_blank';
      zoomLink.rel = 'noopener noreferrer';
      zoomLink.className = 'cta zoom-link';
      zoomLink.textContent = 'Join Zoom Demo ↗';
      message.append(document.createElement('br'), zoomLink);
      if (brochureRequested) downloadBrochure(message);
      
      currentForm.reset();
      otherField.hidden = true;
      currentForm.elements.otherCountryCode.disabled = true;
      currentForm.elements.otherCountryCode.required = false;
    } catch {
      message.textContent = 'We couldn’t confirm your registration. Please try again, or call +91 8125323232 for help.';
      submit.textContent = originalLabel;
      submit.disabled = false;
    } finally {
      clearTimeout(timeout);
      submitting = false;
      currentForm.removeAttribute('aria-busy');
      message.focus();
    }
  });
});


// Keep the full accessible heading and reserve its space during the reveal.
const typingText = document.querySelector('.typing-visible');
if (typingText) {
  const phrase = 'Oracle Fusion Financials.';
  const motionPreference = window.matchMedia('(prefers-reduced-motion: reduce)');
  let typingTimer;
  const finishTyping = () => {
    clearTimeout(typingTimer);
    typingText.textContent = phrase;
    typingText.classList.remove('is-typing');
  };
  motionPreference.addEventListener('change', finishTyping);
  if (!motionPreference.matches) {
    let index = 0;
    typingText.textContent = '';
    typingText.classList.add('is-typing');
    const typeNext = () => {
      if (motionPreference.matches) return finishTyping();
      typingText.textContent = phrase.slice(0, ++index);
      if (index < phrase.length) typingTimer = setTimeout(typeNext, 95);
      else finishTyping();
    };
    typingTimer = setTimeout(typeNext, 350);
  }
}
