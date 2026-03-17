function scrollToTop(smooth = true) {
  window.scrollTo(smooth ? { top: 0, behavior: 'smooth' } : [0, 0]);
}

const btn = document.querySelector('.fab.scroll-top-btn');

// Auto-hide/show
window.addEventListener('scroll', () => {
  if (btn && window.innerWidth >= 768) { // Desktop only
    if (window.scrollY > 300) {
      btn.classList.add('show');
    } else {
      btn.classList.remove('show');
    }
  }
});

window.addEventListener('resize', () => {
  if (window.innerWidth < 768) {
    btn.classList.remove('show');
  }
});

// Click handler
btn?.addEventListener('click', () => scrollToTop(true));


