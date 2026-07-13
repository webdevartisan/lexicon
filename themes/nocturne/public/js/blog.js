document.addEventListener("DOMContentLoaded", function () {
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Hero name: masked lines rise into place. CSS keeps them hidden until
  // is-ready lands, so the fallback below guarantees visibility either way.
  var heroName = document.querySelector(".hero-name");
  if (heroName) {
    var lines = heroName.querySelectorAll(".hero-line > span");
    if (window.gsap && !reduce && lines.length) {
      gsap.to(lines, {
        y: 0,
        yPercent: 0,
        duration: 1.3,
        stagger: 0.14,
        ease: "power4.out",
        delay: 0.2,
        onStart: function () { heroName.classList.add("is-ready"); },
      });
    }
    setTimeout(function () { heroName.classList.add("is-ready"); }, 1200);
  }

  // The floating cover preview that trails the cursor over index rows.
  // Touch layouts show inline thumbnails instead (style.css hides the float).
  var floatEl = document.getElementById("idx-float");
  var lists = document.querySelectorAll(".idx-list");
  var hoverable = window.matchMedia("(hover: hover) and (pointer: fine)").matches;

  if (floatEl && lists.length && hoverable && window.gsap && !reduce) {
    var img = floatEl.querySelector("img");
    var xTo = gsap.quickTo(floatEl, "x", { duration: 0.45, ease: "power3" });
    var yTo = gsap.quickTo(floatEl, "y", { duration: 0.5, ease: "power3" });
    var visible = false;

    var show = function (cover) {
      if (img.getAttribute("src") !== cover) img.setAttribute("src", cover);
      if (!visible) {
        visible = true;
        gsap.to(floatEl, { opacity: 1, scale: 1, duration: 0.35, ease: "power2.out", overwrite: "auto" });
      }
    };

    var hide = function () {
      if (!visible) return;
      visible = false;
      gsap.to(floatEl, { opacity: 0, scale: 0.94, duration: 0.3, ease: "power2.in", overwrite: "auto" });
    };

    gsap.set(floatEl, { opacity: 0, scale: 0.94, xPercent: 6, yPercent: -50 });

    document.addEventListener("mousemove", function (ev) {
      xTo(ev.clientX);
      yTo(ev.clientY);

      var row = ev.target.closest ? ev.target.closest(".idx-row") : null;
      if (row && row.getAttribute("data-cover")) {
        show(row.getAttribute("data-cover"));
      } else {
        hide();
      }
    }, { passive: true });

    document.addEventListener("scroll", hide, { passive: true });
  }

  // Category filter: swap the index rows in place via the theme-rendered fragment.
  var filterList = document.querySelector(".filter-list");
  var list = document.getElementById("idx-list");
  if (filterList && list) {
    var countEl = document.getElementById("filter-count");
    var pills = filterList.querySelectorAll("li");

    // "See all" footer: the whole archive for "All", or a category's own archive
    // when that category has more posts than the six the index shows.
    var footer = document.getElementById("index-footer");
    var footerCount = document.getElementById("index-footer-count");
    var footerLink = document.getElementById("index-footer-link");
    var footerLabel = document.getElementById("index-footer-label");
    var allCountHtml = footerCount ? footerCount.innerHTML : "";
    var allArchive = footer ? footer.getAttribute("data-archive") : "";
    var allMore = footer ? footer.getAttribute("data-allmore") === "1" : false;

    function updateFooter(li) {
      if (!footer || !footerLink) return;
      var b = li.querySelector("a");
      var filter = b ? (b.getAttribute("data-filter") || "") : "";
      var count = b ? parseInt(b.getAttribute("data-count") || "0", 10) : 0;
      var name = b ? b.textContent.trim() : "";

      if (!filter) {
        if (footerCount) footerCount.innerHTML = allCountHtml;
        footerLink.setAttribute("href", allArchive);
        if (footerLabel) footerLabel.textContent = "Open the full archive";
        footer.style.display = allMore ? "" : "none";
      } else if (count > 6) {
        var base = window.location.pathname.replace(/\/+$/, "");
        if (footerCount) footerCount.textContent = count + " posts in " + name;
        footerLink.setAttribute("href", base + "/category/" + encodeURIComponent(filter));
        if (footerLabel) footerLabel.textContent = "View all";
        footer.style.display = "";
      } else {
        footer.style.display = "none";
      }
    }

    pills.forEach(function (li) {
      li.addEventListener("click", function (e) {
        var btn = li.querySelector("a");
        if (!btn) return;
        e.preventDefault();
        if (li.classList.contains("is-active")) return;
        var filter = btn.getAttribute("data-filter") || "";

        pills.forEach(function (other) {
          other.classList.toggle("is-active", other === li);
          var b = other.querySelector("a");
          if (b) b.setAttribute("aria-selected", other === li ? "true" : "false");
        });

        var base = window.location.pathname.replace(/\/+$/, "");
        var url = base + "/index-feed" + (filter ? "?category=" + encodeURIComponent(filter) : "");

        // Reflect the filter in the address bar so it's shareable and the back button works.
        window.history.pushState({ category: filter }, "", base + (filter ? "?category=" + encodeURIComponent(filter) : ""));

        list.style.opacity = "0.35";
        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
          .then(function (r) { return r.text(); })
          .then(function (html) {
            list.innerHTML = html;
            list.style.opacity = "";
            // Newly injected rows start hidden (.reveal) so show them at once.
            list.querySelectorAll(".reveal, .img-veil").forEach(function (el) { el.classList.add("is-in"); });
            if (countEl) {
              var n = list.querySelectorAll(".idx-row").length;
              countEl.textContent = n + " post" + (n === 1 ? "" : "s");
            }
            updateFooter(li);
          })
          .catch(function () { list.style.opacity = ""; });
      });
    });

    // Keep the index in step with the URL on back/forward.
    window.addEventListener("popstate", function () { window.location.reload(); });

    // Arrived with ?category= already applied; sync the "see all" footer to the active pill.
    var initialActive = filterList.querySelector("li.is-active");
    if (initialActive) updateFooter(initialActive);
  }

  if (window.gsap && !reduce) {
    gsap.registerPlugin(window.ScrollTrigger);

    var horizon = document.querySelector(".hero-horizon");
    if (horizon) {
      gsap.from(horizon, { scaleX: 0, duration: 1.6, ease: "power3.inOut", delay: 0.5 });
    }

    var coverImg = document.querySelector(".cover-image img");
    if (coverImg) {
      gsap.to(coverImg, { yPercent: -7, ease: "none", scrollTrigger: { trigger: ".cover-image", start: "top bottom", end: "bottom top", scrub: 0.6 } });
    }

    var deskMark = document.querySelector(".desk-mark");
    if (deskMark) {
      gsap.from(deskMark, { yPercent: 16, ease: "none", scrollTrigger: { trigger: ".desk", start: "top bottom", end: "bottom top", scrub: 0.7 } });
    }
  }
});
