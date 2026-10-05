<?php

declare(strict_types=1);

use App\Services\Traffic\EmailLinkTagger;

beforeEach(function () {
    $this->tagger = new EmailLinkTagger('https://lexicon.test');
});

test('links to counted pages get the campaign, other links stay as they are', function () {
    [$html] = $this->tagger->tag(
        '<a href="https://lexicon.test/en/blog/demo/hello?x=1&amp;y=2#top">post</a>'
        .'<a href="https://lexicon.test/subscriptions/unsubscribe/abc">unsubscribe</a>'
        .'<a href="https://elsewhere.example/blog/demo">other site</a>',
        null,
        'new-post'
    );

    expect($html)->toContain('href="https://lexicon.test/en/blog/demo/hello?x=1&amp;y=2&amp;utm_source=lexicon&amp;utm_medium=email&amp;utm_campaign=new-post#top"')
        ->and($html)->toContain('href="https://lexicon.test/subscriptions/unsubscribe/abc"')
        ->and($html)->toContain('href="https://elsewhere.example/blog/demo"');
});

test('a link someone tagged keeps its own tags', function () {
    [$html] = $this->tagger->tag('<a href="https://lexicon.test/discover?utm_source=friends">x</a>', null, 'weekly');

    expect($html)->toBe('<a href="https://lexicon.test/discover?utm_source=friends">x</a>');
});

test('in plain text the punctuation after an address stays outside it', function () {
    [, $text] = $this->tagger->tag('', 'Read https://lexicon.test/en/discover. Or reset at https://lexicon.test/password/reset/abc.', 'weekly');

    expect($text)->toBe('Read https://lexicon.test/en/discover?utm_source=lexicon&utm_medium=email&utm_campaign=weekly. Or reset at https://lexicon.test/password/reset/abc.');
});

test('the campaign is named after the email', function (string $class, string $campaign) {
    expect(EmailLinkTagger::campaignFor($class))->toBe($campaign);
})->with([
    ['App\\Mail\\NewPostMail', 'new-post'],
    ['App\\Mail\\TrafficDigestMail', 'traffic-digest'],
    ['App\\Mail\\WelcomeEmail', 'welcome-email'],
]);
