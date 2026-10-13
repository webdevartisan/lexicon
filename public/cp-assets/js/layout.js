/**
 * Initial Layout Configuration
 * 
 * RUNS: In <head> before body loads (prevents flash of wrong state)
 * PURPOSE: Load saved preferences and apply them immediately
 * 
 * SECURITY: We validate all values to prevent XSS via localStorage tampering
 * 
 * @file layout.js
 */
(function () {
    'use strict';

    const THEME_KEY = 'theme-pref';

    // ==========================================
    // VALIDATION ALLOWLISTS
    // ==========================================
    
    // We only allow these values (security best practice)
    const ALLOWED_VALUES = {
        preference: new Set(['light', 'dark', 'auto']),
        sidebarSize: new Set(['lg', 'sm']),
        layout: new Set(['vertical'])
    };

    // ==========================================
    // HELPER FUNCTIONS
    // ==========================================

    /**
     * Safely read from storage
     * 
     * We check sessionStorage first (temporary), then localStorage (permanent)
     * 
     * @param {string} key - Storage key
     * @returns {string|null} Value or null
     */
    function readStorage(key) {
        try {
            return sessionStorage.getItem(key) || localStorage.getItem(key);
        } catch (error) {
            // We handle cases where storage is disabled (private browsing)
            console.warn('Storage access denied:', error);
            return null;
        }
    }

    /**
     * Safely set attribute with validation
     * 
     * SECURITY: We validate against allowlist before applying to DOM
     * This prevents malicious values from storage
     * 
     * @param {string} attribute - Attribute name
     * @param {string|null} value - Value to set
     * @param {Set} allowedValues - Allowed values
     * @returns {boolean} True if value was set
     */
    function setValidatedAttribute(attribute, value, allowedValues) {
        if (value && allowedValues.has(value)) {
            document.documentElement.setAttribute(attribute, value);
            return true;
        }
        return false;
    }

    /**
     * Get current window width
     * 
     * We need this to determine if we're on mobile
     * 
     * @returns {number} Window width in pixels
     */
    function getWindowWidth() {
        return document.documentElement.clientWidth;
    }

    // ==========================================
    // APPLY SETTINGS
    // ==========================================

    // We permanently enforce vertical layout
    document.documentElement.setAttribute('data-layout', 'vertical');

    // Resolve the saved preference (light / dark / auto) into a concrete mode and apply it
    const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');

    function getPreference() {
        try {
            const saved = localStorage.getItem(THEME_KEY);
            return ALLOWED_VALUES.preference.has(saved) ? saved : 'auto';
        } catch (error) {
            return 'auto';
        }
    }

    function applyMode() {
        const pref = getPreference();
        const mode = pref === 'auto' ? (darkQuery.matches ? 'dark' : 'light') : pref;
        const root = document.documentElement;
        root.setAttribute('data-mode', mode);
        // We keep sidebar and topbar colors synced with main theme
        root.setAttribute('data-sidebar', mode);
        root.setAttribute('data-topbar', mode);
        root.setAttribute('data-theme-pref', pref);
    }

    function setPreference(pref) {
        if (!ALLOWED_VALUES.preference.has(pref)) return;
        try {
            localStorage.setItem(THEME_KEY, pref);
        } catch (error) {
            // Private browsing: the choice still applies for this page view
        }
        applyMode();
    }

    applyMode();

    // Auto mode follows the device live, e.g. when the OS switches to dark at sunset
    darkQuery.addEventListener('change', applyMode);

    // Other open dashboard tabs pick up a change made in this one
    window.addEventListener('storage', function (event) {
        if (event.key === THEME_KEY) applyMode();
    });

    window.lexiconTheme = { getPreference: getPreference, setPreference: setPreference };

    // ==========================================
    // SIDEBAR SIZE - THE IMPORTANT PART
    // ==========================================

    // Check if user has explicitly toggled sidebar
    const hasUserToggled = readStorage('sidebar-user-toggled') === 'true';
    
    if (hasUserToggled) {
        const sidebarSize = readStorage('sidebar-user-preference');
        const windowWidth = getWindowWidth();

        // THE FIX: Only apply stored size if NOT on mobile
        // Mobile will be handled by windowResizeHover() which runs immediately after DOM loads
        if (windowWidth >= 768) {
            setValidatedAttribute('data-sidebar-size', sidebarSize, ALLOWED_VALUES.sidebarSize);
        }
        // If we ARE on mobile, we don't set anything here.
        // The resize handler will set it to 'lg' correctly.
    }
})();
