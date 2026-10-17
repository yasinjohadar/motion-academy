document.addEventListener('DOMContentLoaded', () => {
  // Theme Management
  const themeToggleBottons = document.querySelectorAll('.theme-toggle');
  const htmlTag = document.documentElement;
  
  // Light is the default; a visitor's own choice is remembered.
  const savedTheme = localStorage.getItem('motion_theme') || 'light';
  htmlTag.setAttribute('data-theme', savedTheme);
  updateThemeIcons(savedTheme);

  themeToggleBottons.forEach(btn => {
    btn.addEventListener('click', () => {
      const currentTheme = htmlTag.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      
      htmlTag.setAttribute('data-theme', newTheme);
      localStorage.setItem('motion_theme', newTheme);
      updateThemeIcons(newTheme);
    });
  });

  // The toggle's look follows html[data-theme] in CSS; only the state is announced here.
  function updateThemeIcons(theme) {
    themeToggleBottons.forEach(btn => {
      btn.setAttribute('aria-pressed', String(theme === 'dark'));
      btn.setAttribute('title', theme === 'dark' ? 'الوضع النهاري' : 'الوضع الليلي');
    });
  }

  // Cart Management (Global)
  updateCartBadge();

  // Initialize Tooltips & Popovers from Bootstrap if needed
  // ...

  // UI Initializations
  initTypingAnimation();
  initCounters();
  initScrollAnimations();
  initSiteNavbar();

  // Blog Page
  if(document.getElementById('blog-posts-grid')) {
    initBlogPage();
  }

  // Categories Page — links go to /courses with filter (no JS needed)

  // Cart Page Logic
  if(document.getElementById('cart-items-container')) {
    initCartPage();
  }

  // Checkout Page Logic
  if(document.getElementById('checkout-order-items')) {
    initCheckoutPage();
  }
});

// Cart Functions
function getCart() {
  const cart = localStorage.getItem('lms_cart');
  return cart ? JSON.parse(cart) : [];
}

function saveCart(cart) {
  localStorage.setItem('lms_cart', JSON.stringify(cart));
}

function updateCartBadge() {
  const cart = getCart();
  const badges = document.querySelectorAll('.cart-badge');
  badges.forEach(b => {
      b.textContent = cart.length;
      if(cart.length > 0) {
          b.style.transform = 'scale(1.2)';
          setTimeout(() => b.style.transform = 'scale(1)', 200);
      } else {
          b.style.transform = 'scale(0)'; // Hide if empty
      }
  });
}

function normalizeCourseItem(input) {
  if (input instanceof HTMLElement) {
    return {
      id: parseInt(input.dataset.courseId, 10),
      title: input.dataset.courseTitle,
      newPrice: parseFloat(input.dataset.coursePrice),
      oldPrice: parseFloat(input.dataset.courseComparePrice || input.dataset.coursePrice),
      slug: input.dataset.courseSlug,
      imgIcon: input.dataset.courseIcon || 'fa-book',
    };
  }

  const course = input;
  return {
    id: course.id,
    title: course.title,
    newPrice: course.newPrice ?? course.price,
    oldPrice: course.oldPrice ?? course.compare_at_price ?? course.newPrice ?? course.price,
    slug: course.slug,
    instructor: course.instructor,
    imgIcon: course.imgIcon || 'fa-book',
  };
}

function addToCart(courseOrBtn) {
  const course = normalizeCourseItem(courseOrBtn);
  const cart = getCart();
  const exists = cart.find(item => item.id === course.id);

  if (exists) {
    showToast('موجود في السلة', 'warning');
  } else {
    cart.push(course);
    saveCart(cart);
    updateCartBadge();
    showToast('تمت الإضافة للسلة!', 'success');
  }
}

function removeFromCart(id) {
  let cart = getCart();
  cart = cart.filter(item => item.id !== id);
  saveCart(cart);
  updateCartBadge();
  if(document.getElementById('cart-items-container')) {
    initCartPage();
  }
}

function clearCart() {
  saveCart([]);
  updateCartBadge();
  if(document.getElementById('cart-items-container')) {
    initCartPage();
  }
}

// Simple Toast Notification Logic
function showToast(title, type='success') {
  let toastContainer = document.getElementById('toast-container');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'toast-container';
    toastContainer.style.position = 'fixed';
    toastContainer.style.bottom = '20px';
    toastContainer.style.left = '20px';
    toastContainer.style.zIndex = '9999';
    document.body.appendChild(toastContainer);
  }

  const toast = document.createElement('div');
  toast.className = `toast align-items-center text-white border-0 glass-panel mb-2`;
  toast.setAttribute('role', 'alert');
  toast.setAttribute('aria-live', 'assertive');
  toast.setAttribute('aria-atomic', 'true');
  toast.style.background = type === 'success' ? 'rgba(40, 167, 69, 0.85)' : type === 'warning' ? 'rgba(255, 193, 7, 0.85)' : 'rgba(220, 53, 69, 0.85)';
  
  toast.innerHTML = `
    <div class="d-flex align-items-center px-3 py-2" style="direction: rtl; gap: 10px;">
      <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'times-circle'}"></i>
      <span class="toast-body py-0 px-0 text-white">${title}</span>
      <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  `;
  
  toastContainer.appendChild(toast);
  
  try {
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
  } catch(e) {
    console.error('Bootstrap JS not loaded', e);
    setTimeout(() => toast.remove(), 3500);
  }
}

function initBlogPage() {
  const posts = document.querySelectorAll('.blog-post-item');
  const searchInput = document.getElementById('blog-search-input');
  const filterTabs = document.querySelectorAll('#blog-filter-tabs .blog-filter-chip');
  const resultsCount = document.getElementById('blog-results-count');
  const emptyState = document.getElementById('blog-empty-state');
  const grid = document.getElementById('blog-posts-grid');

  let currentFilter = 'all';

  function renderBlogPosts() {
    const q = (searchInput?.value || '').trim().toLowerCase();
    let visible = 0;

    posts.forEach(post => {
      const cat = post.dataset.category;
      const title = (post.dataset.title || '').toLowerCase();
      const excerpt = post.querySelector('.blog-excerpt')?.textContent.toLowerCase() || '';
      const matchCat = currentFilter === 'all' || cat === currentFilter;
      const matchSearch = !q || title.includes(q) || excerpt.includes(q);
      const show = matchCat && matchSearch;

      post.classList.toggle('d-none', !show);
      if (show) visible++;
    });

    if (resultsCount) {
      resultsCount.innerHTML = visible
        ? `عرض <span class="en-text">${visible}</span> مقال${visible === 1 ? '' : 'اً'}`
        : 'لا توجد نتائج';
    }

    if (emptyState && grid) {
      emptyState.classList.toggle('d-none', visible > 0);
      grid.classList.toggle('d-none', visible === 0);
    }
  }

  filterTabs.forEach(tab => {
    tab.addEventListener('click', () => {
      filterTabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentFilter = tab.dataset.filter;
      renderBlogPosts();
    });
  });

  searchInput?.addEventListener('input', renderBlogPosts);
  renderBlogPosts();
}

// ============================================
// Animations
// ============================================

// Typing Animation
function initTypingAnimation() {
  const typingElement = document.querySelector('.typing-text');
  if(!typingElement) return;
  
  const texts = JSON.parse(typingElement.getAttribute('data-text')) || [];
  let textIndex = 0;
  let charIndex = 0;
  let isDeleting = false;
  
  function type() {
    const currentText = texts[textIndex];
    if(isDeleting) {
      typingElement.textContent = currentText.substring(0, charIndex - 1);
      charIndex--;
    } else {
      typingElement.textContent = currentText.substring(0, charIndex + 1);
      charIndex++;
    }
    
    let typeSpeed = isDeleting ? 50 : 100;
    
    if(!isDeleting && charIndex === currentText.length) {
      typeSpeed = 2000; // pause at end
      isDeleting = true;
    } else if (isDeleting && charIndex === 0) {
      isDeleting = false;
      textIndex = (textIndex + 1) % texts.length;
      typeSpeed = 500; // pause before typing next
    }
    
    setTimeout(type, typeSpeed);
  }
  
  setTimeout(type, 1000);
}

// CountUp Animation using Intersection Observer
function initCounters() {
  const counters = document.querySelectorAll('.counter');
  const duration = 2000;
  
  const observerOptions = { threshold: 0.5 };
  
  const observer = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if(entry.isIntersecting) {
        const target = entry.target;
        const finalValue = parseInt(target.getAttribute('data-target'));
        const startTime = performance.now();
        
        function updateCounter(currentTime) {
          const elapsedTime = currentTime - startTime;
          if(elapsedTime < duration) {
            const currentValue = Math.floor((elapsedTime / duration) * finalValue);
            target.innerText = currentValue >= 1000 ? (currentValue/1000).toFixed(1) + 'K+' : currentValue;
            requestAnimationFrame(updateCounter);
          } else {
            target.innerText = finalValue >= 1000 ? (finalValue/1000).toFixed(1) + 'K+' : finalValue;
          }
        }
        
        requestAnimationFrame(updateCounter);
        observer.unobserve(target); // Only animate once
      }
    });
  }, observerOptions);
  
  counters.forEach(counter => observer.observe(counter));
}

// Scroll Fade-Up animations
function initScrollAnimations() {
  const fadeElements = document.querySelectorAll('.section-fade-up');
  
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if(entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });
  
  fadeElements.forEach(el => observer.observe(el));
}

function initSiteNavbar() {
  const navbar = document.getElementById('siteNavbar');
  if (!navbar) return;

  const onScroll = () => navbar.classList.toggle('is-scrolled', window.scrollY > 16);
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // Live search lives in search.js.
  window.initNavSearch?.();
}

// ============================================
// Cart Page
// ============================================
function initCartPage() {
  const container = document.getElementById('cart-items-container');
  const emptyMsg  = document.getElementById('empty-cart-msg');
  const countSpan = document.getElementById('cart-page-count');
  const clearBtn  = document.getElementById('clear-cart-btn');
  const checkoutBtn = document.getElementById('checkout-btn');
  const sumOld    = document.getElementById('summary-old-total');
  const sumDisc   = document.getElementById('summary-discount');
  const sumTotal  = document.getElementById('summary-total');

  const cart = getCart();
  if (countSpan) countSpan.textContent = cart.length;

  if (cart.length === 0) {
    container.innerHTML = '';
    if (emptyMsg) { emptyMsg.classList.remove('d-none'); container.appendChild(emptyMsg); }
    if (clearBtn) clearBtn.style.display = 'none';
    if (checkoutBtn) {
      checkoutBtn.classList.add('disabled');
      checkoutBtn.setAttribute('aria-disabled', 'true');
      checkoutBtn.setAttribute('tabindex', '-1');
    }
    if (sumOld) sumOld.textContent = '$0';
    if (sumDisc) sumDisc.textContent = '-$0';
    if (sumTotal) sumTotal.textContent = '$0';
    return;
  }

  if (emptyMsg) emptyMsg.classList.add('d-none');
  if (clearBtn) clearBtn.style.display = 'inline-flex';
  if (checkoutBtn) {
    checkoutBtn.classList.remove('disabled');
    checkoutBtn.removeAttribute('aria-disabled');
    checkoutBtn.removeAttribute('tabindex');
  }

  let oldTotal = 0, newTotal = 0, html = '';
  cart.forEach(item => {
    oldTotal += (item.oldPrice || item.newPrice);
    newTotal += item.newPrice;
    html += `
    <article class="cart-item">
      <span class="cart-item-icon"><i class="fas ${item.imgIcon || 'fa-laptop-code'}"></i></span>
      <div class="cart-item-info">
        <h3 class="cart-item-title">${item.title}</h3>
        ${item.instructor ? `<p class="cart-item-instructor"><i class="fas fa-user-tie"></i> ${item.instructor}</p>` : ''}
      </div>
      <div class="cart-item-actions">
        <span class="cart-item-price en-text">$${item.newPrice}</span>
        <button type="button" class="cart-item-remove" onclick="removeFromCart(${item.id})">
          <i class="fas fa-trash-alt"></i> إزالة
        </button>
      </div>
    </article>`;
  });

  container.innerHTML = html;

  if (sumOld) sumOld.textContent = '$' + oldTotal;
  if (sumDisc) sumDisc.textContent = '-$' + (oldTotal - newTotal);
  if (sumTotal) sumTotal.textContent = '$' + newTotal;

  // Coupon
  const applyBtn   = document.getElementById('apply-coupon');
  const couponInp  = document.getElementById('coupon-input');
  const couponMsg  = document.getElementById('coupon-msg');
  if (applyBtn) {
    const fresh = applyBtn.cloneNode(true);
    applyBtn.parentNode.replaceChild(fresh, applyBtn);
    fresh.addEventListener('click', () => {
      const val = couponInp.value.trim().toUpperCase();
      if (val === 'LMS20') {
        sumTotal.textContent = '$' + (newTotal * 0.8).toFixed(2);
        couponMsg.textContent = 'تم تطبيق خصم 20%!';
        couponMsg.className = 'cart-coupon-msg is-success';
      } else if (val === '') {
        sumTotal.textContent = '$' + newTotal;
        couponMsg.className = 'cart-coupon-msg d-none';
      } else {
        couponMsg.textContent = 'كود الخصم غير صالح';
        couponMsg.className = 'cart-coupon-msg is-error';
        sumTotal.textContent = '$' + newTotal;
      }
    });
  }

  if (clearBtn) {
    const freshClear = clearBtn.cloneNode(true);
    clearBtn.parentNode.replaceChild(freshClear, clearBtn);
    freshClear.addEventListener('click', clearCart);
  }
}

// ============================================
// Checkout Page
// ============================================
function initCheckoutPage() {
  const cart = getCart();
  const itemsEl   = document.getElementById('checkout-order-items');
  const subtotalEl = document.getElementById('checkout-subtotal');
  const discEl    = document.getElementById('checkout-discount');
  const totalEl   = document.getElementById('checkout-total');
  if (!itemsEl) return;

  if (cart.length === 0) {
    itemsEl.innerHTML = `<div class="checkout-empty">
      <i class="fas fa-shopping-cart"></i>
      <p>لا توجد عناصر في السلة.</p>
      <a href="/courses">تصفح الكورسات</a>
    </div>`;
    return;
  }

  let old = 0, fresh = 0, html = '';
  cart.forEach(item => {
    old += (item.oldPrice || item.newPrice);
    fresh += item.newPrice;
    html += `<div class="checkout-order-item">
      <span class="checkout-order-item-icon"><i class="fas ${item.imgIcon || 'fa-laptop-code'}"></i></span>
      <div class="checkout-order-item-info">
        <strong>${item.title}</strong>
        ${item.instructor ? `<small>${item.instructor}</small>` : ''}
      </div>
      <span class="checkout-order-item-price en-text">$${item.newPrice}</span>
    </div>`;
  });
  itemsEl.innerHTML = html;
  if (subtotalEl) subtotalEl.textContent = '$' + old;
  if (discEl) discEl.textContent = '-$' + (old - fresh);
  if (totalEl) totalEl.textContent = '$' + fresh;
}

// Card Live Preview Functions
function updateCardPreview() {
  const numEl    = document.getElementById('card-number-display');
  const holderEl = document.getElementById('card-holder-display');
  const expEl    = document.getElementById('card-exp-display');
  const cvvEl    = document.getElementById('card-cvv-display');
  const typeEl   = document.getElementById('card-type-icon');

  const num    = document.getElementById('card-number')?.value || '';
  const holder = document.getElementById('card-name')?.value || '';
  const exp    = document.getElementById('card-expiry')?.value || '';
  const cvv    = document.getElementById('card-cvv')?.value || '';

  if (numEl) numEl.textContent = num.padEnd(19, '•').replace(/(.{4})/g, '$1 ').trim() || '•••• •••• •••• ••••';
  if (holderEl) holderEl.textContent = holder.toUpperCase() || 'FULL NAME';
  if (expEl) expEl.textContent = exp || 'MM/YY';
  if (cvvEl) cvvEl.textContent = cvv ? '•'.repeat(cvv.length) : '•••';

  // Detect card type
  if (typeEl) {
    const first = num.replace(/\s/g,'')[0];
    if (first === '4') typeEl.innerHTML = '<i class="fab fa-cc-visa fa-2x text-white opacity-75"></i>';
    else if (first === '5') typeEl.innerHTML = '<i class="fab fa-cc-mastercard fa-2x text-white opacity-75"></i>';
    else if (first === '3') typeEl.innerHTML = '<i class="fab fa-cc-amex fa-2x text-white opacity-75"></i>';
    else typeEl.innerHTML = '<i class="fab fa-cc-visa fa-2x text-white opacity-75"></i>';
  }
}

function formatCardNumber(input) {
  let v = input.value.replace(/\D/g, '').substring(0, 16);
  input.value = v.match(/.{1,4}/g)?.join(' ') || v;
}

function formatExpiry(input) {
  let v = input.value.replace(/\D/g, '').substring(0, 4);
  if (v.length > 2) v = v.substring(0, 2) + '/' + v.substring(2);
  input.value = v;
}

function flipCard(show) {
  const preview = document.getElementById('card-preview');
  if (preview) preview.classList.toggle('flipped', show);
}

function submitOrder() {
  const firstName = document.getElementById('first-name')?.value.trim();
  const email     = document.getElementById('email')?.value.trim();
  const cardNum   = document.getElementById('card-number')?.value.replace(/\s/g,'');
  const cardName  = document.getElementById('card-name')?.value.trim();
  const expiry    = document.getElementById('card-expiry')?.value.trim();
  const cvv       = document.getElementById('card-cvv')?.value.trim();

  if (!firstName || !email || cardNum.length < 16 || !cardName || expiry.length < 5 || cvv.length < 3) {
    showToast('يرجى تعبئة جميع الحقول بشكل صحيح', 'danger');
    return;
  }

  const cart = getCart();
  if (!cart.length) {
    showToast('السلة فارغة', 'warning');
    return;
  }

  const checkoutUrl = document.querySelector('meta[name="checkout-url"]')?.content;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  const btn = document.getElementById('submit-order');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>جارٍ المعالجة...</span>';
  }

  const payload = {
    items: cart.map(item => ({ course_id: item.id })),
    simulate_payment: true,
    payment_method: 'card_simulated',
  };

  const finishSuccess = (data) => {
    clearCart();
    const successEl = document.getElementById('order-success');
    if (successEl) successEl.classList.remove('d-none');
    if (btn) btn.classList.add('d-none');
    showToast(data?.message || 'تم تأكيد طلبك بنجاح!', 'success');
    const checkoutItems = document.getElementById('checkout-order-items');
    if (checkoutItems) {
      checkoutItems.innerHTML = '<p class="text-success text-center fw-bold"><i class="fas fa-check-circle me-2"></i>تم الدفع بنجاح!</p>';
    }
  };

  const finishError = (message) => {
    showToast(message || 'تعذّر إتمام الطلب', 'danger');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-lock"></i><span>تأكيد الدفع وإتمام الطلب</span>';
    }
  };

  if (!checkoutUrl) {
    setTimeout(() => finishSuccess({}), 2000);
    return;
  }

  fetch(checkoutUrl, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
    },
    body: JSON.stringify(payload),
    credentials: 'same-origin',
  })
    .then(async (res) => {
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        if (res.status === 401 || res.status === 403) {
          throw new Error('يجب تسجيل الدخول كطالب لإتمام الشراء');
        }
        throw new Error(data.message || 'تعذّر إتمام الطلب');
      }
      finishSuccess(data);
    })
    .catch((err) => finishError(err.message));
}
