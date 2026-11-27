<?php if (!defined('ABSPATH')) { exit; } ?>
<div class="lp-sync-dialog" data-lp-sync-dialog role="dialog" aria-modal="true" aria-labelledby="lp-sync-title" tabindex="-1" hidden>
    <div class="lp-sync-card">
        <div class="lp-sync-stage" aria-hidden="true">
            <svg class="lp-sync-rocket" viewBox="0 0 240 220" fill="none">
                <defs>
                    <linearGradient id="lp-rocket-hull" x1="98" y1="65" x2="143" y2="69" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#73818c"/><stop offset=".22" stop-color="#e8edf0"/><stop offset=".43" stop-color="#fff"/><stop offset=".68" stop-color="#d2d9df"/><stop offset="1" stop-color="#687681"/>
                    </linearGradient>
                    <linearGradient id="lp-rocket-nose" x1="107" y1="25" x2="133" y2="65" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#e9eef0"/><stop offset=".48" stop-color="#97a5af"/><stop offset="1" stop-color="#384750"/>
                    </linearGradient>
                    <linearGradient id="lp-rocket-fin" x1="84" y1="106" x2="110" y2="145" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#85929b"/><stop offset=".32" stop-color="#3d4c56"/><stop offset="1" stop-color="#18232c"/>
                    </linearGradient>
                    <linearGradient id="lp-rocket-glass" x1="111" y1="78" x2="132" y2="99" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#6798b5"/><stop offset=".45" stop-color="#1c455e"/><stop offset="1" stop-color="#091b29"/>
                    </linearGradient>
                    <linearGradient id="lp-rocket-plume" x1="120" y1="148" x2="120" y2="209" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#effbff"/><stop offset=".22" stop-color="#9cddf7"/><stop offset=".55" stop-color="#5d9cdb" stop-opacity=".75"/><stop offset="1" stop-color="#8bc0e3" stop-opacity="0"/>
                    </linearGradient>
                    <radialGradient id="lp-rocket-engine"><stop stop-color="#fff"/><stop offset=".5" stop-color="#b2e6fa"/><stop offset="1" stop-color="#5ea7d9" stop-opacity="0"/></radialGradient>
                </defs>
                <ellipse class="lp-rocket-shadow" cx="120" cy="191" rx="43" ry="6"/>
                <g class="lp-rocket-smoke">
                    <ellipse cx="105" cy="184" rx="17" ry="5"/><ellipse cx="136" cy="187" rx="20" ry="6"/><ellipse cx="119" cy="196" rx="30" ry="6"/>
                </g>
                <g class="lp-rocket-craft">
                    <g class="lp-rocket-thrust">
                        <path d="M112 151Q106 175 120 212Q135 174 128 151Z" fill="url(#lp-rocket-plume)"/>
                        <path d="M116 151Q112 171 120 192Q127 169 124 151Z" fill="#eefbff" opacity=".88"/>
                        <ellipse cx="120" cy="154" rx="16" ry="9" fill="url(#lp-rocket-engine)"/>
                    </g>
                    <path d="M104 101L85 126L83 148L106 135Z" fill="url(#lp-rocket-fin)" stroke="#8e9ca5" stroke-width=".7"/>
                    <path d="M136 101L155 126L157 148L134 135Z" fill="url(#lp-rocket-fin)" stroke="#70818c" stroke-width=".7"/>
                    <path d="M120 21C106 36 100 60 100 101L105 139Q120 147 135 139L140 101C140 60 134 36 120 21Z" fill="url(#lp-rocket-hull)" stroke="#74848f" stroke-width=".8"/>
                    <path d="M120 21C113 29 107 42 104 56Q120 62 136 56C133 42 127 29 120 21Z" fill="url(#lp-rocket-nose)"/>
                    <path d="M104 57Q120 63 136 57M100.5 111Q120 116 139.5 111M103 131Q120 138 137 131" stroke="#6e7f8a" stroke-width=".75" opacity=".65"/>
                    <path d="M108 66L108 108" stroke="#fff" stroke-width="1.6" opacity=".65"/>
                    <circle cx="120" cy="87" r="13" fill="#75828c" stroke="#e5ecef" stroke-width="1.5"/>
                    <circle cx="120" cy="87" r="10" fill="url(#lp-rocket-glass)" stroke="#394d5a" stroke-width="1"/>
                    <path d="M113 83Q116 78 122 80L116 88Z" fill="#e5f7ff" opacity=".48"/>
                    <path d="M120 118L116 141L120 152L124 141Z" fill="#596b77" stroke="#b0bec6" stroke-width=".6"/>
                    <path d="M109 141L112 150Q120 153 128 150L131 141Q120 146 109 141Z" fill="#253844" stroke="#91a3af" stroke-width=".7"/>
                    <path d="M114 145L115 150M119 146V151M124 145L123 150" stroke="#728795" stroke-width=".8"/>
                </g>
            </svg>
            <svg class="lp-sync-complete" viewBox="0 0 100 100" fill="none"><circle cx="50" cy="50" r="39"/><path d="M31 50L44 63L70 36"/></svg>
            <svg class="lp-sync-alert" viewBox="0 0 100 100" fill="none"><circle cx="50" cy="50" r="39"/><path d="M50 29V54M50 67V68"/></svg>
            <span class="lp-sync-orbit"></span>
        </div>
        <p class="lp-sync-eyebrow">CONVERSION BRIDGE / TELECRM</p>
        <div aria-live="polite" aria-atomic="true"><h2 id="lp-sync-title" data-lp-sync-title>Syncing with TeleCRM</h2><p class="lp-sync-description" data-lp-sync-description>Saving your review and updating the lead.</p></div>
        <div class="lp-sync-progress" aria-hidden="true"><span></span></div>
        <button type="button" class="button button-primary" data-lp-sync-close hidden>Done</button>
    </div>
</div>
