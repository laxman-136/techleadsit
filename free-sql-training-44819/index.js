// index.js - Interactive Conversational Registration Script for Free SQL Training
// -------------------------------------------------------------
// CONFIGURATION
// -------------------------------------------------------------
// Paste your Google Apps Script Web App URL here after deployment
const GOOGLE_SHEET_WEBAPP_URL = "https://script.google.com/macros/s/AKfycby75dxSQ7n7ewyuvkYaP3Im5JM329fpnX_6xKCR6VGGFTGK2VGnuWmK3oi7Hn5C7N9SCQ/exec"; 

// Paste your WhatsApp Training Group Link here (for the success page button)
const WHATSAPP_GROUP_LINK = "https://chat.whatsapp.com/FLeuyl1mT477nn4itQYI7T?s=cl&p=a&ilr=4"; 

document.addEventListener("DOMContentLoaded", () => {
  initConversationalForm();
  initTrackingData();
});

// -------------------------------------------------------------
// STATE MANAGEMENT & FORM LOGIC
// -------------------------------------------------------------
function initConversationalForm() {
  const form = document.getElementById("conversational-lead-form");
  const stepCards = document.querySelectorAll(".step-card");
  const progressBar = document.getElementById("progress-bar");
  const progressFill = document.getElementById("progress-fill");
  const progressLabel = document.getElementById("progress-label");
  const submittingState = document.querySelector(".form-submitting-state");
  const submissionError = document.getElementById("submission-error");
  const cardContainer = document.querySelector(".form-card-container");
  
  if (!form) return;

  // Duplicate submission protection using localStorage (Disabled to allow continuous testing)
  /*
  const alreadySubmitted = localStorage.getItem("sql_form_submitted");
  if (alreadySubmitted === "true") {
    const savedName = localStorage.getItem("sql_form_name") || "Student";
    const savedPhone = localStorage.getItem("sql_form_phone") || "";
    const savedStatus = localStorage.getItem("sql_form_status") || "";
    
    // Hide standard form elements
    form.style.display = "none";
    
    // Inject already-submitted state
    setTimeout(() => {
      renderAlreadySubmittedState(savedName, savedPhone, savedStatus);
    }, 100);
    return;
  }
  */

  let currentStepId = "1";
  let isSubmitting = false;
  const historyStack = [];
  const answers = {};

  // Step percentage mapping for progress indicator
  const progressPercentages = {
    "1": 25,
    "2": 50,
    "3": 75,
    "4": 95,
    "success": 100
  };

  // Accessibility improvements (Associate step header IDs with grid groups)
  stepCards.forEach((stepCard, index) => {
    const heading = stepCard.querySelector(".step-question");
    const optionGroup = stepCard.querySelector(".options-grid");
    if (!heading || !optionGroup) return;

    if (!heading.id) heading.id = `step-question-${index + 1}`;
    optionGroup.setAttribute("aria-labelledby", heading.id);
  });

  // 1. Get next step ID (Simple linear flow)
  function getNextStepId(stepId) {
    switch (stepId) {
      case "1": return "2";
      case "2": return "3";
      case "3": return "4";
      case "4": return null; // Trigger submit
      default: return null;
    }
  }

  // 2. Validate current step inputs
  function validateStep(stepId) {
    const card = document.querySelector(`.step-card[data-step="${stepId}"]`);
    if (!card) return true;

    const type = card.getAttribute("data-type");

    if (type === "input") {
      const inputs = card.querySelectorAll("input, select");
      if (inputs.length === 0) return true;

      let allValid = true;

      inputs.forEach(input => {
        if (input.tagName.toLowerCase() === "select") {
          answers[input.name] = input.value;
          return;
        }

        const val = input.value.trim();
        const errorMsg = input.closest(".form-group").querySelector(".input-error-msg");
        if (errorMsg) errorMsg.classList.remove("visible");
        input.setAttribute("aria-invalid", "false");

        const wrapper = input.closest(".phone-input-wrapper");
        if (wrapper) wrapper.setAttribute("aria-invalid", "false");

        if (input.required && !val) {
          input.setAttribute("aria-invalid", "true");
          if (wrapper) wrapper.setAttribute("aria-invalid", "true");
          showError(errorMsg);
          if (allValid) input.focus();
          allValid = false;
          return;
        }

        if (input.type === "tel") {
          const phoneRegex = /^[0-9]{7,15}$/;
          if (!phoneRegex.test(val)) {
            input.setAttribute("aria-invalid", "true");
            if (wrapper) wrapper.setAttribute("aria-invalid", "true");
            showError(errorMsg);
            if (allValid) input.focus();
            allValid = false;
            return;
          }
        }

        if (input.type === "email") {
          const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
          if (!emailRegex.test(val)) {
            input.setAttribute("aria-invalid", "true");
            showError(errorMsg);
            if (allValid) input.focus();
            allValid = false;
            return;
          }
        }

        answers[input.name] = val;
      });

      return allValid;

    } else if (type === "select") {
      const errorMsg = card.querySelector(".input-error-msg");
      if (errorMsg) errorMsg.classList.remove("visible");
      
      const hiddenInput = card.querySelector('input[type="hidden"]');
      if (hiddenInput && !hiddenInput.value) {
        showError(errorMsg);
        const firstOption = card.querySelector(".option-card");
        if (firstOption) firstOption.focus();
        return false;
      }
      return true;
    }

    return true;
  }

  function showError(errorElement) {
    if (errorElement) {
      errorElement.classList.add("visible");
      
      // Card shake micro-animation for validation alert
      const card = errorElement.closest(".step-card");
      card.style.animation = "none";
      void card.offsetWidth; // Force CSS reflow
      card.style.animation = "shake 0.4s ease";
    }
  }

  // Add keyframe for shake animation dynamically if not present
  if (!document.getElementById("shake-keyframe")) {
    const style = document.createElement("style");
    style.id = "shake-keyframe";
    style.innerHTML = `
      @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-6px); }
        75% { transform: translateX(6px); }
      }
    `;
    document.head.appendChild(style);
  }

  // 3. Transition steps
  function transitionToStep(targetStepId, direction = "forward") {
    const currentCard = document.querySelector(`.step-card[data-step="${currentStepId}"]`);
    const targetCard = document.querySelector(`.step-card[data-step="${targetStepId}"]`);
    
    if (!targetCard) return;

    if (currentCard) {
      currentCard.classList.remove("active");
      currentCard.classList.remove("back-transition");
    }

    targetCard.classList.add("active");
    if (direction === "backward") {
      targetCard.classList.add("back-transition");
    } else {
      targetCard.classList.remove("back-transition");
    }

    currentStepId = targetStepId;

    const trustPanel = document.querySelector(".trust-panel");
    if (trustPanel) {
      // Only show trust panel on step 1
      trustPanel.style.display = (targetStepId === "1") ? "grid" : "none";
    }

    // Auto-focus first input or button
    const focusTarget = targetCard.querySelector(".conversational-input, .option-card, .btn");
    if (focusTarget) {
      setTimeout(() => focusTarget.focus(), 150);
    }

    updateProgressBar();
    updateDynamicNames();
  }

  // 4. Progress bar updater
  function updateProgressBar() {
    const percent = progressPercentages[currentStepId] || 0;
    progressFill.style.width = `${percent}%`;
    progressLabel.textContent = `${percent}% completed`;
    progressBar.setAttribute("aria-valuenow", String(percent));
  }

  // 5. Populate first name dynamically in questions
  function updateDynamicNames() {
    const nameSpans = document.querySelectorAll(".dynamic-name");
    const nameVal = answers["name"] || "";
    
    let displayName = "there";
    if (nameVal) {
      const parts = nameVal.trim().split(" ");
      displayName = parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase();
    }

    nameSpans.forEach(span => {
      span.textContent = displayName;
    });
  }

  // 6. Hook up buttons
  document.querySelectorAll(".btn-next").forEach(btn => {
    btn.addEventListener("click", handleNext);
  });

  document.querySelectorAll(".btn-back").forEach(btn => {
    btn.addEventListener("click", handleBack);
  });

  function handleNext() {
    if (validateStep(currentStepId)) {
      const nextStepId = getNextStepId(currentStepId);
      if (nextStepId) {
        historyStack.push(currentStepId);
        transitionToStep(nextStepId, "forward");
      } else {
        submitForm();
      }
    }
  }

  function handleBack() {
    if (historyStack.length > 0) {
      const prevStepId = historyStack.pop();
      transitionToStep(prevStepId, "backward");
    }
  }

  // 7. Option card triggers (Multiple Choice)
  document.querySelectorAll(".option-card").forEach(card => {
    card.addEventListener("click", function() {
      const grid = this.closest(".options-grid");
      const parentStep = this.closest(".step-card");
      const stepId = parentStep.getAttribute("data-step");
      
      grid.querySelectorAll(".option-card").forEach(c => {
        c.classList.remove("selected");
        c.setAttribute("aria-pressed", "false");
      });

      this.classList.add("selected");
      this.setAttribute("aria-pressed", "true");
      
      const val = this.getAttribute("data-value");
      const hiddenInput = parentStep.querySelector('input[type="hidden"]');
      if (hiddenInput) {
        hiddenInput.value = val;
      }

      const errorMsg = parentStep.querySelector(".input-error-msg");
      if (errorMsg) errorMsg.classList.remove("visible");

      const inputName = hiddenInput.getAttribute("name");
      answers[inputName] = val;

      // Auto-proceed with short transition delay
      setTimeout(() => {
        if (currentStepId === stepId) { 
          handleNext();
        }
      }, 350);
    });
  });

  // 8. Keyboard controls
  window.addEventListener("keydown", (e) => {
    const activeCard = document.querySelector(`.step-card[data-step="${currentStepId}"]`);
    if (!activeCard) return;

    const inputs = activeCard.querySelectorAll("input");
    const activeIsInput = Array.from(inputs).some(input => document.activeElement === input);
    
    if (e.key === "Enter") {
      if (document.activeElement && document.activeElement.classList.contains("option-card")) {
        return; // Let standard click click it
      }
      e.preventDefault();
      handleNext();
    }
    
    if (e.key === "Escape" && activeIsInput && document.activeElement) {
      document.activeElement.value = "";
      document.activeElement.dispatchEvent(new Event("input", { bubbles: true }));
    }

    if (!activeIsInput) {
      const shortcutKeys = ["a", "b", "c", "d"];
      const keyIndex = shortcutKeys.indexOf(e.key.toLowerCase());
      
      if (keyIndex !== -1) {
        const options = activeCard.querySelectorAll(".option-card");
        if (options && options[keyIndex]) {
          e.preventDefault();
          options[keyIndex].click();
        }
      }
    }
  });

  // Focus initial input field on load
  const initialInput = document.getElementById("user-name");
  if (initialInput) {
    initialInput.focus();
  }
  updateProgressBar();
  initLiveValidation();

  // -------------------------------------------------------------
  // FORM SUBMISSION (WP LEAD CRM + GOOGLE SHEET WEBAPP)
  // -------------------------------------------------------------
  function submitForm() {
    if (isSubmitting) return;

    isSubmitting = true;
    submissionError.hidden = true;
    form.querySelectorAll("button").forEach(button => button.disabled = true);

    const activeCard = document.querySelector(`.step-card[data-step="${currentStepId}"]`);
    if (activeCard) {
      activeCard.classList.remove("active");
    }

    submittingState.style.display = "flex";
    progressFill.style.width = `98%`;
    progressLabel.textContent = `Saving details...`;
    progressBar.setAttribute("aria-valuenow", "98");

    // Compile payload
    const formElement = document.getElementById("conversational-lead-form");
    const formData = new FormData(formElement);
    const payload = {};
    
    formData.forEach((value, key) => {
      payload[key] = value;
    });

    // Formatting payload fields matching CRM & Apps Script structure
    payload["name"] = answers["name"] || "";
    payload["phone"] = (answers["country_code"] || "+91") + (answers["phone"] || "");
    payload["email"] = answers["email"] || "";
    payload["status"] = answers["status"] || "";
    payload["experience"] = answers["experience"] || "";
    payload["goal"] = answers["goal"] || "";
    
    // Add Course Identifier
    payload["course"] = "Free SQL Training (Aug 5th)";

    // 1. Submit to WordPress CRM Endpoint (if available)
    const crmSubmitPromise = fetch('/wp-json/techleadsit/v1/submit-lead', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('CRM offline or returned error');
      }
      return response.json();
    })
    .catch(err => {
      console.warn("WordPress CRM bypass/not available:", err);
      // Fallback response for offline/separate hosting environments
      return { success: true };
    });

    // 2. Submit to Google Sheet Apps Script Web App
    let sheetSubmitPromise = Promise.resolve({ success: true });
    if (GOOGLE_SHEET_WEBAPP_URL && GOOGLE_SHEET_WEBAPP_URL.startsWith("http")) {
      sheetSubmitPromise = fetch(GOOGLE_SHEET_WEBAPP_URL, {
        method: 'POST',
        mode: 'no-cors', // Standard cross-origin posting mode bypass for Google Sheets
        cache: 'no-cache',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      })
      .catch(err => {
        console.error("Google Sheet submission failed:", err);
        return { success: false, error: err };
      });
    }

    Promise.all([crmSubmitPromise, sheetSubmitPromise])
    .then(() => {
      submittingState.style.display = "none";
      progressFill.style.width = `100%`;
      progressLabel.textContent = `100% completed`;
      progressBar.setAttribute("aria-valuenow", "100");
      renderSuccessState();
    })
    .catch(err => {
      console.error("Submission failed entirely:", err);
      
      // Local/file testing environment fallback
      if (window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1" || window.location.protocol === "file:") {
        console.log("Local development/offline environment detected: Simulating success state.");
        setTimeout(() => {
          submittingState.style.display = "none";
          progressFill.style.width = `100%`;
          progressLabel.textContent = `100% completed`;
          progressBar.setAttribute("aria-valuenow", "100");
          renderSuccessState();
        }, 1000);
      } else {
        isSubmitting = false;
        submittingState.style.display = "none";
        form.querySelectorAll("button").forEach(button => button.disabled = false);
        submissionError.hidden = false;
        transitionToStep("4", "backward");
        submissionError.focus();
      }
    });
  }

  function escapeHtml(value) {
    const el = document.createElement("span");
    el.textContent = String(value);
    return el.innerHTML;
  }

  // 9. Renders final clean Success Card state
  function renderSuccessState() {
    const name = answers["name"] || "Student";
    const phone = answers["phone"] || "";
    const status = answers["status"] || "";
    const experience = answers["experience"] || "";
    const goal = answers["goal"] || "";

    // Save registration status in browser storage (Disabled to allow continuous testing)
    /*
    localStorage.setItem("sql_form_submitted", "true");
    localStorage.setItem("sql_form_name", name);
    localStorage.setItem("sql_form_phone", phone);
    localStorage.setItem("sql_form_status", status);
    */

    const parts = name.trim().split(" ");
    const firstName = parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase();
    const safeFirstName = escapeHtml(firstName);
    const safeName = escapeHtml(name);
    const safePhone = escapeHtml(phone);
    const safeStatus = escapeHtml(status);
    const safeExperience = escapeHtml(experience);
    const safeGoal = escapeHtml(goal);

    cardContainer.innerHTML = `
      <div class="form-success-card" role="status" aria-live="polite">
        <div class="success-icon-box">
          <svg class="success-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
        </div>
        
        <h2 class="success-title">Spot Reserved Successfully!</h2>
        <p class="success-sub">Congratulations, ${safeFirstName}! Your details for the SQL free training starting on <strong>August 5th</strong> have been saved.</p>
        
        <div class="success-details-box">
          <div class="success-item">
            <span>Student Name</span>
            <span>${safeName}</span>
          </div>
          <div class="success-item">
            <span>WhatsApp Number</span>
            <span>+91 ${safePhone}</span>
          </div>
          <div class="success-item">
            <span>Status</span>
            <span>${safeStatus}</span>
          </div>
          <div class="success-item">
            <span>SQL Experience</span>
            <span>${safeExperience}</span>
          </div>
        </div>
        
        <div class="success-counselor-box">
          <h4>Action Required: Join the WhatsApp Training Group</h4>
          <p>We share all daily live lecture links, cheat sheets, code challenges, and software setup guides inside our WhatsApp learning community. Please join immediately.</p>
          <a href="${WHATSAPP_GROUP_LINK}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp">
            <svg viewBox="0 0 24 24">
              <path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984a9.96 9.96 0 0 0 1.333 4.982L2 22l5.202-1.362a9.923 9.923 0 0 0 4.81 1.226h.003c5.505 0 9.99-4.477 9.99-9.985C22.005 6.478 17.519 2 12.012 2zm5.823 14.153c-.255.719-1.5 1.305-2.073 1.393-.503.076-1.162.138-3.355-.77-2.804-1.158-4.577-4.01-4.717-4.197-.14-.187-1.137-1.513-1.137-2.887 0-1.373.72-2.046.974-2.323.255-.277.556-.346.741-.346.186 0 .372.001.533.008.172.007.404-.066.634.488.236.568.805 1.954.875 2.093.07.14.116.301.023.486-.092.185-.14.3-.277.462-.138.163-.291.363-.415.488-.139.14-.284.293-.122.57.162.277.72 1.187 1.543 1.916.634.562 1.171.737 1.496.899.325.161.512.139.704-.077.192-.217.823-.956 1.043-1.28.22-.323.44-.27.742-.16.301.111 1.912.9 2.237 1.062.325.162.541.242.622.378.082.139.082.806-.173 1.525z"/>
            </svg>
            Join Free SQL Training WhatsApp Group
          </a>
        </div>
      </div>
    `;
  }

  // 10. Renders Already Submitted state for duplicate submission protection
  function renderAlreadySubmittedState(name, phone, status) {
    progressFill.style.width = `100%`;
    progressLabel.textContent = `100% completed`;
    progressBar.setAttribute("aria-valuenow", "100");

    const parts = name.trim().split(" ");
    const firstName = parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase();
    const safeFirstName = escapeHtml(firstName);
    const safeName = escapeHtml(name);
    const safePhone = escapeHtml(phone);
    const safeStatus = escapeHtml(status);

    cardContainer.innerHTML = `
      <div class="form-already-submitted" role="status" aria-live="polite">
        <div class="info-icon-box">
          <svg class="info-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
          </svg>
        </div>

        <h2 class="success-title">Already Registered!</h2>
        <p class="success-sub">Hi ${safeFirstName}, you have already secured your spot for the SQL training starting on August 5th.</p>

        <div class="success-details-box">
          <div class="success-item">
            <span>Name</span>
            <span>${safeName}</span>
          </div>
          <div class="success-item">
            <span>WhatsApp Number</span>
            <span>+91 ${safePhone}</span>
          </div>
          ${status ? `
          <div class="success-item">
            <span>Status</span>
            <span>${safeStatus}</span>
          </div>` : ''}
        </div>

        <div class="success-counselor-box">
          <h4>Join the Learning Group</h4>
          <p>Make sure you are in the WhatsApp broadcast group to receive lecture invitations and practice worksheets.</p>
          <a href="${WHATSAPP_GROUP_LINK}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp">
            <svg viewBox="0 0 24 24">
              <path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984a9.96 9.96 0 0 0 1.333 4.982L2 22l5.202-1.362a9.923 9.923 0 0 0 4.81 1.226h.003c5.505 0 9.99-4.477 9.99-9.985C22.005 6.478 17.519 2 12.012 2zm5.823 14.153c-.255.719-1.5 1.305-2.073 1.393-.503.076-1.162.138-3.355-.77-2.804-1.158-4.577-4.01-4.717-4.197-.14-.187-1.137-1.513-1.137-2.887 0-1.373.72-2.046.974-2.323.255-.277.556-.346.741-.346.186 0 .372.001.533.008.172.007.404-.066.634.488.236.568.805 1.954.875 2.093.07.14.116.301.023.486-.092.185-.14.3-.277.462-.138.163-.291.363-.415.488-.139.14-.284.293-.122.57.162.277.72 1.187 1.543 1.916.634.562 1.171.737 1.496.899.325.161.512.139.704-.077.192-.217.823-.956 1.043-1.28.22-.323.44-.27.742-.16.301.111 1.912.9 2.237 1.062.325.162.541.242.622.378.082.139.082.806-.173 1.525z"/>
            </svg>
            Enter SQL Training Group
          </a>
          <button type="button" class="btn btn-outline restart-application" style="margin-top: 1.5rem;">Register Another Person</button>
        </div>
      </div>
    `;

    const restartButton = cardContainer.querySelector(".restart-application");
    if (restartButton) {
      restartButton.addEventListener("click", () => {
        if (!window.confirm("Do you want to clear this registration summary and submit a new form for someone else?")) return;
        ["sql_form_submitted", "sql_form_name", "sql_form_phone", "sql_form_status"].forEach(key => localStorage.removeItem(key));
        window.location.reload();
      });
    }
  }

  // 11. Real-time key/blur validators
  function initLiveValidation() {
    const inputs = document.querySelectorAll(".step-card[data-step='1'] input");
    inputs.forEach(input => {
      input.addEventListener("input", function() {
        validateSingleInput(this, false);
      });
      
      input.addEventListener("blur", function() {
        validateSingleInput(this, true);
      });
    });

    const select = document.getElementById("country-code");
    if (select) {
      answers["country_code"] = select.value;
      select.addEventListener("change", function() {
        answers["country_code"] = this.value;
      });
    }
  }

  function validateSingleInput(input, showIfInvalid) {
    const val = input.value.trim();
    const errorMsg = input.closest(".form-group").querySelector(".input-error-msg");
    if (!errorMsg) return true;
    
    let isValid = true;
    
    if (input.required && !val) {
      isValid = false;
    }
    
    if (isValid && input.type === "tel") {
      const phoneRegex = /^[0-9]{7,15}$/;
      if (!phoneRegex.test(val)) {
        isValid = false;
      }
    }
    
    if (isValid && input.type === "email") {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(val)) {
        isValid = false;
      }
    }
    
    input.setAttribute("aria-invalid", String(!isValid));

    if (input.type === "tel") {
      const wrapper = input.closest(".phone-input-wrapper");
      if (wrapper) {
        wrapper.setAttribute("aria-invalid", String(!isValid));
      }
    }

    if (isValid) {
      errorMsg.classList.remove("visible");
    } else if (showIfInvalid) {
      errorMsg.classList.add("visible");
    }

    return isValid;
  }
}

// -------------------------------------------------------------
// TRACKING LOGIC (UTMs, Client ID, Referrer, Sessions)
// -------------------------------------------------------------
function getCookie(name) {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(';').shift();
  return '';
}

function getGaClientId() {
  const gaCookie = getCookie('_ga');
  if (gaCookie) {
    const parts = gaCookie.split('.');
    if (parts.length >= 4) {
      return parts.slice(-2).join('.');
    }
    return gaCookie;
  }
  return '';
}

function getSessionId() {
  let sessId = sessionStorage.getItem('sql_session_id');
  if (!sessId) {
    sessId = 'sql_s' + Date.now() + '$r' + Math.floor(Math.random() * 1000000);
    sessionStorage.setItem('sql_session_id', sessId);
  }
  return sessId;
}

function getTrackingData() {
  const data = {};
  const urlParams = new URLSearchParams(window.location.search);
  
  const queryParams = [
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content',
    'gclid', 'gbraid', 'wbraid', 'fbclid'
  ];
  
  queryParams.forEach(param => {
    let val = urlParams.get(param);
    if (val) {
      sessionStorage.setItem('sql_' + param, val);
    } else {
      val = sessionStorage.getItem('sql_' + param) || '';
    }
    data[param] = val;
  });
  
  data['fbp'] = getCookie('_fbp') || '';
  
  let fbc = getCookie('_fbc') || '';
  if (!fbc && data['fbclid']) {
    fbc = `fb.1.${Date.now()}.${data['fbclid']}`;
  }
  data['fbc'] = fbc;
  
  data['ga_client_id'] = getGaClientId();
  data['session_id'] = getSessionId();
  data['landing_page'] = window.location.origin + window.location.pathname;
  
  let ref = sessionStorage.getItem('sql_referrer');
  if (!ref) {
    ref = document.referrer || 'direct';
    sessionStorage.setItem('sql_referrer', ref);
  }
  data['referrer'] = ref;
  
  return data;
}

function initTrackingData() {
  const trackingData = getTrackingData();
  
  const fieldMapping = {
    'utm_source': 'track-utm-source',
    'utm_medium': 'track-utm-medium',
    'utm_campaign': 'track-utm-campaign',
    'utm_adgroup': 'track-utm-adgroup',
    'utm_term': 'track-utm-term',
    'utm_content': 'track-utm-content',
    'gclid': 'track-gclid',
    'gbraid': 'track-gbraid',
    'wbraid': 'track-wbraid',
    'fbclid': 'track-fbclid',
    'fbp': 'track-fbp',
    'fbc': 'track-fbc',
    'ga_client_id': 'track-ga-client-id',
    'session_id': 'track-session-id',
    'landing_page': 'track-landing-page',
    'referrer': 'track-referrer'
  };

  for (const [key, elementId] of Object.entries(fieldMapping)) {
    const input = document.getElementById(elementId);
    if (input) {
      input.value = trackingData[key] || '';
    }
  }
}
