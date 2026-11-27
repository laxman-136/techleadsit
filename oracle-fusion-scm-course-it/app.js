'use strict';
const originalPageTitle = document.title;
function updateTabTitle() {
  document.title = document.hidden ? '(1) New Message!!' : originalPageTitle;
}
document.addEventListener('visibilitychange', updateTabTitle);
window.addEventListener('pageshow', updateTabTitle);
updateTabTitle();

const registrationEndpoint = window.SCM_REGISTRATION_ENDPOINT || '/wp-json/tlit-leads/v1/scm-registration';
const otpEndpoint = window.SCM_OTP_ENDPOINT || '/wp-json/tlit-leads/v1/scm-otp';
const demoDate = document.querySelector('time[data-scm-time="demo"]');
const demoTime = Date.parse(demoDate.dateTime);
const demoSchedule = demoDate.textContent.trim();
const demoPlatform = document.querySelector('[data-scm-platform]').textContent.trim();

const formCopy = {
  demo: {
    intent: 'demo',
    title: 'Register for the Free Demo',
    description: 'Oracle Fusion SCM · ' + demoSchedule + ' · ' + demoPlatform,
    submit: 'Register for demo'
  },
  brochure: {
    intent: 'brochure',
    title: 'Download the SCM Brochure',
    description: 'Oracle Fusion SCM syllabus and AI Agents overview',
    submit: 'Download brochure'
  },
  'ai-brochure': {
    intent: 'brochure',
    title: 'Download the AI Brochure',
    description: 'The 8-hour Oracle Fusion AI Agents overview',
    submit: 'Download AI overview'
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
    if (motionControl) motionControl.hidden = reducedMotion.matches;
    carousel.classList.toggle('is-looping', !reducedMotion.matches);
    if (reducedMotion.matches) copy.remove();
    else if (!copy.isConnected) track.append(copy);
  }

  motionControl?.addEventListener('click', () => {
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
  document.querySelectorAll('main .section-copy h2, .curriculum > h2, .ai-grid article')
    .forEach(element => sectionMotion.observe(element));
}

function attachLeadForm(container, source, closeButton) {
  const form = container.querySelector('form');
  const selectControls = window.SCMFormControls?.enhance(form) || new Map();
  const steps = Array.from(form.querySelectorAll('[data-form-step]'));
  const backButton = form.querySelector('[data-form-back]');
  const submitButton = form.querySelector('[type="submit"]');
  const progress = form.querySelector('[data-form-progress]');
  const progressBar = form.querySelector('progress');
  const progressSteps = form.querySelectorAll('[data-progress-step]');
  const statusMessage = container.querySelector('[data-form-status]');
  const resultPanel = container.querySelector('[data-form-result]');
  const resultLinks = resultPanel.querySelectorAll('[data-result-intent]');
  const countryCode = form.elements.countryCode;
  const otherCode = form.elements.otherCountryCode;
  const otherCodeField = form.querySelector('[data-other-code]');
  const otpInput = form.elements.otp;
  const otpDigits = Array.from(form.querySelectorAll('[data-otp-digit]'));
  const resendButton = form.querySelector('[data-resend-otp]');
  const changePhoneButton = form.querySelector('[data-change-phone]');
  const otpDestination = form.querySelector('[data-otp-destination]');

  let offer = 'demo';
  let activeStep = 1;
  let pending = false;
  let requestId;
  let lastSubmission;
  let attribution;
  let challenge = '';
  let challengeRequestId;
  let resendAvailableAt = 0;
  let resendTimer;

  function requiresOTP() {
    const code = countryCode.value === 'other' ? otherCode.value.trim() : countryCode.value;
    return (code + form.elements.mobile.value.replace(/[\s()-]/g, '').replace(/^0+/, '')).startsWith('+91');
  }

  function submitLabel() {
    if (activeStep === steps.length) return offer === 'demo' ? 'Verify & register' : 'Verify & download';
    if (activeStep === steps.length - 1) return requiresOTP() ? 'Send verification code' : formCopy[offer].submit;
    return 'Continue';
  }

  function showMessage(text, state = 'info') {
    statusMessage.textContent = text;
    statusMessage.dataset.state = state;
  }

  function refreshProgress() {
    const total = requiresOTP() ? steps.length : steps.length - 1;
    progress.textContent = 'Step ' + activeStep + ' of ' + total;
    progressBar.max = total;
    progressBar.value = activeStep;
    progressSteps.forEach((item, index) => {
      item.hidden = index >= total;
      item.classList.toggle('is-complete', index + 1 < activeStep);
      item.classList.toggle('is-last', index + 1 === total);
      if (index + 1 === activeStep) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
    });
  }

  function updateResend() {
    const remaining = Math.max(0, Math.ceil((resendAvailableAt - Date.now()) / 1000));
    resendButton.disabled = pending || remaining > 0;
    resendButton.textContent = remaining ? 'Resend code in ' + remaining + 's' : 'Resend code';
    if (!remaining) clearInterval(resendTimer);
  }

  function syncOTP() {
    otpInput.value = otpDigits.map(input => input.value).join('');
  }

  function clearOTP() {
    otpDigits.forEach(input => { input.value = ''; });
    syncOTP();
  }

  function focusOTP() {
    (otpDigits.find(input => !input.value) || otpDigits[3]).focus();
  }

  function enterOTP(index, value) {
    const digits = value.replace(/\D/g, '').slice(0, 4);
    if (!digits) { otpDigits[index].value = ''; syncOTP(); return; }
    // Full-code paste and SMS autofill can arrive in any of the four boxes.
    const start = digits.length === 4 ? 0 : index;
    Array.from(digits).slice(0, 4 - start).forEach((digit, offset) => { otpDigits[start + offset].value = digit; });
    syncOTP();
    otpDigits[Math.min(start + digits.length, 3)].focus();
  }

  otpDigits.forEach((input, index) => {
    input.addEventListener('focus', () => input.select());
    input.addEventListener('input', () => enterOTP(index, input.value));
    input.addEventListener('paste', event => {
      event.preventDefault();
      const digits = event.clipboardData.getData('text').replace(/\D/g, '');
      if (digits) enterOTP(index, digits);
    });
    input.addEventListener('keydown', event => {
      if (event.key === 'Backspace' && !input.value && index > 0) {
        event.preventDefault(); otpDigits[index - 1].value = ''; syncOTP(); otpDigits[index - 1].focus();
      } else if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
        event.preventDefault(); otpDigits[Math.max(0, Math.min(3, index + (event.key === 'ArrowLeft' ? -1 : 1)))].focus();
      }
    });
  });

  function clearVerification() {
    challenge = '';
    challengeRequestId = undefined;
    clearOTP();
    resendAvailableAt = 0;
    clearInterval(resendTimer);
    updateResend();
  }

  function setStep(step, focus = true) {
    activeStep = step;
    refreshProgress();
    selectControls.forEach(control => control.close());
    for (const fieldset of steps) {
      fieldset.hidden = Number(fieldset.dataset.formStep) !== step;
      fieldset.disabled = fieldset.hidden || pending;
    }
    backButton.hidden = step === 1;
    submitButton.textContent = submitLabel();
    showMessage('');
    if (focus) steps[step - 1].querySelector('input:not([disabled]):not([type="hidden"]), [role="combobox"], select:not([hidden])')?.focus();
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
      (!fullMobile.startsWith('+91') || /^\+91[6-9]\d{9}$/.test(fullMobile))
        ? '' : 'Enter a valid mobile number without the country code.'
    );
    return { name, email: form.elements.email.value.trim().toLowerCase(), countryCode: code, mobile: mobile.replace(/^0+/, '') };
  }

  function validate(step) {
    return Array.from(steps[step - 1].elements).every(field => selectControls.has(field) ? selectControls.get(field).validate() : field.reportValidity());
  }

  function reset(nextOffer) {
    offer = nextOffer;
    form.querySelector('[data-contact-description]').textContent = offer === 'demo' ? 'Tell us where to send your demo details.'
      : offer === 'ai-brochure' ? 'Share your details to download the AI overview.' : 'Share your details to get the course brochure.';
    form.reset();
    form.after(statusMessage);
    selectControls.forEach(control => control.sync());
    lastSubmission = undefined;
    attribution = undefined;
    requestId = undefined;
    clearVerification();
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
    steps.forEach(fieldset => { fieldset.disabled = value || Number(fieldset.dataset.formStep) !== activeStep; });
    submitButton.disabled = value;
    backButton.disabled = value;
    if (closeButton) closeButton.disabled = value;
    changePhoneButton.disabled = value;
    otpInput.disabled = value;
    updateResend();
    if (!value) submitButton.textContent = submitLabel();
    if (value) form.setAttribute('aria-busy', 'true');
    else form.removeAttribute('aria-busy');
  }

  countryCode.addEventListener('change', () => { clearVerification(); syncCountryCode(); setStep(activeStep, false); });
  form.addEventListener('input', event => {
    if (event.target.name !== 'otp' && !event.target.matches('[data-otp-digit]') && challenge) clearVerification();
    if (['mobile', 'otherCountryCode'].includes(event.target.name)) {
      refreshProgress();
      submitButton.textContent = submitLabel();
    }
  });
  form.elements.name.addEventListener('input', () => form.elements.name.setCustomValidity(''));
  form.elements.mobile.addEventListener('input', () => form.elements.mobile.setCustomValidity(''));
  otherCode.addEventListener('input', () => form.elements.mobile.setCustomValidity(''));
  backButton.addEventListener('click', () => setStep(activeStep - 1));
  changePhoneButton.addEventListener('click', () => { clearVerification(); setStep(1); form.elements.mobile.focus(); });

  function submissionBody(contact) {
    const { intent } = formCopy[offer];
    const body = new URLSearchParams({
      ...contact,
      source,
      intent,
      targetPackage: form.elements.targetPackage.value,
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
    // Keep attribution stable through SMS verification and safe request retries.
    const tracking=attribution || (attribution=window.TLITAttribution?.decorate(form) || Object.fromEntries(Array.from(form.querySelectorAll('input.all_params[type="hidden"]'), input=>[input.name,input.value])));
    for(const [key,value] of Object.entries(tracking)) body.set(key,value);
    if (intent === 'brochure') body.set('brochureType', offer === 'ai-brochure' ? 'ai' : 'scm');
    const submission = body.toString();
    if (submission !== lastSubmission) {
      requestId = crypto.randomUUID();
      lastSubmission = submission;
    }
    body.set('registrationId', requestId);
    return body;
  }

  async function sendCode(body) {
    const response = await fetch(otpEndpoint, {
      method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: body.get('name'), phone: body.get('countryCode') + body.get('mobile'),
        registrationId: body.get('registrationId'), intent: body.get('intent'), source
      }), signal: AbortSignal.timeout(20000)
    });
    let result;
    try { result = await response.json(); }
    catch { throw new Error('We could not confirm that the code was sent. Please try again.'); }
    if (!response.ok || result.success !== true || !/^[a-f0-9]{48}$/.test(result.challenge)) {
      throw new Error(result.message || 'We could not send the code. Please try again.');
    }
    challenge = result.challenge;
    challengeRequestId = body.get('registrationId');
    clearOTP();
    resendAvailableAt = Date.now() + 30000;
    clearInterval(resendTimer);
    resendTimer = setInterval(updateResend, 1000);
    otpDestination.textContent = 'Enter the four-digit SMS code sent to +91 ' + body.get('mobile') + '.';
    setStep(steps.length);
    showMessage('Code sent. Enter it below to complete your request.', 'success');
  }

  resendButton.addEventListener('click', async () => {
    if (pending || Date.now() < resendAvailableAt) return;
    setPending(true);
    showMessage('Sending a new verification code…', 'pending');
    try { await sendCode(submissionBody(contactDetails())); }
    catch (error) { showMessage(error.message || 'Unable to send the code. Please try again.', 'error'); }
    finally { setPending(false); if (activeStep === steps.length && challenge) focusOTP(); }
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (pending) return;
    const contact = contactDetails();
    if (!validate(activeStep)) return;
    if (activeStep < steps.length - 1) {
      setStep(activeStep + 1);
      return;
    }
    const body = submissionBody(contact);
    const needsOTP = requiresOTP();
    if (needsOTP && activeStep === steps.length - 1) {
      if (challenge && challengeRequestId === requestId) { setStep(steps.length); return; }
      setPending(true);
      showMessage('Sending your verification code…', 'pending');
      try { await sendCode(body); }
      catch (error) { showMessage(error.message || 'Unable to send the code. Please try again.', 'error'); }
      finally { setPending(false); if (activeStep === steps.length && challenge) focusOTP(); }
      return;
    }
    if (needsOTP && (!challenge || challengeRequestId !== requestId)) {
      clearVerification(); setStep(steps.length - 1);
      showMessage('Your details changed. Send a new verification code to continue.', 'error');
      return;
    }
    const { intent } = formCopy[offer];
    if (needsOTP) {
      body.set('challenge', challenge);
      body.set('otp', otpInput.value.trim());
    }

    setPending(true);
    showMessage(needsOTP ? 'Verifying your mobile and saving your details…' : 'Saving your details…', 'pending');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);
    try {
      const response = await fetch(registrationEndpoint, {
        method: 'POST', body, signal: controller.signal, credentials: 'same-origin'
      });
      const result = await response.json();
      if (!response.ok || result.success !== true || !/^LP-[1-9]\d*$/.test(result.receipt) ||
          result.registrationId !== requestId || result.intent !== intent) {
        if (!response.ok && result.message) {
          showMessage(result.message, 'error');
          return;
        }
        throw new Error('Lead receipt did not match the request.');
      }
      form.hidden = true;
      resultPanel.hidden = false;
      resultPanel.querySelector('[data-result-title]').textContent = intent === 'brochure' ? 'Your brochure is ready' : 'You’re registered';
      resultPanel.querySelector('.form-success-heading').after(statusMessage);
      const resultLink = resultPanel.querySelector('[data-result-intent="' + offer + '"]');
      resultLink.hidden = false;
      if (intent === 'brochure') {
        showMessage('Your download is starting. You can also use the link below.', 'success');
        resultLink.click();
      } else {
        showMessage('Your free seat is reserved for ' + demoSchedule + '. Use the link below to join.', 'success');
      }
      lastSubmission = undefined;
      requestId = undefined;
      clearVerification();
    } catch {
      showMessage('We couldn’t confirm your request. Please try again or call +91 8125323232 for help.', 'error');
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
