<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;

/**
 * A small map beside a post's header, pinned on the post's place, with the place name under it.
 */
final class LocatorSlot implements SlotRenderer
{
    private const EXACT_ZOOM = 9;

    /** A step further out, so an approximate place never looks like an exact spot. */
    private const AREA_ZOOM = 8;

    /** @var array<int, array<string, mixed>|null> Post id => its stop, read once per page */
    private array $found = [];

    public function __construct(private StopModel $stops, private MapData $map)
    {
    }

    /**
     * Only on posts that have a place.
     */
    public function shouldRender(SlotContext $context): bool
    {
        return $this->stopOf($context) !== null;
    }

    /**
     * The place name, with a small map when the map data is installed.
     */
    public function render(SlotContext $context): string
    {
        $stop = (array) $this->stopOf($context);

        return Partial::render('locator', [
            't' => Partial::translator(),
            'stop' => $stop,
            'map' => $this->map->available() ? $this->map->urls() : null,
            'view' => [
                'lat' => (float) $stop['lat'],
                'lng' => (float) $stop['lng'],
                'zoom' => $stop['place_precision'] === 'area' ? self::AREA_ZOOM : self::EXACT_ZOOM,
            ],
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
     * @return array<string, mixed>|null
     */
    private function stopOf(SlotContext $context): ?array
    {
        $postId = (int) ($context->post['id'] ?? 0);
        if ($postId === 0) {
            return null;
        }

        if (!array_key_exists($postId, $this->found)) {
            $this->found[$postId] = $this->stops->find($postId);
        }

        return $this->found[$postId];
    }
}
