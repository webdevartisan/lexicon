<?php

declare(strict_types=1);

namespace Framework\Modules;

/**
 * What a slot renderer may know about the page it is drawn into.
 */
final class SlotContext
{
    /**
     * @param int|null $blogId The blog the page belongs to, null on pages outside any blog
     * @param array<string, mixed>|null $blog The blog row, null on pages outside any blog
     * @param array<string, mixed>|null $post The post row on post pages, null elsewhere
     * @param string $page The route action, such as showBlogPost
     * @param list<string> $renderedModules Modules that already drew something on this page
     */
    public function __construct(
        public readonly string $slot,
        public readonly ?int $blogId,
        public readonly ?array $blog,
        public readonly ?array $post,
        public readonly string $page,
        public readonly array $renderedModules = [],
    ) {
    }

    /**
     * The same page, with the modules that have drawn on it so far.
     *
     * @param list<string> $keys
     */
    public function withRenderedModules(array $keys): self
    {
        return new self($this->slot, $this->blogId, $this->blog, $this->post, $this->page, $keys);
    }
}
