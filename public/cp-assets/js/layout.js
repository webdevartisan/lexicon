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

    // ==========================================
    // VALIDATION ALLOWLISTS
    // ==========================================
    
    // We only allow these values (security best practice)
    const ALLOWED_VALUES = {
        mode: new Set(['light', 'dark']),
        dir: new Set(['ltr', 'rtl']),
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

    // Load and apply theme mode
    const mode = readStorage('data-mode');
    if (setValidatedAttribute('data-mode', mode, ALLOWED_VALUES.mode)) {
        // We keep sidebar and topbar colors synced with main theme
        document.documentElement.setAttribute('data-sidebar', mode);
        document.documentElement.setAttribute('data-topbar', mode);
    }

    // Load and apply text direction (for RTL languages like Arabic)
    const dir = readStorage('dir');
    setValidatedAttribute('dir', dir, ALLOWED_VALUES.dir);

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
