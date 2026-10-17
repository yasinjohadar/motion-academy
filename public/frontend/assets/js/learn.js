/**
 * Lesson player (frontend/student/learn.blade.php).
 *
 *  - "أكملت الدرس" toggles completion without reloading (the form still works
 *    without JavaScript).
 *  - A <video> lesson reports where it is every 15 seconds and on pause; the
 *    server marks the lesson complete once 90% has been watched, and the video
 *    resumes from the saved position next time.
 *  - On phones the curriculum is a sheet opened from the top bar.
 *
 * YouTube/Vimeo embeds are completed by hand: tracking them needs each
 * provider's player library.
 */
(function () {
    'use strict';

    const root = document.getElementById('lp');
    if (!root) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const form = root.querySelector('[data-lp-complete-form]');
    const button = root.querySelector('[data-lp-complete]');
    const video = root.querySelector('[data-lp-video]');
    let completed = root.dataset.completed === '1';

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        }).then((response) => (response.ok ? response.json() : Promise.reject(response)));
    }

    function toast(message) {
        const container = document.getElementById('toast-container');
        if (!container) {
            return;
        }
        const item = document.createElement('div');
        item.className = 'lp-toast';
        item.textContent = message;
        container.appendChild(item);
        setTimeout(() => item.classList.add('is-leaving'), 2600);
        setTimeout(() => item.remove(), 3100);
    }

    /** Reflect the server's answer everywhere it shows. */
    function render(state) {
        completed = state.completed;
        root.dataset.completed = completed ? '1' : '0';

        if (button) {
            button.classList.toggle('is-done', completed);
            button.querySelector('i').className = completed ? 'fas fa-check-circle' : 'far fa-circle';
            button.querySelector('span').textContent = completed ? 'مكتمل' : 'أكملت الدرس';
        }
        if (form) {
            form.querySelector('input[name="completed"]').value = completed ? '0' : '1';
        }

        const current = root.querySelector('.lp-item.is-current');
        current?.classList.toggle('is-done', completed);

        const doneCount = root.querySelectorAll('.lp-item.is-done').length;
        root.querySelector('[data-lp-done-count]')?.replaceChildren(String(doneCount));

        if (typeof state.percent === 'number') {
            root.querySelector('[data-lp-bar]')?.style.setProperty('width', state.percent + '%');
            root.querySelector('[data-lp-percent]')?.replaceChildren(state.percent + '%');
        }

        root.querySelector('[data-lp-finished]')?.classList.toggle('d-none', !state.course_completed);
    }

    // ---- Completion toggle ---------------------------------------------------
    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        const target = !completed;
        button.disabled = true;

        post(root.dataset.completeUrl, { completed: target })
            .then((state) => {
                render(state);
                if (state.course_completed && target) {
                    toast('🎉 مبروك! أكملت الدورة كاملة');
                } else if (target) {
                    toast(root.dataset.nextUrl ? 'أحسنت! انتقل للدرس التالي' : 'أحسنت! أكملت الدرس');
                }
            })
            .catch(() => toast('تعذّر الحفظ، حاول مرة أخرى'))
            .finally(() => {
                button.disabled = false;
            });
    });

    // ---- Video position & auto-complete -------------------------------------
    if (video) {
        const start = parseInt(root.dataset.position || '0', 10);
        let lastSent = start;

        video.addEventListener('loadedmetadata', () => {
            // Resume, unless they had practically finished it.
            if (start > 5 && start < video.duration - 10) {
                video.currentTime = start;
            }
        });

        const report = () => {
            const seconds = Math.floor(video.currentTime);
            if (Math.abs(seconds - lastSent) < 5) {
                return;
            }
            lastSent = seconds;

            post(root.dataset.positionUrl, {
                seconds,
                duration: Number.isFinite(video.duration) ? Math.floor(video.duration) : null,
            })
                .then((state) => {
                    if (state.completed && !completed) {
                        render(state);
                        toast('✓ اكتمل الدرس تلقائياً');
                    }
                })
                .catch(() => {});
        };

        let timer = null;
        video.addEventListener('play', () => {
            timer = setInterval(report, 15000);
        });
        video.addEventListener('pause', () => {
            clearInterval(timer);
            report();
        });
        video.addEventListener('ended', () => {
            clearInterval(timer);
            report();
        });
    }

    // ---- Curriculum sheet on phones -----------------------------------------
    const curriculum = root.querySelector('[data-lp-curriculum]');
    root.querySelectorAll('[data-lp-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const open = !root.classList.contains('is-curriculum-open');
            root.classList.toggle('is-curriculum-open', open);
            root.querySelector('.lp-toggle')?.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    // Keep the current lesson in view inside the curriculum (without moving the page).
    const currentItem = curriculum?.querySelector('.lp-item.is-current');
    if (curriculum && currentItem) {
        curriculum.scrollTop = Math.max(0, currentItem.offsetTop - curriculum.clientHeight / 2);
    }
})();
