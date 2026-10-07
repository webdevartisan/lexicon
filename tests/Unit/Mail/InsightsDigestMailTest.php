<?php

declare(strict_types=1);

use App\Mail\InsightsDigestMail;

/**
 * The weekly digest follows the Insights pages: one repeated section per
 * blog, each part saying what its page shows for the week and linking to that
 * page for the same seven days.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';

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

    expect($mail->getSubject())->toBe('Your week on Lexicon: Sep 28 – Oct 4, 2026')
        ->and($mail->getTextBody())->toContain('Views: 1,240 · Daily visitors: 860 · Change on the week before: +24%')
        ->and($mail->getTextBody())->toContain('Top source · Open')
        ->and($mail->getTextBody())->toContain('Google')
        ->and($mail->getTextBody())->toContain('Subscriptions: 3 · Comments: 1 · Likes: 0')
        ->and($mail->getTextBody())->toContain('Ten Days in Crete · 410');
});

test('each blog gets its own section, in the order given', function () {
    $second = ['name' => 'Harbour Diary', 'id' => 8, 'slug' => 'harbour-diary'] + $this->blog;
    $text = (string) (new InsightsDigestMail('owner@example.test', '2026-09-28', '2026-10-04', [$this->blog, $second]))->getTextBody();

    expect(strpos($text, 'Field Notes'))->toBeLessThan((int) strpos($text, 'Harbour Diary'))
        ->and($text)->toContain('https://example.test/dashboard/blog/8/insights/goals?');
});

test('a week with nothing to compare or name shows a dash rather than words', function () {
    $quiet = ['source' => null, 'posts' => [], 'goals' => [], 'previous' => 0] + $this->blog;
    $text = (string) (new InsightsDigestMail('owner@example.test', '2026-09-28', '2026-10-04', [$quiet]))->getTextBody();

    expect($text)->toContain('Change on the week before: –')
        ->and($text)->toContain('Subscriptions: 0 · Comments: 0');
});

test('names written by people are escaped in the HTML', function () {
    $blog = ['name' => '<b>Notes</b>', 'posts' => [['title' => '<script>x</script>', 'views' => 1]]] + $this->blog;
    $body = (new InsightsDigestMail('owner@example.test', '2026-09-28', '2026-10-04', [$blog]))->getBody();

    expect($body)->not->toContain('<script>x</script>')
        ->and($body)->not->toContain('<b>Notes</b>')
        ->and($body)->toContain('&lt;script&gt;');
});
