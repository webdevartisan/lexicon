{% extends "back.lex.php" %}

{% block title %}General Settings{% endblock %}

{% block head %}
<style>
    /* We add tab styling for organizing settings into sections */
    .settings-tabs {
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 2rem;
    }

    .tab-button {
        padding: 0.75rem 1.5rem;
        border-bottom: 3px solid transparent;
        font-weight: 500;
        color: #6b7280;
        transition: all 0.2s;
    }

    .tab-button:hover {
        color: #111827;
    }

    .tab-button.active {
        color: #2563eb;
        border-bottom-color: #2563eb;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }
</style>
{% endblock %}

{% block body %}
<div class="container mx-auto px-4 py-8 max-w-7xl">
    <!-- Flash messages -->
    <?php $flash = flash(); ?>
    <?php if (!empty($flash['success'])) { ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                    clip-rule="evenodd" />
            </svg>
            <span><?= e($flash['success'][0]) ?></span>
        </div>
    <?php } ?>

    <?php if (!empty($flash['error'])) { ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd"
                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293z"
                    clip-rule="evenodd" />
            </svg>
            <span><?= e($flash['error'][0]) ?></span>
        </div>
    <?php } ?>

    <!-- Page header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">General Settings</h1>
        <p class="text-gray-600">Manage your website configuration, email settings, and user registration options.</p>
    </div>

    <!-- Tabs navigation -->
    <div class="settings-tabs flex gap-1">
        <button class="tab-button active" data-tab="identity">
            <span class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Site Identity
            </span>
        </button>
        <button class="tab-button" data-tab="content">
            <span class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Content & Display
            </span>
        </button>
        <button class="tab-button" data-tab="users">
            <span class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                Users & Registration
            </span>
        </button>
        <button class="tab-button" data-tab="email">
            <span class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Email Settings
            </span>
        </button>
    </div>

    <?php $errors = errors(); ?>

    <!-- TAB 1: SITE IDENTITY -->
    <div class="tab-content active" data-section="identity">
        <form method="POST" action="/admin/settings">
            <?= csrf_field() ?>

            <div class="bg-white border rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Site Information</h3>
                <p class="text-sm text-gray-600 mb-6">Basic information about your website that appears in headers,
                    metadata, and system emails.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="site_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Site Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="site_name" name="site_name"
                            value="<?= e(old('site_name', $settings['site_name'] ?? '')) ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?= isset($errors['site_name']) ? 'border-red-500' : '' ?>"
                            required>
                        <?php if (isset($errors['site_name'])) { ?>
                            <p class="text-red-600 text-sm mt-1"><?= e($errors['site_name'][0]) ?></p>
                        <?php } ?>
                    </div>

                    <div>
                        <label for="site_tagline" class="block text-sm font-medium text-gray-700 mb-2">
                            Site Tagline
                        </label>
                        <input type="text" id="site_tagline" name="site_tagline"
                            value="<?= e(old('site_tagline', $settings['site_tagline'] ?? '')) ?>"
                            placeholder="Your site's motto or slogan"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mt-6">
                    <label for="site_description" class="block text-sm font-medium text-gray-700 mb-2">
                        Site Description
                    </label>
                    <textarea id="site_description" name="site_description" rows="3"
                        placeholder="A brief description of your website for SEO and social sharing"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= e(old('site_description', $settings['site_description'] ?? '')) ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <label for="admin_email" class="block text-sm font-medium text-gray-700 mb-2">
                            Admin Email <span class="text-red-500">*</span>
                        </label>
                        <input type="email" id="admin_email" name="admin_email"
                            value="<?= e(old('admin_email', $settings['admin_email'] ?? '')) ?>"
                            placeholder="admin@example.com"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?= isset($errors['admin_email']) ? 'border-red-500' : '' ?>"
                            required>
                        <p class="text-xs text-gray-500 mt-1">Receives system notifications and alerts</p>
                        <?php if (isset($errors['admin_email'])) { ?>
                            <p class="text-red-600 text-sm mt-1"><?= e($errors['admin_email'][0]) ?></p>
                        <?php } ?>
                    </div>

                    <div>
                        <label for="timezone" class="block text-sm font-medium text-gray-700 mb-2">
                            Timezone <span class="text-red-500">*</span>
                        </label>
                        <select id="timezone" name="timezone"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <?php
                            $currentTz = old('timezone', $settings['timezone'] ?? 'UTC');
    $timezones = ['UTC', 'Europe/Athens', 'Europe/London', 'America/New_York', 'America/Los_Angeles', 'Asia/Tokyo'];
    ?>
                            <?php foreach ($timezones as $tz) { ?>
                                <option value="<?= e($tz) ?>" <?= $tz === $currentTz ? 'selected' : '' ?>>
                                    <?= e($tz) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Save Settings
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 2: CONTENT & DISPLAY -->
    <div class="tab-content" data-section="content">
        <form method="POST" action="/admin/settings">
            <?= csrf_field() ?>

            <div class="bg-white border rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Content Display</h3>
                <p class="text-sm text-gray-600 mb-6">Control how blog posts and content are displayed throughout your
                    site.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="posts_per_page" class="block text-sm font-medium text-gray-700 mb-2">
                            Posts Per Page <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="posts_per_page" name="posts_per_page"
                            value="<?= e(old('posts_per_page', $settings['posts_per_page'] ?? '10')) ?>" min="1"
                            max="50"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                    </div>

                    <div>
                        <label for="excerpt_length" class="block text-sm font-medium text-gray-700 mb-2">
                            Excerpt Length (characters) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="excerpt_length" name="excerpt_length"
                            value="<?= e(old('excerpt_length', $settings['excerpt_length'] ?? '200')) ?>" min="50"
                            max="500"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                    </div>

                    <div>
                        <label for="date_format" class="block text-sm font-medium text-gray-700 mb-2">
                            Date Format <span class="text-red-500">*</span>
                        </label>
                        <select id="date_format" name="date_format"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <?php
    $currentFormat = old('date_format', $settings['date_format'] ?? 'F j, Y');
    $formats = [
        'F j, Y' => 'January 21, 2026',
        'Y-m-d' => '2026-01-21',
        'd/m/Y' => '21/01/2026',
        'm/d/Y' => '01/21/2026',
    ];
    ?>
                            <?php foreach ($formats as $format => $example) { ?>
                                <option value="<?= e($format) ?>" <?= $format === $currentFormat ? 'selected' : '' ?>>
                                    <?= e($example) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div>
                        <label for="allow_comments" class="block text-sm font-medium text-gray-700 mb-2">
                            Comments
                        </label>
                        <select id="allow_comments" name="allow_comments"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php $allowComments = old('allow_comments', $settings['allow_comments'] ?? '1'); ?>
                            <option value="1" <?= $allowComments == '1' ? 'selected' : '' ?>>Enabled</option>
                            <option value="0" <?= $allowComments == '0' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Save Settings
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 3: USERS & REGISTRATION -->
    <div class="tab-content" data-section="users">
        <form method="POST" action="/admin/settings">
            <?= csrf_field() ?>

            <div class="bg-white border rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">User Registration</h3>
                <p class="text-sm text-gray-600 mb-6">Control who can register and what role they receive by default.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="registration_enabled" class="block text-sm font-medium text-gray-700 mb-2">
                            Allow Registration
                        </label>
                        <select id="registration_enabled" name="registration_enabled"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php $regEnabled = old('registration_enabled', $settings['registration_enabled'] ?? '1'); ?>
                            <option value="1" <?= $regEnabled == '1' ? 'selected' : '' ?>>Enabled</option>
                            <option value="0" <?= $regEnabled == '0' ? 'selected' : '' ?>>Disabled</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Disable to prevent new user registrations</p>
                    </div>

                    <div>
                        <label for="default_user_role" class="block text-sm font-medium text-gray-700 mb-2">
                            Default User Role <span class="text-red-500">*</span>
                        </label>
                        <select id="default_user_role" name="default_user_role"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <?php
    $currentRole = old('default_user_role', $settings['default_user_role'] ?? '3');
    // should load these from the database, but for now hardcode common roles
    $roles = [
        '3' => 'Author',
        '4' => 'Subscriber',
    ];
    ?>
                            <?php foreach ($roles as $id => $name) { ?>
                                <option value="<?= e($id) ?>" <?= $id == $currentRole ? 'selected' : '' ?>>
                                    <?= e($name) ?>
                                </option>
                            <?php } ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Assigned to new users after registration</p>
                    </div>

                    <div>
                        <label for="require_email_verification" class="block text-sm font-medium text-gray-700 mb-2">
                            Email Verification
                        </label>
                        <select id="require_email_verification" name="require_email_verification"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php $emailVerif = old('require_email_verification', $settings['require_email_verification'] ?? '0'); ?>
                            <option value="1" <?= $emailVerif == '1' ? 'selected' : '' ?>>Required</option>
                            <option value="0" <?= $emailVerif == '0' ? 'selected' : '' ?>>Not required</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Future feature: require email verification before login
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Save Settings
                </button>
            </div>
        </form>
    </div>

    <!-- TAB 4: EMAIL SETTINGS -->
    <div class="tab-content" data-section="email">
        <div class="bg-white border rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Mail Configuration</h3>
            <p class="text-sm text-gray-600 mb-6">Current email settings loaded from your environment configuration.</p>

            <!-- we display read-only mail config for diagnostics -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-4 mb-4 font-mono text-sm">
                <dl class="space-y-2">
                    <div class="flex">
                        <dt class="font-semibold text-gray-700 w-32">Driver:</dt>
                        <dd class="text-gray-600"><?= e($mail_config['driver']) ?></dd>
                    </div>
                    <div class="flex">
                        <dt class="font-semibold text-gray-700 w-32">SMTP Host:</dt>
                        <dd class="text-gray-600"><?= e($mail_config['host']) ?></dd>
                    </div>
                    <div class="flex">
                        <dt class="font-semibold text-gray-700 w-32">SMTP Port:</dt>
                        <dd class="text-gray-600"><?= e($mail_config['port']) ?></dd>
                    </div>
                    <div class="flex">
                        <dt class="font-semibold text-gray-700 w-32">From Address:</dt>
                        <dd class="text-gray-600"><?= e($mail_config['from_address']) ?></dd>
                    </div>
                    <div class="flex">
                        <dt class="font-semibold text-gray-700 w-32">From Name:</dt>
                        <dd class="text-gray-600"><?= e($mail_config['from_name']) ?></dd>
                    </div>
                    <div class="flex">
                        <dt class="font-semibold text-gray-700 w-32">Encryption:</dt>
                        <dd class="text-gray-600"><?= e($mail_config['encryption']) ?></dd>
                    </div>
                    <div class="flex">
                        <dt class="font-semibold text-gray-700 w-32">Debug Mode:</dt>
                        <dd class="text-gray-600"><?= $mail_config['debug'] ? 'Enabled' : 'Disabled' ?></dd>
                    </div>
                </dl>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                <div class="flex">
                    <svg class="w-5 h-5 text-blue-600 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                            clip-rule="evenodd" />
                    </svg>
                    <div class="text-sm text-blue-800">
                        <strong>Note:</strong> SMTP credentials are stored in your <code
                            class="bg-blue-100 px-1 rounded">.env</code> file and cannot be edited here for security
                        reasons. Update your environment configuration to change mail settings.
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Test Email Delivery</h3>
            <p class="text-sm text-gray-600 mb-6">Send a test email to verify your SMTP configuration is working
                correctly.</p>

            <form method="POST" action="/admin/settings/test-email">
                <?= csrf_field() ?>

                <div class="mb-4">
                    <label for="test_recipient" class="block text-sm font-medium text-gray-700 mb-2">
                        Recipient Email <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="test_recipient" name="test_recipient"
                        value="<?= e(old('test_recipient', '')) ?>" placeholder="your-email@example.com"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 <?= isset($errors['test_recipient']) ? 'border-red-500' : '' ?>"
                        required>
                    <p class="text-xs text-gray-500 mt-1">We'll send a test message to this address</p>
                    <?php if (isset($errors['test_recipient'])) { ?>
                        <p class="text-red-600 text-sm mt-1"><?= e($errors['test_recipient'][0]) ?></p>
                    <?php } ?>
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                        class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Send Test Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // handle tab switching without page reload for better UX
    document.addEventListener('DOMContentLoaded', () => {
        const tabs = document.querySelectorAll('.tab-button');
        const sections = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetSection = tab.dataset.tab;

                // update tab active state
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                // show only the selected section
                sections.forEach(section => {
                    if (section.dataset.section === targetSection) {
                        section.classList.add('active');
                    } else {
                        section.classList.remove('active');
                    }
                });
            });
        });
    });
</script>

{% endblock %}