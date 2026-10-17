<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Resources\BlogResource;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Turns a post or a blog into one "who can see this" line, worded the same in
 * the editor, the post list and blog settings, with the one action that
 * changes it.
 *
 * Text comes back as a translation key with ".lead" and ".text" parts.
 * GET action URLs are public paths (prefix base_url()), POST ones are
 * dashboard paths (pass through lurl()).
 */
final class VisibilityPresenter
{
    private const PREFIX = 'setup.visibility.';

    /**
     * The line for a post, or null for moderated and archived posts, which
     * have their own notices.
     *
     * @param  array<string, mixed>  $post  Post row with raw UTC published_at
     * @return array{tone: string, key: string, params: array<string, string>, action: array{labelKey: string, url: string, method: string}|null}|null
     */
    public static function forPost(array $post, BlogResource $blog, int $viewerId): ?array
    {
        $status = (string) ($post['status'] ?? 'draft');
        $visibility = (string) ($post['visibility'] ?? 'public');

        return match ($status) {
            'draft' => self::draftLine($blog, $viewerId),
            'pending' => self::line('scheduled', 'pending'),
            'scheduled' => self::line('scheduled', 'scheduled', ['date' => self::localDate($post)]),
            'published' => self::publishedLine($post, $visibility, $blog, $viewerId),
            default => null,
        };
    }

    /**
     * @return array{tone: string, key: string, params: array<string, string>, action: array{labelKey: string, url: string, method: string}|null}
     */
    public static function forBlog(BlogResource $blog, int $viewerId): array
    {
        return match ($blog->status()) {
            'published' => self::line('live', 'blogOnline', [], self::open('openBlog', '/blog/'.$blog->slug())),
            'archived' => self::line('offline', 'archived'),
            default => self::line('offline', 'blogOffline', [], self::goLive($blog, $viewerId)),
        };
    }

    private static function draftLine(BlogResource $blog, int $viewerId): array
    {
        if (!$blog->canPublish($viewerId)) {
            return self::line('draft', $blog->reviewsPosts() ? 'draftReview' : 'draft');
        }

        $launchesBlog = $blog->status() === 'draft' && !$blog->hasPublishedPost();

        return self::line('draft', $launchesBlog ? 'draftFirst' : 'draft');
    }

    private static function publishedLine(array $post, string $visibility, BlogResource $blog, int $viewerId): array
    {
        if ($visibility === 'private') {
            return self::line('private', 'private');
        }

        if ($blog->status() === 'archived') {
            return self::line('offline', 'archived');
        }

        if ($blog->status() !== 'published') {
            return self::line('offline', 'offlinePost', [], self::goLive($blog, $viewerId));
        }

        $open = self::open('openPost', '/blog/'.$blog->slug().'/'.$post['slug']);

        return self::line('live', $visibility === 'unlisted' ? 'unlisted' : 'live', [], $open);
    }

    private static function goLive(BlogResource $blog, int $viewerId): ?array
    {
        // Same rule as BlogPolicy::update, checked here so members never see a button that would 403
        if (!$blog->userCan($viewerId, 'edit_own_blog')) {
            return null;
        }

        return ['labelKey' => self::PREFIX.'actions.goLive', 'url' => '/dashboard/blog/'.$blog->id().'/go-live', 'method' => 'POST'];
    }

    private static function open(string $label, string $path): array
    {
        return ['labelKey' => self::PREFIX.'actions.'.$label, 'url' => $path, 'method' => 'GET'];
    }

    private static function localDate(array $post): string
    {
        $timezone = (string) ($post['timezone'] ?? '') ?: 'UTC';
        $utc = new DateTimeImmutable((string) ($post['published_at'] ?? 'now'), new DateTimeZone('UTC'));

        return $utc->setTimezone(new DateTimeZone($timezone))->format('j M Y, H:i');
    }

    private static function line(string $tone, string $key, array $params = [], ?array $action = null): array
    {
        return ['tone' => $tone, 'key' => self::PREFIX.$key, 'params' => $params, 'action' => $action];
    }
}
