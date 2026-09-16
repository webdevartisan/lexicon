(function () {
    var versionEl = document.querySelector('script[data-consent-version]');
    var banner = document.getElementById('consentBanner');
    var modal = document.getElementById('consentModal');
    var form = document.getElementById('consentForm');

    if (!versionEl || !banner || !modal || !form) return;

    var siteVersion = parseInt(versionEl.getAttribute('data-consent-version'), 10);

    function readConsentCookie() {
        var prefix = 'app_consent=';
        var cookies = document.cookie.split('; ');

        for (var i = 0; i < cookies.length; i++) {
            if (cookies[i].indexOf(prefix) === 0) {
                var value = decodeURIComponent(cookies[i].slice(prefix.length));

                return value ? JSON.parse(atob(value.split('.')[0])) : null;
            }
        }
        return null;
    }


    // CSRF token management
    var cachedToken = null;
    
    async function fetchCsrfToken() {
        if (cachedToken) return cachedToken;

        var response = await fetch('/csrf-token', {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });

        if (!response.ok) throw new Error('Consent: CSRF token request failed with ' + response.status);

        cachedToken = (await response.json()).token;

        return cachedToken;
    }
    
    function hasDecision(state) {
        return !!(state && state.c && typeof state.ts === 'number' && state.v === siteVersion);
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

        var response = await fetch('/consent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-Token': token
            },
            credentials: 'same-origin',
            body: new URLSearchParams(payload).toString()
        });

        if (!response.ok) throw new Error('Consent: save failed with ' + response.status);

        return response.json();
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
    
    var state = readConsentCookie();
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
            if (!res.ok) throw new Error('Consent: server rejected ' + action);
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
            if (!res.ok) throw new Error('Consent: server rejected the saved options');
            closeModal();
            setBannerVisible(false);
            reloadAfterFade();
        });
    });
    
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
    });
})();
