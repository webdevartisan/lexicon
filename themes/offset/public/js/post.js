document.addEventListener("DOMContentLoaded", function () {
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Press progress: the cobalt bar tracks how much of the sheet has run.
  var bar = document.getElementById("press-progress-bar");
  if (bar) {
    var ticking = false;
    var updateBar = function () {
      var doc = document.documentElement;
      var max = doc.scrollHeight - window.innerHeight;
      var p = max > 0 ? Math.min(window.scrollY / max, 1) : 0;
      bar.style.transform = "scaleX(" + p + ")";
      ticking = false;
    };
    window.addEventListener("scroll", function () {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(updateBar);
      }
    }, { passive: true });
    updateBar();
  }

  var content = document.querySelector(".post-content");
  if (content) {
    // TinyMCE peppers the body with spacer <p>s; flatten the rhythm.
    content.querySelectorAll("p").forEach(function (p) {
      var t = p.innerHTML.trim();
      if (t === "" || t === "<br>" || t === "&nbsp;" || t === " ") p.remove();
    });

    // Pasted code arrives as a run of <p>s rather than a <pre>. Stitch
    // adjacent code-shaped paragraphs into one .code-block so the styles bite.
    var codeStart = /^(?:\s|const\s|let\s|var\s|function\s|async\s|await\s|if\s*\(|else|for\s*\(|while\s*\(|return\s|import\s|export\s|class\s|try\s|catch|\}|throw\s|new\s)|[{};]\s*$/;
    var assignment = /^\s*[a-zA-Z_$][\w$]*\s*[=:(]/;

    var paras = Array.from(content.children);
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

  var cover = document.getElementById("post-cover");

  if (window.gsap && !reduce) {
    gsap.registerPlugin(window.ScrollTrigger);

    // The cover starts as a cobalt proof and develops once the reader
    // settles into the piece.
    if (cover) {
      window.ScrollTrigger.create({
        trigger: cover,
        start: "top 55%",
        once: true,
        onEnter: function () { cover.classList.add("is-developed"); },
      });

      var coverImg = cover.querySelector("img");
      if (coverImg) {
        gsap.to(coverImg, {
          yPercent: -6,
          ease: "none",
          scrollTrigger: { trigger: cover, start: "top bottom", end: "bottom top", scrub: 0.6 },
        });
      }
    }
  } else if (cover) {
    cover.classList.add("is-developed");
  }
});
