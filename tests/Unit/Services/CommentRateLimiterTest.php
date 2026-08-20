<?php

declare(strict_types=1);

/**
 * Unit tests for CommentRateLimiter.
 *
 * The throttle exists because /comments/create is open to guests and every
 * accepted comment queues mail to the blog team, so these tests pin the parts
 * that would let a flood through: the soft limit, the fact that blocked
 * attempts still count, the hard lock, and the separation between the
 * submission and interaction buckets.
 *
 * An in-memory cache double stands in for CacheService so nothing touches the
 * filesystem. Time is never mocked; where decay matters the stored state is
 * seeded with an old timestamp, which is the same thing the real limiter reads.
 */

use Tests\Helpers\ThrottleTestHelper;

/**
 * @return array{0: App\Services\CommentRateLimiter, 1: Framework\Cache\CacheService}
 */
function makeCommentThrottle(): array
{
    return ThrottleTestHelper::commentThrottle();
}

afterEach(function () {
    Mockery::close();
});

describe('CommentRateLimiter submission bucket', function () {

    test('the first five submissions pass and the sixth is refused', function () {
        [$throttle] = makeCommentThrottle();

        $results = [];
        for ($i = 0; $i < 6; $i++) {
            $results[] = $throttle->hitSubmission('198.51.100.1');
        }

        expect($results)->toBe([false, false, false, false, false, true]);
    });

    test('a blocked client that keeps hammering does not decay back under the limit', function () {
        [$throttle, $cache] = makeCommentThrottle();
        $ip = '198.51.100.2';

        for ($i = 0; $i < 6; $i++) {
            $throttle->hitSubmission($ip);
        }

        // Blocked attempts are counted too, so the score keeps climbing. Idling
        // one half-life after them still leaves it above the limit; without
        // counting them, this is exactly where a trickle would leak through.
        for ($i = 0; $i < 4; $i++) {
            $throttle->hitSubmission($ip);
        }

        ThrottleTestHelper::age($cache, "comment:submit:{$ip}", 600);

        expect($throttle->hitSubmission($ip))->toBeTrue();
    });

    test('a quiet client is allowed back once the score decays', function () {
        [$throttle, $cache] = makeCommentThrottle();
        $ip = '198.51.100.3';

        for ($i = 0; $i < 6; $i++) {
            $throttle->hitSubmission($ip);
        }

        // Two half-lives of silence takes a score of six down to about 1.5.
        ThrottleTestHelper::age($cache, "comment:submit:{$ip}", 1200);

        expect($throttle->hitSubmission($ip))->toBeFalse();
    });

    test('availableIn is zero while under the limit and positive once blocked', function () {
        [$throttle] = makeCommentThrottle();
        $ip = '198.51.100.4';

        $throttle->hitSubmission($ip);
        expect($throttle->submissionAvailableIn($ip))->toBe(0);

        for ($i = 0; $i < 6; $i++) {
            $throttle->hitSubmission($ip);
        }

        expect($throttle->submissionAvailableIn($ip))->toBeGreaterThan(0);
    });

    test('one IP being throttled leaves another alone', function () {
        [$throttle] = makeCommentThrottle();

        for ($i = 0; $i < 6; $i++) {
            $throttle->hitSubmission('198.51.100.5');
        }

        expect($throttle->hitSubmission('198.51.100.6'))->toBeFalse();
    });
});

describe('CommentRateLimiter hard lock', function () {

    test('four soft blocks in an hour lock the IP out for half an hour', function () {
        [$throttle, $cache] = makeCommentThrottle();
        $ip = '198.51.100.7';
        $softKey = "comment:submit:{$ip}";

        // Four separate bursts: each one trips the soft limit, then the client
        // waits out the score and comes back. That pattern is the whole point
        // of the hard lock, so drive it rather than poking the lock key.
        for ($burst = 0; $burst < 4; $burst++) {
            for ($i = 0; $i < 6; $i++) {
                $throttle->hitSubmission($ip);
            }

            ThrottleTestHelper::age($cache, $softKey, 1800);
        }

        // The soft score is spent, so only the hard lock can still refuse this.
        expect($throttle->hitSubmission($ip))->toBeTrue()
            ->and($throttle->submissionAvailableIn($ip))->toBeGreaterThan(1500);
    });

    test('a locked IP is refused without inflating its score any further', function () {
        [$throttle, $cache] = makeCommentThrottle();
        $ip = '198.51.100.8';
        $softKey = "comment:submit:{$ip}";

        for ($burst = 0; $burst < 4; $burst++) {
            for ($i = 0; $i < 6; $i++) {
                $throttle->hitSubmission($ip);
            }

            ThrottleTestHelper::age($cache, $softKey, 1800);
        }

        $before = $cache->get($softKey);
        $throttle->hitSubmission($ip);

        expect($cache->get($softKey))->toBe($before);
    });
});

describe('CommentRateLimiter interaction bucket', function () {

    test('voting and reporting get a much looser limit than posting', function () {
        [$throttle] = makeCommentThrottle();
        $ip = '198.51.100.9';

        $results = [];
        for ($i = 0; $i < 60; $i++) {
            $results[] = $throttle->hitInteraction($ip);
        }

        expect($results[58])->toBeFalse()
            ->and($results[59])->toBeTrue();
    });

    test('being throttled for posting still lets a reader vote', function () {
        [$throttle] = makeCommentThrottle();
        $ip = '198.51.100.10';

        for ($i = 0; $i < 6; $i++) {
            $throttle->hitSubmission($ip);
        }

        expect($throttle->hitSubmission($ip))->toBeTrue()
            ->and($throttle->hitInteraction($ip))->toBeFalse()
            ->and($throttle->interactionAvailableIn($ip))->toBe(0);
    });
});
