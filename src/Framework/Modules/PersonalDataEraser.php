<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * A module that stores anything about a person must remove it when that person's account is erased.
 */
interface PersonalDataEraser
{
    /**
     * Remove or anonymize every row the module holds about this account.
     */
    public function eraseUser(int $userId): void;
}
