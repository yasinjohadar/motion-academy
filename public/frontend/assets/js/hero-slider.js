(function () {
  'use strict';

  function initTyping() {
    document.querySelectorAll('.typing-text').forEach(function (el) {
      var raw = el.getAttribute('data-text');
      if (!raw) return;
      var phrases;
      try { phrases = JSON.parse(raw); } catch (e) { return; }
      if (!phrases.length) return;

      var i = 0, j = 0, deleting = false;
      function tick() {
        var current = phrases[i] || '';
        if (!deleting) {
          el.textContent = current.substring(0, j + 1);
          j++;
          if (j === current.length) {
            deleting = true;
            setTimeout(tick, 1800);
            return;
          }
        } else {
          el.textContent = current.substring(0, j - 1);
          j--;
          if (j === 0) {
            deleting = false;
            i = (i + 1) % phrases.length;
          }
        }
        setTimeout(tick, deleting ? 40 : 80);
      }
      tick();
    });
  }

  function initHeroSwiper() {
    var el = document.getElementById('heroSwiper') || document.querySelector('.hero-swiper');
    if (!el || typeof Swiper === 'undefined') return;

    var labels = [];
    try { labels = JSON.parse(el.dataset.labels || '[]'); } catch (e) { labels = []; }

    var config = {
      effect: el.dataset.effect || 'fade',
      speed: parseInt(el.dataset.speed, 10) || 900,
      loop: el.dataset.loop === '1',
    };

    if (config.effect === 'fade') {
      config.fadeEffect = { crossFade: true };
    }

    if (el.dataset.autoplay === '1') {
      config.autoplay = {
        delay: parseInt(el.dataset.delay, 10) || 6000,
        disableOnInteraction: false,
        pauseOnMouseEnter: el.dataset.pauseHover === '1',
      };
    }

    if (el.dataset.showPagination === '1') {
      config.pagination = {
        el: '.hero-pagination',
        clickable: true,
        renderBullet: function (index, className) {
          var label = labels[index] || '';
          return '<span class="' + className + '"><span class="hero-bullet-inner"></span><span class="hero-bullet-label">' + label + '</span></span>';
        },
      };
    }

    if (el.dataset.showNav === '1') {
      config.navigation = {
        nextEl: '.hero-next',
        prevEl: '.hero-prev',
      };
    }

    config.on = {
      init: function () { initTyping(); },
      autoplayTimeLeft: function (s, time, progress) {
        if (el.dataset.showProgress !== '1') return;
        var fill = document.querySelector('.hero-progress-fill');
        if (fill) fill.style.width = ((1 - progress) * 100) + '%';
      },
      slideChangeTransitionStart: function () {
        document.querySelectorAll('.hero-slide.swiper-slide-active .hero-content > *').forEach(function (node) {
          node.style.animation = 'none';
          node.offsetHeight;
          node.style.animation = '';
        });
      },
    };

    new Swiper(el, config);
  }

  document.addEventListener('DOMContentLoaded', function () {
    initHeroSwiper();
  });
})();
