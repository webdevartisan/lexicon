<?php

declare(strict_types=1);

use App\Models\AnalyticsEventModel;
use Tests\Helpers\AnalyticsFixture;

/**
 * Partitions, pruning and the reads the aggregation and admin pages make of raw events.
 */
beforeEach(function () {
    $this->events = new AnalyticsEventModel($this->db);

    $this->view = function (int $daysAgo, string $visitor = 'A', ?int $blogId = 5, ?string $search = null, ?int $results = null) {
        $props = array_filter(['q' => $search, 'search_results' => $results], static fn ($value): bool => $value !== null);

        AnalyticsFixture::view($this->db, [
            'blog_id' => $blogId,
            'page_type' => $search === null ? 'landing' : 'discover',
            'path' => $search === null ? '/blog/b' : '/discover',
            'visitor' => $visitor,
            'props' => $props === [] ? null : $props,
            'engaged_seconds' => 30,
            'created_at' => gmdate('Y-m-d H:i:s', time() - $daysAgo * 86400),
        ]);
    };
});

afterEach(function () {
    // Partition changes outlive the test's cleanup, so the table goes back to how schema.sql builds it.
    $this->db->getConnection()->exec('TRUNCATE TABLE analytics_events');
    $this->db->getConnection()->exec(
        "ALTER TABLE analytics_events PARTITION BY RANGE COLUMNS(local_date) (
            PARTITION p_start VALUES LESS THAN ('2026-01-01'), PARTITION p_future VALUES LESS THAN (MAXVALUE))"
    );
});

test('pruning drops whole old days and deletes only events past retention', function () {
    foreach ([45, 40, 31, 29, 0] as $daysAgo) {
        ($this->view)($daysAgo);
    }

    $added = $this->events->addDayPartitions(3);
    $pruned = $this->events->pruneOlderThan(30);
    $left = $this->db->query('SELECT DATEDIFF(UTC_DATE(), local_date) FROM analytics_events ORDER BY local_date')->fetchAll(PDO::FETCH_COLUMN);

    expect($added)->toBeGreaterThan(40)
        ->and($pruned)->toBe(3)
        ->and(array_map('intval', $left))->toBe([29, 0])
        ->and($this->events->addDayPartitions(3))->toBe(0);
});

test('blogs whose views changed are found by new views and by late leave pings', function () {
    ($this->view)(0, 'A', 5);
    ($this->view)(2, 'B', 6);
    $this->db->execute('UPDATE analytics_events SET engaged_at = UTC_TIMESTAMP() WHERE blog_id = 6');

    expect($this->events->blogsChangedSince(gmdate('Y-m-d H:i:s', time() - 3600)))->toEqualCanonicalizing([5, 6]);
});

test('a search shows only once enough different people made it', function () {
    foreach (['A', 'B', 'C'] as $visitor) {
        ($this->view)(0, $visitor, null, 'gardens', 12);
    }
    ($this->view)(0, 'D', null, 'my own name', 0);
    ($this->view)(0, 'D', null, 'my own name', 0);

    expect($this->events->searchTerms(gmdate('Y-m-d'), gmdate('Y-m-d'), 3, 10))->toBe([
        ['value' => 'gardens', 'searches' => 3, 'visitors' => 3],
    ]);
});

test('searches that found nothing are listed on their own', function () {
    foreach (['A', 'B', 'C'] as $visitor) {
        ($this->view)(0, $visitor, null, 'knitting', 0);
        ($this->view)(0, $visitor, null, 'gardens', 12);
    }

    expect($this->events->searchTerms(gmdate('Y-m-d'), gmdate('Y-m-d'), 3, 10, true))->toBe([
        ['value' => 'knitting', 'searches' => 3, 'visitors' => 3],
    ]);
});
