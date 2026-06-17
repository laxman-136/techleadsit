// index.js - TechLeadsIT Landing Page Interactive Script (Phase 2 with Testimonial Carousel)

document.addEventListener("DOMContentLoaded", () => {
  initDynamicDates();
  initCountdownTimer();
  initFaqAccordion();
  injectTrackingFieldsToAllForms();
  initFormSubmit();
  initTestimonialCarousel();
});

// 1. DYNAMIC DATE LOGIC (Always displays a fresh upcoming batch date)
let formattedDemoDate = "";
let formattedDemoYear = "";

function initDynamicDates() {
  const now = new Date();
  const currentDay = now.getDay(); // 0: Sun, 1: Mon, 2: Tue, etc.
  
  // Create target date copy
  const target = new Date(now.getTime());
  target.setHours(8, 0, 0, 0); // 8:00 AM IST
  
  // Schedule: Thursday or Saturday
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
    const daysToAdd = (currentDay === 5) ? 6 : 5;
    target.setDate(now.getDate() + daysToAdd);
  }

  const options = { month: 'long', day: 'numeric' };
  formattedDemoDate = target.toLocaleDateString('en-US', options); // e.g. "June 20"
  formattedDemoYear = target.getFullYear(); // e.g. "2026"

  // Fallback if formatting fails
  if (!formattedDemoDate) {
    formattedDemoDate = "June 20";
    formattedDemoYear = "2026";
  }

  updateDynamicDateTexts();
}

function updateDynamicDateTexts() {
  // Update header badge date
  const headerDateBadge = document.querySelector("#header-date-badge");
  if (headerDateBadge) {
    headerDateBadge.textContent = formattedDemoDate;
  }

  // Update Hero CTA Button text
  const heroCtaBtn = document.querySelector("#hero-cta-btn");
  if (heroCtaBtn) {
    heroCtaBtn.textContent = `Book My Free Demo Seat — ${formattedDemoDate}`;
  }

  // Update Section 6 detail card Date text
  const detailDateVal = document.querySelector("#detail-date-val");
  if (detailDateVal) {
    detailDateVal.textContent = `${formattedDemoDate}, ${formattedDemoYear}`;
  }

  // Update Section 7 Form Title
  const formHeadline = document.querySelector("#form-section h2");
  if (formHeadline) {
    formHeadline.textContent = `Reserve Your Seat for the ${formattedDemoDate} Free Demo`;
  }

  // Update FAQ Questions/Answers containing June 13 or June 20 placeholders
  const faqTitles = document.querySelectorAll(".faq-trigger > span");
  faqTitles.forEach(title => {
    if (title.innerHTML.includes("June 13")) {
      title.innerHTML = title.innerHTML.replace("June 13", formattedDemoDate);
    }
    if (title.innerHTML.includes("June 20")) {
      title.innerHTML = title.innerHTML.replace("June 20", formattedDemoDate);
    }
  });
  
  const faqContents = document.querySelectorAll(".faq-content p");
  faqContents.forEach(content => {
    if (content.textContent.includes("June 13")) {
      content.textContent = content.textContent.replace("June 13", formattedDemoDate);
    }
    if (content.textContent.includes("June 20")) {
      content.textContent = content.textContent.replace("June 20", formattedDemoDate);
    }
  });

  // Update Final CTA Button text
  const finalCtaBtn = document.querySelector("#final-cta-btn");
  if (finalCtaBtn) {
    finalCtaBtn.textContent = `Book My Free Demo Seat Now — ${formattedDemoDate}`;
  }
}

// 2. REAL-TIME COUNTDOWN TIMER (Counts down to the calculated demo target date at 8:00 AM IST)
function initCountdownTimer() {
  const countdownClock = document.getElementById("countdown-clock");
  if (!countdownClock) return;

  function updateClock() {
    const now = new Date();
    const currentDay = now.getDay();
    
    // Target date logic mirroring initDynamicDates
    const target = new Date(now.getTime());
    target.setHours(8, 0, 0, 0); // 8:00 AM IST
    
    if (currentDay >= 0 && currentDay <= 2) {
      target.setDate(now.getDate() + (4 - currentDay));
    } else if (currentDay >= 3 && currentDay <= 4) {
      target.setDate(now.getDate() + (6 - currentDay));
    } else {
      const daysToAdd = (currentDay === 5) ? 6 : 5;
      target.setDate(now.getDate() + daysToAdd);
    }
    
    const difference = target.getTime() - now.getTime();
    
    if (difference <= 0) {
      countdownClock.textContent = "00h 00m 00s";
      return;
    }
    
    const days = Math.floor(difference / (1000 * 60 * 60 * 24));
    const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((difference % (1000 * 60)) / 1000);
    
    let clockText = "";
    if (days > 0) {
      clockText += `${days}d `;
    }
    clockText += `${hours.toString().padStart(2, '0')}h ${minutes.toString().padStart(2, '0')}m ${seconds.toString().padStart(2, '0')}s`;
    
    countdownClock.textContent = clockText;
  }
  
  updateClock();
  setInterval(updateClock, 1000);
}

// 3. SECURE MARKETING & SESSION TRACKING CAPTURE ENGINE
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
  data['landing_page'] = window.location.href;
  
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

// 4. FAQ ACCORDION TOGGLING
function initFaqAccordion() {
  const triggers = document.querySelectorAll(".faq-trigger");
  
  triggers.forEach(trigger => {
    trigger.addEventListener("click", () => {
      const item = trigger.parentNode;
      const content = item.querySelector(".faq-content");
      const isActive = item.classList.contains("active");
      
      // Close all other open items
      document.querySelectorAll(".faq-item").forEach(faq => {
        faq.classList.remove("active");
        faq.querySelector(".faq-content").style.maxHeight = null;
      });
      
      // If the clicked item wasn't open, open it
      if (!isActive) {
        item.classList.add("active");
        content.style.maxHeight = content.scrollHeight + "px";
      }
    });
  });
}

// 5. TESTIMONIAL CAROUSEL NAVIGATION ENGINE (Continuous Auto-Play upgrade)
function initTestimonialCarousel() {
  const track = document.getElementById("carousel-track");
  const slides = Array.from(document.querySelectorAll(".carousel-slide"));
  const prevBtn = document.getElementById("carousel-prev");
  const nextBtn = document.getElementById("carousel-next");
  const dots = Array.from(document.querySelectorAll(".dot"));
  const container = document.querySelector(".carousel-container");

  if (!track || slides.length === 0 || !prevBtn || !nextBtn) return;

  let currentIndex = 0;
  let autoPlayTimer = null;
  const autoPlayDelay = 4000; // 4 seconds auto-play interval

  // Calculate items visible per page based on viewport width
  function getItemsPerPage() {
    const width = window.innerWidth;
    if (width > 1024) return 3; // Desktop
    if (width > 640) return 2;  // Tablet
    return 1;                  // Mobile
  }

  function getSlideWidth() {
    const item = slides[0];
    const rect = item.getBoundingClientRect();
    return rect.width;
  }

  function updateCarousel() {
    const itemsPerPage = getItemsPerPage();
    const maxIndex = slides.length - itemsPerPage;
    
    // Bounds check
    if (currentIndex > maxIndex) {
      currentIndex = maxIndex;
    }
    if (currentIndex < 0) {
      currentIndex = 0;
    }

    const slideWidth = getSlideWidth();
    const gap = 24; // Gap between cards in CSS
    const translateAmount = -currentIndex * (slideWidth + gap);
    
    track.style.transform = `translateX(${translateAmount}px)`;
    
    // Update active dot indicator and dynamically hide extra dots
    dots.forEach((dot, index) => {
      if (index <= maxIndex) {
        dot.style.display = "inline-block";
        if (index === currentIndex) {
          dot.classList.add("active");
        } else {
          dot.classList.remove("active");
        }
      } else {
        dot.style.display = "none";
      }
    });

    // Arrows are always active because the carousel loops around continuously
    prevBtn.style.opacity = "1";
    prevBtn.style.cursor = "pointer";
    nextBtn.style.opacity = "1";
    nextBtn.style.cursor = "pointer";
  }

  function nextSlide() {
    const itemsPerPage = getItemsPerPage();
    const maxIndex = slides.length - itemsPerPage;
    if (currentIndex >= maxIndex) {
      currentIndex = 0; // Continuous wrap around to first slide
    } else {
      currentIndex++;
    }
    updateCarousel();
  }

  function prevSlide() {
    const itemsPerPage = getItemsPerPage();
    const maxIndex = slides.length - itemsPerPage;
    if (currentIndex <= 0) {
      currentIndex = maxIndex; // Continuous wrap around to last possible view
    } else {
      currentIndex--;
    }
    updateCarousel();
  }

  // Next Slide Trigger
  nextBtn.addEventListener("click", () => {
    nextSlide();
    resetAutoPlay();
  });

  // Previous Slide Trigger
  prevBtn.addEventListener("click", () => {
    prevSlide();
    resetAutoPlay();
  });

  // Dots Pagination Click Events
  dots.forEach((dot, index) => {
    dot.addEventListener("click", () => {
      const itemsPerPage = getItemsPerPage();
      const maxIndex = slides.length - itemsPerPage;
      if (index <= maxIndex) {
        currentIndex = index;
        updateCarousel();
        resetAutoPlay();
      }
    });
  });

  // Auto-play control functions
  function startAutoPlay() {
    if (!autoPlayTimer) {
      autoPlayTimer = setInterval(nextSlide, autoPlayDelay);
    }
  }

  function stopAutoPlay() {
    if (autoPlayTimer) {
      clearInterval(autoPlayTimer);
      autoPlayTimer = null;
    }
  }

  function resetAutoPlay() {
    stopAutoPlay();
    startAutoPlay();
  }

  // Auto-play state listeners (Pause on hover or touch)
  if (container) {
    container.addEventListener("mouseenter", stopAutoPlay);
    container.addEventListener("mouseleave", startAutoPlay);
    container.addEventListener("touchstart", stopAutoPlay, { passive: true });
    container.addEventListener("touchend", startAutoPlay, { passive: true });
  }

  // Synchronize on Window Resize
  window.addEventListener("resize", () => {
    // Disable slide animation during resize to prevent visual lag
    track.style.transition = "none";
    updateCarousel();
    
    // Re-enable smooth transition after resize completes
    setTimeout(() => {
      track.style.transition = "transform 0.55s cubic-bezier(0.16, 1, 0.3, 1)";
    }, 50);
  });

  // Handle onload layout shifts
  window.addEventListener("load", () => {
    updateCarousel();
  });

  // Initialize Rendering & Auto-Play
  updateCarousel();
  startAutoPlay();
}

// 6. FORM SUBMIT HANDLER & SUCCESS STATE (Hooked to WordPress REST API)
function initFormSubmit() {
  const form = document.getElementById("lead-capture-form");
  if (!form) return;
  
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    
    // Ensure all tracking fields are injected and populated before submission
    injectTrackingFieldsToAllForms();

    // Read input fields
    const name = document.getElementById("user-name").value.trim();
    const phone = document.getElementById("user-phone").value.trim();
    const role = document.getElementById("user-role").value.trim();
    const salary = document.getElementById("user-salary").value;
    const experience = document.getElementById("user-experience").value;
    
    // Validate phone number (10 digit regex check)
    const phoneRegex = /^[0-9]{10}$/;
    if (!phoneRegex.test(phone)) {
      alert("Please enter a valid 10-digit WhatsApp mobile number.");
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.textContent;
    submitBtn.textContent = "Reserving Seat...";
    submitBtn.disabled = true;

    // Compile the payload dynamically from all form fields (including dynamically injected ones)
    const formData = new FormData(form);
    const payload = {
      name,
      phone,
      role,
      salary,
      experience
    };
    formData.forEach((value, key) => {
      payload[key] = value;
    });

    // Send payload to the custom WordPress secure endpoint
    fetch('/wp-json/techleadsit/v1/submit-lead', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    })
    .then(response => {
      if (!response.ok) {
        throw new Error('Server returned error status');
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        renderSuccessCard(name, phone);
      } else {
        alert("Registration failed: " + (data.message || "Please check your details."));
        submitBtn.textContent = originalBtnText;
        submitBtn.disabled = false;
      }
    })
    .catch(error => {
      console.error("Error submitting lead:", error);
      alert("Unable to connect to the server. Please check your internet connection and try again.");
      submitBtn.textContent = originalBtnText;
      submitBtn.disabled = false;
    });
  });

  function renderSuccessCard(name, phone) {
    const formWrapper = document.querySelector(".form-wrapper");
    if (formWrapper) {
      formWrapper.innerHTML = `
        <div class="form-success-card">
          <div class="success-icon-box">
            <svg class="success-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
          
          <h3 class="success-title">Seat Confirmed!</h3>
          <p>We've registered you for the Live SCM Cloud Demo.</p>
          
          <div class="success-details-box">
            <div class="success-item">
              <span>Registrant</span>
              <span>${name}</span>
            </div>
            <div class="success-item">
              <span>WhatsApp</span>
              <span>+91 ${phone}</span>
            </div>
            <div class="success-item">
              <span>Date / Time</span>
              <span>${formattedDemoDate} at 8:00 AM IST</span>
            </div>
          </div>
          
          <div class="success-zoom-box">
            <h4>Zoom Joining Details</h4>
            <p>You can join the webinar directly using the link below:</p>
            <a href="https://zoom.us/j/8125323232" target="_blank" class="success-zoom-link">https://zoom.us/j/8125323232</a>
            <p style="font-size:12px; color:#555555; margin-top:8px;">Meeting ID: 812 5323 232 • Reminders will also be sent on WhatsApp.</p>
          </div>
        </div>
      `;
      
      // Auto smooth scroll to top of success card
      formWrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
}
