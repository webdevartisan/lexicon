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
     * Store a newly uploaded avatar as the crop source and render a first avatar
     * from a centred square. Responds with JSON so the profile page can open the
     * cropper on the returned source without a full reload.
     */
    public function uploadAvatar(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];
        $avatarFile = $this->request->files['avatar'] ?? null;

        if (empty($avatarFile['name']) || $avatarFile['error'] !== UPLOAD_ERR_OK) {
            return $this->json(['error' => chrome_translate('account.flash.avatarSelectImage')], 400);
        }

        try {
            $profile = $this->profiles->findOrCreate($userId);

            // clear the previous avatar and its source so we don't leave orphans
            foreach (['avatar_url', 'avatar_source_url'] as $key) {
                if (!empty($profile[$key])) {
                    $this->deleteAvatarFile($userId, $profile[$key]);
                }
            }

            $source = $this->uploader->storeAvatarSource($avatarFile, $userId);
            $rect = $this->centeredSquare($source['width'], $source['height']);
            $avatarUrl = $this->uploader->renderAvatar($userId, $source['path'], $rect);

            $this->profiles->upsert($userId, [
                'avatar_url' => $avatarUrl,
                'avatar_source_url' => $source['url'],
                'avatar_crop' => $this->packCrop($rect),
            ]);

            return $this->json([
                'avatar_url' => $avatarUrl,
                'source_url' => $source['url'],
                'source_width' => $source['width'],
                'source_height' => $source['height'],
                'crop' => $rect,
            ]);

        } catch (Exception $e) {
            error_log("Avatar upload failed for user {$userId}: ".$e->getMessage());

            return $this->json(['error' => chrome_translate('account.flash.avatarError')], 500);
        }
    }

    /**
     * Re-render the avatar from the stored source using a crop rectangle chosen in
     * the browser. No file is uploaded here, so a user can re-frame their avatar
     * any number of times without re-uploading.
     */
    public function cropAvatar(): Response
    {
        csrf()->assertValid($this->request->postParam('_token'));

        $userId = (int) auth()->user()['id'];
        $profile = $this->profiles->findOrCreate($userId);

        if (empty($profile['avatar_source_url'])) {
            return $this->json(['error' => chrome_translate('account.flash.avatarError')], 400);
        }

        [$dir] = $this->uploader->userProfilePath($userId);
        $sourcePath = $dir.'/'.basename((string) $profile['avatar_source_url']);
        if (!is_file($sourcePath)) {
            return $this->json(['error' => chrome_translate('account.flash.avatarError')], 400);
        }

        $rect = [
            'x' => max(0, (int) $this->request->postParam('crop_x')),
            'y' => max(0, (int) $this->request->postParam('crop_y')),
            'width' => (int) $this->request->postParam('crop_w'),
            'height' => (int) $this->request->postParam('crop_h'),
        ];

        if ($rect['width'] < 1 || $rect['height'] < 1) {
            return $this->json(['error' => chrome_translate('account.flash.avatarError')], 400);
        }

        try {
            if (!empty($profile['avatar_url'])) {
                $this->deleteAvatarFile($userId, $profile['avatar_url']);
            }

            $avatarUrl = $this->uploader->renderAvatar($userId, $sourcePath, $rect);

            $this->profiles->upsert($userId, [
                'avatar_url' => $avatarUrl,
                'avatar_crop' => $this->packCrop($rect),
            ]);

            return $this->json(['avatar_url' => $avatarUrl]);

        } catch (Exception $e) {
            error_log("Avatar crop failed for user {$userId}: ".$e->getMessage());

            return $this->json(['error' => chrome_translate('account.flash.avatarError')], 500);
        }
    }

    /**
     * The largest centred square that fits the source, used as the default crop so
     * an avatar exists even if the user never opens the cropper.
     *
     * @return array{x: int, y: int, width: int, height: int}
     */
    private function centeredSquare(int $width, int $height): array
    {
        $side = max(1, min($width, $height));

        return [
            'x' => (int) (($width - $side) / 2),
            'y' => (int) (($height - $side) / 2),
            'width' => $side,
            'height' => $side,
        ];
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $rect
     */
    private function packCrop(array $rect): string
    {
        return $rect['x'].','.$rect['y'].','.$rect['width'].','.$rect['height'];
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

        // clear both the rendered avatar and its re-crop source so nothing lingers
        foreach (['avatar_url', 'avatar_source_url'] as $key) {
            if (!empty($profile[$key])) {
                $this->deleteAvatarFile($userId, $profile[$key]);
            }
        }

        $this->profiles->upsert($userId, [
            'avatar_url' => null,
            'avatar_source_url' => null,
            'avatar_crop' => null,
        ]);

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
