document.addEventListener("DOMContentLoaded", function () {
    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var toggle = document.querySelector(".nav-toggle");
    var nav = document.getElementById("primaryNav");
    if (toggle && nav) {
    toggle.addEventListener("click", function () {
        var open = nav.classList.toggle("is-open");
        toggle.classList.toggle("is-open", open);
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    nav.querySelectorAll("a").forEach(function (a) {
        a.addEventListener("click", function () {
        nav.classList.remove("is-open");
        toggle.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
        });
    });
    }

    var masthead = document.querySelector(".masthead");
    var onScroll = function () { if (masthead) masthead.classList.toggle("is-scrolled", window.scrollY > 8); };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });

    var revealEls = document.querySelectorAll(".reveal, .img-mask");
    var showAll = function () { revealEls.forEach(function (el) { el.classList.add("is-in"); }); };

    if (window.ScrollTrigger) {
    revealEls.forEach(function (el) {
        window.ScrollTrigger.create({ trigger: el, start: "top 90%", once: true, onEnter: function () { el.classList.add("is-in"); } });
    });
    } else {
    // ScrollTrigger may still be loading; fall back to showing everything once load fires
    window.addEventListener("load", showAll, { once: true });
    setTimeout(showAll, 800);
    }

    if (window.gsap && !reduce) {
    gsap.registerPlugin(window.ScrollTrigger);

    var footDisplay = document.querySelector(".foot-display");
    var footWrap = document.querySelector(".foot-display-wrap");
    if (footDisplay && footWrap) {
        var headroom = footWrap.clientWidth - footDisplay.scrollWidth;
        var drift = Math.max(Math.min(headroom, 0), -80);
        gsap.fromTo(footDisplay, { x: 0 }, { x: drift, ease: "none", scrollTrigger: { trigger: footWrap, start: "top bottom", end: "bottom bottom", scrub: 0.8 } });
    }
    }
});