<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Framework\Database;

/**
 * The stored legs of each journey: one row per pair of neighbouring public places, with its road route once planned.
 * A leg is known by the fingerprints of its two places, so saving a post's places again keeps the roads already
 * planned between places that did not move.
 */
final class LegModel
{
    /** A leg that failed this often draws straight until one of its places changes. */
    public const MAX_ATTEMPTS = 3;

    private const ONE_LEG = 'WHERE blog_id = ? AND from_hash = ? AND to_hash = ?';

    public function __construct(private Database $db)
    {
    }

    /**
     * Every leg of a blog, keyed by Leg::key().
     *
     * @return array<string, array<string, mixed>>
     */
    public function forBlog(int $blogId): array
    {
        $rows = $this->db->query(
            'SELECT from_hash, to_hash, status, km, line, attempts FROM journey_legs WHERE blog_id = ?',
            [$blogId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $legs = [];
        foreach ($rows as $row) {
            $legs[$row['from_hash'].':'.$row['to_hash']] = $row;
        }

        return $legs;
    }

    /**
     * A new leg between two places, waiting for its road route.
     *
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     */
    public function add(int $blogId, array $from, array $to): void
    {
        $this->db->execute(
            'INSERT INTO journey_legs (blog_id, from_hash, to_hash, from_lat, from_lng, to_lat, to_lng)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$blogId, Leg::placeHash($from), Leg::placeHash($to), $from['lat'], $from['lng'], $to['lat'], $to['lng']]
        );
    }

    /**
     * Forget a leg whose places are no longer neighbours.
     */
    public function delete(int $blogId, string $fromHash, string $toHash): void
    {
        $this->db->execute('DELETE FROM journey_legs '.self::ONE_LEG, [$blogId, $fromHash, $toHash]);
    }

    /**
     * Legs still to plan, with both places, oldest first. Failed legs come back until they run out of attempts.
     *
     * @return list<array<string, mixed>>
     */
    public function waiting(int $limit): array
    {
        return $this->db->query(
            "SELECT blog_id, from_hash, to_hash, from_lat, from_lng, to_lat, to_lng
             FROM journey_legs
             WHERE status = 'waiting' OR (status = 'failed' AND attempts < ?)
             ORDER BY attempts, blog_id
             LIMIT ".max(1, $limit),
            [self::MAX_ATTEMPTS]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Store a planned leg: a road route with its km and line, or none when no road joins the places.
     */
    public function store(int $blogId, string $fromHash, string $toHash, string $status, ?float $km, ?string $line): void
    {
        $this->db->execute(
            'UPDATE journey_legs SET status = ?, km = ?, line = ?, planned_at = UTC_TIMESTAMP() '.self::ONE_LEG,
            [$status, $km, $line, $blogId, $fromHash, $toHash]
        );
    }

    /**
     * Count a failed try, so the leg is tried again later, up to the limit.
     */
    public function failed(int $blogId, string $fromHash, string $toHash): void
    {
        $this->db->execute(
            "UPDATE journey_legs SET status = 'failed', attempts = attempts + 1 ".self::ONE_LEG,
            [$blogId, $fromHash, $toHash]
        );
    }

    /**
     * Plan every leg again on a rebuilt network. Lines stay until their new answer, so maps never go straight.
     */
    public function replanAll(): void
    {
        $this->db->execute("UPDATE journey_legs SET status = 'waiting', attempts = 0");
    }

    /**
     * Forget every road once the network is removed, so legs draw straight and wait for a new network.
     */
    public function forgetRoads(): void
    {
        $this->db->execute("UPDATE journey_legs SET status = 'waiting', km = NULL, line = NULL, attempts = 0");
    }

    /**
     * How many legs still wait for a route, for the control panel.
     */
    public function waitingCount(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM journey_legs WHERE status = 'waiting' OR (status = 'failed' AND attempts < ?)",
            [self::MAX_ATTEMPTS]
        )->fetchColumn();
    }

    /**
     * Delete every leg of the blog.
     */
    public function eraseBlog(int $blogId): void
    {
        $this->db->execute('DELETE FROM journey_legs WHERE blog_id = ?', [$blogId]);
    }
}
