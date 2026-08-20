<?php
// Engagement bar: like, bookmark, share. Expects $post, $engagement, $share_url.
$engagement = $engagement ?? ['like_count' => 0, 'bookmark_count' => 0, 'my_vote' => 0, 'bookmarked' => false, 'logged_in' => false];
$myVote = (int) ($engagement['my_vote'] ?? 0);
$shareUrl = $share_url ?? '';
$shareTitle = $post['title'] ?? '';
$encodedUrl = rawurlencode($shareUrl);
$encodedTitle = rawurlencode($shareTitle);
$loginUrl = e(lurl('/login'));
?>
<div class="post-engagement" data-auth="<?= $engagement['logged_in'] ? '1' : '0' ?>" data-login="<?= $loginUrl ?>">
  <button type="button" class="engage-btn<?= $myVote === 1 ? ' is-active' : '' ?>"
          data-engage="up" data-url="/posts/<?= (int) ($post['id'] ?? 0) ?>/vote"
          aria-pressed="<?= $myVote === 1 ? 'true' : 'false' ?>" aria-label="Agree with this post">
    <svg viewBox="0 0 24 24" fill="<?= $myVote === 1 ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 22V10l5-8a2.5 2.5 0 0 1 2.4 3.2L13.5 9H19a2.5 2.5 0 0 1 2.4 3.2l-2 7A2.5 2.5 0 0 1 17 22z"/><path d="M7 10H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/></svg>
    <span data-count><?= (int) $engagement['like_count'] ?></span>
  </button>

  <?php // No count on the down vote: it registers the signal without handing
        // readers a pile-on to watch. Same call as the comment thread.?>
  <button type="button" class="engage-btn<?= $myVote === -1 ? ' is-active' : '' ?>"
          data-engage="down" data-url="/posts/<?= (int) ($post['id'] ?? 0) ?>/vote"
          aria-pressed="<?= $myVote === -1 ? 'true' : 'false' ?>" aria-label="Disagree with this post">
    <svg viewBox="0 0 24 24" fill="<?= $myVote === -1 ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 2v12l-5 8a2.5 2.5 0 0 1-2.4-3.2l.9-3.8H5a2.5 2.5 0 0 1-2.4-3.2l2-7A2.5 2.5 0 0 1 7 2z"/><path d="M17 14h3a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1h-3"/></svg>
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

  <div class="engage-menu" data-engage-menu>
    <button type="button" class="engage-btn" data-engage-menu-toggle
            aria-haspopup="true" aria-expanded="false" aria-label="More actions on this post">
      <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
    </button>
    <div class="engage-menu-list" data-engage-menu-list hidden>
      <button type="button" data-report-post data-url="/posts/<?= (int) ($post['id'] ?? 0) ?>/report">Report</button>
    </div>
  </div>

  <form id="engage-token" hidden aria-hidden="true"><?= csrf_field() ?></form>
</div>

<script defer src="<?= $asset('js/engagement.js') ?>"></script>
