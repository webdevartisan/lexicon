<?php
// Engagement bar: like, bookmark, share. Expects $post, $engagement, $share_url.
$engagement = $engagement ?? ['like_count' => 0, 'bookmark_count' => 0, 'liked' => false, 'bookmarked' => false, 'logged_in' => false];
$shareUrl = $share_url ?? '';
$shareTitle = $post['title'] ?? '';
$encodedUrl = rawurlencode($shareUrl);
$encodedTitle = rawurlencode($shareTitle);
$loginUrl = e(lurl('/login'));
?>
<style>
  .post-engagement { display: flex; align-items: center; gap: .5rem; padding: 1.25rem 0; margin-top: 1.5rem; border-top: 1px solid #e8e8e8; position: relative; }
  .post-engagement .engage-btn { display: inline-flex; align-items: center; gap: .4rem; padding: .45rem 1rem; border: 1px solid #d9d9d9; border-radius: 999px; background: transparent; color: #555; font-size: 14px; line-height: 1; cursor: pointer; transition: all .15s ease; }
  .post-engagement .engage-btn:hover { background: rgba(0,0,0,.05); }
  .post-engagement .engage-btn svg { width: 18px; height: 18px; display: block; }
  .post-engagement .engage-btn.is-active { border-color: currentColor; }
  .post-engagement .engage-btn[data-engage="like"].is-active { color: #e0245e; }
  .post-engagement .engage-btn[data-engage="like"].is-active svg { fill: #e0245e; }
  .post-engagement .engage-btn[data-engage="bookmark"].is-active { color: #2271b1; }
  .post-engagement .engage-btn[data-engage="bookmark"].is-active svg { fill: #2271b1; }
  .share-popover { position: absolute; bottom: calc(100% + 8px); right: 0; min-width: 200px; background: #fff; border: 1px solid #eee; border-radius: 10px; box-shadow: 0 8px 24px rgba(0,0,0,.12); padding: .4rem; z-index: 50; }
  .share-popover a, .share-popover button { display: flex; align-items: center; gap: .55rem; width: 100%; padding: .5rem .65rem; border: 0; background: none; border-radius: 6px; color: #444; font-size: 13px; text-decoration: none; cursor: pointer; text-align: left; transition: background .12s ease; }
  .share-popover a:hover, .share-popover button:hover { background: rgba(0,0,0,.06); text-decoration: none; }
  .share-popover svg { width: 16px; height: 16px; flex: none; }
  .comment-replies { list-style: none; margin: .75rem 0 0; padding: 0 0 0 2.5rem; border-left: 2px solid #f0f0f0; }
  .comment-replies .media-body { font-size: 95%; }
  .reply-toggle { font-size: 12px; color: #888; background: none; border: 0; padding: 0; cursor: pointer; }
  .reply-toggle:hover { color: #333; text-decoration: underline; }
  .reply-form { margin-top: .6rem; }
  .reply-form textarea { width: 100%; }
  .reply-form .btn { margin-top: .4rem; }
  .reply-form .reply-cancel { font-size: 12px; color: #888; margin-left: .75rem; }
</style>

<div class="post-engagement" data-auth="<?= $engagement['logged_in'] ? '1' : '0' ?>" data-login="<?= $loginUrl ?>">
  <button type="button" class="engage-btn<?= $engagement['liked'] ? ' is-active' : '' ?>"
          data-engage="like" data-url="/posts/<?= (int) ($post['id'] ?? 0) ?>/like"
          aria-pressed="<?= $engagement['liked'] ? 'true' : 'false' ?>" aria-label="Like this post">
    <svg viewBox="0 0 24 24" fill="<?= $engagement['liked'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
    <span data-count><?= (int) $engagement['like_count'] ?></span>
  </button>

  <button type="button" class="engage-btn<?= $engagement['bookmarked'] ? ' is-active' : '' ?>"
          data-engage="bookmark" data-url="/posts/<?= (int) ($post['id'] ?? 0) ?>/bookmark"
          aria-pressed="<?= $engagement['bookmarked'] ? 'true' : 'false' ?>" aria-label="Bookmark this post">
    <svg viewBox="0 0 24 24" fill="<?= $engagement['bookmarked'] ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
    <span><?= $engagement['bookmarked'] ? 'Saved' : 'Save' ?></span>
  </button>

  <button type="button" class="engage-btn" data-share-toggle aria-haspopup="true" aria-expanded="false" aria-label="Share this post">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
    <span>Share</span>
  </button>

  <div class="share-popover" data-share-popover hidden>
    <button type="button" data-copy-link data-link="<?= e($shareUrl) ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
      <span data-copy-label>Copy link</span>
    </button>
    <a href="https://twitter.com/intent/tweet?url=<?= e($encodedUrl) ?>&text=<?= e($encodedTitle) ?>" target="_blank" rel="noopener noreferrer">
      <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
      <span>Share on X</span>
    </a>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= e($encodedUrl) ?>" target="_blank" rel="noopener noreferrer">
      <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
      <span>Share on Facebook</span>
    </a>
    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= e($encodedUrl) ?>" target="_blank" rel="noopener noreferrer">
      <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 1 1 0-4.125 2.062 2.062 0 0 1 0 4.125zM7.119 20.452H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
      <span>Share on LinkedIn</span>
    </a>
  </div>

  <form id="engage-token" hidden aria-hidden="true"><?= csrf_field() ?></form>
</div>

<script>
(function () {
  var bar = document.querySelector('.post-engagement');
  if (!bar) return;

  var loggedIn = bar.getAttribute('data-auth') === '1';
  var loginUrl = bar.getAttribute('data-login');
  var tokenInput = document.querySelector('#engage-token input[name="_token"]');
  var popover = bar.querySelector('[data-share-popover]');
  var shareBtn = bar.querySelector('[data-share-toggle]');

  bar.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-engage]');
    if (!btn) return;

    if (!loggedIn) { window.location = loginUrl; return; }
    if (btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';

    var body = new FormData();
    body.append('_token', tokenInput ? tokenInput.value : '');

    fetch(btn.getAttribute('data-url'), { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var data = json.data || json;
        var active = !!data.active;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.querySelector('svg').setAttribute('fill', active ? 'currentColor' : 'none');

        if (btn.getAttribute('data-engage') === 'like') {
          btn.querySelector('[data-count]').textContent = data.count;
        } else {
          btn.querySelector('span:last-child').textContent = active ? 'Saved' : 'Save';
        }
      })
      .catch(function () { /* leave state as rendered; next click retries */ })
      .finally(function () { btn.dataset.busy = '0'; });
  });

  if (shareBtn && popover) {
    shareBtn.addEventListener('click', function () {
      var open = !popover.hasAttribute('hidden');
      popover.toggleAttribute('hidden', open);
      shareBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
    });

    document.addEventListener('click', function (ev) {
      if (!popover.hasAttribute('hidden') && !bar.contains(ev.target)) {
        popover.setAttribute('hidden', '');
        shareBtn.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !popover.hasAttribute('hidden')) {
        popover.setAttribute('hidden', '');
        shareBtn.setAttribute('aria-expanded', 'false');
      }
    });

    var copyBtn = popover.querySelector('[data-copy-link]');
    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        var link = copyBtn.getAttribute('data-link');
        var label = copyBtn.querySelector('[data-copy-label]');
        var done = function () {
          label.textContent = 'Copied!';
          setTimeout(function () { label.textContent = 'Copy link'; }, 1500);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(link).then(done);
        } else {
          var tmp = document.createElement('textarea');
          tmp.value = link;
          document.body.appendChild(tmp);
          tmp.select();
          document.execCommand('copy');
          document.body.removeChild(tmp);
          done();
        }
      });
    }
  }
})();
</script>
