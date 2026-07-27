// const { document } = require("postcss");

/**
 * Global State Variables
 * 
 * We store these at module level to maintain state across function calls.
 */
var navbarMenuHTML = document.querySelector(".app-menu")?.innerHTML || '';
let scrollbarElement = null;

/**
 * Scrollbar Logic State Tracker
 * 
 * We prevent multiple simultaneous innerHTML replacement operations
 * by tracking pending timeouts. This eliminates duplicate icon destruction.
 */
let scrollbarResetTimeout = null;

/**
 * Remove active state from dropdown menus
 * 
 * @param {HTMLElement} content - Content element to preserve
 */
function removeActiveMenu(content) {
    document.querySelector("#scrollbar")?.querySelectorAll('.dropdown-button.show').forEach((aTag) => {
        if (!Object.is(aTag?.nextElementSibling, content)) {
            aTag?.classList.remove('show');
            aTag?.nextElementSibling?.classList.add('opacity-100');
            aTag?.nextElementSibling?.classList.add('hidden');
        }
    });
}

/**
 * Update parent element active states
 * 
 * We recursively update parent dropdown states when a child is active.
 * 
 * @param {HTMLElement} button - Button element
 */
function updateParentActive(button) {
    if (button?.closest(".dropdown-content")) {
        button.closest(".dropdown-content").classList.remove("hidden");
        button.closest(".dropdown-content").classList.remove('opacity-100');
        button.closest(".dropdown-content").previousElementSibling?.classList.add("show");
        updateParentActive(button.closest(".dropdown-content").previousElementSibling);
    }
}

/**
 * Handle hamburger menu click
 * 
 * WHAT THIS DOES:
 * - Desktop/Tablet: Toggle between sm/lg
 * - Mobile: Show overlay menu
 * 
 * We call this when user clicks the hamburger icon in header.
 */
function toggleHamburgerMenu() {
    // We figure out what screen size we're on
    const category = SidebarStateManager.getBreakpointCategory();
    
    // We get current sidebar size
    const currentSize = document.documentElement.getAttribute('data-sidebar-size');
    
    // We find elements we need to manipulate
    const hamburgerIcon = document.querySelector('.hamburger-icon');
    const verticalOverlay = document.getElementById('sidebar-overlay');
    const appMenu = document.querySelector('.app-menu');
    
    // We handle MOBILE differently (uses overlay system)
    if (category === 'mobile') {
        handleMobileMenuOpen(verticalOverlay, appMenu);
        return; // Stop here, don't continue to desktop logic
    }
    
    // We toggle hamburger icon animation (for desktop/tablet only)
    if (hamburgerIcon) {
        hamburgerIcon.classList.toggle('open');
    }
    
    // We calculate NEW size (opposite of current)
    const newSize = currentSize === SidebarStateManager.SIDEBAR_SIZES.SMALL
        ? SidebarStateManager.SIDEBAR_SIZES.LARGE
        : SidebarStateManager.SIDEBAR_SIZES.SMALL;
    
    // We apply the new size to HTML
    document.documentElement.setAttribute('data-sidebar-size', newSize);
    
    // We save or clear preference based on new size
    if (newSize === SidebarStateManager.SIDEBAR_SIZES.SMALL) {
        // User collapsed - save their preference
        SidebarStateManager.saveUserPreference(newSize);
    } else {
        // User expanded - clear preference (back to defaults)
        SidebarStateManager.clearUserPreference();
    }
    
    // We update scrollbar if needed
    applyScrollbarLogic();
}

/**
 * Handle mobile menu opening
 * 
 * WHAT THIS DOES:
 * Mobile uses an overlay system (menu slides over content)
 * Desktop uses inline collapse (sidebar shrinks)
 * 
 * @param {HTMLElement} overlay - The dark background overlay
 * @param {HTMLElement} menu - The sidebar menu
 */
function handleMobileMenuOpen(overlay, menu) {
    // We do safety check to make sure elements exist
    if (!overlay || !menu) {
        console.warn('Sidebar overlay or menu not found');
        return;
    }
    
    // We prevent page scrolling when menu is open
    document.body.classList.add('overflow-hidden');
    
    // We show overlay and menu if they're hidden
    if (overlay.classList.contains('hidden')) {
        overlay.classList.remove('hidden');
        menu.classList.remove('hidden');
    }
    
    // Mobile always uses large size for readability
    document.documentElement.setAttribute(
        'data-sidebar-size',
        SidebarStateManager.SIDEBAR_SIZES.LARGE
    );
    
    // We update scrollbar
    applyScrollbarLogic();
}

/**
 * Set up overlay click handler
 * 
 * We close the sidebar when user clicks the overlay background.
 */
function isLoadBodyElement() {
    var windowSize = document.documentElement.clientWidth;
    var verticalOverlay = document.getElementById("sidebar-overlay");
    
    if (verticalOverlay) {
        verticalOverlay.addEventListener("click", function () {
            if (!verticalOverlay.classList.contains("hidden")) {
                if (windowSize <= 768) {
                    document.querySelector(".app-menu").classList.add("hidden");
                    document.body.classList.remove("overflow-hidden");
                } else {
                    document.documentElement.getAttribute("data-sidebar-size") == "sm" ?
                    document.documentElement.setAttribute("data-sidebar-size", "lg") :
                    document.documentElement.setAttribute("data-sidebar-size", "sm");
                }
                verticalOverlay.classList.add("hidden");
            }
        });
    }
}

/**
 * Handle window resize events
 * 
 * WHAT THIS DOES:
 * When user resizes browser window, we recalculate sidebar size.
 * The magic is in SidebarStateManager.calculateSidebarSize() which
 * knows the difference between mobile (always lg) and desktop (use preference).
 */
function windowResizeHover() {
    // We get screen category and calculated size
    const category = SidebarStateManager.getBreakpointCategory();
    const calculatedSize = SidebarStateManager.calculateSidebarSize();
    
    // We find elements we need
    const hamburgerIcon = document.querySelector('.hamburger-icon');
    const appMenu = document.querySelector('.app-menu');
    
    // We clean up mobile-specific classes when NOT on mobile
    if (category !== 'mobile') {
        document.body.classList.remove('overflow-hidden');
        if (appMenu) {
            appMenu.classList.add('hidden');
        }
    }
    
    // We apply the calculated size
    document.documentElement.setAttribute('data-sidebar-size', calculatedSize);
    
    // We update hamburger icon visual state
    if (hamburgerIcon) {
        if (calculatedSize === SidebarStateManager.SIDEBAR_SIZES.SMALL) {
            hamburgerIcon.classList.add('open');
        } else {
            hamburgerIcon.classList.remove('open');
        }
    }
    
    // We update scrollbar - but ONLY after debounced resize settles
    applyScrollbarLogic();
}

/**
 * Read preference from storage
 * 
 * We prefer localStorage because it survives browser restarts,
 * but keep sessionStorage for same-tab reloads.
 * 
 * @param {string} key - Storage key
 * @returns {string|null} Stored value
 */
function readPref(key) {
    return localStorage.getItem(key) ?? sessionStorage.getItem(key);
}

/**
 * Set attribute and persist to storage
 * 
 * We update DOM attribute and save to both storage types
 * for maximum persistence across sessions.
 * 
 * @param {string} attr - Attribute name
 * @param {string} val - Attribute value
 */
function setAttrItemAndTag(attr, val) {
    document.documentElement.setAttribute(attr, val);
    sessionStorage.setItem(attr, val);
    localStorage.setItem(attr, val);
}

/**
 * Initialize light/dark mode toggle
 * 
 * We set up the theme switcher button to toggle between light and dark modes.
 */
function lightDarkMode() {
    const lightDarkBtn = document.getElementById("light-dark-mode");
    if (!lightDarkBtn) return;
    
    /**
     * Seed initial preferences from server-rendered HTML
     * 
     * We read the initial mode from HTML attributes so the first click
     * toggles correctly without requiring a default assumption.
     */
    const seedFromHtml = () => {
        const htmlMode = document.documentElement.getAttribute("data-mode") || "light";
        if (!readPref("data-mode")) {
            // We seed initial preferences from server-rendered HTML
            setAttrItemAndTag("data-mode", htmlMode);
            setAttrItemAndTag("data-sidebar", htmlMode);
            setAttrItemAndTag("data-topbar", htmlMode);
        }
    };
    
    /**
     * Get current mode from storage or DOM
     * 
     * @returns {string} Current mode ('light' or 'dark')
     */
    const getCurrentMode = () => {
        return (
            readPref("data-mode") ||
            document.documentElement.getAttribute("data-mode") ||
            "light"
        );
    };
    
    seedFromHtml();
    
    lightDarkBtn.addEventListener("click", function () {
        const current = getCurrentMode();
        const next = current === "light" ? "dark" : "light";
        setAttrItemAndTag("data-mode", next);
        setAttrItemAndTag("data-sidebar", next);
        setAttrItemAndTag("data-topbar", next);
    });
}

/**
 * Initialize active menu highlighting
 * 
 * We highlight the current page's menu item based on URL path.
 * This provides visual feedback about current location in the app.
 */
function initActiveMenu() {
    var currentPath = location.pathname == "/" ? "index.html" : location.pathname.substring(1);
    currentPath = currentPath.substring(currentPath.lastIndexOf("/") + 1);
    
    if (currentPath) {
        // We find the navbar-nav element
        var a = document.getElementById("navbar-nav")?.querySelector('[href="' + currentPath + '"]');
        
        if (a) {
            a.classList.add("active");
            var parentCollapseDiv = a.parentElement.parentElement.parentElement;
            
            if (parentCollapseDiv) {
                if (document.documentElement.getAttribute("data-layout") == "vertical") {
                    parentCollapseDiv.classList.remove("hidden");
                }
                parentCollapseDiv.classList.add("active");
                parentCollapseDiv.previousElementSibling?.classList.add("active");
                parentCollapseDiv.previousElementSibling?.classList.add("show");
                
                if (document.documentElement.getAttribute("data-layout") == "vertical") {
                    parentCollapseDiv.previousElementSibling?.parentElement.parentElement.parentElement?.classList.remove("hidden");
                }
                parentCollapseDiv.previousElementSibling?.parentElement.parentElement.parentElement?.previousElementSibling?.classList.add("active");
            }
            
            initMenuItemScroll();
        }
    }
}

/**
 * Apply scrollbar logic based on sidebar size
 * 
 * OPTIMIZED VERSION:
 * We initialize SimpleBar for smooth scrolling in expanded mode.
 * We avoid innerHTML replacement entirely to prevent icon destruction.
 * 
 * SECURITY: We don't use innerHTML replacement which could introduce XSS risks.
 * PERFORMANCE: We eliminate unnecessary DOM destruction/recreation.
 */
function applyScrollbarLogic() {
    // We only apply scrollbar logic to vertical layouts
    if (document.documentElement.getAttribute("data-layout") !== "vertical") {
        return;
    }
    
    const currentSize = document.documentElement.getAttribute("data-sidebar-size");
    const scrollbarContainer = document.getElementById("scrollbar");
    
    if (!scrollbarContainer) {
        console.warn('Scrollbar container not found');
        return;
    }
    
    // We clear any pending innerHTML replacement timeout
    if (scrollbarResetTimeout) {
        clearTimeout(scrollbarResetTimeout);
        scrollbarResetTimeout = null;
    }
    
    if (currentSize === "sm") {
        // We destroy SimpleBar instance if it exists (collapsed sidebar doesn't need smooth scrolling)
        if (scrollbarElement && typeof scrollbarElement.unMount === 'function') {
            scrollbarElement.unMount();
            scrollbarElement = null;
        }
        
        // We reset active menu states without destroying DOM
        initActiveMenu();
    } else {
        // We initialize SimpleBar for smooth scrolling (expanded sidebar)
        if (!scrollbarElement) {
            scrollbarElement = new SimpleBar(scrollbarContainer);
        }
        
        initActiveMenu();
    }
}

/**
 * Scroll active menu item into view
 * 
 * We automatically scroll the sidebar to show the active menu item
 * when it's outside the visible area. This improves UX by ensuring
 * users always see their current location in the navigation.
 */
function initMenuItemScroll() {
    var sidebarMenu = document.getElementById("navbar-nav");
    if (!sidebarMenu) return;
    
    var currentPath = location.pathname == "/" ? "index.html" : location.pathname.substring(1);
    currentPath = currentPath.substring(currentPath.lastIndexOf("/") + 1);
    
    var activeMenu = document.getElementById("navbar-nav")?.querySelector('[href="' + currentPath + '"]');
    if (!activeMenu) return;
    
    const bodyHeight = (window.innerHeight/2) < 85 ? 85 : window.innerHeight/2;
    var offsetTopRelativeToBody = 0;
    
    // We calculate total offset from top
    while (activeMenu) {
        offsetTopRelativeToBody += activeMenu.offsetTop;
        activeMenu = activeMenu.offsetParent;
    }
    
    // We only scroll if item is far from top
    if (offsetTopRelativeToBody > 300) {
        var verticalMenu = document.getElementsByClassName("app-menu") ? document.getElementsByClassName("app-menu")[0] : "";
        var scrollWrapper = verticalMenu?.querySelector(".simplebar-content-wrapper");
        
        if (verticalMenu && scrollWrapper) {
            var scrollTop = offsetTopRelativeToBody == 330 ? offsetTopRelativeToBody + 85 : offsetTopRelativeToBody - bodyHeight;
            scrollWrapper.scrollTo({
                top: scrollTop,
                behavior: "smooth"
            });
        }
    }
}

/**
 * Set up window event listeners
 * 
 * We initialize all window-level event listeners for resize,
 * scroll, and load events.
 */
function windowLoadContent() {
    window.addEventListener("resize", windowResizeHover);
    
    document.addEventListener("scroll", function () {
        windowScroll();
    });
    
    window.addEventListener("load", function () {
        initActiveMenu();
        isLoadBodyElement();
    });
    
    if (document.getElementById("topnav-hamburger-icon")) {
        document.getElementById("topnav-hamburger-icon").addEventListener("click", toggleHamburgerMenu);
    }
}

/**
 * Initialize resize listener with debouncing
 * 
 * WHAT IS DEBOUNCING?
 * When user drags browser window to resize, the 'resize' event
 * fires HUNDREDS of times per second. This is wasteful.
 * 
 * Debouncing means: "Wait until user STOPS resizing, then react"
 * We wait 150 milliseconds after last resize event.
 * 
 * BENEFIT: Prevents lag and flickering during resize
 */
function initializeSidebarResize() {
    let resizeTimeout;
    
    window.addEventListener('resize', function() {
        // We clear previous timeout (if user is still resizing)
        clearTimeout(resizeTimeout);
        
        // We set new timeout: run windowResizeHover after 150ms of no resizing
        resizeTimeout = setTimeout(function() {
            windowResizeHover();
        }, 150);
    });
    
    // We run once immediately on page load to set initial state
    windowResizeHover();
}

/**
 * Initialize all app functionality
 * 
 * This runs once when page loads and bootstraps the entire application.
 * We follow a specific initialization order to prevent race conditions.
 */
function init() {
    // We set up window event listeners first
    windowLoadContent();
    
    // We initialize theme switcher
    lightDarkMode();
    
    // We initialize menu item scrolling
    initMenuItemScroll();
    
    // We initialize sidebar resize handling
    // NOTE: This calls windowResizeHover() which calls applyScrollbarLogic()
    // so we don't need to call applyScrollbarLogic() separately
    initializeSidebarResize();
}

// We bootstrap the application
init();

/**
 * Window scroll sticky class handler
 * 
 * We add a sticky class to the topbar when user scrolls down.
 * This creates a fixed header effect for better navigation.
 */
function windowScroll() {
    var navbar = document.getElementById("page-topbar");
    if (navbar) {
        if (document.body.scrollTop >= 50 || document.documentElement.scrollTop >= 50) {
            navbar.classList.add("is-sticky");
        } else {
            navbar.classList.remove("is-sticky");
        }
    }
}

/**
 * Confirm-before-submit for destructive buttons
 *
 * Reads the message off data-confirm instead of an inline onclick, so
 * buttons stay CSP-safe under script-src (no inline attribute handlers).
 */
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-confirm]');
    if (!btn) return;

    if (!window.confirm(btn.getAttribute('data-confirm'))) {
        e.preventDefault();
    }
});
