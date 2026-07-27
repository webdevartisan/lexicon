document.addEventListener("DOMContentLoaded", function () {
  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Brass hairline at the top tracks how far into the article the reader is.
  var bar = document.getElementById("read-progress-bar");
  var content = document.querySelector(".post-content");
  if (bar && content) {
    var update = function () {
      var rect = content.getBoundingClientRect();
      var total = rect.height - window.innerHeight;
      var read = Math.min(Math.max(-rect.top, 0), Math.max(total, 1));
      var pct = total > 0 ? (read / total) * 100 : 100;
      bar.style.width = pct + "%";
    };
    update();
    window.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", update, { passive: true });
  }

  if (window.gsap && !reduce) {
    gsap.registerPlugin(window.ScrollTrigger);

    var cover = document.querySelector(".post-cover img");
    if (cover) {
      gsap.to(cover, { yPercent: -5, ease: "none", scrollTrigger: { trigger: ".post-cover", start: "top bottom", end: "bottom top", scrub: 0.6 } });
    }
  }

  // Reply forms and collapsed reply threads under each comment
  document.addEventListener("click", function (ev) {
    var replyBtn = ev.target.closest("[data-reply-toggle]");
    if (replyBtn) {
      var form = document.getElementById("reply-form-" + replyBtn.getAttribute("data-reply-toggle"));
      if (form) {
        var hidden = form.toggleAttribute("hidden");
        if (!hidden) {
          var textarea = form.querySelector("textarea");
          if (textarea) textarea.focus();
        }
      }
      return;
    }

    var cancelBtn = ev.target.closest("[data-reply-cancel]");
    if (cancelBtn) {
      var cancelForm = document.getElementById("reply-form-" + cancelBtn.getAttribute("data-reply-cancel"));
      if (cancelForm) cancelForm.setAttribute("hidden", "");
      return;
    }

    var collapseBtn = ev.target.closest("[data-collapse-toggle]");
    if (collapseBtn) {
      var replies = document.getElementById("replies-" + collapseBtn.getAttribute("data-collapse-toggle"));
      if (!replies) return;
      var isHidden = replies.toggleAttribute("hidden");
      var label = isHidden ? collapseBtn.getAttribute("data-label-show") : collapseBtn.getAttribute("data-label-hide");
      if (label) collapseBtn.textContent = label;
    }
  });
});
