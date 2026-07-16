document.addEventListener("DOMContentLoaded", function () {
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var headline = document.querySelector("[data-split]");
  if (headline) {
    var words = headline.textContent.split(" ");
    headline.textContent = "";
    words.forEach(function (word, wi) {
      var w = document.createElement("span");
      w.className = "word";
      for (var i = 0; i < word.length; i++) {
        var c = document.createElement("span");
        c.className = "char";
        c.textContent = word[i];
        w.appendChild(c);
      }
      headline.appendChild(w);
      if (wi < words.length - 1) headline.appendChild(document.createTextNode(" "));
    });
  }

  // ---------- waypoint nodes ----------
  // A node lights up once its row has been walked past; delegated through a
  // rebindable function so the AJAX category swap keeps working.
  function bindNodes() {
    var rows = document.querySelectorAll(".waypoint:not(.is-passed)");
    if (reduce || !("IntersectionObserver" in window)) {
      rows.forEach(function (r) { r.classList.add("is-passed"); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-passed");
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.6 });
    rows.forEach(function (r) { io.observe(r); });
  }
  bindNodes();

  // ---------- category filter ----------
  var filterList = document.querySelector(".filter-list");
  var grid = document.querySelector(".index-rows");
  if (filterList && grid) {
    var countEl = document.getElementById("filter-count");
    var pills = filterList.querySelectorAll("li");

    // "See all" footer: the whole route log for "Full route", or a category's
    // own listing when that leg has more posts than the six rows shown.
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
        if (footerLabel) footerLabel.textContent = "Follow the full route";
        footer.style.display = allMore ? "" : "none";
      } else if (count > 6) {
        var base = window.location.pathname.replace(/\/+$/, "");
        if (footerCount) footerCount.textContent = count + " waypoints on the " + name + " leg";
        footerLink.setAttribute("href", base + "/category/" + encodeURIComponent(filter));
        if (footerLabel) footerLabel.textContent = "Follow this leg";
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

        // Swap in this leg's waypoints (or recent for "Full route") without a reload.
        var base = window.location.pathname.replace(/\/+$/, "");
        var url = base + "/index-feed" + (filter ? "?category=" + encodeURIComponent(filter) : "");

        // Reflect the filter in the address bar so it's shareable and the back button works.
        window.history.pushState({ category: filter }, "", base + (filter ? "?category=" + encodeURIComponent(filter) : ""));

        grid.style.opacity = "0.35";
        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
          .then(function (r) { return r.text(); })
          .then(function (html) {
            grid.innerHTML = html;
            grid.style.opacity = "";
            // Newly injected rows start hidden (.reveal) — show them.
            grid.querySelectorAll(".reveal").forEach(function (el) { el.classList.add("is-in"); });
            if (countEl) {
              var n = grid.querySelectorAll(".waypoint").length;
              countEl.textContent = n + " waypoint" + (n === 1 ? "" : "s");
            }
            updateFooter(li);
            bindNodes();
            // The rail spans the rows, so its scrub window changed height.
            if (window.ScrollTrigger) window.ScrollTrigger.refresh();
          })
          .catch(function () { grid.style.opacity = ""; });
      });
    });

    // Keep the rows in step with the URL on back/forward.
    window.addEventListener("popstate", function () { window.location.reload(); });

    // Arrived with ?category= already applied — sync the "see all" footer to the active pill.
    var initialActive = filterList.querySelector("li.is-active");
    if (initialActive) updateFooter(initialActive);
  }

  // ---------- motion ----------
  var walked = document.getElementById("route-walked");
  var yah = document.getElementById("yah");

  if (window.gsap && !reduce) {
    gsap.registerPlugin(window.ScrollTrigger);

    var chars = document.querySelectorAll(".th-name .char");
    if (chars.length) {
      gsap.set(chars, { yPercent: 112, opacity: 0 });
      gsap.to(chars, {
        yPercent: 0,
        opacity: 1,
        duration: 1.05,
        stagger: 0.022,
        ease: "power3.out",
        delay: 0.1,
      });
    }

    // The walked line draws itself out to the marker, which then plants.
    if (walked) {
      gsap.fromTo(walked, { strokeDashoffset: 1 }, { strokeDashoffset: 0, duration: 1.5, ease: "power2.inOut", delay: 0.7 });
    }
    if (yah) {
      gsap.set(yah, { opacity: 0, y: 10, scale: 0.6 });
      gsap.to(yah, { opacity: 1, y: 0, scale: 1, duration: 0.55, ease: "back.out(2.2)", delay: 2.05 });
    }
    var start = document.querySelector(".route-start");
    if (start) {
      gsap.fromTo(start, { scale: 0, transformOrigin: "50% 50%" }, { scale: 1, duration: 0.4, ease: "back.out(2)", delay: 0.6 });
    }

    // The rail fills as the reader walks the list.
    var railFill = document.getElementById("route-rail-fill");
    var railWrap = document.querySelector(".route-wrap");
    if (railFill && railWrap) {
      gsap.fromTo(railFill, { scaleY: 0 }, {
        scaleY: 1,
        ease: "none",
        scrollTrigger: { trigger: railWrap, start: "top 70%", end: "bottom 65%", scrub: 0.5 },
      });
    }

    // The nearest inset drifts a touch as it scrolls by. Over-scale it so the
    // drift never exposes the frame edge, and hand transform fully to GSAP.
    var nearImg = document.querySelector(".nearest-inset img");
    if (nearImg) {
      nearImg.style.transition = "filter 0.55s cubic-bezier(0.4, 0, 0.2, 1)";
      gsap.set(nearImg, { scale: 1.08 });
      gsap.to(nearImg, { yPercent: -5, ease: "none", scrollTrigger: { trigger: ".nearest-inset", start: "top bottom", end: "bottom top", scrub: 0.6 } });
    }
  }
});
