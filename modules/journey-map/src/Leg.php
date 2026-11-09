<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * One post's part of the journey: the place before it, the post's own places in order and the place after it.
 */
final class Leg
{
    /**
     * @param  non-empty-list<array<string, mixed>>  $stops  The post's own places in order
     * @param  array<string, mixed>|null  $previous  The public place before the post's first one, null at the start
     * @param  array<string, mixed>|null  $next  The public place after the post's last one, null at the end
     * @param  int|null  $position  The post's place among the journey's posts from 1, null while it is not public
     * @param  int  $total  How many public posts the journey has
     * @param  array<string, array<string, mixed>>  $roads  Stored legs keyed by key()
     */
    public function __construct(
        public readonly array $stops,
        public readonly ?array $previous = null,
        public readonly ?array $next = null,
        public readonly ?int $position = null,
        public readonly int $total = 0,
        private readonly array $roads = [],
    ) {}

    /**
     * Find the post among the journey's public places. A draft or private post has its own places
     * but no neighbours, since readers never see it on the journey.
     *
     * @param  list<array<string, mixed>>  $journey  Public places in journey order
     * @param  non-empty-list<array<string, mixed>>  $stops  The post's own places in order
     * @param  array<string, array<string, mixed>>  $roads  Stored legs keyed by key()
     */
    public static function around(array $journey, int $postId, array $stops, array $roads = []): self
    {
        $ids = array_map('intval', array_column($journey, 'post_id'));
        $first = array_search($postId, $ids, true);
        if ($first === false) {
            return new self($stops);
        }

        $last = (int) array_key_last(array_filter($ids, static fn (int $id): bool => $id === $postId));
        $posts = array_values(array_unique($ids));

        return new self(
            array_slice($journey, $first, $last - $first + 1),
            $journey[$first - 1] ?? null,
            $journey[$last + 1] ?? null,
            (int) array_search($postId, $posts, true) + 1,
            count($posts),
            $roads
        );
    }

    /**
     * The post's first place.
     *
     * @return array<string, mixed>
     */
    public function first(): array
    {
        return $this->stops[0];
    }

    /**
     * The post's last place.
     *
     * @return array<string, mixed>
     */
    public function last(): array
    {
        return $this->stops[count($this->stops) - 1];
    }

    /**
     * The stored legs the post travels, one per step: from the place before it, then between its own places.
     * A step between two places at the same spot has none.
     *
     * @return list<array<string, mixed>|null>
     */
    public function roads(): array
    {
        $path = $this->path();
        $roads = [];
        for ($i = 1, $n = count($path); $i < $n; $i++) {
            $roads[] = $this->roadBetween($path[$i - 1], $path[$i]);
        }

        return $roads;
    }

    /**
     * Kilometres the post travels, from the place before it through its own places: by road where the road
     * is known, otherwise in a straight line. Null for a post with one place at the start of the journey.
     */
    public function km(): ?float
    {
        $path = $this->path();
        if (count($path) < 2) {
            return null;
        }

        $km = 0.0;
        for ($i = 1, $n = count($path); $i < $n; $i++) {
            $road = $this->roadBetween($path[$i - 1], $path[$i]);
            $km += self::points($road) === null ? Distance::between($path[$i - 1], $path[$i]) : (float) $road['km'];
        }

        return $km;
    }

    /**
     * The road into one of the post's own places, as map points, or null while that step draws straight.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    public function lineInto(int $index): ?array
    {
        $before = $index === 0 ? $this->previous : ($this->stops[$index - 1] ?? null);

        return $before === null ? null : self::points($this->roadBetween($before, $this->stops[$index]));
    }

    /**
     * The road from the post's last place to the place after it, as map points.
     *
     * @return list<array{0: float, 1: float}>|null
     */
    public function lineOut(): ?array
    {
        return $this->next === null ? null : self::points($this->roadBetween($this->last(), $this->next));
    }

    /**
     * Whether any place the post travels through is only an approximate area, so the distance is approximate too.
     */
    public function isApproximate(): bool
    {
        foreach ($this->path() as $stop) {
            if ($stop['place_precision'] === 'area') {
                return true;
            }
        }

        return false;
    }

    /**
     * The fingerprint of a place, so a leg knows when one of its ends has moved.
     *
     * @param  array<string, mixed>  $stop
     */
    public static function placeHash(array $stop): string
    {
        return substr(sha1(sprintf('%.6F,%.6F', (float) $stop['lat'], (float) $stop['lng'])), 0, 16);
    }

    /**
     * How a stored leg between two places is known, or null when both are the same spot and no road is needed.
     *
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     */
    public static function key(array $from, array $to): ?string
    {
        $fromHash = self::placeHash($from);
        $toHash = self::placeHash($to);

        return $fromHash === $toHash ? null : $fromHash.':'.$toHash;
    }

    /**
     * The road a stored leg follows as [lng, lat] points, or null while it has none and draws straight.
     *
     * @param  array<string, mixed>|null  $road
     * @return list<array{0: float, 1: float}>|null
     */
    public static function points(?array $road): ?array
    {
        $line = (string) ($road['line'] ?? '');
        $points = $line === '' || !isset($road['km']) ? [] : Polyline::decode($line);

        return count($points) < 2 ? null : $points;
    }

    /**
     * The places the post travels through: the one before it, when there is one, then its own.
     *
     * @return list<array<string, mixed>>
     */
    private function path(): array
    {
        return $this->previous === null ? $this->stops : [$this->previous, ...$this->stops];
    }

    /**
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @return array<string, mixed>|null
     */
    private function roadBetween(array $from, array $to): ?array
    {
        $key = self::key($from, $to);

        return $key === null ? null : ($this->roads[$key] ?? null);
    }
}
