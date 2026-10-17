const STORAGE_DARK = 'valexdarktheme';
const STORAGE_MENU = 'admin-menu-layout';

/** The three sidebar shapes offered in «إعدادات العرض» → «شكل القائمة». */
const MENU_LAYOUTS = {
    full: { input: '#switcher-default-menu', style: 'overlay', toggled: null },
    closed: { input: '#switcher-closed-menu', style: 'closed', toggled: 'close-menu-close' },
    icons: { input: '#switcher-icon-overlay', style: 'overlay', toggled: 'icon-overlay-close' },
};

export function storedMenuLayout() {
    const value = localStorage.getItem(STORAGE_MENU);

    return MENU_LAYOUTS[value] ? value : 'full';
}

/**
 * Apply a sidebar shape and remember it. On phones the sidebar is an off-canvas
 * drawer, so only the choice is stored; it takes effect on wider screens.
 */
export function applyMenuLayout(layout, { persist = true } = {}) {
    const config = MENU_LAYOUTS[layout] ?? MENU_LAYOUTS.full;
    const html = document.documentElement;

    if (persist) {
        localStorage.setItem(STORAGE_MENU, MENU_LAYOUTS[layout] ? layout : 'full');
    }

    const input = document.querySelector(config.input);
    if (input) input.checked = true;

    if (window.innerWidth < 992) {
        return;
    }

    html.setAttribute('data-vertical-style', config.style);
    if (config.toggled) {
        html.setAttribute('data-toggled', config.toggled);
    } else {
        html.removeAttribute('data-toggled');
    }
}

/**
 * The vendored switcher script (custom-switcher.min.js) calls menu functions from
 * the template's own sidebar script, which this app does not load — our sidebar is
 * React. Point the ones behind our three options at applyMenuLayout, and give the
 * rest harmless stand-ins so a click never throws.
 */
function provideSwitcherMenuFunctions() {
    const map = {
        closedSidemenuFn: () => applyMenuLayout('closed'),
        iconOverayFn: () => applyMenuLayout('icons'),
        iconTextFn: () => applyMenuLayout('icons'),
        detachedFn: () => {},
        doubletFn: () => {},
        iconClickFn: () => {},
        iconHoverFn: () => {},
        menuClickFn: () => {},
        menuhoverFn: () => {},
        // Called by the switcher's «كاملة» button; our change listener applies the layout.
        toggleSidemenu: () => {},
    };
    Object.entries(map).forEach(([name, fn]) => {
        if (typeof window[name] !== 'function') {
            window[name] = fn;
        }
    });
}

/** Wire the «شكل القائمة» and reset controls (once per page load). */
export function bindSwitcherControls() {
    provideSwitcherMenuFunctions();

    Object.entries(MENU_LAYOUTS).forEach(([layout, config]) => {
        const input = document.querySelector(config.input);
        if (!input || input.dataset.adminBound === '1') {
            return;
        }
        input.dataset.adminBound = '1';
        // `change` fires after the vendored click handlers, so this has the last word.
        input.addEventListener('change', () => input.checked && applyMenuLayout(layout));
    });

    const reset = document.querySelector('#reset-all');
    if (reset && reset.dataset.adminBound !== '1') {
        reset.dataset.adminBound = '1';
        reset.addEventListener('click', () => {
            localStorage.removeItem(STORAGE_MENU);
            setTimeout(() => applyMenuLayout('full', { persist: false }), 60);
        });
    }
}

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

    // Keep the chosen sidebar shape across page visits.
    if (window.innerWidth >= 992 && storedMenuLayout() !== 'full') {
        applyMenuLayout(storedMenuLayout(), { persist: false });
    }
}

export function toggleSidemenu() {
    const html = document.documentElement;
    const overlay = document.querySelector('#responsive-overlay');

    if (window.innerWidth >= 992) {
        // The burger folds to the chosen shape (icons by default) and back.
        const folded = storedMenuLayout() === 'closed' ? 'close-menu-close' : 'icon-overlay-close';
        if (html.getAttribute('data-toggled') === folded) {
            html.removeAttribute('data-toggled');
        } else {
            html.setAttribute('data-toggled', folded);
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
