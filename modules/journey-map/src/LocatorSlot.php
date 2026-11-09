<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;

/**
 * A short map after a post showing its leg of the journey, with a line naming its places and the distance travelled.
 */
final class LocatorSlot implements SlotRenderer
{
    private const EXACT_ZOOM = 9;

    /** A step further out, so an approximate place never looks like an exact spot. */
    private const AREA_ZOOM = 8;

    /** Neighbours further than this are left out of the frame, so a long flight never shrinks the map to a dot. */
    private const FRAME_KM = 300.0;

    /** @var array<int, Leg|null> Post id => its leg, read once per page */
    private array $found = [];

    public function __construct(private Route $route, private MapData $map) {}

    /**
     * Only on posts that have a place.
     */
    public function shouldRender(SlotContext $context): bool
    {
        return $this->legOf($context) !== null;
    }

    /**
     * The place line, with a small map of the leg when the map data is installed.
     */
    public function render(SlotContext $context): string
    {
        $leg = $this->legOf($context);
        $t = Partial::translator();
        $blogSlug = (string) ($context->blog['blog_slug'] ?? '');

        return Partial::render('locator', [
            't' => $t,
            'place' => LegCaption::places($leg),
            'caption' => LegCaption::text($leg, $t),
            'map' => $this->map->available() ? $this->map->urls() : null,
            'view' => $this->view($leg, $blogSlug),
        ]);
    }

    /**
     * Map files when the map data is installed, otherwise just the reader styles.
     */
    public function assets(): array
    {
        if (!$this->map->available()) {
            return ['css' => ['map.css'], 'js' => []];
        }

        $map = $this->map->assets();

        return ['css' => [...$map['css'], 'map.css'], 'js' => [...$map['js'], 'map.js']];
    }

    /**
     * Where the map looks: the post's own places, each with the road into it, plus the neighbours near enough
     * to frame with them.
     *
     * @return array<string, mixed>
     */
    private function view(Leg $leg, string $blogSlug): array
    {
        $stops = [];
        $exact = true;
        foreach ($leg->stops as $index => $stop) {
            $stops[] = [
                'lat' => (float) $stop['lat'],
                'lng' => (float) $stop['lng'],
                'place' => (string) $stop['place_name'],
                'lineIn' => $leg->lineInto($index),
            ];
            $exact = $exact && $stop['place_precision'] !== 'area';
        }

        return [
            'zoom' => $exact ? self::EXACT_ZOOM : self::AREA_ZOOM,
            'stops' => $stops,
            'previous' => $this->neighbour($leg->previous, $leg->first(), $blogSlug),
            'next' => $this->neighbour($leg->next, $leg->last(), $blogSlug, $leg->lineOut()),
        ];
    }

    /**
     * A neighbouring place as the map needs it, marked when it is too far away to frame.
     *
     * @param  array<string, mixed>|null  $stop
     * @param  array<string, mixed>  $nearest  The post's own place next to it
     * @param  list<array{0: float, 1: float}>|null  $lineIn  The road from the post's last place to this one
     * @return array<string, mixed>|null
     */
    private function neighbour(?array $stop, array $nearest, string $blogSlug, ?array $lineIn = null): ?array
    {
        if ($stop === null) {
            return null;
        }

        return [
            'lat' => (float) $stop['lat'],
            'lng' => (float) $stop['lng'],
            'title' => (string) $stop['title'],
            'place' => (string) $stop['place_name'],
            'url' => lurl('/blog/'.rawurlencode($blogSlug).'/'.rawurlencode((string) $stop['slug'])),
            'framed' => Distance::between($nearest, $stop) <= self::FRAME_KM,
            'lineIn' => $lineIn,
        ];
    }

    /**
     * The post's leg, or null when the post has no place.
     */
    private function legOf(SlotContext $context): ?Leg
    {
        $postId = (int) ($context->post['id'] ?? 0);
        if ($postId === 0 || $context->blogId === null) {
            return null;
        }

        if (!array_key_exists($postId, $this->found)) {
            $this->found[$postId] = $this->route->legOf($context->blogId, $postId);
        }

        return $this->found[$postId];
    }
}
