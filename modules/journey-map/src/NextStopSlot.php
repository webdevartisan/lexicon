<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Models\PublishedPostModel;
use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;

/**
 * "Next stop: X" under a post, only when the journey goes somewhere other than the next post by date.
 * Every post page already links the next post by date, so repeating it would only say the same thing twice.
 */
final class NextStopSlot implements SlotRenderer
{
    /** @var array<int, array<string, mixed>|null> Post id => the stop after it worth linking, read once per page */
    private array $next = [];

    public function __construct(private Route $route, private PublishedPostModel $posts)
    {
    }

    /**
     * Only when another public stop follows this post and it is not the next post by date.
     */
    public function shouldRender(SlotContext $context): bool
    {
        return $this->nextOf($context) !== null;
    }

    /**
     * The link to the following stop.
     */
    public function render(SlotContext $context): string
    {
        $next = (array) $this->nextOf($context);
        $blogSlug = (string) ($context->blog['blog_slug'] ?? '');

        return Partial::render('next-stop', [
            't' => Partial::translator(),
            'title' => (string) $next['title'],
            'url' => lurl('/blog/'.rawurlencode($blogSlug).'/'.rawurlencode((string) $next['slug'])),
        ]);
    }

    /**
     * The reader styles.
     */
    public function assets(): array
    {
        return ['css' => ['map.css'], 'js' => []];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nextOf(SlotContext $context): ?array
    {
        $postId = (int) ($context->post['id'] ?? 0);
        if ($postId === 0 || $context->blogId === null) {
            return null;
        }

        if (!array_key_exists($postId, $this->next)) {
            $this->next[$postId] = $this->worthLinking($context, $postId);
        }

        return $this->next[$postId];
    }

    /**
     * The next stop, unless the page already links it as the next post by date.
     *
     * @return array<string, mixed>|null
     */
    private function worthLinking(SlotContext $context, int $postId): ?array
    {
        $stop = $this->route->legOf((int) $context->blogId, $postId)?->next;
        $publishedAt = (string) ($context->post['published_at_raw'] ?? '');
        if ($stop === null || $publishedAt === '') {
            return $stop;
        }

        $byDate = $this->posts->findNextByBlogIdAndDate((int) $context->blogId, $publishedAt);

        return (int) ($byDate['id'] ?? 0) === (int) $stop['post_id'] ? null : $stop;
    }
}
