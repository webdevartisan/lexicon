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

    // Insurance: if the GSAP rise never runs, the chars are still visible after this.
    setTimeout(function () { headline.classList.add("is-ready"); }, 1400);
  }

  var ornament = document.querySelector(".hero-ornament");

  // ---------- room filter ----------
  var filterList = document.querySelector(".filter-list");
  var grid = document.querySelector(".salon-grid");
  if (filterList && grid) {
    var countEl = document.getElementById("filter-count");
    var pills = filterList.querySelectorAll("li");

    // "See all" footer: the whole catalogue for "All rooms", or a room's own
    // archive when that room holds more works than the six on the wall.
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
        if (footerLabel) footerLabel.textContent = "Open the catalogue raisonné";
        footer.style.display = allMore ? "" : "none";
      } else if (count > 6) {
        var base = window.location.pathname.replace(/\/+$/, "");
        if (footerCount) footerCount.textContent = count + " works in " + name;
        footerLink.setAttribute("href", base + "/category/" + encodeURIComponent(filter));
        if (footerLabel) footerLabel.textContent = "View the whole room";
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

        // Rehang the wall with this room's works (or recent for "All") without a reload.
        var base = window.location.pathname.replace(/\/+$/, "");
        var url = base + "/index-feed" + (filter ? "?category=" + encodeURIComponent(filter) : "");

        // Reflect the room in the address bar so it's shareable and the back button works.
        window.history.pushState({ category: filter }, "", base + (filter ? "?category=" + encodeURIComponent(filter) : ""));

        grid.style.opacity = "0.35";
        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
          .then(function (r) { return r.text(); })
          .then(function (html) {
            grid.innerHTML = html;
            grid.style.opacity = "";
            // Newly hung works start hidden (.reveal). show them.
            grid.querySelectorAll(".reveal").forEach(function (el) { el.classList.add("is-in"); });
            if (countEl) {
              var n = grid.querySelectorAll(".work").length;
              countEl.textContent = n + " work" + (n === 1 ? "" : "s");
            }
            updateFooter(li);
          })
          .catch(function () { grid.style.opacity = ""; });
      });
    });

    // Keep the wall in step with the URL on back/forward.
    window.addEventListener("popstate", function () { window.location.reload(); });

    // Arrived with ?category= already applied, sync the "see all" footer to the active pill.
    var initialActive = filterList.querySelector("li.is-active");
    if (initialActive) updateFooter(initialActive);
  }

  // ---------- motion ----------
  if (window.gsap && !reduce) {
    gsap.registerPlugin(window.ScrollTrigger);

    var chars = document.querySelectorAll(".hero-headline .char");
    if (chars.length) {
      gsap.set(chars, { yPercent: 112, opacity: 0 });
      gsap.to(chars, {
        yPercent: 0,
        opacity: 1,
        duration: 1.1,
        stagger: 0.024,
        ease: "power3.out",
        delay: 0.1,
        onComplete: function () { if (headline) headline.classList.add("is-ready"); },
      });
    } else if (headline) {
      headline.classList.add("is-ready");
    }

    // The lozenge is set beneath the name once the letters have risen.
    if (ornament) {
      setTimeout(function () { ornament.classList.add("is-set"); }, 950);
    }

    var centreImg = document.querySelector(".centre-frame img");
    if (centreImg) {
      // overscale slightly so the drift never uncovers the mat
      gsap.fromTo(centreImg, { yPercent: 5, scale: 1.1 }, { yPercent: -5, scale: 1.1, ease: "none", scrollTrigger: { trigger: ".centre-frame", start: "top bottom", end: "bottom top", scrub: 0.6 } });
    }

    var est = document.querySelector(".curator-est");
    if (est) {
      gsap.from(est, { yPercent: 16, ease: "none", scrollTrigger: { trigger: ".curator", start: "top bottom", end: "bottom top", scrub: 0.7 } });
    }
  } else if (ornament) {
    ornament.classList.add("is-set");
  }
});
