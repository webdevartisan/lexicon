<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;

/**
 * "Next stop: X" under a post that is a stop, linking the following public stop of the journey.
 */
final class NextStopSlot implements SlotRenderer
{
    /** @var array<int, array<string, mixed>|null> Post id => the stop after it, read once per page */
    private array $next = [];

    public function __construct(private StopModel $stops)
    {
    }

    /**
     * Only when another public stop follows this post.
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
            $this->next[$postId] = $this->stops->nextAfter($context->blogId, $postId);
        }

        return $this->next[$postId];
    }
}
