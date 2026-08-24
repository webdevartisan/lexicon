{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.profile.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
<link rel="stylesheet" href="/assets/vendor/cropper/cropper.min.css" />
<style>
  /* Cropper-specific bits; the generic .lx-modal styles live in lexicon.css. */
  #avatar-cropper-modal .lx-modal-panel{max-inline-size:32rem}
  .lx-cropper-stage{max-height:60vh;overflow:hidden;background:var(--lx-paper-sunk);border-radius:var(--lx-radius)}
  .lx-cropper-stage img{max-width:100%;display:block}
  .lx-modal .cropper-view-box,.lx-modal .cropper-face{border-radius:50%}
</style>
{% endblock %}

{% block body %}
<?php
$fieldErrors = errors();
$slug = $user['slug'] ?? '';

/**
 * One field row, reusing the auth pages' lx-field markup. $type covers text /
 * url / textarea; errors set aria-invalid and aria-describedby so the message
 * is announced with the control rather than stranded.
 */
$field = function (string $name, string $label, string $type = 'text', string $value = '', string $placeholder = '', string $hint = '') use ($fieldErrors): void {
    $invalid = !empty($fieldErrors[$name]);
    $describedBy = $invalid ? $name.'_error' : ($hint !== '' ? $name.'_hint' : '');
    ?>
    <div class="lx-field<?= $invalid ? ' lx-field--invalid' : '' ?>">
        <label class="lx-field__label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <?php if ($type === 'textarea') { ?>
        <textarea class="lx-field__input" name="<?= e($name) ?>" id="<?= e($name) ?>" rows="4"
                  <?= $invalid ? 'aria-invalid="true"' : '' ?> <?= $describedBy !== '' ? 'aria-describedby="'.e($describedBy).'"' : '' ?>><?= e(old($name) ?? $value) ?></textarea>
        <?php } else { ?>
        <input class="lx-field__input" type="<?= e($type) ?>" name="<?= e($name) ?>" id="<?= e($name) ?>"
               value="<?= e(old($name) ?? $value) ?>"<?= $placeholder !== '' ? ' placeholder="'.e($placeholder).'"' : '' ?>
               <?= $invalid ? 'aria-invalid="true"' : '' ?> <?= $describedBy !== '' ? 'aria-describedby="'.e($describedBy).'"' : '' ?>>
        <?php } ?>
        <?php if ($hint !== '') { ?><p class="lx-field__hint" id="<?= e($name) ?>_hint"><?= e($hint) ?></p><?php } ?>
        <?php foreach ($fieldErrors[$name] ?? [] as $message) { ?>
        <p class="lx-field__error" id="<?= e($name) ?>_error"><?= e($message) ?></p>
        <?php } ?>
    </div>
<?php };
?>
<section class="lx-wrap lx-account">
    <?php $accountSection = 'profile'; ?>
    <header class="lx-account-head">
        <h1><?= e($t('account.profile.heading')) ?></h1>
        <p class="lx-muted"><?= e($t('account.profile.intro')) ?></p>
    </header>

    {% include "partials/_account_shell.lex.php" %}

    <div class="lx-account-body">
        <form method="post" action="<?= e(lurl('/account/profile/update')) ?>" class="lx-account-form">
            <?= csrf_field() ?>

            <h2 class="lx-account-section"><?= e($t('account.profile.nameSection')) ?></h2>
            <div class="lx-grid-2">
                <?php $field('first_name', $t('account.profile.firstName'), 'text', (string) ($user['first_name'] ?? '')); ?>
                <?php $field('last_name', $t('account.profile.lastName'), 'text', (string) ($user['last_name'] ?? '')); ?>
            </div>

            <h2 class="lx-account-section"><?= e($t('account.profile.aboutSection')) ?></h2>
            <div class="lx-grid-2">
                <?php $field('occupation', $t('account.profile.occupation'), 'text', (string) ($user['occupation'] ?? ''), $t('account.profile.occupationPlaceholder')); ?>
                <?php $field('location', $t('account.profile.location'), 'text', (string) ($user['location'] ?? '')); ?>
            </div>
            <?php $field('bio', $t('account.profile.bio'), 'textarea', (string) ($user['bio'] ?? '')); ?>

            <h2 class="lx-account-section"><?= e($t('account.profile.publicPageSection')) ?></h2>
            <?php $field('public_profile_url', $t('account.profile.publicUrl'), 'text', (string) $slug, $t('account.profile.publicUrlPlaceholder'), $t('account.profile.publicUrlHelp')); ?>

            <label class="lx-check">
                <input type="checkbox" name="is_public" value="1" <?= !empty($user['is_public']) ? 'checked' : '' ?>>
                <span>
                    <span class="lx-check-title"><?= e($t('account.profile.makePublic')) ?></span>
                    <span class="lx-check-help"><?= e($t('account.profile.makePublicHelp')) ?></span>
                </span>
            </label>

            <h2 class="lx-account-section"><?= e($t('account.profile.socialSection')) ?></h2>
            <div class="lx-grid-2">
                <?php $field('website', $t('account.profile.website'), 'url', (string) ($user['website'] ?? '')); ?>
                <?php $field('twitter', $t('account.profile.twitter'), 'url', (string) ($user['twitter'] ?? '')); ?>
                <?php $field('instagram', $t('account.profile.instagram'), 'url', (string) ($user['instagram'] ?? '')); ?>
                <?php $field('linkedin', $t('account.profile.linkedin'), 'url', (string) ($user['linkedin'] ?? '')); ?>
                <?php $field('github', $t('account.profile.github'), 'url', (string) ($user['github'] ?? '')); ?>
            </div>

            <div class="lx-account-actions">
                <button type="reset" class="lx-btn lx-btn--subtle"><?= e($t('account.common.reset')) ?></button>
                <button type="submit" class="lx-btn lx-btn--primary"><?= e($t('account.profile.save')) ?></button>
            </div>
        </form>

        <aside class="lx-account-aside">
            <?php
            $hasAvatar = !empty($user['avatar_url']);
$hasSource = !empty($user['avatar_source_url']);
$isPublic = !empty($user['is_public']) && $slug !== '';
?>
            <section class="lx-card lx-card--avatar">
                <h2 class="lx-card-title"><?= e($t('account.profile.avatarHeading')) ?></h2>

                <div class="lx-avatar" id="avatar-box">
                    <?php if ($hasAvatar) { ?>
                    <img id="avatar-preview" class="lx-avatar-img" src="<?= e($user['avatar_url']) ?>" alt="">
                    <?php } else { ?>
                    <div id="avatar-placeholder" class="lx-avatar-initials"><?= e($user['initials'] ?? '?') ?></div>
                    <?php } ?>
                </div>

                <form id="avatar-form" method="post" action="<?= e(lurl('/account/profile/avatar')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input id="avatar-input" name="avatar" type="file" class="lx-visually-hidden"
                           accept="image/jpeg,image/png,image/webp" data-max-size="20971520">

                    <div class="lx-avatar-controls" role="group" aria-label="<?= e($t('account.profile.avatarHeading')) ?>">
                        <label for="avatar-input" class="lx-icon-btn" id="avatar-upload-btn"
                               title="<?= e($t('account.profile.changeAvatar')) ?>">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 8h3l1.5-2h7L18 8h2a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.2"/>
                            </svg>
                            <span class="lx-visually-hidden"><?= e($t('account.profile.changeAvatar')) ?></span>
                        </label>

                        <button type="button" id="recrop-avatar-btn" class="lx-icon-btn"
                                title="<?= e($t('account.profile.recropAvatar')) ?>"
                                data-source="<?= e($user['avatar_source_url'] ?? '') ?>" data-crop="<?= e($user['avatar_crop'] ?? '') ?>"
                                <?= $hasSource ? '' : 'hidden' ?>>
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M2 6h14a2 2 0 0 1 2 2v14"/>
                            </svg>
                            <span class="lx-visually-hidden"><?= e($t('account.profile.recropAvatar')) ?></span>
                        </button>

                        <button type="button" id="remove-avatar-btn" class="lx-icon-btn lx-icon-btn--danger"
                                data-confirm-open="confirm-remove-avatar"
                                title="<?= e($t('account.profile.removeAvatar')) ?>" <?= $hasAvatar ? '' : 'hidden' ?>>
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-12"/>
                            </svg>
                            <span class="lx-visually-hidden"><?= e($t('account.profile.removeAvatar')) ?></span>
                        </button>
                    </div>

                    <div id="upload-loading" class="lx-avatar-status" hidden><span class="lx-muted"><?= e($t('account.profile.uploading')) ?></span></div>
                </form>

                <form id="remove-avatar-form" method="post" action="<?= e(lurl('/account/profile/avatar/remove')) ?>" class="lx-visually-hidden">
                    <?= csrf_field() ?>
                </form>

                <?php foreach ($fieldErrors['avatar'] ?? [] as $message) { ?>
                <p class="lx-field__error"><?= e($message) ?></p>
                <?php } ?>
            </section>

            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.profile.activityHeading')) ?></h2>
                <dl class="lx-statgrid">
                    <div class="lx-stat-item">
                        <dd class="lx-stat-num"><?= e((string) ($user['post_count'] ?? 0)) ?></dd>
                        <dt class="lx-stat-label"><?= e($t('account.profile.totalPosts')) ?></dt>
                    </div>
                    <div class="lx-stat-item">
                        <dd class="lx-stat-num"><?= e((string) ($user['comment_count'] ?? 0)) ?></dd>
                        <dt class="lx-stat-label"><?= e($t('account.profile.commentsReceived')) ?></dt>
                    </div>
                    <?php if (!empty($user['created_at'])) {
                        $memberSince = date('M Y', strtotime((string) $user['created_at']));
                        ?>
                    <div class="lx-stat-item">
                        <dd class="lx-stat-num lx-stat-num--sm"><?= e($memberSince) ?></dd>
                        <dt class="lx-stat-label"><?= e($t('account.profile.memberSince')) ?></dt>
                    </div>
                    <?php } ?>
                </dl>
            </section>

            <section class="lx-card lx-card--public">
                <div class="lx-pubcard-head">
                    <h2 class="lx-card-title"><?= e($t('account.profile.publicPageHeading')) ?></h2>
                    <span class="lx-badge <?= $isPublic ? 'lx-badge--on' : 'lx-badge--off' ?>">
                        <?= e($isPublic ? $t('account.profile.statusLive') : $t('account.profile.statusHidden')) ?>
                    </span>
                </div>

                <?php if ($slug !== '') { ?>
                <p class="lx-pubcard-url"><?= e(rtrim((string) ($profileUrlPrefix ?? ''), '/').'/'.$slug) ?></p>
                <a class="lx-btn lx-btn--primary lx-btn--fit" href="<?= e(lurl('/profile/'.$slug)) ?>" target="_blank" rel="noopener">
                    <?= e($t('account.profile.viewPublicProfile')) ?>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>
                </a>
                <?php if (!$isPublic) { ?>
                <p class="lx-muted lx-pubcard-note"><?= e($t('account.profile.makePublicHelp')) ?></p>
                <?php } ?>
                <?php } else { ?>
                <p class="lx-muted"><?= e($t('account.profile.publicPageDisabledHelp')) ?></p>
                <?php } ?>
            </section>
        </aside>
    </div>
</section>

<div id="avatar-cropper-modal" class="lx-modal" hidden role="dialog" aria-modal="true" aria-labelledby="avatar-cropper-title">
    <div class="lx-modal-backdrop" data-close></div>
    <div class="lx-modal-panel">
        <h3 class="lx-modal-title" id="avatar-cropper-title"><?= e($t('account.profile.cropTitle')) ?></h3>
        <div class="lx-cropper-stage">
            <img id="avatar-cropper-image" alt="">
        </div>
        <div class="lx-modal-actions">
            <button type="button" class="lx-btn lx-btn--subtle" data-close><?= e($t('account.profile.cropCancel')) ?></button>
            <button type="button" id="avatar-cropper-apply" class="lx-btn lx-btn--primary"><?= e($t('account.profile.cropApply')) ?></button>
        </div>
    </div>
</div>

<?php
$cmId = 'confirm-remove-avatar';
$cmFormId = 'remove-avatar-form';
$cmTone = 'danger';
$cmTitle = $t('account.profile.removeAvatarConfirmTitle');
$cmMessage = $t('account.profile.removeAvatarConfirmText');
$cmConfirm = $t('account.profile.removeAvatar');
$cmCancel = $t('account.profile.cropCancel');
?>
{% include "partials/_confirm_modal.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/_confirm_modal_js.lex.php" %}
<script src="/assets/vendor/cropper/cropper.min.js" nonce="<?= csp_nonce() ?>"></script>
<script nonce="<?= csp_nonce() ?>">
  // Avatar flow: picking a file uploads it as a crop source, then the cropper opens
  // on that stored source so the coordinates we send back match the pixels the server
  // will crop. Re-crop reuses the stored source with no re-upload.
  (function () {
    const input = document.getElementById('avatar-input');
    if (!input || typeof Cropper === 'undefined') return;

    const form = document.getElementById('avatar-form');
    const loading = document.getElementById('upload-loading');
    const box = document.getElementById('avatar-box');
    const removeBtn = document.getElementById('remove-avatar-btn');
    const removeForm = document.getElementById('remove-avatar-form');
    const recropBtn = document.getElementById('recrop-avatar-btn');

    const modal = document.getElementById('avatar-cropper-modal');
    const modalImage = document.getElementById('avatar-cropper-image');
    const applyBtn = document.getElementById('avatar-cropper-apply');
    const cropUrl = form.action + '/crop';

    let cropper = null;

    function token() {
      const el = form.querySelector('input[name="_token"]');
      return el ? el.value : '';
    }

    // Swap the avatar box between the placeholder initials and a real image.
    function setPreview(url) {
      let img = document.getElementById('avatar-preview');
      if (img) {
        img.src = url + '?v=' + Date.now();
        return;
      }
      const placeholder = document.getElementById('avatar-placeholder');
      img = document.createElement('img');
      img.id = 'avatar-preview';
      img.className = 'lx-avatar-img';
      img.alt = '';
      img.src = url + '?v=' + Date.now();
      if (placeholder) placeholder.replaceWith(img); else box.appendChild(img);
    }

    function openCropper(sourceUrl, crop) {
      modalImage.src = sourceUrl;
      modal.hidden = false;
      modalImage.onload = function () {
        if (cropper) cropper.destroy();
        cropper = new Cropper(modalImage, {
          aspectRatio: 1,
          viewMode: 1,
          autoCropArea: 1,
          background: false,
          responsive: true,
          ready: function () {
            if (crop && crop.width) cropper.setData(crop);
          }
        });
      };
    }

    function closeCropper() {
      if (cropper) { cropper.destroy(); cropper = null; }
      modal.hidden = true;
      modalImage.removeAttribute('src');
    }

    // Reveal the re-crop and remove controls once an avatar exists, so a fresh
    // upload can be re-framed or removed without a page reload.
    function revealAvatarControls(sourceUrl, crop) {
      if (recropBtn) {
        recropBtn.dataset.source = sourceUrl;
        recropBtn.dataset.crop = crop ? [crop.x, crop.y, crop.width, crop.height].join(',') : '';
        recropBtn.hidden = false;
      }
      if (removeBtn) removeBtn.hidden = false;
    }

    function parseCrop(str) {
      if (!str) return null;
      const p = str.split(',').map(Number);
      if (p.length !== 4) return null;
      return { x: p[0], y: p[1], width: p[2], height: p[3] };
    }

    input.addEventListener('change', function (e) {
      const file = e.target.files[0];
      if (!file) return;

      const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
      if (!validTypes.includes(file.type)) { alert('Please select a valid image (JPG, PNG or WebP).'); input.value = ''; return; }
      if (file.size > (parseInt(input.dataset.maxSize, 10) || 20971520)) { alert('File size must be less than 20MB.'); input.value = ''; return; }

      const data = new FormData();
      data.append('_token', token());
      data.append('avatar', file);
      if (loading) loading.hidden = false;

      fetch(form.action, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (loading) loading.hidden = true;
          input.value = '';
          if (res.error) { alert(res.error); return; }
          setPreview(res.avatar_url);
          revealAvatarControls(res.source_url, res.crop);
          openCropper(res.source_url, res.crop);
        })
        .catch(function () { if (loading) loading.hidden = true; alert('Upload failed. Please try again.'); });
    });

    if (recropBtn) recropBtn.addEventListener('click', function () {
      openCropper(recropBtn.dataset.source, parseCrop(recropBtn.dataset.crop));
    });

    applyBtn.addEventListener('click', function () {
      if (!cropper) return;
      const c = cropper.getData(true);
      const data = new FormData();
      data.append('_token', token());
      data.append('crop_x', c.x);
      data.append('crop_y', c.y);
      data.append('crop_w', c.width);
      data.append('crop_h', c.height);
      applyBtn.disabled = true;

      fetch(cropUrl, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          applyBtn.disabled = false;
          if (res.error) { alert(res.error); return; }
          setPreview(res.avatar_url);
          const btn = document.getElementById('recrop-avatar-btn');
          if (btn) btn.dataset.crop = [c.x, c.y, c.width, c.height].join(',');
          closeCropper();
        })
        .catch(function () { applyBtn.disabled = false; alert('Could not save the crop. Please try again.'); });
    });

    modal.querySelectorAll('[data-close]').forEach(function (el) {
      el.addEventListener('click', closeCropper);
    });
    // Avatar removal is confirmed through the shared _confirm_modal dialog,
    // wired by data-confirm-open on the remove button.
  })();
</script>
{% endblock %}
