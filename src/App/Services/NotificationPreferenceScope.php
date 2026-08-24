<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\UserPreferencesModel;

/**
 * Decides which notification toggles a user should even see.
 *
 * A toggle is only worth showing when the user could actually receive an email
 * of that family. Reader-only accounts never get review requests or the blog
 * firehose, so those toggles are noise to them. Each family is gated on the same
 * condition that produces its email, resolved against the real permission engine
 * rather than role-name strings.
 *
 * This single source of truth is consumed by both the controller's render and
 * its save path: the save path must iterate the same set, otherwise a hidden
 * toggle (absent from the POST) would be written off and silently disable a
 * notification the user would want the day they gain the role.
 */
final class NotificationPreferenceScope
{
    public function __construct(private BlogModel $blogModel) {}

    /**
     * The NOTIFY_KEYS that apply to this user, in NOTIFY_KEYS order.
     *
     * @return string[]
     */
    public function applicableKeys(int $userId): array
    {
        $permissions = $this->blogModel->aggregateBlogPermissions($userId);

        $authors = in_array('create_posts', $permissions, true);

        $ownsBlog = $this->blogModel->userOwnsAnyBlog($userId);

        $applicable = [
            // The only universal one: everyone can comment, so everyone can get a reply.
            'notify_comment_replies' => true,
            'notify_post_status' => $authors,
            'notify_comments_authored' => $authors,
            'notify_review_requests' => in_array('review_posts', $permissions, true),
            // Moderation reaches the owner and editors, the exact audience of
            // edit_own_blog (BlogPolicy::update, which CommentAudienceResolver mirrors).
            'notify_comments_moderation' => in_array('edit_own_blog', $permissions, true),
            // The firehose is a pure ownership concern, matching CommentAudienceResolver.
            'notify_comments_blog' => $ownsBlog,
            // Role changes only reach collaborators; owners are not members of their own blog.
            'notify_role_changes' => $this->blogModel->userIsCollaborator($userId),
            // invite_declined is dispatched to the blog owner, so only owners get it.
            'notify_invites' => $ownsBlog,
        ];

        return array_values(array_filter(
            UserPreferencesModel::NOTIFY_KEYS,
            static fn (string $key): bool => !empty($applicable[$key])
        ));
    }
}
