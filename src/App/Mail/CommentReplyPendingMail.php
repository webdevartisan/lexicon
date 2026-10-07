<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Tells someone a reader replied to a comment they wrote, while the reply
 * waits for a moderator's approval.
 */
class CommentReplyPendingMail extends CommentMail {}
