// index.js - Interactive Script for RISE V2 Landing Page
// Handles Dynamic Dates, Hero Tabs, Stepper Simulator, Roadmap, Curriculum Accordion, FAQ, OTP form, and CRM submit.

document.addEventListener("DOMContentLoaded", () => {
  initDynamicDates();
  initHeroTabs();
  initStepperConsole();
  initRoadmap();
  initCurriculum();
  initFaqAccordion();
  initOtpVerification();
  initFormSubmit();
  injectTrackingFieldsToAllForms();
});

// 1. DYNAMIC DATE LOGIC
let formattedDemoDate = "";
let formattedDemoYear = "";

function initDynamicDates() {
  const now = new Date();
  const currentDay = now.getDay(); // 0: Sun, 1: Mon, 2: Tue, etc.
  const target = new Date(now.getTime());
  target.setHours(8, 0, 0, 0); // 8:00 AM IST
  
  if (currentDay >= 0 && currentDay <= 2) {
    const daysToAdd = 4 - currentDay; // Sun, Mon, Tue -> Target next Thursday
    target.setDate(now.getDate() + daysToAdd);
  } else if (currentDay >= 3 && currentDay <= 4) {
    const daysToAdd = 6 - currentDay; // Wed, Thu -> Target next Saturday
    target.setDate(now.getDate() + daysToAdd);
  } else {
    const daysToAdd = (currentDay === 5) ? 6 : 5; // Fri, Sat -> Target next Thursday of following week
    target.setDate(now.getDate() + daysToAdd);
  }

  const options = { month: 'long', day: 'numeric' };
  formattedDemoDate = target.toLocaleDateString('en-US', options); // e.g. "June 20"
  formattedDemoYear = target.getFullYear();

  if (!formattedDemoDate) {
    formattedDemoDate = "June 20";
    formattedDemoYear = "2026";
  }

  updateDynamicDateTexts();
}

function updateDynamicDateTexts() {
  const headerDateBadges = document.querySelectorAll("#header-date-badge");
  headerDateBadges.forEach(el => el.textContent = formattedDemoDate);

  const heroCtaBtn = document.querySelector("#hero-cta-btn");
  if (heroCtaBtn) {
    heroCtaBtn.textContent = `Check My Eligibility — ${formattedDemoDate} Batch →`;
  }

  const formDateBadge = document.querySelector("#form-date-badge");
  if (formDateBadge) {
    formDateBadge.textContent = formattedDemoDate;
  }

  const classroomDateBadge = document.querySelector("#classroom-date-badge");
  if (classroomDateBadge) {
    classroomDateBadge.textContent = formattedDemoDate;
  }
}

// 2. HERO CLASSROOM BOARD TABS
function initHeroTabs() {
  const triggers = document.querySelectorAll(".hero-tab-btn");
  const contents = document.querySelectorAll(".hero-tab-content");

  triggers.forEach(btn => {
    btn.addEventListener("click", () => {
      // Deactivate all triggers
      triggers.forEach(t => {
        t.classList.remove("border-primary", "text-primary");
        t.classList.add("border-transparent", "text-neutral-400");
      });
      // Activate clicked trigger
      btn.classList.remove("border-transparent", "text-neutral-400");
      btn.classList.add("border-primary", "text-primary");

      // Hide all contents
      contents.forEach(c => c.classList.add("hidden"));

      // Show matching content
      const tabId = btn.id.replace("-btn", "");
      const targetContent = document.getElementById(tabId);
      if (targetContent) {
        targetContent.classList.remove("hidden");
      }
    });
  });
}

// 3. STEPPER CONSOLE (RISE Articulation Simulator)
const stepperData = {
  1: {
    question: "Explain how you defined Requisitioning Business Unit relationships.",
    options: [
      {
        label: "BU Functions",
        code: "We assign Requisitioning BU and Purchasing BU functions to the business unit.",
        mentor: "Mentor: Correct. Demonstrates structural clarity. SCM BU configurations mapped successfully.",
        score: "9/10"
      },
      {
        label: "Procurement Agent",
        code: "We must define the user as a Procurement Agent with access to both BUs.",
        mentor: "Mentor: Excellent! Crucial step for document creation. Shows real project experience.",
        score: "10/10"
      },
      {
        label: "Financial Options",
        code: "We specify the primary ledger and legal entity mapping.",
        mentor: "Mentor: Good, but this is ledger configuration. Focus on the procurement agent definition.",
        score: "7/10"
      }
    ],
    goal: "Define Procurement Business Functions & Requisitioning BU relationships.",
    title: "Core Configuration Setup"
  },
  2: {
    question: "How do you configure 3-way matching controls?",
    options: [
      {
        label: "2-Way Matching",
        code: "Match price and quantity between Invoice and Purchase Order.",
        mentor: "Mentor: Correct, but 2-way matching does not verify receiving dock receipts.",
        score: "8/10"
      },
      {
        label: "3-Way Matching",
        code: "Introduce warehouse receipt quantity checks to eliminate billing fraud.",
        mentor: "Mentor: Outstanding! Mentions warehouse receipts. Standard practice in MNC setups.",
        score: "10/10"
      },
      {
        label: "4-Way Matching",
        code: "Introduce QA inspection verification on top of receipts and orders.",
        mentor: "Mentor: Correct, but this is 4-way matching. Focus on the standard 3-way setup logic.",
        score: "8/10"
      }
    ],
    goal: "Define Invoice Matching Rules & Payables Options in FSM.",
    title: "BPM Approvals & Controls"
  },
  3: {
    question: "How does the system auto-generate a Drop Ship PO?",
    options: [
      {
        label: "Book Sales Order",
        code: "When a customer order is booked, the Purchase Requisition is created automatically.",
        mentor: "Mentor: Perfect! Shows understanding of SCM orchestration rules.",
        score: "10/10"
      },
      {
        label: "Approve Requisition",
        code: "Approved requisition triggers the Drop Ship Purchase Order.",
        mentor: "Mentor: Correct, but SCM Order Management must trigger the demand first.",
        score: "8/10"
      },
      {
        label: "Verify ASN",
        code: "The supplier sends an Advanced Shipment Notice, creating the customer receipt.",
        mentor: "Mentor: That occurs at the shipping stage, not the creation stage. Review the flow.",
        score: "6/10"
      }
    ],
    goal: "Map Drop Ship sourcing rules & Order Management parameters.",
    title: "Drop Ship Orchestration"
  },
  4: {
    question: "How do you route internal inventory transfers?",
    options: [
      {
        label: "Direct Transfer",
        code: "Instant transfer between subinventories without shipment documentation.",
        mentor: "Mentor: Good for same-site transfers, but inter-org needs shipping papers.",
        score: "7/10"
      },
      {
        label: "Intransit Shipment",
        code: "Create an intransit shipment with receipt confirmations between organizations.",
        mentor: "Mentor: Excellent! Covers transit latency and physical shipping documents.",
        score: "9/10"
      },
      {
        label: "Transfer Order",
        code: "Leverage SCM orchestration to track routing and shipping rules.",
        mentor: "Mentor: Perfect! Shows knowledge of Oracle SCM Transfer Orders.",
        score: "10/10"
      }
    ],
    goal: "Define Inter-Organization shipping networks & shipping parameters.",
    title: "Internal Transfer Routing"
  },
  5: {
    question: "How do you debug a matched invoice line placed on hold?",
    options: [
      {
        label: "Tolerance Hold",
        code: "Check tolerance settings on the Purchase Order style configuration.",
        mentor: "Mentor: Perfect! Matches invoice price variations to PO styles.",
        score: "10/10"
      },
      {
        label: "Receiving Hold",
        code: "Verify if the warehouse receipt quantity matches the invoice quantity.",
        mentor: "Mentor: Correct, standard invoice hold debugging steps.",
        score: "9/10"
      },
      {
        label: "BPM Debugging",
        code: "Check the approval workflow log to verify agent routing status.",
        mentor: "Mentor: Good, but BPM controls approval, not invoice matching rules.",
        score: "7/10"
      }
    ],
    goal: "Analyze Invoice Matching holds & Transaction tolerances.",
    title: "Troubleshooting Holds"
  }
};

function initStepperConsole() {
  setConsoleStep(1);
}

function setConsoleStep(step) {
  // Update progress bar width
  const progress = document.getElementById("console-progress");
  if (progress) {
    progress.style.width = `${(step - 1) * 25}%`;
  }

  // Update step buttons active class
  const buttons = document.querySelectorAll(".step-btn");
  buttons.forEach((btn, idx) => {
    const circle = btn.querySelector(".step-circle");
    const label = btn.querySelector(".step-title");
    const stepIdx = idx + 1;
    
    if (stepIdx === step) {
      btn.classList.add("scale-105");
      circle.className = "step-circle w-11 h-11 rounded-full flex items-center justify-center font-mono font-bold text-xs border-2 transition-all duration-300 relative z-10 bg-primary text-white border-primary shadow-[0_0_15px_rgba(16,115,173,0.4)]";
      label.className = "step-title text-xs font-bold text-primary";
      btn.querySelector("div:last-child").style.opacity = "1";
    } else {
      btn.classList.remove("scale-105");
      circle.className = "step-circle w-11 h-11 rounded-full flex items-center justify-center font-mono font-bold text-xs border-2 transition-all duration-300 relative z-10 bg-white text-neutral-500 border-neutral-250";
      label.className = "step-title text-xs font-bold text-neutral-700";
      btn.querySelector("div:last-child").style.opacity = "0.6";
    }
  });

  // Load step content into left console
  const data = stepperData[step];
  document.getElementById("console-goal-desc").textContent = data.goal;
  
  // Render options buttons
  const wrapper = document.getElementById("console-question-wrapper");
  let optionsHtml = `<span class="text-[10px] font-bold text-neutral-400 uppercase block">${data.question}</span>`;
  optionsHtml += `<div class="flex flex-col gap-2">`;
  
  data.options.forEach((opt, idx) => {
    optionsHtml += `
      <button onclick="submitConsoleAnswer(${step}, ${idx})" class="p-3 rounded-xl border text-left transition-all flex items-start gap-3 bg-white border-neutral-200 hover:bg-neutral-50">
        <div class="w-3.5 h-3.5 rounded-full border-2 flex items-center justify-center mt-0.5 border-neutral-300"></div>
        <div>
          <span class="text-xs font-bold text-neutral-800 block leading-none">${opt.label}</span>
          <span class="text-[9px] text-neutral-400 block mt-0.5">${opt.code.substring(0, 50)}...</span>
        </div>
      </button>
    `;
  });
  optionsHtml += `</div>`;
  wrapper.innerHTML = optionsHtml;

  // Reset terminal log to waiting state
  const term = document.getElementById("console-terminal-text");
  term.innerHTML = `
    <div class="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-3">
      <p class="text-primary font-bold">&gt; INTERVIEW SCENARIO LOADED: Step 0${step}</p>
      <p class="text-neutral-500 pl-3">&gt; Waiting for candidate's answer response...</p>
      <div class="flex items-center gap-2 pl-3 text-neutral-400">
        <span class="w-3.5 h-3.5 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
        <span>Awaiting choice selection...</span>
      </div>
    </div>
  `;
}

// Window exposing for onclick actions
window.setConsoleStep = setConsoleStep;

function submitConsoleAnswer(step, optionIdx) {
  const data = stepperData[step];
  const opt = data.options[optionIdx];

  const term = document.getElementById("console-terminal-text");
  term.innerHTML = `
    <div class="p-5 rounded-2xl bg-neutral-950 border border-neutral-800 space-y-3 font-mono">
      <p class="text-primary font-bold">&gt; INTERVIEW MOCK RESULT</p>
      <div class="space-y-1.5 pl-3">
        <p class="text-neutral-200"><span class="text-neutral-500">Candidate:</span> "${opt.code}"</p>
        <div class="h-px bg-neutral-800 my-2"></div>
        <p class="text-emerald-400 font-bold">&gt; ${opt.mentor}</p>
        <p class="text-amber-400 font-bold">&gt; SCORING: ${opt.score}</p>
      </div>
    </div>
  `;

  // Set active style on option button
  const buttons = document.querySelectorAll("#console-question-wrapper button");
  buttons.forEach((btn, idx) => {
    const circle = btn.querySelector("div > div") || btn.querySelector(".rounded-full");
    if (idx === optionIdx) {
      btn.className = "p-3 rounded-xl border text-left transition-all flex items-start gap-3 bg-white border-primary shadow-sm";
      circle.className = "w-3.5 h-3.5 rounded-full border-2 flex items-center justify-center mt-0.5 border-primary bg-primary text-white";
      circle.innerHTML = `<div class="w-1 h-1 rounded-full bg-white"></div>`;
    } else {
      btn.className = "p-3 rounded-xl border text-left transition-all flex items-start gap-3 bg-white border-neutral-200 hover:bg-neutral-50";
      circle.className = "w-3.5 h-3.5 rounded-full border-2 flex items-center justify-center mt-0.5 border-neutral-300";
      circle.innerHTML = "";
    }
  });
}

window.submitConsoleAnswer = submitConsoleAnswer;

// 4. ROADMAP INTERACTION
const roadmapData = {
  1: {
    heading: "Assess & Intake",
    description: "Personalized intake roadmap analysis",
    elements: [
      { label: "SCM Profile Intake", desc: "Map previous logistics or legacy ERP details." },
      { label: "Transition Plan Matrix", desc: "Identify setup and modules knowledge gaps." },
      { label: "Instance Keys Onboarding", desc: "Access live Oracle SCM Cloud playground instances." }
    ]
  },
  2: {
    heading: "Practice Simulation",
    description: "Live configuration tasks in Oracle Cloud SCM",
    elements: [
      { label: "Configure Enterprise Structures", desc: "Map Ledgers, Legal Entities, Business Units." },
      { label: "Deploy BPM Approval Rules", desc: "Set up 3-way matching and tolerance variables." },
      { label: "Execute Drop Ship Flows", desc: "Test automated inventory order routing rules." }
    ]
  },
  3: {
    heading: "Conditioning Mocks",
    description: "Condition performance under interview stress",
    elements: [
      { label: "Daily Articulation Cards", desc: "Read, map, and prepare structured SCM answers." },
      { label: "Live Presentation Mock", desc: "Speak and explain your setups to working advisors." },
      { label: "Performance Scorecard", desc: "Receive immediate scorecards and keywords checklist." }
    ]
  },
  4: {
    heading: "Guaranteed Placement",
    description: "arranged interview pipeline for MNCs",
    elements: [
      { label: "Resume Keywords Lock", desc: "Optimize CV with live simulation project achievements." },
      { label: "10 Guaranteed MNC Mocks", desc: "Arranged rounds with top hiring consulting partners." },
      { label: "On-the-job Back-support", desc: "Advisory guidance during the first 3-6 months in the role." }
    ]
  }
};

function initRoadmap() {
  setRoadmapStep(1);
}

function setRoadmapStep(step) {
  // Update roadmap buttons
  const buttons = document.querySelectorAll(".roadmap-btn");
  buttons.forEach((btn, idx) => {
    const num = btn.querySelector(".roadmap-num");
    const badge = btn.querySelector(".roadmap-badge");
    const stepIdx = idx + 1;

    if (stepIdx === step) {
      btn.classList.add("border-primary", "shadow-lg", "scale-[1.01]");
      btn.classList.remove("border-neutral-200", "opacity-80");
      num.className = "roadmap-num w-9 h-9 rounded-lg flex items-center justify-center font-mono font-bold text-xs flex-shrink-0 bg-primary text-white";
      if (badge) {
        badge.className = "roadmap-badge text-[9px] font-mono font-bold tracking-wider px-1.5 py-0.2 rounded bg-primary/10 text-primary";
      }
    } else {
      btn.classList.remove("border-primary", "shadow-lg", "scale-[1.01]");
      btn.classList.add("border-neutral-200", "opacity-80");
      num.className = "roadmap-num w-9 h-9 rounded-lg flex items-center justify-center font-mono font-bold text-xs flex-shrink-0 bg-neutral-100 text-neutral-500";
      if (badge) {
        badge.className = "roadmap-badge text-[9px] font-mono font-bold tracking-wider px-1.5 py-0.2 rounded bg-neutral-100 text-neutral-400";
      }
    }
  });

  // Load right roadmap details
  const data = roadmapData[step];
  document.getElementById("roadmap-active-layer").textContent = `ACTIVE_LAYER: 0${step}`;
  
  const display = document.getElementById("roadmap-display-content");
  let html = `<h5 class="text-[10px] font-mono text-neutral-500 uppercase tracking-widest text-center">${data.description}</h5>`;
  html += `<div class="w-full max-w-md flex flex-col items-center gap-3 font-mono text-[10px]">`;
  
  data.elements.forEach((el, idx) => {
    html += `
      <div class="px-5 py-3 rounded-xl bg-neutral-950 border border-neutral-800 shadow-sm text-neutral-250 w-full">
        <span class="text-primary font-bold block mb-1">0${idx+1}. ${el.label}</span>
        <span class="text-[9px] text-neutral-400 block">${el.desc}</span>
      </div>
    `;
    if (idx < 2) {
      html += `<div class="h-3 w-px border-l border-dashed border-primary"></div>`;
    }
  });
  html += `</div>`;
  display.innerHTML = html;
}

window.setRoadmapStep = setRoadmapStep;

// 5. CURRICULUM ACCORDION AND SUBTABS
const curriculumData = {
  1: {
    catId: "PHASE_ID: RISE_P01",
    title: "Phase 1: Real-Time SCM Project Simulation",
    desc: "Understand enterprise setups from scratch. You will configure and test complete procurement, inventory, and order flows on live Oracle Cloud instances.",
    subtabs: {
      1: [
        { icon: "settings", title: "Configure Ledgers & Legal Entities" },
        { icon: "settings", title: "Map Requisitioning and Purchasing Business Units" },
        { icon: "settings", title: "Configure inventory orgs and receiving parameter definitions" }
      ],
      2: [
        { label: "Sourcing Rules", desc: "Define automatic sourcing networks." },
        { label: "Tolerance Parameters", desc: "Manage matching limits for invoice payments." }
      ],
      3: [
        { label: "Live setup verify", desc: "Execute requisition to purchase order workflows." }
      ]
    }
  },
  2: {
    catId: "PHASE_ID: RISE_P02",
    title: "Phase 2: Scenario Articulation & Mocks",
    desc: "Move past concepts. Learn how to explain setups, rules, and troubleshooting scenarios under real interview pressure in daily mock panels.",
    subtabs: {
      1: [
        { icon: "settings", title: "Present Enterprise Structure setups" },
        { icon: "settings", title: "Explain BPM Approval Workflow definitions" },
        { icon: "settings", title: "Demonstrate invoice matched line troubleshooting" }
      ],
      2: [
        { label: "Keyword Conditioning", desc: "Condition articulation around SCM parameters." },
        { label: "Stress Drills", desc: "Answer surprise business scenario questions live." }
      ],
      3: [
        { label: "Articulation Record", desc: "Complete 5 mock presentations for scoring." }
      ]
    }
  },
  3: {
    catId: "PHASE_ID: RISE_P03",
    title: "Phase 3: Resume Building & profile Lock",
    desc: "Build a high-conviction resume that details project achievements, setup configurations, and data migration flows. Lock your profile in TeleCRM.",
    subtabs: {
      1: [
        { icon: "settings", title: "Incorporate setup achievements in CV" },
        { icon: "settings", title: "Optimize profiles for keyword matches" },
        { icon: "settings", title: "Lock candidate profiles in TeleCRM database" }
      ],
      2: [
        { label: "CV Mapping", desc: "Map previous logistics experience to SCM setups." },
        { label: "LinkedIn Lock", desc: "Optimize LinkedIn headline and skills tags." }
      ],
      3: [
        { label: "Profile Sync", desc: "Submit resume and details for placement mapping." }
      ]
    }
  },
  4: {
    catId: "PHASE_ID: RISE_P04",
    title: "Phase 4: MNC Placement Drives & Support",
    desc: "Unlock the arranged interview pipeline. Attend placement rounds with tier-1 MNC hiring partners with active support.",
    subtabs: {
      1: [
        { icon: "settings", title: "Access 10 arranged MNC interview panels" },
        { icon: "settings", title: "Receive back-support during the onboarding phase" },
        { icon: "settings", title: "3 to 6 months of active mentoring in the role" }
      ],
      2: [
        { label: "MNC Placement", desc: "Fast-track pipeline access with hire guarantee." },
        { label: "Salary Negotiations", desc: "Support in maximizing consultant offers." }
      ],
      3: [
        { label: "Placement Release", desc: "Clear first rounds and secure offer letter." }
      ]
    }
  }
};

let activePhase = 1;
let activeSubtab = 1;

function initCurriculum() {
  updateCurriculum();
}

function setCurriculumPhase(phase) {
  activePhase = phase;
  
  // Update phase triggers
  const buttons = document.querySelectorAll(".curr-btn");
  buttons.forEach((btn, idx) => {
    const num = btn.querySelector(".curr-num");
    const label = btn.querySelector("h3");
    const sub = btn.querySelector("span:first-child");
    const icon = btn.querySelector("svg");
    const phaseIdx = idx + 1;

    if (phaseIdx === phase) {
      btn.className = "curr-btn text-left p-4 rounded-2xl border transition-all flex items-start gap-4 group relative bg-white border-primary shadow-md scale-[1.02]";
      num.className = "curr-num w-8 h-8 rounded-xl flex items-center justify-center font-mono font-bold text-xs shrink-0 transition-all bg-primary text-white";
      sub.className = "text-[9px] font-bold uppercase tracking-wider block text-primary";
      label.className = "text-xs font-bold leading-snug truncate text-neutral-900";
      icon.className = "w-4 h-4 text-primary shrink-0";
    } else {
      btn.className = "curr-btn text-left p-4 rounded-2xl border transition-all flex items-start gap-4 group relative bg-neutral-50 border-neutral-100 text-neutral-700 hover:bg-neutral-50/80";
      num.className = "curr-num w-8 h-8 rounded-xl flex items-center justify-center font-mono font-bold text-xs shrink-0 transition-all bg-white border border-neutral-200 text-neutral-400";
      sub.className = "text-[9px] font-bold uppercase tracking-wider block text-neutral-400";
      label.className = "text-xs font-bold leading-snug truncate text-neutral-700";
      icon.className = "w-4 h-4 text-neutral-400 group-hover:translate-x-0.5 group-hover:-translate-y-0.5";
    }
  });

  updateCurriculum();
}

function setCurrSubTab(tabIdx) {
  activeSubtab = tabIdx;
  
  // Update subtab triggers
  const tabs = document.querySelectorAll(".curr-subtab-btn");
  tabs.forEach((tab, idx) => {
    const tabIdxCheck = idx + 1;
    if (tabIdxCheck === tabIdx) {
      tab.className = "curr-subtab-btn py-3 flex items-center justify-center gap-2 text-[10px] font-bold uppercase tracking-wider border-b-2 transition-all border-primary text-primary bg-primary/[0.01]";
    } else {
      tab.className = "curr-subtab-btn py-3 flex items-center justify-center gap-2 text-[10px] font-bold uppercase tracking-wider border-b-2 transition-all border-transparent text-neutral-500 hover:text-neutral-800";
    }
  });

  updateCurriculum();
}

function updateCurriculum() {
  const data = curriculumData[activePhase];
  document.getElementById("curr-cat-id").textContent = data.catId;

  const contentWrapper = document.getElementById("curr-detail-content");
  
  let html = `
    <div class="space-y-2">
      <h3 class="text-base font-bold text-neutral-900 leading-tight">${data.title}</h3>
      <p class="text-xs text-neutral-500 leading-relaxed">${data.desc}</p>
    </div>
    <div class="h-px bg-neutral-150"></div>
  `;

  const subItems = data.subtabs[activeSubtab];
  
  if (activeSubtab === 1) {
    html += `
      <div class="space-y-3">
        <span class="text-[9px] font-bold text-neutral-400 uppercase tracking-widest block">Simulation Map Checklist</span>
        <div class="grid gap-2">
    `;
    subItems.forEach(item => {
      html += `
        <div class="flex items-center gap-2 text-[11px] text-neutral-700 p-2.5 rounded-xl bg-neutral-50 border border-neutral-100">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-primary shrink-0"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
          <span class="font-mono">${item.title}</span>
        </div>
      `;
    });
    html += `</div></div>`;
  } else {
    html += `
      <div class="space-y-3">
        <span class="text-[9px] font-bold text-neutral-400 uppercase tracking-widest block">Practice Scopes</span>
        <div class="grid gap-3">
    `;
    subItems.forEach(item => {
      html += `
        <div class="p-4 rounded-xl bg-neutral-50 border border-neutral-100">
          <span class="text-xs font-bold text-neutral-800 block">${item.label}</span>
          <span class="text-[10px] text-neutral-400 block mt-1 leading-relaxed">${item.desc || 'Complete target scenario tasks.'}</span>
        </div>
      `;
    });
    html += `</div></div>`;
  }

  contentWrapper.innerHTML = html;
}

window.setCurriculumPhase = setCurriculumPhase;
window.setCurrSubTab = setCurrSubTab;

// 6. FAQ ACCORDION TOGGLE
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
      
      // Open if not active
      if (!isActive) {
        item.classList.add("active");
        content.style.maxHeight = content.scrollHeight + "px";
      }
    });
  });
}

// 7. OTP EMAIL VERIFICATION
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
    otpStatus.style.color = "#666666";

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
        otpStatus.textContent = "OTP sent! Check your inbox (and spam folder).";
        otpStatus.style.color = "#27b376";
        otpInputGroup.style.display = "block";
        sendOtpBtn.textContent = "Resend OTP";
        sendOtpBtn.disabled = false;
      } else {
        throw new Error(res.body.message || "Failed to send OTP.");
      }
    })
    .catch(err => {
      console.error(err);
      otpStatus.textContent = err.message || "Error sending code. Try again.";
      otpStatus.style.color = "#d41816";
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
        otpStatus.style.color = "#27b376";
        otpInputGroup.style.display = "none";
        sendOtpBtn.style.display = "none";
        emailInput.readOnly = true;
        
        // Enable submit button
        submitBtn.disabled = false;
        submitBtn.style.opacity = "1";
        submitBtn.style.cursor = "pointer";
        submitBtn.textContent = "Submit Profile & Check Eligibility →";
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

// 8. LEAD SUBMISSION & SUCCESS STATE
function initFormSubmit() {
  const form = document.getElementById("lead-capture-form");
  if (!form) return;
  
  form.addEventListener("submit", (e) => {
    e.preventDefault();
    injectTrackingFieldsToAllForms();

    const name = document.getElementById("user-name").value.trim();
    const email = document.getElementById("user-email").value.trim();
    const phone = document.getElementById("user-phone").value.trim();
    const role = document.getElementById("user-role").value;
    const salary = document.getElementById("user-salary").value;
    const experience = document.getElementById("user-experience").value;
    
    if (!name || !email || !phone || !role || !experience || !salary) {
      alert("Please fill in all required fields.");
      return;
    }

    const submitBtn = document.getElementById("submit-booking-btn");
    submitBtn.textContent = "Submitting Application...";
    submitBtn.disabled = true;

    const formData = new FormData(form);
    const payload = {
      name, email, phone, role, salary, experience
    };
    formData.forEach((value, key) => {
      payload[key] = value;
    });

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
        renderSuccessCard(name, phone, role);
      } else {
        alert("Registration failed: " + (data.message || "Try again."));
        submitBtn.textContent = "Submit Profile & Check Eligibility →";
        submitBtn.disabled = false;
      }
    })
    .catch(error => {
      console.error("Error submitting lead:", error);
      alert("Unable to register: " + error.message);
      submitBtn.textContent = "Submit Profile & Check Eligibility →";
      submitBtn.disabled = false;
    });
  });

  function renderSuccessCard(name, phone, role) {
    const formWrapper = document.querySelector(".form-wrapper");
    if (formWrapper) {
      formWrapper.innerHTML = `
        <div class="p-6 text-center space-y-4">
          <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="w-6 h-6"><polyline points="20 6 9 17 4 12"></polyline></svg>
          </div>
          <h3 class="text-xl font-display font-bold text-neutral-900 uppercase">Application Submitted!</h3>
          <p class="text-xs text-neutral-500 leading-relaxed">Your SCM profile eligibility check has been registered successfully.</p>
          
          <div class="bg-neutral-50 p-4 rounded-xl border border-neutral-100 text-left text-xs space-y-2 font-medium">
            <div class="flex justify-between"><span>Name:</span><span class="text-neutral-800 font-bold">${name}</span></div>
            <div class="flex justify-between"><span>WhatsApp:</span><span class="text-neutral-800 font-bold">+91 ${phone}</span></div>
            <div class="flex justify-between"><span>Category:</span><span class="text-neutral-800 font-bold">${role}</span></div>
          </div>
          
          <div class="bg-primary/5 p-4 rounded-xl border border-primary/20 text-center space-y-2">
            <h4 class="text-xs font-bold text-primary uppercase">Admissions Review In Progress</h4>
            <p class="text-[11px] text-neutral-500 leading-relaxed">An Admissions Officer will review your background and message you on WhatsApp within 24 hours to schedule your SCM simulation evaluation.</p>
            <a href="https://wa.me/918125323232?text=Hi,%20I%20have%20submitted%20my%20eligibility%20form%20for%20RISE.%20My%20name%20is%20${encodeURIComponent(name)}." target="_blank" class="inline-block mt-2 text-xs font-bold text-primary hover:underline">Message Admissions Team on WhatsApp →</a>
          </div>
        </div>
      `;
      formWrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
}

// 9. MARKETING COOKIE & UTM CAPTURE ENGINE
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
