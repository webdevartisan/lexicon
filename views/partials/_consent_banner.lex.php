<?php /** @var string $csrfToken */ ?>
<div class="consent-banner" id="consentBanner" hidden>
  <div class="consent-banner-inner" role="region" aria-label="Cookie consent">
    <p class="consent-banner-text">
      We use necessary cookies for core functionality. Optional cookies help with preferences, analytics and marketing.
    </p>

    <div class="consent-banner-actions">
      <button class="lx-btn lx-btn-small lx-btn-ghost" type="button" data-consent-open>Manage</button>
      <?php if (!empty($rejectAllShowBtn)) { ?>
      <button class="lx-btn lx-btn-small lx-btn-ghost" type="button" data-consent-action="reject_all">Reject all</button>
      <?php } ?>
      <button class="lx-btn lx-btn-small lx-btn-gilt" type="button" data-consent-action="accept_all">Accept all</button>
    </div>
  </div>
</div>

<div class="consent-modal" id="consentModal" hidden>
  <div class="consent-modal-backdrop" data-consent-close></div>

  <div class="consent-modal-panel" role="dialog" aria-modal="true" aria-label="Cookie settings">
    <header class="consent-modal-header">
      <h2 class="consent-modal-title">Cookie settings</h2>
      <button class="lx-btn lx-btn-small lx-btn-subtle" type="button" data-consent-close aria-label="Close">Close</button>
    </header>

    <form id="consentForm">
      <input type="hidden" name="action" value="save">
      <div class="consent-row">
        <div>
          <div class="consent-row-label">Necessary</div>
          <div class="consent-row-hint">Required for security and core features.</div>
        </div>

        <div class="consent-row-control">
          <div class="consent-switch consent-switch-locked">
      <input class="consent-switch-input" type="checkbox" id="consent_necessary" checked disabled>
            <label class="consent-switch-label" for="consent_necessary">
              <span class="sr-only">Necessary</span>
            </label>
          </div>
        </div>
      </div>
      <div class="consent-row">
        <div>
          <div class="consent-row-label">Preferences</div>
          <div class="consent-row-hint">Remembers settings like language.</div>
        </div>

        <div class="consent-row-control">
          <div class="consent-switch">
            <input class="consent-switch-input" type="checkbox" id="consent_preferences" name="preferences" value="1">
            <label class="consent-switch-label" for="consent_preferences">
              <span class="sr-only">Preferences</span>
            </label>
          </div>
        </div>
      </div>

      <div class="consent-row">
        <div>
          <div class="consent-row-label">Analytics</div>
          <div class="consent-row-hint">Helps improve the site.</div>
        </div>

        <div class="consent-row-control">
          <div class="consent-switch">
            <input
              class="consent-switch-input"
              type="checkbox"
              id="consent_analytics"
              name="analytics"
              value="1"
            >
            <label class="consent-switch-label" for="consent_analytics">
              <span class="sr-only">Analytics</span>
            </label>
          </div>
        </div>
      </div>
      <div class="consent-row">
        <div>
          <div class="consent-row-label">Marketing</div>
          <div class="consent-row-hint">Measures marketing performance.</div>
        </div>

        <div class="consent-row-control">
          <div class="consent-switch">
            <input class="consent-switch-input" type="checkbox" id="consent_marketing" name="marketing" value="1">
            <label class="consent-switch-label" for="consent_marketing">
              <span class="sr-only">Marketing</span>
            </label>
          </div>
        </div>
      </div>

      <footer class="consent-modal-footer">
        <button class="lx-btn lx-btn-small lx-btn-subtle" type="button" data-consent-action="reject_all">Reject all</button>
        <button class="lx-btn lx-btn-small lx-btn-primary" type="submit">Save</button>
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
    aria-label="Cookie settings">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path d="M12 3a9 9 0 1 0 9 9 3.4 3.4 0 0 1-4.4-4.4A3.4 3.4 0 0 1 12 3Z"/>
    <circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/>
    <circle cx="13.5" cy="14.5" r="1" fill="currentColor" stroke="none"/>
    <circle cx="8.5" cy="15" r="1" fill="currentColor" stroke="none"/>
  </svg>
</button>