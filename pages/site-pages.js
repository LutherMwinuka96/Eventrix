(function () {
  const nav = document.getElementById('spNav');
  const menuToggle = document.getElementById('spMenuToggle');
  const mobileMenu = document.getElementById('spMobileMenu');

  if (nav) {
    window.addEventListener('scroll', () => {
      nav.classList.toggle('scrolled', window.scrollY > 20);
    });
  }

  if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', () => {
      const open = mobileMenu.classList.toggle('open');
      menuToggle.innerHTML = open
        ? '<i class="fa-solid fa-xmark"></i>'
        : '<i class="fa-solid fa-bars"></i>';
    });
    mobileMenu.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        mobileMenu.classList.remove('open');
        menuToggle.innerHTML = '<i class="fa-solid fa-bars"></i>';
      });
    });
  }

  const page = document.body.dataset.page;
  if (page) {
    document.querySelectorAll(`[data-nav="${page}"]`).forEach(el => el.classList.add('active'));
  }

  const revealObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

  document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
})();

function spToast(message, type = 'info') {
  let wrap = document.querySelector('.sp-toast-wrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.className = 'sp-toast-wrap';
    document.body.appendChild(wrap);
  }
  const toast = document.createElement('div');
  toast.className = `sp-toast ${type}`;
  toast.textContent = message;
  wrap.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transition = 'opacity 0.3s';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

function initFaqAccordion(containerId, searchId, tabsSelector) {
  const container = document.getElementById(containerId);
  const search = document.getElementById(searchId);
  if (!container) return;

  const items = [...container.querySelectorAll('.sp-accordion-item')];
  let activeCategory = 'all';

  function filterItems() {
    const q = (search?.value || '').trim().toLowerCase();
    items.forEach(item => {
      const cat = item.dataset.category || 'all';
      const text = (item.textContent || '').toLowerCase();
      const matchCat = activeCategory === 'all' || cat === activeCategory;
      const matchSearch = !q || text.includes(q);
      item.style.display = matchCat && matchSearch ? '' : 'none';
    });
  }

  container.addEventListener('click', e => {
    const btn = e.target.closest('.sp-accordion-q');
    if (!btn) return;
    const item = btn.closest('.sp-accordion-item');
    const wasOpen = item.classList.contains('open');
    container.querySelectorAll('.sp-accordion-item.open').forEach(el => el.classList.remove('open'));
    if (!wasOpen) item.classList.add('open');
  });

  if (search) search.addEventListener('input', filterItems);

  document.querySelectorAll(tabsSelector).forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll(tabsSelector).forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeCategory = tab.dataset.category || 'all';
      filterItems();
    });
  });
}

function animateCounters(selector) {
  document.querySelectorAll(selector).forEach(el => {
    const target = parseInt(el.dataset.count, 10);
    if (Number.isNaN(target)) return;
    const suffix = el.dataset.suffix || '';
    const duration = 1200;
    const start = performance.now();
    function tick(now) {
      const progress = Math.min((now - start) / duration, 1);
      const value = Math.floor(target * (1 - Math.pow(1 - progress, 3)));
      el.textContent = value.toLocaleString() + suffix;
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  });
}
