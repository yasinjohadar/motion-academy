const STORAGE_DARK = 'valexdarktheme';

export function initThemeAttributes() {
    const html = document.documentElement;
    html.setAttribute('dir', 'rtl');
    html.setAttribute('lang', 'ar');
    html.setAttribute('data-nav-layout', 'vertical');
    html.setAttribute('data-vertical-style', 'overlay');

    if (window.innerWidth < 992) {
        html.setAttribute('data-toggled', 'close');
    } else if (html.getAttribute('data-toggled') === 'close') {
        html.removeAttribute('data-toggled');
    }

    if (localStorage.getItem(STORAGE_DARK) === 'true') {
        applyDarkTheme();
    } else {
        applyLightTheme();
    }
}

export function toggleSidemenu() {
    const html = document.documentElement;
    const overlay = document.querySelector('#responsive-overlay');

    if (window.innerWidth >= 992) {
        if (html.getAttribute('data-toggled') === 'icon-overlay-close') {
            html.removeAttribute('data-toggled');
        } else {
            html.setAttribute('data-toggled', 'icon-overlay-close');
        }
        return;
    }

    if (html.getAttribute('data-toggled') === 'close') {
        html.setAttribute('data-toggled', 'open');
        overlay?.classList.add('active');
    } else {
        html.setAttribute('data-toggled', 'close');
        overlay?.classList.remove('active');
    }
}

export function toggleThemeMode() {
    const html = document.documentElement;
    if (html.getAttribute('data-theme-mode') === 'dark') {
        applyLightTheme();
        localStorage.removeItem(STORAGE_DARK);
        localStorage.removeItem('valexMenu');
        localStorage.removeItem('valexHeader');
    } else {
        applyDarkTheme();
        localStorage.setItem(STORAGE_DARK, 'true');
        localStorage.setItem('valexMenu', 'dark');
        localStorage.setItem('valexHeader', 'dark');
    }
}

function applyDarkTheme() {
    const html = document.documentElement;
    html.setAttribute('data-theme-mode', 'dark');
    html.setAttribute('data-header-styles', 'dark');
    html.setAttribute('data-menu-styles', 'dark');

    const dark = document.querySelector('#switcher-dark-theme');
    const menuDark = document.querySelector('#switcher-menu-dark');
    const headerDark = document.querySelector('#switcher-header-dark');
    if (dark) dark.checked = true;
    if (menuDark) menuDark.checked = true;
    if (headerDark) headerDark.checked = true;
}

function applyLightTheme() {
    const html = document.documentElement;
    html.setAttribute('data-theme-mode', 'light');
    html.setAttribute('data-header-styles', 'light');
    html.setAttribute('data-menu-styles', 'light');
    html.removeAttribute('data-bg-theme');

    const light = document.querySelector('#switcher-light-theme');
    const menuLight = document.querySelector('#switcher-menu-light');
    const headerLight = document.querySelector('#switcher-header-light');
    if (light) light.checked = true;
    if (menuLight) menuLight.checked = true;
    if (headerLight) headerLight.checked = true;
}

export function bindOverlayClose() {
    const overlay = document.querySelector('#responsive-overlay');
    if (!overlay || overlay.dataset.bound === '1') {
        return;
    }

    overlay.dataset.bound = '1';
    overlay.addEventListener('click', () => {
        document.documentElement.setAttribute('data-toggled', 'close');
        overlay.classList.remove('active');
    });
}
