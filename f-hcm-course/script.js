// ==========================================================================
// Oracle Fusion HCM Landing Page — Script Actions
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {

    // ==========================================================================
    // Persistent Ascending Live Visitor Counter (Never Decreases)
    // ==========================================================================
    (function initPersistentVisitorCounter() {
        const courseId = window.TECHLEADSIT_BATCH_CONFIG?.courseId || 'hcm';
        const storageKey = 'techleadsit_visitor_counter_' + courseId;
        const baseFloor = parseInt(window.TECHLEADSIT_BATCH_CONFIG?.baseVisitorFloor, 10) || 1842;
        
        // Retrieve stored count
        let stored = parseInt(localStorage.getItem(storageKey), 10);
        if (isNaN(stored) || stored < baseFloor) {
            stored = baseFloor + Math.floor(Math.random() * 8) + 1;
        } else {
            // Increment monotonically on every visit (never decrease)
            stored += Math.floor(Math.random() * 2) + 1;
        }
        localStorage.setItem(storageKey, stored);

        // Animate counter display
        const countEl = document.getElementById('liveVisitorCount');
        if (countEl) {
            let current = Math.max(baseFloor - 40, stored - 25);
            const step = Math.max(1, Math.ceil((stored - current) / 15));
            const timer = setInterval(() => {
                current += step;
                if (current >= stored) {
                    current = stored;
                    clearInterval(timer);
                }
                countEl.textContent = Number(current).toLocaleString('en-IN');
            }, 40);
        }

        // Live viewers in batch card (organic fluctuation between 18 and 36)
        const liveViewerEl = document.getElementById('cohortLiveViewerCount');
        if (liveViewerEl) {
            let viewers = 18 + Math.floor(Math.random() * 15);
            liveViewerEl.textContent = viewers;
            
            setInterval(() => {
                const delta = Math.random() > 0.5 ? 1 : -1;
                viewers = Math.min(38, Math.max(14, viewers + delta));
                liveViewerEl.textContent = viewers;
            }, 12000);
        }
    })();


    // ==========================================================================
    // Dynamic Campaign Strategy: URL Query Overrides & Marketing Parameters
    // ==========================================================================
    (function handleMarketingCampaignOverrides() {
        const params = new URLSearchParams(window.location.search);
        
        // 1. Batch Date Override (e.g. ?batch_date=28th+Sep,+26)
        if (params.has('batch_date')) {
            const customDate = params.get('batch_date');
            document.querySelectorAll('.cohort-date-val').forEach(el => el.textContent = customDate);
        }

        // 2. Batch Timing Override (e.g. ?batch_time=7:00+PM+to+8:30+PM)
        if (params.has('batch_time')) {
            const customTime = params.get('batch_time');
            document.querySelectorAll('.cohort-time-item span').forEach(el => el.textContent = customTime);
        }

        // 3. Batch Seats Left (e.g. ?seats=3)
        if (params.has('seats')) {
            const customSeats = params.get('seats');
            document.querySelectorAll('#cohortSeatsCounter, #seats-left-counter').forEach(el => el.textContent = customSeats);
        }

        // 4. Section Visibility Toggles (e.g. ?hide_batch=1 or ?hide_countdown=1)
        if (params.get('hide_batch') === '1') {
            const b = document.getElementById('cohort-schedule');
            if (b) b.style.display = 'none';
        }
        if (params.get('hide_countdown') === '1') {
            const c = document.getElementById('countdownBar');
            if (c) c.style.display = 'none';
        }

        // 5. Custom Countdown Target (e.g. ?countdown=2026-09-28T19:00)
        if (params.has('countdown')) {
            window.TECHLEADSIT_BATCH_CONFIG = window.TECHLEADSIT_BATCH_CONFIG || {};
            window.TECHLEADSIT_BATCH_CONFIG.countdownTarget = params.get('countdown');
        }

        // 6. Reset Marketing Strategy Cache (e.g. ?reset_campaign=1)
        if (params.get('reset_campaign') === '1') {
            localStorage.removeItem('hcm_lead_submitted');
            sessionStorage.clear();
            console.log('[Campaign] Marketing storage reset successfully.');
        }
    })();

    // Initialize Lenis Smooth Scroll
    if (typeof Lenis !== 'undefined') {
        const lenis = new Lenis({
            duration: 1.2,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            direction: 'vertical',
            gestureDirection: 'vertical',
            smooth: true,
            mouseMultiplier: 1,
            smoothTouch: false,
            touchMultiplier: 2,
            infinite: false,
        });

        function raf(time) {
            lenis.raf(time);
            requestAnimationFrame(raf);
        }

        requestAnimationFrame(raf);
        
        // Link internal navigation anchors to Lenis scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    lenis.scrollTo(target);
                }
            });
        });

        // Prevent Lenis from blocking scroll inside modals and lightboxes
        document.querySelectorAll('.modal-overlay, .modal-dialog, .lightbox-overlay').forEach(el => {
            el.setAttribute('data-lenis-prevent', 'true');
        });
    }

    // Disable auto-fill dropdowns dynamically for security and lead quality
    document.querySelectorAll('form').forEach(form => {
        form.setAttribute('autocomplete', 'off');
    });
    document.querySelectorAll('form input:not([type="hidden"]), form select').forEach(input => {
        input.setAttribute('autocomplete', 'new-password');
    });

    // GA4 Visitor Type Identification Event
    window.dataLayer = window.dataLayer || [];
    const isReturningVisitor = localStorage.getItem('eduVisitorFirstVisit') !== null;
    window.dataLayer.push({
        event: 'visitor_identified',
        visitor_type: isReturningVisitor ? 'returning' : 'new',
        timestamp: Date.now()
    });

    // Check for popup debug parameter
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('test_popups')) {
        window.bypassPopupLimits = true;
        initPopupDebugger();
    }
    
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
   1. Evergreen / Targeted Countdown Timer (Target: Configurable from WP Admin)
   ========================================================================== */
function initCountdown() {
    const dEl = document.getElementById('days');
    const hEl = document.getElementById('hours');
    const mEl = document.getElementById('minutes');
    const sEl = document.getElementById('seconds');
    
    if (!dEl || !hEl || !mEl || !sEl) return;

    function getTargetTime() {
        // 1. Check if set dynamically via WordPress Admin Settings
        if (window.TECHLEADSIT_BATCH_CONFIG && window.TECHLEADSIT_BATCH_CONFIG.countdownTarget) {
            const rawTarget = window.TECHLEADSIT_BATCH_CONFIG.countdownTarget;
            const parsed = new Date(rawTarget).getTime();
            if (!isNaN(parsed)) {
                const now = new Date().getTime();
                if (now < parsed) {
                    return parsed;
                }
                // If the target in WP has expired, roll over to the next 7-day upcoming cycle
                const daysToAdd = 7 - (((now - parsed) / (1000 * 60 * 60 * 24)) % 7);
                return parsed + Math.floor(daysToAdd * 24 * 60 * 60 * 1000);
            }
        }

        // 2. Default target: 23 September 2026, 20:30:00 (8:30 PM IST)
        const now = new Date();
        const year = now.getFullYear();
        let target = new Date(year, 8, 23, 20, 30, 0, 0);

        if (now.getTime() >= target.getTime()) {
            const daysToAdd = 7 - (((now.getTime() - target.getTime()) / (1000 * 60 * 60 * 24)) % 7);
            target = new Date(now.getTime() + daysToAdd * 24 * 60 * 60 * 1000);
            target.setHours(20, 30, 0, 0);
        }
        return target.getTime();
    }

    const targetTime = getTargetTime();

    function updateTimer() {
        const currentTime = new Date().getTime();
        const difference = targetTime - currentTime;

        if (difference <= 0) {
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

        dEl.textContent = String(days).padStart(2, '0');
        hEl.textContent = String(hours).padStart(2, '0');
        mEl.textContent = String(minutes).padStart(2, '0');
        sEl.textContent = String(seconds).padStart(2, '0');
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
    const advisorModal = document.getElementById('advisorModal');
    const closeLeadBtn = document.getElementById('modalCloseBtn');
    const closeDownloadBtn = document.getElementById('downloadCloseBtn');
    const closeAdvisorBtn = document.getElementById('advisorCloseBtn');
    
    // Wire all cta-triggers, gate-demo-btns, and apply buttons to open the Lead Capture Modal
    document.querySelectorAll('.cta-trigger, .gate-demo-btn, .cohort-quick-apply-btn, .btn-cohort-solid').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(leadModal);
        });
    });

    // Wire Request a Callback button to open Advisor Modal
    const callbackBtn = document.getElementById('cohortCallbackBtn');
    if (callbackBtn && advisorModal) {
        callbackBtn.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(advisorModal);
        });
    }

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
        if (!window.bypassPopupLimits && localStorage.getItem('hcm_lead_submitted') === 'true') {
            console.log('[Popups] Scroll 45% popup bypassed: lead already submitted.');
            return;
        }

        // Calculate scroll percentage
        const scrollTop = window.scrollY || window.pageYOffset || document.documentElement.scrollTop;
        const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (totalHeight <= 0) return;

        const scrollPercent = (scrollTop / totalHeight) * 100;
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
    let hasTriggeredAdvisorModal = false;

    window.addEventListener('scroll', () => {
        if (hasTriggeredAdvisorModal) return;
        if (!window.bypassPopupLimits && localStorage.getItem('hcm_lead_submitted') === 'true') {
            console.log('[Popups] Scroll 75% advisor popup bypassed: lead already submitted.');
            return;
        }

        const scrollTop = window.scrollY || window.pageYOffset || document.documentElement.scrollTop;
        const totalHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (totalHeight <= 0) return;

        const scrollPercent = (scrollTop / totalHeight) * 100;
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

    // Push analytics events
    window.dataLayer = window.dataLayer || [];
    const modalId = modal.getAttribute('id');
    if (modalId) {
        window.dataLayer.push({
            event: 'modal_open',
            modal_id: modalId,
            timestamp: Date.now()
        });
    }
}

function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = ''; // Restore background scroll

    // Push analytics events
    window.dataLayer = window.dataLayer || [];
    const modalId = modal.getAttribute('id');
    if (modalId) {
        window.dataLayer.push({
            event: 'modal_close',
            modal_id: modalId,
            timestamp: Date.now()
        });
    }
}

// Make them explicitly global
window.openModal = openModal;
window.closeModal = closeModal;

/* ==========================================================================
   6. Testimonial Showcase Carousel (Ultra-Smooth Animation & Interactions)
   ========================================================================== */
function initTestimonialSlider() {
    const track = document.getElementById('testiTrack');
    const prevBtn = document.getElementById('testiPrevBtn');
    const nextBtn = document.getElementById('testiNextBtn');
    const progressTrack = document.querySelector('.testi-progress-track');
    const progressThumb = document.getElementById('testiProgressIndicator');
    const cards = document.querySelectorAll('.testi-card-wrapper');

    if (!track || cards.length === 0) return;

    // Auto-Reset on Inactivity: After 5s of no interaction, flip cards back to image face
    let autoResetTimer = null;
    const AUTO_RESET_DELAY = 5000;

    function resetFlippedCards() {
        cards.forEach(card => {
            card.classList.remove('is-flipped');
        });
    }

    function scheduleAutoReset() {
        if (autoResetTimer) clearTimeout(autoResetTimer);
        const hasFlipped = Array.from(cards).some(c => c.classList.contains('is-flipped'));
        if (hasFlipped) {
            autoResetTimer = setTimeout(() => {
                resetFlippedCards();
            }, AUTO_RESET_DELAY);
        }
    }

    // 1. High-Performance Smooth Scroll Interpolation via requestAnimationFrame
    let isAnimating = false;

    function smoothScrollTrackTo(targetScrollLeft, duration = 550) {
        if (isAnimating) return;
        isAnimating = true;
        const startScrollLeft = track.scrollLeft;
        const distance = targetScrollLeft - startScrollLeft;
        const startTime = performance.now();

        function easeOutQuart(t) {
            return 1 - (--t) * t * t * t;
        }

        function step(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const ease = easeOutQuart(progress);

            track.scrollLeft = startScrollLeft + (distance * ease);
            updateProgress();

            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                track.scrollLeft = targetScrollLeft;
                updateProgress();
                isAnimating = false;
            }
        }

        requestAnimationFrame(step);
    }

    // 2. Click Ripple Effect on Navigation Buttons
    function createRipple(button, event) {
        const rect = button.getBoundingClientRect();
        const ripple = document.createElement('span');
        ripple.className = 'ripple-wave';
        const size = Math.max(rect.width, rect.height);
        ripple.style.width = ripple.style.height = `${size}px`;
        ripple.style.left = `${(event.clientX || rect.left + rect.width / 2) - rect.left - size / 2}px`;
        ripple.style.top = `${(event.clientY || rect.top + rect.height / 2) - rect.top - size / 2}px`;
        button.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
    }

    // 3. Navigation Arrow Handlers
    const getCardStep = () => {
        const firstCard = cards[0];
        const cardWidth = firstCard ? firstCard.offsetWidth : 285;
        return cardWidth + 26;
    };

    if (prevBtn) {
        prevBtn.addEventListener('click', (e) => {
            createRipple(prevBtn, e);
            const maxScroll = track.scrollWidth - track.clientWidth;
            const target = Math.max(0, track.scrollLeft - getCardStep());
            smoothScrollTrackTo(target, 550);
            scheduleAutoReset();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', (e) => {
            createRipple(nextBtn, e);
            const maxScroll = track.scrollWidth - track.clientWidth;
            const target = Math.min(maxScroll, track.scrollLeft + getCardStep());
            smoothScrollTrackTo(target, 550);
            scheduleAutoReset();
        });
    }

    // 4. Instant Smooth 3D Card Flip on Click with Activity Tracking
    cards.forEach(card => {
        card.addEventListener('click', (e) => {
            if (card.dataset.preventFlip === 'true') {
                card.dataset.preventFlip = 'false';
                return;
            }
            card.classList.toggle('is-flipped');
            scheduleAutoReset();
        });

        card.addEventListener('mousemove', () => {
            scheduleAutoReset();
        });
    });

    // 5. Dynamic Scroll Progress Bar Sync
    function updateProgress() {
        if (!progressThumb) return;
        const maxScroll = track.scrollWidth - track.clientWidth;
        if (maxScroll <= 0) {
            progressThumb.style.transform = 'translateX(0)';
            return;
        }
        const scrollRatio = Math.min(1, Math.max(0, track.scrollLeft / maxScroll));
        const maxTranslate = 185;
        progressThumb.style.transform = `translateX(${scrollRatio * maxTranslate}%)`;
    }

    track.addEventListener('scroll', updateProgress, { passive: true });
    window.addEventListener('resize', updateProgress, { passive: true });
    setTimeout(updateProgress, 100);

    // 6. Click on Progress Track to Seek Position
    if (progressTrack) {
        progressTrack.addEventListener('click', (e) => {
            const rect = progressTrack.getBoundingClientRect();
            const clickRatio = (e.clientX - rect.left) / rect.width;
            const maxScroll = track.scrollWidth - track.clientWidth;
            smoothScrollTrackTo(clickRatio * maxScroll, 550);
            scheduleAutoReset();
        });
    }

    // 7. Touch / Mouse Drag to Scroll
    let isDown = false;
    let startX = 0;
    let scrollStart = 0;
    let dragMove = 0;

    track.addEventListener('mousedown', (e) => {
        isDown = true;
        dragMove = 0;
        track.classList.add('is-dragging');
        startX = e.pageX - track.offsetLeft;
        scrollStart = track.scrollLeft;
        scheduleAutoReset();
    });

    window.addEventListener('mouseup', () => {
        if (isDown) {
            isDown = false;
            track.classList.remove('is-dragging');
            scheduleAutoReset();
        }
    });

    track.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        const x = e.pageX - track.offsetLeft;
        const walk = (x - startX) * 1.4;
        dragMove += Math.abs(walk);
        if (dragMove > 10) {
            cards.forEach(c => c.dataset.preventFlip = 'true');
        }
        track.scrollLeft = scrollStart - walk;
        updateProgress();
        scheduleAutoReset();
    });

    // 8. Keyboard Navigation Support
    track.setAttribute('tabindex', '0');
    track.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            if (prevBtn) prevBtn.click();
        } else if (e.key === 'ArrowRight') {
            e.preventDefault();
            if (nextBtn) nextBtn.click();
        }
        scheduleAutoReset();
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
            language: data.language || 'Not Provided',
            segment: data.role || 'Not Provided',
            motivation: data.motivation || 'Not Provided',
            background: data.background || 'Not Provided',
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
                "previousRole": "Ex-HR Generalist",
                "currentRole": "Oracle Fusion HCM Consultant",
                "company": "Accenture",
                "package": "9.5 LPA",
                "avatarText": "AS",
                "content": "Sumesh sir's explanation of Fusion Global Payroll elements, calculations, and Fast Formulas was excellent. Coming from a non-technical HR background, the hands-on labs helped me learn the cloud configuration easily. Placed at Accenture!"
        },
        {
                "name": "Sai Kiran Reddy",
                "previousRole": "Ex-EBS HRMS Developer",
                "currentRole": "Lead Oracle Fusion HCM Consultant",
                "company": "Cognizant",
                "package": "18.5 LPA",
                "avatarText": "SK",
                "content": "Perfect training for EBS developers switching to Cloud. The modules on HCM Data Loader (HDL), HCM Spreadsheet Data Loader (HSDL), and HCM Extracts are covered in-depth. Security consoles and sandbox customizations were very helpful."
        },
        {
                "name": "Priyanka N.",
                "previousRole": "MBA HR Fresher",
                "currentRole": "Associate Fusion HCM Analyst",
                "company": "Infosys",
                "package": "6.2 LPA",
                "avatarText": "PN",
                "content": "As an MBA fresher, I wanted to build a career in Oracle Cloud. The Fusion HCM training at Tech Leads IT gave me a huge competitive edge. The mock interviews and certification preparation prep helped me clear the Infosys drive!"
        },
        {
                "name": "Rohan Deshmukh",
                "previousRole": "Ex-HR Administrator",
                "currentRole": "Fusion Core HR Analyst",
                "company": "Tech Mahindra",
                "package": "8.8 LPA",
                "avatarText": "RD",
                "content": "Transitioning from general admin to Oracle Cloud HCM consulting was the best career move. The sessions on Enterprise Structure, security profiles, and Approval Management (BPM) are highly detailed with live implementations."
        },
        {
                "name": "Karthik Venkat",
                "previousRole": "Ex-PL/SQL Developer",
                "currentRole": "Fusion HCM Technical Consultant",
                "company": "NTT Data",
                "package": "14.2 LPA",
                "avatarText": "KV",
                "content": "Excellent lab access and real-time business scenarios. Designing custom BI Publisher reports, OTBI dashboards, and outbound HCM Extracts was covered comprehensively. Cleared my Oracle Cloud Certification on first try!"
        },
        {
                "name": "Meera Nair",
                "previousRole": "Ex-HR Recruiter",
                "currentRole": "Senior Fusion HCM Specialist",
                "company": "L&T Technology Services",
                "package": "21.0 LPA",
                "avatarText": "MN",
                "content": "This Fusion HCM course is highly practical and focused on corporate client implementation guidelines. Sumesh Sir is incredibly knowledgeable in payroll balance adjustments and absence plan configurations."
        },
        {
                "name": "Vikram Adithya",
                "previousRole": "Ex-PeopleSoft HRMS Consultant",
                "currentRole": "Oracle Cloud HCM Administrator",
                "company": "Mphasis",
                "package": "15.5 LPA",
                "avatarText": "VA",
                "content": "Moving from PeopleSoft to Oracle Fusion HCM was smooth. The course covers security console, job/data roles, and data migration tools (HDL) very thoroughly. The server availability is highly reliable."
        },
        {
                "name": "Sneha Reddy",
                "previousRole": "MBA HR Graduate",
                "currentRole": "Fusion Compensation Analyst",
                "company": "Genpact",
                "package": "7.0 LPA",
                "avatarText": "SR",
                "content": "The Workforce Compensation and Individual Benefits modules are explained with great clarity. Setting up salary bases, plan cycles, and budget pools was taught step-by-step. The mock interview questions were extremely helpful."
        },
        {
                "name": "Abhishek Rao",
                "previousRole": "Ex-Technical Support",
                "currentRole": "Fusion Cloud Support Engineer",
                "company": "MouriTech",
                "package": "8.0 LPA",
                "avatarText": "AR",
                "content": "This course completely changed my career path. The training covers all modules including Absence Management and Workforce Directory. The real-time support tickets solved during the lab sessions prepared me for corporate work."
        },
        {
                "name": "Divya Teja",
                "previousRole": "Ex-HR Recruiter",
                "currentRole": "Fusion Talent Management Consultant",
                "company": "SplashBI",
                "package": "10.5 LPA",
                "avatarText": "DT",
                "content": "The Talent Management modules like Goal Management, Performance Templates, and Talent Review configurations are taught with practical live environments. I got placed at SplashBI shortly after completing the course."
        },
        {
                "name": "Harish Kumar",
                "previousRole": "Fresher (B.Tech)",
                "currentRole": "Oracle Fusion HCM Trainee",
                "company": "CES",
                "package": "6.0 LPA",
                "avatarText": "HK",
                "content": "Sumesh Sir is an amazing trainer with 23+ years of experience. He starts every Fusion HCM module from the absolute basics, making it easy to learn the security architecture and payroll configurations. Placed at CES."
        },
        {
                "name": "Tejaswi G.",
                "previousRole": "Ex-HR Operations",
                "currentRole": "Oracle Cloud HCM Consultant",
                "company": "Accenture",
                "package": "11.2 LPA",
                "avatarText": "TG",
                "content": "Purely hands-on training. I spent most of my time configuring security roles, departments, and payroll elements on the live Oracle instance. The mock interviews helped me build the confidence to clear Accenture."
        },
        {
                "name": "Manish Sharma",
                "previousRole": "Ex-ERP Consultant",
                "currentRole": "Senior Fusion HCM Lead",
                "company": "Infosys",
                "package": "19.0 LPA",
                "avatarText": "MS",
                "content": "An outstanding program for experienced professionals. The extraction tools, BIP integrations, HCM extracts, and technical loaders are covered in great detail, saving weeks of self-study."
        },
        {
                "name": "Nisha Patnaik",
                "previousRole": "MBA HR Graduate",
                "currentRole": "Fusion Payroll Consultant",
                "company": "Tech Mahindra",
                "package": "6.8 LPA",
                "avatarText": "NP",
                "content": "The payroll module training is highly structured. Setting up consolidation groups, run types, and element entries was taught using realistic business scenarios. Landed at Tech Mahindra with a solid salary."
        },
        {
                "name": "Rahul Varma",
                "previousRole": "Ex-SQL Developer",
                "currentRole": "Fusion HCM Technical Consultant",
                "company": "Cognizant",
                "package": "12.5 LPA",
                "avatarText": "RV",
                "content": "Transitioning from SQL development to Fusion HCM technical consulting was a breeze. The market demand for integration specialists (REST/SOAP web services and HCM extracts) is massive, and this course covers them fully."
        },
        {
                "name": "Deepika Rao",
                "previousRole": "Ex-HR Specialist",
                "currentRole": "Fusion Core HR Analyst",
                "company": "NTT Data",
                "package": "9.0 LPA",
                "avatarText": "DR",
                "content": "Excellent course structure. We practiced setting up grades, positions, jobs, and organizational structures. The trainers and lab coordinators are extremely helpful with resolving configuration issues."
        },
        {
                "name": "Srinivas Rao",
                "previousRole": "Ex-Oracle EBS Technical",
                "currentRole": "Lead Cloud HCM Integrator",
                "company": "L&T Technology Services",
                "package": "16.8 LPA",
                "avatarText": "SR",
                "content": "I joined to learn Cloud technical architecture. The lessons on BI Publisher layout templates and HCM Extracts parameter mapping are very thorough. It makes transitioning from EBS very smooth."
        },
        {
                "name": "Kavitha M.",
                "previousRole": "MBA HR Fresher",
                "currentRole": "Trainee Cloud HCM Consultant",
                "company": "Genpact",
                "package": "6.4 LPA",
                "avatarText": "KM",
                "content": "The lab facilities and server availability are great. The placement coordinator arranged multiple interviews, and I cleared the technical round at Genpact. Highly recommended for beginners."
        },
        {
                "name": "Sandeep Verma",
                "previousRole": "Ex-Operations Associate",
                "currentRole": "Fusion HCM Support Consultant",
                "company": "Mphasis",
                "package": "8.5 LPA",
                "avatarText": "SV",
                "content": "Sumesh sir connects every concept to a real client implementation case study. It helped me understand system parameters and configurations thoroughly. Landed a support role at Mphasis."
        },
        {
                "name": "Pooja Hegde",
                "previousRole": "Ex-HR Assistant",
                "currentRole": "Fusion Core HR Consultant",
                "company": "MouriTech",
                "package": "7.8 LPA",
                "avatarText": "PH",
                "content": "Excellent training. We configured legal structures, positions, business units, and security rules. The lab scenarios match exactly what I am doing now on my client project at MouriTech."
        },
        {
                "name": "Varun Reddy",
                "previousRole": "Ex-ERP Support Analyst",
                "currentRole": "Senior Fusion HCM Consultant",
                "company": "SplashBI",
                "package": "13.0 LPA",
                "avatarText": "VR",
                "content": "The training covers OTBI analysis, dashboard filters, and HCM Extracts extensively. The practical exercises are designed exceptionally well. Placed at SplashBI with a solid package."
        },
        {
                "name": "Ritu Sen",
                "previousRole": "MBA HR Fresher",
                "currentRole": "Trainee HCM Analyst",
                "company": "CES",
                "package": "6.1 LPA",
                "avatarText": "RS",
                "content": "The mock tests and resume building support are the best highlights. The trainer helped me refine my profile to focus on my Core HR and Goal Management skills, which helped me clear CES."
        },
        {
                "name": "Amit Patel",
                "previousRole": "Ex-EBS Consultant",
                "currentRole": "Lead Oracle Cloud HCM Consultant",
                "company": "Accenture",
                "package": "23.5 LPA",
                "avatarText": "AP",
                "content": "The course content is highly aligned with the Oracle Certification Syllabus. Practicing HCM Data Loader and Global Payroll setups prepared me to clear both implementation professional exams."
        },
        {
                "name": "Pranitha K.",
                "previousRole": "Ex-HR Recruiter",
                "currentRole": "Oracle Recruiting Cloud Specialist",
                "company": "Infosys",
                "package": "10.0 LPA",
                "avatarText": "PK",
                "content": "The sessions on Oracle Recruiting Cloud (ORC) configuration, candidate selection processes, and career site design are excellent. It was the perfect course to transition into Cloud HCM consulting."
        },
        {
                "name": "Nikhil D.",
                "previousRole": "Fresher (B.Tech)",
                "currentRole": "Fusion HCM Technical Trainee",
                "company": "Tech Mahindra",
                "package": "6.6 LPA",
                "avatarText": "ND",
                "content": "Great lab support. The lab coordinators are always online to resolve any instance configuration errors. The step-by-step documentation was very handy during my preparation."
        },
        {
                "name": "Radhika J.",
                "previousRole": "Ex-HR Generalist",
                "currentRole": "Fusion HCM Functional Consultant",
                "company": "Cognizant",
                "package": "12.0 LPA",
                "avatarText": "RJ",
                "content": "Sumesh sir's teaching methodology is top-notch. He starts with enterprise structures and moves to complex topics like Payroll element setups and Absence accrual rules. Placed at Cognizant!"
        },
        {
                "name": "Vijay Bhaskar",
                "previousRole": "Ex-EBS Apps DBA",
                "currentRole": "Fusion HCM Technical Architect",
                "company": "NTT Data",
                "package": "20.5 LPA",
                "avatarText": "VB",
                "content": "Excellent program for DBAs transitioning to Cloud. The training covers BI Publisher dashboard structures, HDL interfaces, security setups, and sandbox tool tools. Secured a lead role at NTT Data."
        },
        {
                "name": "Swathi Latha",
                "previousRole": "MBA HR Fresher",
                "currentRole": "Trainee HCM Consultant",
                "company": "L&T Technology Services",
                "package": "6.7 LPA",
                "avatarText": "SL",
                "content": "Highly supportive placement team. They aligned interviews and helped with profile building. The training covers Core HR, Goal, and Performance modules, which are essential for freshers."
        },
        {
                "name": "Gopal Krishna",
                "previousRole": "Ex-Support Associate",
                "currentRole": "Fusion Core HR Analyst",
                "company": "Genpact",
                "package": "8.7 LPA",
                "avatarText": "GK",
                "content": "Excellent course. The exercises on custom workflow configurations in BPM Worklist were highly informative. Solving configuration errors independently built real confidence."
        },
        {
                "name": "Kiranmai P.",
                "previousRole": "Ex-HR Coordinator",
                "currentRole": "Oracle Cloud HCM Consultant",
                "company": "Mphasis",
                "package": "11.5 LPA",
                "avatarText": "KP",
                "content": "The sessions on descriptive flexfields (DFF), extensible flexfields (EFF), and lookups setups are very thorough. It helped me crack the Mphasis technical round in the first attempt."
        },
        {
                "name": "Manoj Kumar",
                "previousRole": "B.Sc Computer Science Fresher",
                "currentRole": "Trainee Cloud Support Associate",
                "company": "MouriTech",
                "package": "6.0 LPA",
                "avatarText": "MK",
                "content": "The cloud server lab access is available 24/7, which allowed me to practice configurations outside class hours. Sumesh Sir's guidance helped me get placed at MouriTech."
        },
        {
                "name": "Archana Singh",
                "previousRole": "Ex-HR Specialist",
                "currentRole": "Fusion Talent Management Analyst",
                "company": "SplashBI",
                "package": "9.8 LPA",
                "avatarText": "AS",
                "content": "Sumesh sir's teaching is exceptional. The sessions on sandbox setups, page composer modifications, and goal configurations are highly practical. Got placed at SplashBI with a solid hike."
        },
        {
                "name": "Raghava Rao",
                "previousRole": "Ex-EBS Developer",
                "currentRole": "Senior Fusion HCM Developer",
                "company": "CES",
                "package": "16.0 LPA",
                "avatarText": "RR",
                "content": "Excellent training on HCM Extracts and BI Publisher reports. The scenarios are modeled on real client business requirements, helping me clear my technical architect interview at CES."
        },
        {
                "name": "Shravya Reddy",
                "previousRole": "MBA HR Fresher",
                "currentRole": "Oracle Cloud HCM Consultant",
                "company": "Accenture",
                "package": "6.9 LPA",
                "avatarText": "SR",
                "content": "The training program is very structured and clean. The payroll configuration steps, Fast Formulas, and element entries are explained thoroughly. Placed at Accenture with a great package."
        },
        {
                "name": "Balaji Naidu",
                "previousRole": "Ex-PeopleSoft Tech Lead",
                "currentRole": "Fusion HCM Technical Consultant",
                "company": "Infosys",
                "package": "19.5 LPA",
                "avatarText": "BN",
                "content": "The technical components of Oracle Fusion HCM, including HCM Data Loader (HDL) and BI Publisher dashboard integrations, are covered very deeply. It was the perfect course to transition to the cloud."
        },
        {
                "name": "Neeraja G.",
                "previousRole": "Ex-Recruitment Lead",
                "currentRole": "Fusion Talent Consultant",
                "company": "Tech Mahindra",
                "package": "12.8 LPA",
                "avatarText": "NG",
                "content": "Learning Oracle Recruiting Cloud (ORC) and Core HR modules here helped me shift from a recruiting lead to a functional HCM consultant. Detailed study materials and mock interviews."
        },
        {
                "name": "Kishore Babu",
                "previousRole": "MBA HR Fresher",
                "currentRole": "Fusion HCM Associate",
                "company": "Cognizant",
                "package": "6.3 LPA",
                "avatarText": "KB",
                "content": "The mock interview preparation checklist was highly effective. The placement team scheduled my interview with Cognizant and guided me through all rounds. Got a 6.3 LPA start!"
        },
        {
                "name": "Sowmya K.",
                "previousRole": "Ex-HR Generalist",
                "currentRole": "Oracle Fusion HCM Consultant",
                "company": "NTT Data",
                "package": "8.9 LPA",
                "avatarText": "SK",
                "content": "Sumesh sir's teaching is highly practical. The server access allowed me to practice all enterprise structure and legal entity configurations. Placed at NTT Data!"
        },
        {
                "name": "Pradeep Chawla",
                "previousRole": "Ex-EBS Architect",
                "currentRole": "Principal Fusion HCM Architect",
                "company": "L&T Technology Services",
                "package": "24.0 LPA",
                "avatarText": "PC",
                "content": "The PaaS extensions, REST API integrations, and spreadsheet loaders (HSDL) are covered exceptionally well. Highly recommended for senior developers upgrading their technical profiles."
        },
        {
                "name": "Divya Reddy",
                "previousRole": "MBA HR Fresher",
                "currentRole": "Fusion Payroll Consultant",
                "company": "Genpact",
                "package": "6.8 LPA",
                "avatarText": "DR",
                "content": "Deep dive training in Global Payroll, including payroll definitions, consolidation groups, and costings. The case studies were very helpful in clearing the technical interview at Genpact."
        },
        {
                "name": "Sudhir Rao",
                "previousRole": "Ex-SQL Developer",
                "currentRole": "Fusion HCM Integrator",
                "company": "Mphasis",
                "package": "11.0 LPA",
                "avatarText": "SR",
                "content": "Excellent course. The extracts and BI Publisher reporting layouts sessions are very thorough, which helped me switch to a Cloud HCM technical consultant role at Mphasis."
        },
        {
                "name": "Anupama Nair",
                "previousRole": "Ex-HR Executive",
                "currentRole": "Fusion Absence Consultant",
                "company": "MouriTech",
                "package": "9.2 LPA",
                "avatarText": "AN",
                "content": "The Absence Management module configurations, accrual rules, and fast formula inclusions are explained very clearly. Very supportive coordinators and lab helpers."
        },
        {
                "name": "Gautam Sen",
                "previousRole": "Fresher (B.Tech)",
                "currentRole": "Oracle Cloud HCM Specialist",
                "company": "SplashBI",
                "package": "6.2 LPA",
                "avatarText": "GS",
                "content": "The practical lab tasks helped me learn the system configurations easily. The mock interviews helped me build real-time confidence. Placed at SplashBI with a solid hike."
        },
        {
                "name": "Pallavi G.",
                "previousRole": "Ex-HR Generalist",
                "currentRole": "Fusion Core HR Analyst",
                "company": "CES",
                "package": "10.8 LPA",
                "avatarText": "PG",
                "content": "The weekend sessions are very convenient. The trainer answers every configuration question patiently. Study guides, resumes templates, and sample mock tests were very useful."
        },
        {
                "name": "Naveen Prasad",
                "previousRole": "Ex-PL/SQL Developer",
                "currentRole": "Lead Fusion HCM Technical Lead",
                "company": "Accenture",
                "package": "22.5 LPA",
                "avatarText": "NP",
                "content": "Transitioning from traditional EBS PL/SQL coding to Cloud BI Publisher reports and HCM Data Loader mappings was seamless. The course covers cloud integration setups deeply."
        },
        {
                "name": "Tejaswini K.",
                "previousRole": "MBA HR Fresher",
                "currentRole": "Trainee HCM Analyst",
                "company": "Infosys",
                "package": "6.5 LPA",
                "avatarText": "TK",
                "content": "Excellent training. The curriculum covers Core HR, goals configuration, and payroll calculations. The placement team helped me schedule my Infosys interview. Got a 6.5 LPA offer."
        },
        {
                "name": "Rajesh Kannan",
                "previousRole": "Ex-Support Executive",
                "currentRole": "Fusion HCM Support Analyst",
                "company": "Tech Mahindra",
                "package": "8.2 LPA",
                "avatarText": "RK",
                "content": "The lab facilities and coaching quality are outstanding. Sumesh sir makes complex Oracle HCM setups simple to understand. Got placed at Tech Mahindra with a solid salary."
        },
        {
                "name": "Manasa Rao",
                "previousRole": "Ex-Recruiting Lead",
                "currentRole": "Fusion Talent Consultant",
                "company": "Cognizant",
                "package": "9.4 LPA",
                "avatarText": "MR",
                "content": "The sessions on Goal and Performance management are highly detailed. The mock interviews were similar to the actual Cognizant rounds. Very grateful for the support."
        },
        {
                "name": "Yashwant G.",
                "previousRole": "Ex-Database Administrator",
                "currentRole": "Fusion HCM Tech Specialist",
                "company": "NTT Data",
                "package": "17.5 LPA",
                "avatarText": "YG",
                "content": "The configuration steps taught in the class match real-world project scenarios. The training covers REST API integrations, BI Publisher layouts, and spreadsheet loaders (HSDL) in detail."
        },
        {
                "name": "Shruti Gupta",
                "previousRole": "MBA Graduate",
                "currentRole": "Fusion Compensation Analyst",
                "company": "L&T Technology Services",
                "package": "6.6 LPA",
                "avatarText": "SG",
                "content": "The Workforce Compensation and Individual Benefits configurations are covered deeply. Configuring salary structures and budget allocations was taught step-by-step. Placed at L&T!"
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
    let firstVisitTime = localStorage.getItem('eduVisitorFirstVisit');
    if (!firstVisitTime) {
        firstVisitTime = Date.now().toString();
        localStorage.setItem('eduVisitorFirstVisit', firstVisitTime);
        if (!window.bypassPopupLimits) {
            // On a visitor's first visit: Store the visit timestamp and do NOT display the popup
            return;
        }
    }

    // 2. Display Rules Check
    // Show only on a future visit in a new session (not page refreshes)
    if (!isNewSession && !window.bypassPopupLimits) {
        console.log('[Popups] Welcome back popup bypassed: not a new session.');
        return;
    }

    // Frequency cap: Do not show more than once every 7 days (604800000 milliseconds)
    const sevenDaysInMs = 7 * 24 * 60 * 60 * 1000;
    const lastShownTime = localStorage.getItem('eduVisitorLastPopupShown');
    if (!window.bypassPopupLimits && lastShownTime && (Date.now() - Number(lastShownTime) < sevenDaysInMs)) {
        console.log('[Popups] Welcome back popup bypassed: frequency cap active.');
        return;
    }

    // Do not show again after the visitor submits any lead form
    if (!window.bypassPopupLimits && localStorage.getItem('hcm_lead_submitted') === 'true') {
        console.log('[Popups] Welcome back popup bypassed: lead already submitted.');
        return;
    }

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
        if (!window.bypassPopupLimits && localStorage.getItem('hcm_lead_submitted') === 'true') {
            console.log('[Popups] Exit intent popup bypassed: lead already submitted.');
            return;
        }
        
        // Don't show if another modal is currently open to avoid stacking
        const activeModal = document.querySelector('.modal-overlay.open');
        if (activeModal && !window.bypassPopupLimits) return;

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


/* ==========================================================================
   14. Popup Test Preview Widget Panel
   ========================================================================== */
function initPopupDebugger() {
    if (document.getElementById('popup-debugger')) return;

    const div = document.createElement('div');
    div.id = 'popup-debugger';
    div.style.cssText = `
        position: fixed; 
        bottom: 20px; 
        left: 20px; 
        z-index: 999999; 
        background: rgba(15, 23, 42, 0.95); 
        border: 1px solid rgba(255, 255, 255, 0.15); 
        padding: 15px; 
        border-radius: 12px; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.5); 
        backdrop-filter: blur(10px); 
        color: #fff; 
        font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
        width: 280px; 
        text-align: left;
    `;
    div.innerHTML = `
        <div style="font-weight: 700; font-size: 13px; margin-bottom: 10px; color: #38bdf8; display: flex; justify-content: space-between; align-items: center;">
            <span>POPUP PREVIEW PANEL</span>
            <button onclick="document.getElementById('popup-debugger').remove()" style="background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 14px;">✕</button>
        </div>
        <div style="font-size: 11px; color: #94a3b8; margin-bottom: 12px; line-height: 1.4;">
            Testing Mode is Active. Frequency caps, visitor checks, and submission limits are bypassed.
        </div>
        <div style="display: flex; flex-direction: column; gap: 8px;">
            <button onclick="window.openModal(document.getElementById('leadModal'))" style="background: #1e293b; border: 1px solid #334155; color: #fff; padding: 8px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; text-align: left; transition: background 0.2s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">Trigger 45% Scroll Popup (Lead Modal)</button>
            <button onclick="window.openModal(document.getElementById('advisorModal'))" style="background: #1e293b; border: 1px solid #334155; color: #fff; padding: 8px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; text-align: left; transition: background 0.2s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">Trigger 75% Scroll Popup (Advisor Modal)</button>
            <button onclick="window.openModal(document.getElementById('exitIntentModal'))" style="background: #1e293b; border: 1px solid #334155; color: #fff; padding: 8px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; text-align: left; transition: background 0.2s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">Trigger Exit Intent Popup</button>
            <button onclick="window.openModal(document.getElementById('returningVisitorModal'))" style="background: #1e293b; border: 1px solid #334155; color: #fff; padding: 8px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; text-align: left; transition: background 0.2s;" onmouseover="this.style.background='#334155'" onmouseout="this.style.background='#1e293b'">Trigger Welcome Back Popup</button>
            <button onclick="localStorage.clear(); sessionStorage.clear(); location.reload();" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border: none; color: #fff; padding: 9px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; font-weight: 600; text-align: center; margin-top: 5px; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2);">Reset All Storage & Reload</button>
        </div>
    `;
    document.body.appendChild(div);
}
