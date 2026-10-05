<?php

declare(strict_types=1);

use App\Models\TrafficHitModel;

/**
 * Partitions, pruning and the reads the aggregation and admin pages make of raw views.
 */
beforeEach(function () {
    $this->hits = new TrafficHitModel($this->db);

    $this->view = function (int $daysAgo, string $visitor = 'A', ?int $blogId = 5, ?string $search = null, ?int $seconds = 30) {
        $this->db->execute(
            "INSERT INTO traffic_hits (view_id, blog_id, page_type, path, path_hash, visitor_hash, visitor_kind, channel, search_term,
                                       device, browser, os, locale, engaged_seconds, local_date, created_at)
             VALUES (?, ?, 'landing', '/blog/b', ?, ?, 'daily', 'direct', ?, 'desktop', 'Chrome', 'Windows', 'en', ?,
                     UTC_DATE() - INTERVAL ? DAY, UTC_TIMESTAMP() - INTERVAL ? DAY)",
            [random_bytes(16), $blogId, random_bytes(8), md5($visitor, true), $search, $seconds, $daysAgo, $daysAgo]
        );
    };
});

afterEach(function () {
    // Partition changes outlive the test's cleanup, so the table goes back to how schema.sql builds it.
    $this->db->getConnection()->exec('TRUNCATE TABLE traffic_hits');
    $this->db->getConnection()->exec(
        "ALTER TABLE traffic_hits PARTITION BY RANGE COLUMNS(local_date) (
            PARTITION p_start VALUES LESS THAN ('2026-01-01'), PARTITION p_future VALUES LESS THAN (MAXVALUE))"
    );
});

test('pruning drops whole old days and deletes only views past retention', function () {
    foreach ([45, 40, 31, 29, 0] as $daysAgo) {
        ($this->view)($daysAgo);
    }

    $added = $this->hits->addDayPartitions(3);
    $pruned = $this->hits->pruneOlderThan(30);
    $left = $this->db->query('SELECT DATEDIFF(UTC_DATE(), local_date) FROM traffic_hits ORDER BY local_date')->fetchAll(PDO::FETCH_COLUMN);

    expect($added)->toBeGreaterThan(40)
        ->and($pruned)->toBe(3)
        ->and(array_map('intval', $left))->toBe([29, 0])
        ->and($this->hits->addDayPartitions(3))->toBe(0);
});

test('blogs whose views changed are found by new views and by late leave pings', function () {
    ($this->view)(0, 'A', 5);
    ($this->view)(2, 'B', 6);
    $this->db->execute('UPDATE traffic_hits SET engaged_at = UTC_TIMESTAMP() WHERE blog_id = 6');

    expect($this->hits->blogsChangedSince(gmdate('Y-m-d H:i:s', time() - 3600)))->toEqualCanonicalizing([5, 6]);
});

test('a search shows only once enough different people made it', function () {
    foreach (['A', 'B', 'C'] as $visitor) {
        ($this->view)(0, $visitor, null, 'gardens');
    }
    ($this->view)(0, 'D', null, 'my own name');
    ($this->view)(0, 'D', null, 'my own name');

    expect($this->hits->searchTerms(gmdate('Y-m-d'), gmdate('Y-m-d'), 3, 10))->toBe([
        ['value' => 'gardens', 'searches' => 3, 'visitors' => 3],
    ]);
});
