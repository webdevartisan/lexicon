<?php

declare(strict_types=1);

namespace App\Services;

use Framework\Database;

/**
 * Answers "where is this image used?" for a blog on demand.
 *
 * Usage is computed by querying the places that can reference an image rather
 * than kept in a join table: the query is always accurate and there is nothing
 * to keep in sync when a post or a setting changes. Used by the media editor to
 * show attachment context and to warn before deleting something still in use.
 */
final class MediaUsageResolver
{
    public function __construct(private readonly Database $database) {}

    /**
     * @return list<array{type: string, label: string, context: string, post_id?: int}>
     */
    public function usages(int $blogId, string $url): array
    {
        return array_merge(
            $this->postUsages($blogId, $url),
            $this->brandingUsages($blogId, $url),
        );
    }

    /**
     * Posts referencing the image as a featured/social image (exact match) or
     * embedded in the body (substring match on the stored URL).
     *
     * @return list<array{type: string, label: string, context: string, post_id: int}>
     */
    private function postUsages(int $blogId, string $url): array
    {
        $like = '%'.$url.'%';
        $sql = 'SELECT id, title, status,
                    (featured_image = ?) AS as_featured,
                    (og_image = ?) AS as_og,
                    (twitter_image = ?) AS as_twitter,
                    (content LIKE ?) AS as_inline
                FROM posts
                WHERE blog_id = ?
                  AND (featured_image = ? OR og_image = ? OR twitter_image = ? OR content LIKE ?)
                ORDER BY id DESC';

        $params = [$url, $url, $url, $like, $blogId, $url, $url, $url, $like];
        $rows = $this->database->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $roles = [];
            if ($row['as_featured']) {
                $roles[] = 'featured image';
            }
            if ($row['as_inline']) {
                $roles[] = 'in the body';
            }
            if ($row['as_og'] || $row['as_twitter']) {
                $roles[] = 'social preview';
            }

            $out[] = [
                'type' => 'post',
                'label' => $row['title'] !== '' ? $row['title'] : 'Untitled post',
                'context' => ucfirst($row['status']).' · '.implode(', ', $roles),
                'post_id' => (int) $row['id'],
                'link' => lurl('/dashboard/post/'.(int) $row['id'].'/edit'),
            ];
        }

        return $out;
    }

    /**
     * Blog branding slots pointing at the image.
     *
     * @return list<array{type: string, label: string, context: string}>
     */
    private function brandingUsages(int $blogId, string $url): array
    {
        $sql = 'SELECT (banner_path = ?) AS as_banner,
                       (logo_path = ?) AS as_logo,
                       (favicon_path = ?) AS as_favicon
                FROM blog_settings WHERE blog_id = ? LIMIT 1';

        $row = $this->database->query($sql, [$url, $url, $url, $blogId])->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return [];
        }

        $slots = ['as_banner' => 'Banner', 'as_logo' => 'Logo', 'as_favicon' => 'Favicon'];
        $link = lurl('/dashboard/blog/'.$blogId.'/appearance').'#branding';
        $out = [];
        foreach ($slots as $col => $label) {
            if (!empty($row[$col])) {
                $out[] = ['type' => 'branding', 'label' => $label, 'context' => 'Blog appearance', 'link' => $link];
            }
        }

        return $out;
    }
}
