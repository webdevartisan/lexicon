(function () {
  'use strict';
  
  // Prevent multiple initializations
  if (window.AlertComponent) return;
  window.AlertComponent = true;
  
  const alerts = new Map();
  
  function initializeAlert(box) {
    if (alerts.has(box)) return;
    
    const autoClose = parseInt(box.dataset.autoClose) || 0;
    const progressBar = box.querySelector('[data-progress-bar]');
    
    if (autoClose > 0 && progressBar) {
      const startTime = Date.now();
      const updateInterval = 50;
      
      const timer = setInterval(() => {
        const elapsed = Date.now() - startTime;
        const remaining = Math.max(0, autoClose - elapsed);
        const percentage = (remaining / autoClose) * 100;
        
        progressBar.style.width = percentage + '%';
        
        if (remaining === 0) {
          clearInterval(timer);
          closeAlert(box);
        }
      }, updateInterval);
      
      alerts.set(box, { timer });
      
      // Pause on hover
      box.addEventListener('mouseenter', () => {
        if (alerts.has(box)) {
          clearInterval(alerts.get(box).timer);
        }
      });
      
      box.addEventListener('mouseleave', () => {
        if (alerts.has(box)) {
          const currentWidth = parseFloat(progressBar.style.width) || 100;
          const remainingTime = (currentWidth / 100) * autoClose;
          const newStartTime = Date.now();
          
          const newTimer = setInterval(() => {
            const elapsed = Date.now() - newStartTime;
            const remaining = Math.max(0, remainingTime - elapsed);
            const percentage = (remaining / remainingTime) * 100;
            
            progressBar.style.width = percentage + '%';
            
            if (remaining === 0) {
              clearInterval(newTimer);
              closeAlert(box);
            }
          }, updateInterval);
          
          alerts.set(box, { timer: newTimer });
        }
      });
    }
  }
  
  function closeAlert(box) {
    box.style.opacity = '0';
    box.style.transform = 'translateX(100%)';
    
    setTimeout(() => {
      box.remove();
      alerts.delete(box);
    }, 300);
  }
  
  function onCloseClick(e) {
    const btn = e.target.closest('[data-close]');
    if (!btn) return;
    
    const box = btn.closest('[data-closable]');
    if (!box) return;
    
    if (alerts.has(box)) {
      clearInterval(alerts.get(box).timer);
    }
    
    closeAlert(box);
  }
  
  // Initialize existing alerts
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-closable]').forEach(initializeAlert);
  });
  
  // Watch for dynamically added alerts
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      mutation.addedNodes.forEach((node) => {
        
        if (node.nodeType === 1 && node.hasAttribute('data-closable')) {
            console.log('tset');
          initializeAlert(node);
        }
      });
    });
  });
  
  observer.observe(document.body, { childList: true, subtree: true });
  
  document.addEventListener('click', onCloseClick);
})();
