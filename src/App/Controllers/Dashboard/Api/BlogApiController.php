<?php

namespace App\Controllers\Dashboard\Api;

use App\Controllers\AppController;
use App\Models\BlogModel;
use App\Models\CommentModel;
use App\Models\PostModel;
use Framework\Core\Response;

/**
 * BlogApiController
 *
 * We handle AJAX/API requests for blog-related operations.
 * This controller returns JSON responses only and extends AppController
 * to inherit authentication, validation, and JSON response helpers.
 */
class BlogApiController extends AppController
{
    /**
     * Constructor - We inject required models.
     */
    public function __construct(
        private BlogModel $blogModel,
        private PostModel $postModel,
        private CommentModel $commentModel) {}

    /**
     * Get blog deletion impact statistics.
     *
     * We calculate what will be deleted if the user proceeds:
     * - Total posts in this blog
     * - Total comments across all posts
     * - Total collaborators with access
     *
     * This endpoint is used by the delete confirmation modal to show
     * users the full impact before they permanently delete a blog.
     *
     * @param  int  $id  Blog ID
     * @return Response JSON response with stats or error
     */
    public function getDeletionStats(int|string $id): Response
    {
        // fetch the blog
        $blog = $this->blogModel->getBlog($id);

        if (!$blog) {
            return $this->jsonError('Blog not found', 404);
        }

        $blog = $blog->toArray();

        // verify ownership - only blog owner can view deletion stats
        $currentUser = auth()->user();
        if ((int) $blog['owner_id'] !== (int) $currentUser['id']) {
            return $this->jsonError('You do not have permission to access this blog', 403);
        }

        // calculate deletion impact
        $stats = [
            'postCount' => $this->postModel->countByBlogId($id),
            'commentCount' => $this->commentModel->countByBlogId($id),
            'collaboratorCount' => $this->blogModel->countCollaborators($id),
        ];

        return $this->jsonSuccess($stats);
    }
}
