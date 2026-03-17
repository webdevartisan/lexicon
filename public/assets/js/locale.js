(function() {
  var supported = (window.AppLocales && Array.isArray(window.AppLocales.supported))
    ? window.AppLocales.supported.map(function(x){ return String(x).toLowerCase(); })
    : ['en']; // fallback

  var defaultLocale = (window.AppLocales && window.AppLocales.default)
    ? String(window.AppLocales.default).toLowerCase()
    : (supported[0] || 'en');

  function getParts() {
    var loc = window.location;
    var path = loc.pathname || '/';
    var parts = path.split('/').filter(Boolean);
    return { parts: parts, query: loc.search || '' };
  }

  function currentLocale() {
    var p = getParts().parts;
    return (p.length && supported.indexOf(p[0].toLowerCase()) !== -1) ? p[0].toLowerCase() : null;
  }

  function rewriteToLocale(locale) {
    var pieces = getParts();
    var parts = pieces.parts.slice();
    var qs = pieces.query;

    // Remove existing supported prefix, if any
    if (parts.length && supported.indexOf(parts[0].toLowerCase()) !== -1) {
      parts.shift();
    }
    var newPath = '/' + locale + (parts.length ? '/' + parts.join('/') : '');
    window.location.assign(newPath + (qs || ''));
  }

  var toggle = document.getElementById('langToggle');
  var menu = document.getElementById('langMenu');
  var currentLangEl = document.getElementById('currentLang');

  // Reflect current locale in button and list
  var cur = currentLocale() || defaultLocale;
  currentLangEl.textContent = cur.toUpperCase();
  Array.prototype.forEach.call(menu.querySelectorAll('li[data-lang]'), function(li) {
    li.setAttribute('aria-selected', (li.getAttribute('data-lang') || '').toLowerCase() === cur ? 'true' : 'false');
  });

  // Size menu to button width for tidy edges
  function sizeMenuToButton() {
    var rect = toggle.getBoundingClientRect();
    menu.style.minWidth = Math.ceil(rect.width) + 'px';
  }

  // Position menu under the button, but flip above if near bottom of the viewport.
  function positionMenu() {
    var rect = toggle.getBoundingClientRect(); // viewport-relative geometry
    var gap = 8; // px space between button and menu

    // Prefer VisualViewport height on mobile (URL bar / keyboard can affect visible area)
    var viewportH = (window.visualViewport && window.visualViewport.height)
      ? window.visualViewport.height
      : window.innerHeight;

    // Measure menu height even though it's currently display:none (because .show isn't applied yet)
    var prevDisplay = menu.style.display;
    var prevVisibility = menu.style.visibility;
    var prevPointerEvents = menu.style.pointerEvents;

    menu.style.display = 'block';
    menu.style.visibility = 'hidden';
    menu.style.pointerEvents = 'none';

    var menuHeight = Math.ceil(menu.getBoundingClientRect().height);

    // Restore (so your existing show/hide logic remains the single source of truth)
    menu.style.display = prevDisplay;
    menu.style.visibility = prevVisibility;
    menu.style.pointerEvents = prevPointerEvents;

    var spaceBelow = viewportH - rect.bottom;
    var spaceAbove = rect.top;

    // Always keep your horizontal anchoring
    menu.style.right = 'auto';
    menu.style.left = '50%';
    menu.style.transform = 'translateX(-50%)';


    // Reset both so we don't accumulate stale state across opens
    menu.style.top = '';
    menu.style.bottom = '';
    menu.style.maxHeight = '';
    menu.style.overflowY = '';

    // Flip up if there's not enough room below and above is better
    var openUp = (spaceBelow < (menuHeight + gap)) && (spaceAbove > spaceBelow);

    if (openUp) {
      // Open above the button
      menu.style.top = 'auto';
      menu.style.bottom = (Math.ceil(rect.height) + gap) + 'px';

      // If still too tall, constrain to available space above
      var maxUp = Math.floor(spaceAbove - gap);
      if (maxUp > 0 && menuHeight > maxUp) {
        menu.style.maxHeight = maxUp + 'px';
        menu.style.overflowY = 'auto';
      }
    } else {
      // Open below the button (your current behavior)
      menu.style.bottom = 'auto';
      menu.style.top = (Math.ceil(rect.height) + gap) + 'px';

      // If too tall below, constrain to available space below
      var maxDown = Math.floor(spaceBelow - gap);
      if (maxDown > 0 && menuHeight > maxDown) {
        menu.style.maxHeight = maxDown + 'px';
        menu.style.overflowY = 'auto';
      }
    }
  }


  sizeMenuToButton();

  // Open/close behavior
  toggle.addEventListener('click', function(e) {
    e.preventDefault();
    var open = menu.classList.contains('show');
    if (!open) {
      sizeMenuToButton();
      positionMenu();
    }
    menu.classList.toggle('show', !open);
    toggle.setAttribute('aria-expanded', String(!open));
  });

  // Close on outside click and Escape
  document.addEventListener('click', function(e) {
    if (!menu.classList.contains('show')) return;
    if (!e.target.closest('.lang-switcher')) {
      menu.classList.remove('show');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && menu.classList.contains('show')) {
      menu.classList.remove('show');
      toggle.setAttribute('aria-expanded', 'false');
    }
  });

  // Recompute on resize and after webfonts load (prevents vertical drift)
  window.addEventListener('resize', function() {
    sizeMenuToButton();
    if (menu.classList.contains('show')) positionMenu();
  });
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(function() {
      sizeMenuToButton();
      if (menu.classList.contains('show')) positionMenu();
    });
  }

  // Handle selection
  menu.addEventListener('click', function(e) {
    var li = e.target.closest('li[data-lang]');
    if (!li) return;
    var locale = (li.getAttribute('data-lang') || '').toLowerCase();
    if (!locale || supported.indexOf(locale) === -1) return;
    rewriteToLocale(locale);
  });
})();
