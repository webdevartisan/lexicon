<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use App\Controllers\ModuleController;
use App\Models\BlogModuleModel;
use App\Services\BlogPageContext;
use Framework\Core\Response;

/**
 * The Journey page: every public place of a blog on one map, with a plain list under it. A post with
 * several places shows as several stops.
 */
final class JourneyController extends ModuleController
{
    private const SUMMARY_LENGTH = 180;

    public function __construct(
        BlogModuleModel $blogModules,
        private Route $route,
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
            'stops' => $this->forReaders((int) $page['blog']['id'], $blogSlug),
            'total' => $this->total((int) $page['blog']['id']),
            'map' => $hasMap ? $this->map->urls() : null,
            'scripts' => $this->scripts($hasMap),
        ]);
    }

    /**
     * The whole journey's length, added up the way each post page counts its part: by road where the road is
     * known, otherwise in a straight line. It is approximate once any step is straight or any place an area.
     *
     * @return array{km: float, approximate: bool}
     */
    private function total(int $blogId): array
    {
        $journey = $this->route->journey($blogId);
        $roads = $this->route->roads($blogId);
        $km = 0.0;
        $approximate = false;
        foreach ($journey as $index => $stop) {
            $approximate = $approximate || $stop['place_precision'] === 'area';
            $previous = $journey[$index - 1] ?? null;
            $key = $previous === null ? null : Leg::key($previous, $stop);
            if ($key === null) {
                continue;
            }

            $road = $roads[$key] ?? null;
            $straight = Leg::points($road) === null;
            $approximate = $approximate || $straight;
            $km += $straight ? Distance::between($previous, $stop) : (float) $road['km'];
        }

        return ['km' => $km, 'approximate' => $approximate];
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
     * @param  array<string, mixed>  $meta
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
     * Each stop with its post address, a cover only when it is a real image path, a short plain summary,
     * and the road from the stop before it when one was planned.
     *
     * @return list<array<string, mixed>>
     */
    private function forReaders(int $blogId, string $blogSlug): array
    {
        $base = '/blog/'.rawurlencode($blogSlug).'/';
        $roads = $this->route->roads($blogId);
        $previous = null;
        $stops = [];
        foreach ($this->route->journey($blogId) as $stop) {
            $key = $previous === null ? null : Leg::key($previous, $stop);
            $stops[] = $this->forReader($stop, $base) + ['lineIn' => Leg::points($roads[$key ?? ''] ?? null)];
            $previous = $stop;
        }

        return $stops;
    }

    /**
     * One stop as the page and its map show it.
     *
     * @param  array<string, mixed>  $stop
     * @return array<string, mixed>
     */
    private function forReader(array $stop, string $base): array
    {
        return [
            'post_id' => (int) $stop['post_id'],
            'title' => (string) $stop['title'],
            'url' => lurl($base.rawurlencode((string) $stop['slug'])),
            'cover' => $this->cover($stop['featured_image'] ?? null),
            'summary' => $this->summary((string) ($stop['excerpt'] ?? '')),
            'place' => (string) $stop['place_name'],
            'lat' => (float) $stop['lat'],
            'lng' => (float) $stop['lng'],
        ];
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

        $files = [...$this->map->assets()['js'], 'map.js', 'replay.js'];

        return array_map(static fn (string $file): string => versioned_asset('/modules/journey-map/'.$file), $files);
    }
}
