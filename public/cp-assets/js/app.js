/** SimpleBar on the sidebar, created the first time it is expanded and kept from then on. */
let scrollbarElement = null;

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
    const verticalOverlay = document.getElementById('sidebar-overlay');
    const appMenu = document.querySelector('.app-menu');
    
    // We handle MOBILE differently (uses overlay system)
    if (category === 'mobile') {
        handleMobileMenuOpen(verticalOverlay, appMenu);
        return; // Stop here, don't continue to desktop logic
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
    
    // We update scrollbar - but ONLY after debounced resize settles
    applyScrollbarLogic();
}

/**
 * Give the expanded sidebar its SimpleBar scroller.
 *
 * It is never unmounted: unMount() leaves its wrappers behind, so each collapse and
 * expand nested another scroller inside the last. On the rail, CSS opens the
 * wrappers up and hides the tracks instead.
 */
function applyScrollbarLogic() {
    // We only apply scrollbar logic to vertical layouts
    if (document.documentElement.getAttribute("data-layout") !== "vertical") {
        return;
    }
    
    const scrollbarContainer = document.getElementById("scrollbar");
    
    if (!scrollbarContainer) {
        console.warn('Scrollbar container not found');
        return;
    }
    
    if (scrollbarElement || document.documentElement.getAttribute("data-sidebar-size") === "sm") {
        return;
    }

    // autoHide off keeps the thumb visible, so touch users can see the list scrolls
    scrollbarElement = new SimpleBar(scrollbarContainer, { autoHide: false });
    watchSidebarOverflow(scrollbarContainer, scrollbarElement);
}

/**
 * Flag the sidebar while more items sit below the fold.
 *
 * CSS fades the bottom edge when data-more-below is set, which hints at hidden items.
 * The flag drops once the list is scrolled to the end or fits without scrolling.
 *
 * @param {HTMLElement} container The #scrollbar element
 * @param {SimpleBar} bar The SimpleBar instance wrapping it
 */
function watchSidebarOverflow(container, bar) {
    const scroller = bar.getScrollElement();
    const update = function () {
        const remaining = scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight;
        container.toggleAttribute("data-more-below", remaining > 4);
    };

    scroller.addEventListener("scroll", update, { passive: true });
    // Groups expand and the mobile drawer opens after load, both change the available height
    if (typeof ResizeObserver !== "undefined") {
        const observer = new ResizeObserver(update);
        observer.observe(scroller);
        observer.observe(bar.getContentElement());
    }
    update();
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
        isLoadBodyElement();
        // Only now may size changes animate: the size set while loading is where the page starts, not a move.
        requestAnimationFrame(function () {
            document.documentElement.setAttribute("data-sidebar-motion", "");
        });
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
    var holder = e.target.closest('[data-confirm]');
    if (!holder || holder.dataset.confirmed === '1') return;

    // The attribute sits on a button, a link or a whole form; only a submit button counts inside a form
    var trigger = holder.matches('form') ? e.target.closest('button, input[type="submit"]') : holder;
    if (!trigger || (holder.matches('form') && trigger.type === 'button')) return;

    e.preventDefault();

    window.cpConfirm(holder.getAttribute('data-confirm'), {
        title: holder.getAttribute('data-confirm-title') || undefined,
        confirmLabel: holder.getAttribute('data-confirm-ok') || undefined,
        tone: holder.getAttribute('data-confirm-tone') || undefined
    }).then(function (ok) {
        if (!ok) return;

        holder.dataset.confirmed = '1';
        trigger.click();
        delete holder.dataset.confirmed;
    });
});
