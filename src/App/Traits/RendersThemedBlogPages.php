<?php

declare(strict_types=1);

namespace App\Traits;

use App\Services\BlogPageContext;
use App\Services\HeadI18nBuilder;

/**
 * Starts a page that renders inside a blog's theme, for blog pages and module pages alike.
 *
 * The context is resolved through app() by default, so a module controller gets it without
 * changing its constructor.
 */
trait RendersThemedBlogPages
{
    /**
     * The blog, owner, settings, meta, viewer, languages, wording and flash messages a themed page renders with.
     *
     * @return array<string, mixed>
     *
     * @throws \Framework\Exceptions\PageNotFoundException When the blog cannot be shown to this viewer
     */
    protected function themedBlogPage(string $blogSlug): array
    {
        $page = $this->blogPageContext()->forSlug($blogSlug);

        // Every themed page renders these, so the subscribe confirmation (and any
        // other flash) shows up no matter which blog page the visitor lands back on.
        $page['flashes'] = $this->getFlashMessages();
        $this->refreshHeadI18n();

        return $page;
    }

    /**
     * Where the page context comes from. A controller that has one injected returns it instead.
     */
    protected function blogPageContext(): BlogPageContext
    {
        return app(BlogPageContext::class);
    }

    /**
     * Rebuild the language links in the head once the page's languages are known.
     *
     * HeadI18nGlobals runs before the controller, so it could only assume every
     * supported locale. Template globals are merged at render time, so this later
     * call is what the page actually renders with.
     */
    protected function refreshHeadI18n(): void
    {
        $full = $this->request->uri ?? '/';

        $this->viewer->addGlobals(app(HeadI18nBuilder::class)->build(
            parse_url($full, PHP_URL_PATH) ?: '/',
            $this->request->get !== [] ? http_build_query($this->request->get) : null
        ));
    }
}
