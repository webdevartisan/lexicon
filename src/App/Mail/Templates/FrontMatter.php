<?php

declare(strict_types=1);

namespace App\Mail\Templates;

/**
 * Reads the fields at the top of a shipped email or layout file:
 *
 *     ---
 *     subject: Reset your password
 *     preheader: The link works for an hour.
 *     ---
 *     <tr>...</tr>
 *
 * One "name: value" per line. Everything after the closing line is the file's
 * HTML. A file without the opening line is all HTML.
 */
final class FrontMatter
{
    /**
     * @return array{0: array<string, string>, 1: string} The fields and the HTML
     */
    public static function parse(string $file): array
    {
        $file = str_replace("\r\n", "\n", $file);

        if (!str_starts_with($file, "---\n")) {
            return [[], $file];
        }

        $end = strpos($file, "\n---\n", 3);

        if ($end === false) {
            return [[], $file];
        }

        $fields = [];

        foreach (explode("\n", substr($file, 4, $end - 4)) as $line) {
            $colon = strpos($line, ':');

            if ($colon !== false && trim(substr($line, 0, $colon)) !== '') {
                $fields[trim(substr($line, 0, $colon))] = trim(substr($line, $colon + 1));
            }
        }

        return [$fields, ltrim(substr($file, $end + 5), "\n")];
    }
}
