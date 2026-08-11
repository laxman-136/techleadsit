// RISE V4 Landing Page Interactive Logic

document.addEventListener("DOMContentLoaded", () => {
  initDynamicDates();
  initHeaderScroll();
  initHeroTabs();
  initSimulator();
  initCurriculum();
  initFAQ();
  initMobileCTA();
  initFormValidation();
});

// 1. DYNAMIC BATCH DATES LOGIC
function initDynamicDates() {
  const dateElements = [
    document.getElementById("hero-dynamic-date-cohort"),
    document.getElementById("form-dynamic-date-batch")
  ];
  
  const now = new Date();
  const currentDay = now.getDay(); // 0: Sun, 1: Mon, ..., 6: Sat
  const targetDate = new Date(now.getTime());
  
  if (currentDay >= 0 && currentDay <= 2) {
    // Sun, Mon, Tue -> Target next Thursday
    const daysToAdd = 4 - currentDay;
    targetDate.setDate(now.getDate() + daysToAdd);
  } else if (currentDay >= 3 && currentDay <= 4) {
    // Wed, Thu -> Target next Saturday
    const daysToAdd = 6 - currentDay;
    targetDate.setDate(now.getDate() + daysToAdd);
  } else {
    // Fri, Sat -> Target next Thursday of the following week
    const daysToAdd = (currentDay === 5) ? 6 : 5;
    targetDate.setDate(now.getDate() + daysToAdd);
  }
  
  const options = { month: 'long', day: 'numeric' };
  const formattedDate = targetDate.toLocaleDateString('en-US', options); // e.g. "July 16"
  
  dateElements.forEach(el => {
    if (el) {
      el.textContent = `${formattedDate} Batch`;
    }
  });
}

// 2. HEADER SCROLL EFFECT
function initHeaderScroll() {
  const header = document.getElementById("main-header");
  if (!header) return;
  
  window.addEventListener("scroll", () => {
    if (window.scrollY > 50) {
      header.classList.add("scrolled");
    } else {
      header.classList.remove("scrolled");
    }
  });
}

// 3. HERO visual BOARD TABS
function initHeroTabs() {
  const tabs = [
    { btn: document.getElementById("btn-hero-del"), pane: document.getElementById("pane-hero-del") },
    { btn: document.getElementById("btn-hero-syl"), pane: document.getElementById("pane-hero-syl") },
    { btn: document.getElementById("btn-hero-sch"), pane: document.getElementById("pane-hero-sch") }
  ];
  
  tabs.forEach(tab => {
    if (!tab.btn || !tab.pane) return;
    
    tab.btn.addEventListener("click", () => {
      // Deactivate all
      tabs.forEach(t => {
        t.btn.classList.remove("active");
        t.pane.classList.remove("active");
      });
      
      // Activate clicked
      tab.btn.classList.add("active");
      tab.pane.classList.add("active");
    });
  });
}

// 4. INTERACTIVE STEPPER (SCM ARTICULATION SIMULATOR)
const simulatorData = {
  1: {
    title: "Enterprise Setup",
    label: "Scenario: Core Config",
    question: "Explain how you defined Requisitioning Business Unit relationships in your SCM project.",
    target: "Demonstrate structural clarity and SCM BU functions.",
    options: [
      {
        text: "We assign Requisitioning BU and Purchasing BU functions to the business unit.",
        verdict: "Acceptable. Shows structural knowledge of Oracle Cloud SCM BUs.",
        score: "8/10",
        code: "Define Business Unit -> Assign Business Unit Functions -> Requisitioning & Procurement functions checked."
      },
      {
        text: "We define the user as a Procurement Agent with access to both BUs.",
        verdict: "Outstanding! This is the crucial step required for document security. Shows real-project experience.",
        score: "10/10",
        code: "Manage Procurement Agent -> Associate Agent with Requisitioning BU & Procurement BU -> Configure Document Actions."
      },
      {
        text: "We specify Ledger and Legal Entity mapping in Financial Options.",
        verdict: "Good, but this is General Ledger / Payables structure rather than Procurement BU functions directly.",
        score: "6/10",
        code: "Configure Common Options for Payables and Procurement -> Legal Entity association setup completed."
      }
    ]
  },
  2: {
    title: "Match Controls",
    label: "Scenario: Invoice Matching",
    question: "How do you configure 3-way matching controls for inventory purchases?",
    target: "Verify understanding of receipts and billing validation controls.",
    options: [
      {
        text: "Match unit price and quantities between Invoice and Purchase Order.",
        verdict: "Incorrect. This is 2-way matching. It does not verify warehouse receipts.",
        score: "6/10",
        code: "Set Invoice Matching Level: 2-Way -> Matches Invoice price to PO price and Invoice qty to PO qty."
      },
      {
        text: "Verify matching between Invoice, Purchase Order, and Warehouse Receipts.",
        verdict: "Excellent! Standard practice in MNC SCM setups to prevent billing leakage.",
        score: "10/10",
        code: "Set Invoice Matching Level: 3-Way -> PO Price, PO Qty, and Receipt Qty validated before payment release."
      },
      {
        text: "Verify price, PO quantities, receipts, and quality inspection approval.",
        verdict: "Correct, but this defines 4-way matching (with Inspection). Exceeds simple 3-way matching specifications.",
        score: "8/10",
        code: "Set Invoice Matching Level: 4-Way -> PO, Receipt, and Quality Inspection quantities matched."
      }
    ]
  },
  3: {
    title: "Drop Ship",
    label: "Scenario: Drop Ship PO",
    question: "How does the system auto-generate a Drop Ship Purchase Order when a Sales Order is booked?",
    target: "Explain order orchestration rules and demand sourcing mapping.",
    options: [
      {
        text: "When Sales Order is booked, the Purchase Requisition is created automatically.",
        verdict: "Correct. Shows understanding of global order orchestration routing.",
        score: "9/10",
        code: "Book Sales Order -> Trigger DOO (Distributed Order Orchestration) -> Auto-create Purchase Requisition."
      },
      {
        text: "An approved Requisition triggers the Drop Ship Purchase Order automatically.",
        verdict: "Correct in isolation, but fails to account for how Order Management routes the demand to Sourcing.",
        score: "7/10",
        code: "Submit Requisition -> Sourcing Engine -> Generate PO. (Missing initial O2C linkage)."
      },
      {
        text: "Orchestration process checks Drop Ship sourcing rules to release both SO and PO.",
        verdict: "Excellent! Demonstrates expert knowledge of cross-module setups (OM to Procurement).",
        score: "10/10",
        code: "Define Sourcing Rule (Drop Ship Type) -> Map in Assignment Set -> Order Management routes demand -> Procurement agent auto-creates PO."
      }
    ]
  },
  4: {
    title: "Transfers",
    label: "Scenario: Internal Transfers",
    question: "How do you route internal inventory transfers between two distant warehouses?",
    target: "Explain shipping networks, transit latency, and documentation.",
    options: [
      {
        text: "Create a Direct Transfer transaction between the subinventories.",
        verdict: "Incomplete. Direct transfers are instantaneous and don't produce transit shipping documents or track delay.",
        score: "6/10",
        code: "Perform Subinventory Transfer -> Immediate quantity adjustment (no transit document created)."
      },
      {
        text: "Configure an Intransit Shipment with shipping network rules.",
        verdict: "Excellent! Accounting for transit latency and generating shipment notes is vital for distant hubs.",
        score: "9/10",
        code: "Define Interorganization Shipping Network -> Set Transfer Type: Intransit -> Ship inventory -> Receive destination."
      },
      {
        text: "Leverage Internal Material Transfer (IMT) routed via SCM Orchestration.",
        verdict: "Flawless! Demonstrates complete mastery of modern Oracle Cloud SCM Transfer Orders.",
        score: "10/10",
        code: "Generate Min-Max Planning demand -> Auto-create Transfer Order -> Route via Inventory Shipping -> Track Transit -> Receive."
      }
    ]
  },
  5: {
    title: "Troubleshoot",
    label: "Scenario: Resolving Holds",
    question: "A Purchase Order is approved, but the Supplier Invoice is stuck on Price Hold. How do you resolve this?",
    target: "Demonstrate structured troubleshooting and validation rules.",
    options: [
      {
        text: "We manually delete the invoice and ask the accounts team to recreate it with the correct price.",
        verdict: "Weak practice. Bypasses audit tracking and does not fix the underlying configuration issue.",
        score: "5/10",
        code: "Cancel Invoice -> Re-enter Invoice with matching PO prices manually."
      },
      {
        text: "Verify tolerance limits under Invoice Matching configuration and adjust price difference.",
        verdict: "Outstanding! Identifies the configuration cause and follows standard SCM resolving path.",
        score: "10/10",
        code: "Review Invoice Tolerances -> Identify price variance percent -> Approve variance or update PO price via Change Order."
      },
      {
        text: "We override the hold flag in Payables Options without changing PO values.",
        verdict: "Temporary workaround. Resolves payment block but leaves the PO discrepancy unresolved in inventory logs.",
        score: "7/10",
        code: "Force Release Hold -> Release hold status flag -> Block remains open on Purchase Order closure."
      }
    ]
  }
};

let activeSimStep = 1;

function initSimulator() {
  loadSimStep(1);
}

function loadSimStep(stepIdx) {
  activeSimStep = stepIdx;
  const data = simulatorData[stepIdx];
  if (!data) return;
  
  // Update step navigation buttons active classes
  const buttons = document.querySelectorAll(".sim-step-btn");
  buttons.forEach((btn, idx) => {
    if (idx + 1 === stepIdx) {
      btn.classList.add("active");
    } else {
      btn.classList.remove("active");
    }
  });
  
  // Update progress bar fill
  const progressLine = document.getElementById("sim-progress-line");
  if (progressLine) {
    const progressWidth = ((stepIdx - 1) / 4) * 90; // Calculate percentage (0 to 90%)
    progressLine.style.width = `${progressWidth}%`;
  }
  
  // Update Question Panel
  document.getElementById("sim-scenario-lbl").textContent = data.label;
  document.getElementById("sim-question-text").textContent = data.question;
  document.getElementById("sim-target-text").textContent = data.target;
  
  // Render Option buttons
  const optionsWrapper = document.getElementById("sim-options-wrapper");
  optionsWrapper.innerHTML = "";
  
  data.options.forEach((opt, idx) => {
    const btn = document.createElement("button");
    btn.className = "sim-option-btn";
    btn.innerHTML = `<span style="font-weight: 700; color: var(--color-primary); margin-right: 0.5rem;">0${idx+1}.</span> ${opt.text}`;
    btn.addEventListener("click", () => {
      // Set active option button
      const allOptBtns = optionsWrapper.querySelectorAll(".sim-option-btn");
      allOptBtns.forEach(b => b.classList.remove("active"));
      btn.classList.add("active");
      
      // Update terminal output logs
      document.getElementById("term-candidate-code").innerHTML = `<span style="color: var(--color-accent); font-weight: bold;">Config Executed:</span><br>${opt.code}`;
      document.getElementById("term-evaluator-verdict").textContent = opt.verdict;
      document.getElementById("term-score-value").textContent = opt.score;
      
      // Highlight score color based on value
      const scoreVal = parseInt(opt.score);
      const scoreEl = document.getElementById("term-score-value");
      if (scoreVal >= 9) {
        scoreEl.style.color = "var(--color-success)";
      } else if (scoreVal >= 7) {
        scoreEl.style.color = "var(--color-primary)";
      } else {
        scoreEl.style.color = "var(--color-danger)";
      }
    });
    optionsWrapper.appendChild(btn);
  });
  
  // Reset Terminal Output state
  document.getElementById("term-candidate-code").textContent = "Select a response option on the left to evaluate articulation.";
  document.getElementById("term-evaluator-verdict").textContent = "Waiting for candidate selection...";
  document.getElementById("term-score-value").textContent = "--/10";
  document.getElementById("term-score-value").style.color = "var(--text-secondary)";
}

// Make loadSimStep globally accessible
window.loadSimStep = loadSimStep;


// 5. 45-DAY CURRICULUM SELECTOR LOGIC
const curriculumData = {
  1: {
    code: "PHASE_SCOPE: RISE_P01",
    title: "Foundation & Practice Discipline",
    desc: "Module-wise setup revision across all core modules with daily scenario articulation drills. Candidates learn how to navigate structures, complete setups, and run transactions.",
    syllabus: [
      "Configure Ledgers, Legal Entities, and Business Unit functions.",
      "Set up Business Unit relationships (Requisitioning vs. Procurement BUs).",
      "Configure Inventory Organizations, subinventories, and receiving rules.",
      "Map document sequencing parameters and common SCM profiles."
    ],
    scenarios: [
      { name: "BU Function Assignment Rules", desc: "Understanding when to isolate a BU vs. when to share procurement relationships across segments." },
      { name: "Unit-of-Measure (UOM) Standard Conversions", desc: "Defining class-specific conversions for purchase quantities vs. stocking quantities." }
    ],
    labs: [
      "Execute complete procure-to-pay (P2P) validation on live Oracle Cloud instance.",
      "Configure approval rule logic for Purchase Requisitions via FSM."
    ]
  },
  2: {
    code: "PHASE_SCOPE: RISE_P02",
    title: "Real-Time Project Simulation",
    desc: "Simulate a live implementation project from Requirement Gathering to Go-Live. Candidates own SCM project deliverables, write design approach sheets, and simulate ticketing flows.",
    syllabus: [
      "Gathering business requirements and drafting BRD / Configuration documents.",
      "Setting up complex integrations (FBDI templates, REST web services).",
      "Designing approval hierarchies (BPM Worklist and approval groups).",
      "Executing System Integration Testing (SIT) and User Acceptance (UAT)."
    ],
    scenarios: [
      { name: "Orchestrated Drop Ship PO routing", desc: "Configuring rules to trigger drop-ship PO demand creation automatically from a sales order." },
      { name: "Inter-company transit shipping nets", desc: "Routing internal material transfers with shipping documents, freight charges, and delays." }
    ],
    labs: [
      "Build a complete functional configuration checksheet (BRD mapping).",
      "Simulate support ticketing queue inside Jira for resolving matching hold variance."
    ]
  },
  3: {
    code: "PHASE_SCOPE: RISE_P03",
    title: "Interview Conditioning",
    desc: "Move past definitions. Participate in daily 1:1 and panel mock interviews testing SCM configurations, integrations, and troubleshooting scenarios under stress.",
    syllabus: [
      "Daily mock panel rounds with ex-consultants checking setup logic.",
      "Building articulation conditioning across 4 pillars: Clarity, Structure, confidence, and Recovery.",
      "Optimizing resumes with SCM project stories and keyword-rich layouts.",
      "Synching recruiter-magnet LinkedIn profiles for direct MNC discovery."
    ],
    scenarios: [
      { name: "Surprise SCM business failures", desc: "Troubleshooting receipt quantity variances, invoice price holds, and orchestration routing errors live." },
      { name: "Articulation under cross-questioning", desc: "Structuring answers to open-ended scenario questions without hesitating or freezing." }
    ],
    labs: [
      "Participate in 5 evaluated panel mock drives with detailed scorecards.",
      "Lock and upload your approved consultant profile inside our CRM database."
    ]
  },
  4: {
    code: "PHASE_SCOPE: RISE_P04",
    title: "Placement & Market Readiness",
    desc: "Unlocking the arranged interview pipeline with tier-1 partner networks. Secure drives, clear rounds, and receive active support during your onboarding phase.",
    syllabus: [
      "Profile release to MNC hiring partners with active SCM bootcamps.",
      "Coordinating 10 arranged interview drives under written refund guarantee.",
      "Maximizing salary packages through expert counter-offer coaching.",
      "3 to 6 months of active on-the-job mentor back-support inside your role."
    ],
    scenarios: [
      { name: "Client Panel Articulation", desc: "Navigating final round manager/client panels where real project credibility is evaluated." },
      { name: "On-the-job setup troubleshooting", desc: "Consulting with RISE mentors to resolve live configuration issues in your new role." }
    ],
    labs: [
      "Attend arranged recruitment drives with partner MNC panels.",
      "Access on-the-job support portal for ticket resolution advice."
    ]
  }
};

let currentPhase = 1;
let currentSubTab = "syllabus";

function initCurriculum() {
  renderCurriculum();
}

function switchCurriculumPhase(phaseIdx) {
  currentPhase = phaseIdx;
  
  // Update sidebar buttons active classes
  const buttons = document.querySelectorAll(".curr-nav-btn");
  buttons.forEach((btn, idx) => {
    if (idx + 1 === phaseIdx) {
      btn.classList.add("active");
    } else {
      btn.classList.remove("active");
    }
  });
  
  // Render
  renderCurriculum();
}

function switchSubTab(tabName) {
  currentSubTab = tabName;
  
  // Update detail tab buttons active classes
  const tabBtns = document.querySelectorAll(".curr-detail-tab-btn");
  tabBtns.forEach(btn => {
    if (btn.id === `btn-sub-${tabName.substring(0, 3)}`) {
      btn.classList.add("active");
    } else {
      btn.classList.remove("active");
    }
  });
  
  // Render list
  renderCurriculumList();
}

function renderCurriculum() {
  const data = curriculumData[currentPhase];
  if (!data) return;
  
  document.getElementById("curr-meta-code").textContent = data.code;
  document.getElementById("curr-detail-title").textContent = data.title;
  document.getElementById("curr-detail-desc").textContent = data.desc;
  
  renderCurriculumList();
}

function renderCurriculumList() {
  const data = curriculumData[currentPhase];
  const listWrapper = document.getElementById("curr-items-list");
  const listTitle = document.getElementById("curr-list-title");
  if (!listWrapper || !listTitle || !data) return;
  
  listWrapper.innerHTML = "";
  
  if (currentSubTab === "syllabus") {
    listTitle.textContent = "Simulation Map Checklist";
    data.syllabus.forEach(item => {
      const el = document.createElement("div");
      el.className = "curr-detail-item";
      el.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="lucide lucide-settings"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
        <span>${item}</span>
      `;
      listWrapper.appendChild(el);
    });
  } else if (currentSubTab === "scenarios") {
    listTitle.textContent = "Interview Scenario Focus";
    const container = document.createElement("div");
    container.className = "curr-detail-checklist";
    
    data.scenarios.forEach(item => {
      const el = document.createElement("div");
      el.className = "curr-check-card";
      el.innerHTML = `
        <span class="curr-check-lbl">${item.name}</span>
        <span class="curr-check-desc">${item.desc}</span>
      `;
      container.appendChild(el);
    });
    listWrapper.appendChild(container);
  } else if (currentSubTab === "labs") {
    listTitle.textContent = "Hands-On Practice Labs";
    data.labs.forEach(item => {
      const el = document.createElement("div");
      el.className = "curr-detail-item";
      el.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="lucide lucide-check-circle"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        <span>${item}</span>
      `;
      listWrapper.appendChild(el);
    });
  }
}

// Make curriculum functions globally accessible
window.switchCurriculumPhase = switchCurriculumPhase;
window.switchSubTab = switchSubTab;


// 6. FAQ ACCORDION LOGIC
function initFAQ() {
  const faqItems = document.querySelectorAll(".faq-item");
  
  faqItems.forEach(item => {
    const trigger = item.querySelector(".faq-trigger");
    const content = item.querySelector(".faq-content");
    
    if (!trigger || !content) return;
    
    trigger.addEventListener("click", () => {
      const isActive = item.classList.contains("active");
      
      // Close all first
      faqItems.forEach(i => {
        i.classList.remove("active");
        const c = i.querySelector(".faq-content");
        if (c) c.style.maxHeight = null;
      });
      
      // Open clicked if it wasn't open
      if (!isActive) {
        item.classList.add("active");
        content.style.maxHeight = content.scrollHeight + "px";
      }
    });
  });
}


// 7. PERSISTENT MOBILE CTA BAR REVEAL
function initMobileCTA() {
  const mobileBar = document.getElementById("mobile-cta-bar");
  if (!mobileBar) return;
  
  window.addEventListener("scroll", () => {
    // Show bar after scrolling 400px down, hide it if near the top
    if (window.scrollY > 400) {
      mobileBar.classList.add("active");
    } else {
      mobileBar.classList.remove("active");
    }
  });
}


// 8. REGISTER INTAKE FORM & MOCK OTP VERIFICATION
function initFormValidation() {
  const form = document.getElementById("lead-capture-form");
  const sendOtpBtn = document.getElementById("btn-send-otp");
  const verifyOtpBtn = document.getElementById("btn-verify-otp");
  const emailInput = document.getElementById("input-email");
  const otpInput = document.getElementById("input-otp");
  const otpGroup = document.getElementById("group-otp-verify");
  const otpStatusMsg = document.getElementById("msg-otp-status");
  const submitBtn = document.getElementById("btn-submit-lead");
  
  if (!form) return;
  
  let isEmailVerified = false;
  
  // Send OTP
  sendOtpBtn.addEventListener("click", () => {
    const emailVal = emailInput.value.trim();
    if (!emailVal || !emailInput.checkValidity()) {
      otpStatusMsg.className = "form-status-msg error";
      otpStatusMsg.textContent = "Please enter a valid email address first.";
      otpStatusMsg.style.display = "block";
      return;
    }
    
    otpStatusMsg.className = "form-status-msg info";
    otpStatusMsg.textContent = "Sending 6-digit OTP code to email...";
    otpStatusMsg.style.display = "block";
    sendOtpBtn.disabled = true;
    sendOtpBtn.style.opacity = 0.5;
    
    // Simulate API call delay
    setTimeout(() => {
      otpGroup.style.display = "flex";
      otpStatusMsg.className = "form-status-msg success";
      otpStatusMsg.textContent = `OTP code sent. Use test code: 123456`;
      sendOtpBtn.disabled = false;
      sendOtpBtn.style.opacity = 1;
    }, 1000);
  });
  
  // Verify OTP
  verifyOtpBtn.addEventListener("click", () => {
    const otpVal = otpInput.value.trim();
    if (otpVal === "123456" || (otpVal.length === 6 && !isNaN(otpVal))) {
      isEmailVerified = true;
      otpStatusMsg.className = "form-status-msg success";
      otpStatusMsg.textContent = "Email verified successfully! Slot unlocked.";
      otpGroup.style.display = "none";
      emailInput.readOnly = true;
      sendOtpBtn.style.display = "none";
      
      // Enable submit button
      submitBtn.disabled = false;
      submitBtn.style.opacity = 1;
      submitBtn.style.cursor = "pointer";
      submitBtn.textContent = "Lock My SCM Sandbox Slot";
    } else {
      otpStatusMsg.className = "form-status-msg error";
      otpStatusMsg.textContent = "Invalid OTP code. Please enter 123456 to test.";
    }
  });
  
  // Form Submit
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    if (!isEmailVerified) {
      otpStatusMsg.className = "form-status-msg error";
      otpStatusMsg.textContent = "Please verify your email address to submit.";
      otpStatusMsg.style.display = "block";
      return;
    }
    
    submitBtn.disabled = true;
    submitBtn.style.opacity = 0.5;
    submitBtn.textContent = "Booking Sandbox Slot...";
    
    setTimeout(() => {
      alert("Eligibility Check Completed!\nYour sandbox slot is reserved.\nAdmissions advisor will contact you on WhatsApp shortly.");
      form.reset();
      
      // Reset verification state
      isEmailVerified = false;
      emailInput.readOnly = false;
      sendOtpBtn.style.display = "inline-flex";
      otpStatusMsg.style.display = "none";
      submitBtn.textContent = "Verify Email to Lock Slot";
      submitBtn.disabled = true;
      submitBtn.style.opacity = 0.6;
      submitBtn.style.cursor = "not-allowed";
    }, 1200);
  });
}

// 9. PERSONA SELECTION JUMP
function selectPersonaInForm(personaName) {
  const selectBg = document.getElementById("select-bg");
  if (selectBg) {
    // Map card triggers to select values
    if (personaName === "SCM Domain Professional" || personaName === "Legacy SCM Consultant" || personaName === "EBS Migrant") {
      selectBg.value = personaName;
    }
  }
  
  // Scroll smoothly to form section
  const formSection = document.getElementById("eligibility-form-section");
  if (formSection) {
    formSection.scrollIntoView({ behavior: "smooth" });
  }
}

// Make selectPersonaInForm globally accessible
window.selectPersonaInForm = selectPersonaInForm;
