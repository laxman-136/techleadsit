// index.js - Interactive Conversational Form Script for RISE Program

// -------------------------------------------------------------
// CONFIGURATION
// -------------------------------------------------------------
// Paste your Google Apps Script Web App URL here after deployment
const GOOGLE_SHEET_WEBAPP_URL = "https://script.google.com/macros/s/AKfycbw7yoGrC-xyQpliFSxb3b6dUnSt0aQN5Suk9EhcSdcON9HJJsFgLbWBidES6aUJ6Gm2YA/exec";

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

  const alreadySubmitted = localStorage.getItem("rise_form_submitted");
  if (alreadySubmitted === "true") {
    const savedName = localStorage.getItem("rise_form_name") || "Applicant";
    const savedPhone = localStorage.getItem("rise_form_phone") || "";
    const savedRole = localStorage.getItem("rise_form_role") || "";
    
    // Hide standard form elements
    form.style.display = "none";
    
    // Inject already-submitted state
    setTimeout(() => {
      renderAlreadySubmittedState(savedName, savedPhone, savedRole);
    }, 100);
    return;
  }

  let currentStepId = "1";
  let isSubmitting = false;
  const historyStack = [];
  const answers = {};
  const branchAnswerNames = [
    "step4a_ans", "step5a_ans",
    "step4b_ans", "step5b_ans",
    "step4c_ans", "step5c_ans",
    "step4d_ans", "step5d_ans"
  ];

  // Step percentage mapping for progress indicator
  const progressPercentages = {
    "1": 15,
    "1.5": 25,
    "2": 35,
    "2.5": 50,
    "3": 65,
    "4a": 75, "4b": 75, "4c": 75, "4d": 75,
    "5a": 90, "5b": 90, "5c": 90, "5d": 90,
    "6": 95,
    "success": 100
  };

  stepCards.forEach((stepCard, index) => {
    const heading = stepCard.querySelector(".step-question");
    const optionGroup = stepCard.querySelector(".options-grid");
    if (!heading || !optionGroup) return;

    if (!heading.id) heading.id = `step-question-${index + 1}`;
    optionGroup.setAttribute("aria-labelledby", heading.id);
  });

  // 1. Get next step ID based on branching logic
  function getActiveBranchAnswerNames(role) {
    const branchMap = {
      "Fresher / Just completed training": ["step4a_ans", "step5a_ans"],
      "Working professional looking to switch": ["step4b_ans", "step5b_ans"],
      "EBS consultant moving to Fusion": ["step4c_ans", "step5c_ans"],
      "Unemployed, actively looking": ["step4d_ans", "step5d_ans"]
    };
    return branchMap[role] || [];
  }

  function clearInactiveBranchAnswers(role) {
    const activeNames = getActiveBranchAnswerNames(role);

    branchAnswerNames.forEach(name => {
      if (activeNames.includes(name)) return;

      delete answers[name];
      const input = form.querySelector(`[name="${name}"]`);
      if (!input) return;

      input.value = "";
      const branchCard = input.closest(".step-card");
      branchCard.querySelectorAll(".option-card").forEach(option => {
        option.classList.remove("selected");
        option.setAttribute("aria-pressed", "false");
      });
    });
  }

  function getNextStepId(stepId) {
    switch (stepId) {
      case "1": return "1.5";
      case "1.5": return "2";
      case "2":
        const scmTraining = answers["scm_training"];
        if (scmTraining === "Yes, completed") return "2.5";
        return "3";
      case "2.5": return "3";
      case "3":
        const role = answers["role"];
        if (role === "Fresher / Just completed training") return "4a";
        if (role === "Working professional looking to switch") return "4b";
        if (role === "EBS consultant moving to Fusion") return "4c";
        if (role === "Unemployed, actively looking") return "4d";
        return "6";
      case "4a": return "5a";
      case "4b": return "5b";
      case "4c": return "5c";
      case "4d": return "5d";
      case "5a": case "5b": case "5c": case "5d": return "6";
      case "6": return null; // Submit
      default: return null;
    }
  }

  // 2. Validate current step
  function validateStep(stepId) {
    const card = document.querySelector(`.step-card[data-step="${stepId}"]`);
    if (!card) return true;

    const type = card.getAttribute("data-type");

    if (type === "input") {
      const inputs = card.querySelectorAll("input");
      if (inputs.length === 0) return true;

      let allValid = true;

      inputs.forEach(input => {
        const val = input.value.trim();
        const errorMsg = input.parentNode.querySelector(".input-error-msg");
        if (errorMsg) errorMsg.classList.remove("visible");
        input.setAttribute("aria-invalid", "false");

        if (input.required && !val) {
          input.setAttribute("aria-invalid", "true");
          showError(errorMsg);
          if (allValid) input.focus();
          allValid = false;
          return;
        }

        if (input.type === "tel") {
          const phoneRegex = /^[0-9]{10}$/;
          if (!phoneRegex.test(val)) {
            input.setAttribute("aria-invalid", "true");
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
      // Subtle shake animation
      const card = errorElement.closest(".step-card");
      card.style.animation = "none";
      void card.offsetWidth; // Trigger reflow
      card.style.animation = "shake 0.4s ease";
    }
  }

  // Add shake animation style to head dynamically
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

  // 3. Transition to step
  function transitionToStep(targetStepId, direction = "forward") {
    const currentCard = document.querySelector(`.step-card[data-step="${currentStepId}"]`);
    const targetCard = document.querySelector(`.step-card[data-step="${targetStepId}"]`);
    
    if (!targetCard) return;

    // Remove active and classes from current
    if (currentCard) {
      currentCard.classList.remove("active");
      currentCard.classList.remove("back-transition");
    }

    // Set up target card
    targetCard.classList.add("active");
    if (direction === "backward") {
      targetCard.classList.add("back-transition");
    } else {
      targetCard.classList.remove("back-transition");
    }

    currentStepId = targetStepId;

    const trustPanel = document.querySelector(".trust-panel");
    if (trustPanel) {
      if (targetStepId === "1") {
        trustPanel.style.display = "grid";
      } else {
        trustPanel.style.display = "none";
      }
    }

    const focusTarget = targetCard.querySelector(".conversational-input, .option-card, .btn");
    if (focusTarget) {
      setTimeout(() => focusTarget.focus(), 150);
    }

    updateProgressBar();
    updateDynamicNames();
  }

  // 4. Update Progress Bar
  function updateProgressBar() {
    const percent = progressPercentages[currentStepId] || 0;
    progressFill.style.width = `${percent}%`;
    progressLabel.textContent = `${percent}% completed`;
    progressBar.setAttribute("aria-valuenow", String(percent));
  }

  // 5. Inject dynamic user name in prompts
  function updateDynamicNames() {
    const nameSpans = document.querySelectorAll(".dynamic-name");
    const nameVal = answers["name"] || "";
    
    // Capitalize first letter of the name
    let displayName = "there";
    if (nameVal) {
      const parts = nameVal.trim().split(" ");
      displayName = parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase();
    }

    nameSpans.forEach(span => {
      span.textContent = displayName;
    });
  }

  // 6. Navigation Buttons Event Listeners
  document.querySelectorAll(".btn-next").forEach(btn => {
    btn.addEventListener("click", () => {
      handleNext();
    });
  });

  document.querySelectorAll(".btn-back").forEach(btn => {
    btn.addEventListener("click", () => {
      handleBack();
    });
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

  // 7. Option Card Click Handler
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
      if (inputName === "role") clearInactiveBranchAnswers(val);
      if (inputName === "scm_training" && val !== "Yes, completed") {
        delete answers["scm_year"];
        const yearCard = document.querySelector(`.step-card[data-step="2.5"]`);
        if (yearCard) {
          const yearHiddenInput = yearCard.querySelector('input[type="hidden"]');
          if (yearHiddenInput) yearHiddenInput.value = "";
          yearCard.querySelectorAll(".option-card").forEach(o => {
            o.classList.remove("selected");
            o.setAttribute("aria-pressed", "false");
          });
        }
      }

      // Auto-proceed with micro-delay for visual feedback
      setTimeout(() => {
        if (currentStepId === stepId) { // Verify they haven't manually navigated in the meantime
          handleNext();
        }
      }, 350);
    });
  });

  // 8. Keyboard navigation
  window.addEventListener("keydown", (e) => {
    const activeCard = document.querySelector(`.step-card[data-step="${currentStepId}"]`);
    if (!activeCard) return;

    const inputs = activeCard.querySelectorAll("input");
    const activeIsInput = Array.from(inputs).some(input => document.activeElement === input);
    
    // Pressing Enter
    if (e.key === "Enter") {
      // If focused inside an option button, let normal click fire it
      if (document.activeElement && document.activeElement.classList.contains("option-card")) {
        return;
      }
      e.preventDefault();
      handleNext();
    }
    
    // Pressing Esc to clear active input
    if (e.key === "Escape" && activeIsInput && document.activeElement) {
      document.activeElement.value = "";
      document.activeElement.dispatchEvent(new Event("input", { bubbles: true }));
    }

    // Keyboard Shortcuts (A/B/C/D) for option selections
    // Only capture if user is NOT typing inside a text/tel/email input field
    if (!activeIsInput) {
      const optionKeys = ["a", "b", "c", "d"];
      const keyIdx = optionKeys.indexOf(e.key.toLowerCase());
      
      if (keyIdx !== -1) {
        const options = activeCard.querySelectorAll(".option-card");
        if (options && options[keyIdx]) {
          e.preventDefault();
          options[keyIdx].click();
        }
      }
    }
  });

  // Focus initial name field on load
  const initialInput = document.getElementById("user-name");
  if (initialInput) {
    initialInput.focus();
  }
  updateProgressBar();
  initLiveValidation();

  // -------------------------------------------------------------
  // FORM SUBMISSION (CRM + GOOGLE SHEET WEBAPP)
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
    progressLabel.textContent = `Submitting details...`;
    progressBar.setAttribute("aria-valuenow", "98");

    // Compile payload
    const formElement = document.getElementById("conversational-lead-form");
    const formData = new FormData(formElement);
    const payload = {};
    
    // Inject all captured inputs
    formData.forEach((value, key) => {
      payload[key] = value;
    });

    // Populate segment responses depending on chosen path
    let step3Ans = "";
    let step4Ans = "";
    
    const role = answers["role"];
    if (role === "Fresher / Just completed training") {
      step3Ans = answers["step4a_ans"] || "";
      step4Ans = answers["step5a_ans"] || "";
    } else if (role === "Working professional looking to switch") {
      step3Ans = answers["step4b_ans"] || "";
      step4Ans = answers["step5b_ans"] || "";
    } else if (role === "EBS consultant moving to Fusion") {
      step3Ans = answers["step4c_ans"] || "";
      step4Ans = answers["step5c_ans"] || "";
    } else if (role === "Unemployed, actively looking") {
      step3Ans = answers["step4d_ans"] || "";
      step4Ans = answers["step5d_ans"] || "";
    }

    // Standard CRM fields alignment
    payload["name"] = answers["name"] || "";
    payload["phone"] = answers["phone"] || "";
    payload["email"] = answers["email"] || "";
    payload["location"] = answers["location"] || "";
    payload["scm_training"] = answers["scm_training"] || "";
    payload["scm_year"] = answers["scm_year"] || "";
    payload["role"] = answers["role"] || "";
    payload["experience"] = step3Ans; // Aligning CRM fields
    payload["salary"] = step4Ans;     // Map secondary segment selection to salary parameter to fit standard schema
    
    // Additional parameters for tracking sheet
    payload["step3_answer"] = step3Ans;
    payload["step4_answer"] = step4Ans;
    payload["step5_answer"] = answers["commitment"] || "";

    // 1. Submit to TeleCRM Endpoint
    const crmSubmitPromise = fetch('/wp-json/techleadsit/v1/submit-lead', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('CRM returned error');
      }
      return response.json();
    });

    // 2. Submit to Google Sheet (if configured)
    let sheetSubmitPromise = Promise.resolve({ success: true });
    if (GOOGLE_SHEET_WEBAPP_URL && GOOGLE_SHEET_WEBAPP_URL.startsWith("http")) {
      sheetSubmitPromise = fetch(GOOGLE_SHEET_WEBAPP_URL, {
        method: 'POST',
        mode: 'no-cors', // standard Apps Script bypass for cross-origin posts
        cache: 'no-cache',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      })
      .catch(err => {
        console.error("Google Sheets error logging lead:", err);
        // Do not fail the submission flow for sheets error, CRM is primary
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
      console.error("Submission failed:", err);
      if (window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1" || window.location.protocol === "file:") {
        console.log("Local/Offline environment: Mocking success state.");
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
        transitionToStep("6", "backward");
        submissionError.focus();
      }
    });
  }

  function escapeHtml(value) {
    const element = document.createElement("span");
    element.textContent = String(value);
    return element.innerHTML;
  }

  // 9. Renders final clean Success Card state
  function renderSuccessState() {
    const name = answers["name"] || "Applicant";
    const phone = answers["phone"] || "";
    const role = answers["role"] || "";

    // Save to localStorage to prevent duplicate submissions
    localStorage.setItem("rise_form_submitted", "true");
    localStorage.setItem("rise_form_name", name);
    localStorage.setItem("rise_form_phone", phone);
    localStorage.setItem("rise_form_role", role);

    const parts = name.trim().split(" ");
    const firstName = parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase();
    const safeFirstName = escapeHtml(firstName);
    const safeName = escapeHtml(name);
    const safePhone = escapeHtml(phone);
    const safeRole = escapeHtml(role);

    cardContainer.innerHTML = `
      <div class="form-success-card" role="status" aria-live="polite">
        <div class="success-icon-box">
          <svg class="success-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="20 6 9 17 4 12"></polyline>
          </svg>
        </div>
        
        <h2 class="success-title">Application Submitted!</h2>
        <p class="success-sub">Thank you, ${safeFirstName} — your details have been received and saved. Our admissions team will review your profile shortly.</p>
        
        <div class="success-details-box">
          <div class="success-item">
            <span>Applicant Name</span>
            <span>${safeName}</span>
          </div>
          <div class="success-item">
            <span>WhatsApp Number</span>
            <span>+91 ${safePhone}</span>
          </div>
          <div class="success-item">
            <span>Profile Category</span>
            <span>${safeRole}</span>
          </div>
        </div>
        
        <div class="success-counselor-box">
          <h4>Admissions Assessment Pending</h4>
          <p>Our Admissions Officer will contact you on WhatsApp within 24 hours with the next steps for the RISE program.</p>
          <a href="https://wa.me/918125323232?text=Hi,%20I%20have%20submitted%20my%20conversational%20eligibility%20form%20for%20RISE.%20My%20name%20is%20${encodeURIComponent(name)}." target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp">
            <svg viewBox="0 0 24 24">
              <path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984a9.96 9.96 0 0 0 1.333 4.982L2 22l5.202-1.362a9.923 9.923 0 0 0 4.81 1.226h.003c5.505 0 9.99-4.477 9.99-9.985C22.005 6.478 17.519 2 12.012 2zm5.823 14.153c-.255.719-1.5 1.305-2.073 1.393-.503.076-1.162.138-3.355-.77-2.804-1.158-4.577-4.01-4.717-4.197-.14-.187-1.137-1.513-1.137-2.887 0-1.373.72-2.046.974-2.323.255-.277.556-.346.741-.346.186 0 .372.001.533.008.172.007.404-.066.634.488.236.568.805 1.954.875 2.093.07.14.116.301.023.486-.092.185-.14.3-.277.462-.138.163-.291.363-.415.488-.139.14-.284.293-.122.57.162.277.72 1.187 1.543 1.916.634.562 1.171.737 1.496.899.325.161.512.139.704-.077.192-.217.823-.956 1.043-1.28.22-.323.44-.27.742-.16.301.111 1.912.9 2.237 1.062.325.162.541.242.622.378.082.139.082.806-.173 1.525z"/>
            </svg>
            Message Admissions on WhatsApp
          </a>
        </div>
      </div>
    `;
  }

  // 10. Renders Already Submitted state for duplicate submission protection
  function renderAlreadySubmittedState(name, phone, role) {
    progressFill.style.width = `100%`;
    progressLabel.textContent = `100% completed`;
    progressBar.setAttribute("aria-valuenow", "100");

    const parts = name.trim().split(" ");
    const firstName = parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase();
    const safeFirstName = escapeHtml(firstName);
    const safeName = escapeHtml(name);
    const safePhone = escapeHtml(phone);
    const safeRole = escapeHtml(role);

    cardContainer.innerHTML = `
      <div class="form-success-card" role="status" aria-live="polite">
        <div class="success-icon-box" style="background: rgba(212, 163, 89, 0.1); color: var(--color-primary);">
          <svg class="success-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
        </div>

        <h2 class="success-title">Already Submitted!</h2>
        <p class="success-sub">Hi ${safeFirstName}, you have already submitted your details. Our counselor will call you shortly.</p>

        <div class="success-details-box">
          <div class="success-item">
            <span>Name</span>
            <span>${safeName}</span>
          </div>
          <div class="success-item">
            <span>WhatsApp Number</span>
            <span>+91 ${safePhone}</span>
          </div>
          ${role ? `
          <div class="success-item">
            <span>Profile Category</span>
            <span>${safeRole}</span>
          </div>` : ''}
        </div>

        <div class="success-counselor-box">
          <h4>Admissions Assessment Pending</h4>
          <p>We will contact you on WhatsApp shortly to align you with the RISE program requirements.</p>
          <a href="https://wa.me/918125323232?text=Hi,%20I%20have%20already%20submitted%20my%20conversational%20eligibility%20form%20for%20RISE.%20My%20name%20is%20${encodeURIComponent(name)}." target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp">
            <svg viewBox="0 0 24 24">
              <path d="M12.012 2c-5.506 0-9.989 4.478-9.99 9.984a9.96 9.96 0 0 0 1.333 4.982L2 22l5.202-1.362a9.923 9.923 0 0 0 4.81 1.226h.003c5.505 0 9.99-4.477 9.99-9.985C22.005 6.478 17.519 2 12.012 2zm5.823 14.153c-.255.719-1.5 1.305-2.073 1.393-.503.076-1.162.138-3.355-.77-2.804-1.158-4.577-4.01-4.717-4.197-.14-.187-1.137-1.513-1.137-2.887 0-1.373.72-2.046.974-2.323.255-.277.556-.346.741-.346.186 0 .372.001.533.008.172.007.404-.066.634.488.236.568.805 1.954.875 2.093.07.14.116.301.023.486-.092.185-.14.3-.277.462-.138.163-.291.363-.415.488-.139.14-.284.293-.122.57.162.277.72 1.187 1.543 1.916.634.562 1.171.737 1.496.899.325.161.512.139.704-.077.192-.217.823-.956 1.043-1.28.22-.323.44-.27.742-.16.301.111 1.912.9 2.237 1.062.325.162.541.242.622.378.082.139.082.806-.173 1.525z"/>
            </svg>
            Message Admissions on WhatsApp
          </a>
          <button type="button" class="btn btn-outline restart-application">Start a new application</button>
          <p class="restart-note">This only resets this browser's form status. It does not remove an existing application.</p>
        </div>
      </div>
    `;

    const restartButton = cardContainer.querySelector(".restart-application");
    if (restartButton) {
      restartButton.addEventListener("click", () => {
        if (!window.confirm("Start a new application on this browser? Your previous application will remain on record.")) return;
        ["rise_form_submitted", "rise_form_name", "rise_form_phone", "rise_form_role"].forEach(key => localStorage.removeItem(key));
        window.location.reload();
      });
    }
  }

  // 11. Add Live Input Listeners for real-time validation feedback
  function initLiveValidation() {
    const inputs = document.querySelectorAll(".step-card[data-step='1'] input, .step-card[data-step='1.5'] input");
    inputs.forEach(input => {
      // Validate on input typing (to remove error once it becomes valid)
      input.addEventListener("input", function() {
        validateSingleInput(this, false); // don't force show error if empty on typing
      });
      
      // Validate on blur (when user moves to next field)
      input.addEventListener("blur", function() {
        validateSingleInput(this, true); // force show error if invalid on blur
      });
    });
  }

  function validateSingleInput(input, showIfInvalid) {
    const val = input.value.trim();
    const errorMsg = input.parentNode.querySelector(".input-error-msg");
    if (!errorMsg) return true;
    
    let isValid = true;
    
    // Empty check
    if (input.required && !val) {
      isValid = false;
    }
    
    // Phone pattern check
    if (isValid && input.type === "tel") {
      const phoneRegex = /^[0-9]{10}$/;
      if (!phoneRegex.test(val)) {
        isValid = false;
      }
    }
    
    // Email pattern check
    if (isValid && input.type === "email") {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(val)) {
        isValid = false;
      }
    }
    
    input.setAttribute("aria-invalid", String(!isValid));

    if (isValid) {
      errorMsg.classList.remove("visible");
    } else if (showIfInvalid) {
      errorMsg.classList.add("visible");
    }

    return isValid;
  }
}

// -------------------------------------------------------------
// TRACKING LOGIC (UTMs, Client ID, referrer)
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
  let sessId = sessionStorage.getItem('techleads_session_id');
  if (!sessId) {
    sessId = 's' + Date.now() + '$r' + Math.floor(Math.random() * 1000000);
    sessionStorage.setItem('techleads_session_id', sessId);
  }
  return sessId;
}

function getTrackingData() {
  const data = {};
  const urlParams = new URLSearchParams(window.location.search);
  
  // URL params with session persistence
  const queryParams = [
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content',
    'gclid', 'gbraid', 'wbraid', 'fbclid'
  ];
  
  queryParams.forEach(param => {
    let val = urlParams.get(param);
    if (val) {
      sessionStorage.setItem('techleads_' + param, val);
    } else {
      val = sessionStorage.getItem('techleads_' + param) || '';
    }
    data[param] = val;
  });
  
  // Cookie tracking details
  data['fbp'] = getCookie('_fbp') || '';
  
  let fbc = getCookie('_fbc') || '';
  if (!fbc && data['fbclid']) {
    fbc = `fb.1.${Date.now()}.${data['fbclid']}`;
  }
  data['fbc'] = fbc;
  
  data['ga_client_id'] = getGaClientId();
  data['session_id'] = getSessionId();
  data['landing_page'] = window.location.origin + window.location.pathname;
  
  let ref = sessionStorage.getItem('techleads_referrer');
  if (!ref) {
    ref = document.referrer || 'direct';
    sessionStorage.setItem('techleads_referrer', ref);
  }
  data['referrer'] = ref;
  
  return data;
}

function initTrackingData() {
  const trackingData = getTrackingData();
  
  // Inject tracking data into hidden inputs
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
