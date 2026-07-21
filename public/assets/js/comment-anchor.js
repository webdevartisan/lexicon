/**
 * Makes #comment-<id> deep links land on the right comment.
 *
 * Replies render inside a hidden container and top-level comments start at
 * opacity 0 until the reveal observer fires, so a plain fragment jump either
 * cannot scroll or arrives at blank space. This expands the thread, forces the
 * target visible, scrolls with a header offset, then flashes a highlight.
 */
(function () {
  'use strict';

  var HIGHLIGHT_MS = 3000;
  var HEADER_OFFSET = 80;
  var highlightTimer = null;

  function prefersReducedMotion() {
    return window.matchMedia
      && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  /** Un-hide the reply thread containing the target and correct its toggle label. */
  function expandThread(el) {
    var container = el.closest('.comment-replies[hidden]');
    if (!container) {
      return;
    }

    container.removeAttribute('hidden');

    var parentId = container.id.replace('replies-', '');
    var toggle = document.querySelector('[data-collapse-toggle="' + parentId + '"]');
    var hideLabel = toggle && toggle.getAttribute('data-label-hide');

    // Without this the toggle still reads "View 3 replies" over an open thread
    if (hideLabel) {
      toggle.textContent = hideLabel;
    }
  }

  /** Paint the target now instead of waiting for the scroll observer. */
  function forceReveal(el) {
    var node = el;

    while (node && node !== document.body) {
      if (node.classList && node.classList.contains('reveal')) {
        node.classList.add('is-in');
      }
      node = node.parentElement;
    }
  }

  function highlight(el) {
    if (highlightTimer) {
      window.clearTimeout(highlightTimer);
    }

    el.classList.remove('comment-target');
    // Force a reflow so re-targeting the same comment restarts the animation
    void el.offsetWidth;
    el.classList.add('comment-target');

    highlightTimer = window.setTimeout(function () {
      el.classList.remove('comment-target');
      highlightTimer = null;
    }, HIGHLIGHT_MS);
  }

  function scrollToTarget(el, smooth) {
    var top = el.getBoundingClientRect().top + window.pageYOffset - HEADER_OFFSET;

    window.scrollTo({
      top: top < 0 ? 0 : top,
      behavior: smooth ? 'smooth' : 'auto'
    });
  }

  function targetFromHash() {
    var match = /^#comment-(\d+)$/.exec(window.location.hash);

    return match ? document.getElementById('comment-' + match[1]) : null;
  }

  function go(smooth, withHighlight) {
    var el = targetFromHash();

    // Comment may be deleted, unapproved, or on another post
    if (!el) {
      return;
    }

    expandThread(el);
    forceReveal(el);
    el.classList.add('is-in');
    scrollToTarget(el, smooth && !prefersReducedMotion());

    if (withHighlight) {
      highlight(el);
    }
  }

  // Smooth scrolling issued during load gets cancelled, so the first pass is
  // always instant; a second pass after load corrects for late-loading images
  function onReady() {
    go(false, true);
    window.addEventListener('load', function () {
      go(false, false);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }

  window.addEventListener('hashchange', function () {
    go(true, true);
  });
})();
