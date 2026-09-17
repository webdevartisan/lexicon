<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The email and password were right, but an administrator has suspended the account.
 */
class AccountSuspendedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This account is suspended.');
    }
}
