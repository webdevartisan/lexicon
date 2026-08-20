<?php
/**
 * Shared comment thread for every theme's post page.
 *
 * Themes own the section wrapper and the wording; the thread itself lives here
 * so threading, removal, voting and reporting behave identically whichever
 * theme a blog happens to wear.
 *
 * Expects from the controller: $comments, $comments_enabled, $post, $blog,
 * $comment_votes, $can_remove_comment, $can_moderate_comments, $comment_sort,
 * $comment_sort_urls, $viewer_name, $viewer_avatar.
 *
 * A theme sets $comment_labels before including this to keep its own voice;
 * every key falls back to neutral wording. See the defaults below.
 */
$commentList = $comments ?? [];
$commentsOpen = !empty($comments_enabled);
$postId = (int) ($post['id'] ?? 0);
$viewerVotes = $comment_votes ?? [];
$canRemove = $can_remove_comment ?? static fn (array $c): bool => false;
$canModerate = !empty($can_moderate_comments);
$sort = ($comment_sort ?? 'top') === 'new' ? 'new' : 'top';
$sortUrls = $comment_sort_urls ?? ['top' => '?comments=top#comments', 'new' => '?comments=new#comments'];
$viewerIsGuest = !auth()->check();

// The indent steps as far as replies are allowed to nest, no further. The data
// cap is the one that matters, so read it from the model rather than guessing.
$indentCap = App\Models\CommentModel::MAX_DEPTH;

// Replies past this many on one comment start out hidden behind "Show more".
$replyBatch = 3;

/**
 * Total comments in the tree, replies at every depth included.
 *
 * @param  array<int, array<string, mixed>>  $nodes  Thread nodes
 */
$countThread = static function (array $nodes) use (&$countThread): int {
    $total = 0;

    foreach ($nodes as $node) {
        $total += 1 + $countThread($node['replies'] ?? []);
    }

    return $total;
};

$totalComments = $countThread($commentList);

$labels = array_merge([
    'title_prefix' => '',
    'noun' => 'Comment',
    'noun_plural' => 'Comments',
    'form_label' => 'Add a comment…',
    'form_button' => 'Comment',
    'closed' => 'Comments are closed for this post.',
], $comment_labels ?? []);

$commentsHeading = $labels['title_prefix'].number_format($totalComments).' '
    .($totalComments === 1 ? $labels['noun'] : $labels['noun_plural']);

/**
 * A person's picture, or a coloured disc carrying their initial.
 *
 * The hue is derived from the name, so the same person keeps the same colour
 * everywhere without anything being stored.
 *
 * @param  string  $name  Display name
 * @param  string|null  $url  Avatar path; null for guests and private profiles
 */
$avatar = static function (string $name, ?string $url): string {
    if (!empty($url)) {
        return '<span class="comment-avatar"><img src="'.e($url).'" alt="" loading="lazy"></span>';
    }

    $initial = mb_strtoupper(mb_substr(trim($name) !== '' ? $name : '?', 0, 1));
    $hue = crc32($name) % 360;

    return '<span class="comment-avatar is-letter" style="--avatar-hue: '.$hue.'">'.e($initial).'</span>';
};

/**
 * Render one comment and everything hanging off it.
 *
 * Recursive rather than two hard-coded levels: a reply at depth six is the
 * same object as a reply at depth one and deserves the same affordances. The
 * only thing depth changes is how far it sits from the left edge, and that
 * stops moving at $indentCap.
 *
 * @param  array<string, mixed>  $comment  Node from the threaded query
 * @param  int  $depth  0 for a top-level comment
 */
$renderComment = static function (array $comment, int $depth) use (
    &$renderComment, $postId, $commentsOpen, $viewerVotes, $canRemove,
    $canModerate, $viewerIsGuest, $indentCap, $replyBatch, $avatar
): void {
    $id = (int) $comment['id'];
    $removed = !empty($comment['deleted_at']);
    $pinned = !empty($comment['pinned_at']);
    $authorName = (string) ($comment['user_name'] ?? 'Guest');
    $myVote = (int) ($viewerVotes[$id] ?? 0);
    $up = (int) ($comment['upvotes'] ?? 0);
    $replies = $comment['replies'] ?? [];

    // The @mention earns its place from depth two down: at depth one the
    // comment sits directly under what it answered, so naming it is noise.
    // Past the indent cap it is the only thing left pointing at the parent.
    $answeredId = (int) ($comment['answered_id'] ?? 0);
    $answeredGone = !empty($comment['answered_deleted_at']);
    $showMention = $depth >= 2 && $answeredId > 0 && (!empty($comment['parent_name']) || $answeredGone);

    // A removed target keeps the pointer and loses the name. Dropping the whole
    // mention would leave the reply reading as an opening remark, which is the
    // one thing it is not.
    $mentionLabel = $answeredGone
        ? 'in reply to [removed]'
        : '@'.(string) ($comment['parent_name'] ?? '');

    // A removed comment keeps its slot and its timestamp so the replies under
    // it still read in order, but it stops being a person saying something:
    // no name, no body, nothing to vote on or answer.
    $removedText = ($comment['deleted_by'] ?? 'author') === 'moderator'
        ? 'This comment was removed by a moderator.'
        : 'This comment was deleted by its author.';
    ?>
    <li class="comment-item<?= $depth === 0 ? ' reveal' : '' ?><?= $removed ? ' is-removed' : '' ?>" id="comment-<?= $id ?>">
      <?php if ($pinned && !empty($comment['pinned_by_name'])) { ?>
        <p class="comment-pinned">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M16 3v2l1.5 1.5V11l2 2v2h-6v6l-1 1-1-1v-6h-6v-2l2-2V6.5L9 5V3z"/></svg>
          Pinned by @<?= e($comment['pinned_by_name']) ?>
        </p>
      <?php } ?>

      <div class="comment-row">
        <?= $removed ? '<span class="comment-avatar is-ghost"></span>' : $avatar($authorName, $comment['author_avatar'] ?? null) ?>

        <div class="comment-main">
          <?php
            // What this person said, kept in one box so the deep-link flash can
            // land on it without washing over the replies hanging underneath.
          ?>
          <div class="comment-block">
            <p class="comment-meta">
              <?php if ($removed) { ?>
                <strong class="comment-ghost">[removed]</strong>
              <?php } else { ?>
                <strong><?= profile_link($authorName, $comment['author_profile_slug'] ?? null) ?></strong>
              <?php } ?>
              <?php if (!empty($comment['created_at'])) { ?>
                <time><?= e(relative_time($comment['created_at'])) ?></time>
              <?php } ?>
            </p>

            <?php if ($removed) { ?>
              <p class="comment-body comment-removed"><?= e($removedText) ?></p>
            <?php } else { ?>
              <p class="comment-body">
                <?php if ($showMention) { ?><a class="comment-mention<?= $answeredGone ? ' is-gone' : '' ?>" href="#comment-<?= $answeredId ?>"><?= e($mentionLabel) ?></a> <?php } ?>
                <?= nl2br(e($comment['content'] ?? '')) ?>
              </p>

              <div class="comment-actions">
                <div class="comment-votes" data-comment-id="<?= $id ?>">
                  <button type="button" class="comment-vote<?= $myVote === 1 ? ' is-active' : '' ?>"
                          data-vote="up" aria-pressed="<?= $myVote === 1 ? 'true' : 'false' ?>"
                          aria-label="Agree with this comment">
                    <svg viewBox="0 0 24 24" fill="<?= $myVote === 1 ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 22V10l5-8a2.5 2.5 0 0 1 2.4 3.2L13.5 9H19a2.5 2.5 0 0 1 2.4 3.2l-2 7A2.5 2.5 0 0 1 17 22z"/><path d="M7 10H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/></svg>
                    <span data-vote-count="up"><?= $up > 0 ? number_format($up) : '' ?></span>
                  </button>
                  <?php
                    // No count on the down vote, the way YouTube settled it in
                    // 2021: a public dislike tally invited pile-ons and told
                    // readers nothing.
                ?>
                  <button type="button" class="comment-vote<?= $myVote === -1 ? ' is-active' : '' ?>"
                          data-vote="down" aria-pressed="<?= $myVote === -1 ? 'true' : 'false' ?>"
                          aria-label="Disagree with this comment">
                    <svg viewBox="0 0 24 24" fill="<?= $myVote === -1 ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 2v12l-5 8a2.5 2.5 0 0 1-2.4-3.2l.9-3.8H5a2.5 2.5 0 0 1-2.4-3.2l2-7A2.5 2.5 0 0 1 7 2z"/><path d="M17 14h3a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1h-3"/></svg>
                  </button>
                </div>

                <?php if ($commentsOpen) { ?>
                  <button type="button" class="reply-toggle" data-reply-toggle="<?= $id ?>">Reply</button>
                <?php } ?>
              </div>

              <?php if ($commentsOpen) { ?>
                <form class="reply-form" id="reply-form-<?= $id ?>" action="/comments/create" method="post" hidden>
                  <?= csrf_field() ?>
                  <input type="hidden" name="post_id" value="<?= $postId ?>">
                  <input type="hidden" name="parent_comment_id" value="<?= $id ?>">
                  <textarea name="content" rows="1" maxlength="2000"
                            placeholder="Reply to <?= e($authorName) ?>…" required></textarea>
                  <?php if ($viewerIsGuest) { ?>
                    <p class="comment-form-note">You'll be asked to log in — your reply is kept.</p>
                  <?php } ?>
                  <div class="comment-form-actions">
                    <button type="button" class="reply-toggle reply-cancel" data-reply-cancel="<?= $id ?>">Cancel</button>
                    <button type="submit" class="btn-reply">Reply</button>
                  </div>
                </form>
              <?php } ?>
            <?php } ?>
          </div>

          <?php if ($replies !== []) { ?>
            <?php
            $replyTotal = count($replies);
              $replyWord = $replyTotal === 1 ? 'reply' : 'replies';
              $childDepth = $depth + 1;
              $hidden = max(0, $replyTotal - $replyBatch);
              ?>
            <button type="button" class="reply-toggle replies-toggle" aria-expanded="false"
                    aria-controls="replies-<?= $id ?>"
                    data-collapse-toggle="<?= $id ?>"
                    data-label-show="<?= $replyTotal ?> <?= $replyWord ?>"
                    data-label-hide="Hide <?= $replyWord ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
              <span data-collapse-label><?= $replyTotal ?> <?= $replyWord ?></span>
            </button>

            <ol class="comment-replies<?= $childDepth > $indentCap ? ' is-flush' : '' ?>" id="replies-<?= $id ?>" hidden>
              <?php foreach ($replies as $index => $reply) { ?>
                <?php
                  // Replies past the first batch are rendered but held back, so
                  // "Show more" costs nothing at click time and still works for a
                  // reader who arrived on a deep link into one of them.
                  $batched = $index >= $replyBatch;
                  ob_start();
                  $renderComment($reply, $childDepth);
                  $markup = (string) ob_get_clean();

                  echo $batched
                      ? preg_replace('/^(\s*)<li /', '$1<li hidden data-batched="'.$id.'" ', $markup, 1)
                      : $markup;
                  ?>
              <?php } ?>

              <?php if ($hidden > 0) { ?>
                <li class="comment-more" data-more-row="<?= $id ?>">
                  <button type="button" class="reply-toggle" data-show-more="<?= $id ?>">
                    Show <?= $hidden ?> more <?= $hidden === 1 ? 'reply' : 'replies' ?>
                  </button>
                </li>
              <?php } ?>
            </ol>
          <?php } ?>
        </div>

        <?php if (!$removed) { ?>
          <div class="comment-menu" data-comment-menu>
            <button type="button" class="comment-menu-button" data-menu-toggle
                    aria-haspopup="true" aria-expanded="false" aria-label="More actions on this comment">
              <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            </button>

            <div class="comment-menu-list" data-menu-list hidden>
              <button type="button" data-copy-comment data-anchor="#comment-<?= $id ?>">Copy link</button>

              <?php if (!$viewerIsGuest) { ?>
                <button type="button" data-report-comment data-comment-id="<?= $id ?>">Report</button>
              <?php } ?>

              <?php if ($canModerate && $depth === 0) { ?>
                <form action="/comments/<?= $id ?>/pin" method="post">
                  <?= csrf_field() ?>
                  <button type="submit"><?= $pinned ? 'Unpin' : 'Pin to top' ?></button>
                </form>
              <?php } ?>

              <?php if ($canRemove($comment)) { ?>
                <form action="/comments/<?= $id ?>/delete" method="post"
                      data-confirm="Remove this comment? Any replies to it stay in the thread.">
                  <?= csrf_field() ?>
                  <button type="submit" class="is-destructive">Remove</button>
                </form>
              <?php } ?>
            </div>
          </div>
        <?php } ?>
      </div>
    </li>
  <?php
};
?>

<style>
  /* Structure and rhythm only. Colour and type family come from whichever
     theme is mounted, which is why everything below leans on currentColor and
     relative sizes. */

  /* ---- rhythm ----------------------------------------------------------
     Everything belonging to one comment sits tight; the air goes between
     comments. That spacing is what separates them, not a rule. */
  .comment-thread { list-style: none; margin: 0; padding: 0; }
  .comment-item { position: relative; margin: 0 0 1.75rem; padding: 0; border: 0; }
  .comment-replies .comment-item { margin-bottom: 1.15rem; }
  .comment-thread > .comment-item:last-child { margin-bottom: 0; }

  .comment-row { display: flex; align-items: flex-start; gap: .75rem; }
  .comment-main { flex: 1 1 auto; min-width: 0; }

  .comment-avatar {
    flex: none; width: 2.25rem; height: 2.25rem; border-radius: 50%;
    overflow: hidden; display: grid; place-items: center;
  }
  .comment-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .comment-avatar.is-letter {
    background: hsl(var(--avatar-hue, 210) 42% 42%); color: #fff;
    font-size: .95rem; font-weight: 600; line-height: 1;
  }
  .comment-avatar.is-ghost { background: currentColor; opacity: .12; }
  .comment-replies .comment-avatar { width: 1.75rem; height: 1.75rem; }
  .comment-replies .comment-avatar.is-letter { font-size: .78rem; }

  .comment-pinned {
    display: flex; align-items: center; gap: .3rem;
    margin: 0 0 .3rem; font-size: .66em; letter-spacing: .02em; opacity: .6;
  }
  .comment-pinned svg { width: 11px; height: 11px; }

  .comment-item .comment-meta { margin: 0 0 .15rem; font-size: .78em; line-height: 1.35; }
  .comment-item .comment-meta time { margin-left: .45rem; opacity: .55; font-size: .88em; }
  .comment-item .comment-body { margin: 0; line-height: 1.55; }
  .comment-mention { font-weight: 600; text-decoration: none; color: var(--comment-accent, currentColor); }
  .comment-mention:hover { text-decoration: underline; }
  .comment-removed { font-style: italic; opacity: .6; }
  .comment-ghost { opacity: .6; }
  .comment-item.is-removed .comment-meta { opacity: .6; }
  .comment-mention.is-gone { font-weight: 400; font-style: italic; opacity: .55; }

  /* ---- deep-linked comment ---------------------------------------------
     The flash lands on the comment's own block, not on its <li>: a top-level
     comment carries its entire reply tree inside that element, and washing a
     screenful of other people's answers in colour to point at one of them
     helps nobody. What looks like padding is a spread shadow, so nothing in
     the thread moves when the flash fires or clears. */
  .comment-block { border-radius: 10px; }

  .comment-target > .comment-row > .comment-main > .comment-block {
    --flash: var(--comment-highlight, color-mix(in srgb, var(--comment-accent, currentColor) 14%, transparent));
    animation: comment-target-flash 3s ease-out forwards;
  }

  @keyframes comment-target-flash {
    from { background: transparent; box-shadow: 0 0 0 .6rem transparent; }
    12%, 62% { background: var(--flash); box-shadow: 0 0 0 .6rem var(--flash); }
    to { background: transparent; box-shadow: 0 0 0 .6rem transparent; }
  }

  .comment-actions { display: flex; align-items: center; flex-wrap: wrap; gap: .9rem; margin-top: .3rem; }
  .comment-votes { display: inline-flex; align-items: center; gap: .25rem; margin-left: -.4rem; }
  .comment-vote { display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .4rem; border: 0; border-radius: 999px; background: none; color: inherit; opacity: .65; font: inherit; font-size: .8em; line-height: 1; cursor: pointer; transition: opacity .15s ease, background .15s ease; }
  .comment-vote:hover { opacity: 1; background: rgba(128, 128, 128, .14); }
  .comment-vote svg { width: 15px; height: 15px; display: block; }
  .comment-vote.is-active { opacity: 1; color: var(--comment-accent, currentColor); }
  .comment-vote span { min-width: .5em; font-variant-numeric: tabular-nums; }
  .comment-vote[data-vote="down"] svg { transform: translateY(1px); }
  .comment-item .reply-toggle { font-size: .78em; }

  /* ---- the connector tree ----------------------------------------------
     Drawn per reply rather than as one rule down the whole list: the rail is
     the left edge of each reply's elbow, and only replies that have a sibling
     below them continue it. That is what stops the line overshooting past the
     last reply, which a single full-height rule cannot avoid. */
  .comment-replies {
    --ct-gutter: 2.25rem;  /* one indent step */
    --ct-rail: .7rem;      /* rail offset inside that step */
    --ct-elbow: .9rem;     /* drop to the vertical centre of a reply's avatar */
    --ct-gap: .9rem;       /* space between a comment and its first reply */
    list-style: none;
    position: relative;
    margin: var(--ct-gap) 0 0;
    padding: 0 0 0 var(--ct-gutter);
    border-left: 0;
  }

  /* Bridges the gap between a comment and the first reply's elbow. */
  .comment-replies::before {
    content: ""; position: absolute; left: var(--ct-rail);
    top: calc(var(--ct-gap) * -1); height: var(--ct-gap);
    border-left: 2px solid currentColor; opacity: .16;
  }

  .comment-replies > .comment-item::before,
  .comment-replies > .comment-item::after {
    content: ""; position: absolute;
    left: calc(var(--ct-rail) - var(--ct-gutter));
    border-left: 2px solid currentColor; opacity: .16;
  }

  /* Down from the rail and curving into this reply. */
  .comment-replies > .comment-item::before {
    top: 0; height: var(--ct-elbow); width: calc(var(--ct-gutter) * .5);
    border-bottom: 2px solid currentColor;
    border-bottom-left-radius: 9px;
  }

  /* Carries the rail on to the next sibling; the last reply ends the branch.
     Reaching into the sibling gap keeps the line unbroken between replies. */
  .comment-replies > .comment-item::after { top: var(--ct-elbow); bottom: -1.15rem; }
  .comment-replies > .comment-item:last-child::after { display: none; }

  /* Past the indent cap the elbows would march off the right edge, so the
     @mention takes over the job of naming the parent. */
  .comment-replies.is-flush { padding-left: 0; }
  .comment-replies.is-flush::before,
  .comment-replies.is-flush > .comment-item::before,
  .comment-replies.is-flush > .comment-item::after { display: none; }

  /* Not a comment, so it gets no connector and must not end the branch. */
  .comment-more { list-style: none; margin: 0; }

  /* ---- header ---------------------------------------------------------- */
  .comment-thread-head { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
  .comment-thread-head .comments-title { margin: 0; }

  /* A details element rather than a scripted dropdown: it opens, closes and
     takes keyboard focus on its own, so the control still works with no JS. */
  .comment-sort { position: relative; font-size: .82em; }
  .comment-sort > summary { display: inline-flex; align-items: center; gap: .5rem; padding: .3rem .1rem; list-style: none; cursor: pointer; opacity: .75; }
  .comment-sort > summary::-webkit-details-marker { display: none; }
  .comment-sort > summary:hover { opacity: 1; }
  .comment-sort > summary svg { width: 16px; height: 16px; }
  .comment-sort-list { position: absolute; top: calc(100% + 4px); left: 0; z-index: 40; min-width: 250px; padding: .35rem; border-radius: 8px; background: var(--comment-menu-bg, #1c1c1c); box-shadow: 0 8px 26px rgba(0, 0, 0, .3); }
  .comment-sort-list a { display: block; padding: .6rem .75rem; border-radius: 5px; color: var(--comment-menu-fg, #f2f2f2); text-decoration: none; }
  .comment-sort-list a:hover, .comment-sort-list a.is-current { background: rgba(128, 128, 128, .22); }
  .comment-sort-list strong { display: block; font-weight: 600; }
  .comment-sort-list span { display: block; margin-top: .1rem; font-size: .88em; opacity: .6; }

  /* ---- composer --------------------------------------------------------
     One quiet line until it is wanted. JS collapses it on load, so with
     scripting off the whole form is simply there and works. */
  .comment-form { margin: 0 0 2.25rem; }
  .comment-form h4 { display: none; }
  .comment-form textarea,
  .reply-form textarea {
    width: 100%; padding: .35rem 0; border: 0; border-bottom: 1px solid currentColor;
    border-radius: 0; background: transparent; color: inherit; font: inherit;
    line-height: 1.5; resize: none; overflow: hidden;
  }
  .comment-form textarea::placeholder,
  .reply-form textarea::placeholder { opacity: .5; }
  .comment-form textarea:focus,
  .reply-form textarea:focus { outline: 0; border-bottom-width: 2px; }
  .comment-form-note { margin: .6rem 0 0; font-size: .78em; opacity: .6; }
  .comment-form-actions { display: flex; align-items: center; justify-content: flex-end; gap: 1rem; margin-top: .7rem; }

  .comment-form.is-collapsed .comment-form-note,
  .comment-form.is-collapsed .comment-form-actions { display: none; }
  .comment-form.is-collapsed .comment-avatar { opacity: .55; }

  .reply-form { margin-top: .65rem; }
  .replies-toggle { display: inline-flex; align-items: center; gap: .4rem; margin-top: .5rem; }
  .replies-toggle svg { width: 13px; height: 13px; transition: transform .18s ease; }
  .replies-toggle[aria-expanded="true"] svg { transform: rotate(180deg); }

  .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }

  /* ---- overflow menu --------------------------------------------------- */
  .comment-menu { flex: none; position: relative; margin-left: auto; }
  .comment-menu-button { display: block; padding: .25rem; border: 0; border-radius: 999px; background: none; color: inherit; opacity: 0; cursor: pointer; transition: opacity .15s ease; }
  .comment-menu-button svg { width: 16px; height: 16px; display: block; }
  .comment-item:hover > .comment-row > .comment-menu .comment-menu-button,
  .comment-menu-button:focus-visible,
  .comment-menu-button[aria-expanded="true"] { opacity: .7; }
  .comment-menu-button:hover { opacity: 1; }
  .comment-menu-list { position: absolute; top: calc(100% + 4px); right: 0; z-index: 40; min-width: 170px; padding: .3rem; border-radius: 6px; background: var(--comment-menu-bg, #1c1c1c); box-shadow: 0 8px 26px rgba(0, 0, 0, .3); }
  .comment-menu-list form { margin: 0; }
  .comment-menu-list button { display: block; width: 100%; padding: .5rem .6rem; border: 0; border-radius: 4px; background: none; color: var(--comment-menu-fg, #f2f2f2); font: inherit; font-size: .85em; text-align: left; cursor: pointer; }
  .comment-menu-list button:hover { background: rgba(128, 128, 128, .22); }
  .comment-menu-list button.is-destructive { color: #ff6b6b; }

  @media (max-width: 640px) {
    /* A phone has room for a narrower step; the tree still reads, the text
       column survives. Only the tokens move, the geometry follows. */
    .comment-replies { --ct-gutter: 1.3rem; --ct-rail: .35rem; }
    .comment-avatar { width: 1.85rem; height: 1.85rem; }
    .comment-menu-button { opacity: .7; }
  }

  @media (prefers-reduced-motion: reduce) {
    .comment-vote, .replies-toggle svg, .comment-menu-button { transition: none; }

    /* No fade, but the reader still has to be shown which comment they came
       for, so the tint is simply left on. */
    .comment-target > .comment-row > .comment-main > .comment-block {
      animation: none;
      background: var(--flash);
      box-shadow: 0 0 0 .6rem var(--flash);
    }
  }
</style>

<div class="comment-thread-root" id="comments"
     data-auth="<?= $viewerIsGuest ? '0' : '1' ?>" data-login="<?= e(lurl('/login')) ?>">

  <div class="comment-thread-head">
    <h2 class="comments-title"><?= $commentsHeading ?></h2>

    <?php if ($totalComments > 1) { ?>
      <details class="comment-sort">
        <summary>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M6 12h12M9 17h6"/></svg>
          Sort by
        </summary>
        <div class="comment-sort-list">
          <a href="<?= e($sortUrls['top']) ?>"<?= $sort === 'top' ? ' class="is-current" aria-current="true"' : '' ?>>
            <strong>Top</strong>
            <span>Highest rated first</span>
          </a>
          <a href="<?= e($sortUrls['new']) ?>"<?= $sort === 'new' ? ' class="is-current" aria-current="true"' : '' ?>>
            <strong>Newest</strong>
            <span>Most recent first</span>
          </a>
        </div>
      </details>
    <?php } ?>
  </div>

  <?php if ($commentsOpen) { ?>
    <form class="comment-form" action="/comments/create" method="post" data-comment-composer>
      <?= csrf_field() ?>
      <input type="hidden" name="post_id" value="<?= $postId ?>">

      <div class="comment-row">
        <?= $avatar((string) ($viewer_name ?? 'Guest'), $viewer_avatar ?? null) ?>

        <div class="comment-main">
          <label class="sr-only" for="comment_content"><?= $labels['form_label'] ?></label>
          <textarea id="comment_content" name="content" rows="1" maxlength="2000"
                    placeholder="<?= $labels['form_label'] ?>" required></textarea>

          <p class="comment-form-note">
            <?php if ($viewerIsGuest) { ?>
              You'll be asked to log in — what you wrote is kept.
            <?php } else { ?>
              Commenting as <?= e((string) ($viewer_name ?? '')) ?>.
            <?php } ?>
          </p>

          <div class="comment-form-actions">
            <button type="button" class="reply-toggle" data-composer-cancel>Cancel</button>
            <button type="submit" class="btn-submit"><?= $labels['form_button'] ?></button>
          </div>
        </div>
      </div>
    </form>
  <?php } else { ?>
    <p class="comments-closed"><?= $labels['closed'] ?></p>
  <?php } ?>

  <?php if ($totalComments > 0) { ?>
    <ol class="comment-thread">
      <?php foreach ($commentList as $comment) { ?>
        <?php $renderComment($comment, 0); ?>
      <?php } ?>
    </ol>
  <?php } ?>

  <form class="comment-token" hidden aria-hidden="true"><?= csrf_field() ?></form>
</div>

<script defer src="/assets/js/comments.js"></script>
