<div class="consent-banner" id="consentBanner" hidden>
  <div class="consent-banner-inner" role="region" aria-label="<?= e($t('consent.region')) ?>">
    <p class="consent-banner-text">
      <?= e($t('consent.bannerText')) ?>
      <a href="<?= e(lurl('/cookies')) ?>"><?= e($t('consent.policyLink')) ?></a>
    </p>

    <div class="consent-banner-actions">
      <button class="lx-btn lx-btn-small lx-btn-ghost" type="button" data-consent-open><?= e($t('consent.manage')) ?></button>
      <button class="lx-btn lx-btn-small lx-btn-gilt" type="button" data-consent-action="reject_all"><?= e($t('consent.rejectAll')) ?></button>
      <button class="lx-btn lx-btn-small lx-btn-gilt" type="button" data-consent-action="accept_all"><?= e($t('consent.acceptAll')) ?></button>
    </div>
  </div>
</div>

<div class="consent-modal" id="consentModal" hidden>
  <div class="consent-modal-backdrop" data-consent-close></div>

  <div class="consent-modal-panel" role="dialog" aria-modal="true" aria-label="<?= e($t('consent.settingsTitle')) ?>">
    <header class="consent-modal-header">
      <h2 class="consent-modal-title"><?= e($t('consent.settingsTitle')) ?></h2>
      <button class="lx-btn lx-btn-small lx-btn-subtle" type="button" data-consent-close><?= e($t('consent.close')) ?></button>
    </header>

    <form id="consentForm">
      <input type="hidden" name="action" value="save">
      <?php foreach (['necessary', 'preferences', 'analytics', 'marketing'] as $category) { ?>
      <div class="consent-row">
        <div>
          <div class="consent-row-label"><?= e($t('consent.'.$category)) ?></div>
          <div class="consent-row-hint"><?= e($t('consent.'.$category.'Hint')) ?></div>
        </div>

        <div class="consent-row-control">
          <div class="consent-switch<?= $category === 'necessary' ? ' consent-switch-locked' : '' ?>">
            <?php if ($category === 'necessary') { ?>
            <input class="consent-switch-input" type="checkbox" id="consent_necessary" checked disabled>
            <?php } else { ?>
            <input class="consent-switch-input" type="checkbox" id="consent_<?= $category ?>" name="<?= $category ?>" value="1">
            <?php } ?>
            <label class="consent-switch-label" for="consent_<?= $category ?>">
              <span class="sr-only"><?= e($t('consent.'.$category)) ?></span>
            </label>
          </div>
        </div>
      </div>
      <?php } ?>

      <footer class="consent-modal-footer">
        <button class="lx-btn lx-btn-small lx-btn-subtle" type="button" data-consent-action="reject_all"><?= e($t('consent.rejectAll')) ?></button>
        <button class="lx-btn lx-btn-small lx-btn-primary" type="submit"><?= e($t('consent.save')) ?></button>
      </footer>
    </form>
  </div>
</div>

<?php
// Inline mark rather than a Font Awesome glyph: this button is on every public
// page, and the auth layout deliberately does not load the icon font, which
// left the button rendering as a bare circle there.
?>
<button
    type="button"
    class="consent-fab"
    data-consent-open
    aria-label="<?= e($t('consent.settingsTitle')) ?>">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="M12 3a9 9 0 1 0 9 9 3.4 3.4 0 0 1-4.4-4.4A3.4 3.4 0 0 1 12 3Z"/>
    <circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/>
    <circle cx="13.5" cy="14.5" r="1" fill="currentColor" stroke="none"/>
    <circle cx="8.5" cy="15" r="1" fill="currentColor" stroke="none"/>
  </svg>
</button>
