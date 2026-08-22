<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\LinksHelper;
use App\Models\UserModel;
use App\Models\UserProfileModel;
use App\Models\UserSocialLinkModel;
use App\Services\DisplayNameService;
use App\Services\PublicCacheInvalidator;
use App\Services\UploadService;
use Exception;
use Framework\Core\Response;

/**
 * Public identity on the front: name, bio, avatar, public profile URL, social
 * links. The private preferences live in AccountPreferencesController and
 * credentials in AccountSecurityController.
 *
 * Moved out of the dashboard: settings belong to the person, not to a blog, so
 * they live on the front for every account whatever its role.
 */
final class AccountProfileController extends AppController
{
    public function __construct(
        private UserModel $users,
        private UserProfileModel $profiles,
        private UserSocialLinkModel $socials,
        private UploadService $uploader,
        private PublicCacheInvalidator $cacheInvalidator,
        private DisplayNameService $displayNames
    ) {}

    /**
     * /account lands here rather than showing a page of its own.
     */
    public function redirectToProfile(): Response
    {
        return $this->redirect(lurl('/account/profile'));
    }

    /**
     * Display the public identity form.
     */
    public function edit(): Response
    {
        $userId = (int) auth()->user()['id'];

        return $this->view('public.Account.profile', [
            'user' => $this->loadIdentity($userId),
            'profileUrlPrefix' => rtrim(base_url(), '/').lurl('/profile').'/',
        ]);
    }

    /**
     * Persist name, bio, public URL and social links.
     */
    public function update(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];
        $slugInput = strtolower(trim((string) $this->request->postParam('public_profile_url')));

        $rules = [
            'first_name' => 'required|name|min:2|max:50',
            'last_name' => 'required|name|min:2|max:50',
            'bio' => 'max:1000',
            'occupation' => 'max:100',
            'location' => 'max:100',
            'website' => 'url',
            'twitter' => 'url',
            'instagram' => 'url',
            'linkedin' => 'url',
            'github' => 'url',
        ];

        // the slug rule rejects empty values, so only apply it when a slug was submitted
        if ($slugInput !== '') {
            $rules['public_profile_url'] = 'slug|min:2|max:50';
        }

        $validator = $this->validateOrFail($rules, [
            'public_profile_url.slug' => 'Profile URL may only contain lowercase letters, numbers, and single hyphens.',
        ]);

        $validated = $validator->validated();

        $profile = $this->profiles->findOrCreate($userId);
        $currentSlug = $profile['slug'] ?? null;

        // reserved and taken slugs would hit the unique index at write time; reject them with a
        // friendly error. Unchanged slugs are skipped so a grandfathered reserved slug stays usable.
        if ($slugInput !== '' && $slugInput !== $currentSlug && !$this->profiles->isSlugAvailable($slugInput, $userId)) {
            $this->session->set('_errors', [
                'public_profile_url' => ['This profile URL is already taken.'],
            ]);
            $this->session->set('_old_input', $this->request->all());
            $this->flash('error', chrome_translate('account.flash.profileUrlTaken'));

            return $this->redirect(lurl('/account/profile'));
        }

        $user = auth()->user();
        $userUpdate = changedFields([
            'first_name' => $validated['first_name'] ?? '',
            'last_name' => $validated['last_name'] ?? '',
        ], $user);

        if (!empty($userUpdate)) {
            $this->users->updateById($userId, $userUpdate);
        }

        $profileData = changedFields([
            'bio' => $validated['bio'] ?? '',
            // empty slug must be NULL: the unique index tolerates many NULLs but only one ''
            'slug' => $slugInput !== '' ? $slugInput : null,
            'occupation' => $validated['occupation'] ?? '',
            'location' => $validated['location'] ?? '',
            'is_public' => $this->request->postParam('is_public') ? '1' : '0',
        ], $profile);

        if (!empty($profileData)) {
            $this->profiles->upsert($userId, $profileData);

            // Author links are baked into cached blog pages, so a visibility or
            // slug change has to clear them or the stale links outlive the TTL.
            // The old slug is purged, since that's the URL already cached.
            if (array_key_exists('is_public', $profileData) || array_key_exists('slug', $profileData)) {
                $this->cacheInvalidator->purgeAuthorSurfaces(
                    is_string($currentSlug) && $currentSlug !== '' ? $currentSlug : null
                );
            }
        }

        $socialLinks = $this->socials->getKeyValueArrayLinks($userId);
        $socialData = changedFields([
            'website' => $validated['website'] ?? '',
            'twitter' => $validated['twitter'] ?? '',
            'instagram' => $validated['instagram'] ?? '',
            'linkedin' => $validated['linkedin'] ?? '',
            'github' => $validated['github'] ?? '',
        ], $socialLinks);

        if (!empty($socialData)) {
            foreach ($socialData as $network => $url) {
                $this->socials->upsertLink($userId, $network, $url);
            }
        }

        // a name change moves the cached display name when the preference is 'name'
        $this->displayNames->refreshCached($userId);

        $this->flash('success', chrome_translate('account.flash.profileSaved'));

        return $this->redirect(lurl('/account/profile'));
    }

    /**
     * Upload user profile avatar.
     *
     * The dropzone submits this form from JavaScript, which drops the clicked
     * button's name and value, so no validation rule may key on a button here.
     */
    public function uploadAvatar(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];
        $avatarFile = $this->request->files['avatar'] ?? null;

        if (empty($avatarFile['name']) || $avatarFile['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', chrome_translate('account.flash.avatarSelectImage'));

            return $this->redirect(lurl('/account/profile'));
        }

        try {
            [$dir, $url] = $this->uploader->userProfilePath($userId);

            // delete old avatar before uploading new one
            $profile = $this->profiles->findOrCreate($userId);
            if (!empty($profile['avatar_url'])) {
                $this->deleteAvatarFile($userId, $profile['avatar_url']);
            }

            $avatarUrl = $this->uploader->storeImage($avatarFile, [
                'dir' => $dir,
                'base_url' => $url,
                'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp'],
                'max_bytes' => 2 * 1024 * 1024, // 2MB max
                'rename' => 'avatar',
            ]);

            $this->profiles->upsert($userId, ['avatar_url' => $avatarUrl]);

            $this->flash('success', chrome_translate('account.flash.avatarUploaded'));

            return $this->redirect(lurl('/account/profile'));

        } catch (Exception $e) {
            error_log("Avatar upload failed for user {$userId}: ".$e->getMessage());
            $this->flash('error', chrome_translate('account.flash.avatarError'));

            return $this->redirect(lurl('/account/profile'));
        }
    }

    /**
     * Remove the user's profile avatar from storage and the database.
     */
    public function removeAvatar(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];
        $profile = $this->profiles->findOrCreate($userId);

        if (!empty($profile['avatar_url'])) {
            $this->deleteAvatarFile($userId, $profile['avatar_url']);
        }

        $this->profiles->upsert($userId, ['avatar_url' => null]);

        $this->flash('success', chrome_translate('account.flash.avatarRemoved'));

        return $this->redirect(lurl('/account/profile'));
    }

    /**
     * Delete an avatar file from storage.
     *
     * Failures are logged rather than thrown: the database update matters more
     * than reclaiming the file.
     */
    private function deleteAvatarFile(int $userId, string $avatarUrl): void
    {
        try {
            [$dir, $url] = $this->uploader->userProfilePath($userId);

            $filename = basename(parse_url($avatarUrl, PHP_URL_PATH));
            $filePath = $dir.'/'.$filename;

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (Exception $e) {
            error_log("Failed to delete avatar file for user {$userId}: ".$e->getMessage());
        }
    }

    /**
     * Load the public-facing fields plus the activity counters shown alongside them.
     *
     * @return array<string, mixed>
     */
    private function loadIdentity(int $userId): array
    {
        $user = $this->users->findById($userId);

        if (!$user) {
            throw new Exception("User record not found for ID {$userId}");
        }

        $profile = $this->profiles->findOrCreate($userId);
        $links = $this->socials->listByUser($userId);

        $merged = array_merge($user, $profile ?: [], LinksHelper::linksToFlatInputs($links));

        $merged['avatar'] = $merged['avatar_url'] ?? null;
        $merged['initials'] = $this->computeInitials(
            $merged['first_name'] ?? '',
            $merged['last_name'] ?? '',
            $merged['username'] ?? ''
        );

        // denormalized counters are unreliable (often stale at 0), so recount when empty
        $merged['post_count'] = !empty($merged['posts_count'])
            ? (int) $merged['posts_count']
            : $this->users->countPosts($userId);
        $merged['comment_count'] = !empty($merged['comments_received_count'])
            ? (int) $merged['comments_received_count']
            : $this->users->countCommentsReceived($userId);

        return $merged;
    }

    /**
     * Build the avatar placeholder initials from name, falling back to username.
     */
    private function computeInitials(string $first, string $last, string $username): string
    {
        $initials = mb_strtoupper(mb_substr(trim($first), 0, 1).mb_substr(trim($last), 0, 1));

        if ($initials === '') {
            $initials = mb_strtoupper(mb_substr(trim($username), 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }
}
