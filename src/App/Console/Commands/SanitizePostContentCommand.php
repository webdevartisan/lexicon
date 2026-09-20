<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PostContentSanitizer;
use Framework\Database;
use Throwable;

/**
 * CLI command that runs stored post bodies through the content sanitizer.
 *
 * Saving cleans what arrives, which does nothing for what was stored before the
 * sanitizer existed. This reports what would change and only writes when asked,
 * because a post losing markup its theme relies on means the allowlist is wrong
 * and that should be read before it is applied, not after.
 *
 * Usage: php cli posts:sanitize-content [--apply] [--verbose]
 */
class SanitizePostContentCommand
{
    public function __construct(
        private Database $database,
        private PostContentSanitizer $sanitizer,
    ) {}

    /**
     * Report, or rewrite, every stored post and translation body.
     *
     * @param  array<string|int, mixed>  $arguments  Parsed CLI arguments
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function handle(array $arguments = []): int
    {
        $apply = $this->flag($arguments, 'apply');
        $verbose = $this->flag($arguments, 'verbose');

        try {
            $changed = 0;
            $scanned = 0;
            $failed = 0;

            foreach ($this->sources() as $source) {
                foreach ($this->database->query($source['select'])->fetchAll() as $row) {
                    $scanned++;
                    $original = (string) $row['content'];

                    try {
                        $clean = $this->sanitizer->clean($original);
                    } catch (Throwable $e) {
                        $failed++;
                        echo "  ! {$source['label']} {$row['ref']}: {$e->getMessage()}\n";
                        continue;
                    }

                    if ($clean === $original) {
                        continue;
                    }

                    $changed++;
                    echo "  {$source['label']} {$row['ref']}: {$row['title']}\n";
                    echo '      '.$this->describe($original, $clean)."\n";

                    if ($verbose) {
                        echo '      before: '.$this->snippet($original)."\n";
                        echo '      after:  '.$this->snippet($clean)."\n";
                    }

                    if ($apply) {
                        $this->database->query($source['update'], [':content' => $clean, ':id' => $row['id']]);
                    }
                }
            }

            echo "\nScanned {$scanned} bodies, {$changed} would change";
            echo $apply ? ", all written.\n" : ". Re-run with --apply to write them.\n";

            if ($failed > 0) {
                echo "{$failed} body(ies) could not be read and were left alone.\n";

                return 1;
            }

            return 0;
        } catch (Throwable $e) {
            echo "✗ Error sanitizing stored content: {$e->getMessage()}\n";

            return 1;
        }
    }

    /**
     * @return array<int, array{label: string, select: string, update: string}>
     */
    private function sources(): array
    {
        return [
            [
                'label' => 'post',
                'select' => "SELECT id, id AS ref, title, content FROM posts WHERE content <> ''",
                'update' => 'UPDATE posts SET content = :content WHERE id = :id',
            ],
            [
                'label' => 'translation',
                'select' => "SELECT id, CONCAT(post_id, '/', locale) AS ref, title, content
                             FROM post_translations WHERE content <> ''",
                'update' => 'UPDATE post_translations SET content = :content WHERE id = :id',
            ],
        ];
    }

    /**
     * A one-line account of what the sanitizer took out of one body.
     */
    private function describe(string $original, string $clean): string
    {
        $parts = [];

        foreach ($this->missing($this->tags($original), $this->tags($clean)) as $tag => $count) {
            $parts[] = $count.' <'.$tag.'>';
        }

        foreach ($this->missing($this->attributes($original), $this->attributes($clean)) as $attribute => $count) {
            $parts[] = $count.' '.$attribute.'=';
        }

        if ($parts === []) {
            // Both bodies hold the same markup, so the only difference is how the
            // sanitizer writes characters like = and @ back out as entities.
            return 'character encoding only, no markup removed';
        }

        return 'removed '.implode(', ', $parts);
    }

    /**
     * @return array<string, int>
     */
    private function tags(string $html): array
    {
        preg_match_all('/<([a-zA-Z0-9]+)/', $html, $matches);

        return array_count_values(array_map('strtolower', $matches[1]));
    }

    /**
     * @return array<string, int>
     */
    private function attributes(string $html): array
    {
        // An attribute with an empty value is written back without its quotes, so
        // names are counted whether or not a value follows them.
        preg_match_all('/<[a-zA-Z][^>]*>/', $html, $tags);
        // Values are dropped first so that words inside a placeholder or an alt do
        // not read as attribute names of their own.
        $bare = preg_replace('/=\s*("[^"]*"|\'[^\']*\')/', '', implode(' ', $tags[0])) ?? '';
        preg_match_all('/\s([a-zA-Z_:][a-zA-Z0-9_:.-]*)/', $bare, $matches);

        return array_count_values(array_map('strtolower', $matches[1]));
    }

    /**
     * How many of each name the sanitizer took away.
     *
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     * @return array<string, int>
     */
    private function missing(array $before, array $after): array
    {
        $gone = [];

        foreach ($before as $name => $count) {
            $remaining = $after[$name] ?? 0;
            if ($count > $remaining) {
                $gone[$name] = $count - $remaining;
            }
        }

        return $gone;
    }

    private function snippet(string $html): string
    {
        return mb_strimwidth(preg_replace('/\s+/', ' ', $html) ?? $html, 0, 200, '…');
    }

    /**
     * @param  array<string|int, mixed>  $arguments
     */
    private function flag(array $arguments, string $name): bool
    {
        if (!isset($arguments[$name])) {
            return false;
        }

        return !in_array((string) $arguments[$name], ['0', 'false', ''], true);
    }
}
