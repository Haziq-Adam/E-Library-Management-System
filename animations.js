/* ══════════════════════════════════════════════════
   STB E-Library — WOW Animations (JS)
   Lightweight, performant, progressive-enhancement
   ══════════════════════════════════════════════════ */

(function () {
    'use strict';

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reducedMotion) return; // respect user preference

    // ══════════════════════════════════════════════════
    // 1. SCROLL PROGRESS BAR
    // ══════════════════════════════════════════════════
    function initScrollProgress() {
        const bar = document.createElement('div');
        bar.className = 'scroll-progress';
        document.body.appendChild(bar);

        let raf = null;
        function update() {
            const scroll = window.scrollY;
            const height = document.documentElement.scrollHeight - window.innerHeight;
            const pct = height > 0 ? (scroll / height) * 100 : 0;
            bar.style.width = pct + '%';
            raf = null;
        }
        window.addEventListener('scroll', () => {
            if (!raf) raf = requestAnimationFrame(update);
        }, { passive: true });
        update();
    }

    // ══════════════════════════════════════════════════
    // 3. SCROLL REVEAL (IntersectionObserver)
    //    Auto-apply to common card classes + [data-reveal]
    // ══════════════════════════════════════════════════
    function initScrollReveal() {
        if (!('IntersectionObserver' in window)) return;

        // Auto-tag common containers if not already tagged
        const autoSelectors = [
            '.stat-card', '.upcoming-card', '.feature-card', '.room-card',
            '.public-card', '.booking-row', '.today-card', '.section-header',
            '.booking-form-card', '.empty-card', '.status-tab', '.page-head',
        ];
        document.querySelectorAll(autoSelectors.join(', ')).forEach((el, i) => {
            if (!el.hasAttribute('data-reveal')) {
                el.setAttribute('data-reveal', '');
            }
            // Apply stagger delay within each parent grid
            if (!el.hasAttribute('data-delay')) {
                const parent = el.parentElement;
                if (parent) {
                    const siblings = Array.from(parent.children).filter(c => c.matches(autoSelectors.join(', ')));
                    const idx = siblings.indexOf(el);
                    if (idx >= 0 && idx < 6) el.setAttribute('data-delay', String(idx));
                }
            }
        });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -50px 0px' });

        document.querySelectorAll('[data-reveal]').forEach((el) => {
            // Reveal elements above fold immediately
            const rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                requestAnimationFrame(() => el.classList.add('revealed'));
            } else {
                observer.observe(el);
            }
        });
    }

    // ══════════════════════════════════════════════════
    // 4. BUTTON RIPPLE on click
    // ══════════════════════════════════════════════════
    function initButtonRipple() {
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn, .btn-primary, .btn-ghost, .btn-nav-cta, .btn-submit');
            if (!btn) return;

            const rect = btn.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;

            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            btn.appendChild(ripple);
            setTimeout(() => ripple.remove(), 800);
        });
    }

    // (Button radial highlight removed)

    // (Card 3D tilt removed)

    // (Magnetic buttons removed)

    // ══════════════════════════════════════════════════
    // 8. TEXT REVEAL (split words in h1 elements)
    // ══════════════════════════════════════════════════
    function initTextReveal() {
        const targets = document.querySelectorAll('[data-text-reveal], .page-head h1, .dash-hero-text h1');
        targets.forEach((el) => {
            if (el.dataset.textRevealDone) return;
            el.dataset.textRevealDone = '1';

            // Skip if contains complex children (em, span)
            const hasInline = el.querySelector('em, span');
            if (hasInline) {
                // Wrap each child/textnode's words carefully
                const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
                const textNodes = [];
                let n;
                while ((n = walker.nextNode())) textNodes.push(n);

                let wordCount = 0;
                textNodes.forEach((tn) => {
                    const words = tn.textContent.split(/(\s+)/);
                    const frag = document.createDocumentFragment();
                    words.forEach((w) => {
                        if (/\s/.test(w) || w === '') {
                            frag.appendChild(document.createTextNode(w));
                        } else {
                            const span = document.createElement('span');
                            span.className = 'word';
                            span.textContent = w;
                            span.style.animationDelay = (wordCount * 0.06 + 0.2) + 's';
                            wordCount++;
                            frag.appendChild(span);
                        }
                    });
                    tn.parentNode.replaceChild(frag, tn);
                });
            } else {
                const words = el.textContent.split(/\s+/);
                el.textContent = '';
                words.forEach((w, i) => {
                    const span = document.createElement('span');
                    span.className = 'word';
                    span.textContent = w;
                    span.style.animationDelay = (i * 0.06 + 0.2) + 's';
                    el.appendChild(span);
                    el.appendChild(document.createTextNode(' '));
                });
            }
            el.classList.add('text-reveal');
        });
    }

    // ══════════════════════════════════════════════════
    // 9. PAGE EXIT TRANSITION
    // ══════════════════════════════════════════════════
    function initPageTransitions() {
        document.addEventListener('click', (e) => {
            const a = e.target.closest('a');
            if (!a) return;
            const href = a.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) return;
            if (a.target === '_blank' || a.hasAttribute('download')) return;
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;

            // Only same-origin
            try {
                const url = new URL(href, window.location.href);
                if (url.origin !== window.location.origin) return;
                if (url.pathname === window.location.pathname && url.search === window.location.search) return;
            } catch (err) { return; }

            e.preventDefault();
            document.querySelector('main')?.classList.add('page-leaving');
            setTimeout(() => { window.location.href = href; }, 380);
        });
    }

    // ══════════════════════════════════════════════════
    // 10. INIT ALL
    // ══════════════════════════════════════════════════
    function init() {
        initScrollProgress();
        initScrollReveal();
        initButtonRipple();
        initTextReveal();
        initPageTransitions();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
