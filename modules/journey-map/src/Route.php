<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * A blog's public journey, read once per request, so the slots on one page share a single query.
 */
final class Route
{
    /** @var array<int, list<array<string, mixed>>> Blog id => public stops in journey order */
    private array $journeys = [];

    /** @var array<int, array<string, array<string, mixed>>> Blog id => stored legs keyed by Leg::key() */
    private array $roads = [];

    public function __construct(private StopModel $stops, private LegModel $legs) {}

    /**
     * The post's leg, or null when the post has no place.
     */
    public function legOf(int $blogId, int $postId): ?Leg
    {
        $stops = $this->stops->ofPost($postId);
        if ($stops === []) {
            return null;
        }

        return Leg::around($this->journey($blogId), $postId, $stops, $this->roads($blogId));
    }

    /**
     * Public stops in journey order.
     *
     * @return list<array<string, mixed>>
     */
    public function journey(int $blogId): array
    {
        return $this->journeys[$blogId] ??= $this->stops->published($blogId);
    }

    /**
     * The blog's stored legs, keyed by Leg::key().
     *
     * @return array<string, array<string, mixed>>
     */
    public function roads(int $blogId): array
    {
        return $this->roads[$blogId] ??= $this->legs->forBlog($blogId);
    }
}
