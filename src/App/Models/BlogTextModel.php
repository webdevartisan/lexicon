<?php

declare(strict_types=1);

namespace App\Models;

/**
 * A blog's own wording per language, one row per text.
 *
 * No row means the blog has no wording of its own for that text. A row with an
 * empty value is kept on purpose: the owner removed the text.
 */
class BlogTextModel extends AppModel
{
    protected ?string $table = 'blog_texts';

    /**
     * Every stored text of a blog in one language, keyed by text key.
     *
     * @return array<string, string>
     */
    public function forLocale(int $blogId, string $locale): array
    {
        $rows = $this->database->query(
            "SELECT text_key, value FROM {$this->getTable()} WHERE blog_id = ? AND locale = ?",
            [$blogId, $locale]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        return array_map('strval', $rows ?: []);
    }

    /**
     * Store the given texts in one language; null drops a text's row.
     *
     * @param  array<string, string|null>  $texts  Text key => value
     */
    public function saveForLocale(int $blogId, string $locale, array $texts): void
    {
        $this->database->transaction(function () use ($blogId, $locale, $texts): void {
            foreach ($texts as $key => $value) {
                if ($value === null) {
                    $this->database->execute(
                        "DELETE FROM {$this->getTable()} WHERE blog_id = ? AND locale = ? AND text_key = ?",
                        [$blogId, $locale, $key]
                    );

                    continue;
                }

                $this->database->execute(
                    "INSERT INTO {$this->getTable()} (blog_id, locale, text_key, value) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)",
                    [$blogId, $locale, $key, $value]
                );
            }
        });
    }
}
