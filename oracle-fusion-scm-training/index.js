// index.js - Oracle Fusion SCM Training Landing Page Logic

document.addEventListener("DOMContentLoaded", () => {
  initDynamicDates();
  initCountdownTimer();
  initSeatCounter();
  initAccordion();
  initFaqAccordion();
  initConversationalForm();
  initStatsCounter();
});

// 1. DYNAMIC DATES CALCULATION (Thursday & Saturday schedule)
let targetDemoDate = null;
let formattedDemoDate = "";

function initDynamicDates() {
  const now = new Date();
  const currentDay = now.getDay(); // 0: Sun, 1: Mon, 2: Tue, etc.
  
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

  const options = { month: 'long', day: 'numeric' };
  formattedDemoDate = target.toLocaleDateString('en-US', options);

  if (!formattedDemoDate) {
    formattedDemoDate = "July 23";
  }

  // Update dates across page
  updateDynamicDateTexts();
}

function updateDynamicDateTexts() {
  // Update header badge
  const headerBadge = document.querySelector("#batch-header-badge strong");
  if (headerBadge) headerBadge.textContent = formattedDemoDate;

  // Update hero date text
  const heroDateText = document.getElementById("hero-date-text");
  if (heroDateText) heroDateText.textContent = formattedDemoDate;

  // Update seats counter
  const seatsCounterText = document.getElementById("live-seats-counter");
  if (seatsCounterText) {
    seatsCounterText.textContent = `Only 7 seats left for ${formattedDemoDate} Batch`;
  }

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

  if (!daysEl) return;

  function updateCountdown() {
    const now = new Date().getTime();
    const distance = targetDemoDate.getTime() - now;

    if (distance < 0) {
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

  const seatInterval = setInterval(() => {
    if (seatsLeft > 3) {
      seatsLeft--;
      if (seatsCounterVal) {
        seatsCounterVal.textContent = `Only ${seatsLeft} seats left for ${formattedDemoDate} Batch`;
      }
      if (modalSeatsVal) {
        modalSeatsVal.textContent = seatsLeft;
      }
    } else {
      clearInterval(seatInterval);
    }
  }, 25000);
}



// 5. ACCORDION (Curriculum Breakdown)
function initAccordion() {
  const triggers = document.querySelectorAll(".accordion-trigger");
  triggers.forEach(trigger => {
    trigger.addEventListener("click", () => {
      const item = trigger.parentNode;
      const isActive = item.classList.contains("active");
      
      // Close all items
      document.querySelectorAll(".accordion-item").forEach(el => {
        el.classList.remove("active");
        el.querySelector(".accordion-content").style.maxHeight = null;
      });

      if (!isActive) {
        item.classList.add("active");
        const content = item.querySelector(".accordion-content");
        content.style.maxHeight = content.scrollHeight + "px";
      }
    });
  });
}

// 6. FAQ ACCORDION
function initFaqAccordion() {
  const triggers = document.querySelectorAll(".faq-trigger");
  triggers.forEach(trigger => {
    trigger.addEventListener("click", () => {
      const item = trigger.parentNode;
      const isActive = item.classList.contains("active");
      
      document.querySelectorAll(".faq-item").forEach(el => {
        el.classList.remove("active");
        el.querySelector(".faq-content").style.maxHeight = null;
      });

      if (!isActive) {
        item.classList.add("active");
        const content = item.querySelector(".faq-content");
        content.style.maxHeight = content.scrollHeight + "px";
      }
    });
  });
}

// 7. CONVERSATIONAL FORM ENGINE
let currentStep = 1;
const totalSteps = 5;

// Lead data state
const leadData = {
  name: "",
  email: "",
  phone: "",
  role: "",
  batch: "",
  experience: "",
  salary: ""
};

function initConversationalForm() {
  // Option Card Selection for Step 4 (Profession) and Step 5 (Preferred Batch)
  const professionCards = document.querySelectorAll(".step-profession .dropdown-option-card");
  professionCards.forEach(card => {
    card.addEventListener("click", () => {
      professionCards.forEach(c => c.classList.remove("selected"));
      card.classList.add("selected");
      leadData.role = card.dataset.value;
      
      // Auto advance to next step after a tiny delay for better UX
      setTimeout(() => {
        nextStep();
      }, 300);
    });
  });

  const batchCards = document.querySelectorAll(".step-batch .dropdown-option-card");
  batchCards.forEach(card => {
    card.addEventListener("click", () => {
      batchCards.forEach(c => c.classList.remove("selected"));
      card.classList.add("selected");
      leadData.batch = card.dataset.value;
    });
  });
}

window.openBookingModal = function() {
  const modal = document.getElementById("booking-modal");
  if (modal) {
    modal.showModal();
    document.body.style.overflow = "hidden";
    resetConversationalForm();
  }
}

window.closeBookingModal = function() {
  const modal = document.getElementById("booking-modal");
  if (modal) {
    modal.close();
    document.body.style.overflow = "";
  }
}

function resetConversationalForm() {
  currentStep = 1;
  showStep(currentStep);
  document.getElementById("modal-form-state").classList.remove("hidden");
  document.getElementById("modal-success-state").classList.add("hidden");
  
  // Clear inputs
  document.getElementById("conv-name").value = "";
  document.getElementById("conv-email").value = "";
  document.getElementById("conv-phone").value = "";
  
  document.querySelectorAll(".dropdown-option-card").forEach(c => c.classList.remove("selected"));
  
  leadData.name = "";
  leadData.email = "";
  leadData.phone = "";
  leadData.role = "";
  leadData.batch = "";
}

function showStep(step) {
  document.querySelectorAll(".form-step").forEach(el => el.classList.remove("active"));
  document.querySelector(`.step-${step}`).classList.add("active");
  
  // Update progress bar
  const progressPercent = ((step - 1) / (totalSteps - 1)) * 100;
  document.querySelector(".form-progress-bar").style.width = `${progressPercent}%`;
  
  // Hide/Show Back Button
  const backBtn = document.getElementById("conv-back-btn");
  if (backBtn) {
    backBtn.style.visibility = step === 1 ? "hidden" : "visible";
  }

  // Submit vs Next Button Text
  const nextBtn = document.getElementById("conv-next-btn");
  if (nextBtn) {
    nextBtn.textContent = step === totalSteps ? "Reserve My Free Demo Seat" : "Continue";
  }
}

window.prevStep = function() {
  if (currentStep > 1) {
    currentStep--;
    showStep(currentStep);
  }
}

window.nextStep = function() {
  if (validateStep(currentStep)) {
    if (currentStep < totalSteps) {
      currentStep++;
      showStep(currentStep);
    } else {
      submitConversationalForm();
    }
  }
}

function validateStep(step) {
  if (step === 1) {
    const name = document.getElementById("conv-name").value.trim();
    if (!name) {
      alert("Please enter your full name.");
      return false;
    }
    leadData.name = name;
  }
  
  if (step === 2) {
    const email = document.getElementById("conv-email").value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRegex.test(email)) {
      alert("Please enter a valid email address.");
      return false;
    }
    leadData.email = email;
  }

  if (step === 3) {
    const phone = document.getElementById("conv-phone").value.trim();
    const phoneRegex = /^[0-9]{10}$/;
    if (!phone || !phoneRegex.test(phone)) {
      alert("Please enter a valid 10-digit mobile number.");
      return false;
    }
    leadData.phone = phone;
  }

  if (step === 4) {
    if (!leadData.role) {
      alert("Please select your current profession.");
      return false;
    }
  }

  if (step === 5) {
    if (!leadData.batch) {
      alert("Please select your preferred batch.");
      return false;
    }
  }

  return true;
}

// 8. MARKETING TRACKING ENGINE (UTM, Cookies, Referrer)
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

// 9. SUBMISSION VIA API
function submitConversationalForm() {
  const nextBtn = document.getElementById("conv-next-btn");
  const originalText = nextBtn.textContent;
  
  nextBtn.disabled = true;
  nextBtn.textContent = "Reserving Slot...";
  
  // Gather tracking data
  const trackingData = getTrackingData();
  
  // Combine lead data
  const payload = {
    name: leadData.name,
    email: leadData.email,
    phone: leadData.phone,
    role: leadData.role,
    experience: "Not Provided",
    salary: "Not Provided",
    ...trackingData
  };

  fetch('/wp-json/techleadsit/v1/submit-lead', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  })
  .then(response => {
    return response.json().then(data => ({
      status: response.status,
      body: data
    }));
  })
  .then(res => {
    if (res.status === 200 && res.body.success) {
      // Success State
      document.getElementById("modal-form-state").classList.add("hidden");
      document.getElementById("modal-success-state").classList.remove("hidden");
      
      document.getElementById("success-email").textContent = leadData.email;
      document.getElementById("success-phone").textContent = `+91 ${leadData.phone}`;
    } else {
      throw new Error(res.body.message || "Failed to submit registration.");
    }
  })
  .catch(err => {
    console.error(err);
    alert(err.message || "An error occurred. Please try again.");
    nextBtn.disabled = false;
    nextBtn.textContent = originalText;
  });
}

// 10. SCROLL-TRIGGERED STATS COUNTER
function initStatsCounter() {
  const statsSection = document.querySelector(".stats-section");
  const counters = document.querySelectorAll(".stat-count");
  
  if (!statsSection || counters.length === 0) return;
  
  const startCounting = (counter) => {
    const target = +counter.getAttribute("data-target");
    let count = 0;
    
    // Calculate dynamic increment step based on target size
    // Ensure they finish around the same time (~1-1.5 seconds)
    const duration = 1200; // Total duration in ms
    const frameDuration = 15; // Loop frequency in ms
    const totalFrames = duration / frameDuration;
    const increment = Math.ceil(target / totalFrames);
    
    const updateCount = () => {
      count += increment;
      if (count < target) {
        counter.textContent = count.toLocaleString();
        setTimeout(updateCount, frameDuration);
      } else {
        counter.textContent = target.toLocaleString();
      }
    };
    
    updateCount();
  };
  
  const observer = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        // Small delay before start for smooth feel
        setTimeout(() => {
          counters.forEach(counter => startCounting(counter));
        }, 200);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });
  
  observer.observe(statsSection);
}

// 11. WHATSAPP GALLERY LIGHTBOX
window.openLightbox = function(imgSrc) {
  const lightbox = document.getElementById("whatsapp-lightbox");
  const lightboxImg = document.getElementById("lightbox-img");
  if (lightbox && lightboxImg) {
    // If the image fails to load (e.g. hasn't been uploaded yet), we can load the fallback SVG placeholder
    lightboxImg.src = imgSrc;
    lightboxImg.onerror = function() {
      // Create inline SVG fallback matching the clicked image container's dummy data
      let dummyText = "WhatsApp Chat Details";
      if (imgSrc.includes("whatsapp-1")) dummyText = "Amit (Working Professional) - Interview cleared!";
      if (imgSrc.includes("whatsapp-2")) dummyText = "Priya (SCM Analyst) - 70% salary hike offer!";
      if (imgSrc.includes("whatsapp-3")) dummyText = "Rahul (Fresher Graduate) - First MNC offer released!";
      if (imgSrc.includes("whatsapp-4")) dummyText = "Suresh (ERP EBS User) - GOP Config test success!";
      
      this.src = `data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="360" height="580" style="background:#efeae2"><rect x="20" y="40" width="280" height="90" rx="8" fill="%23fff"/><text x="35" y="75" font-family="sans-serif" font-size="14" fill="%23333" font-weight="bold">${dummyText}</text><text x="35" y="105" font-family="sans-serif" font-size="12" fill="%23555">Real screenshot will show here</text><rect x="60" y="160" width="280" height="110" rx="8" fill="%23d9fdd3"/><text x="75" y="195" font-family="sans-serif" font-size="12" fill="%23333" font-weight="bold">Trainer Krishna</text><text x="75" y="222" font-family="sans-serif" font-size="12" fill="%23555">Awesome progress! Practice setups</text><text x="75" y="247" font-family="sans-serif" font-size="12" fill="%23555">are the key to cracking these functional roles.</text><circle cx="180" cy="400" r="30" fill="%2325d366" opacity="0.1"/><text x="180" y="405" text-anchor="middle" font-family="sans-serif" font-size="20" fill="%23128c7e">%20💬%20</text></svg>`;
      this.onerror = null; // Prevent infinite loop
    };
    lightbox.classList.add("active");
  }
}

window.closeLightbox = function() {
  const lightbox = document.getElementById("whatsapp-lightbox");
  if (lightbox) {
    lightbox.classList.remove("active");
  }
}
