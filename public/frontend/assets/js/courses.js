/**
 * Courses catalog — AJAX filters, pagination, URL state
 */
(function () {
  const searchUrl = window.COURSES_SEARCH_URL;
  if (!searchUrl) return;

  const container = document.getElementById('all-courses-container');
  const paginationWrap = document.getElementById('courses-pagination-wrap');
  const countSpan = document.getElementById('courses-count');
  const searchInput = document.getElementById('search-input');
  const priceRange = document.getElementById('price-range');
  const priceVal = document.getElementById('price-val');
  const sortSelect = document.getElementById('sort-select');
  const resetBtn = document.getElementById('reset-filters');
  const viewBtns = document.querySelectorAll('.toggle-view');
  const filterPanel = document.getElementById('coursesFilters');

  if (!container) return;

  let currentView = localStorage.getItem('courses_view') || 'grid';
  let debounceTimer = null;
  let isLoading = false;

  function getFilters(page = 1) {
    const categories = Array.from(document.querySelectorAll('.filter-category:checked')).map((el) => el.value);
    const tags = Array.from(document.querySelectorAll('.filter-tag:checked')).map((el) => el.value);
    const levels = Array.from(document.querySelectorAll('.filter-level-input:checked')).map((el) => el.value);

    const params = new URLSearchParams();
    const search = searchInput?.value?.trim();
    if (search) params.set('search', search);
    categories.forEach((c) => params.append('categories[]', c));
    tags.forEach((t) => params.append('tags[]', t));
    levels.forEach((l) => params.append('levels[]', l));
    if (priceRange) params.set('price_max', priceRange.value);
    if (sortSelect) params.set('sort', sortSelect.value);
    params.set('view', currentView);
    if (page > 1) params.set('page', String(page));

    return params;
  }

  function updateUrl(params) {
    const qs = params.toString();
    const url = qs ? `${window.location.pathname}?${qs}` : window.location.pathname;
    history.replaceState(null, '', url);
  }

  function setLoading(loading) {
    isLoading = loading;
    container.classList.toggle('is-loading', loading);
    if (loading) container.style.opacity = '0.5';
    else container.style.opacity = '1';
  }

  async function fetchCourses(page = 1) {
    if (isLoading) return;

    const params = getFilters(page);
    setLoading(true);

    try {
      const response = await fetch(`${searchUrl}?${params.toString()}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });

      if (!response.ok) throw new Error('Search failed');

      const data = await response.json();
      container.innerHTML = data.html || '';
      if (paginationWrap) paginationWrap.innerHTML = data.pagination || '';
      if (countSpan) countSpan.textContent = data.count ?? 0;

      updateUrl(params);
      bindPagination();
      bindCartButtons();
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  function debouncedFetch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchCourses(1), 300);
  }

  function bindPagination() {
    if (!paginationWrap) return;

    paginationWrap.querySelectorAll('.courses-ajax-pagination a.page-link[data-page]').forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault();
        const page = parseInt(link.dataset.page, 10);
        if (page) fetchCourses(page);
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    });
  }

  function bindCartButtons() {
    container.querySelectorAll('.course-card-cart').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (typeof addToCart === 'function') addToCart(btn);
      });
    });
  }

  function applyViewToggle() {
    viewBtns.forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.view === currentView);
    });
    container.classList.toggle('courses-list-view', currentView === 'list');
  }

  function resetFilters() {
    if (searchInput) searchInput.value = '';
    if (priceRange) {
      priceRange.value = priceRange.max;
      if (priceVal) priceVal.textContent = `$${priceRange.max}`;
    }
    if (sortSelect) sortSelect.value = 'popular';
    document.querySelectorAll('.filter-checkbox').forEach((cb) => { cb.checked = false; });
    fetchCourses(1);
  }

  // Events
  searchInput?.addEventListener('input', debouncedFetch);
  sortSelect?.addEventListener('change', () => fetchCourses(1));
  priceRange?.addEventListener('input', (e) => {
    if (priceVal) priceVal.textContent = `$${e.target.value}`;
    debouncedFetch();
  });
  priceRange?.addEventListener('change', () => fetchCourses(1));

  document.querySelectorAll('.filter-checkbox').forEach((cb) => {
    cb.addEventListener('change', () => fetchCourses(1));
  });

  resetBtn?.addEventListener('click', resetFilters);

  viewBtns.forEach((btn) => {
    btn.addEventListener('click', (e) => {
      currentView = e.currentTarget.dataset.view || 'grid';
      localStorage.setItem('courses_view', currentView);
      applyViewToggle();
      fetchCourses(1);
    });
  });

  // Init from URL
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('search') && searchInput) searchInput.value = urlParams.get('search');
  if (urlParams.get('sort') && sortSelect) sortSelect.value = urlParams.get('sort');
  if (urlParams.get('price_max') && priceRange) {
    priceRange.value = urlParams.get('price_max');
    if (priceVal) priceVal.textContent = `$${priceRange.value}`;
  }
  urlParams.getAll('categories[]').forEach((slug) => {
    const el = document.querySelector(`.filter-category[value="${slug}"]`);
    if (el) el.checked = true;
  });
  urlParams.getAll('tags[]').forEach((slug) => {
    const el = document.querySelector(`.filter-tag[value="${slug}"]`);
    if (el) el.checked = true;
  });
  urlParams.getAll('levels[]').forEach((lvl) => {
    const el = document.querySelector(`.filter-level-input[value="${lvl}"]`);
    if (el) el.checked = true;
  });

  applyViewToggle();
  bindPagination();
  bindCartButtons();

  // Only AJAX refresh if filters in URL or user interacts; SSR already rendered
  const hasFilters = window.location.search.length > 0;
  if (hasFilters) fetchCourses(parseInt(urlParams.get('page') || '1', 10));
})();
