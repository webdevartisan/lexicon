<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\LinksHelper;
use App\Models\GuidedSetupMarkModel;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Models\UserSocialLinkModel;
use App\Services\DisplayNameService;
use App\Services\PublicCacheInvalidator;
use App\Services\UploadService;
use App\Services\UserHandleValidator;
use Exception;
use Framework\Core\Response;

/**
 * Public identity on the front: name and tag, bio, avatar, social links.
 * Preferences, including whether the profile is public, live in
 * AccountPreferencesController and credentials in AccountSecurityController.
 *
 * Moved out of the dashboard: settings belong to the person, not to a blog, so
 * they live on the front for every account whatever its role.
 */
final class AccountProfileController extends AppController
{
    /**
     * The page each section's form lives on, which is where its save returns.
     */
    private const SECTION_PAGES = [
        'profile' => '/account/profile',
        'name' => '/account/name',
        'social' => '/account/social',
    ];

    public function __construct(
        private UserModel $users,
        private UserProfileModel $profiles,
        private UserSocialLinkModel $socials,
        private UserPreferencesModel $prefs,
        private UploadService $uploader,
        private PublicCacheInvalidator $cacheInvalidator,
        private DisplayNameService $displayNames,
        private UserHandleValidator $userHandleValidator,
        private GuidedSetupMarkModel $setupMarks,
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
     * Display the name and tag form.
     */
    public function editName(): Response
    {
        $userId = (int) auth()->user()['id'];

        return $this->view('public.Account.name', [
            'user' => $this->loadIdentity($userId),
            'profileUrlPrefix' => rtrim(base_url(), '/').lurl('/profile').'/',
        ]);
    }

    /**
     * Display the social link fields.
     */
    public function editSocial(): Response
    {
        $userId = (int) auth()->user()['id'];

        return $this->view('public.Account.social', ['user' => $this->loadIdentity($userId)]);
    }

    /**
     * Persist one section of the account: profile, name and tag, or social links.
     * Each page posts only its own fields, so a save never clears the others.
     */
    public function update(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->postParam('_token'));

        $section = (string) $this->request->postParam('section');
        $section = array_key_exists($section, self::SECTION_PAGES) ? $section : 'profile';
        $back = lurl(self::SECTION_PAGES[$section]);

        $validator = $this->validateOrFail($this->rulesFor($section), [
            'handle.user_handle' => 'A tag may only contain lowercase letters, numbers, and single hyphens.',
        ]);
        $validated = $validator->validated();

        $userId = (int) auth()->user()['id'];
        $user = auth()->user();
        $currentHandle = (string) $user['handle'];

        if ($section === 'name') {
            // An unchanged tag is not re-checked, so one claimed before its word was reserved stays usable.
            $handleRejection = $validated['handle'] === $currentHandle ? null : $this->handleRejection($validated['handle'], $userId);

            if ($handleRejection !== null) {
                $this->session->set('_errors', ['handle' => [$handleRejection]]);
                $this->session->set('_old_input', sanitize_old_input($this->request->all()));
                $this->flash('error', $handleRejection);

                return $this->redirect($back);
            }
        }

        $identityMoved = match ($section) {
            'name' => $this->saveName($userId, $user, $validated),
            'social' => $this->saveSocial($userId, $validated),
            default => $this->saveProfile($userId, $validated),
        };

        // a name change moves the cached display name when the preference is 'name'
        $newDisplayName = $this->displayNames->refreshCached($userId);

        // Author names and links are baked into cached blog pages, so any of
        // them moving has to clear those or the stale copy outlives the TTL.
        // The OLD handle is purged, since that is the URL already cached.
        if ($identityMoved || $newDisplayName !== $user['display_name_cached']) {
            $this->cacheInvalidator->purgeAuthorSurfaces($currentHandle);
        }

        $this->setupMarks->markMemberBlogs($userId, 'profile_saved');
        $this->flash('success', chrome_translate('account.flash.profileSaved'));

        return $this->redirect($back);
    }

    /**
     * Validation rules for the fields one section posts.
     *
     * @return array<string, string>
     */
    private function rulesFor(string $section): array
    {
        return match ($section) {
            'name' => [
                'first_name' => 'required|name|min:2|max:50',
                'last_name' => 'required|name|min:2|max:50',
                'handle' => 'required|user_handle|min:2|max:50',
            ],
            'social' => [
                'website' => 'url',
                'twitter' => 'url',
                'instagram' => 'url',
                'linkedin' => 'url',
                'github' => 'url',
            ],
            default => [
                'bio' => 'max:1000',
                'occupation' => 'max:100',
                'location' => 'max:100',
            ],
        };
    }

    /**
     * Save first and last name, the tag, and whether readers see the name beside it.
     *
     * @param  array<string, mixed>  $user  The signed-in user row
     * @param  array<string, mixed>  $validated
     * @return bool Whether the tag, and so the public address, changed
     */
    private function saveName(int $userId, array $user, array $validated): bool
    {
        $userUpdate = changedFields([
            'first_name' => $validated['first_name'] ?? '',
            'last_name' => $validated['last_name'] ?? '',
            'handle' => $validated['handle'],
        ], $user);

        if (!empty($userUpdate)) {
            $this->users->updateById($userId, $userUpdate);
        }

        // Must be written before the display name is recomputed, which re-reads it.
        $this->prefs->upsert($userId, [
            'display_name_preference' => $this->request->postParam('show_name') ? 'name' : 'handle',
        ]);

        return array_key_exists('handle', $userUpdate);
    }

    /**
     * Save the about fields: bio, occupation and location.
     *
     * @param  array<string, mixed>  $validated
     */
    private function saveProfile(int $userId, array $validated): bool
    {
        $profileData = changedFields([
            'bio' => $validated['bio'] ?? '',
            'occupation' => $validated['occupation'] ?? '',
            'location' => $validated['location'] ?? '',
        ], $this->profiles->findOrCreate($userId));

        if (!empty($profileData)) {
            $this->profiles->upsert($userId, $profileData);
        }

        return false;
    }

    /**
     * Save the five social links.
     *
     * @param  array<string, mixed>  $validated
     */
    private function saveSocial(int $userId, array $validated): bool
    {
        $socialData = changedFields([
            'website' => $validated['website'] ?? '',
            'twitter' => $validated['twitter'] ?? '',
            'instagram' => $validated['instagram'] ?? '',
            'linkedin' => $validated['linkedin'] ?? '',
            'github' => $validated['github'] ?? '',
        ], $this->socials->getKeyValueArrayLinks($userId));

        foreach ($socialData as $network => $url) {
            $this->socials->upsertLink($userId, $network, $url);
        }

        return false;
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

            $source = $this->uploader->storeAvatarSource($avatarFile);
            $rect = $this->centeredSquare($source['width'], $source['height']);
            $avatarUrl = $this->uploader->renderAvatar($source['path'], $rect);

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

        $sourcePath = $this->uploader->avatarFilePath((string) $profile['avatar_source_url']);
        if ($sourcePath === null || !is_file($sourcePath)) {
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

            $avatarUrl = $this->uploader->renderAvatar($sourcePath, $rect);

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
            $this->uploader->deleteAvatar($avatarUrl);
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
        $preferences = $this->prefs->findOrCreate($userId);

        $merged = array_merge($user, $profile ?: [], LinksHelper::linksToFlatInputs($links));

        $merged['show_name'] = $preferences['display_name_preference'] === 'name';

        $merged['avatar'] = $merged['avatar_url'] ?? null;
        $merged['initials'] = $this->computeInitials(
            $merged['first_name'] ?? '',
            $merged['last_name'] ?? '',
            $merged['handle']
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
     * Why a new handle cannot be claimed, or null when it can.
     */
    private function handleRejection(string $handle, int $userId): ?string
    {
        if ($this->userHandleValidator->isReserved($handle)) {
            return chrome_translate('account.flash.tagReserved');
        }

        if ($this->userHandleValidator->isTaken($handle, $userId)) {
            return chrome_translate('account.flash.tagTaken');
        }

        return null;
    }

    /**
     * Build the avatar placeholder initials from name, falling back to the tag.
     */
    private function computeInitials(string $first, string $last, string $handle): string
    {
        $initials = mb_strtoupper(mb_substr(trim($first), 0, 1).mb_substr(trim($last), 0, 1));

        if ($initials === '') {
            $initials = mb_strtoupper(mb_substr(trim($handle), 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }
}
