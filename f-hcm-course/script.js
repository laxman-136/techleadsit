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

    // Retrieve end date from localStorage or create a new one
    let targetTime = localStorage.getItem('hcm_countdown_target');
    const now = new Date().getTime();

    if (!targetTime || parseInt(targetTime) < now) {
        // Create target time: target days in the future
        targetTime = now + (daysSpan * 24 * 60 * 60 * 1000);
        localStorage.setItem('hcm_countdown_target', targetTime.toString());
    }

    const targetDate = new Date(parseInt(targetTime));

    function updateTimer() {
        const currentTime = new Date().getTime();
        const difference = targetDate - currentTime;

        if (difference <= 0) {
            // Timer expired, reset target to another daysSpan
            const newTarget = currentTime + (daysSpan * 24 * 60 * 60 * 1000);
            localStorage.setItem('hcm_countdown_target', newTarget.toString());
            location.reload(); // Refresh to start countdown over
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

    const headlines = {
        A: "Go From Learning Oracle Fusion HCM to Implementing It — In 2.5 Months",
        B: "The Oracle Fusion HCM Course 5,000+ Learners Used to Change Careers",
        C: "Oracle Fusion HCM Training Built for Real Implementation Work, Not Just Theory"
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

    if (!timeline || nodes.length === 0) return;

    // Curriculum details DB matching exact brochure content
    const curriculumData = {
        1: {
            number: "Module 1",
            name: "Functional Setup Manager",
            topics: "Getting started with Fusion Applications · Initial activities for Oracle Fusion Implementation · Preparing Fusion Applications · User & Role Management · Creating Implementation Users via FA Super-User · Synchronizing Users and Roles in LDAP with Fusion HCM · Introduction to Functional Setup Manager · Creation of Implementation Project (Offerings, Options, Features) · Fusion HCM Business Process Overview"
        },
        2: {
            number: "Module 2",
            name: "Core HR",
            topics: "Implementation Tasks · Manage Geographies · Define Currencies & Locations · Manage Enterprise Creation, Structure & Components · Enterprise Divisions, Legal Entities, Business Units · Reference Data Sets · Manage Work Structures (Departments, Jobs, Positions, Grades & Grade Rates) · HCM Security Profiles · Role Mapping · Profile Options · Employment Model (Two-Tier & Three-Tier: Work Relationship, Employment Terms, Assignment)"
        },
        3: {
            number: "Module 3",
            name: "Global Payroll",
            topics: "Payroll Introduction · HCM Security · Common Application Configurations · Define Payroll Business Definitions, Pay Frequencies, Payroll Elements · Create Element Entries · Define Fast Formulas & Balance Definitions · Calculate & Run Payroll · Define Events, Payment Methods, Payroll Costing · Payroll Flexfields · Object Groups · Payroll Patterns & Security · Payslip Overview"
        },
        4: {
            number: "Module 4",
            name: "Profile & Performance Management",
            topics: "Introducing Oracle Fusion Talent Management · Main Business Activities · Security & Functional Setup Manager Overview · Role-Based Access Control Role Types · Talent Management Job Roles & Duties · Creating Implementation Projects and Assigning Tasks · Define Talent Profile Settings · Profile Management"
        },
        5: {
            number: "Module 5",
            name: "Compensation",
            topics: "Introduction to Compensation Management · Types of Compensation · Base Pay Configuration, Pay Levels & Ranges · Implementing a Compensation Program · Administering Pay Increases and Bonuses · Linking Compensation to Performance · Salary Basis & Salary Ranges · Grade Ladder with Progression Configuration · Individual & Workforce Compensation Plans"
        },
        6: {
            number: "Module 6",
            name: "Talent Management",
            topics: "Integrations and Setup · Define Talent Profile Content · Content Library, Content Types & Items · Rating Models & Talent Profiles · Profile Types, Components & Instance Qualifiers · Writing Assistant · Manage Talent Profiles · Team Talent & Talent Profile Cards · Compare Items and Best Fit · Creating Review Periods · Managing Performance Document Types & Goal Library · Talent Management Notifications & Auditing"
        },
        7: {
            number: "Module 7",
            name: "Goal Management Concepts",
            topics: "Goal Management Setup · Lookups, Flexfields & Profile Options · Managing & Creating Goal Plans and Plan Sets · Administering and Mass Assigning Goals · Managing Worker/Organization Goals & Approvals · Questionnaires (Concepts, Question Library, Response Types, Templates) · Understanding & Defining Worker Performance · Performance Management Overview · Performance Roles & Matrix Management · Eligibility Profiles & Process Flow Definitions · Performance Templates & Sections · Performance Documents and Worker Evaluations"
        },
        8: {
            number: "Module 8",
            name: "Oracle HCM Communicate",
            topics: "Target Messaging · Newsletter Customization · Scheduling · Message Tracking · Collaboration · Audience Filtering & Creation"
        },
        9: {
            number: "Module 9",
            name: "Absence Management",
            topics: "Absence Management Setup & Supporting Components · Accrual Plan Attributes, Types & Participation · Eligibility Profiles · Defining Accrual Limits, Rates & Balances · Qualification Plans and Term Types · Fast Formulas in Absence Management · Absence Types, Reasons, Categories & Certifications · Scheduling and Maintaining Absences · Managing Absence Records, Entitlements & Approvals · Monitoring Absence Processes"
        },
        10: {
            number: "Module 10",
            name: "Time and Labour",
            topics: "Repeating Time Periods · Time Card Layout Components & Sets · Manage HCM Groups · Create Time Card Components · Time Card Calculation Rule Templates & Rules · Worker Time Entry Profile · Time Processing Profile · Loading of Timecards"
        },
        11: {
            number: "Module 11",
            name: "Technical Concepts",
            topics: "OTBI Reports · BI Reports · FBL · Fast Formulas · HCM Data Loader · Spreadsheet Data Loader"
        }
    };

    function selectModule(index) {
        // Toggle active classes on timeline list nodes
        nodes.forEach(node => node.classList.remove('active'));
        const activeNode = timeline.querySelector(`.timeline-node[data-index="${index}"]`);
        if (activeNode) activeNode.classList.add('active');

        // Draw progress line height
        const percent = ((index - 1) / (nodes.length - 1)) * 100;
        progressLine.style.height = `${percent}%`;

        // Load details to right content box with fade animation
        const moduleData = curriculumData[index];
        if (moduleData) {
            paneNum.style.opacity = 0;
            paneName.style.opacity = 0;
            paneContent.style.opacity = 0;
            
            setTimeout(() => {
                paneNum.textContent = moduleData.number;
                paneName.textContent = moduleData.name;
                
                // Format topics with high-fidelity block elements
                const topicsArray = moduleData.topics.split(' · ');
                let formattedHtml = '<div class="topics-grid-container">';
                topicsArray.forEach(topic => {
                    formattedHtml += `
                        <div class="topic-block-card">
                            <div class="topic-check-badge">
                                <svg class="tick-icon" viewBox="0 0 24 24">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                            </div>
                            <span class="topic-block-text">${topic}</span>
                        </div>
                    `;
                });
                formattedHtml += '</div>';
                
                paneContent.innerHTML = formattedHtml;
                
                paneNum.style.opacity = 1;
                paneName.style.opacity = 1;
                paneContent.style.opacity = 1;
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

    items.forEach(item => {
        const header = item.querySelector('.accordion-header');
        
        header.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            
            // Collapse all other items
            items.forEach(el => {
                el.classList.remove('active');
                el.querySelector('.accordion-header').setAttribute('aria-expanded', 'false');
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
            scm_year: 'HCM Course',
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
            scm_year: 'HCM Syllabus Download',
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
    // Trigger A: 8-second timer
    const timerId = setTimeout(showPopup, 8000);

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
        "2 minutes ago", "5 minutes ago", "8 minutes ago", "12 minutes ago", "15 minutes ago", 
        "22 minutes ago", "28 minutes ago", "35 minutes ago", "42 minutes ago", "50 minutes ago"
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

        // Slide out and hide after 4.5 seconds
        toastTimeout = setTimeout(() => {
            toast.classList.remove('show');
        }, 4500);

        // Advance to next record
        index = (index + 1) % fomoData.length;
    }

    // Delay the first popup trigger by 5 seconds on page load
    setTimeout(() => {
        triggerToast();
        
        // Start running every 10 seconds thereafter
        cycleInterval = setInterval(triggerToast, 10000);
    }, 5000);

    // Stop and clear notifications if closed manually by the visitor
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            toast.classList.remove('show');
            clearTimeout(toastTimeout);
            clearInterval(cycleInterval);
        });
    }
}
