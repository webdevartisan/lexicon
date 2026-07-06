{% extends "back.lex.php" %}

{% block title %}Settings{% endblock %}
{% block subtitle %}Site identity, content display, registration, availability, and email.{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
{% endblock %}

{% block body %}
<?php
$errors = errors();

$sectionTitleClass = 'text-base font-semibold text-slate-900 dark:text-zink-50 mb-1';
$sectionHintClass = 'text-sm text-slate-500 dark:text-zink-300 mb-5';

$saveButton = '<button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">'
    .'<i data-lucide="save" class="size-4"></i> Save Settings</button>';

// Values with old-input fallback for redisplay after validation errors
$siteName = old('site_name', $settings['site_name'] ?? '');
$siteTagline = old('site_tagline', $settings['site_tagline'] ?? '');
$siteDescription = old('site_description', $settings['site_description'] ?? '');
$adminEmail = old('admin_email', $settings['admin_email'] ?? '');

$currentTz = old('timezone', $settings['timezone'] ?? 'UTC');
$timezoneOptions = array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers());

$postsPerPage = old('posts_per_page', $settings['posts_per_page'] ?? '10');
$excerptLength = old('excerpt_length', $settings['excerpt_length'] ?? '200');

$currentFormat = old('date_format', $settings['date_format'] ?? 'F j, Y');
$formatOptions = [
    'F j, Y' => date('F j, Y'),
    'Y-m-d' => date('Y-m-d'),
    'd/m/Y' => date('d/m/Y'),
    'm/d/Y' => date('m/d/Y'),
];

$allowComments = old('allow_comments', $settings['allow_comments'] ?? '1');
$regEnabled = old('registration_enabled', $settings['registration_enabled'] ?? '1');
$emailVerification = old('require_email_verification', $settings['require_email_verification'] ?? '0');
// Maintenance state comes from the flag file, not the settings table
$maintenance = old('maintenance_mode', !empty($maintenance_active) ? '1' : '0');
$currentRole = old('default_user_role', $settings['default_user_role'] ?? '4');

$roleOptions = [];
foreach ($roles ?? [] as $r) {
    // administrators are never a sensible self-registration default
    if (($r['role_slug'] ?? '') !== 'administrator') {
        $roleOptions[(string) $r['id']] = $r['role_name'];
    }
}

$onOff = static function (string $current, string $onLabel, string $offLabel): string {
    return '<option value="1"'.($current == '1' ? ' selected' : '').'>'.e($onLabel).'</option>'
        .'<option value="0"'.($current == '0' ? ' selected' : '').'>'.e($offLabel).'</option>';
};

$selectClass = 'form-select w-full border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-5xl">

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 mb-5 border-b border-slate-200 dark:border-zink-600" role="tablist">
        <?php
        $tabs = [
            'identity' => ['home', 'Site Identity'],
            'content' => ['file-text', 'Content & Display'],
            'users' => ['users', 'Users & Availability'],
            'email' => ['mail', 'Email'],
        ];
foreach ($tabs as $tabKey => [$tabIcon, $tabLabel]) { ?>
        <button type="button" role="tab" data-tab="<?= e($tabKey) ?>"
                class="tab-button inline-flex items-center gap-2 px-4 py-2.5 -mb-px text-sm font-medium border-b-2 border-transparent text-slate-500 dark:text-zink-300 hover:text-slate-700 dark:hover:text-zink-100 transition-colors <?= $tabKey === 'identity' ? 'active' : '' ?>">
            <i data-lucide="<?= e($tabIcon) ?>" class="size-4"></i> <?= e($tabLabel) ?>
        </button>
        <?php } ?>
    </div>

    <!-- TAB 1: SITE IDENTITY -->
    <div class="tab-content active" data-section="identity">
        <form method="POST" action="/admin/settings" class="card">
            {{ csrf_field() }}
            <div class="card-body">
                <h3 class="<?= $sectionTitleClass ?>">Site Information</h3>
                <p class="<?= $sectionHintClass ?>">Basic information that appears in headers, metadata, and system emails.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {% cmp="input" type="text" label="Site Name" name="site_name" value="{$siteName}" required %}
                    {% cmp="input" type="text" label="Site Tagline" name="site_tagline" value="{$siteTagline}" placeholder="Your site's motto or slogan" %}
                </div>

                <div class="mt-5">
                    {% cmp="input" type="textarea" label="Site Description" name="site_description" value="{$siteDescription}" rows="3" placeholder="A brief description for SEO and social sharing" %}
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5">
                    {% cmp="input" type="email" label="Admin Email" name="admin_email" value="{$adminEmail}" required underlabel="Receives system notifications and alerts" %}
                    {% cmp="searchable-select" name="timezone" label="Timezone" options="{$timezoneOptions}" selectedKey="{$currentTz}" placeholder="Search timezones..." required %}
                </div>
            </div>
            <div class="card-body flex justify-end border-t border-slate-100 dark:border-zink-600">
                <?= $saveButton ?>
            </div>
        </form>
    </div>

    <!-- TAB 2: CONTENT & DISPLAY -->
    <div class="tab-content" data-section="content">
        <form method="POST" action="/admin/settings" class="card">
            {{ csrf_field() }}
            <div class="card-body">
                <h3 class="<?= $sectionTitleClass ?>">Content Display</h3>
                <p class="<?= $sectionHintClass ?>">Control how posts and content are displayed throughout the site.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    {% cmp="input" type="number" label="Posts Per Page" name="posts_per_page" value="{$postsPerPage}" required %}
                    {% cmp="input" type="number" label="Excerpt Length (characters)" name="excerpt_length" value="{$excerptLength}" required %}

                    <div>
                        <label for="date_format" class="inline-block mb-2 text-base font-medium">Date Format</label>
                        <select id="date_format" name="date_format" required class="<?= $selectClass ?>">
                            <?php foreach ($formatOptions as $format => $example) { ?>
                            <option value="<?= e($format) ?>" <?= $format === $currentFormat ? 'selected' : '' ?>><?= e($example) ?></option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label for="allow_comments" class="inline-block mb-2 text-base font-medium">Comments</label>
                        <select id="allow_comments" name="allow_comments" class="<?= $selectClass ?>">
                            <?= $onOff((string) $allowComments, 'Enabled', 'Disabled') ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body flex justify-end border-t border-slate-100 dark:border-zink-600">
                <?= $saveButton ?>
            </div>
        </form>
    </div>

    <!-- TAB 3: USERS & AVAILABILITY -->
    <div class="tab-content" data-section="users">
        <form method="POST" action="/admin/settings" class="card">
            {{ csrf_field() }}
            <div class="card-body">
                <h3 class="<?= $sectionTitleClass ?>">Registration &amp; Availability</h3>
                <p class="<?= $sectionHintClass ?>">Who can sign up, what they become, and whether the site is open to visitors.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label for="registration_enabled" class="inline-block mb-2 text-base font-medium">Allow Registration</label>
                        <select id="registration_enabled" name="registration_enabled" class="<?= $selectClass ?>">
                            <?= $onOff((string) $regEnabled, 'Enabled', 'Disabled') ?>
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">Disable to prevent new user registrations</p>
                    </div>

                    <div>
                        <label for="default_user_role" class="inline-block mb-2 text-base font-medium">Default User Role</label>
                        <select id="default_user_role" name="default_user_role" required class="<?= $selectClass ?>">
                            <?php foreach ($roleOptions as $roleId => $roleName) { ?>
                            <option value="<?= e($roleId) ?>" <?= $roleId == $currentRole ? 'selected' : '' ?>><?= e($roleName) ?></option>
                            <?php } ?>
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">Assigned to new users after registration</p>
                    </div>

                    <div>
                        <label for="require_email_verification" class="inline-block mb-2 text-base font-medium">Email Verification</label>
                        <select id="require_email_verification" name="require_email_verification" class="<?= $selectClass ?>">
                            <?= $onOff((string) $emailVerification, 'Required', 'Not required') ?>
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">Future feature: require verification before login</p>
                    </div>

                    <div>
                        <label for="maintenance_mode" class="inline-block mb-2 text-base font-medium">Maintenance Mode</label>
                        <select id="maintenance_mode" name="maintenance_mode" class="<?= $selectClass ?>">
                            <?php echo '<option value="0"'.($maintenance == '0' ? ' selected' : '').'>Off - site is live</option>'
                                .'<option value="1"'.($maintenance == '1' ? ' selected' : '').'>On - show visitors a maintenance page</option>'; ?>
                        </select>
                        <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">Allowlisted IPs, the admin panel, and the login page stay reachable. Stored as storage/maintenance.json so it works even if the database is down.</p>
                    </div>
                </div>
            </div>
            <div class="card-body flex justify-end border-t border-slate-100 dark:border-zink-600">
                <?= $saveButton ?>
            </div>
        </form>
    </div>

    <!-- TAB 4: EMAIL -->
    <div class="tab-content" data-section="email">
        <div class="card mb-5">
            <div class="card-body">
                <h3 class="<?= $sectionTitleClass ?>">Mail Configuration</h3>
                <p class="<?= $sectionHintClass ?>">Current email settings loaded from your environment configuration.</p>

                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm p-4 rounded-md bg-slate-50 dark:bg-zink-600/40 font-mono">
                    <?php
                    $mailLabels = [
                        'driver' => 'Driver', 'host' => 'SMTP Host', 'port' => 'SMTP Port',
                        'encryption' => 'Encryption', 'from_address' => 'From Address', 'from_name' => 'From Name',
                    ];
foreach ($mailLabels as $mailKey => $mailLabel) { ?>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1 font-sans"><?= e($mailLabel) ?></dt>
                        <dd class="text-slate-900 dark:text-zink-50"><?= e((string) $mail_config[$mailKey]) ?></dd>
                    </div>
                    <?php } ?>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1 font-sans">Debug</dt>
                        <dd class="text-slate-900 dark:text-zink-50"><?= $mail_config['debug'] ? 'Enabled' : 'Disabled' ?></dd>
                    </div>
                </dl>

                <p class="flex items-start gap-2 mt-4 text-xs text-slate-500 dark:text-zink-300">
                    <i data-lucide="info" class="size-4 shrink-0 text-custom-500"></i>
                    SMTP credentials live in your .env file and cannot be edited here for security reasons.
                </p>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="<?= $sectionTitleClass ?>">Testing Email Delivery</h3>
                <p class="<?= $sectionHintClass ?>">Template previews and delivery tests live on the Email Templates page so there is one place to test email.</p>
                <a href="<?= e(lurl('/admin/email-test')) ?>" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                    <i data-lucide="mail" class="size-4"></i> Open Email Templates
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .tab-button.active { color: rgb(var(--tw-colors-custom-500, 59 130 246)); border-bottom-color: currentColor; }
</style>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/choices.js/public/assets/scripts/choices.min.js"></script>
<script src="/cp-assets/js/searchable-select.init.js"></script>
<script>
    // switch settings sections without a page reload
    document.addEventListener('DOMContentLoaded', () => {
        const tabs = document.querySelectorAll('.tab-button');
        const sections = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                sections.forEach(section => {
                    section.classList.toggle('active', section.dataset.section === tab.dataset.tab);
                });
            });
        });
    });
</script>
{% endblock %}
