<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * The invitation for someone without an account yet.
 *
 * Same link and data as BlogInviteMail; the copy tells them the link walks
 * through creating an account before they join.
 */
class BlogInviteNewUserMail extends BlogInviteMail {}
