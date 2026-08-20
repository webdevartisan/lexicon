{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.profile.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
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

                    <div id="selected-file" hidden>
                        <span id="file-name"></span>
                        <button type="button" id="clear-file" class="lx-linkbtn"><?= e($t('a11y.dismiss')) ?></button>
                    </div>
                    <div id="upload-actions" hidden>
                        <button type="submit" class="lx-btn lx-btn--primary lx-btn--fit">
                            <?= e(!empty($user['avatar_url']) ? $t('account.profile.replaceAvatar') : $t('account.profile.uploadAvatar')) ?>
                        </button>
                    </div>
                    <div id="upload-loading" hidden><span class="lx-muted"><?= e($t('account.profile.uploading')) ?></span></div>
                </form>

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
{% endblock %}

{% block scripts %}
<script nonce="<?= csp_nonce() ?>">
  // Avatar upload preview and validation, ported from the dashboard. The upload
  // is a real submit button, so no name/value is dropped; the JS only previews.
  (function () {
    const input = document.getElementById('avatar-input');
    if (!input) return;

    const selected = document.getElementById('selected-file');
    const fileName = document.getElementById('file-name');
    const actions = document.getElementById('upload-actions');
    const loading = document.getElementById('upload-loading');
    const form = document.getElementById('avatar-form');
    const clearBtn = document.getElementById('clear-file');
    const removeBtn = document.getElementById('remove-avatar-btn');
    const removeForm = document.getElementById('remove-avatar-form');
    const box = document.getElementById('avatar-box');
    const originalUrl = '<?= e($user['avatar_url'] ?? '') ?>';
    const initials = '<?= e($user['initials'] ?? '?') ?>';

    function reset() {
      input.value = '';
      selected.hidden = true;
      actions.hidden = true;
      const preview = document.getElementById('avatar-preview');
      if (preview && !originalUrl) {
        const ph = document.createElement('div');
        ph.id = 'avatar-placeholder';
        ph.className = 'lx-avatar-initials';
        ph.textContent = initials;
        preview.replaceWith(ph);
      } else if (preview && originalUrl) {
        preview.src = originalUrl;
      }
    }

    input.addEventListener('change', function (e) {
      const file = e.target.files[0];
      if (!file) { reset(); return; }

      const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
      if (!validTypes.includes(file.type)) { alert('Please select a valid image (JPG, PNG or WebP).'); reset(); return; }
      if (file.size > (parseInt(input.dataset.maxSize, 10) || 2097152)) { alert('File size must be less than 2MB.'); reset(); return; }

      fileName.textContent = file.name;
      selected.hidden = false;
      actions.hidden = false;

      const reader = new FileReader();
      reader.onload = function (ev) {
        let preview = document.getElementById('avatar-preview');
        const placeholder = document.getElementById('avatar-placeholder');
        if (preview) {
          preview.src = ev.target.result;
        } else if (placeholder) {
          const img = document.createElement('img');
          img.id = 'avatar-preview';
          img.className = 'lx-avatar-img';
          img.alt = '';
          img.src = ev.target.result;
          placeholder.replaceWith(img);
        }
      };
      reader.readAsDataURL(file);
    });

    if (clearBtn) clearBtn.addEventListener('click', reset);

    if (form) form.addEventListener('submit', function () {
      actions.hidden = true;
      selected.hidden = true;
      loading.hidden = false;
    });

    if (removeBtn && removeForm) removeBtn.addEventListener('click', function () {
      if (confirm('Remove your profile avatar?')) removeForm.submit();
    });
  })();
</script>
{% endblock %}
