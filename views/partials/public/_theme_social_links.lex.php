      <?php
      // Social icons come from blog settings (Appearance > Social Profiles);
      // platforms without a URL simply don't render.
      $socialLinks = is_array($settings['social_links'] ?? null) ? $settings['social_links'] : [];
  $socialIcons = [
      'x' => '<path d="M4 4l16 16M20 4L4 20" />',
      'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />',
      'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5" ry="5" /><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" /><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />',
      'linkedin' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4V9h4v1a6 6 0 0 1 2-2z" /><rect x="2" y="9" width="4" height="12" /><circle cx="4" cy="4" r="2" />',
      'youtube' => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z" /><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" />',
      'github' => '<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22" />',
  ];
  $socialLabels = [
      'x' => 'X (Twitter)',
      'facebook' => 'Facebook',
      'instagram' => 'Instagram',
      'linkedin' => 'LinkedIn',
      'youtube' => 'YouTube',
      'github' => 'GitHub',
  ];
  ?>
      <?php if (!empty($socialLinks)) { ?>
      <nav class="foot-social" aria-label="Social profiles">
        <?php foreach ($socialLinks as $platform => $url) { ?>
          <?php if (empty($socialIcons[$platform])) {
              continue;
          } ?>
          <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($socialLabels[$platform]) ?>" title="<?= e($socialLabels[$platform]) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $socialIcons[$platform] ?></svg>
          </a>
        <?php } ?>
      </nav>
      <?php } ?>
