/**
 * Lucide Icons - Self-Loading Initialization
 * 
 * @file lucide.init.js
 */
(function() {
    'use strict';
    
    const DEBUG = false;
    
    function log(...args) {
        if (DEBUG) console.log(...args);
    }
    
    function info(...args) {
        if (DEBUG) console.info(...args);
    }
    
    function isLucideLoaded() {
        return typeof lucide !== 'undefined';
    }
    
    function needsConversion() {
        const cachedAreas = document.querySelectorAll('[data-sidebar], .app-menu, #navbar-nav');
        let unconvertedCount = 0;
        cachedAreas.forEach(area => {
            if (area) {
                unconvertedCount += area.querySelectorAll('i[data-lucide]').length;
            }
        });
        return unconvertedCount > 0;
    }
    
    function loadLucideLibrary() {
        return new Promise((resolve, reject) => {
            if (isLucideLoaded()) {
                resolve(lucide);
                return;
            }
            
            const script = document.createElement('script');
            script.src = '/cp-assets/libs/lucide/umd/lucide.js';
            script.onload = () => resolve(window.lucide);
            script.onerror = () => reject(new Error('Failed to load Lucide'));
            document.head.appendChild(script);
        });
    }
    
    function init(rootElement = null) {
        if (!isLucideLoaded()) {
            console.warn('Lucide library not loaded yet');
            return;
        }
        
        const options = {};
        if (rootElement) {
            options.root = rootElement;
        }
        
        lucide.createIcons(options);
        info('Lucide icons initialized', rootElement ? '(targeted)' : '(global)');
    }
    
    function setupSidebarObserver() {
        const sidebar = document.querySelector('[data-sidebar]');
        
        if (!sidebar) {
            console.warn('Sidebar element not found - skipping auto-reinitializer');
            return;
        }
        
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && 
                    (mutation.attributeName === 'data-sidebar-size' || 
                     mutation.attributeName === 'data-sidebar')) {
                    
                    log('Sidebar state changed - checking for unconverted icons');
                    
                    if (needsConversion()) {
                        requestAnimationFrame(() => {
                            init(sidebar);
                        });
                    }
                }
            });
        });
        
        observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['data-sidebar-size', 'data-sidebar']
        });
        
        info('Sidebar icon auto-reinitializer active');
    }
    
    async function bootstrap() {
        setTimeout(async () => {
            if (!needsConversion()) {
                info('✓ Icons from cache - Lucide not needed (~25KB saved)');
                return;
            }
            
            info('🔄 Icons need conversion - loading Lucide');
            
            try {
                await loadLucideLibrary();
                
                setTimeout(() => {
                    log('Initializing icons after sidebar state settled');
                    init();
                    setupSidebarObserver();
                }, 100);
            } catch (error) {
                console.error('Failed to load Lucide:', error);
            }
        }, 100);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
    
    window.reinitializeLucideIcons = function(rootElement = null) {
        init(rootElement);
    };
})();
