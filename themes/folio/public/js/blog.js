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

  document.querySelectorAll(".filter-list li").forEach(function (li, _, pills) {
    li.addEventListener("click", function () {
      var btn = li.querySelector("button");
      if (!btn) return;
      var filter = btn.getAttribute("data-filter") || "*";
      pills.forEach(function (other) {
        other.classList.toggle("is-active", other === li);
        var b = other.querySelector("button");
        if (b) b.setAttribute("aria-selected", other === li ? "true" : "false");
      });
      document.querySelectorAll(".articles-grid .article").forEach(function (a) {
        var cat = a.getAttribute("data-category") || "";
        a.style.display = (filter === "*" || cat === filter) ? "" : "none";
      });
    });
  });

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