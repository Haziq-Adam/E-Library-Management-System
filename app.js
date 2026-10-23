/* ============================================
   STB E-Library — GamGam Interactions
   ============================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ─── PRELOADER ───────────────────────────────
    const preloader = document.getElementById('preloader');
    if (preloader) {
        const hide = () => preloader.classList.add('hidden');
        if (document.readyState === 'complete') {
            setTimeout(hide, 250);
        } else {
            window.addEventListener('load', () => setTimeout(hide, 250));
        }
        setTimeout(hide, 2000); // safety
    }

    // ─── MOBILE NAV TOGGLE ───────────────────────
    const navToggle = document.getElementById('navToggle');
    const navLinks = document.getElementById('navLinks');
    if (navToggle && navLinks) {
        navToggle.addEventListener('click', () => navLinks.classList.toggle('open'));
    }

    // ─── SCROLL REVEAL ───────────────────────────
    const revealEls = document.querySelectorAll('.scroll-reveal');
    if (revealEls.length && 'IntersectionObserver' in window) {
        const obs = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('revealed');
                    obs.unobserve(e.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach((el) => obs.observe(el));
    }

    // ─── ANIMATED COUNTERS ───────────────────────
    const counters = document.querySelectorAll('.stat-number');
    if (counters.length && 'IntersectionObserver' in window) {
        const cObs = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    animateCounter(e.target);
                    cObs.unobserve(e.target);
                }
            });
        }, { threshold: 0.5 });
        counters.forEach((c) => cObs.observe(c));
    }

    function animateCounter(el) {
        const target = parseInt(el.getAttribute('data-target') || el.textContent, 10);
        if (isNaN(target)) return;
        if (target === 0) { el.textContent = '0'; return; }
        const duration = 1200;
        const start = performance.now();
        function step(now) {
            const p = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = Math.round(target * eased);
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // ─── SOFT CARD HOVER LIFT ────────────────────
    document.querySelectorAll('.lift-on-hover').forEach((card) => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-6px)';
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = '';
        });
    });

    // ─── SMOOTH SCROLL FOR ANCHORS ───────────────
    document.querySelectorAll('a[href^="#"]').forEach((a) => {
        a.addEventListener('click', function (e) {
            const t = document.querySelector(this.getAttribute('href'));
            if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
        });
    });
});

/* Scroll reveal base styles injected */
(function () {
    const style = document.createElement('style');
    style.textContent = `
        .scroll-reveal { opacity: 0; transform: translateY(24px); transition: opacity .7s cubic-bezier(.22,1,.36,1), transform .7s cubic-bezier(.22,1,.36,1); }
        .scroll-reveal.revealed { opacity: 1; transform: translateY(0); }
        .lift-on-hover { transition: transform .35s cubic-bezier(.22,1,.36,1), box-shadow .35s; }
    `;
    document.head.appendChild(style);
})();
