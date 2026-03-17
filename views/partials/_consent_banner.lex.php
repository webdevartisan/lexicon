<?php /** @var string $csrfToken */ ?>
<div class="consent-banner" id="consentBanner" hidden>
  <div class="consent-banner__inner" role="region" aria-label="Cookie consent">
    <p class="consent-banner__text">
      We use necessary cookies for core functionality. Optional cookies help with preferences, analytics and marketing.
    </p>

    <div class="consent-banner__actions">
      <button class="button small alt" type="button" data-consent-open>Manage</button>
      <?php if (!empty($rejectAllShowBtn)) { ?>
      <button class="button small" type="button" data-consent-action="reject_all">Reject all</button>
      <?php } ?>
      <button class="button small primary" type="button" data-consent-action="accept_all">Accept all</button>
    </div>
  </div>
</div>

<div class="consent-modal" id="consentModal" hidden>
  <div class="consent-modal__backdrop" data-consent-close></div>

  <div class="consent-modal__panel" role="dialog" aria-modal="true" aria-label="Cookie settings">
    <header class="consent-modal__header">
      <h2 class="consent-modal__title">Cookie settings</h2>
      <button class="button small" type="button" data-consent-close aria-label="Close">Close</button>
    </header>

    <form id="consentForm">
      <input type="hidden" name="action" value="save">

      <!-- Necessary (always on) -->
      <div class="consent-row">
        <div>
          <div class="consent-row__label">Necessary</div>
          <div class="consent-row__hint">Required for security and core features.</div>
        </div>

        <div class="consent-row__control">
          <div class="consent-switch consent-switch--locked">
      <input class="consent-switch__input" type="checkbox" id="consent_necessary" checked disabled>
            <label class="consent-switch__label" for="consent_necessary">
              <span class="sr-only">Necessary</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Preferences -->
      <div class="consent-row">
        <div>
          <div class="consent-row__label">Preferences</div>
          <div class="consent-row__hint">Remembers settings like language.</div>
        </div>

        <div class="consent-row__control">
          <div class="consent-switch">
            <input class="consent-switch__input" type="checkbox" id="consent_preferences" name="preferences" value="1">
            <label class="consent-switch__label" for="consent_preferences">
              <span class="sr-only">Preferences</span>
            </label>
          </div>
        </div>
      </div>

      <div class="consent-row">
        <div>
          <div class="consent-row__label">Analytics</div>
          <div class="consent-row__hint">Helps improve the site.</div>
        </div>

        <div class="consent-row__control">
          <div class="consent-switch">
            <input
              class="consent-switch__input"
              type="checkbox"
              id="consent_analytics"
              name="analytics"
              value="1"
            >
            <label class="consent-switch__label" for="consent_analytics">
              <span class="sr-only">Analytics</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Marketing -->
      <div class="consent-row">
        <div>
          <div class="consent-row__label">Marketing</div>
          <div class="consent-row__hint">Measures marketing performance.</div>
        </div>

        <div class="consent-row__control">
          <div class="consent-switch">
            <input class="consent-switch__input" type="checkbox" id="consent_marketing" name="marketing" value="1">
            <label class="consent-switch__label" for="consent_marketing">
              <span class="sr-only">Marketing</span>
            </label>
          </div>
        </div>
      </div>

      <footer class="consent-modal__footer">
        <button class="button small" type="button" data-consent-action="reject_all">Reject all</button>
        <button class="button small primary" type="submit">Save</button>
      </footer>
    </form>
  </div>
</div>

<button 
    type="button" 
    class="consent-fab" 
    data-consent-open 
    aria-label="Cookie settings">
</button>