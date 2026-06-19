document.addEventListener("DOMContentLoaded", function () {
var content = document.querySelector(".post-content");
if (content) {
    // TinyMCE peppers the body with spacer <p>s; flatten the rhythm.
    content.querySelectorAll("p").forEach(function (p) {
    var t = p.innerHTML.trim();
    if (t === "" || t === "<br>" || t === "&nbsp;" || t === " ") p.remove();
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

if (window.gsap && !window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    gsap.registerPlugin(window.ScrollTrigger);

    var cover = document.querySelector(".post-cover");
    var coverImg = cover && cover.querySelector("img");
    if (coverImg) {
    gsap.to(coverImg, {
        yPercent: -8,
        ease: "none",
        scrollTrigger: { trigger: cover, start: "top bottom", end: "bottom top", scrub: 0.6 },
    });
    }
}
});