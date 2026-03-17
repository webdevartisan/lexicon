(() => {
  const offset = 10;
  let activeBtn = null;
  let activeTip = null;

  function positionTooltip(btn, tip) {
    const b = btn.getBoundingClientRect();

    tip.hidden = false; // need it visible to measure it correctly.
    const t = tip.getBoundingClientRect();

    const top = window.scrollY + b.top - t.height - offset;
    const left = window.scrollX + b.left;

    tip.dataset.placement = 'top';
    tip.style.top = `${Math.max(8, top)}px`;

    const maxLeft = window.scrollX + window.innerWidth - t.width - 8;
    tip.style.left = `${Math.max(8, Math.min(left, maxLeft))}px`;
  }

  function getTip(btn) {
    const id = btn.getAttribute('data-tooltip');
    if (!id) return null;
    return document.getElementById(id);
  }

  function openTip(btn) {
    const tip = getTip(btn);
    if (!tip) return;

    activeBtn = btn;
    activeTip = tip;

    tip.hidden = false;
    positionTooltip(btn, tip);
  }

  function closeTip(btn) {
    const tip = getTip(btn);
    if (!tip) return;

    tip.hidden = true;

    if (activeBtn === btn) {
      activeBtn = null;
      activeTip = null;
    }
  }

  document.querySelectorAll('[data-tooltip]').forEach((btn) => {
    btn.addEventListener('mouseenter', () => openTip(btn));
    btn.addEventListener('mouseleave', () => closeTip(btn));
    btn.addEventListener('focus', () => openTip(btn));
    btn.addEventListener('blur', () => closeTip(btn));

    btn.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeTip(btn);
    });
  });

  // Option A (recommended for simple hints): close tooltips on scroll/resize so they never “stick”.
  window.addEventListener('scroll', () => {
    if (activeBtn) closeTip(activeBtn);
  }, { passive: true });

  window.addEventListener('resize', () => {
    if (activeBtn) closeTip(activeBtn);
  });

  // Option B (alternative): comment out Option A and use this instead to only reposition if open.
  // window.addEventListener('scroll', () => {
  //   if (activeBtn && activeTip && !activeTip.hidden) positionTooltip(activeBtn, activeTip);
  // }, { passive: true });
  //
  // window.addEventListener('resize', () => {
  //   if (activeBtn && activeTip && !activeTip.hidden) positionTooltip(activeBtn, activeTip);
  // });
})();