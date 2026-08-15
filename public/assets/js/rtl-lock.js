(function () {
    'use strict';

    function enforceRtl() {
        const html = document.documentElement;
        html.setAttribute('dir', 'rtl');
        html.setAttribute('lang', 'ar');

        const styleLink = document.querySelector('#style');
        if (styleLink) {
            const href = styleLink.getAttribute('href') || '';
            if (!href.includes('bootstrap.rtl')) {
                styleLink.setAttribute('href', href.replace('bootstrap.min.css', 'bootstrap.rtl.min.css'));
            }
        }

        localStorage.setItem('valexrtl', true);
        localStorage.removeItem('valexltr');

        const rtlInput = document.querySelector('#switcher-rtl');
        const ltrInput = document.querySelector('#switcher-ltr');
        if (rtlInput) rtlInput.checked = true;
        if (ltrInput) ltrInput.checked = false;
    }

    enforceRtl();

    document.addEventListener('DOMContentLoaded', enforceRtl);

    const resetBtn = document.querySelector('#reset-all');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            setTimeout(enforceRtl, 50);
        });
    }

    const ltrInput = document.querySelector('#switcher-ltr');
    if (ltrInput) {
        ltrInput.addEventListener('change', enforceRtl);
    }
})();
