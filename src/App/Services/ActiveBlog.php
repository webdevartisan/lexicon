<?php

declare(strict_types=1);

namespace App\Services;

use App\Gate;
use App\Models\UserPreferencesModel;
use App\Resources\BlogResource;

/**
 * The blog a user is working in: any blog they may view, owned or shared.
 *
 * Stored as user_preferences.default_blog_id. Pages that name a blog in their
 * URL make it the active one, so the sidebar and the page never disagree.
 */
final class ActiveBlog
{
    public function __construct(private UserPreferencesModel $preferences) {}

    /**
     * Make $blog the active blog when the user may view it. Writes only when it changes.
     *
     * @param  array<string, mixed>  $user  Authenticated user record
     * @return bool Whether $blog is the active blog now
     */
    public function follow(array $user, BlogResource $blog, ?int $currentId = null): bool
    {
        if (!Gate::allows('view', $blog, $user)) {
            return false;
        }

        $userId = (int) $user['id'];
        $currentId ??= $this->preferences->getDefaultBlogId($userId);
        if ($currentId !== $blog->id()) {
            $this->preferences->setDefaultBlogId($userId, $blog->id());
        }

        return true;
    }
}
