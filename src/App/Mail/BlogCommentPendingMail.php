<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a blog owner a reader commented somewhere on their blog, while the
 * comment waits for a moderator's approval.
 */
class BlogCommentPendingMail extends CommentMail {}
