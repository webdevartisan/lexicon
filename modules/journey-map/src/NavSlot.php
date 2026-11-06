<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;

/**
 * A "Journey" link in the blog's menu, once readers have at least one stop to see.
 */
final class NavSlot implements SlotRenderer
{
    public function __construct(private StopModel $stops)
    {
    }

    /**
     * Only once readers have a public stop to see.
     */
    public function shouldRender(SlotContext $context): bool
    {
        return $context->blogId !== null && $this->stops->hasPublished($context->blogId);
    }

    /**
     * The menu link to the Journey page.
     */
    public function render(SlotContext $context): string
    {
        $blogSlug = (string) ($context->blog['blog_slug'] ?? '');

        return Partial::render('nav', [
            't' => Partial::translator(),
            'url' => lurl('/blog/'.rawurlencode($blogSlug).'/m/journey-map'),
        ]);
    }

    /**
     * Nothing; the link takes the theme's menu style.
     */
    public function assets(): array
    {
        return ['css' => [], 'js' => []];
    }
}
