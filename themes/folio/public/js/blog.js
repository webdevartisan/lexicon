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

  var filterList = document.querySelector(".filter-list");
  var grid = document.querySelector(".articles-grid");
  if (filterList && grid) {
    var countEl = document.getElementById("filter-count");
    var pills = filterList.querySelectorAll("li");

    // "See all" footer: the whole archive for "All", or a category's own archive
    // when that category has more posts than the six the grid shows.
    var footer = document.getElementById("index-footer");
    var footerCount = document.getElementById("index-footer-count");
    var footerLink = document.getElementById("index-footer-link");
    var footerLabel = document.getElementById("index-footer-label");
    var allCountHtml = footerCount ? footerCount.innerHTML : "";
    var allArchive = footer ? footer.getAttribute("data-archive") : "";
    var allMore = footer ? footer.getAttribute("data-allmore") === "1" : false;

    function updateFooter(li) {
      if (!footer || !footerLink) return;
      var b = li.querySelector("button");
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
      li.addEventListener("click", function () {
        var btn = li.querySelector("button");
        if (!btn || li.classList.contains("is-active")) return;
        var filter = btn.getAttribute("data-filter") || "";

        pills.forEach(function (other) {
          other.classList.toggle("is-active", other === li);
          var b = other.querySelector("button");
          if (b) b.setAttribute("aria-selected", other === li ? "true" : "false");
        });

        // Swap in this category's cards (or recent for "All") without a reload.
        var base = window.location.pathname.replace(/\/+$/, "");
        var url = base + "/index-feed" + (filter ? "?category=" + encodeURIComponent(filter) : "");

        grid.style.opacity = "0.35";
        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
          .then(function (r) { return r.text(); })
          .then(function (html) {
            grid.innerHTML = html;
            grid.style.opacity = "";
            // Newly injected cards start hidden (.reveal/.img-mask) — show them.
            grid.querySelectorAll(".reveal, .img-mask").forEach(function (el) { el.classList.add("is-in"); });
            if (countEl) {
              var n = grid.querySelectorAll(".article").length;
              countEl.textContent = n + " post" + (n === 1 ? "" : "s");
            }
            updateFooter(li);
          })
          .catch(function () { grid.style.opacity = ""; });
      });
    });
  }

  if (window.gsap && !reduce) {
    gsap.registerPlugin(window.ScrollTrigger);

    var chars = document.querySelectorAll(".hero-headline .char");
    if (chars.length) {
      gsap.set(chars, { yPercent: 110, opacity: 0 });
      gsap.to(chars, {
        yPercent: 0,
        opacity: 1,
        duration: 1.1,
        stagger: 0.024,
        ease: "power3.out",
        delay: 0.15,
        onComplete: function () { if (headline) headline.classList.add("is-ready"); },
      });
    } else if (headline) {
      headline.classList.add("is-ready");
    }

    var colNum = document.querySelector(".colophon-number");
    if (colNum) {
      gsap.from(colNum, { yPercent: 18, ease: "none", scrollTrigger: { trigger: ".colophon", start: "top bottom", end: "bottom top", scrub: 0.7 } });
    }

    var leadImg = document.querySelector(".lead-image img");
    if (leadImg) {
      gsap.to(leadImg, { yPercent: -6, ease: "none", scrollTrigger: { trigger: ".lead-image", start: "top bottom", end: "bottom top", scrub: 0.6 } });
    }
  }
});