<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlogModel;
use App\Models\BlogSettingsModel;
use App\Models\PostModel;
use App\Models\UserPreferencesModel;

/**
 * Orchestrates complete blog deletion with cascading dependencies.
 *
 * Handles multi-table deletion, file cleanup, and preference reassignment
 * with transaction safety. Follows the same pattern as UserDeletionService.
 */
final class BlogDeletionService
{
    public function __construct(
        private BlogModel $blogs,
        private PostModel $posts,
        private BlogSettingsModel $settings,
        private UserPreferencesModel $preferences,
        private UploadService $uploader
    ) {}

    /**
     * Delete blog with all associated data and files.
     *
     * Cascading deletion order:
     * 1. Physical files (branding, post uploads) - outside transaction
     * 2. Database records (comments → post_tags → posts → collaborators → settings → blog)
     * 3. Default preference reassignment (if needed)
     *
     * @param  int  $blogId  Blog ID to delete
     * @param  int  $userId  Owner user ID
     * @param  int  $ownerId  Blog owner ID (may differ from deleting user if admin)
     * @return array{deleted_posts: int, deleted_comments: int, deleted_collaborators: int} Deletion stats
     *
     * @throws \Exception If deletion fails
     */
    public function deleteBlog(int $blogId, int $userId, int $ownerId): array
    {
        // Check if this blog is user's default BEFORE deletion
        // ON DELETE SET NULL will clear it during deletion
        $wasDefaultBlog = $this->isUserDefaultBlog($userId, $blogId);

        // Stage 1: Delete uploaded files (outside transaction)
        $this->deleteAllBlogFiles($blogId, $ownerId);

        // Stage 2: Database cascading deletion (inside transaction)
        $stats = $this->blogs->transaction(function () use ($blogId): array {
            // Delete comments first (depend on posts)
            $deletedComments = $this->posts->deleteCommentsByBlogId($blogId);

            // Delete post-tag relationships
            $deletedPostTags = $this->posts->deletePostTagsByBlogId($blogId);

            // Delete all posts
            $deletedPosts = $this->posts->deleteByBlogId($blogId);

            // Delete collaborators
            $deletedCollaborators = $this->blogs->deleteCollaboratorsByBlogId($blogId);

            // Delete settings
            $this->settings->deleteByBlogId($blogId);

            // Delete blog itself
            if (!$this->blogs->delete($blogId)) {
                throw new \Exception('Failed to delete blog record');
            }

            return [
                'deleted_posts' => $deletedPosts,
                'deleted_comments' => $deletedComments,
                'deleted_collaborators' => $deletedCollaborators,
                'deleted_post_tags' => $deletedPostTags,
            ];
        });

        // Stage 3: Reassign default blog if needed
        if ($wasDefaultBlog) {
            $this->reassignDefaultBlog($userId, $blogId);
        }

        return $stats;
    }

    /**
     * Delete all files associated with blog.
     *
     * Includes branding (banner, logo, favicon), post images, and entire blog directory.
     * File deletion failures are logged but don't block database deletion.
     *
     * @param  int  $blogId  Blog ID
     * @param  int  $ownerId  Blog owner user ID
     */
    private function deleteAllBlogFiles(int $blogId, int $ownerId): void
    {
        try {
            // Delete branding files
            $settings = $this->settings->findByBlogId($blogId);
            if ($settings) {
                foreach (['banner_path', 'logo_path', 'favicon_path'] as $fileKey) {
                    if (!empty($settings[$fileKey])) {
                        $this->deleteFile($settings[$fileKey]);
                    }
                }
            }

            // Delete post-related files
            $posts = $this->posts->getAllByBlogId($blogId);
            foreach ($posts as $post) {
                if (!empty($post['featured_image'])) {
                    $this->deleteFile($post['featured_image']);
                }

                // Extract and delete embedded images from content
                if (!empty($post['content'])) {
                    $this->deleteContentImages($post['content'], $blogId);
                }
            }

            // Delete entire blog directory
            [$blogDir] = $this->uploader->blogBrandingPath($ownerId, $blogId);
            $blogRootDir = dirname($blogDir); // Remove /branding to get /blogs/{blogId}

            if (is_dir($blogRootDir)) {
                $this->deleteDirectory($blogRootDir);
            }

        } catch (\Exception $e) {
            // Log but don't throw - file cleanup can happen later via maintenance
            error_log("File deletion failed for blog {$blogId}: ".$e->getMessage());
        }
    }

    /**
     * Extract and delete images embedded in post content.
     *
     * @param  string  $content  Post HTML content
     * @param  int  $blogId  Blog ID for path validation
     */
    private function deleteContentImages(string $content, int $blogId): void
    {
        $pattern = '#/uploads/blogs/'.$blogId.'/[^\s"\'<>]+#';
        preg_match_all($pattern, $content, $matches);

        foreach ($matches[0] as $relativePath) {
            $this->deleteFile($relativePath);
        }
    }

    /**
     * Delete single uploaded file.
     *
     * @param  string  $filePathOrUrl  Relative URL or path
     */
    private function deleteFile(string $filePathOrUrl): void
    {
        $filePath = ROOT_PATH.'/public/'.ltrim($filePathOrUrl, '/');

        if (file_exists($filePath) && is_file($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * Recursively delete directory and contents.
     *
     * @param  string  $dir  Directory path
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * Check if blog is user's default.
     *
     * Must check BEFORE deletion due to ON DELETE SET NULL constraint.
     *
     * @param  int  $userId  User ID
     * @param  int  $blogId  Blog ID
     */
    private function isUserDefaultBlog(int $userId, int $blogId): bool
    {
        return $this->preferences->isDefaultBlog($userId, $blogId);
    }

    /**
     * Reassign default blog after deletion.
     *
     * If user has remaining blogs, assign most recent as default.
     * If no blogs remain, preference already NULL from ON DELETE SET NULL.
     *
     * @param  int  $userId  User ID
     * @param  int  $deletedBlogId  Deleted blog ID
     */
    private function reassignDefaultBlog(int $userId, int $deletedBlogId): void
    {
        try {
            $remainingBlogs = $this->blogs->getBlogsByOwnerId($userId);

            if (empty($remainingBlogs)) {
                // No blogs left - preference already NULL from constraint
                return;
            }

            // Assign most recent blog as new default
            $newDefaultBlogId = (int) $remainingBlogs[0]['id'];
            $this->preferences->setDefaultBlogId($userId, $newDefaultBlogId);

        } catch (\Exception $e) {
            // Log but don't throw - preference failure shouldn't block deletion
            error_log("Default blog reassignment failed for user {$userId}: ".$e->getMessage());
        }
    }
}
