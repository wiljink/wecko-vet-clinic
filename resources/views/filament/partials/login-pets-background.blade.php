{{-- Decorative pet illustrations + paw-print texture behind the login card. Purely
     decorative (aria-hidden), fixed-positioned so it sits behind the card regardless
     of where in the page this partial is injected, and hidden on small screens so it
     never crowds the sign-in form. --}}
<style>
    .fi-simple-layout {
        position: relative;
        background: linear-gradient(180deg, #f0fdfa 0%, #ccfbf1 45%, #99f6e4 100%);
    }

    :is(.dark) .fi-simple-layout {
        background: linear-gradient(180deg, #042f2e 0%, #0a3d3a 45%, #0f4c47 100%);
    }

    .fi-simple-main {
        position: relative;
        z-index: 1;
    }

    .wecko-login-decor {
        position: fixed;
        inset: 0;
        z-index: 0;
        pointer-events: none;
    }

    .wecko-login-pet {
        position: fixed;
        bottom: -12px;
        z-index: 0;
        pointer-events: none;
        filter: drop-shadow(0 14px 18px rgb(15 118 110 / 0.20));
    }

    .wecko-login-pet--dog {
        left: clamp(-24px, 3vw, 48px);
        width: clamp(150px, 15vw, 230px);
    }

    .wecko-login-pet--cat {
        right: clamp(-16px, 3vw, 64px);
        width: clamp(130px, 13vw, 200px);
    }

    @media (max-width: 900px) {
        .wecko-login-pet {
            display: none;
        }
    }
</style>

<svg class="wecko-login-decor" aria-hidden="true" focusable="false">
    <defs>
        <pattern id="wecko-paw-pattern" width="120" height="120" patternUnits="userSpaceOnUse" patternTransform="rotate(16)">
            <g class="dark:opacity-40" fill="#0d9488" opacity="0.12">
                <ellipse cx="60" cy="78" rx="15" ry="12" />
                <ellipse cx="40" cy="55" rx="7" ry="9" />
                <ellipse cx="53" cy="43" rx="7" ry="9" />
                <ellipse cx="69" cy="43" rx="7" ry="9" />
                <ellipse cx="82" cy="55" rx="7" ry="9" />
            </g>
        </pattern>
    </defs>
    <rect width="100%" height="100%" fill="url(#wecko-paw-pattern)" />
</svg>

{{-- Sitting dog --}}
<svg class="wecko-login-pet wecko-login-pet--dog" viewBox="0 0 200 220" aria-hidden="true" focusable="false">
    <ellipse cx="55" cy="112" rx="21" ry="39" transform="rotate(-18 55 112)" fill="#9a5f26" />
    <ellipse cx="145" cy="112" rx="21" ry="39" transform="rotate(18 145 112)" fill="#9a5f26" />
    <path d="M32,220 Q30,138 100,138 Q170,138 168,220 Z" fill="#c9853b" />
    <path d="M50,141 Q100,157 150,141" stroke="#0d9488" stroke-width="11" fill="none" stroke-linecap="round" />
    <circle cx="100" cy="151" r="5.5" fill="#facc15" />
    <circle cx="100" cy="95" r="52" fill="#c9853b" />
    <ellipse cx="100" cy="116" rx="27" ry="21" fill="#f3d9b1" />
    <circle cx="78" cy="87" r="6.5" fill="#3f2a1a" />
    <circle cx="122" cy="87" r="6.5" fill="#3f2a1a" />
    <circle cx="80" cy="85" r="1.6" fill="#fff" />
    <circle cx="124" cy="85" r="1.6" fill="#fff" />
    <ellipse cx="100" cy="108" rx="9.5" ry="7.5" fill="#3f2a1a" />
    <path d="M86,123 Q100,133 114,123" stroke="#3f2a1a" stroke-width="3.5" fill="none" stroke-linecap="round" />
    <rect x="68" y="196" width="19" height="26" rx="9.5" fill="#f3d9b1" />
    <rect x="113" y="196" width="19" height="26" rx="9.5" fill="#f3d9b1" />
</svg>

{{-- Sitting cat --}}
<svg class="wecko-login-pet wecko-login-pet--cat" viewBox="0 0 180 205" aria-hidden="true" focusable="false">
    <path d="M148,196 C186,196 190,138 162,116" stroke="#64748b" stroke-width="15" fill="none" stroke-linecap="round" />
    <path d="M22,205 Q22,118 90,118 Q158,118 158,205 Z" fill="#94a3b8" />
    <polygon points="55,50 68,8 83,52" fill="#94a3b8" />
    <polygon points="97,52 112,8 125,50" fill="#94a3b8" />
    <polygon points="60,45 68,22 76,46" fill="#5eead4" />
    <polygon points="104,46 112,22 120,45" fill="#5eead4" />
    <circle cx="90" cy="78" r="46" fill="#cbd5e1" />
    <ellipse cx="90" cy="96" rx="23" ry="16" fill="#f1f5f9" />
    <ellipse cx="72" cy="70" rx="7" ry="9.5" fill="#134e4a" />
    <ellipse cx="108" cy="70" rx="7" ry="9.5" fill="#134e4a" />
    <polygon points="84,89 96,89 90,97" fill="#f472b6" />
    <path d="M100,100 Q90,106 80,100" stroke="#475569" stroke-width="2.5" fill="none" stroke-linecap="round" />
    <g stroke="#64748b" stroke-width="2" stroke-linecap="round">
        <line x1="52" y1="93" x2="18" y2="87" />
        <line x1="52" y1="98" x2="16" y2="98" />
        <line x1="52" y1="103" x2="18" y2="110" />
        <line x1="128" y1="93" x2="162" y2="87" />
        <line x1="128" y1="98" x2="164" y2="98" />
        <line x1="128" y1="103" x2="162" y2="110" />
    </g>
    <ellipse cx="70" cy="192" rx="13" ry="10" fill="#f1f5f9" />
    <ellipse cx="110" cy="192" rx="13" ry="10" fill="#f1f5f9" />
</svg>
