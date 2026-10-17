<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The email and password were right, but the account was deleted and is only waiting out its grace period.
 */
class AccountDeletedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This account has been deleted.');
    }
}
