{% extends "back.lex.php" %}

{% block title %}Edit User{% endblock %}
{% block subtitle %}Profile, preferences and links. Password, roles and suspension each have their own action.{% endblock %}

{% block body %}
<?php
$userId = (int) $user['id'];
$handle = (string) $user['handle'];
$email = (string) $user['email'];
$firstName = (string) ($user['first_name'] ?? '');
$lastName = (string) ($user['last_name'] ?? '');
$bio = (string) ($profile['bio'] ?? '');
$occupation = (string) ($profile['occupation'] ?? '');
$location = (string) ($profile['location'] ?? '');
$isPublic = !isset($profile['is_public']) || (int) $profile['is_public'] === 1;
$hasAvatar = !empty($profile['avatar_url']);
$showName = ($preferences['display_name_preference'] ?? 'handle') === 'name';
$timezone = (string) ($preferences['timezone'] ?? '');
$locale = (string) ($preferences['locale'] ?? '');
$defaultBlog = (string) ($preferences['default_blog_id'] ?? '');

$localeChoices = ['auto' => 'Follow the page language'];
foreach ($locales as $code) {
    $localeChoices[$code] = strtoupper($code);
}

$blogOptions = ['' => 'None'] + array_map('strval', $blogChoices);

$emailHint = 'Changing it sends a confirmation link to the new address. It takes effect only once they follow that link while signed in, the same as when they change it themselves.';
if ($pendingEmail) {
    $emailHint = 'Waiting on confirmation of '.$pendingEmail['new_email']
        .((int) $pendingEmail['is_expired'] === 1 ? ' (link expired).' : '.').' '.$emailHint;
}

// Labels come from the account's own notification page, so both read the same.
$notifyLabels = [
    'notify_post_status' => 'account.notifications.postStatus',
    'notify_review_requests' => 'account.notifications.review',
    'notify_comment_replies' => 'account.notifications.reply',
    'notify_comments_authored' => 'account.notifications.authored',
    'notify_comments_moderation' => 'account.notifications.moderation',
    'notify_comments_blog' => 'account.notifications.blog',
    'notify_role_changes' => 'account.notifications.role',
    'notify_invites' => 'account.notifications.invites',
];

$checkboxClass = 'form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500';
$sectionTitle = 'text-base font-semibold text-slate-900 dark:text-zink-50';
$sectionHint = 'text-xs text-slate-400 dark:text-zink-400 mb-4';
$showUrl = '/admin/users/'.$userId;
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-3xl">
    {% include "areas/admin/_errors.lex.php" %}

    <form method="post" action="/admin/users/<?= $userId ?>/update" class="card">
        {{ csrf_field() }}

        <div class="card-body border-b border-slate-200 dark:border-zink-500">
            <h3 class="<?= $sectionTitle ?>">Account</h3>
            <p class="<?= $sectionHint ?>">How they sign in and how they are named.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {% cmp="input" type="text" label="Tag" name="handle" value="{$handle}" underlabel="Their @tag and public page address. Reserved words are not checked here." required %}
                {% cmp="input" type="email" label="Email" name="email" value="{$email}" underlabel="{$emailHint}" required %}
                {% cmp="input" type="text" label="First Name" name="first_name" value="{$firstName}" %}
                {% cmp="input" type="text" label="Last Name" name="last_name" value="{$lastName}" %}
            </div>
        </div>

        <div class="card-body border-b border-slate-200 dark:border-zink-500">
            <h3 class="<?= $sectionTitle ?>">Public profile</h3>
            <p class="<?= $sectionHint ?>">What readers see at /profile/<?= e($handle) ?>.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    {% cmp="input" type="textarea" label="Bio" name="bio" value="{$bio}" rows="4" %}
                </div>
                {% cmp="input" type="text" label="Occupation" name="occupation" value="{$occupation}" %}
                {% cmp="input" type="text" label="Location" name="location" value="{$location}" %}
                <div class="md:col-span-2 flex flex-col gap-2">
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="is_public" value="1" class="<?= $checkboxClass ?>" <?= $isPublic ? 'checked' : '' ?>>
                        Profile is public
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="show_name" value="1" class="<?= $checkboxClass ?>" <?= $showName ? 'checked' : '' ?>>
                        Show their name instead of their @tag
                    </label>
                    <?php if ($hasAvatar) { ?>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="checkbox" name="remove_avatar" value="1" class="<?= $checkboxClass ?>">
                        Remove their profile picture
                    </label>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="card-body border-b border-slate-200 dark:border-zink-500">
            <h3 class="<?= $sectionTitle ?>">Links</h3>
            <p class="<?= $sectionHint ?>">Leave a field empty to remove that link.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php foreach ($networks as $network) {
                    $networkUrl = (string) ($socialLinks[$network] ?? '');
                    $networkLabel = ucfirst($network); ?>
                {% cmp="input" type="url" label="{$networkLabel}" name="{$network}" value="{$networkUrl}" placeholder="https://" %}
                <?php } ?>
            </div>
        </div>

        <div class="card-body border-b border-slate-200 dark:border-zink-500">
            <h3 class="<?= $sectionTitle ?>">Preferences</h3>
            <p class="<?= $sectionHint ?>">Settings they would normally change themselves.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {% cmp="input" type="text" label="Timezone" name="timezone" value="{$timezone}" placeholder="Europe/Athens" underlabel="An IANA name. Empty follows the site timezone." %}
                {% cmp="select" label="Interface language" name="locale" options="{$localeChoices}" selectedKey="{$locale}" %}
                {% cmp="select" label="Default blog" name="default_blog_id" options="{$blogOptions}" selectedKey="{$defaultBlog}" %}
            </div>
        </div>

        <div class="card-body">
            <h3 class="<?= $sectionTitle ?>">Email notifications</h3>
            <p class="<?= $sectionHint ?>">Only the ones that apply to their roles are listed, exactly as on their own settings page.</p>
            <?php if ($notifyKeys === []) { ?>
            <p class="text-sm text-slate-500 dark:text-zink-300">None apply to this account.</p>
            <?php } else { ?>
            <div class="flex flex-col gap-2">
                <?php foreach ($notifyKeys as $notifyKey) {
                    $notifyOn = !isset($preferences[$notifyKey]) || (int) $preferences[$notifyKey] === 1; ?>
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="<?= e($notifyKey) ?>" value="1" class="<?= $checkboxClass ?>" <?= $notifyOn ? 'checked' : '' ?>>
                    <?= e($t($notifyLabels[$notifyKey] ?? $notifyKey)) ?>
                </label>
                <?php } ?>
            </div>
            <?php } ?>
        </div>

        {% cmp="form-footer" cancelHref="{$showUrl}" submitLabel="Save Changes" submitIcon="save" %}
    </form>
</div>
{% endblock %}
