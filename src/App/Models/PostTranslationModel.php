<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Per-locale overlays of post content for blogs with localized posts enabled.
 *
 * The base posts row holds the default-language content. A translation row
 * carries only the readable fields (title, content, excerpt); anything else
 * (slug, status, taxonomy, images) is shared with the base post.
 */
class PostTranslationModel extends AppModel
{
    protected ?string $table = 'post_translations';

    /**
     * All translations of a post, keyed by locale.
     *
     * @return array<string, array<string, mixed>>
     */
    public function findForPost(int $postId): array
    {
        $rows = $this->database->query(
            "SELECT * FROM {$this->getTable()} WHERE post_id = ?",
            [$postId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $byLocale = [];
        foreach ($rows as $row) {
            $byLocale[(string) $row['locale']] = $row;
        }

        return $byLocale;
    }

    /**
     * One translation row, or null when the locale has no translation yet.
     *
     * @return array<string, mixed>|null
     */
    public function findOne(int $postId, string $locale): ?array
    {
        $row = $this->database->query(
            "SELECT * FROM {$this->getTable()} WHERE post_id = ? AND locale = ? LIMIT 1",
            [$postId, $locale]
        )->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Create or update a post's translation for one locale.
     *
     * @param  array{title: string, content?: string, excerpt?: string}  $data
     */
    public function upsert(int $postId, string $locale, array $data): bool
    {
        $sql = "INSERT INTO {$this->getTable()} (post_id, locale, title, content, excerpt)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    content = VALUES(content),
                    excerpt = VALUES(excerpt)";

        // rowCount is 1 for insert, 2 for update, 0 for identical no-op — all fine.
        $this->database->execute($sql, [
            $postId,
            $locale,
            (string) $data['title'],
            $data['content'] ?? null,
            $data['excerpt'] ?? null,
        ]);

        return true;
    }

    /**
     * Remove one locale's translation; the public page falls back to the base post.
     */
    public function deleteOne(int $postId, string $locale): bool
    {
        return $this->database->execute(
            "DELETE FROM {$this->getTable()} WHERE post_id = ? AND locale = ? LIMIT 1",
            [$postId, $locale]
        ) > 0;
    }

    /**
     * Overlay translated fields onto a list of post rows for one locale.
     *
     * One query per list; posts without a translation pass through unchanged.
     *
     * @param  array<int, array<string, mixed>>  $posts  Post rows with at least an id
     * @return array<int, array<string, mixed>>
     */
    public function overlay(array $posts, string $locale): array
    {
        $ids = array_values(array_filter(array_map(
            static fn (array $p): int => (int) ($p['id'] ?? 0),
            $posts
        )));

        if ($ids === []) {
            return $posts;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->database->query(
            "SELECT post_id, title, content, excerpt FROM {$this->getTable()}
             WHERE locale = ? AND post_id IN ({$placeholders})",
            array_merge([$locale], $ids)
        )->fetchAll(\PDO::FETCH_ASSOC);

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['post_id']] = $row;
        }

        foreach ($posts as &$post) {
            $t = $byId[(int) ($post['id'] ?? 0)] ?? null;
            if ($t === null) {
                continue;
            }

            $post['title'] = $t['title'] !== '' ? $t['title'] : ($post['title'] ?? '');
            if (!empty($t['content'])) {
                $post['content'] = $t['content'];
            }
            if (!empty($t['excerpt'])) {
                $post['excerpt'] = $t['excerpt'];
            }
        }
        unset($post);

        return $posts;
    }
}
