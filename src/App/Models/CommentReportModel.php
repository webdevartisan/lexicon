<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Reader reports on comments, one per person per comment.
 *
 * Surfaced through the "Reported" tab in both moderation queues and cleared
 * when a moderator approves the comment.
 */
class CommentReportModel extends ReportModel
{
    protected ?string $table = 'comment_reports';

    protected function subjectColumn(): string
    {
        return 'comment_id';
    }

    protected function subjectTable(): string
    {
        return 'comments';
    }
}
