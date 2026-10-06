<?php

declare(strict_types=1);

use App\Mail\InsightsDigestMail;

/**
 * The weekly digest follows the Insights pages: each part says what its page
 * shows for the week and links to that page for the same seven days.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';

    $this->blog = [
        'name' => 'Field Notes', 'id' => 7, 'slug' => 'field-notes', 'from' => '2026-09-28', 'to' => '2026-10-04',
        'views' => 1240, 'previous' => 1000, 'visitors' => 860, 'source' => 'Google',
        'posts' => [['title' => 'Ten Days in Crete', 'views' => 410]],
        'goals' => ['subscribe' => 3, 'comment' => 1],
    ];
});

test('each part links to its own page for the week the digest covers', function () {
    $mail = new InsightsDigestMail('owner@example.test', '2026-09-28', '2026-10-04', [$this->blog]);
    $week = 'range=custom&from=2026-09-28&to=2026-10-04';

    foreach (['overview', 'content', 'acquisition', 'goals'] as $page) {
        expect($mail->getTextBody())->toContain("https://example.test/dashboard/blog/7/insights/{$page}?{$week}");
    }

    expect($mail->getTextBody())->toContain('Overview: 1,240 views from 860 daily visitors, up 24% on the week before.')
        ->and($mail->getTextBody())->toContain('Acquisition: Top source: Google.')
        ->and($mail->getTextBody())->toContain('Goals: 3 subscriptions, 1 comment.')
        ->and($mail->getBody())->toContain('Ten Days in Crete');
});

test('a page with nothing to say that week is left out', function () {
    $quiet = ['source' => null, 'posts' => [], 'goals' => [], 'previous' => 0] + $this->blog;
    $text = (string) (new InsightsDigestMail('owner@example.test', '2026-09-28', '2026-10-04', [$quiet]))->getTextBody();

    expect($text)->toContain('nothing to compare with yet')
        ->and($text)->not->toContain('Content')
        ->and($text)->not->toContain('Acquisition')
        ->and($text)->not->toContain('Goals');
});

test('names written by people are escaped in the HTML', function () {
    $blog = ['name' => '<b>Notes</b>', 'posts' => [['title' => '<script>x</script>', 'views' => 1]]] + $this->blog;
    $body = (new InsightsDigestMail('owner@example.test', '2026-09-28', '2026-10-04', [$blog]))->getBody();

    expect($body)->not->toContain('<script>x</script>')
        ->and($body)->not->toContain('<b>Notes</b>')
        ->and($body)->toContain('&lt;script&gt;');
});
