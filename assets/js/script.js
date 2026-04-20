/* Cozy-Library — Script v3.0
   Perubahan v3.0: smooth wheel scroll dengan lerp momentum
*/

/* ============================================================
   SMOOTH WHEEL SCROLL — lerp momentum per-elemen
   Membuat scroll mouse wheel terasa lebih halus & natural
   di sidebar, konten utama, modal, dan tabel.
   ============================================================ */
(function () {
  'use strict';

  // Hindari intersep scroll di perangkat sentuh (HP/Tablet).
  if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
    return;
  }

  // Faktor lerp: semakin kecil = semakin lambat/smooth (0.06–0.14 ideal)
  var EASE = 0.09;
  // Kecepatan scroll per notch wheel (px)
  var SPEED = 90;

  // Elemen yang di-intercept
  var SCROLL_SELECTORS = [
    '.sidebar',
    '.sidebar-nav',
    '.content',
    '.main-area',
    '.modal-body',
    '.table-wrap',
    '.table-responsive',
    '[data-smooth-scroll]',
  ];

  function lerp(a, b, t) {
    return a + (b - a) * t;
  }

  function SmoothScroller(el) {
    this.el       = el;
    this.target   = el.scrollTop;
    this.current  = el.scrollTop;
    this.running  = false;
    this._bind();
  }

  SmoothScroller.prototype._bind = function () {
    var self = this;
    this.el.addEventListener('wheel', function (e) {
      // Jangan intercept saat horizontal scroll (shift+wheel)
      if (e.shiftKey) return;

      e.preventDefault();

      // Normalize delta antar browser & OS
      var delta = e.deltaY;
      if (e.deltaMode === 1) delta *= 20;   // Firefox line mode
      if (e.deltaMode === 2) delta *= 400;  // page mode

      // Clamp agar tidak terlalu kencang di trackpad
      delta = Math.max(-SPEED * 3, Math.min(SPEED * 3, delta * (SPEED / 100)));

      var max = self.el.scrollHeight - self.el.clientHeight;
      self.target = Math.max(0, Math.min(max, self.target + delta));

      if (!self.running) self._tick();
    }, { passive: false });
  };

  SmoothScroller.prototype._tick = function () {
    var self = this;
    self.running = true;

    self.current = lerp(self.current, self.target, EASE);

    // Snap ke target jika sudah sangat dekat (< 0.5px)
    if (Math.abs(self.current - self.target) < 0.5) {
      self.current = self.target;
      self.el.scrollTop = self.target;
      self.running = false;
      return;
    }

    self.el.scrollTop = self.current;
    requestAnimationFrame(function () { self._tick(); });
  };

  function init() {
    var seen = new WeakSet();

    function attach(el) {
      if (!el || seen.has(el)) return;
      // Hanya elemen yang bisa di-scroll secara vertikal
      if (el.scrollHeight <= el.clientHeight) return;
      seen.add(el);
      new SmoothScroller(el);
    }

    SCROLL_SELECTORS.forEach(function (sel) {
      document.querySelectorAll(sel).forEach(attach);
    });

    // Observe DOM mutations: attach ke elemen baru (modal, dll)
    if (window.MutationObserver) {
      var obs = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
          m.addedNodes.forEach(function (node) {
            if (node.nodeType !== 1) return;
            SCROLL_SELECTORS.forEach(function (sel) {
              if (node.matches && node.matches(sel)) attach(node);
              node.querySelectorAll && node.querySelectorAll(sel).forEach(attach);
            });
          });
        });
      });
      obs.observe(document.body, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();


/* ============================================================
   APP LOGIC
   ============================================================ */
document.addEventListener('DOMContentLoaded', function () {
  function forceDetailPageScrollOnMobile() {
    if (window.innerWidth > 992) return;
    if (!document.querySelector('.bookd-wrap')) return;

    document.documentElement.style.overflowY = 'auto';
    document.body.style.overflowY = 'auto';
    document.body.style.overflowX = 'hidden';

    ['.app-wrap', '.main-area', '.content'].forEach(function (sel) {
      var el = document.querySelector(sel);
      if (!el) return;
      el.style.height = 'auto';
      el.style.maxHeight = 'none';
      el.style.overflow = 'visible';
    });
  }

  forceDetailPageScrollOnMobile();
  window.addEventListener('resize', forceDetailPageScrollOnMobile);

  /* ── Sidebar toggle (mobile) ── */
  var sidebar = document.querySelector('.sidebar');
  var toggle  = document.querySelector('.sidebar-toggle');
  var overlay = document.querySelector('.sidebar-overlay');

  if (window.innerWidth <= 1024) {
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
  }

  if (toggle && sidebar) {
    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('show');
    });

    if (overlay) {
      overlay.addEventListener('click', function () {
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
      });
    }

    sidebar.querySelectorAll('.nav-link').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.innerWidth <= 1024) {
          sidebar.classList.remove('open');
          if (overlay) overlay.classList.remove('show');
        }
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
      }
    });

    window.addEventListener('resize', function () {
      if (window.innerWidth > 1024) {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('show');
      }
    });
  }

  /* ── Auto-dismiss alerts ── */
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 4500);
  });

  /* ── Table live search ── */
  var liveSearch = document.querySelector('[data-search-table]');
  if (liveSearch) {
    var table = document.getElementById(liveSearch.dataset.searchTable);
    liveSearch.addEventListener('input', function () {
      var q = liveSearch.value.toLowerCase();
      if (table) {
        table.querySelectorAll('tbody tr').forEach(function (r) {
          r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
      }
    });
  }

  /* ── Smooth scroll untuk anchor link dalam halaman ── */
  document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
    anchor.addEventListener('click', function (e) {
      var target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
});

/* ── Modal helpers ── */
function showModal(id)  { var m = document.getElementById(id); if (m) m.style.display = 'flex'; }
function closeModal(id) { var m = document.getElementById(id); if (m) m.style.display = 'none'; }
function showReset(id, nama) {
  var el = document.getElementById('resetId');
  var tl = document.getElementById('resetTitle');
  if (el) el.value = id;
  if (tl) tl.textContent = 'Reset Password: ' + nama;
  showModal('resetModal');
}



