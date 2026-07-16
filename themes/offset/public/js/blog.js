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

    // Insurance — if the GSAP slide-in never runs, the chars are still visible after this.
    setTimeout(function () { headline.classList.add("is-ready"); }, 1400);
  }

  // ---------- the light table ----------
  // Rows carry their cover in data attributes; resting on a row proofs it in
  // the pinned pane. Delegated so the AJAX category swap keeps working.
  var rowsWrap = document.querySelector(".index-rows");
  var lightTable = document.getElementById("light-table");
  var lightPlate = lightTable && lightTable.querySelector(".light-plate");
  var lightImg = lightPlate && lightPlate.querySelector("img");
  var lightCap = document.getElementById("light-cap");

  if (rowsWrap && lightImg) {
    var currentCover = lightImg.getAttribute("src") || "";

    rowsWrap.addEventListener("mouseover", function (ev) {
      var row = ev.target.closest(".index-row");
      if (!row || !rowsWrap.contains(row)) return;

      lightPlate.classList.add("is-developed");

      var cover = row.getAttribute("data-cover") || "";
      if (cover && cover !== currentCover) {
        currentCover = cover;
        lightPlate.classList.add("is-switching");
        setTimeout(function () {
          lightImg.setAttribute("src", cover);
          if (lightCap) lightCap.textContent = row.getAttribute("data-cap") || "";
          lightPlate.classList.remove("is-switching");
        }, 180);
      } else if (lightCap) {
        lightCap.textContent = row.getAttribute("data-cap") || "";
      }
    });

    rowsWrap.addEventListener("mouseleave", function () {
      lightPlate.classList.remove("is-developed");
    });
  }

  // ---------- category filter ----------
  var filterList = document.querySelector(".filter-list");
  var grid = document.querySelector(".index-rows");
  if (filterList && grid) {
    var countEl = document.getElementById("filter-count");
    var pills = filterList.querySelectorAll("li");

    // "See all" footer: the whole catalogue for "All", or a category's own
    // archive when that category has more posts than the six rows shown.
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
        if (footerLabel) footerLabel.textContent = "Open the full catalogue";
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

        // Swap in this category's rows (or recent for "All") without a reload.
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
              var n = grid.querySelectorAll(".index-row").length;
              countEl.textContent = n + " post" + (n === 1 ? "" : "s");
            }
            updateFooter(li);
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
  if (window.gsap && !reduce) {
    gsap.registerPlugin(window.ScrollTrigger);

    var chars = document.querySelectorAll(".hero-headline .char");
    if (chars.length) {
      gsap.set(chars, { yPercent: 112, opacity: 0 });
      gsap.to(chars, {
        yPercent: 0,
        opacity: 1,
        duration: 1.05,
        stagger: 0.022,
        ease: "power3.out",
        delay: 0.1,
        onComplete: function () { if (headline) headline.classList.add("is-ready"); },
      });
    } else if (headline) {
      headline.classList.add("is-ready");
    }

    // The proof stamp thumps down once the name is set.
    var stamp = document.querySelector(".proof-stamp");
    if (stamp) {
      gsap.set(stamp, { opacity: 0, scale: 1.6, rotate: 2 });
      gsap.to(stamp, { opacity: 1, scale: 1, rotate: -4, duration: 0.5, ease: "power4.in", delay: 1.05 });
    }

    var leadImg = document.querySelector(".lead-plate img");
    if (leadImg) {
      gsap.to(leadImg, { yPercent: -6, ease: "none", scrollTrigger: { trigger: ".lead-plate", start: "top bottom", end: "bottom top", scrub: 0.6 } });
    }

    var colNum = document.querySelector(".colophon-number");
    if (colNum) {
      gsap.from(colNum, { yPercent: 16, ease: "none", scrollTrigger: { trigger: ".colophon", start: "top bottom", end: "bottom top", scrub: 0.7 } });
    }
  } else {
    var stampStatic = document.querySelector(".proof-stamp");
    if (stampStatic) stampStatic.style.opacity = "1";
  }
});
