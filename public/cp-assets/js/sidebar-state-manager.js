/**
 * Sidebar State Manager Module
 * 
 * PURPOSE: manage sidebar state separately from display logic.
 * This file contains all the "rules" about how the sidebar should behave.
 * 
 * @file sidebar-state-manager.js
 */

// wrap everything in an IIFE (Immediately Invoked Function Expression)
// This creates a "private scope" so our variables don't pollute the global namespace
const SidebarStateManager = (function() {
    'use strict'; // enable strict mode for better error checking

    // ==========================================
    // CONSTANTS - Values that never change
    // ==========================================
    
    /**
     * Storage keys used to save/load preferences
     * define these once to avoid typos elsewhere
     */
    const STORAGE_KEYS = {
        USER_PREFERENCE: 'sidebar-user-preference',   // What size user chose
        USER_TOGGLED: 'sidebar-user-toggled'          // Did user click hamburger?
    };

    /**
     * Sidebar size values
     * sm = small (collapsed), lg = large (expanded)
     */
    const SIDEBAR_SIZES = {
        LARGE: 'lg',
        SMALL: 'sm'
    };

    /**
     * Screen width breakpoints (in pixels)
     * These match your Tailwind/CSS breakpoints
     */
    const BREAKPOINTS = {
        MOBILE: 768,    // Below this = mobile phones
        TABLET: 1025    // Below this but above mobile = tablets
    };

    // ==========================================
    // PRIVATE HELPER FUNCTIONS
    // ==========================================

    /**
     * Get current window width
     * 
     * use clientWidth (not innerWidth) because it excludes scrollbars.
     * This gives more consistent results across browsers.
     * 
     * @returns {number} Window width in pixels
     */
    function getWindowWidth() {
        return document.documentElement.clientWidth;
    }

    // ==========================================
    // PUBLIC API FUNCTIONS
    // ==========================================

    /**
     * Check if user has made an explicit choice
     * 
     * EXAMPLE: User clicked hamburger to collapse sidebar
     * Result: Returns true
     * 
     * @returns {boolean} True if user toggled sidebar
     */
    function hasUserPreference() {
        return sessionStorage.getItem(STORAGE_KEYS.USER_TOGGLED) === 'true';
    }

    /**
     * Get the user's saved preference
     * 
     * IMPORTANT: This only returns a value if user explicitly clicked hamburger.
     * If they haven't, it returns null (meaning "use responsive defaults").
     * 
     * @returns {string|null} 'sm', 'lg', or null
     */
    function getUserPreference() {
        if (!hasUserPreference()) {
            return null; // User hasn't made a choice yet
        }
        return sessionStorage.getItem(STORAGE_KEYS.USER_PREFERENCE);
    }

    /**
     * Save user's preference when they click hamburger
     * 
     * EXAMPLE: User clicks hamburger to collapse on desktop
     * call: saveUserPreference('sm')
     * 
     * @param {string} size - Must be 'sm' or 'lg'
     */
    function saveUserPreference(size) {
        sessionStorage.setItem(STORAGE_KEYS.USER_PREFERENCE, size);
        sessionStorage.setItem(STORAGE_KEYS.USER_TOGGLED, 'true');
    }

    /**
     * Clear user preference (reset to defaults)
     * 
     * call this when user expands sidebar,
     * treating expansion as "I want defaults again"
     */
    function clearUserPreference() {
        sessionStorage.removeItem(STORAGE_KEYS.USER_PREFERENCE);
        sessionStorage.removeItem(STORAGE_KEYS.USER_TOGGLED);
    }

    /**
     * Determine which screen size category we're in
     * 
     * @returns {string} 'mobile', 'tablet', or 'desktop'
     */
    function getBreakpointCategory() {
        const width = getWindowWidth();
        
        if (width < BREAKPOINTS.MOBILE) {
            return 'mobile';    // < 768px
        }
        if (width < BREAKPOINTS.TABLET) {
            return 'tablet';    // 768px - 1024px
        }
        return 'desktop';       // >= 1025px
    }

    /**
     * THE MAGIC FUNCTION: Calculate correct sidebar size
     * 
     * This is where fix your bug. The logic is:
     * 1. Mobile ALWAYS shows 'lg' (full overlay menu)
     * 2. Desktop/tablet checks if user has a preference
     * 3. If no preference, use responsive defaults
     * 
     * @returns {string} 'sm' or 'lg'
     */
    function calculateSidebarSize() {
        const category = getBreakpointCategory();
        const userPreference = getUserPreference();

        // RULE 1: Mobile always uses large (overlay mode)
        if (category === 'mobile') {
            return SIDEBAR_SIZES.LARGE;
        }

        // RULE 2: If user clicked hamburger, respect their choice
        if (userPreference) {
            return userPreference;
        }

        // RULE 3: Responsive defaults when no user preference
        // Desktop = expanded, Tablet = collapsed
        return category === 'desktop' 
            ? SIDEBAR_SIZES.LARGE 
            : SIDEBAR_SIZES.SMALL;
    }

    // ==========================================
    // RETURN PUBLIC API
    // ==========================================
    
    // only expose functions want other code to use
    // This is called the "Revealing Module Pattern"
    return {
        // Export constants so other code can use them
        SIDEBAR_SIZES: SIDEBAR_SIZES,
        BREAKPOINTS: BREAKPOINTS,
        
        // Export functions
        hasUserPreference: hasUserPreference,
        getUserPreference: getUserPreference,
        saveUserPreference: saveUserPreference,
        clearUserPreference: clearUserPreference,
        getBreakpointCategory: getBreakpointCategory,
        calculateSidebarSize: calculateSidebarSize,
        getWindowWidth: getWindowWidth
    };
})();

// make it available globally so other scripts can use it
// In a module system (like webpack), you'd use export/import instead
