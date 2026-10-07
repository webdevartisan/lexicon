<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells a post's author a reader commented on it, while the comment waits
 * for a moderator's approval.
 */
class PostCommentPendingMail extends CommentMail {}
