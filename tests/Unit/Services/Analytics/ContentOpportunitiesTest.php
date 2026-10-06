<?php

declare(strict_types=1);

use App\Services\Analytics\ContentOpportunities;

function opportunityPost(int $id, int $views, int $readViews): array
{
    return ['post_id' => $id, 'views' => $views, 'read_views' => $readViews];
}

test('a busy post few people read and a quiet post most people read both stand out', function () {
    // 450 of 1,100 views read, about 41%. Post 7 counts towards that but is too small to judge.
    $found = ContentOpportunities::find([
        opportunityPost(1, 400, 80),
        opportunityPost(2, 300, 150),
        opportunityPost(3, 200, 100),
        opportunityPost(4, 100, 50),
        opportunityPost(5, 50, 40),
        opportunityPost(6, 40, 20),
        opportunityPost(7, 10, 10),
    ]);

    expect($found['average'])->toBe(450 / 1100)
        ->and($found['busyFrom'])->toBe(300)
        ->and($found['quietUpTo'])->toBe(100)
        ->and(array_column($found['underread'], 'post_id'))->toBe([1])
        ->and(array_column($found['overlooked'], 'post_id'))->toBe([5]);
});

test('posts with too few views are left out of the comparison', function () {
    $found = ContentOpportunities::find([
        opportunityPost(1, 300, 30),
        opportunityPost(2, 200, 100),
        opportunityPost(3, 100, 50),
        opportunityPost(4, 50, 25),
        opportunityPost(5, 19, 19),
    ]);

    expect(array_column($found['overlooked'], 'post_id'))->not->toContain(5)
        ->and(array_column($found['underread'], 'post_id'))->toBe([1]);
});

test('with only a few measured posts nothing is singled out', function () {
    $found = ContentOpportunities::find([
        opportunityPost(1, 500, 10),
        opportunityPost(2, 300, 290),
        opportunityPost(3, 100, 50),
    ]);

    expect($found['underread'])->toBe([])
        ->and($found['overlooked'])->toBe([])
        ->and($found['busyFrom'])->toBeNull();
});

test('a range with no views has no average', function () {
    expect(ContentOpportunities::find([])['average'])->toBeNull();
});
