{% extends "back.lex.php" %}

{% block title %}Edit User{% endblock %}
{% block subtitle %}Profile, preferences, access and security for this account.{% endblock %}

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
    'notify_insights_digest' => 'account.notifications.analyticsDigest',
    'notify_insights_milestones' => 'account.notifications.analyticsMilestones',
    'notify_insights_spikes' => 'account.notifications.analyticsSpikes',
];

$switchOn = static fn (string $name, string $label, bool $on): string =>
    '<div class="flex items-center justify-between gap-4"><label for="'.e($name).'" class="text-sm font-medium">'.$label.'</label>'
    .'<input type="hidden" name="'.e($name).'" value="0">'
    .'<input type="checkbox" role="switch" class="switch" id="'.e($name).'" name="'.e($name).'" value="1"'.($on ? ' checked' : '').'></div>';

$sectionTitle = 'text-base font-semibold text-slate-900 dark:text-zink-50 mb-1';
$sectionHint = 'text-sm text-slate-500 dark:text-zink-300 mb-5';
$small = 'text-xs text-slate-500 dark:text-zink-300';
$base = '/admin/users/'.$userId;
$mayActOnTarget = $canManageUsers && (!$targetIsAdmin || $actorIsAdmin);
$isSuspended = $user['suspended_at'] !== null;
$twoFactorOn = !empty($user['two_factor_confirmed_at']);

$saveBar = static function (string $section): string {
    return '<div class="flex justify-end mt-6">'
        .'<button type="submit" name="_section" value="'.e($section).'" class="btn inline-flex items-center gap-2 font-medium rounded-lg bg-slate-900 text-white border-slate-900 hover:bg-slate-700 hover:border-slate-700 dark:bg-zink-100 dark:text-zink-800 dark:border-zink-100 dark:hover:bg-white">'
        .'<i data-lucide="save" class="size-4"></i> Save changes</button></div>';
};

$tabs = [
    'account' => ['user', 'Account'],
    'profile' => ['user-circle', 'Public profile'],
    'links' => ['link', 'Links'],
    'preferences' => ['sliders-horizontal', 'Preferences'],
    'notifications' => ['bell', 'Notifications'],
    'access' => ['shield', 'Access'],
];
if ($canManageUsers) {
    $tabs['security'] = ['key-round', 'Security'];
}
if ($mayActOnTarget && !$isSelf) {
    $tabs['danger'] = ['alert-triangle', 'Danger zone'];
}

$row = 'flex flex-wrap items-center justify-between gap-3 py-4 border-t border-slate-100 dark:border-zink-600 first:border-t-0 first:pt-0';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-6xl">
    {% include "areas/admin/_errors.lex.php" %}

    <div class="settings-layout" data-panel-tabs>
        <nav class="settings-nav" role="tablist" aria-label="Account sections">
            <?php foreach ($tabs as $key => [$icon, $label]) { ?>
            <button type="button" role="tab" data-panel-tab="<?= e($key) ?>" class="settings-nav-item <?= $key === 'account' ? 'active' : '' ?>">
                <i data-lucide="<?= e($icon) ?>" class="size-4"></i> <?= e($label) ?>
            </button>
            <?php } ?>
        </nav>

        <div class="settings-panels">
            <form method="post" action="/admin/users/<?= $userId ?>/update">
                {{ csrf_field() }}

                <section data-panel="account" class="card">
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Account</h3>
                        <p class="<?= $sectionHint ?>">How they sign in and how they are named.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {% cmp="input" type="text" label="Tag" name="handle" value="{$handle}" underlabel="Their @tag and public page address. Reserved words are not checked here." required %}
                            {% cmp="input" type="email" label="Email" name="email" value="{$email}" underlabel="{$emailHint}" required %}
                            {% cmp="input" type="text" label="First Name" name="first_name" value="{$firstName}" %}
                            {% cmp="input" type="text" label="Last Name" name="last_name" value="{$lastName}" %}
                        </div>
                        <?= $saveBar('account') ?>
                    </div>
                </section>

                <section data-panel="profile" class="card" hidden>
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Public profile</h3>
                        <p class="<?= $sectionHint ?>">What readers see at /profile/<?= e($handle) ?>.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-2">
                                {% cmp="input" type="textarea" label="Bio" name="bio" value="{$bio}" rows="4" %}
                            </div>
                            {% cmp="input" type="text" label="Occupation" name="occupation" value="{$occupation}" %}
                            {% cmp="input" type="text" label="Location" name="location" value="{$location}" %}
                        </div>
                        <div class="flex flex-col gap-4 mt-6 pt-6 border-t border-slate-100 dark:border-zink-600">
                            <?= $switchOn('is_public', 'Profile is public', $isPublic) ?>
                            <?= $switchOn('show_name', 'Show their name instead of their @tag', $showName) ?>
                            <?php if ($hasAvatar) { ?>
                            <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                                <input type="checkbox" name="remove_avatar" value="1" class="form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500">
                                Remove their profile picture
                            </label>
                            <?php } ?>
                        </div>
                        <?= $saveBar('profile') ?>
                    </div>
                </section>

                <section data-panel="links" class="card" hidden>
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Links</h3>
                        <p class="<?= $sectionHint ?>">Leave a field empty to remove that link.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <?php foreach ($networks as $network) {
                                $networkUrl = (string) ($socialLinks[$network] ?? '');
                                $networkLabel = ucfirst($network); ?>
                            {% cmp="input" type="url" label="{$networkLabel}" name="{$network}" value="{$networkUrl}" placeholder="https://" %}
                            <?php } ?>
                        </div>
                        <?= $saveBar('links') ?>
                    </div>
                </section>

                <section data-panel="preferences" class="card" hidden>
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Preferences</h3>
                        <p class="<?= $sectionHint ?>">Settings they would normally change themselves.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            {% cmp="input" type="text" label="Timezone" name="timezone" value="{$timezone}" placeholder="Europe/Athens" underlabel="An IANA name. Empty follows the site timezone." %}
                            {% cmp="select" label="Interface language" name="locale" options="{$localeChoices}" selectedKey="{$locale}" %}
                            {% cmp="select" label="Default blog" name="default_blog_id" options="{$blogOptions}" selectedKey="{$defaultBlog}" %}
                        </div>
                        <?= $saveBar('preferences') ?>
                    </div>
                </section>

                <section data-panel="notifications" class="card" hidden>
                    <div class="card-body">
                        <h3 class="<?= $sectionTitle ?>">Email notifications</h3>
                        <p class="<?= $sectionHint ?>">Only the ones that apply to their roles are listed, exactly as on their own settings page.</p>
                        <?php if ($notifyKeys === []) { ?>
                        <p class="text-sm text-slate-500 dark:text-zink-300">None apply to this account.</p>
                        <?php } else { ?>
                        <div class="flex flex-col gap-4">
                            <?php foreach ($notifyKeys as $notifyKey) {
                                $notifyOn = !isset($preferences[$notifyKey]) || (int) $preferences[$notifyKey] === 1; ?>
                            <?= $switchOn($notifyKey, e($t($notifyLabels[$notifyKey] ?? $notifyKey)), $notifyOn) ?>
                            <?php } ?>
                        </div>
                        <?= $saveBar('notifications') ?>
                        <?php } ?>
                    </div>
                </section>
            </form>

            <section data-panel="access" class="card" hidden>
                <div class="card-body">
                    <h3 class="<?= $sectionTitle ?>">Access</h3>
                    <p class="<?= $sectionHint ?>">What they can do on the site. Each change has its own screen with its own checks.</p>

                    <div class="<?= $row ?>">
                        <div>
                            <p class="text-sm font-medium">Site role</p>
                            <p class="<?= $small ?>"><?= $siteRoles === [] ? 'No site role.' : e(implode(', ', array_column($siteRoles, 'role_name'))) ?></p>
                        </div>
                        <?php if ($canManageUsers && $canAssignSiteRoles) { ?>
                        {% cmp="btn" href="{$base}/site-role" variant="slate" icon="shield" label="Change site role" %}
                        <?php } ?>
                    </div>

                    <div class="<?= $row ?>">
                        <div>
                            <p class="text-sm font-medium">Blog roles</p>
                            <p class="<?= $small ?>"><?= $memberBlogs === [] ? 'Not a member of any blog.' : count($memberBlogs).' blog'.(count($memberBlogs) === 1 ? '' : 's') ?></p>
                        </div>
                        <?php if ($mayActOnTarget) { ?>
                        {% cmp="btn" href="{$base}/blog-roles" variant="slate" icon="users" label="Change blog roles" %}
                        <?php } ?>
                    </div>

                    <div class="<?= $row ?>">
                        <div>
                            <p class="text-sm font-medium">Suspension</p>
                            <p class="<?= $small ?>"><?= $isSuspended ? 'Suspended.' : 'Not suspended.' ?></p>
                        </div>
                        <?php if ($mayActOnTarget && !$isSelf) { ?>
                        <?php $suspendLabel = $isSuspended ? 'Lift suspension' : 'Suspend'; ?>
                        {% cmp="btn" href="{$base}/suspend" variant="slate" icon="ban" label="{$suspendLabel}" %}
                        <?php } ?>
                    </div>
                </div>
            </section>

            <?php if ($canManageUsers) { ?>
            <section data-panel="security" class="card" hidden>
                <div class="card-body">
                    <h3 class="<?= $sectionTitle ?>">Security</h3>
                    <p class="<?= $sectionHint ?>">Sign-in protection and ways to act as this account.</p>

                    <div class="<?= $row ?>">
                        <div>
                            <p class="text-sm font-medium">Password</p>
                            <p class="<?= $small ?>">Send a reset link or set a new one.</p>
                        </div>
                        <?php if ($mayActOnTarget && !$isSelf) { ?>
                        {% cmp="btn" href="{$base}/password" variant="slate" icon="key-round" label="Reset password" %}
                        <?php } ?>
                    </div>

                    <div class="<?= $row ?>">
                        <div>
                            <p class="text-sm font-medium">Two-step sign-in</p>
                            <p class="<?= $small ?>"><?= $twoFactorOn ? 'On.' : 'Off.' ?></p>
                        </div>
                        <?php if ($mayActOnTarget && !$isSelf && $twoFactorOn) {
                            $resetTwoFactorAttrs = [
                                'form' => 'resetTwoFactorForm',
                                'data-confirm' => '@'.$user['handle'].' will be signed out everywhere and can sign in with just a password until they set it up again.',
                                'data-confirm-title' => 'Turn off two-step sign-in?',
                                'data-confirm-ok' => 'Turn it off',
                                'data-confirm-tone' => 'warning',
                            ]; ?>
                        {% cmp="btn" type="submit" variant="slate" icon="shield-off" label="Reset two-step sign-in" attrs="{$resetTwoFactorAttrs}" %}
                        <?php } ?>
                    </div>

                    <div class="<?= $row ?>">
                        <div>
                            <p class="text-sm font-medium">Log in as</p>
                            <p class="<?= $small ?>"><?= $impersonationRefusal === null ? 'Sign in as this account to see what they see.' : e($impersonationRefusal) ?></p>
                        </div>
                        <?php if ($impersonationRefusal === null) { ?>
                        {% cmp="btn" href="{$base}/impersonate" variant="slate" icon="log-in" label="Log in as" %}
                        <?php } ?>
                    </div>

                    <?php if ($pendingErasure) { ?>
                    <p class="<?= $small ?> mt-2">They asked to delete this account; it is due <?= e(local_datetime((string) ($pendingErasure['scheduled_for'] ?? ''), 'M j, Y')) ?>.</p>
                    <?php } ?>
                </div>
            </section>
            <?php } ?>

            <?php if ($mayActOnTarget && !$isSelf) { ?>
            <section data-panel="danger" class="card" hidden>
                <div class="card-body">
                    <h3 class="<?= $sectionTitle ?>">Danger zone</h3>
                    <p class="<?= $sectionHint ?>">Deleting an account asks what happens to their content first.</p>
                    {% cmp="btn" href="{$base}/delete" variant="red" icon="trash-2" label="Delete user" %}
                </div>
            </section>
            <?php } ?>
        </div>
    </div>
</div>

<?php if ($mayActOnTarget && !$isSelf && $twoFactorOn) { ?>
<form method="post" action="<?= e($base) ?>/two-factor/reset" id="resetTwoFactorForm"><?= csrf_field() ?></form>
<?php } ?>
{% endblock %}
