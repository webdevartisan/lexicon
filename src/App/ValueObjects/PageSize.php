<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Framework\Core\Request;

/**
 * How many rows an admin list shows per page, taken from `per_page` in the query string.
 *
 * Only the listed sizes are accepted. Anything else falls back to the page's own
 * default, so a hand-edited URL cannot ask the database for ten thousand rows.
 */
final class PageSize
{
    public const CHOICES = [10, 20, 25, 50, 100];

    public static function fromRequest(Request $request, int $default): int
    {
        $raw = $request->get['per_page'] ?? '';
        $size = is_scalar($raw) ? (int) $raw : 0;

        return in_array($size, self::CHOICES, true) ? $size : $default;
    }
}
