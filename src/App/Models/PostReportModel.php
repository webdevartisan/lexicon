<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Reader reports on posts, one per person per post.
 *
 * Surfaced as a flag on the blog's own post list; unlike a comment there is no
 * queue to approve a post out of, so the count simply stands until the blog
 * team acts on the post itself.
 */
class PostReportModel extends ReportModel
{
    protected ?string $table = 'post_reports';

    protected function subjectColumn(): string
    {
        return 'post_id';
    }

    protected function subjectTable(): string
    {
        return 'posts';
    }
}
