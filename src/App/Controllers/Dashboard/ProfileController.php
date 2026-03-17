<?php

namespace App\Controllers\Dashboard;

use App\Controllers\AppController;
use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Models\UserSocialLinkModel;
use App\Services\UploadService;
use DateTime;
use DateTimeZone;
use Exception;
use Framework\Core\Response;

class ProfileController extends AppController
{
    public function __construct(
        private UserModel $users,
        private UserProfileModel $profiles,
        private UserPreferencesModel $prefs,
        private UserSocialLinkModel $socials,
        private UploadService $uploader
    ) {}

    /**
     * Display the profile edit form.
     *
     * load user data from multiple tables and merge into a single
     * view model for easier template consumption.
     */
    public function edit(): Response
    {
        $userId = (int) auth()->user()['id'];

        // load all profile data
        $profileData = $this->loadProfileData($userId);

        // prepare timezone options for select dropdown
        $timezones = $this->getGroupedTimezones();

        return $this->view([
            'user' => $profileData,
            'timezones' => $timezones,
            'noBreadcrumb' => false,
        ]);
    }

    public function update(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $userId = (int) auth()->user()['id'];

        // validate all form input using the validation framework
        $validator = $this->validateOrFail([
            'first_name' => 'required|alpha|min:2|max:50',
            'last_name' => 'required|alpha|min:2|max:50',
            'email' => 'required|email|unique:users,email,'.$userId,
            'bio' => 'max:500',
            'public_profile_url' => 'alpha|min:2|max:50',
            'occupation' => 'max:100',
            'location' => 'max:100',
            'website' => 'url',
            'twitter_(x)' => 'url',
            'instagram' => 'url',
            'linkedin' => 'url',
            'github' => 'url',
            'display_name' => 'in:name,username',
            'default_visibility' => 'in:public,private,unlisted',
            'timezone' => 'in:'.implode(',', DateTimeZone::listIdentifiers()),
            'notify_comments' => 'boolean',
            'notify_likes' => 'boolean',
        ], [
            'email.unique' => 'This email address is already in use.',
            'timezone.in' => 'Please select a valid timezone.',
        ]);

        $validated = $validator->validated();

        // prepare user table updates only if fields changed
        $user = auth()->user();
        $userUpdate = changedFields([
            'first_name' => $validated['first_name'] ?? '',
            'last_name' => $validated['last_name'] ?? '',
            'email' => $validated['email'] ?? '',
        ], $user);

        if (!empty($userUpdate)) {
            $this->users->updateById($userId, $userUpdate);
        }

        // handle profile fields (bio, occupation, location)
        $profile = $this->profiles->findOrCreate($userId);
        $profileData = changedFields([
            'bio' => $validated['bio'] ?? '',
            'slug' => $validated['public_profile_url'] ?? '',
            'occupation' => $validated['occupation'] ?? '',
            'location' => $validated['location'] ?? '',
        ], $profile);

        if (!empty($profileData)) {
            $this->profiles->upsert($userId, $profileData);
        }

        // handle preferences
        $prefData = [
            'display_name_preference' => $validated['display_name'] ?? 'username',
            'default_post_visibility' => $validated['default_visibility'] ?? 'public',
            'timezone' => $validated['timezone'] ?? null,
            'notify_comments' => isset($validated['notify_comments']) ? 1 : 0,
            'notify_likes' => isset($validated['notify_likes']) ? 1 : 0,
        ];
        $this->prefs->upsert($userId, $prefData);

        // handle social links
        $socialLinks = $this->socials->getKeyValueArrayLinks($userId);
        $socialData = changedFields([
            'website' => $validated['website'] ?? '',
            'twitter' => $validated['twitter_(x)'] ?? '',
            'instagram' => $validated['instagram'] ?? '',
            'linkedin' => $validated['linkedin'] ?? '',
            'github' => $validated['github'] ?? '',
        ], $socialLinks);

        if (!empty($socialData)) {
            foreach ($socialData as $network => $url) {
                $this->socials->upsertLink($userId, $network, $url);
            }
        }

        // recompute cached display name according to preference
        $fresh = $this->users->findById($userId);
        $pref = $this->prefs->findOrCreate($userId);
        $display = $this->computeDisplay(
            $pref['display_name_preference'] ?? 'username',
            $fresh['first_name'] ?? '',
            $fresh['last_name'] ?? '',
            $fresh['username'] ?? ''
        );
        $this->users->updateById($userId, ['display_name_cached' => $display]);

        $this->flash('success', 'Profile updated successfully.');

        return $this->redirect('/dashboard/profile');
    }

    /**
     * Upload user profile avatar.
     *
     * handle file validation, storage, and database update.
     * Old avatar is automatically replaced.
     */
    public function uploadAvatar(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $userId = (int) auth()->user()['id'];
        $avatarFile = $this->request->files['avatar'] ?? null;

        // validate that a file was uploaded
        if (empty($avatarFile['name']) || $avatarFile['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Please select an image to upload.');

            return $this->redirect('/dashboard/profile');
        }

        try {
            // get the upload directory and URL base
            [$dir, $url] = $this->uploader->userProfilePath($userId);

            // delete old avatar before uploading new one
            $profile = $this->profiles->findOrCreate($userId);
            if (!empty($profile['avatar_url'])) {
                $this->deleteAvatarFile($userId, $profile['avatar_url']);
            }

            // store the new avatar
            $avatarUrl = $this->uploader->storeImage($avatarFile, [
                'dir' => $dir,
                'base_url' => $url,
                'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp'],
                'max_bytes' => 2 * 1024 * 1024, // 2MB max
                'rename' => 'avatar',
            ]);

            // update database with new avatar URL
            $this->profiles->upsert($userId, ['avatar_url' => $avatarUrl]);

            $this->flash('success', 'Avatar uploaded successfully.');

            return $this->redirect('/dashboard/profile');

        } catch (Exception $e) {
            // log upload errors and show user-friendly message
            error_log("Avatar upload failed for user {$userId}: ".$e->getMessage());
            $this->flash('error', 'Failed to upload avatar. '.$e->getMessage());

            return $this->redirect('/dashboard/profile');
        }
    }

    /**
     * Remove user's profile avatar.
     *
     * delete the avatar file from storage and clear the database reference.
     */
    public function removeAvatar(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $userId = (int) auth()->user()['id'];
        $profile = $this->profiles->findOrCreate($userId);

        // delete the physical file if it exists
        if (!empty($profile['avatar_url'])) {
            $this->deleteAvatarFile($userId, $profile['avatar_url']);
        }

        // clear avatar_url in database
        $this->profiles->upsert($userId, ['avatar_url' => null]);

        $this->flash('success', 'Avatar removed successfully.');

        return $this->redirect('/dashboard/profile');
    }

    public function updatePassword(): Response
    {
        // enforce CSRF protection
        csrf()->assertValid($this->request->post['_token'] ?? null);

        $userId = (int) auth()->user()['id'];

        // validate password fields using the validation framework
        $validator = $this->validateOrFail([
            'current_password' => 'required|password:basic|current_password',
            'new_password' => 'required|password:basic',
            'new_password_confirm' => 'required|password:basic|same:new_password',
        ], [
            'new_password.min' => 'Password must be at least 8 characters for security.',
            'new_password_confirm.same' => 'Password confirmation does not match.',
        ]);

        $validated = $validator->validated();

        // hash the new password (bcrypt handles salting automatically)
        $newHash = password_hash($validated['new_password'], PASSWORD_DEFAULT);

        // persist the new password hash
        $success = $this->users->updatePasswordHashById($userId, $newHash);

        if (!$success) {
            // log database errors but show generic message to user
            error_log("Failed to update password for user {$userId}");

            $this->session->set('_errors', [
                'new_password' => ['Could not update password. Please try again.'],
            ]);

            $this->flash('error', 'Failed to update password. Please try again.');

            return $this->redirect('/dashboard/profile');
        }

        // regenerate session ID after credential change to prevent session fixation
        session_regenerate_id(true);

        $this->flash('success', 'Password updated successfully.');

        return $this->redirect('/dashboard/profile');
    }

    /**
     * Delete avatar file from storage.
     *
     * construct the file path from the user ID and attempt deletion.
     * Failures are logged but don't throw exceptions to avoid breaking the flow.
     *
     * @param  int  $userId  User ID
     * @param  string  $avatarUrl  Avatar URL to delete
     */
    private function deleteAvatarFile(int $userId, string $avatarUrl): void
    {
        try {
            // construct the file path from upload directory
            [$dir, $url] = $this->uploader->userProfilePath($userId);

            // extract filename from URL and build full path
            $filename = basename(parse_url($avatarUrl, PHP_URL_PATH));
            $filePath = $dir.'/'.$filename;

            // delete the file if it exists
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (Exception $e) {
            // log but don't fail - database update is more important
            error_log("Failed to delete avatar file for user {$userId}: ".$e->getMessage());
        }
    }

    private function computeDisplay(string $pref, string $first, string $last, string $username): string
    {
        if ($pref === 'name') {
            $full = trim($first.' '.$last);

            return $full !== '' ? $full : $username;
        }

        return $username;
    }

    /**
     * Load and merge all profile-related data for a user.
     *
     * fetch from multiple tables and transform into a flat structure
     * that's easy to consume in templates and forms.
     *
     * @return array<string, mixed>
     */
    private function loadProfileData(int $userId): array
    {
        // fetch base user record
        $user = $this->users->findById($userId);

        if (!$user) {
            throw new Exception("User record not found for ID {$userId}");
        }

        // ensure profile and preferences exist (creates with defaults if missing)
        $profile = $this->profiles->findOrCreate($userId);
        $preferences = $this->prefs->findOrCreate($userId);
        $links = $this->socials->listByUser($userId);

        // merge all data sources into a single array
        $merged = array_merge(
            $user,
            $profile ?: [],
            $preferences ?: [],
            $this->linksToFlatInputs($links)
        );

        // add computed/transformed fields
        return $this->enrichProfileData($merged, $userId);
    }

    /**
     * Add computed fields and transform data for template consumption.
     *
     * handle defaults, format dates, and compute activity stats.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enrichProfileData(array $data, int $userId): array
    {
        // set sensible defaults for template consumption
        $enriched = $data;
        $enriched['avatar'] = $data['avatar_url'] ?? null;
        $enriched['display_name'] = $data['display_name_preference'] ?? 'username';
        $enriched['default_visibility'] = $data['default_post_visibility'] ?? 'public';
        $enriched['timezone'] = $data['timezone'] ?? 'UTC';

        // default notification preferences to enabled
        $enriched['notify_comments'] = isset($data['notify_comments'])
            ? (int) $data['notify_comments']
            : 1;
        $enriched['notify_likes'] = isset($data['notify_likes'])
            ? (int) $data['notify_likes']
            : 1;

        // use denormalized counts if available, otherwise compute them
        $enriched['post_count'] = $data['posts_count']
            ?? $this->users->countPosts($userId);
        $enriched['comment_count'] = $data['comments_received_count']
            ?? $this->users->countCommentsReceived($userId);

        // format last login time in user's timezone
        $enriched['last_login'] = $this->formatLastLogin(
            $data['last_login'] ?? null,
            $enriched['timezone']
        );

        return $enriched;
    }

    /**
     * Format last login timestamp in user's timezone.
     *
     * store all timestamps in UTC but display in user's preferred timezone.
     * Invalid timezones fall back to UTC silently to prevent crashes.
     *
     * @return string|null Formatted datetime or null if never logged in
     */
    private function formatLastLogin(?string $lastLogin, string $timezone): ?string
    {
        if (!$lastLogin) {
            return null;
        }

        try {
            // parse the UTC timestamp
            $date = new DateTime($lastLogin, new DateTimeZone('UTC'));

            // convert to user's timezone if valid
            if ($this->isValidTimezone($timezone)) {
                $date->setTimezone(new DateTimeZone($timezone));
            } else {
                // log invalid timezone but don't crash
                error_log("Invalid timezone '{$timezone}' for user, using UTC");
            }

            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            // log datetime parsing errors but don't crash the page
            error_log("Failed to parse last_login '{$lastLogin}': ".$e->getMessage());

            return null;
        }
    }

    /**
     * Validate timezone identifier.
     *
     * check against PHP's list of valid timezones to prevent crashes.
     */
    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Get timezones grouped by region for select dropdown.
     *
     * group timezones like "America/New_York" by their region prefix
     * to create organized optgroups in the UI.
     *
     * TODO: Cache this result as it's expensive and static.
     *
     * @return array<string, string[]>
     */
    private function getGroupedTimezones(): array
    {
        $zones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $grouped = [];

        foreach ($zones as $zone) {
            // split "America/New_York" into ["America", "New_York"]
            $parts = explode('/', $zone, 2);
            $region = $parts[0];

            // skip deprecated/unusual zones without region prefix
            if (count($parts) === 1) {
                $grouped['Other'][] = $zone;
                continue;
            }

            $grouped[$region][] = $zone;
        }

        return $grouped;
    }
}
