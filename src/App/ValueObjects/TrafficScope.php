<?php

declare(strict_types=1);

namespace App\ValueObjects;

/**
 * What a set of traffic numbers counts: the whole site, the platform's own
 * pages, one blog, one post, or an author's posts added together.
 */
final class TrafficScope
{
    public const SITE = 'site';

    public const PLATFORM = 'platform';

    public const BLOG = 'blog';

    public const POST = 'post';

    /** Breakdowns every scope keeps. */
    private const COMMON_BREAKDOWNS = [
        'channel', 'source', 'utm_source', 'utm_medium', 'utm_campaign', 'device', 'browser', 'os', 'country', 'locale',
    ];

    /**
     * @param  list<int>  $ids  The scope_id values to add up: 0 for site and platform, a blog id, or post ids
     * @param  int|null  $blogId  The blog a blog or post scope belongs to
     */
    private function __construct(
        public readonly string $type,
        public readonly array $ids,
        public readonly ?int $blogId,
        private readonly string $key,
        private readonly bool $onePost = false,
    ) {}

    public static function site(): self
    {
        return new self(self::SITE, [0], null, 'site');
    }

    public static function platform(): self
    {
        return new self(self::PLATFORM, [0], null, 'platform');
    }

    public static function blog(int $blogId): self
    {
        return new self(self::BLOG, [$blogId], $blogId, "blog:{$blogId}");
    }

    public static function post(int $blogId, int $postId): self
    {
        return new self(self::POST, [$postId], $blogId, "post:{$postId}", true);
    }

    /**
     * @param  list<int>  $postIds  The author's posts in the blog; empty means nothing to count
     */
    public static function authorPosts(int $blogId, int $userId, array $postIds): self
    {
        return new self(self::POST, $postIds, $blogId, "blog:{$blogId}:author:{$userId}");
    }

    /**
     * Names the scope in cache keys. Never the viewer.
     */
    public function key(): string
    {
        return $this->key;
    }

    /**
     * Whether the scope holds posts to rank: the whole site, a blog, or an author's posts.
     */
    public function ranksPosts(): bool
    {
        return match ($this->type) {
            self::SITE, self::BLOG => true,
            self::POST => !$this->onePost,
            default => false,
        };
    }

    /**
     * The breakdown lists this scope shows.
     *
     * @return list<string>
     */
    public function breakdowns(): array
    {
        return self::breakdownsFor($this->type);
    }

    /**
     * @return list<string>
     */
    public static function breakdownsFor(string $type): array
    {
        // The site has no "elsewhere on Lexicon": every part of it is the site.
        return match ($type) {
            self::PLATFORM => [...self::COMMON_BREAKDOWNS, 'page', 'lexicon', 'entry', 'exit', 'hour'],
            self::BLOG => [...self::COMMON_BREAKDOWNS, 'page', 'lexicon', 'entry', 'exit', 'hour', 'category', 'tag', 'author'],
            self::POST => [...self::COMMON_BREAKDOWNS, 'lexicon', 'next', 'hour'],
            self::SITE => [...self::COMMON_BREAKDOWNS, 'hour'],
            default => throw new \InvalidArgumentException("Unknown traffic scope '{$type}'."),
        };
    }

    /**
     * Breakdowns shown as a bar list. The rest have their own widgets.
     *
     * @return list<string>
     */
    public function listBreakdowns(): array
    {
        return array_values(array_diff($this->breakdowns(), ['hour']));
    }
}
