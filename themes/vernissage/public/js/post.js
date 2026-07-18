document.addEventListener("DOMContentLoaded", function () {
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Tour progress: the viridian hairline tracks how far along the wall the
  // visitor has walked. CSS decides the axis (vertical rail on desktop,
  // top bar on mobile); the script only feeds it a 0–1 custom property.
  var fill = document.getElementById("tour-progress-fill");
  if (fill) {
    var ticking = false;
    var updateFill = function () {
      var doc = document.documentElement;
      var max = doc.scrollHeight - window.innerHeight;
      var p = max > 0 ? Math.min(window.scrollY / max, 1) : 0;
      fill.style.setProperty("--tour-progress", p);
      ticking = false;
    };
    window.addEventListener("scroll", function () {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(updateFill);
      }
    }, { passive: true });
    updateFill();
  }

  var essay = document.querySelector(".work-essay");
  if (essay) {
    // The drop cap lands on the first paragraph with real prose in it —
    // editors nest content too unpredictably for a CSS-only selector.
    var paragraphs = essay.querySelectorAll("p");
    for (var d = 0; d < paragraphs.length; d++) {
      var text = (paragraphs[d].textContent || "").trim();
      if (text.length > 60 && !paragraphs[d].closest("blockquote,figure,pre")) {
        paragraphs[d].classList.add("has-dropcap");
        break;
      }
    }

    // TinyMCE peppers the body with spacer <p>s; flatten the rhythm.
    essay.querySelectorAll("p").forEach(function (p) {
      var t = p.innerHTML.trim();
      if (t === "" || t === "<br>" || t === "&nbsp;" || t === " ") p.remove();
    });

    // Pasted code arrives as a run of <p>s rather than a <pre>. Stitch
    // adjacent code-shaped paragraphs into one .code-block so the styles bite.
    var codeStart = /^(?:\s|const\s|let\s|var\s|function\s|async\s|await\s|if\s*\(|else|for\s*\(|while\s*\(|return\s|import\s|export\s|class\s|try\s|catch|\}|throw\s|new\s)|[{};]\s*$/;
    var assignment = /^\s*[a-zA-Z_$][\w$]*\s*[=:(]/;

    var paras = Array.from(essay.children);
    for (var i = 0; i < paras.length;) {
      var el = paras[i];
      if (el.tagName !== "P" || el.closest("pre,code") || !codeStart.test(el.textContent || "")) {
        i++;
        continue;
      }

      var run = [el];
      var j = i + 1;
      while (j < paras.length && paras[j].tagName === "P") {
        var t = paras[j].textContent || "";
        if (!codeStart.test(t) && !assignment.test(t)) break;
        run.push(paras[j]);
        j++;
      }

      if (run.length >= 2) {
        var wrap = document.createElement("div");
        wrap.className = "code-block";
        el.parentNode.insertBefore(wrap, el);
        run.forEach(function (p) { wrap.appendChild(p); });
        i = j;
      } else {
        i++;
      }
    }
  }

  if (window.gsap && !reduce) {
    gsap.registerPlugin(window.ScrollTrigger);

    // The framed piece drifts gently inside its mat as the visitor passes.
    var figure = document.getElementById("work-figure");
    var coverImg = figure && figure.querySelector("img");
    if (coverImg) {
      // overscale slightly so the drift never uncovers the mat
      gsap.fromTo(coverImg, { yPercent: 4, scale: 1.09 }, {
        yPercent: -4,
        scale: 1.09,
        ease: "none",
        scrollTrigger: { trigger: figure, start: "top bottom", end: "bottom top", scrub: 0.6 },
      });
    }
  }
});
