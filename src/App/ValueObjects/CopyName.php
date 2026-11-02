<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * Names for a copy of a post: "Title (copy)", then "Title (copy 2)", and an
 * address ending in "-copy" that PostModel::availableSlug() numbers when taken.
 *
 * Copying a copy starts from the original name, so it reads "Title (copy 2)"
 * rather than "Title (copy) (copy)".
 */
final class CopyName
{
    private const TITLE_MARK = '/\s\(copy(?:\s\d+)?\)$/u';

    private const SLUG_MARK = '/-copy(?:-\d+)?$/';

    /**
     * The title without a copy mark at its end.
     *
     * @param  string  $title  Title of the post being copied
     */
    public static function titleBase(string $title): string
    {
        return (string) preg_replace(self::TITLE_MARK, '', trim($title));
    }

    /**
     * The first copy title the blog does not use yet.
     *
     * @param  string  $title  Title of the post being copied
     * @param  string[]  $taken  Titles already in the blog that start with the same base
     * @param  int  $max  Longest title the editor accepts
     */
    public static function title(string $title, array $taken, int $max = 100): string
    {
        $base = self::titleBase($title);
        $taken = array_flip(array_map('mb_strtolower', $taken));

        for ($n = 1; ; $n++) {
            $suffix = $n === 1 ? ' (copy)' : ' (copy '.$n.')';
            $candidate = rtrim(mb_substr($base, 0, $max - mb_strlen($suffix))).$suffix;
            if (!isset($taken[mb_strtolower($candidate)])) {
                return $candidate;
            }
        }
    }

    /**
     * The address to ask for, before checking whether the blog already uses it.
     *
     * @param  string  $slug  Address of the post being copied
     * @param  int  $max  Longest address the editor accepts
     */
    public static function slug(string $slug, int $max = PostFieldRules::SLUG_MAX): string
    {
        $base = (string) preg_replace(self::SLUG_MARK, '', $slug);

        return rtrim(substr($base, 0, $max - 5), '-').'-copy';
    }
}
