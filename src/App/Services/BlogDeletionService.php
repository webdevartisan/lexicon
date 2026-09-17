<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\UploadServiceInterface;
use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserPreferencesModel;

/**
 * Deletes a blog, everything written in it, and every file uploaded to it.
 */
final class BlogDeletionService
{
    public function __construct(
        private BlogModel $blogs,
        private PostModel $posts,
        private BlogSettingsModel $settings,
        private UserPreferencesModel $preferences,
        private UploadServiceInterface $uploader,
        private PublicCacheInvalidator $cacheInvalidator,
    ) {}

    /**
     * Delete the records first and the files after, so a failed database step
     * never leaves a live blog pointing at images that are already gone.
     *
     * @param  int  $blogId  Blog ID to delete
     * @param  int  $userId  User whose default blog may need reassigning
     * @return array{deleted_posts: int, deleted_comments: int, deleted_collaborators: int, deleted_post_tags: int}
     *
     * @throws \RuntimeException If the blog record or any of its files could not be deleted
     */
    public function deleteBlog(int $blogId, int $userId): array
    {
        $wasDefaultBlog = $this->preferences->isDefaultBlog($userId, $blogId);

        $stats = $this->blogs->transaction(function () use ($blogId): array {
            $deletedComments = $this->posts->deleteCommentsByBlogId($blogId);
            $deletedPostTags = $this->posts->deletePostTagsByBlogId($blogId);
            $deletedPosts = $this->posts->deleteByBlogId($blogId);
            $deletedCollaborators = $this->blogs->deleteCollaboratorsByBlogId($blogId);
            $this->settings->deleteByBlogId($blogId);

            if (!$this->blogs->delete($blogId)) {
                throw new \RuntimeException("Failed to delete blog record {$blogId}.");
            }

            return [
                'deleted_posts' => $deletedPosts,
                'deleted_comments' => $deletedComments,
                'deleted_collaborators' => $deletedCollaborators,
                'deleted_post_tags' => $deletedPostTags,
            ];
        });

        $this->uploader->deleteBlogUploads($blogId);

        if ($wasDefaultBlog) {
            $remainingBlogs = $this->blogs->getBlogsByOwnerId($userId);

            if ($remainingBlogs !== []) {
                $this->preferences->setDefaultBlogId($userId, (int) $remainingBlogs[0]['id']);
            }
        }

        $this->cacheInvalidator->purgeHome();
        $this->cacheInvalidator->purgeExplore();

        return $stats;
    }
}
