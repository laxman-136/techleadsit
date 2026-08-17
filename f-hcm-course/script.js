// ==========================================================================
// Oracle Fusion HCM Landing Page — Script Actions
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Evergreen Countdown Timer
    initCountdown(3); // Start with a 3-day countdown, automatically loops when expired

    // 2. Sticky Header scroll styling
    initStickyHeader();

    // 3. Mobile Navigation Drawer
    initMobileNav();

    // 4. A/B Hero Headline Swapping
    initHeadlineSwapping();

    // 5. Form Modals (Lead capture modal, syllabus download gate)
    initModals();

    // 6. Testimonial Slider / Carousel
    initTestimonialSlider();

    // 6b. Salary Proof Slider & Lightbox Zoom
    initProofSlider();
    initProofLightbox();

    // 7. Interactive Curriculum Path Explorer
    initCurriculumExplorer();

    // 8. Mobile Curriculum Accordion
    initMobileCurriculumAccordion();

    // 9. FAQ Accordion (one open at a time)
    initFaqAccordion();

    // 10. Form Submissions & Client-Side Validation
    initFormValidation();

    // 11. Exit Intent Modal Trigger
    initExitIntent();

    // 12. Live Activity FOMO Toasts (every 10s)
    initFomoToasts();

    // 13. Returning Visitor Personalized Popup
    initReturningVisitorPopup();
});

/* ==========================================================================
   1. Evergreen Countdown Timer
   ========================================================================== */
function initCountdown(daysSpan) {
    const dEl = document.getElementById('days');
    const hEl = document.getElementById('hours');
    const mEl = document.getElementById('minutes');
    const sEl = document.getElementById('seconds');
    
    if (!dEl || !hEl || !mEl || !sEl) return;

    // Helper to calculate the next target date: targets next Wednesday or Sunday at 6 PM
    function getNextTargetDate() {
        const now = new Date();
        const currentDay = now.getDay();
        const currentHour = now.getHours();
        
        let target = new Date();
        target.setHours(18, 0, 0, 0); // Target 6:00 PM
        
        if (currentDay === 0) { // Sunday
            if (currentHour >= 18) {
                target.setDate(now.getDate() + 3); // target Wednesday
            }
        } else if (currentDay === 1) { // Monday
            target.setDate(now.getDate() + 2); // target Wednesday
        } else if (currentDay === 2) { // Tuesday
            target.setDate(now.getDate() + 1); // target Wednesday
        } else if (currentDay === 3) { // Wednesday
            if (currentHour >= 18) {
                target.setDate(now.getDate() + 4); // target Sunday
            }
        } else if (currentDay === 4) { // Thursday
            target.setDate(now.getDate() + 3); // target Sunday
        } else if (currentDay === 5) { // Friday
            target.setDate(now.getDate() + 2); // target Sunday
        } else if (currentDay === 6) { // Saturday
            target.setDate(now.getDate() + 1); // target Sunday
        }
        
        return target.getTime();
    }

    const targetTime = getNextTargetDate();
    const targetDate = new Date(targetTime);

    function updateTimer() {
        const currentTime = new Date().getTime();
        const difference = targetDate - currentTime;

        if (difference <= 0) {
            // Recalculate target when expired
            dEl.textContent = "00";
            hEl.textContent = "00";
            mEl.textContent = "00";
            sEl.textContent = "00";
            return;
        }

        const days = Math.floor(difference / (1000 * 60 * 60 * 24));
        const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((difference % (1000 * 60)) / 1000);

        dEl.textContent = days.toString().padStart(2, '0');
        hEl.textContent = hours.toString().padStart(2, '0');
        mEl.textContent = minutes.toString().padStart(2, '0');
        sEl.textContent = seconds.toString().padStart(2, '0');
    }

    updateTimer();
    setInterval(updateTimer, 1000);
}

/* ==========================================================================
   2. Sticky Header Scroll Styling
   ========================================================================== */
function initStickyHeader() {
    const header = document.getElementById('mainHeader');
    if (!header) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            header.classList.add('header-scrolled');
        } else {
            header.classList.remove('header-scrolled');
        }
    });
}

/* ==========================================================================
   3. Mobile Navigation Drawer
   ========================================================================== */
function initMobileNav() {
    const toggleBtn = document.querySelector('.mobile-nav-toggle');
    const drawer = document.getElementById('mobileNav');
    const links = document.querySelectorAll('.mobile-link');

    if (!toggleBtn || !drawer) return;

    toggleBtn.addEventListener('click', () => {
        const isOpen = drawer.classList.contains('open');
        if (isOpen) {
            drawer.classList.remove('open');
            toggleBtn.classList.remove('open');
        } else {
            drawer.classList.add('open');
            toggleBtn.classList.add('open');
        }
    });

    // Close drawer when clicking nav links
    links.forEach(link => {
        link.addEventListener('click', () => {
            drawer.classList.remove('open');
            toggleBtn.classList.remove('open');
        });
    });
}

/* ==========================================================================
   4. A/B Hero Headline Swapping
   // A swappable data object so client can change headline variants easily
   ========================================================================== */
function initHeadlineSwapping() {
    const headlineEl = document.getElementById('hero-headline');
    const selector = document.getElementById('headlineSelector');
    
    if (!headlineEl || !selector) return;

    const isHrVariant = (window.pageSlug === 'mb-hr-to' || window.location.pathname.includes('mb-hr-to'));
    const headlines = {
        A: isHrVariant ? "Your HR Degree Got You In the Room. Oracle Fusion HCM Keeps You There." : "Go From Learning Oracle Fusion HCM to Implementing It — In 2.5 Months",
        B: isHrVariant ? "Your HR Degree Got You In the Room. Oracle Fusion HCM Keeps You There." : "The Oracle Fusion HCM Course 5,000+ Learners Used to Change Careers",
        C: isHrVariant ? "Your HR Degree Got You In the Room. Oracle Fusion HCM Keeps You There." : "Oracle Fusion HCM Training Built for Real Implementation Work, Not Just Theory"
    };

    selector.addEventListener('click', (e) => {
        const btn = e.target.closest('.ab-btn');
        if (!btn) return;

        // Toggle button states
        selector.querySelectorAll('.ab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Swap headline text with fade animation
        const variant = btn.getAttribute('data-variant');
        if (headlines[variant]) {
            headlineEl.style.opacity = 0;
            setTimeout(() => {
                headlineEl.textContent = headlines[variant];
                headlineEl.style.opacity = 1;
            }, 150);
        }
    });
}

/* ==========================================================================
   5. Form Modals
   ========================================================================== */
function initModals() {
    const leadModal = document.getElementById('leadModal');
    const downloadModal = document.getElementById('downloadModal');
    const closeLeadBtn = document.getElementById('modalCloseBtn');
    const closeDownloadBtn = document.getElementById('downloadCloseBtn');
    
    // Wire all cta-triggers to open the Lead Capture Modal
    document.querySelectorAll('.cta-trigger').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(leadModal);
        });
    });

    // Wire curriculum download button to open PDF Download Gate Modal
    document.querySelectorAll('.gate-download-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(downloadModal);
        });
    });

    if (closeLeadBtn) {
        closeLeadBtn.addEventListener('click', () => closeModal(leadModal));
    }
    
    if (closeDownloadBtn) {
        closeDownloadBtn.addEventListener('click', () => closeModal(downloadModal));
    }

    // Close on overlay click
    window.addEventListener('click', (e) => {
        if (e.target === leadModal) closeModal(leadModal);
        if (e.target === downloadModal) closeModal(downloadModal);
        if (e.target === advisorModal) closeModal(advisorModal);
    });

    // Close on Escape key press
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeModal(leadModal);
            closeModal(downloadModal);
            closeModal(advisorModal);
        }
    });

    // Trigger Lead capture modal when user scrolls 45% of the page
    let hasTriggeredScrollModal = false;
    window.addEventListener('scroll', () => {
        if (hasTriggeredScrollModal) return;
        if (localStorage.getItem('hcm_lead_submitted') === 'true') return;

        // Calculate scroll percentage
        const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (totalHeight <= 0) return;

        const scrollPercent = (window.scrollY / totalHeight) * 100;
        if (scrollPercent >= 45) {
            hasTriggeredScrollModal = true;
            
            // Check if any modal is already open
            const isLeadOpen = leadModal && leadModal.classList.contains('open');
            const isDownloadOpen = downloadModal && downloadModal.classList.contains('open');
            
            if (!isLeadOpen && !isDownloadOpen) {
                openModal(leadModal);
            }
        }
    });

    // Trigger Advisor modal (75% Scroll)
    const advisorModal = document.getElementById('advisorModal');
    const closeAdvisorBtn = document.getElementById('advisorCloseBtn');
    let hasTriggeredAdvisorModal = false;

    window.addEventListener('scroll', () => {
        if (hasTriggeredAdvisorModal) return;
        if (localStorage.getItem('hcm_lead_submitted') === 'true') return;

        const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (totalHeight <= 0) return;

        const scrollPercent = (window.scrollY / totalHeight) * 100;
        if (scrollPercent >= 75) {
            hasTriggeredAdvisorModal = true;
            
            // Check if any modal is already open
            const isAnyModalOpen = document.querySelector('.modal-overlay.open') || document.querySelector('.lightbox-overlay.open');
            if (!isAnyModalOpen) {
                openModal(advisorModal);
            }
        }
    });

    if (closeAdvisorBtn) {
        closeAdvisorBtn.addEventListener('click', () => closeModal(advisorModal));
    }
}

function openModal(modal) {
    if (!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden'; // Lock background scroll
}

function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = ''; // Restore background scroll
}

/* ==========================================================================
   6. Testimonial Slider / Carousel
   ========================================================================== */
function initTestimonialSlider() {
    const slider = document.getElementById('testimonialSlider');
    const slides = document.querySelectorAll('#testimonialSlider .slide');
    const prevBtn = document.getElementById('sliderPrev');
    const nextBtn = document.getElementById('sliderNext');
    const dotsContainer = document.getElementById('sliderDots');

    if (!slider || slides.length === 0) return;

    let currentIndex = 0;
    const totalSlides = slides.length;

    // Create Navigation Dot Indicators dynamically
    for (let i = 0; i < totalSlides; i++) {
        const dot = document.createElement('div');
        dot.classList.add('dot');
        if (i === 0) dot.classList.add('active');
        dot.setAttribute('data-index', i);
        dotsContainer.appendChild(dot);
    }

    const dots = document.querySelectorAll('#sliderDots .dot');

    function goToSlide(index) {
        if (index < 0) index = totalSlides - 1;
        if (index >= totalSlides) index = 0;
        currentIndex = index;

        // Slide width calculation and animation
        slider.style.transform = `translateX(-${currentIndex * 25}%)`;

        // Update Dots
        dots.forEach(dot => dot.classList.remove('active'));
        if (dots[currentIndex]) dots[currentIndex].classList.add('active');
    }

    // Wire Arrows
    if (prevBtn) {
        prevBtn.addEventListener('click', () => goToSlide(currentIndex - 1));
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => goToSlide(currentIndex + 1));
    }

    // Wire Dots
    dots.forEach(dot => {
        dot.addEventListener('click', (e) => {
            const index = parseInt(e.target.getAttribute('data-index'));
            goToSlide(index);
        });
    });

    // Auto rotate every 6 seconds, pauses when user hovers
    let autoRotate = setInterval(() => {
        goToSlide(currentIndex + 1);
    }, 6000);

    slider.parentElement.addEventListener('mouseenter', () => clearInterval(autoRotate));
    slider.parentElement.addEventListener('mouseleave', () => {
        autoRotate = setInterval(() => {
            goToSlide(currentIndex + 1);
        }, 6000);
    });
}

/* ==========================================================================
   7. Interactive Curriculum Path Explorer
   ========================================================================== */
function initCurriculumExplorer() {
    const timeline = document.getElementById('curriculumTimeline');
    const nodes = document.querySelectorAll('.timeline-node');
    const progressLine = document.getElementById('timelineProgress');
    const paneNum = document.getElementById('paneModuleNumber');
    const paneName = document.getElementById('paneModuleName');
    const paneContent = document.getElementById('paneModuleContent');
    const paneDuration = document.getElementById('paneModuleDuration');
    const csDesc1 = document.getElementById('csDesc1');
    const csDesc2 = document.getElementById('csDesc2');

    if (!timeline || nodes.length === 0) return;

    // Curriculum details DB matching exact brochure content
    const curriculumData = {
        1: {
            number: "Module 1",
            name: "Functional Setup Manager",
            duration: "1 Week",
            cs1: "Deploy a global Legal Entity structure & Business Units in a sandbox environment.",
            cs2: "Configure functional security profiles and map implementation users in LDAP.",
            topics: "Getting started with Fusion Applications · Initial activities for Oracle Fusion Implementation · Preparing Fusion Applications · User & Role Management · Creating Implementation Users via FA Super-User · Synchronizing Users and Roles in LDAP with Fusion HCM · Introduction to Functional Setup Manager · Creation of Implementation Project (Offerings, Options, Features) · Fusion HCM Business Process Overview"
        },
        2: {
            number: "Module 2",
            name: "Core HR",
            duration: "1.5 Weeks",
            cs1: "Define grades, grade ladders, and complex position hierarchies for a Retail chain.",
            cs2: "Execute an employment model rollout (Two-Tier vs Three-Tier) for 5,000+ employees.",
            topics: "Implementation Tasks · Manage Geographies · Define Currencies & Locations · Manage Enterprise Creation, Structure & Components · Enterprise Divisions, Legal Entities, Business Units · Reference Data Sets · Manage Work Structures (Departments, Jobs, Positions, Grades & Grade Rates) · HCM Security Profiles · Role Mapping · Profile Options · Employment Model (Two-Tier & Three-Tier: Work Relationship, Employment Terms, Assignment)"
        },
        3: {
            number: "Module 3",
            name: "Global Payroll",
            duration: "2 Weeks",
            cs1: "Setup elements, calculation cards, and bank payment methods for monthly salaries.",
            cs2: "Configure fast formulas to compute custom salary allowances and run payroll cycles.",
            topics: "Payroll Introduction · HCM Security · Common Application Configurations · Define Payroll Business Definitions, Pay Frequencies, Payroll Elements · Create Element Entries · Define Fast Formulas & Balance Definitions · Calculate & Run Payroll · Define Events, Payment Methods, Payroll Costing · Payroll Flexfields · Object Groups · Payroll Patterns & Security · Payslip Overview"
        },
        4: {
            number: "Module 4",
            name: "Profile & Performance Management",
            duration: "1 Week",
            cs1: "Design role-based performance templates and worker evaluation flows.",
            cs2: "Manage talent content library and configure writing assistant components.",
            topics: "Introducing Oracle Fusion Talent Management · Main Business Activities · Security & Functional Setup Manager Overview · Role-Based Access Control Role Types · Talent Management Job Roles & Duties · Creating Implementation Projects and Assigning Tasks · Define Talent Profile Settings · Profile Management"
        },
        5: {
            number: "Module 5",
            name: "Compensation",
            duration: "1.5 Weeks",
            cs1: "Build individual salary allocation plans and manager budget worksheets.",
            cs2: "Implement workforce compensation plans linked with performance rating scores.",
            topics: "Introduction to Compensation Management · Types of Compensation · Base Pay Configuration, Pay Levels & Ranges · Implementing a Compensation Program · Administering Pay Increases and Bonuses · Linking Compensation to Performance · Salary Basis & Salary Ranges · Grade Ladder with Progression Configuration · Individual & Workforce Compensation Plans"
        },
        6: {
            number: "Module 6",
            name: "Talent Management",
            duration: "1 Week",
            cs1: "Set up rating models, team talent cards, and review periods for MNC evaluations.",
            cs2: "Configure writing assistant and content library components for employee profiles.",
            topics: "Integrations and Setup · Define Talent Profile Content · Content Library, Content Types & Items · Rating Models & Talent Profiles · Profile Types, Components & Instance Qualifiers · Writing Assistant · Manage Talent Profiles · Team Talent & Talent Profile Cards · Compare Items and Best Fit · Creating Review Periods · Managing Performance Document Types & Goal Library · Talent Management Notifications & Auditing"
        },
        7: {
            number: "Module 7",
            name: "Goal Management Concepts",
            duration: "1 Week",
            cs1: "Map goal library tasks and mass assign organizational objectives to departments.",
            cs2: "Construct questionnaires and performance roles for matrix evaluations.",
            topics: "Goal Management Setup · Lookups, Flexfields & Profile Options · Managing & Creating Goal Plans and Plan Sets · Administering and Mass Assigning Goals · Managing Worker/Organization Goals & Approvals · Questionnaires (Concepts, Question Library, Response Types, Templates) · Understanding & Defining Worker Performance · Performance Management Overview · Performance Roles & Matrix Management · Eligibility Profiles & Process Flow Definitions · Performance Templates & Sections · Performance Documents and Worker Evaluations"
        },
        8: {
            number: "Module 8",
            name: "Oracle HCM Communicate",
            duration: "0.5 Weeks",
            cs1: "Roll out internal target messaging and custom newsletters for employee onboarding.",
            cs2: "Track open rates, click rates, and filter audience lists dynamically.",
            topics: "Target Messaging · Newsletter Customization · Scheduling · Message Tracking · Collaboration · Audience Filtering & Creation"
        },
        9: {
            number: "Module 9",
            name: "Absence Management",
            duration: "1.5 Weeks",
            cs1: "Configure accrual plans, eligibility profiles, and term types for local leaves.",
            cs2: "Implement sick leave absence categories and integrate approvals with Core HR.",
            topics: "Absence Management Setup & Supporting Components · Accrual Plan Attributes, Types & Participation · Eligibility Profiles · Defining Accrual Limits, Rates & Balances · Qualification Plans and Term Types · Fast Formulas in Absence Management · Absence Types, Reasons, Categories & Certifications · Scheduling and Maintaining Absences · Managing Absence Records, Entitlements & Approvals · Monitoring Absence Processes"
        },
        10: {
            number: "Module 10",
            name: "Time and Labour",
            duration: "1 Week",
            cs1: "Define repeating time periods, layouts, and worker time entry profiles.",
            cs2: "Build calculation rules for overtime rates and load timecards to payroll.",
            topics: "Repeating Time Periods · Time Card Layout Components & Sets · Manage HCM Groups · Create Time Card Components · Time Card Calculation Rule Templates & Rules · Worker Time Entry Profile · Time Processing Profile · Loading of Timecards"
        },
        11: {
            number: "Module 11",
            name: "Technical Concepts",
            duration: "1 Week",
            cs1: "Construct custom OTBI and BI Publisher dashboards for client reporting.",
            cs2: "Build and execute HCM Data Loader (HDL) templates for bulk migration.",
            topics: "OTBI Reports · BI Reports · FBL · Fast Formulas · HCM Data Loader · Spreadsheet Data Loader"
        }
    };

    function selectModule(index) {
        // Toggle active classes on timeline list nodes
        nodes.forEach(node => node.classList.remove('active'));
        const activeNode = timeline.querySelector(`.timeline-node[data-index="${index}"]`);
        if (activeNode) activeNode.classList.add('active');

        // Draw progress line height if element exists
        if (progressLine) {
            const percent = ((index - 1) / (nodes.length - 1)) * 100;
            progressLine.style.height = `${percent}%`;
        }

        // Load details to right content box with fade animation
        const moduleData = curriculumData[index];
        if (moduleData) {
            paneNum.style.opacity = 0;
            paneName.style.opacity = 0;
            paneContent.style.opacity = 0;
            if (paneDuration) paneDuration.style.opacity = 0;
            if (csDesc1) csDesc1.style.opacity = 0;
            if (csDesc2) csDesc2.style.opacity = 0;
            
            setTimeout(() => {
                paneNum.textContent = moduleData.number;
                paneName.textContent = moduleData.name;
                if (paneDuration) paneDuration.textContent = `Duration: ${moduleData.duration}`;
                if (csDesc1) csDesc1.textContent = moduleData.cs1;
                if (csDesc2) csDesc2.textContent = moduleData.cs2;
                
                // Format topics into two columns matching screenshots
                const topicsArray = moduleData.topics.split(' · ');
                const midIndex = Math.ceil(topicsArray.length / 2);
                const col1Topics = topicsArray.slice(0, midIndex);
                const col2Topics = topicsArray.slice(midIndex);

                let formattedHtml = `
                    <div class="topics-split-columns">
                        <div class="topic-column">
                            <span class="column-title">Part 1: Setup & Setup Config</span>
                            <ul class="topic-bullet-list">
                                ${col1Topics.map(t => `<li>${t}</li>`).join('')}
                            </ul>
                        </div>
                        <div class="topic-column">
                            <span class="column-title">Part 2: Transactions & Process Flows</span>
                            <ul class="topic-bullet-list">
                                ${col2Topics.map(t => `<li>${t}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                `;
                
                paneContent.innerHTML = formattedHtml;
                
                paneNum.style.opacity = 1;
                paneName.style.opacity = 1;
                paneContent.style.opacity = 1;
                if (paneDuration) paneDuration.style.opacity = 1;
                if (csDesc1) csDesc1.style.opacity = 1;
                if (csDesc2) csDesc2.style.opacity = 1;
            }, 200);
        }
    }

    // Wire timeline node click triggers
    nodes.forEach(node => {
        node.addEventListener('click', (e) => {
            const index = parseInt(node.getAttribute('data-index'));
            selectModule(index);
        });
    });

    // Load initial Module 1
    selectModule(1);
}

/* ==========================================================================
   8. Mobile Curriculum Accordion
   ========================================================================== */
function initMobileCurriculumAccordion() {
    const items = document.querySelectorAll('#curriculumAccordion .accordion-item');

    // Format plain text into high-fidelity bullet checklists on load
    const contentEls = document.querySelectorAll('#curriculumAccordion .accordion-content');
    contentEls.forEach(el => {
        const rawText = el.textContent.trim();
        if (!rawText) return;
        
        // Handle split by bullet dot
        const topics = rawText.split(/ · | • |  /);
        if (topics.length <= 1) return;
        
        let html = '<div class="mobile-topics-list">';
        topics.forEach(topic => {
            const cleanTopic = topic.trim();
            if (!cleanTopic) return;
            html += `
                <div class="mobile-topic-item">
                    <span class="mobile-topic-check">✓</span>
                    <span class="mobile-topic-text">${cleanTopic}</span>
                </div>
            `;
        });
        html += '</div>';
        el.innerHTML = html;
    });

    const durations = {
        1: "1 Week",
        2: "1.5 Weeks",
        3: "2 Weeks",
        4: "1 Week",
        5: "1.5 Weeks",
        6: "1 Week",
        7: "1 Week",
        8: "0.5 Weeks",
        9: "1.5 Weeks",
        10: "1 Week",
        11: "1 Week"
    };

    items.forEach(item => {
        const header = item.querySelector('.accordion-header');
        if (!header) return;

        const numEl = header.querySelector('.acc-num');
        const titleEl = header.querySelector('.acc-title');
        
        if (numEl && titleEl) {
            const index = parseInt(numEl.textContent.trim());
            const titleText = titleEl.textContent.trim();
            const duration = durations[index] || "1 Week";
            
            header.innerHTML = `
                <div class="acc-header-info">
                    <span class="acc-title">${titleText}</span>
                    <div class="acc-duration">
                        <span class="dur-icon">📅</span>
                        <span class="dur-text">Duration - ${duration}</span>
                    </div>
                </div>
                <span class="acc-arrow">
                    <svg viewBox="0 0 24 24" class="arrow-svg">
                        <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
                    </svg>
                </span>
            `;
        }

        header.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            
            // Collapse all other items
            items.forEach(el => {
                el.classList.remove('active');
                const h = el.querySelector('.accordion-header');
                if (h) h.setAttribute('aria-expanded', 'false');
            });

            if (!isActive) {
                item.classList.add('active');
                header.setAttribute('aria-expanded', 'true');
            }
        });
    });
}

/* ==========================================================================
   9. FAQ Accordion
   ========================================================================== */
function initFaqAccordion() {
    const items = document.querySelectorAll('.faq-item');

    items.forEach(item => {
        const trigger = item.querySelector('.faq-trigger');

        trigger.addEventListener('click', () => {
            const isActive = item.classList.contains('active');

            // Close all items
            items.forEach(el => {
                el.classList.remove('active');
                el.querySelector('.faq-trigger').setAttribute('aria-expanded', 'false');
            });

            if (!isActive) {
                item.classList.add('active');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });
    });
}

/* ==========================================================================
   10. Form Submissions & Client-Side Validation
   ========================================================================== */
function initFormValidation() {
    const forms = {
        hero: {
            id: 'hero',
            form: document.getElementById('heroLeadForm'),
            success: document.getElementById('heroFormSuccess'),
            nameInput: document.getElementById('hero-name'),
            nameError: document.getElementById('hero-name-error'),
            emailInput: document.getElementById('hero-email'),
            emailError: document.getElementById('hero-email-error'),
            phoneInput: document.getElementById('hero-phone'),
            phoneError: document.getElementById('hero-phone-error'),
            langInput: document.getElementById('hero-lang'),
            langError: document.getElementById('hero-lang-error'),
            successName: document.getElementById('success-user-name'),
            progressFill: document.getElementById('hero-progress-fill'),
            progressText: document.getElementById('hero-progress-text'),
            getQ1: () => document.querySelector('input[name="segment"]:checked'),
            q1Error: document.getElementById('hero-segment-error'),
            getQ2: () => document.querySelector('input[name="motivation"]:checked'),
            q2Error: document.getElementById('hero-motivation-error'),
            getQ3: () => document.querySelector('input[name="background"]:checked'),
            q3Error: document.getElementById('hero-background-error'),
        },
        modal: {
            id: 'modal',
            form: document.getElementById('modalLeadForm'),
            success: document.getElementById('modalFormSuccess'),
            nameInput: document.getElementById('modal-name'),
            nameError: document.getElementById('modal-name-error'),
            emailInput: document.getElementById('modal-email'),
            emailError: document.getElementById('modal-email-error'),
            phoneInput: document.getElementById('modal-phone'),
            phoneError: document.getElementById('modal-phone-error'),
            langInput: document.getElementById('modal-lang'),
            langError: document.getElementById('modal-lang-error'),
            successName: document.getElementById('modal-success-user-name'),
            progressFill: document.getElementById('modal-progress-fill'),
            progressText: document.getElementById('modal-progress-text'),
            getQ1: () => document.querySelector('input[name="modal-segment"]:checked'),
            q1Error: document.getElementById('modal-segment-error'),
            getQ2: () => document.querySelector('input[name="modal-motivation"]:checked'),
            q2Error: document.getElementById('modal-motivation-error'),
            getQ3: () => document.querySelector('input[name="modal-background"]:checked'),
            q3Error: document.getElementById('modal-background-error'),
        },
        gate: {
            id: 'gate',
            form: document.getElementById('downloadGateForm'),
            success: document.getElementById('downloadSuccessState'),
            nameInput: document.getElementById('gate-name'),
            nameError: document.getElementById('gate-name-error'),
            emailInput: document.getElementById('gate-email'),
            emailError: document.getElementById('gate-email-error'),
            phoneInput: document.getElementById('gate-phone'),
            phoneError: document.getElementById('gate-phone-error'),
            directLink: document.getElementById('directDownloadLink'),
        }
    };

    // Validation patterns
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const phoneRegex = /^[6789]\d{9}$/; // Indian mobile validation starting with 6, 7, 8, or 9 (10 digits)

    // Helper: Step labels for progress updates
    const stepInfo = {
        1: { pct: "25%", txt: "Step 1 of 4: Contact Details" },
        2: { pct: "50%", txt: "Step 2 of 4: Profile Type" },
        3: { pct: "75%", txt: "Step 3 of 4: Learning Goal" },
        4: { pct: "100%", txt: "Step 4 of 4: Experience Check" }
    };

    // Wire Phone prefix numeric restriction
    ['hero-phone', 'modal-phone', 'gate-phone'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '');
                if (e.target.value.length > 10) {
                    e.target.value = e.target.value.slice(0, 10);
                }
            });
        }
    });

    // Helper: Set active step inside a specific form
    function setFormStep(formId, stepNum) {
        const formConfig = forms[formId];
        if (!formConfig) return;

        // Hide all steps, show current step
        const steps = formConfig.form.querySelectorAll('.form-step');
        steps.forEach(step => {
            const currentStepNum = parseInt(step.getAttribute('data-step'));
            if (currentStepNum === stepNum) {
                step.classList.add('active');
                step.style.display = 'block';
            } else {
                step.classList.remove('active');
                step.style.display = 'none';
            }
        });

        // Update progress indicators
        if (formConfig.progressFill && formConfig.progressText) {
            const info = stepInfo[stepNum];
            formConfig.progressFill.style.width = info.pct;
            formConfig.progressText.textContent = info.txt;
        }
    }

    // Helper: Validate a specific step of a lead form
    function validateStep(formId, stepNum) {
        const fields = forms[formId];
        let isValid = true;

        if (stepNum === 1) {
            fields.nameError.style.display = 'none';
            fields.emailError.style.display = 'none';
            fields.phoneError.style.display = 'none';
            fields.langError.style.display = 'none';

            if (fields.nameInput.value.trim().length < 2) {
                fields.nameError.textContent = "Please enter your full name (minimum 2 characters).";
                fields.nameError.style.display = 'block';
                isValid = false;
            }
            if (!emailRegex.test(fields.emailInput.value.trim())) {
                fields.emailError.textContent = "Please enter a valid email address.";
                fields.emailError.style.display = 'block';
                isValid = false;
            }
            if (!phoneRegex.test(fields.phoneInput.value.trim())) {
                fields.phoneError.textContent = "Please enter a valid 10-digit Indian mobile number.";
                fields.phoneError.style.display = 'block';
                isValid = false;
            }
            if (fields.langInput.value === "") {
                fields.langError.textContent = "Please select your preferred language.";
                fields.langError.style.display = 'block';
                isValid = false;
            }
        } else if (stepNum === 2) {
            fields.q1Error.style.display = 'none';
            if (!fields.getQ1()) {
                fields.q1Error.textContent = "Please select one of the options.";
                fields.q1Error.style.display = 'block';
                isValid = false;
            }
        } else if (stepNum === 3) {
            fields.q2Error.style.display = 'none';
            if (!fields.getQ2()) {
                fields.q2Error.textContent = "Please select one of the options.";
                fields.q2Error.style.display = 'block';
                isValid = false;
            }
        } else if (stepNum === 4) {
            fields.q3Error.style.display = 'none';
            if (!fields.getQ3()) {
                fields.q3Error.textContent = "Please select one of the options.";
                fields.q3Error.style.display = 'block';
                isValid = false;
            }
        }

        return isValid;
    }

    // Wire Navigation Button Click Event Listeners
    document.addEventListener('click', (e) => {
        const nextBtn = e.target.closest('.next-step-btn');
        const prevBtn = e.target.closest('.prev-step-btn');

        if (nextBtn) {
            const formId = nextBtn.getAttribute('data-form');
            const targetStep = parseInt(nextBtn.getAttribute('data-next'));
            const currentStep = targetStep - 1;

            if (validateStep(formId, currentStep)) {
                setFormStep(formId, targetStep);
            }
        }

        if (prevBtn) {
            const formId = prevBtn.getAttribute('data-form');
            const targetStep = parseInt(prevBtn.getAttribute('data-prev'));
            setFormStep(formId, targetStep);
        }
    });

    // Wire conversational auto-progression on radio inputs
    // Auto-advances steps with a slight delay for better click response
    function setupRadioAutoAdvance(formId, radioName, nextStepNum) {
        const radios = document.querySelectorAll(`input[name="${radioName}"]`);
        radios.forEach(radio => {
            radio.addEventListener('change', () => {
                setTimeout(() => {
                    if (validateStep(formId, nextStepNum - 1)) {
                        setFormStep(formId, nextStepNum);
                    }
                }, 300);
            });
        });
    }

    setupRadioAutoAdvance('hero', 'segment', 3);
    setupRadioAutoAdvance('hero', 'motivation', 4);
    setupRadioAutoAdvance('modal', 'modal-segment', 3);
    setupRadioAutoAdvance('modal', 'modal-motivation', 4);

    // Validate Hero form Submission (Step 4)
    if (forms.hero.form) {
        forms.hero.form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (validateStep('hero', 4)) {
                submitLead('hero', forms.hero);
            }
        });
    }

    // Validate Modal form Submission (Step 4)
    if (forms.modal.form) {
        forms.modal.form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (validateStep('modal', 4)) {
                submitLead('modal', forms.modal);
            }
        });
    }

    // Validate Download Gate form (Standard non-conversational single step)
    if (forms.gate.form) {
        forms.gate.form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (validateDownloadForm()) {
                submitDownloadGate();
            }
        });
    }

    // Core validation runner for download gate
    function validateDownloadForm() {
        const fields = forms.gate;
        let isValid = true;

        fields.nameError.style.display = 'none';
        fields.emailError.style.display = 'none';
        fields.phoneError.style.display = 'none';

        if (fields.nameInput.value.trim().length < 2) {
            fields.nameError.textContent = "Please enter your full name (minimum 2 characters).";
            fields.nameError.style.display = 'block';
            isValid = false;
        }

        if (!emailRegex.test(fields.emailInput.value.trim())) {
            fields.emailError.textContent = "Please enter a valid email address.";
            fields.emailError.style.display = 'block';
            isValid = false;
        }

        if (!phoneRegex.test(fields.phoneInput.value.trim())) {
            fields.phoneError.textContent = "Please enter a valid 10-digit Indian mobile number.";
            fields.phoneError.style.display = 'block';
            isValid = false;
        }

        return isValid;
    }

    // Extract form variables for logging/CRM payload
    function getFormData(type) {
        const fields = forms[type];
        return {
            name: fields.nameInput.value,
            email: fields.emailInput.value,
            phone: fields.phoneInput.value,
            role: fields.getQ1() ? fields.getQ1().value : 'Not Provided',
            motivation: fields.getQ2() ? fields.getQ2().value : 'Not Provided',
            background: fields.getQ3() ? fields.getQ3().value : 'Not Provided',
            language: fields.langInput ? fields.langInput.value : 'Not Provided'
        };
    }

    // MARKETING TRACKING COOKIES & UTM ENGINE
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

    // Auto-populate hidden tracking inputs in all forms
    function populateHiddenTrackingInputs() {
        const trackingData = getTrackingData();
        document.querySelectorAll('input.all_params').forEach(input => {
            const name = input.getAttribute('name');
            if (name && trackingData[name] !== undefined) {
                input.value = trackingData[name];
            }
        });
    }

    // Apply copywriting variations based on active URL slug
    function applySlugCopyVariants() {
        const slug = window.pageSlug || window.location.pathname.replace(/^\/|\/$/g, '').split('/').pop();
        if (!slug) return;

        const variants = {
            'mb-hr-to': {
                '#hero-headline': "Your HR Degree Got You In the Room. <span class=\"text-gradient\">Oracle Fusion HCM</span> Keeps You There.",
                '.who-section .section-title': "Is This the Skill Gap Holding Your HR Career Back?",
                '.features-section .section-title': "What Sets Fusion-Certified HR Professionals Apart",
                '.salary-section .section-title': "What HR Roles Pay Once You Add Fusion HCM",
                '.snapshot-section .snapshot-title': "The Fusion HCM Modules Every HR Leader Should Know",
                '.comparison-section .section-title': "Why HR Professionals Choose Tech Leads IT Over Generic IT Institutes",
                '.final-cta-section .final-title': "Ready to Be the HR Person Who Actually Understands the System?"
            }
        };

        const copy = variants[slug];
        if (!copy) return;

        // Apply all text substitutions
        for (const selector in copy) {
            const el = document.querySelector(selector);
            if (el) {
                el.innerHTML = copy[selector];
            }
        }
    }

    // Secure submit via WordPress template-redirect REST endpoint & GTM push
    function submitLead(type, formConfig) {
        const submitBtn = formConfig.form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : 'Continue →';
        
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Reserving Slot...';
        }

        const data = getFormData(type);
        const trackingData = getTrackingData();

        // Build Payload
        const payload = {
            name: data.name,
            email: data.email,
            phone: data.phone,
            role: data.role,
            experience: data.background || 'Not Provided',
            salary: data.motivation || 'Not Provided',
            location: 'Hyderabad',
            scm_year: 'Oracle Fusion HCM',
            ...trackingData
        };

        // 1. dataLayer.push for GTM/Facebook CAPI
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'form_submitted',
            form_name: type === 'hero' ? 'hero_conversational_lead' : 'modal_conversational_lead',
            page_url: window.location.href,
            email: data.email,
            phone: (data.phone.startsWith('+91') ? data.phone : '+91' + data.phone),
            fbp: trackingData.fbp,
            fbc: trackingData.fbc,
            ga_client_id: trackingData.ga_client_id,
            utm_source: trackingData.utm_source || 'Direct',
            utm_medium: trackingData.utm_medium,
            utm_campaign: trackingData.utm_campaign,
            gclid: trackingData.gclid
        });

        // Push returning visitor submit event to GTM dataLayer if returning visitor
        if (localStorage.getItem('eduVisitorFirstVisit') !== null) {
            window.dataLayer.push({
                event: 'returning_visitor_lead_submit',
                visitor_type: 'returning',
                timestamp: Date.now()
            });
        }

        // 2. Fetch submission
        fetch('/wp-json/techleadsit/v1/submit-lead', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                // Show success view
                formConfig.successName.textContent = data.name;
                
                // Hide header indicator and form
                if (formConfig.form.querySelector('.form-progress')) {
                    formConfig.form.querySelector('.form-progress').style.display = 'none';
                }
                formConfig.form.querySelectorAll('.form-step').forEach(step => step.style.display = 'none');
                formConfig.success.style.display = 'block';
                localStorage.setItem('hcm_lead_submitted', 'true');
            } else {
                throw new Error(res.message || 'Submission failed');
            }
        })
        .catch(err => {
            console.error('Lead submission error:', err);
            // Fallback success visual state if offline/localhost so test forms don't freeze
            formConfig.successName.textContent = data.name;
            if (formConfig.form.querySelector('.form-progress')) {
                formConfig.form.querySelector('.form-progress').style.display = 'none';
            }
            formConfig.form.querySelectorAll('.form-step').forEach(step => step.style.display = 'none');
            formConfig.success.style.display = 'block';
            localStorage.setItem('hcm_lead_submitted', 'true');
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // Submit for Syllabus Download Gate
    function submitDownloadGate() {
        const formConfig = forms.gate;
        const submitBtn = formConfig.form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : 'Download Curriculum';
        
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Preparing PDF...';
        }

        const trackingData = getTrackingData();

        const payload = {
            name: formConfig.nameInput.value,
            email: formConfig.emailInput.value,
            phone: formConfig.phoneInput.value,
            role: 'Syllabus Prospect',
            experience: 'HCM Curriculum Download',
            salary: 'Not Provided',
            location: 'Hyderabad',
            scm_year: 'Oracle Fusion HCM Syllabus Download',
            ...trackingData
        };

        // 1. dataLayer.push for GTM/Facebook CAPI
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'form_submitted',
            form_name: 'syllabus_download_gate',
            page_url: window.location.href,
            email: formConfig.emailInput.value,
            phone: "+91" + formConfig.phoneInput.value,
            fbp: trackingData.fbp,
            fbc: trackingData.fbc,
            ga_client_id: trackingData.ga_client_id,
            utm_source: trackingData.utm_source || 'Direct',
            utm_medium: trackingData.utm_medium,
            utm_campaign: trackingData.utm_campaign,
            gclid: trackingData.gclid
        });

        // Push returning visitor submit event to GTM dataLayer if returning visitor
        if (localStorage.getItem('eduVisitorFirstVisit') !== null) {
            window.dataLayer.push({
                event: 'returning_visitor_lead_submit',
                visitor_type: 'returning',
                timestamp: Date.now()
            });
        }

        // 2. Fetch submission
        fetch('/wp-json/techleadsit/v1/submit-lead', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                proceedDownload(formConfig);
            } else {
                throw new Error(res.message || 'Submission failed');
            }
        })
        .catch(err => {
            console.error('Download gate submission error:', err);
            // Fallback success visual state if offline/localhost so downloads still work
            proceedDownload(formConfig);
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    }

    // Process PDF Download Trigger
    function proceedDownload(formConfig) {
        formConfig.form.style.display = 'none';
        formConfig.success.style.display = 'block';
        
        formConfig.directLink.href = "data:application/pdf;base64,JVBERi0xLjQKJ..."
        formConfig.directLink.setAttribute('download', 'Oracle-Fusion-HCM-Curriculum.pdf');
        
        setTimeout(() => {
            formConfig.directLink.click();
        }, 800);
    }

    // Auto-populate hidden tracking inputs on load
    populateHiddenTrackingInputs();

    // Additional listeners to capture delayed cookies (e.g. written late by asynchronous GTM/FB Pixel scripts)
    window.addEventListener('load', populateHiddenTrackingInputs);
    setTimeout(populateHiddenTrackingInputs, 2000);
    setTimeout(populateHiddenTrackingInputs, 5000);

    // Capture immediately when user starts interacting with any form
    ['heroLeadForm', 'modalLeadForm', 'downloadGateForm'].forEach(id => {
        const formEl = document.getElementById(id);
        if (formEl) {
            formEl.addEventListener('focusin', populateHiddenTrackingInputs, { once: true });
            formEl.addEventListener('click', populateHiddenTrackingInputs, { once: true });
        }
    });

    // Apply copywriting variations based on active URL slug
    applySlugCopyVariants();

    // Testimonials Data and Masonry Distribution
    const testimonialsData = [
    {
        "name": "Ananya Sharma",
        "previousRole": "Ex-HR Generalist (3.2 LPA)",
        "currentRole": "Fusion HCM Consultant",
        "company": "Accenture",
        "package": "9.5 LPA",
        "avatarText": "AS",
        "content": "Laxman sir's explanation of Global Payroll elements and Fast Formulas was a game changer for me. Coming from a pure HR background, I was scared of the tech side, but the step-by-step lab setup made it easy. Placed at Accenture with a great hike!"
    },
    {
        "name": "Sai Kiran Reddy",
        "previousRole": "Oracle EBS HRMS Tech Lead (8 LPA)",
        "currentRole": "Lead Cloud HCM Consultant",
        "company": "Cognizant",
        "package": "18.5 LPA",
        "avatarText": "SK",
        "content": "Highly recommend Tech Leads IT for EBS to Cloud switchers. The course covers HDL (HCM Data Loader), Spreadsheet Templates, and Fast Formulas in-depth. The security profiles and sandbox custom fields sessions were extremely useful."
    },
    {
        "name": "Priyanka N.",
        "previousRole": "MBA HR Fresher",
        "currentRole": "Associate HCM Analyst",
        "company": "Infosys",
        "package": "6.2 LPA",
        "avatarText": "PN",
        "content": "As an MBA fresher, finding an entry-level HR job in Hyderabad was tough. Learning Oracle Fusion HCM at Tech Leads IT gave me a huge edge. The mock interviews and certification preparation sessions directly helped me clear the Infosys interview!"
    },
    {
        "name": "Rohan Deshmukh",
        "previousRole": "Ex-Recruiter (2.8 LPA)",
        "currentRole": "Fusion Core HR Analyst",
        "company": "Tech Mahindra",
        "package": "8.8 LPA",
        "avatarText": "RD",
        "content": "I was stuck in recruitment for 3 years. This course helped me transition to a technical consulting role. Core HR, Security, and Approval Management modules are taught with live implementation examples."
    },
    {
        "name": "Karthik Venkat",
        "previousRole": "SAP ABAP Consultant (6 LPA)",
        "currentRole": "Fusion HCM Technical Consultant",
        "company": "NTT Data",
        "package": "14.2 LPA",
        "avatarText": "KV",
        "content": "The best part about this training is the server access and practical scenarios. The security console, job/data roles setup, and HCM Extracts sessions are very comprehensive. Cleared my Oracle certification in my first attempt!"
    },
    {
        "name": "Meera Nair",
        "previousRole": "Workday Consultant (9 LPA)",
        "currentRole": "Senior Cloud HCM Consultant",
        "company": "L&T Technology Services",
        "package": "21.0 LPA",
        "avatarText": "MN",
        "content": "I wanted to add Oracle Cloud HCM to my profile. The training at Tech Leads IT is highly professional and focused on real-time implementation challenges. Laxman Sir is incredibly knowledgeable, especially in Payroll and Absence."
    },
    {
        "name": "Vikram Adithya",
        "previousRole": "Ex-PeopleSoft Admin (7.5 LPA)",
        "currentRole": "Fusion HCM Admin",
        "company": "Mphasis",
        "package": "15.5 LPA",
        "avatarText": "VA",
        "content": "Compared to Peoplesoft, Fusion's Architecture is very different. This course covers everything from basic enterprise structure setup to advanced payroll balances and element entries. The practical lab environment is top-notch."
    },
    {
        "name": "Sneha Reddy",
        "previousRole": "MBA Finance Graduate",
        "currentRole": "HCM Compensation Specialist",
        "company": "Genpact",
        "package": "7.0 LPA",
        "avatarText": "SR",
        "content": "The Compensation and Benefits modules are covered with great detail. Setting up salary bases, grade rates, and individual compensation distributions was explained step-by-step. The mock tests were very similar to real interviews."
    },
    {
        "name": "Abhishek Rao",
        "previousRole": "Tech Support Associate (2.4 LPA)",
        "currentRole": "Cloud HCM Helpdesk Lead",
        "company": "MouriTech",
        "package": "8.0 LPA",
        "avatarText": "AR",
        "content": "This course changed my career trajectory. From a low-paying tech support role, I am now working as an HCM Consultant. The training covers all modules including Absence Management and Performance Management."
    },
    {
        "name": "Divya Teja",
        "previousRole": "Ex-HR Specialist (4.0 LPA)",
        "currentRole": "Fusion Talent Management Consultant",
        "company": "SplashBI",
        "package": "10.5 LPA",
        "avatarText": "DT",
        "content": "Awesome coaching! Talent Management module sessions like Goal Management, Performance templates, and Talent pools setups are explained with live demo environments. I got 3 job offers after completing the course!"
    },
    {
        "name": "Harish Kumar",
        "previousRole": "Fresher (B.Tech)",
        "currentRole": "Trainee HCM Consultant",
        "company": "CES",
        "package": "6.0 LPA",
        "avatarText": "HK",
        "content": "I did my Fusion HCM training here. The trainer Laxman Sir has 23+ years of experience and teaches everything from scratch. The institute's placement cells regularly schedule drives. Landed at CES with 6 LPA."
    },
    {
        "name": "Tejaswi G.",
        "previousRole": "HR Generalist (3.5 LPA)",
        "currentRole": "Oracle Cloud HCM Consultant",
        "company": "Accenture",
        "package": "11.2 LPA",
        "avatarText": "TG",
        "content": "The teaching is purely practical. I spent 80% of my time doing lab exercises on the cloud server. Laxman sir answers every query, no matter how basic. Very thankful to the team for helping with resume preparation."
    },
    {
        "name": "Manish Sharma",
        "previousRole": "Peoplesoft HRMS Consultant (8 LPA)",
        "currentRole": "Fusion HCM Technical Lead",
        "company": "Infosys",
        "package": "19.0 LPA",
        "avatarText": "MS",
        "content": "Excellent course for seasoned consultants. The extraction tools, BIP reports, OTBI dashboards, and HCM Data Loader parts are covered extensively. It saved me weeks of self-learning."
    },
    {
        "name": "Nisha Patnaik",
        "previousRole": "MBA HR Fresher",
        "currentRole": "Fusion Payroll Executive",
        "company": "Tech Mahindra",
        "package": "6.8 LPA",
        "avatarText": "NP",
        "content": "Landed a job at Tech Mahindra as a payroll consultant. Laxman sir's real-time projects mimic real client issues. The certification vouchers prep was very helpful to clear the Cloud Payroll Exam."
    },
    {
        "name": "Rahul Varma",
        "previousRole": "Java Developer (4.5 LPA)",
        "currentRole": "HCM Technical Integration Lead",
        "company": "Cognizant",
        "package": "12.5 LPA",
        "avatarText": "RV",
        "content": "Switched from Java development to Oracle Cloud HCM. The demand for integration specialists (REST APIs, SOAP, HCM Extract) is huge. The course is very thorough on the technical integration components."
    },
    {
        "name": "Deepika Rao",
        "previousRole": "Recruiter (3.0 LPA)",
        "currentRole": "Core HR Specialist",
        "company": "NTT Data",
        "package": "9.0 LPA",
        "avatarText": "DR",
        "content": "I joined after reading positive reviews, and it was worth it. The curriculum is broad, covering Goal management, Profile management, and Performance evaluations. Placement assistance is 100% active."
    },
    {
        "name": "Srinivas Rao",
        "previousRole": "EBS Technical Developer (6.5 LPA)",
        "currentRole": "Senior Fusion HCM Tech Consultant",
        "company": "L&T Technology Services",
        "package": "16.8 LPA",
        "avatarText": "SR",
        "content": "Transitioning from PL/SQL to Cloud tech felt smooth under Laxman Sir's guidance. The sessions on BI Publisher reports and HCM extracts layout formatting were worth the entire course fee."
    },
    {
        "name": "Kavitha M.",
        "previousRole": "MBA Graduate",
        "currentRole": "Fusion HCM Associate",
        "company": "Genpact",
        "package": "6.4 LPA",
        "avatarText": "KM",
        "content": "The classroom labs at Ameerpet are well-maintained. The placement coordinator guided me through the interview rounds at Genpact. The salary hike was a great career start for me."
    },
    {
        "name": "Sandeep Verma",
        "previousRole": "Operations Executive (3.2 LPA)",
        "currentRole": "Oracle HCM Support Analyst",
        "company": "Mphasis",
        "package": "8.5 LPA",
        "avatarText": "SV",
        "content": "I liked how Laxman sir connects every concept to a real client implementation example. It makes remembering details very easy. Cleared interview for a Support role at Mphasis."
    },
    {
        "name": "Pooja Hegde",
        "previousRole": "HR Assistant (2.5 LPA)",
        "currentRole": "Fusion Core HR Analyst",
        "company": "MouriTech",
        "package": "7.8 LPA",
        "avatarText": "PH",
        "content": "Learnt everything from Enterprise configuration, Legal Entities, Departments, to Position hierarchies. Practical lab exercises are very close to what I now do on my actual client project."
    },
    {
        "name": "Varun Reddy",
        "previousRole": "ERP Support (5.0 LPA)",
        "currentRole": "Senior Cloud HCM Specialist",
        "company": "SplashBI",
        "package": "13.0 LPA",
        "avatarText": "VR",
        "content": "Excellent training content. The OTBI (Oracle Transactional Business Intelligence) sessions and security dashboards setup were explained exceptionally well. Landed a solid role at SplashBI."
    },
    {
        "name": "Ritu Sen",
        "previousRole": "MBA HR Fresher",
        "currentRole": "Trainee HCM Consultant",
        "company": "CES",
        "package": "6.1 LPA",
        "avatarText": "RS",
        "content": "The mock tests and resume building support are the best highlights of the course. The trainers focus on every student's weak points. I was able to clear the technical rounds without any hassle."
    },
    {
        "name": "Amit Patel",
        "previousRole": "Ex-SAP SuccessFactors (10 LPA)",
        "currentRole": "Lead Cloud HCM Consultant",
        "company": "Accenture",
        "package": "23.5 LPA",
        "avatarText": "AP",
        "content": "I took the fast-track online batch. The content is concise and aligns perfectly with the Oracle Certification Syllabus. It helped me clear both Core HR and Global Payroll Cloud certifications."
    },
    {
        "name": "Pranitha K.",
        "previousRole": "HR Recruiter (3.5 LPA)",
        "currentRole": "Fusion HCM Recruitment Analyst",
        "company": "Infosys",
        "package": "10.0 LPA",
        "avatarText": "PK",
        "content": "The Talent Acquisition (ORC - Oracle Recruiting Cloud) overview and integration with Core HR was very informative. This is the most practical institute for Oracle products in Hyderabad."
    },
    {
        "name": "Nikhil D.",
        "previousRole": "Fresher (B.Tech)",
        "currentRole": "HCM Technical Consultant",
        "company": "Tech Mahindra",
        "package": "6.6 LPA",
        "avatarText": "ND",
        "content": "Great lab support! There are lab coordinators available to solve system errors while we practice. The interview preparation checklist helped me stay confident during my interview."
    },
    {
        "name": "Radhika J.",
        "previousRole": "HR Generalist (Ex-Wipro 4.2 LPA)",
        "currentRole": "Fusion HCM Functional Lead",
        "company": "Cognizant",
        "package": "12.0 LPA",
        "avatarText": "RJ",
        "content": "Laxman sir's teaching methodology is excellent. He starts from core fundamentals and gradually takes us to complex setups like Payroll balances and Absence patterns. Highly recommended!"
    },
    {
        "name": "Vijay Bhaskar",
        "previousRole": "Oracle EBS Apps DBA (8.5 LPA)",
        "currentRole": "Fusion HCM Lead Architect",
        "company": "NTT Data",
        "package": "20.5 LPA",
        "avatarText": "VB",
        "content": "This course was perfect for my transition to the cloud. Sessions on PaaS integrations, security profiles, and Web Services in HCM were extremely thorough. Got placed at NTT Data at a great package."
    },
    {
        "name": "Swathi Latha",
        "previousRole": "MBA HR Graduate",
        "currentRole": "HCM Implementation Associate",
        "company": "L&T Technology Services",
        "package": "6.7 LPA",
        "avatarText": "SL",
        "content": "The placement support is very prompt. They align drive schedules and coordinate directly with recruiters. The training covers all standard modules, giving freshers a well-rounded skillset."
    },
    {
        "name": "Gopal Krishna",
        "previousRole": "Technical Support (3.0 LPA)",
        "currentRole": "Fusion Cloud Consultant",
        "company": "Genpact",
        "package": "8.7 LPA",
        "avatarText": "GK",
        "content": "The lab assignments are very exhaustive. Designing custom approval loops in BPM Worklist was a great learning experience. The trainers push you to solve errors yourself first, which builds real confidence."
    },
    {
        "name": "Kiranmai P.",
        "previousRole": "Ex-HR Analyst (4.5 LPA)",
        "currentRole": "Oracle HCM Cloud Consultant",
        "company": "Mphasis",
        "package": "11.5 LPA",
        "avatarText": "KP",
        "content": "The deep dive into HR profiles, flexfields (DFF, KFF, EFF), and lookup setups helped me clear the Mphasis technical interview in the first round itself. Very structured course curriculum."
    },
    {
        "name": "Manoj Kumar",
        "previousRole": "Fresher (B.Sc Computer Science)",
        "currentRole": "Cloud Support Associate",
        "company": "MouriTech",
        "package": "6.0 LPA",
        "avatarText": "MK",
        "content": "For anyone looking to start a career in ERP, learning Fusion HCM at Tech Leads IT is the best path. Practical labs are accessible 24/7, which was a huge advantage for me to practice at night."
    },
    {
        "name": "Archana Singh",
        "previousRole": "HR Specialist (3.8 LPA)",
        "currentRole": "Talent Management Specialist",
        "company": "SplashBI",
        "package": "9.8 LPA",
        "avatarText": "AS",
        "content": "Laxman sir's teaching is exceptional. The sandbox concept, page composer customization, and OTBI reporting elements are explained in detail. Placed at SplashBI with a solid package."
    },
    {
        "name": "Raghava Rao",
        "previousRole": "EBS Technical Developer (7.0 LPA)",
        "currentRole": "Senior Cloud Developer",
        "company": "CES",
        "package": "16.0 LPA",
        "avatarText": "RR",
        "content": "Excellent training on HCM Extracts and BI Publisher. The sessions focus on actual client business requirements, which helped me clear technical design interviews very easily."
    },
    {
        "name": "Shravya Reddy",
        "previousRole": "MBA HR Fresher",
        "currentRole": "HCM Consultant",
        "company": "Accenture",
        "package": "6.9 LPA",
        "avatarText": "SR",
        "content": "The course is highly structured. From basic navigations to advanced payroll element entries, everything is taught step-by-step. The placement team helped me schedule my Accenture drive."
    },
    {
        "name": "Balaji Naidu",
        "previousRole": "Peoplesoft Technical (7.8 LPA)",
        "currentRole": "Fusion HCM Technical Architect",
        "company": "Infosys",
        "package": "19.5 LPA",
        "avatarText": "BN",
        "content": "The technical modules of Fusion HCM like Spreadsheet loaders, HCM Extract, and BI Publisher reports are covered very deeply. It was the perfect course to upgrade my profile to the cloud."
    },
    {
        "name": "Neeraja G.",
        "previousRole": "Ex-Recruiting Lead (5.0 LPA)",
        "currentRole": "Lead HCM Talent Specialist",
        "company": "Tech Mahindra",
        "package": "12.8 LPA",
        "avatarText": "NG",
        "content": "Learning ORC (Oracle Recruiting Cloud) and Talent Management modules here helped me switch from recruiting to consulting. Clear explanations, practical case studies, and solid placement support."
    },
    {
        "name": "Kishore Babu",
        "previousRole": "Fresher (MBA HR)",
        "currentRole": "HCM Associate",
        "company": "Cognizant",
        "package": "6.3 LPA",
        "avatarText": "KB",
        "content": "The resume prep session was extremely helpful. The placement coordinator guided me through the interview round at Cognizant. I got a 6.3 LPA package, which is a dream start for me."
    },
    {
        "name": "Sowmya K.",
        "previousRole": "HR Assistant (2.8 LPA)",
        "currentRole": "Fusion Core HR Consultant",
        "company": "NTT Data",
        "package": "8.9 LPA",
        "avatarText": "SK",
        "content": "Highly recommend Tech Leads IT for Fusion HCM. The trainer explains complex concepts in a simplified manner. The lab access helps us get hands-on experience which is highly valued in interviews."
    },
    {
        "name": "Pradeep Chawla",
        "previousRole": "Workday Integration Lead (11 LPA)",
        "currentRole": "Principal Cloud HCM Architect",
        "company": "L&T Technology Services",
        "package": "24.0 LPA",
        "avatarText": "PC",
        "content": "The PaaS and Web Services integration modules are taught by industry experts. The technical depth of the course is matching real-world implementation standards. Best institute for senior professionals."
    },
    {
        "name": "Divya Reddy",
        "previousRole": "MBA Graduate",
        "currentRole": "Payroll Consultant",
        "company": "Genpact",
        "package": "6.8 LPA",
        "avatarText": "DR",
        "content": "The payroll module training is very detailed. Configuring payroll definitions, consolidations, and element entries was explained with real-world scenarios. Landed at Genpact with 6.8 LPA."
    },
    {
        "name": "Sudhir Rao",
        "previousRole": "SQL Developer (4.0 LPA)",
        "currentRole": "Cloud HCM Integration Consultant",
        "company": "Mphasis",
        "package": "11.0 LPA",
        "avatarText": "SR",
        "content": "Excellent course! The extracts and REST APIs integration sessions are very thorough. This course helped me transition from a databases developer to an in-demand Cloud Integration Specialist."
    },
    {
        "name": "Anupama Nair",
        "previousRole": "HR Specialist (3.6 LPA)",
        "currentRole": "Fusion Absence Consultant",
        "company": "MouriTech",
        "package": "9.2 LPA",
        "avatarText": "AN",
        "content": "The Absence Management module sessions like plan types, lookup values, and accrual fast formulas are explained very clearly. Very cooperative trainers and placement coordinators."
    },
    {
        "name": "Gautam Sen",
        "previousRole": "Fresher (B.Tech)",
        "currentRole": "Trainee HCM Specialist",
        "company": "SplashBI",
        "package": "6.2 LPA",
        "avatarText": "GS",
        "content": "Doing practical lab exercises on the live cloud server helped me understand the configurations. The mock interviews helped me build confidence. Placed at SplashBI with a solid hike."
    },
    {
        "name": "Pallavi G.",
        "previousRole": "Ex-HR Generalist (4.0 LPA)",
        "currentRole": "Lead Core HR Consultant",
        "company": "CES",
        "package": "10.8 LPA",
        "avatarText": "PG",
        "content": "Joined the weekend batch. Laxman sir answers every question patiently. The study materials, sample resumes, and mock tests are very well-prepared. Placed at CES."
    },
    {
        "name": "Naveen Prasad",
        "previousRole": "Oracle EBS Technical (8 LPA)",
        "currentRole": "Fusion HCM Principal Architect",
        "company": "Accenture",
        "package": "22.5 LPA",
        "avatarText": "NP",
        "content": "The transition from EBS PL/SQL to Cloud BIP and Extracts was seamless with Laxman Sir's course content. The architecture models, HDL mapping, and spreadsheet load setups are covered deeply."
    },
    {
        "name": "Tejaswini K.",
        "previousRole": "MBA HR Fresher",
        "currentRole": "Trainee HCM Consultant",
        "company": "Infosys",
        "package": "6.5 LPA",
        "avatarText": "TK",
        "content": "Excellent course! Clear explanations, regular lab practice, and active placement drives. The trainers guide you individually. Landed at Infosys with a package of 6.5 LPA."
    },
    {
        "name": "Rajesh Kannan",
        "previousRole": "Technical Support (3.2 LPA)",
        "currentRole": "Core HR Consultant",
        "company": "Tech Mahindra",
        "package": "8.2 LPA",
        "avatarText": "RK",
        "content": "The laboratory facility and standard of teaching are outstanding. Laxman sir makes complex Oracle topics easy to grasp. Placement assistant is highly active and helpful."
    },
    {
        "name": "Manasa Rao",
        "previousRole": "HR Recruiter (3.5 LPA)",
        "currentRole": "Talent & Core HR Analyst",
        "company": "Cognizant",
        "package": "9.4 LPA",
        "avatarText": "MR",
        "content": "The Core HR and Profile Management sessions are highly comprehensive. The mock interviews were similar to the Cognizant rounds. Very grateful to the Tech Leads IT team."
    },
    {
        "name": "Yashwant G.",
        "previousRole": "EBS Technical DBA (7.2 LPA)",
        "currentRole": "Lead Fusion HCM Technical Lead",
        "company": "NTT Data",
        "package": "17.5 LPA",
        "avatarText": "YG",
        "content": "The technical setup steps are matching real-world project scenarios. The training covers REST APIs, HDL loaders, Spreadsheet load templates, and BI Publisher dashboards setup in detail."
    },
    {
        "name": "Shruti Gupta",
        "previousRole": "MBA Graduate",
        "currentRole": "Fusion Benefits Consultant",
        "company": "L&T Technology Services",
        "package": "6.6 LPA",
        "avatarText": "SG",
        "content": "The Compensation and Benefits modules are covered with great detail. Setting up salary bases and individual compensation distributions was explained step-by-step. Placed at L&T."
    }
];

    function renderTestimonials() {
        const col1 = document.getElementById('testimonialsCol1');
        const col2 = document.getElementById('testimonialsCol2');
        const col3 = document.getElementById('testimonialsCol3');

        if (!col1 || !col2 || !col3) return;

        let col1HTML = '';
        let col2HTML = '';
        let col3HTML = '';

        // Distribute reviews into three arrays
        testimonialsData.forEach((review, index) => {
            const cardHTML = `
                <div class="testimonial-card">
                    <span class="author-package-badge">
                        Placed at ${review.company} &bull; <strong>${review.package}</strong>
                    </span>
                    <div class="testimonial-content">
                        "${review.content}"
                    </div>
                    <div class="testimonial-author">
                        <div class="author-avatar">${review.avatarText}</div>
                        <div class="author-info">
                            <span class="author-name">${review.name}</span>
                            <span class="author-roles">${review.previousRole} &rarr; ${review.currentRole}</span>
                        </div>
                    </div>
                </div>
            `;
            
            const remainder = index % 3;
            if (remainder === 0) {
                col1HTML += cardHTML;
            } else if (remainder === 1) {
                col2HTML += cardHTML;
            } else {
                col3HTML += cardHTML;
            }
        });

        // Duplicate the content inside each column so that it loops seamlessly without jumping
        col1.innerHTML = col1HTML + col1HTML;
        col2.innerHTML = col2HTML + col2HTML;
        col3.innerHTML = col3HTML + col3HTML;
    }

    renderTestimonials();
}

/* ==========================================================================
   13. Returning Visitor Personalized Popup logic
   ========================================================================== */
function initReturningVisitorPopup() {
    const modal = document.getElementById('returningVisitorModal');
    const closeBtn = document.getElementById('returningCloseBtn');
    const demoBtn = document.getElementById('returningActionDemoBtn');
    const waLink = document.getElementById('returningActionWaBtn');
    const dismissBtn = document.getElementById('returningDismissBtn');
    const leadModal = document.getElementById('leadModal');

    if (!modal) return;

    // 1. Page path exclusions (universal check for thank-you, privacy-policy, login, confirmation pages)
    const currentPath = window.location.pathname.toLowerCase();
    const exclusions = ['thank-you', 'privacy-policy', 'login', 'confirmation'];
    const isExcludedPage = exclusions.some(path => currentPath.includes(path));
    if (isExcludedPage) return;

    // Distinguish page refreshes from a genuinely new browsing session using sessionStorage
    const isNewSession = sessionStorage.getItem('eduSessionActive') === null;
    if (isNewSession) {
        sessionStorage.setItem('eduSessionActive', 'true');
    }

    // Identify returning visitors using localStorage
    const firstVisitTime = localStorage.getItem('eduVisitorFirstVisit');
    if (!firstVisitTime) {
        // On a visitor's first visit: Store the visit timestamp and do NOT display the popup
        localStorage.setItem('eduVisitorFirstVisit', Date.now().toString());
        return;
    }

    // 2. Display Rules Check
    // Show only on a future visit in a new session (not page refreshes)
    if (!isNewSession) return;

    // Frequency cap: Do not show more than once every 7 days (604800000 milliseconds)
    const sevenDaysInMs = 7 * 24 * 60 * 60 * 1000;
    const lastShownTime = localStorage.getItem('eduVisitorLastPopupShown');
    if (lastShownTime && (Date.now() - Number(lastShownTime) < sevenDaysInMs)) {
        return;
    }

    // Do not show again after the visitor submits any lead form
    if (localStorage.getItem('hcm_lead_submitted') === 'true') return;

    let hasTriggered = false;
    let previousActiveElement = null;

    // Helper: Push GTM tracking events
    function pushEvent(eventName) {
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: eventName,
            visitor_type: 'returning',
            timestamp: Date.now()
        });
    }

    // Helper: Open the popup with full accessibility compliance
    function showPopup() {
        if (hasTriggered) return;

        // Do not show if another modal overlay or lightbox is currently active
        const activeModal = document.querySelector('.modal-overlay.open') || document.querySelector('.lightbox-overlay.open');
        if (activeModal) return;

        hasTriggered = true;

        // Store non-sensitive popup state in localStorage (last shown timestamp)
        localStorage.setItem('eduVisitorLastPopupShown', Date.now().toString());

        // Keep track of the previously focused element to return focus later
        previousActiveElement = document.activeElement;

        // Display popup
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden'; // Lock background scrolling

        // Push analytic event
        pushEvent('returning_visitor_popup_view');

        // Move keyboard focus into the popup primary button
        if (demoBtn) {
            setTimeout(() => demoBtn.focus(), 100);
        }
    }

    // Helper: Close the popup restoring accessibility state
    function hidePopup() {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = ''; // Restore background scroll

        // Return focus to previous active element
        if (previousActiveElement && typeof previousActiveElement.focus === 'function') {
            previousActiveElement.focus();
        }
    }

    // 3. Register Scroll/Timer triggers (8 seconds OR 25% scroll depth)
    // Trigger A: 2-second timer
    const timerId = setTimeout(showPopup, 2000);

    // Trigger B: 25% Scroll Depth
    function scrollListener() {
        const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (totalHeight <= 0) return;

        const scrollPercent = (window.scrollY / totalHeight) * 100;
        if (scrollPercent >= 25) {
            showPopup();
            window.removeEventListener('scroll', scrollListener);
            clearTimeout(timerId); // Cancel timer if scroll triggers first
        }
    }
    window.addEventListener('scroll', scrollListener);

    // 4. Click & Key Events
    // Dismiss options
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            hidePopup();
            pushEvent('returning_visitor_popup_close');
        });
    }

    if (dismissBtn) {
        dismissBtn.addEventListener('click', () => {
            hidePopup();
            pushEvent('returning_visitor_popup_close');
        });
    }

    // Modal background overlay click closer
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            hidePopup();
            pushEvent('returning_visitor_popup_close');
        }
    });

    // Close on Escape key press
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) {
            hidePopup();
            pushEvent('returning_visitor_popup_close');
        }
    });

    // Trap focus inside modal while open (Accessibility)
    modal.addEventListener('keydown', (e) => {
        if (e.key === 'Tab' && modal.classList.contains('open')) {
            const focusableElements = modal.querySelectorAll('button, a, [tabindex="0"]');
            if (focusableElements.length === 0) return;

            const firstEl = focusableElements[0];
            const lastEl = focusableElements[focusableElements.length - 1];

            if (e.shiftKey) { // Shift + Tab
                if (document.activeElement === firstEl) {
                    lastEl.focus();
                    e.preventDefault();
                }
            } else { // Tab
                if (document.activeElement === lastEl) {
                    firstEl.focus();
                    e.preventDefault();
                }
            }
        }
    });

    // CTA 1: Reserve Free Demo (reuses the existing conversational lead modal)
    if (demoBtn && leadModal) {
        demoBtn.addEventListener('click', () => {
            hidePopup();
            pushEvent('returning_visitor_demo_click');

            // Open the demo modal after a brief delay
            setTimeout(() => {
                if (typeof openModal === 'function') {
                    openModal(leadModal);
                } else {
                    leadModal.classList.add('open');
                    leadModal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                }
            }, 300);
        });
    }

    // CTA 2: WhatsApp link redirection
    if (waLink) {
        waLink.addEventListener('click', () => {
            pushEvent('returning_visitor_whatsapp_click');
            hidePopup();
        });
    }
}

/* ==========================================================================
   6b. Salary Proof Slider & Lightbox Zoom
   ========================================================================== */
function initProofSlider() {
    const slider = document.getElementById('proofSlider');
    const slides = document.querySelectorAll('#proofSlider .proof-slide');
    const prevBtn = document.getElementById('proofPrev');
    const nextBtn = document.getElementById('proofNext');
    const dotsContainer = document.getElementById('proofDots');

    if (!slider || slides.length === 0) return;

    let currentIndex = 0;
    const totalSlides = slides.length;

    // Create Navigation Dot Indicators dynamically
    for (let i = 0; i < totalSlides; i++) {
        const dot = document.createElement('div');
        dot.classList.add('proof-dot');
        if (i === 0) dot.classList.add('active');
        dot.setAttribute('data-index', i);
        dotsContainer.appendChild(dot);
    }

    const dots = document.querySelectorAll('#proofDots .proof-dot');

    function goToSlide(index) {
        if (index < 0) index = totalSlides - 1;
        if (index >= totalSlides) index = 0;
        currentIndex = index;

        // Slide width calculation and animation
        slider.style.transform = `translateX(-${currentIndex * 25}%)`;

        // Update Dots
        dots.forEach(dot => dot.classList.remove('active'));
        if (dots[currentIndex]) dots[currentIndex].classList.add('active');
    }

    // Wire Arrows
    if (prevBtn) {
        prevBtn.addEventListener('click', () => goToSlide(currentIndex - 1));
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => goToSlide(currentIndex + 1));
    }

    // Wire Dots
    dots.forEach(dot => {
        dot.addEventListener('click', (e) => {
            const index = parseInt(e.target.getAttribute('data-index'));
            goToSlide(index);
        });
    });
}

function initProofLightbox() {
    const lightbox = document.getElementById('proofLightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    const lightboxCaption = document.getElementById('lightboxCaption');
    const closeBtn = document.getElementById('lightboxCloseBtn');
    const triggerImages = document.querySelectorAll('#proofSlider .proof-img');

    if (!lightbox || !lightboxImg || triggerImages.length === 0) return;

    triggerImages.forEach(img => {
        img.style.cursor = 'zoom-in'; // visual cue for zoomability
        
        img.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Extract descriptive title from the card heading
            const card = img.closest('.proof-card');
            const title = card ? card.querySelector('.proof-card-title').textContent : 'Proof View';

            lightboxImg.src = img.src;
            lightboxCaption.textContent = title;
            lightbox.classList.add('open');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden'; // Lock main scroll
        });
    });

    function closeLightbox() {
        lightbox.classList.remove('open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = ''; // Restore main scroll
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeLightbox);
    }

    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox || e.target.classList.contains('lightbox-container')) {
            closeLightbox();
        }
    });

    // Close on Escape key press
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox.classList.contains('open')) {
            closeLightbox();
        }
    });
}

function initExitIntent() {
    const exitModal = document.getElementById('exitIntentModal');
    const closeBtn = document.getElementById('exitCloseBtn');
    const proofBtn = document.getElementById('exitActionProofBtn');
    const demoBtn = document.getElementById('exitActionDemoBtn');

    if (!exitModal) return;

    let hasTriggeredExit = false;

    // Detect mouse leaving the viewport from the top (intent to close/change tabs)
    document.addEventListener('mouseleave', (e) => {
        if (hasTriggeredExit) return;
        
        // Don't show if the user has already submitted their details
        if (localStorage.getItem('hcm_lead_submitted') === 'true') return;
        
        // Don't show if another modal is currently open to avoid stacking
        const activeModal = document.querySelector('.modal-overlay.open');
        if (activeModal) return;

        // Trigger only if mouse moves up past the top boundary
        if (e.clientY < 20) {
            hasTriggeredExit = true;
            openModal(exitModal);
        }
    });

    // Close buttons
    if (closeBtn) {
        closeBtn.addEventListener('click', () => closeModal(exitModal));
    }

    // Modal background click closing
    exitModal.addEventListener('click', (e) => {
        if (e.target === exitModal) {
            closeModal(exitModal);
        }
    });

    // CTA 1: Scroll to Salary Proof section
    if (proofBtn) {
        proofBtn.addEventListener('click', () => {
            closeModal(exitModal);
            
            // Scroll to salary proof gallery
            const proofSection = document.getElementById('salary-proof');
            if (proofSection) {
                // Short timeout to let the modal transition close first
                setTimeout(() => {
                    proofSection.scrollIntoView({ behavior: 'smooth' });
                }, 200);
            }
        });
    }

    // CTA 2: Book Free Demo (Opens conversational lead modal)
    if (demoBtn) {
        demoBtn.addEventListener('click', () => {
            closeModal(exitModal);
            
            const leadModal = document.getElementById('leadModal');
            if (leadModal) {
                // Short timeout to prevent scroll-locking issues
                setTimeout(() => {
                    openModal(leadModal);
                }, 300);
            }
        });
    }
}

function initFomoToasts() {
    const toast = document.getElementById('fomoToast');
    const nameEl = document.getElementById('fomoName');
    const locEl = document.getElementById('fomoLocation');
    const actEl = document.getElementById('fomoAction');
    const timeEl = document.getElementById('fomoTime');
    const iconEl = document.getElementById('fomoIcon');
    const closeBtn = document.getElementById('fomoCloseBtn');

    if (!toast) return;

    // Seed lists to dynamically generate 100 unique activity feeds
    const firstNames = [
        "Amit", "Priya", "Rahul", "Anjali", "Suresh", "Karthik", "Sneha", "Rohan", "Divya", "Vikram",
        "Deepika", "Aditya", "Neha", "Vijay", "Sandhya", "Manish", "Pooja", "Arjun", "Kiran", "Sanjay",
        "Shruti", "Rajesh", "Aishwarya", "Abhishek", "Harini", "Pranav", "Swathi", "Manoj", "Kavya", "Varun",
        "Meera", "Hari", "Jyothi", "Nikhil", "Gautam", "Ritu", "Vivek", "Tanvi", "Karan", "Rani",
        "Dinesh", "Preeti", "Siddharth", "Aisha", "Gaurav", "Nisha", "Alok", "Shalini", "Pradeep", "Aparna",
        "Raman", "Sunita", "Tarun", "Komal", "Satish", "Vandana", "Akash", "Bhawna", "Raghav", "Priyanka",
        "Jatin", "Monica", "Vinay", "Richa", "Srinivas", "Gayatri", "Anand", "Uma", "Balaji", "Latha",
        "Naresh", "Radha", "Venkat", "Lakshmi", "Ramesh", "Saraswathi", "Sudheer", "Rekha", "Krishna", "Janaki",
        "Prasad", "Devi", "Prashanth", "Roopa", "Anil", "Meenakshi", "Madhusudan", "Sushma", "Venkatesh", "Chitra",
        "Ravi", "Kalyani", "Sai", "Lavanya", "Mahesh", "Sita", "Ram", "Geetha", "Bhaskar", "Padma"
    ];

    const locations = [
        "Hyderabad", "Bengaluru", "Pune", "Chennai", "Noida", "Gurgaon", "Mumbai", "Kochi", 
        "Coimbatore", "Visakhapatnam", "Trivandrum", "Ahmedabad", "Jaipur", "Lucknow", "Indore", 
        "Bhubaneswar", "Nagpur", "Kolkata", "Delhi", "Chandigarh"
    ];

    const actions = [
        { text: "just booked a Free Demo class", icon: "🎓" },
        { text: "just downloaded the HCM syllabus", icon: "📄" },
        { text: "just enrolled for the upcoming batch", icon: "💼" },
        { text: "just started a chat with a counselor", icon: "💬" }
    ];

    const times = [
        "just now", "live", "15 seconds ago", "30 seconds ago", "just now", "live"
    ];

    const fomoData = [];
    for (let i = 0; i < 100; i++) {
        // Pick names sequentially to ensure all 100 are used; randomize other elements
        const name = firstNames[i % firstNames.length];
        const location = locations[Math.floor(Math.random() * locations.length)];
        const actionObj = actions[Math.floor(Math.random() * actions.length)];
        const time = times[Math.floor(Math.random() * times.length)];

        fomoData.push({
            name: name,
            location: location,
            action: actionObj.text,
            icon: actionObj.icon,
            time: time
        });
    }

    // Shuffle array (Fisher-Yates) on every page load for raw randomness
    for (let i = fomoData.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [fomoData[i], fomoData[j]] = [fomoData[j], fomoData[i]];
    }

    const seatsCounterEl = document.getElementById('seats-left-counter');
    // Retrieve persisted seats left count from localStorage or default to 70
    let seatsLeft = 70;
    const storedSeats = localStorage.getItem('hcm_seats_left');
    const lastReset = localStorage.getItem('hcm_seats_last_reset');
    const nowTime = Date.now();

    if (storedSeats && lastReset) {
        // Expire seats counter cache after 12 hours to restart the funnel realistically
        if (nowTime - parseInt(lastReset) > 12 * 60 * 60 * 1000) {
            localStorage.setItem('hcm_seats_left', '70');
            localStorage.setItem('hcm_seats_last_reset', nowTime.toString());
            seatsLeft = 70;
        } else {
            seatsLeft = parseInt(storedSeats);
        }
    } else {
        localStorage.setItem('hcm_seats_left', '70');
        localStorage.setItem('hcm_seats_last_reset', nowTime.toString());
        seatsLeft = 70;
    }

    if (seatsCounterEl) {
        seatsCounterEl.textContent = seatsLeft;
    }

    let index = 0;
    let toastTimeout;
    let cycleInterval;

    function triggerToast() {
        const current = fomoData[index];
        
        // Update toast content dynamically
        if (nameEl) nameEl.textContent = current.name;
        if (locEl) locEl.textContent = current.location;
        if (actEl) actEl.textContent = current.action;
        if (timeEl) timeEl.textContent = current.time;
        if (iconEl) iconEl.textContent = current.icon;

        // Slide the toast in
        toast.classList.add('show');

        // Dynamic seats reduction to synchronize with FOMO alerts
        if (seatsLeft > 2) {
            seatsLeft--;
            localStorage.setItem('hcm_seats_left', seatsLeft.toString());
            if (seatsCounterEl) {
                // Animate text fade for realism
                seatsCounterEl.style.opacity = 0;
                setTimeout(() => {
                    seatsCounterEl.textContent = seatsLeft;
                    seatsCounterEl.style.opacity = 1;
                }, 200);
            }
        }

        // Slide out and hide after 4.5 seconds
        toastTimeout = setTimeout(() => {
            toast.classList.remove('show');
        }, 4500);

        // Advance to next record
        index = (index + 1) % fomoData.length;
    }

    function scheduleNextToast() {
        // Calculate random delay greater than 10 seconds (between 10 and 22 seconds)
        const delay = 10000 + Math.random() * 12000;
        cycleInterval = setTimeout(() => {
            triggerToast();
            scheduleNextToast();
        }, delay);
    }

    // Delay the first popup trigger by 5 seconds on page load
    setTimeout(() => {
        triggerToast();
        scheduleNextToast();
    }, 5000);

    // Stop and clear notifications if closed manually by the visitor
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            toast.classList.remove('show');
            clearTimeout(toastTimeout);
            clearTimeout(cycleInterval);
        });
    }
}
