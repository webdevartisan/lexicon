{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.profile.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
<link rel="stylesheet" href="/assets/vendor/cropper/cropper.min.css" />
<style>
  .lx-modal[hidden]{display:none}
  .lx-modal{position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem}
  .lx-modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.55)}
  .lx-modal-panel{position:relative;background:#fff;color:#1a1a1a;border-radius:.75rem;max-width:32rem;width:100%;padding:1.25rem;box-shadow:0 20px 50px rgba(0,0,0,.3)}
  .lx-modal-title{margin:0 0 .75rem;font-size:1.15rem}
  .lx-cropper-stage{max-height:60vh;overflow:hidden;background:#f2f2f2}
  .lx-cropper-stage img{max-width:100%;display:block}
  .lx-modal .cropper-view-box,.lx-modal .cropper-face{border-radius:50%}
  .lx-modal-actions{display:flex;gap:.5rem;justify-content:flex-end;margin-top:1rem}
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
    {% include "partials/_account_shell.lex.php" %}

    <div class="lx-account-body">
        <header class="lx-account-head">
            <h1><?= e($t('account.profile.heading')) ?></h1>
            <p class="lx-muted"><?= e($t('account.profile.intro')) ?></p>
        </header>

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
                <button type="submit" class="lx-btn lx-btn--primary"><?= e($t('account.profile.save')) ?></button>
            </div>
        </form>

        <aside class="lx-account-aside">
            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.profile.avatarHeading')) ?></h2>
                <div class="lx-avatar" id="avatar-box">
                    <?php if (!empty($user['avatar_url'])) { ?>
                    <img id="avatar-preview" class="lx-avatar-img" src="<?= e($user['avatar_url']) ?>" alt="">
                    <?php } else { ?>
                    <div id="avatar-placeholder" class="lx-avatar-initials"><?= e($user['initials'] ?? '?') ?></div>
                    <?php } ?>
                </div>

                <form id="avatar-form" method="post" action="<?= e(lurl('/account/profile/avatar')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input id="avatar-input" name="avatar" type="file" class="lx-visually-hidden"
                           accept="image/jpeg,image/png,image/webp" data-max-size="2097152">
                    <label for="avatar-input" class="lx-btn lx-btn--ghost lx-btn--fit"><?= e($t('account.profile.changeAvatar')) ?></label>
                    <p class="lx-muted lx-avatar-help"><?= e($t('account.profile.avatarConstraints')) ?></p>

                    <div id="upload-loading" hidden><span class="lx-muted"><?= e($t('account.profile.uploading')) ?></span></div>
                </form>

                <?php if (!empty($user['avatar_source_url'])) { ?>
                <button type="button" id="recrop-avatar-btn" class="lx-btn lx-btn--ghost lx-btn--fit"
                        data-source="<?= e($user['avatar_source_url']) ?>" data-crop="<?= e($user['avatar_crop'] ?? '') ?>">
                    <?= e($t('account.profile.recropAvatar')) ?>
                </button>
                <?php } ?>

                <?php if (!empty($user['avatar_url'])) { ?>
                <form id="remove-avatar-form" method="post" action="<?= e(lurl('/account/profile/avatar/remove')) ?>">
                    <?= csrf_field() ?>
                    <button type="button" id="remove-avatar-btn" class="lx-btn lx-btn--danger lx-btn--fit"><?= e($t('account.profile.removeAvatar')) ?></button>
                </form>
                <?php } ?>

                <?php foreach ($fieldErrors['avatar'] ?? [] as $message) { ?>
                <p class="lx-field__error"><?= e($message) ?></p>
                <?php } ?>
            </section>

            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.profile.activityHeading')) ?></h2>
                <p class="lx-stat"><span><?= e($t('account.profile.totalPosts')) ?></span><strong><?= e((string) ($user['post_count'] ?? 0)) ?></strong></p>
                <p class="lx-stat"><span><?= e($t('account.profile.commentsReceived')) ?></span><strong><?= e((string) ($user['comment_count'] ?? 0)) ?></strong></p>
            </section>

            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.profile.publicPageHeading')) ?></h2>
                <?php if (!empty($slug)) { ?>
                <a class="lx-btn lx-btn--ghost lx-btn--fit" href="<?= e(lurl('/profile/'.$slug)) ?>" target="_blank" rel="noopener">
                    <?= e($t('account.profile.viewPublicProfile')) ?>
                </a>
                <p class="lx-muted lx-avatar-help"><?= e($t('account.profile.makePublicHelp')) ?></p>
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
            <button type="button" class="lx-btn lx-btn--ghost" data-close><?= e($t('account.profile.cropCancel')) ?></button>
            <button type="button" id="avatar-cropper-apply" class="lx-btn lx-btn--primary"><?= e($t('account.profile.cropApply')) ?></button>
        </div>
    </div>
</div>
{% endblock %}

{% block scripts %}
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
    const initials = '<?= e($user['initials'] ?? '?') ?>';

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

    // Make sure a re-crop button exists after the first upload so the user can
    // re-frame without re-uploading.
    function ensureRecropButton(sourceUrl, crop) {
      let btn = document.getElementById('recrop-avatar-btn');
      if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'recrop-avatar-btn';
        btn.className = 'lx-btn lx-btn--ghost lx-btn--fit';
        btn.textContent = '<?= e($t('account.profile.recropAvatar')) ?>';
        btn.addEventListener('click', function () {
          openCropper(btn.dataset.source, parseCrop(btn.dataset.crop));
        });
        form.insertAdjacentElement('afterend', btn);
      }
      btn.dataset.source = sourceUrl;
      btn.dataset.crop = crop ? [crop.x, crop.y, crop.width, crop.height].join(',') : '';
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
      if (file.size > (parseInt(input.dataset.maxSize, 10) || 2097152)) { alert('File size must be less than 2MB.'); input.value = ''; return; }

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
          ensureRecropButton(res.source_url, res.crop);
          openCropper(res.source_url, res.crop);
        })
        .catch(function () { if (loading) loading.hidden = true; alert('Upload failed. Please try again.'); });
    });

    const existingRecrop = document.getElementById('recrop-avatar-btn');
    if (existingRecrop) existingRecrop.addEventListener('click', function () {
      openCropper(existingRecrop.dataset.source, parseCrop(existingRecrop.dataset.crop));
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

    if (removeBtn && removeForm) removeBtn.addEventListener('click', function () {
      if (confirm('Remove your profile avatar?')) removeForm.submit();
    });
  })();
</script>
{% endblock %}
