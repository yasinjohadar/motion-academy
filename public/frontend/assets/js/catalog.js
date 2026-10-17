/**
 * Courses catalogue (pages/courses.blade.php).
 *
 * Every control updates one state object; each change fetches the matching cards
 * from courses.search, swaps them in, and mirrors the state into the URL so a
 * filtered view can be shared or reloaded.
 */
(function () {
  const root = document.getElementById('ct-catalog');
  if (!root) return;

  const searchUrl = root.dataset.searchUrl;
  const maxPrice = Number(root.dataset.maxPrice) || 0;
  const VIEW_KEY = 'motion_courses_view';

  const els = {
    results: root.querySelector('[data-results]'),
    pagination: root.querySelector('[data-pagination]'),
    count: root.querySelector('[data-count]'),
    chips: root.querySelector('[data-chips]'),
    loader: root.querySelector('[data-loader]'),
    price: root.querySelector('[data-price]'),
    priceOut: root.querySelector('[data-price-out]'),
    activeCount: root.querySelector('[data-active-count]'),
    sortToggle: root.querySelector('[data-sort-toggle]'),
    sortList: root.querySelector('.ct-sort-list'),
    sortLabel: root.querySelector('[data-sort-label]'),
    sortIcon: root.querySelector('[data-sort-icon]'),
    search: document.getElementById('ct-search-input'),
    searchClear: document.querySelector('.ct-search-clear'),
    tracks: document.querySelectorAll('.ct-track'),
  };

  /* ---------- State ---------- */

  const url = new URL(window.location.href);
  const state = {
    search: url.searchParams.get('search') || '',
    categories: url.searchParams.getAll('categories[]').concat(url.searchParams.get('category') ? [url.searchParams.get('category')] : []),
    levels: url.searchParams.getAll('levels[]'),
    pricing: url.searchParams.get('pricing') || '',
    price_max: url.searchParams.has('price_max') ? Number(url.searchParams.get('price_max')) : maxPrice,
    sort: url.searchParams.get('sort') || 'popular',
    page: Number(url.searchParams.get('page') || 1),
    view: readView(),
  };

  function readView() {
    try {
      return localStorage.getItem(VIEW_KEY) === 'list' ? 'list' : 'grid';
    } catch {
      return 'grid';
    }
  }

  function saveView(view) {
    try {
      localStorage.setItem(VIEW_KEY, view);
    } catch {
      /* preference only */
    }
  }

  const params = (includeView = true) => {
    const p = new URLSearchParams();
    if (state.search) p.set('search', state.search);
    state.categories.forEach((c) => p.append('categories[]', c));
    state.levels.forEach((l) => p.append('levels[]', l));
    if (state.pricing) p.set('pricing', state.pricing);
    if (state.price_max < maxPrice) p.set('price_max', String(state.price_max));
    if (state.sort !== 'popular') p.set('sort', state.sort);
    if (state.page > 1) p.set('page', String(state.page));
    if (includeView) p.set('view', state.view);
    return p;
  };

  /* ---------- Fetch ---------- */

  let controller = null;
  let debounce = null;

  async function load({ scroll = false } = {}) {
    controller?.abort();
    controller = new AbortController();
    els.loader.hidden = false;
    els.results.classList.add('is-loading');

    try {
      const response = await fetch(`${searchUrl}?${params()}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: controller.signal,
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();

      els.results.innerHTML = data.html || '';
      els.pagination.innerHTML = data.pagination || '';
      animateCount(Number(data.count) || 0);
      els.results.classList.toggle('ct-list-view', state.view === 'list');
      staggerCards();

      const qs = params(false).toString();
      history.replaceState(null, '', qs ? `${location.pathname}?${qs}` : location.pathname);

      if (scroll) {
        const top = root.getBoundingClientRect().top + window.scrollY - 110;
        window.scrollTo({ top, behavior: 'smooth' });
      }
    } catch (error) {
      if (error.name !== 'AbortError') console.error(error);
    } finally {
      els.loader.hidden = true;
      els.results.classList.remove('is-loading');
    }
  }

  function refresh(options) {
    state.page = 1;
    renderControls();
    load(options);
  }

  function animateCount(target) {
    const start = Number(els.count.textContent) || 0;
    const startedAt = performance.now();
    const step = (now) => {
      const t = Math.min(1, (now - startedAt) / 400);
      els.count.textContent = String(Math.round(start + (target - start) * t));
      if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }

  function staggerCards() {
    els.results.querySelectorAll('.mc-card').forEach((card, i) => {
      card.style.animationDelay = `${i * 50}ms`;
      card.classList.add('ct-card-in');
    });
  }

  /* ---------- Controls → state ---------- */

  const labelFor = {
    category: (slug) => document.querySelector(`.ct-track[data-category="${CSS.escape(slug)}"] .ct-track-name`)?.textContent.trim() || slug,
    level: (value) => document.querySelector(`.ct-level[data-level="${CSS.escape(value)}"]`)?.textContent.trim() || value,
    pricing: (value) => document.querySelector(`[data-segment="pricing"] [data-value="${CSS.escape(value)}"]`)?.textContent.trim() || value,
  };

  function renderControls() {
    els.tracks.forEach((track) => {
      const slug = track.dataset.category;
      track.classList.toggle('is-active', slug ? state.categories.includes(slug) : state.categories.length === 0);
    });
    root.querySelectorAll('.ct-level').forEach((btn) => btn.classList.toggle('is-active', state.levels.includes(btn.dataset.level)));
    root.querySelectorAll('[data-segment="pricing"] button').forEach((btn) => btn.classList.toggle('is-active', btn.dataset.value === state.pricing));
    root.querySelectorAll('[data-view]').forEach((btn) => btn.classList.toggle('is-active', btn.dataset.view === state.view));
    els.price.value = String(state.price_max);
    paintRange();
    if (els.search.value !== state.search) els.search.value = state.search;
    els.searchClear.hidden = !state.search;
    renderChips();
  }

  function renderChips() {
    const chips = [];
    if (state.search) chips.push({ key: 'search', label: `«${state.search}»`, icon: 'fas fa-search' });
    state.categories.forEach((c) => chips.push({ key: 'category', value: c, label: labelFor.category(c), icon: 'fas fa-layer-group' }));
    state.levels.forEach((l) => chips.push({ key: 'level', value: l, label: labelFor.level(l), icon: 'fas fa-signal' }));
    if (state.pricing) chips.push({ key: 'pricing', label: labelFor.pricing(state.pricing), icon: 'fas fa-tag' });
    if (state.price_max < maxPrice) chips.push({ key: 'price', label: `حتى $${state.price_max}`, icon: 'fas fa-coins' });

    els.chips.hidden = chips.length === 0;
    els.chips.innerHTML = chips
      .map((chip) => `<button type="button" class="ct-chip" data-key="${chip.key}" data-value="${chip.value ?? ''}"><i class="${chip.icon}"></i>${escapeHtml(chip.label)}<span class="ct-chip-x" aria-label="إزالة">×</span></button>`)
      .join('') + (chips.length > 1 ? '<button type="button" class="ct-chip ct-chip-clear" data-key="all">مسح الكل</button>' : '');

    if (els.activeCount) {
      els.activeCount.hidden = chips.length === 0;
      els.activeCount.textContent = String(chips.length);
    }
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  function paintRange() {
    const pct = maxPrice ? (state.price_max / maxPrice) * 100 : 100;
    els.price.style.setProperty('--fill', `${pct}%`);
    els.priceOut.textContent = `$${state.price_max}`;
    els.priceOut.style.setProperty('--pos', `${pct}%`);
  }

  function resetAll() {
    Object.assign(state, { search: '', categories: [], levels: [], pricing: '', price_max: maxPrice, sort: 'popular' });
    setSort('popular', false);
    refresh();
  }

  function setSort(value, reload = true) {
    state.sort = value;
    const option = root.querySelector(`[data-sort="${value}"]`);
    root.querySelectorAll('[data-sort]').forEach((btn) => btn.classList.toggle('is-active', btn === option));
    if (option) {
      els.sortLabel.textContent = option.textContent.trim();
      els.sortIcon.className = option.dataset.icon;
    }
    if (reload) refresh();
  }

  /* ---------- Events ---------- */

  els.tracks.forEach((track) =>
    track.addEventListener('click', () => {
      const slug = track.dataset.category;
      if (!slug) state.categories = [];
      else state.categories = state.categories.includes(slug) ? state.categories.filter((c) => c !== slug) : [...state.categories, slug];
      refresh();
    }),
  );

  root.querySelectorAll('.ct-level').forEach((btn) =>
    btn.addEventListener('click', () => {
      const value = btn.dataset.level;
      state.levels = state.levels.includes(value) ? state.levels.filter((l) => l !== value) : [...state.levels, value];
      refresh();
    }),
  );

  root.querySelectorAll('[data-segment="pricing"] button').forEach((btn) =>
    btn.addEventListener('click', () => {
      state.pricing = btn.dataset.value;
      refresh();
    }),
  );

  els.price.addEventListener('input', () => {
    state.price_max = Number(els.price.value);
    paintRange();
    clearTimeout(debounce);
    debounce = setTimeout(() => refresh(), 300);
  });

  els.search.addEventListener('input', () => {
    state.search = els.search.value.trim();
    els.searchClear.hidden = !state.search;
    clearTimeout(debounce);
    debounce = setTimeout(() => refresh(), 320);
  });

  els.search.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      clearTimeout(debounce);
      refresh({ scroll: true });
    }
  });

  els.searchClear.addEventListener('click', () => {
    state.search = '';
    refresh();
    els.search.focus();
  });

  root.querySelector('[data-reset]').addEventListener('click', resetAll);

  els.chips.addEventListener('click', (event) => {
    const chip = event.target.closest('.ct-chip');
    if (!chip) return;
    const { key, value } = chip.dataset;
    if (key === 'all') return resetAll();
    if (key === 'search') state.search = '';
    if (key === 'category') state.categories = state.categories.filter((c) => c !== value);
    if (key === 'level') state.levels = state.levels.filter((l) => l !== value);
    if (key === 'pricing') state.pricing = '';
    if (key === 'price') state.price_max = maxPrice;
    refresh();
  });

  // Sort menu
  els.sortToggle.addEventListener('click', () => {
    const open = els.sortList.hidden;
    els.sortList.hidden = !open;
    els.sortToggle.setAttribute('aria-expanded', String(open));
  });
  root.querySelectorAll('[data-sort]').forEach((btn) =>
    btn.addEventListener('click', () => {
      els.sortList.hidden = true;
      els.sortToggle.setAttribute('aria-expanded', 'false');
      setSort(btn.dataset.sort);
    }),
  );
  document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-sort-menu]')) els.sortList.hidden = true;
  });

  // Grid / list
  root.querySelectorAll('[data-view]').forEach((btn) =>
    btn.addEventListener('click', () => {
      if (state.view === btn.dataset.view) return;
      state.view = btn.dataset.view;
      saveView(state.view);
      renderControls();
      load();
    }),
  );

  // Pagination links rendered by the server
  els.pagination.addEventListener('click', (event) => {
    const link = event.target.closest('a.page-link[data-page]');
    if (!link) return;
    event.preventDefault();
    state.page = Number(link.dataset.page) || 1;
    load({ scroll: true });
  });

  /* ---------- Boot ---------- */

  renderControls();
  staggerCards();
  // The server rendered the grid; reload once only if the visitor prefers the list.
  if (state.view === 'list') load();
})();
