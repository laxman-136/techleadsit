'use strict';
const registrationEndpoint = window.SCM_REGISTRATION_ENDPOINT || '/wp-json/tlit-leads/v1/scm-registration';
const demoDate = document.querySelector('time[data-scm-time="demo"]');
const demoTime = Date.parse(demoDate.dateTime);
const demoSchedule = demoDate.textContent.trim();
const demoPlatform = document.querySelector('[data-scm-platform]').textContent.trim();

const formCopy = {
  demo: {
    intent: 'demo',
    title: 'Register for the Free Demo',
    description: 'Oracle Fusion SCM · ' + demoSchedule + ' · ' + demoPlatform,
    submit: 'Register for the Free Demo'
  },
  brochure: {
    intent: 'brochure',
    title: 'Download the SCM Brochure',
    description: 'Oracle Fusion SCM syllabus and AI Agents overview',
    submit: 'Download the SCM Brochure'
  },
  'ai-brochure': {
    intent: 'brochure',
    title: 'Download the AI Brochure',
    description: 'The 8-hour Oracle Fusion AI Agents overview',
    submit: 'Download the AI Brochure'
  }
};

function updateCountdown() {
  const remaining = Math.max(0, Math.floor((demoTime - Date.now()) / 1000));
  const parts = {
    days: Math.floor(remaining / 86400),
    hours: Math.floor(remaining / 3600) % 24,
    minutes: Math.floor(remaining / 60) % 60,
    seconds: remaining % 60
  };
  for (const [unit, value] of Object.entries(parts)) {
    document.querySelector('[data-countdown="' + unit + '"]').textContent = String(value).padStart(2, '0');
  }
  if (!remaining) document.querySelector('#countdown-label').textContent = 'The demo start time has arrived';
  return remaining;
}
if (updateCountdown()) {
  const countdownTimer = setInterval(() => {
    if (!updateCountdown()) clearInterval(countdownTimer);
  }, 1000);
}

const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
for (const carousel of document.querySelectorAll('[data-loop-carousel]')) {
  const track = carousel.querySelector('[data-loop-track]');
  const copy = track.firstElementChild.cloneNode(true);
  copy.setAttribute('aria-hidden', 'true');
  const motionControl = document.querySelector('[aria-controls="' + carousel.id + '"]');

  function syncMotion() {
    motionControl.hidden = reducedMotion.matches;
    carousel.classList.toggle('is-looping', !reducedMotion.matches);
    if (reducedMotion.matches) copy.remove();
    else if (!copy.isConnected) track.append(copy);
  }

  motionControl.addEventListener('click', () => {
    const paused = carousel.classList.toggle('is-paused');
    motionControl.textContent = (paused ? 'Resume ' : 'Pause ') + motionControl.dataset.motionLabel + ' scroll';
  });
  reducedMotion.addEventListener('change', syncMotion);
  syncMotion();
}

const trainerVideo = document.querySelector('.trainer-video');
if ('IntersectionObserver' in window) {
  let playOnEntry = true;
  const visibility = new IntersectionObserver(([entry]) => {
    if (!entry.isIntersecting && !trainerVideo.paused) {
      playOnEntry = true;
      trainerVideo.pause();
    } else if (entry.intersectionRatio >= 0.45 && playOnEntry && !reducedMotion.matches && !trainerVideo.ended) {
      playOnEntry = false;
      trainerVideo.muted = true;
      trainerVideo.play().catch(() => {});
    }
  }, { threshold: [0, 0.45] });
  visibility.observe(trainerVideo);

  const sectionMotion = new IntersectionObserver(entries => {
    for (const entry of entries) {
      if (!entry.isIntersecting) continue;
      if (!reducedMotion.matches) entry.target.classList.add('is-in-view');
      sectionMotion.unobserve(entry.target);
    }
  }, { threshold: 0.25 });
  document.querySelectorAll('main .section-copy h2, .curriculum > h3, .ai-grid article')
    .forEach(element => sectionMotion.observe(element));
}

function attachLeadForm(container, source, closeButton) {
  const form = container.querySelector('form');
  const steps = Array.from(form.querySelectorAll('[data-form-step]'));
  const backButton = form.querySelector('[data-form-back]');
  const submitButton = form.querySelector('[type="submit"]');
  const progress = form.querySelector('[data-form-progress]');
  const progressBar = form.querySelector('progress');
  const statusMessage = container.querySelector('[data-form-status]');
  const resultPanel = container.querySelector('[data-form-result]');
  const resultLinks = resultPanel.querySelectorAll('[data-result-intent]');
  const countryCode = form.elements.countryCode;
  const otherCode = form.elements.otherCountryCode;
  const otherCodeField = form.querySelector('[data-other-code]');

  let offer = 'demo';
  let activeStep = 1;
  let pending = false;
  let requestId;
  let lastSubmission;

  function setStep(step, focus = true) {
    activeStep = step;
    progress.textContent = 'Step ' + step + ' of ' + steps.length;
    progressBar.value = step;
    for (const fieldset of steps) {
      fieldset.hidden = Number(fieldset.dataset.formStep) !== step;
      fieldset.disabled = fieldset.hidden;
    }
    backButton.hidden = step === 1;
    submitButton.textContent = step === steps.length ? formCopy[offer].submit : 'Continue';
    statusMessage.textContent = '';
    if (focus) steps[step - 1].querySelector('input, select').focus();
  }

  function syncCountryCode() {
    const needsOtherCode = countryCode.value === 'other';
    otherCodeField.hidden = !needsOtherCode;
    otherCode.disabled = !needsOtherCode;
    otherCode.required = needsOtherCode;
    form.elements.mobile.setCustomValidity('');
  }

  function contactDetails() {
    const code = countryCode.value === 'other' ? otherCode.value.trim() : countryCode.value;
    const mobile = form.elements.mobile.value.trim().replace(/[\s()-]/g, '');
    const fullMobile = code + mobile.replace(/^0+/, '');
    const name = form.elements.name.value.trim();
    const nameLength = new TextEncoder().encode(name).length;
    form.elements.name.setCustomValidity(nameLength < 2 ? 'Please enter your full name.' : nameLength > 80 ? 'Please enter a shorter name.' : '');
    form.elements.mobile.setCustomValidity(
      /^\d{6,14}$/.test(mobile) && /^\+[1-9]\d{7,14}$/.test(fullMobile) &&
      (code !== '+91' || /^[6-9]\d{9}$/.test(mobile))
        ? '' : 'Enter a valid mobile number without the country code.'
    );
    return { name, email: form.elements.email.value.trim().toLowerCase(), countryCode: code, mobile };
  }

  function validate(step) {
    return Array.from(steps[step - 1].elements).every(field => field.reportValidity());
  }

  function reset(nextOffer) {
    offer = nextOffer;
    form.reset();
    form.elements.name.setCustomValidity('');
    syncCountryCode();
    resultPanel.hidden = true;
    for (const link of resultLinks) link.hidden = true;
    form.hidden = false;
    submitButton.disabled = false;
    setStep(1, false);
  }

  function setPending(value) {
    pending = value;
    submitButton.disabled = value;
    backButton.disabled = value;
    if (closeButton) closeButton.disabled = value;
    if (value) form.setAttribute('aria-busy', 'true');
    else form.removeAttribute('aria-busy');
  }

  countryCode.addEventListener('change', syncCountryCode);
  form.elements.name.addEventListener('input', () => form.elements.name.setCustomValidity(''));
  form.elements.mobile.addEventListener('input', () => form.elements.mobile.setCustomValidity(''));
  otherCode.addEventListener('input', () => form.elements.mobile.setCustomValidity(''));
  backButton.addEventListener('click', () => setStep(activeStep - 1));

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (pending) return;
    const contact = contactDetails();
    if (!validate(activeStep)) return;
    if (activeStep < steps.length) {
      setStep(activeStep + 1);
      return;
    }

    const { intent } = formCopy[offer];
    const body = new URLSearchParams({
      ...contact,
      source,
      intent,
      currentStatus: form.elements.currentStatus.value,
      highestQualification: form.elements.highestQualification.value,
      codingComfort: form.elements.codingComfort.value,
      careerObjective: form.elements.careerObjective.value,
      startTimeline: form.elements.startTimeline.value,
      enrollmentDecision: form.elements.enrollmentDecision.value,
      trainingBudget: form.elements.trainingBudget.value,
      termsAccepted: String(form.elements.termsAccepted.checked),
      website: form.elements.website.value
    });
    if (intent === 'brochure') body.set('brochureType', offer === 'ai-brochure' ? 'ai' : 'scm');
    const submission = body.toString();
    if (submission !== lastSubmission) {
      requestId = crypto.randomUUID();
      lastSubmission = submission;
    }
    body.set('registrationId', requestId);

    setPending(true);
    statusMessage.textContent = 'Saving your details…';
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(registrationEndpoint, {
        method: 'POST', body, signal: controller.signal, credentials: 'same-origin'
      });
      const result = await response.json();
      if (!response.ok || result.success !== true || !/^LP-[1-9]\d*$/.test(result.receipt) ||
          result.registrationId !== requestId || result.intent !== intent) {
        throw new Error('Lead receipt did not match the request.');
      }
      form.hidden = true;
      resultPanel.hidden = false;
      const resultLink = resultPanel.querySelector('[data-result-intent="' + offer + '"]');
      resultLink.hidden = false;
      if (intent === 'brochure') {
        statusMessage.textContent = 'Your brochure is ready. If the download doesn’t start, use the link below.';
        resultLink.click();
      } else {
        statusMessage.textContent = 'Your free seat is reserved for ' + demoSchedule + '. Use the link below to join.';
      }
      lastSubmission = undefined;
      requestId = undefined;
    } catch {
      statusMessage.textContent = 'We couldn’t confirm your request. Please try again or call +91 8125323232 for help.';
    } finally {
      clearTimeout(timeout);
      setPending(false);
      statusMessage.focus();
    }
  });

  reset('demo');
  return { reset, focus: () => form.elements.name.focus(), isPending: () => pending };
}

const inlineFields = document.querySelector('[data-hero-fields]');
const popupFields = document.querySelector('[data-popup-fields]');
popupFields.append(...inlineFields.cloneNode(true).childNodes);
popupFields.querySelector('form').id = 'demo-form';

const dialog = document.querySelector('#registration');
const closeButton = dialog.querySelector('.close');
attachLeadForm(inlineFields, 'inline');
const popup = attachLeadForm(popupFields, 'popup', closeButton);
let opener;

document.querySelectorAll('[data-lead-intent]').forEach(button => {
  button.addEventListener('click', () => {
    opener = button;
    const offer = button.dataset.leadIntent;
    dialog.querySelector('#form-title').textContent = formCopy[offer].title;
    dialog.querySelector('#form-description').textContent = formCopy[offer].description;
    popup.reset(offer);
    dialog.showModal();
    document.body.classList.add('modal-open');
    popup.focus();
  });
});
closeButton.addEventListener('click', () => { if (!popup.isPending()) dialog.close(); });
dialog.addEventListener('cancel', event => { if (popup.isPending()) event.preventDefault(); });
dialog.addEventListener('close', () => {
  document.body.classList.remove('modal-open');
  opener?.focus();
});
