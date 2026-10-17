/**
 * Site navigation (layouts/navbar.blade.php).
 * - Desktop: a gradient indicator slides under the hovered / current link.
 * - Below 1200px: the search slides down from the bar; the menu is a side sheet.
 * - The bar condenses once the page is scrolled and hides the dock while scrolling down.
 */
(function () {
  const header = document.getElementById('siteNavbar');
  if (!header) return;

  const links = header.querySelector('.mnav-links');
  const indicator = header.querySelector('.mnav-indicator');
  const sheet = document.getElementById('mnavSheet');
  const dock = document.querySelector('.mnav-dock');
  const searchInput = document.getElementById('nav-search-input');
  const openButtons = document.querySelectorAll('[data-nav-open]');
  const desktop = window.matchMedia('(min-width: 1200px)');

  /* ---------- Sliding indicator ---------- */

  function moveIndicator(target) {
    if (!indicator || !links) return;
    if (!target) {
      indicator.style.opacity = '0';
      return;
    }
    const box = links.getBoundingClientRect();
    const rect = target.getBoundingClientRect();
    indicator.style.opacity = '1';
    indicator.style.width = `${rect.width}px`;
    indicator.style.transform = `translateX(${rect.left - box.left}px)`;
  }

  const current = () => links?.querySelector('.mnav-link.is-active') || null;

  if (links) {
    links.querySelectorAll('.mnav-link').forEach((link) => {
      link.addEventListener('mouseenter', () => moveIndicator(link));
      link.addEventListener('focus', () => moveIndicator(link));
    });
    links.addEventListener('mouseleave', () => moveIndicator(current()));
    // Fonts change link widths after load; place the indicator once they settle.
    const place = () => {
      indicator.style.transition = 'none';
      moveIndicator(current());
      requestAnimationFrame(() => (indicator.style.transition = ''));
    };
    (document.fonts?.ready || Promise.resolve()).then(place);
    window.addEventListener('resize', place);
  }

  /* ---------- Search (below 1200px it drops down) ---------- */

  document.querySelectorAll('[data-nav-search]').forEach((button) =>
    button.addEventListener('click', () => {
      if (desktop.matches) {
        searchInput?.focus();
        return;
      }
      const open = !header.classList.contains('is-search-open');
      closeSheet();
      header.classList.toggle('is-search-open', open);
      document.querySelectorAll('[data-nav-search]').forEach((b) => b.setAttribute('aria-expanded', String(open)));
      if (open) {
        window.scrollTo({ top: Math.min(window.scrollY, 0), behavior: 'smooth' });
        setTimeout(() => searchInput?.focus(), 180);
      }
    }),
  );

  /* ---------- Side sheet ---------- */

  function openSheet() {
    if (!sheet) return;
    header.classList.remove('is-search-open');
    sheet.classList.add('is-open');
    sheet.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mnav-locked');
    openButtons.forEach((b) => b.setAttribute('aria-expanded', 'true'));
    setTimeout(() => sheet.querySelector('.mnav-sheet-link')?.focus(), 250);
  }

  function closeSheet() {
    if (!sheet?.classList.contains('is-open')) return;
    sheet.classList.remove('is-open');
    sheet.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mnav-locked');
    openButtons.forEach((b) => b.setAttribute('aria-expanded', 'false'));
  }

  openButtons.forEach((b) => b.addEventListener('click', openSheet));
  sheet?.querySelectorAll('[data-nav-close]').forEach((el) => el.addEventListener('click', closeSheet));
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeSheet();
      header.classList.remove('is-search-open');
    }
  });
  desktop.addEventListener('change', (event) => {
    if (event.matches) {
      closeSheet();
      header.classList.remove('is-search-open');
    }
  });

  /* ---------- Scroll states ---------- */

  let lastY = window.scrollY;
  const onScroll = () => {
    const y = window.scrollY;
    header.classList.toggle('is-scrolled', y > 16);
    const max = document.documentElement.scrollHeight - window.innerHeight;
    header.style.setProperty('--progress', max > 0 ? (y / max).toFixed(4) : '0');
    if (dock) dock.classList.toggle('is-hidden', y > lastY && y > 200);
    lastY = y;
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- Account menu: close on outside click and Escape ---------- */
  const account = document.querySelector('[data-nav-account]');
  if (account) {
    document.addEventListener('click', (event) => {
      if (account.open && !account.contains(event.target)) {
        account.open = false;
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && account.open) {
        account.open = false;
        account.querySelector('summary')?.focus();
      }
    });
  }
})();
