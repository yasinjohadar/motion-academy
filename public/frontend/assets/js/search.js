/**
 * Navbar live search — Motion Academy.
 *
 * - The placeholder types out real category and course names.
 * - Focusing an empty box shows recent searches, categories and featured courses.
 * - Typing shows courses, categories and articles, with the match highlighted.
 * - Arrow keys move through results, Enter opens one, Esc closes, Ctrl/⌘+K focuses.
 */
(function () {
  const RECENT_KEY = 'motion_recent_searches';
  const MAX_RECENT = 5;

  const escapeHtml = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  const highlight = (text, query) => {
    const safe = escapeHtml(text);
    if (!query) return safe;
    const pattern = query.trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return pattern ? safe.replace(new RegExp(`(${pattern})`, 'gi'), '<mark>$1</mark>') : safe;
  };

  const readRecent = () => {
    try {
      return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]').filter(Boolean).slice(0, MAX_RECENT);
    } catch {
      return [];
    }
  };

  const saveRecent = (query) => {
    try {
      const next = [query, ...readRecent().filter((q) => q !== query)].slice(0, MAX_RECENT);
      localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    } catch {
      /* storage unavailable: recent searches are a convenience only */
    }
  };

  const clearRecent = () => {
    try {
      localStorage.removeItem(RECENT_KEY);
    } catch {
      /* ignore */
    }
  };

  window.initNavSearch = function initNavSearch() {
    const input = document.getElementById('nav-search-input');
    const panel = document.getElementById('nav-search-results');
    const box = document.getElementById('siteSearchBox');
    const form = document.querySelector('.site-search-form');
    if (!input || !panel || !box) return;

    const suggestUrl = document.querySelector('meta[name="search-suggest-url"]')?.content || '/search/suggest';
    const coursesUrl = document.querySelector('meta[name="courses-url"]')?.content || '/courses';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let debounceTimer = null;
    let controller = null;
    let suggestions = null;
    let activeIndex = -1;
    let lastQuery = '';

    /* ---------- Rendering ---------- */

    const courseRow = (course, query) => `
      <a href="${escapeHtml(course.url)}" class="ss-item ss-course" role="option" style="--item-color: ${escapeHtml(course.color)}">
        <span class="ss-thumb">${
          course.thumbnail
            ? `<img src="${escapeHtml(course.thumbnail)}" alt="" loading="lazy">`
            : `<i class="${escapeHtml(course.icon)}"></i>`
        }</span>
        <span class="ss-body">
          <strong>${highlight(course.title, query)}</strong>
          <small>${escapeHtml(course.category || '')}${course.lessons ? ` · ${course.lessons} درس` : ''}</small>
        </span>
        <span class="ss-price ${course.is_free ? 'is-free' : ''}">${escapeHtml(course.price)}</span>
      </a>`;

    const categoryChip = (category, query) => `
      <a href="${escapeHtml(category.url)}" class="ss-item ss-chip" role="option" style="--item-color: ${escapeHtml(category.color)}">
        <i class="${escapeHtml(category.icon)}"></i>
        <span>${highlight(category.name, query)}</span>
        ${category.count ? `<em>${category.count}</em>` : ''}
      </a>`;

    const postRow = (post, query) => `
      <a href="${escapeHtml(post.url)}" class="ss-item ss-post" role="option">
        <span class="ss-post-icon"><i class="fas fa-feather-alt"></i></span>
        <span class="ss-body"><strong>${highlight(post.title, query)}</strong><small>${escapeHtml(post.meta)}</small></span>
      </a>`;

    const group = (title, icon, body, extra = '') =>
      body ? `<div class="ss-group"><div class="ss-group-head"><span><i class="${icon}"></i> ${title}</span>${extra}</div>${body}</div>` : '';

    const renderSuggestions = () => {
      const recent = readRecent();
      const recentHtml = recent
        .map((q) => `<a href="${coursesUrl}?search=${encodeURIComponent(q)}" class="ss-item ss-recent" role="option" data-query="${escapeHtml(q)}"><i class="fas fa-history"></i><span>${escapeHtml(q)}</span></a>`)
        .join('');

      panel.innerHTML =
        group('عمليات بحث سابقة', 'fas fa-history', recentHtml, '<button type="button" class="ss-clear">مسح</button>') +
        group('تصفّح المجالات', 'fas fa-shapes', suggestions?.categories?.length ? `<div class="ss-chips">${suggestions.categories.map((c) => categoryChip(c, '')).join('')}</div>` : '') +
        group('دورات مقترحة', 'fas fa-star', (suggestions?.courses || []).map((c) => courseRow(c, '')).join('')) +
        `<div class="ss-hint"><span><kbd>↑</kbd><kbd>↓</kbd> للتنقل</span><span><kbd>Enter</kbd> للفتح</span><span><kbd>Esc</kbd> للإغلاق</span></div>`;

      panel.querySelector('.ss-clear')?.addEventListener('mousedown', (event) => {
        event.preventDefault();
        clearRecent();
        renderSuggestions();
      });
      open();
    };

    const renderResults = (data) => {
      const query = data.query;
      if (!data.total) {
        panel.innerHTML = `
          <div class="ss-empty">
            <span class="ss-empty-icon"><i class="fas fa-search"></i></span>
            <strong>لا توجد نتائج لـ «${escapeHtml(query)}»</strong>
            <small>جرّب كلمة أقصر، أو تصفّح كل الدورات.</small>
            <a href="${coursesUrl}" class="ss-empty-link">تصفّح كل الدورات <i class="fas fa-arrow-left"></i></a>
          </div>`;
        open();
        return;
      }

      panel.innerHTML =
        group('الدورات', 'fas fa-play-circle', data.courses.map((c) => courseRow(c, query)).join('')) +
        group('المجالات', 'fas fa-shapes', data.categories.length ? `<div class="ss-chips">${data.categories.map((c) => categoryChip(c, query)).join('')}</div>` : '') +
        group('من المدونة', 'fas fa-feather-alt', data.posts.map((p) => postRow(p, query)).join('')) +
        `<a href="${escapeHtml(data.all_url)}" class="ss-item ss-all" role="option">عرض كل نتائج «${escapeHtml(query)}» <i class="fas fa-arrow-left"></i></a>`;
      open();
    };

    /* ---------- Data ---------- */

    const fetchJson = async (query) => {
      controller?.abort();
      controller = new AbortController();
      box.classList.add('is-loading');
      try {
        const response = await fetch(`${suggestUrl}?q=${encodeURIComponent(query)}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          signal: controller.signal,
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return await response.json();
      } finally {
        box.classList.remove('is-loading');
      }
    };

    const loadSuggestions = async () => {
      if (!suggestions) {
        try {
          suggestions = await fetchJson('');
          startPlaceholder(suggestions.phrases || []);
        } catch (error) {
          if (error.name !== 'AbortError') suggestions = { courses: [], categories: [], phrases: [] };
        }
      }
      return suggestions;
    };

    const search = async (query) => {
      lastQuery = query;
      if (!query) {
        await loadSuggestions();
        if (!input.value.trim()) renderSuggestions();
        return;
      }
      try {
        const data = await fetchJson(query);
        if (query === lastQuery) renderResults(data);
      } catch (error) {
        if (error.name !== 'AbortError') console.error(error);
      }
    };

    /* ---------- Open / close / keyboard ---------- */

    const items = () => Array.from(panel.querySelectorAll('.ss-item'));

    const setActive = (index) => {
      const list = items();
      list.forEach((el) => el.classList.remove('is-active'));
      activeIndex = list.length ? (index + list.length) % list.length : -1;
      if (activeIndex >= 0) {
        list[activeIndex].classList.add('is-active');
        list[activeIndex].scrollIntoView({ block: 'nearest' });
      }
    };

    function open() {
      activeIndex = -1;
      panel.hidden = false;
      box.classList.add('is-open');
      input.setAttribute('aria-expanded', 'true');
    }

    function close() {
      panel.hidden = true;
      box.classList.remove('is-open');
      input.setAttribute('aria-expanded', 'false');
    }

    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-controls', 'nav-search-results');
    input.setAttribute('aria-expanded', 'false');
    panel.setAttribute('role', 'listbox');

    input.addEventListener('input', () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => search(input.value.trim()), 220);
    });

    input.addEventListener('focus', () => {
      box.classList.add('is-focused');
      search(input.value.trim());
    });

    input.addEventListener('blur', () => {
      setTimeout(() => {
        close();
        box.classList.remove('is-focused');
      }, 160);
    });

    input.addEventListener('keydown', (event) => {
      if (panel.hidden) return;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setActive(activeIndex + 1);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setActive(activeIndex - 1);
      } else if (event.key === 'Enter' && activeIndex >= 0) {
        event.preventDefault();
        const target = items()[activeIndex];
        if (target.dataset.query) saveRecent(target.dataset.query);
        window.location.href = target.href;
      } else if (event.key === 'Escape') {
        close();
        input.blur();
      }
    });

    // Following a result from a typed query remembers the query.
    panel.addEventListener('mousedown', (event) => {
      if (event.target.closest('.ss-item') && input.value.trim()) saveRecent(input.value.trim());
    });

    form?.addEventListener('submit', (event) => {
      event.preventDefault();
      const query = input.value.trim();
      if (!query) {
        input.focus();
        return;
      }
      saveRecent(query);
      window.location.href = `${coursesUrl}?search=${encodeURIComponent(query)}`;
    });

    document.addEventListener('keydown', (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        input.focus();
        input.select();
      }
    });

    /* ---------- Typing placeholder ---------- */

    const basePlaceholder = input.getAttribute('placeholder') || '';
    let placeholderTimer = null;

    function startPlaceholder(phrases) {
      if (reduceMotion || !phrases.length || placeholderTimer) return;
      let phrase = 0;
      let length = 0;
      let deleting = false;

      const tick = () => {
        if (document.activeElement === input || input.value) {
          input.setAttribute('placeholder', basePlaceholder);
          placeholderTimer = setTimeout(tick, 1200);
          return;
        }
        const text = phrases[phrase];
        length += deleting ? -1 : 1;
        input.setAttribute('placeholder', `ابحث عن: ${text.slice(0, length)}`);

        let delay = deleting ? 40 : 85;
        if (!deleting && length === text.length) {
          deleting = true;
          delay = 1600;
        } else if (deleting && length === 0) {
          deleting = false;
          phrase = (phrase + 1) % phrases.length;
          delay = 350;
        }
        placeholderTimer = setTimeout(tick, delay);
      };
      placeholderTimer = setTimeout(tick, 800);
    }

    // Warm the suggestions once the page is idle so the placeholder can start.
    (window.requestIdleCallback || ((fn) => setTimeout(fn, 1200)))(() => loadSuggestions());
  };
})();
