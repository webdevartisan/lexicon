(function () {
    var stateEl = document.getElementById('consent-state');
    var banner = document.getElementById('consentBanner');
    var modal = document.getElementById('consentModal');
    var form = document.getElementById('consentForm');
    
    if (!stateEl || !banner || !modal || !form) return;
    
    // Read consent cookie directly (works with cached pages)
    function readConsentCookie() {
        var cookieName = 'app_consent';
        var cookies = document.cookie.split('; ');
        
        for (var i = 0; i < cookies.length; i++) {
            var parts = cookies[i].split('=');
            if (parts[0] === cookieName && parts[1]) {
                try {
                    // Cookie format: "base64(json).hmac_signature"
                    // Use only the base64 part; the signature is verified server‑side.
                    var cookieValue = parts[1];
                    var base64Part = cookieValue.split('.')[0]; // Get part before "."
                    
                    if (!base64Part) {
                        console.warn('Invalid consent cookie format');
                        return null;
                    }
                    
                    // Decode base64 to get JSON string
                    var jsonString = atob(base64Part);
                    
                    // Parse JSON to get consent object
                    var payload = JSON.parse(jsonString);
                    
                    return payload;
                } catch (e) {
                    console.warn('Failed to parse consent cookie:', e);
                    return null;
                }
            }
        }
        return null;
    }

    
    // CSRF token management
    var cachedToken = null;
    
    async function fetchCsrfToken() {
        if (cachedToken) return cachedToken;
        
        try {
            var response = await fetch('/csrf-token', {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });
            
            if (!response.ok) throw new Error('Failed to fetch CSRF token');
            
            var data = await response.json();
            cachedToken = data.token;
            return cachedToken;
        } catch (e) {
            console.error('CSRF token fetch failed:', e);
            return '';
        }
    }
    
    function readState() {
        // Read from cookie FIRST (client-side, not cached)
        var cookieState = readConsentCookie();
        if (cookieState) return cookieState;
        
        // Fallback to server-rendered state (for first visit before cache)
        try { return JSON.parse(stateEl.textContent || 'null'); }
        catch (e) { return null; }
    }
    
    function hasDecision(state) {
        return !!(state && state.c && typeof state.ts === 'number');
    }
    
    function setBannerVisible(isVisible) {
        banner.hidden = !isVisible;
        document.documentElement.classList.toggle('consent-banner-visible', isVisible);
    }
    
    function openModal() {
        modal.hidden = false;
        document.documentElement.classList.add('consent-lock');
    }
    
    function closeModal() {
        modal.hidden = true;
        document.documentElement.classList.remove('consent-lock');
    }
    
    async function postConsent(payload) {
        var token = await fetchCsrfToken();
        payload._token = token;
        
        return fetch('/consent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-Token': token
            },
            credentials: 'same-origin',
            body: new URLSearchParams(payload).toString()
        }).then(function (r) { return r.json(); });
    }
    
    function syncForm(state) {
        if (!state || !state.c) return;
        form.elements.preferences.checked = !!state.c.preferences;
        form.elements.analytics.checked = !!state.c.analytics;
        form.elements.marketing.checked = !!state.c.marketing;
    }
    
    function reloadAfterFade() {
        window.setTimeout(function () {
            window.location.reload();
        }, 200);
    }
    
    // Initial state from cookie (not cached HTML)
    var state = readState();
    if (!hasDecision(state)) {
        setBannerVisible(true);
    } else {
        syncForm(state);
        setBannerVisible(false);
    }
    
    document.addEventListener('click', function (e) {
        var openBtn = e.target.closest('[data-consent-open]');
        if (openBtn) return openModal();
        
        var closeBtn = e.target.closest('[data-consent-close]');
        if (closeBtn) return closeModal();
        
        var actionBtn = e.target.closest('[data-consent-action]');
        if (!actionBtn) return;
        
        var action = actionBtn.getAttribute('data-consent-action');
        if (!action) return;
        
        postConsent({ action: action }).then(function (res) {
            if (!res || !res.ok) return;
            setBannerVisible(false);
            reloadAfterFade();
        });
    });
    
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        
        postConsent({
            action: 'save',
            preferences: form.elements.preferences.checked ? '1' : '',
            analytics: form.elements.analytics.checked ? '1' : '',
            marketing: form.elements.marketing.checked ? '1' : ''
        }).then(function (res) {
            if (!res || !res.ok) return;
            closeModal();
            setBannerVisible(false);
            reloadAfterFade();
        });
    });
    
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
