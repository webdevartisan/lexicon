<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Controllers\ModuleController;
use App\Models\BlogModuleModel;
use App\Services\BlogPageContext;
use Framework\Core\Response;

/**
 * The Journey page: every public stop of a blog on one map, with a plain list under it.
 */
final class JourneyController extends ModuleController
{
    private const SUMMARY_LENGTH = 180;

    public function __construct(
        BlogModuleModel $blogModules,
        private StopModel $stops,
        private MapData $map,
        private BlogPageContext $pageContext,
    ) {
        parent::__construct($blogModules);
    }

    /**
     * Show the journey of one blog inside the blog's theme.
     *
     * @throws \Framework\Exceptions\PageNotFoundException When the blog is hidden or the module is off for it
     */
    public function show(string $blogSlug): Response
    {
        $page = $this->themedBlogPage($blogSlug);
        $this->requireActive((int) $page['blog']['id']);

        $hasMap = $this->map->available();
        $page['meta'] = $this->meta($page['meta'], (string) $page['blog']['blog_slug']);

        return $this->view('journey-map::journey', $page + [
            'stops' => $this->forReaders($this->stops->published((int) $page['blog']['id']), $blogSlug),
            'map' => $hasMap ? $this->map->urls() : null,
            'scripts' => $this->scripts($hasMap),
        ]);
    }

    /**
     * The module whose switch decides if this page exists on a blog.
     */
    protected function moduleKey(): string
    {
        return 'journey-map';
    }

    /**
     * The injected context, so tests can build it on their own database.
     */
    protected function blogPageContext(): BlogPageContext
    {
        return $this->pageContext;
    }

    /**
     * The blog's meta with this page's own title and address.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function meta(array $meta, string $blogSlug): array
    {
        $url = rtrim(base_url(), '/').lurl('/blog/'.rawurlencode($blogSlug).'/m/journey-map');

        return [
            'title' => chrome_translate('journey-map::page.title').' · '.$meta['site_name'],
            'url' => $url,
            'canonical' => $url,
        ] + $meta;
    }

    /**
     * Each stop with its post address, a cover only when it is a real image path, and a short plain summary.
     *
     * @param list<array<string, mixed>> $stops
     * @return list<array<string, mixed>>
     */
    private function forReaders(array $stops, string $blogSlug): array
    {
        $base = '/blog/'.rawurlencode($blogSlug).'/';

        return array_map(fn (array $stop): array => [
            'post_id' => (int) $stop['post_id'],
            'title' => (string) $stop['title'],
            'url' => lurl($base.rawurlencode((string) $stop['slug'])),
            'cover' => $this->cover($stop['featured_image'] ?? null),
            'summary' => $this->summary((string) ($stop['excerpt'] ?? '')),
            'place' => (string) $stop['place_name'],
            'lat' => (float) $stop['lat'],
            'lng' => (float) $stop['lng'],
        ], $stops);
    }

    /**
     * Some older posts store a category label in featured_image, so only real paths count as covers.
     */
    private function cover(mixed $path): ?string
    {
        $path = trim((string) $path);

        return preg_match('#^(https?://|/)#i', $path) === 1 ? $path : null;
    }

    /**
     * The excerpt as one line of plain text, cut to fit a list row and a map card.
     */
    private function summary(string $excerpt): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($excerpt)));

        return mb_strimwidth($text, 0, self::SUMMARY_LENGTH, '…');
    }

    /**
     * Full addresses of the map scripts, or none without a map. The theme's head.end slot
     * already links the module's stylesheets, but scripts only follow slots that rendered,
     * so a module page links its own.
     *
     * @return list<string>
     */
    private function scripts(bool $hasMap): array
    {
        if (!$hasMap) {
            return [];
        }

        $files = [...$this->map->assets()['js'], 'map.js'];

        return array_map(static fn (string $file): string => versioned_asset('/modules/journey-map/'.$file), $files);
    }
}
