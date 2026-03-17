<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Username utility functions for validation and normalization.
 */
class UsernameHelper
{
    /**
     * Normalize username for spam detection and comparison.
     *
     * Removes common separators spammers use to bypass reserved word checks.
     *
     * @param  string  $username  Username to normalize
     * @return string Normalized username
     */
    public static function normalize(string $username): string
    {
        $normalized = strtolower($username);

        return str_replace(['-', '_', '.', ' '], '', $normalized);
    }
}
