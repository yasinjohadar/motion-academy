/**
 * "Who we are" page (pages/who-we-are.blade.php, css/brand-story.css).
 *  - sections fade in as they enter the screen;
 *  - the numbers count up the first time they are seen;
 *  - the story timeline's playhead follows the scroll;
 *  - the hero timecode runs like an editor at 24 fps.
 * Respects prefers-reduced-motion: everything is simply shown.
 */
(function () {
    'use strict';

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.documentElement.classList.add('ws-js');

    // ---- Reveal --------------------------------------------------------------
    const revealables = document.querySelectorAll('[data-ws-reveal]');
    if (reduce || !('IntersectionObserver' in window)) {
        revealables.forEach((el) => el.classList.add('is-in'));
    } else {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-in');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
        );
        revealables.forEach((el) => observer.observe(el));
    }

    // ---- Count-up ------------------------------------------------------------
    const format = new Intl.NumberFormat('en-US');
    const counters = document.querySelectorAll('[data-ws-count]');

    function countUp(el) {
        const target = parseInt(el.dataset.wsCount, 10) || 0;
        if (reduce || target === 0) {
            el.textContent = format.format(target);
            return;
        }
        const duration = 1400;
        const start = performance.now();
        const step = (now) => {
            const t = Math.min(1, (now - start) / duration);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = format.format(Math.round(target * eased));
            if (t < 1) {
                requestAnimationFrame(step);
            }
        };
        el.textContent = '0';
        requestAnimationFrame(step);
    }

    if ('IntersectionObserver' in window) {
        const counterObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        countUp(entry.target);
                        counterObserver.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.6 },
        );
        counters.forEach((el) => counterObserver.observe(el));
    }

    // ---- Story playhead follows the scroll -----------------------------------
    const story = document.querySelector('[data-ws-story]');
    if (story && !reduce) {
        let ticking = false;
        const update = () => {
            const rect = story.getBoundingClientRect();
            const span = rect.height + window.innerHeight * 0.4;
            const progress = Math.min(1, Math.max(0, (window.innerHeight * 0.8 - rect.top) / span));
            story.style.setProperty('--progress', progress.toFixed(3));
            ticking = false;
        };
        window.addEventListener(
            'scroll',
            () => {
                if (!ticking) {
                    ticking = true;
                    requestAnimationFrame(update);
                }
            },
            { passive: true },
        );
        update();
    } else if (story) {
        story.style.setProperty('--progress', '1');
    }

    // ---- Hero timecode at 24 fps ---------------------------------------------
    const timecode = document.querySelector('[data-ws-timecode]');
    if (timecode && !reduce) {
        const pad = (n) => String(n).padStart(2, '0');
        const begin = performance.now();
        setInterval(() => {
            const frames = Math.floor(((performance.now() - begin) / 1000) * 24);
            const seconds = Math.floor(frames / 24);
            timecode.textContent = `00:${pad(Math.floor(seconds / 60) % 60)}:${pad(seconds % 60)}:${pad(frames % 24)}`;
        }, 1000 / 24);
    }
})();
