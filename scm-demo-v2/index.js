// index.js - TechLeadsIT Landing Page Interactive Script

document.addEventListener("DOMContentLoaded", () => {
  initDynamicDates();
  initCountdownTimer();
  initSeatCounter();
  calculateSalaryPotential(); // Initial calculation
  injectTrackingFieldsToAllForms();
  initOtpVerification();
});

// 1. DYNAMIC DATES CALCULATION (Thursday & Saturday schedule)
let targetDemoDate = null;
let formattedDemoDate = "";

function initDynamicDates() {
  const now = new Date();
  const currentDay = now.getDay(); // 0: Sun, 1: Mon, 2: Tue, etc.
  
  // Create target date copy
  const target = new Date(now.getTime());
  target.setHours(19, 0, 0, 0); // 7:00 PM IST
  
  if (currentDay >= 0 && currentDay <= 2) {
    // Sun, Mon, Tue -> Target next Thursday (day 4)
    const daysToAdd = 4 - currentDay;
    target.setDate(now.getDate() + daysToAdd);
  } else if (currentDay >= 3 && currentDay <= 4) {
    // Wed, Thu -> Target next Saturday (day 6)
    const daysToAdd = 6 - currentDay;
    target.setDate(now.getDate() + daysToAdd);
  } else {
    // Fri, Sat -> Target next Thursday of the following week
    const daysToAdd = (currentDay === 5) ? 6 : 5; // Fri -> Thu (6 days), Sat -> Thu (5 days)
    target.setDate(now.getDate() + daysToAdd);
  }

  targetDemoDate = target;

  // Format date, e.g., "June 18"
  const options = { month: 'long', day: 'numeric' };
  formattedDemoDate = target.toLocaleDateString('en-US', options);

  // Fallback: If formatting fails, use a static date close to today
  if (!formattedDemoDate) {
    formattedDemoDate = "June 18";
  }

  // Update all elements with the dynamic date
  updateDynamicDateTexts();
}

function updateDynamicDateTexts() {
  // Update header badge
  const headerBadge = document.querySelector("#batch-header-badge strong, #batch-header-badge .text-orange");
  if (headerBadge) headerBadge.textContent = formattedDemoDate;

  // Update hero CTA button
  const heroCtaBtn = document.getElementById("hero-cta-btn");
  if (heroCtaBtn) {
    const svgIcon = `<svg class="btn-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>`;
    const btnText = heroCtaBtn.querySelector(".elementor-button-text");
    if (btnText) {
      btnText.innerHTML = `Book My Free Demo Seat — ${formattedDemoDate} ${svgIcon}`;
    } else {
      heroCtaBtn.innerHTML = `Book My Free Demo Seat — ${formattedDemoDate} ${svgIcon}`;
    }
  }

  // Update live seats counter sentence
  const seatsCounterText = document.getElementById("live-seats-counter");
  if (seatsCounterText) {
    seatsCounterText.textContent = `Only 7 seats`;
  }

  // Update agenda subtitle
  const agendaDesc = document.querySelector("#agenda .section-desc");
  if (agendaDesc) {
    const innerP = agendaDesc.querySelector("p, .elementor-text-editor");
    const newText = `Reserve your Zoom seat. Here is the agenda of what we will cover on ${formattedDemoDate} live with Oracle Lead Trainers.`;
    if (innerP) {
      innerP.textContent = newText;
    } else {
      agendaDesc.textContent = newText;
    }
  }

  // Update agenda button
  const agendaBtn = document.querySelector("#agenda .btn-primary-lg");
  if (agendaBtn) {
    const btnText = agendaBtn.querySelector(".elementor-button-text");
    const newText = `Reserve My Seat for ${formattedDemoDate} Demo`;
    if (btnText) {
      btnText.textContent = newText;
    } else {
      agendaBtn.textContent = newText;
    }
  }

  // Update FAQs
  const faqTitles = document.querySelectorAll(".faq-trigger span, .elementor-accordion-title");
  faqTitles.forEach(el => {
    if (el.textContent.includes("June 13")) {
      el.textContent = el.textContent.replace("June 13", formattedDemoDate);
    }
  });

  const faqParagraphs = document.querySelectorAll(".faq-content p, .elementor-accordion-content");
  faqParagraphs.forEach(el => {
    if (el.textContent.includes("June 13")) {
      el.textContent = el.textContent.replace("June 13", formattedDemoDate);
    }
  });

  // Update final CTA
  const finalCtaBtn = document.querySelector(".final-cta-section .btn-primary-lg");
  if (finalCtaBtn) {
    const btnText = finalCtaBtn.querySelector(".elementor-button-text");
    const newText = `Book My Free Demo Seat — ${formattedDemoDate}`;
    if (btnText) {
      btnText.textContent = newText;
    } else {
      finalCtaBtn.textContent = newText;
    }
  }

  const finalCtaMicro = document.querySelectorAll(".final-cta-section .micro-item, .final-cta-section .hero-microcopy span");
  finalCtaMicro.forEach(item => {
    if (item.textContent.includes("June 13")) {
      item.textContent = item.textContent.replace("June 13", formattedDemoDate);
    }
  });

  // Update Modal Subtitle
  const modalSub = document.querySelector(".modal-subtitle");
  if (modalSub) {
    modalSub.innerHTML = `${formattedDemoDate} Live Demo Batch • Only <span id="modal-seats-count">3</span> Seats Left Today`;
  }

  // Update success modal
  const successZoomLabel = document.querySelector(".zoom-label");
  if (successZoomLabel) {
    successZoomLabel.textContent = `📅 ${formattedDemoDate} Batch • Zoom Live`;
  }
}

// 2. COUNTDOWN TIMER
function initCountdownTimer() {
  const daysEl = document.getElementById("days");
  const hoursEl = document.getElementById("hours");
  const minsEl = document.getElementById("minutes");
  const secsEl = document.getElementById("seconds");

  function updateCountdown() {
    const now = new Date().getTime();
    const distance = targetDemoDate.getTime() - now;

    if (distance < 0) {
      // If expired, reset target to 2 days in the future to keep urgency
      initDynamicDates();
      return;
    }

    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

    daysEl.textContent = String(days).padStart(2, "0");
    hoursEl.textContent = String(hours).padStart(2, "0");
    minsEl.textContent = String(minutes).padStart(2, "0");
    secsEl.textContent = String(seconds).padStart(2, "0");
  }

  updateCountdown();
  setInterval(updateCountdown, 1000);
}

// 3. SEAT COUNTER DECREMENT (Urgency Simulation)
let seatsLeft = 7;
function initSeatCounter() {
  const seatsCounterVal = document.getElementById("live-seats-counter");
  const modalSeatsVal = document.getElementById("modal-seats-count");

  // Every 25 seconds, decrement seats by 1 until it hits 3
  const seatInterval = setInterval(() => {
    if (seatsLeft > 3) {
      seatsLeft--;
      if (seatsCounterVal) {
        seatsCounterVal.textContent = `Only ${seatsLeft} seats`;
        // Apply brief glow effect on update
        seatsCounterVal.style.textShadow = "0 0 10px #ff4d00";
        setTimeout(() => {
          seatsCounterVal.style.textShadow = "none";
        }, 1000);
      }
      if (modalSeatsVal) {
        modalSeatsVal.textContent = seatsLeft;
      }
    } else {
      clearInterval(seatInterval);
    }
  }, 25000);
}

// 4. SALARY CALCULATOR LOGIC
function updateExpLabel() {
  const expVal = document.getElementById("calc-exp").value;
  const label = document.getElementById("calc-exp-val");
  label.textContent = expVal == 0 ? "Fresher" : `${expVal} Year${expVal > 1 ? 's' : ''}`;
}

function updateSalaryLabel() {
  const salVal = document.getElementById("calc-current-salary").value;
  const label = document.getElementById("calc-current-salary-val");
  label.textContent = `₹${parseFloat(salVal).toFixed(1)} Lakhs (LPA)`;
}

function calculateSalaryPotential() {
  const domain = document.getElementById("calc-domain").value;
  const exp = parseInt(document.getElementById("calc-exp").value);
  const currentSalary = parseFloat(document.getElementById("calc-current-salary").value);

  // Base multiplier and flat offset by domain
  let baseOffset = 8.5; // Lakhs
  if (domain === "planning") baseOffset = 10.0;
  if (domain === "purchasing") baseOffset = 9.0;
  if (domain === "inventory") baseOffset = 8.0;
  if (domain === "fresh") baseOffset = 6.0;

  // Calculate potential: SCM multiplier increases with experience
  // Base potential salary increases:
  // - Fresher: ~₹8-12 LPA
  // - Mid (3-5 years): ~₹16-26 LPA
  // - High (6-9 years): ~₹28-38 LPA
  // - Senior (10+ years): ~₹38-49 LPA
  
  let potential = baseOffset + (exp * 2.8) + (currentSalary * 0.4);

  // Apply range formatting
  if (exp === 0) {
    potential = Math.min(potential, 10.0);
    potential = Math.max(potential, 6.0);
  } else if (exp <= 3) {
    potential = Math.min(potential, 18.0);
    potential = Math.max(potential, 10.0);
  } else if (exp <= 7) {
    potential = Math.min(potential, 32.0);
    potential = Math.max(potential, 16.0);
  } else {
    potential = Math.min(potential, 49.0);
    potential = Math.max(potential, 28.0);
  }

  // Capping at limits
  if (potential > 49.0) potential = 49.0;
  
  // Format results
  const potentialSalaryEl = document.getElementById("potential-salary");
  const potentialRangeEl = document.getElementById("potential-range");
  const wageGapEl = document.getElementById("wage-gap-value");
  const gapPercentEl = document.getElementById("gap-percent");

  potentialSalaryEl.textContent = `₹${potential.toFixed(1)} LPA`;
  
  const minRange = Math.max(potential - (potential * 0.15), exp === 0 ? 6.0 : currentSalary * 1.5).toFixed(1);
  const maxRange = Math.min(potential + (potential * 0.15), 49.0).toFixed(1);
  potentialRangeEl.textContent = `Potential Range: ₹${minRange} - ₹${maxRange} LPA`;

  // Wage Gap = Potential SCM - Current
  const wageGap = Math.max(potential - currentSalary, 0);
  wageGapEl.textContent = `₹${wageGap.toFixed(1)} LPA`;

  // Wage Gap Percentage
  const pct = currentSalary > 0 ? ((wageGap / currentSalary) * 100) : 0;
  gapPercentEl.textContent = `${Math.round(pct)}% less`;
}

// 5. FAQ ACCORDION TOGGLES
function toggleFaq(btn) {
  const item = btn.parentNode;
  const isActive = item.classList.contains("active");
  
  // Close all other active FAQ items
  document.querySelectorAll(".faq-item").forEach(faq => {
    faq.classList.remove("active");
    const content = faq.querySelector(".faq-content");
    content.style.maxHeight = null;
  });

  if (!isActive) {
    item.classList.add("active");
    const content = item.querySelector(".faq-content");
    // Set max height dynamically to ensure smooth transitions
    content.style.maxHeight = content.scrollHeight + "px";
  }
}

// 6. BOOKING MODAL CONTROL
const modal = document.getElementById("booking-modal");

function openBookingModal() {
  if (modal) {
    modal.showModal();
    document.body.style.overflow = "hidden"; // Prevent background scroll
    injectTrackingFieldsToAllForms();
  }
}

function closeBookingModal() {
  if (modal) {
    modal.close();
    document.body.style.overflow = ""; // Re-enable background scroll
    
    // Reset form after closing if it was successful
    setTimeout(() => {
      document.getElementById("modal-form-state").classList.remove("hidden");
      document.getElementById("modal-success-state").classList.add("hidden");
      
      const form = document.getElementById("booking-form");
      if (form) form.reset();

      const emailInput = document.getElementById("user-email");
      const sendOtpBtn = document.getElementById("send-otp-btn");
      const otpStatus = document.getElementById("email-otp-status");
      const otpInputGroup = document.getElementById("otp-input-group");
      const submitBtn = document.getElementById("submit-booking-btn");
      
      if (emailInput) emailInput.readOnly = false;
      if (sendOtpBtn) {
        sendOtpBtn.style.display = "block";
        sendOtpBtn.textContent = "Send OTP";
        sendOtpBtn.disabled = false;
      }
      if (otpStatus) otpStatus.textContent = "";
      if (otpInputGroup) otpInputGroup.style.display = "none";
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = "0.5";
        submitBtn.style.cursor = "not-allowed";
        submitBtn.textContent = "Confirm My Demo Seat (Verify Email First)";
      }
    }, 400);
  }
}

// Close modal when clicking backdrop
if (modal) {
  modal.addEventListener("click", (e) => {
    const dialogDimensions = modal.getBoundingClientRect();
    if (
      e.clientX < dialogDimensions.left ||
      e.clientX > dialogDimensions.right ||
      e.clientY < dialogDimensions.top ||
      e.clientY > dialogDimensions.bottom
    ) {
      closeBookingModal();
    }
  });
}

// 6.5 SECURE MARKETING & SESSION TRACKING CAPTURE ENGINE
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
  
  // 1. URL params with session persistence
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
  
  // 2. Cookie params
  data['fbp'] = getCookie('_fbp') || '';
  
  // fbc can be cookie-based or fallback to fbclid URL parameter
  let fbc = getCookie('_fbc') || '';
  if (!fbc && data['fbclid']) {
    fbc = `fb.1.${Date.now()}.${data['fbclid']}`;
  }
  data['fbc'] = fbc;
  
  data['ga_client_id'] = getGaClientId();
  
  // 3. Session / Metadata
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

function injectTrackingFieldsToAllForms() {
  const trackingData = getTrackingData();
  const forms = document.querySelectorAll('form');
  
  forms.forEach(form => {
    for (const [key, value] of Object.entries(trackingData)) {
      let input = form.querySelector(`input[name="${key}"]`);
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        form.appendChild(input);
      }
      input.value = value;
    }
  });
}

// 6.7 EMAIL OTP VERIFICATION ENGINE
function initOtpVerification() {
  const emailInput = document.getElementById("user-email");
  const sendOtpBtn = document.getElementById("send-otp-btn");
  const otpStatus = document.getElementById("email-otp-status");
  const otpInputGroup = document.getElementById("otp-input-group");
  const otpInput = document.getElementById("user-otp");
  const verifyOtpBtn = document.getElementById("verify-otp-btn");
  const submitBtn = document.getElementById("submit-booking-btn");

  if (!sendOtpBtn || !verifyOtpBtn) return;

  sendOtpBtn.addEventListener("click", () => {
    const email = emailInput.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRegex.test(email)) {
      alert("Please enter a valid email address first.");
      return;
    }

    sendOtpBtn.textContent = "Sending...";
    sendOtpBtn.disabled = true;
    otpStatus.textContent = "Sending verification code...";
    otpStatus.style.color = "#a1a1aa"; // Grayish

    fetch('/wp-json/techleadsit/v1/send-otp', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ email })
    })
    .then(response => {
      const contentType = response.headers.get("content-type");
      if (contentType && contentType.indexOf("application/json") !== -1) {
        return response.json().then(data => ({ status: response.status, body: data }));
      } else {
        return { status: response.status, body: { success: false, message: `Server error (${response.status})` } };
      }
    })
    .then(res => {
      if (res.status === 200 && res.body.success) {
        otpStatus.textContent = "OTP sent! Please check your inbox (and spam folder).";
        otpStatus.style.color = "#21c45d"; // Green
        otpInputGroup.style.display = "block"; // Show OTP input field
        sendOtpBtn.textContent = "Resend OTP";
        sendOtpBtn.disabled = false;
      } else {
        throw new Error(res.body.message || "Failed to send OTP.");
      }
    })
    .catch(err => {
      console.error(err);
      otpStatus.textContent = err.message || "Error sending code. Try again.";
      otpStatus.style.color = "#ef4444"; // Red
      sendOtpBtn.textContent = "Send OTP";
      sendOtpBtn.disabled = false;
    });
  });

  verifyOtpBtn.addEventListener("click", () => {
    const email = emailInput.value.trim();
    const otp = otpInput.value.trim();

    if (!otp || otp.length !== 6 || isNaN(otp)) {
      alert("Please enter a valid 6-digit OTP code.");
      return;
    }

    verifyOtpBtn.textContent = "Verifying...";
    verifyOtpBtn.disabled = true;

    fetch('/wp-json/techleadsit/v1/verify-otp', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ email, otp })
    })
    .then(response => {
      const contentType = response.headers.get("content-type");
      if (contentType && contentType.indexOf("application/json") !== -1) {
        return response.json().then(data => ({ status: response.status, body: data }));
      } else {
        return { status: response.status, body: { success: false, message: `Server error (${response.status})` } };
      }
    })
    .then(res => {
      if (res.status === 200 && res.body.success) {
        otpStatus.textContent = "Email verified successfully!";
        otpStatus.style.color = "#21c45d"; // Green
        otpInputGroup.style.display = "none"; // Hide OTP input once verified
        sendOtpBtn.style.display = "none"; // Hide send OTP button once verified
        emailInput.readOnly = true; // Lock email field once verified
        
        // Enable submit button
        submitBtn.disabled = false;
        submitBtn.style.opacity = "1";
        submitBtn.style.cursor = "pointer";
        submitBtn.textContent = "Confirm My Demo Seat — Free";
      } else {
        throw new Error(res.body.message || "Invalid OTP code.");
      }
    })
    .catch(err => {
      console.error(err);
      alert(err.message || "Verification failed. Please try again.");
      verifyOtpBtn.textContent = "Verify Code";
      verifyOtpBtn.disabled = false;
    });
  });
}

// 7. FORM SUBMISSION (Securely integrated with WP REST API and TeleCRM)
function handleFormSubmit(event) {
  event.preventDefault();
  
  const name = document.getElementById("user-name").value.trim();
  const email = document.getElementById("user-email").value.trim();
  const phone = document.getElementById("user-phone").value.trim();
  
  // Optional calculator details to enrich the lead data
  const domain = document.getElementById("calc-domain") ? document.getElementById("calc-domain").value : "";
  const exp = document.getElementById("calc-exp") ? document.getElementById("calc-exp").value : "";
  const currentSalary = document.getElementById("calc-current-salary") ? document.getElementById("calc-current-salary").value : "";

  // Validate email
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(email)) {
    alert("Please enter a valid email address.");
    return;
  }

  // Validate phone
  const phoneRegex = /^[0-9]{10}$/;
  if (!phoneRegex.test(phone)) {
    alert("Please enter a valid 10-digit WhatsApp mobile number.");
    return;
  }

  // Get submit button to show loading
  const submitBtn = event.target.querySelector('button[type="submit"]');
  const originalBtnText = submitBtn ? submitBtn.innerHTML : "Book Free Demo Seat";
  if (submitBtn) {
    submitBtn.textContent = "Reserving...";
    submitBtn.disabled = true;
  }

  // Compile payload dynamically from all form fields (including dynamically injected ones)
  const formData = new FormData(event.target);
  const payload = {
    name: name,
    email: email,
    phone: phone,
    role: domain ? `SCM Domain: ${domain}` : '',
    experience: exp ? `${exp} Years` : '',
    salary: currentSalary ? `${currentSalary} LPA` : ''
  };
  formData.forEach((value, key) => {
    payload[key] = value;
  });

  // Send AJAX post to secure WordPress endpoint
  fetch('/wp-json/techleadsit/v1/submit-lead', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  })
  .then(response => {
    if (!response.ok) {
      return response.json().then(errData => {
        throw new Error(errData.message || 'Lead submission rejected by server');
      }).catch(() => {
        throw new Error('Lead submission rejected by server');
      });
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      // Update success state elements
      document.getElementById("success-email").textContent = email;
      document.getElementById("success-phone").textContent = `+91 ${phone}`;

      // Toggle visible states
      document.getElementById("modal-form-state").classList.add("hidden");
      document.getElementById("modal-success-state").classList.remove("hidden");
    } else {
      alert("Registration failed: " + (data.message || "Please check details."));
      if (submitBtn) {
        submitBtn.innerHTML = originalBtnText;
        submitBtn.disabled = false;
      }
    }
  })
  .catch(error => {
    console.error("Error submitting lead:", error);
    alert("Unable to register: " + error.message);
    if (submitBtn) {
      submitBtn.innerHTML = originalBtnText;
      submitBtn.disabled = false;
    }
  });
}
