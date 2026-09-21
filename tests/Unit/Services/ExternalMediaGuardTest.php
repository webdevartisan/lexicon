<?php

declare(strict_types=1);

use App\Services\ExternalMediaGuard;

beforeEach(function () {
    $this->guard = new ExternalMediaGuard('https://lexicon.test');
});

test('first-party images pass', function (string $src) {
    expect($this->guard->newExternalSources('<p><img src="'.$src.'" alt=""></p>'))->toBe([]);
})->with([
    '/uploads/users/1/blogs/6/postImages/a.webp',
    'https://lexicon.test/uploads/a.png',
    'data:image/png;base64,iVBORw0KGgo=',
    'relative/path.png',
]);

test('outside images and embeds are reported', function (string $html, string $url) {
    expect($this->guard->newExternalSources($html))->toBe([$url]);
})->with([
    'img' => ['<img src="https://images.example.com/a.jpg">', 'https://images.example.com/a.jpg'],
    'protocol relative' => ['<img src="//cdn.example.com/a.jpg">', '//cdn.example.com/a.jpg'],
    'srcset' => ['<img src="/uploads/a.jpg" srcset="/uploads/a.jpg 1x, https://x.example/b.jpg 2x">', 'https://x.example/b.jpg'],
    'iframe' => ['<iframe src="https://www.youtube.com/embed/abc"></iframe>', 'https://www.youtube.com/embed/abc'],
    'video poster' => ['<video poster="https://x.example/p.jpg"></video>', 'https://x.example/p.jpg'],
    'object' => ['<object data="https://x.example/f.swf"></object>', 'https://x.example/f.swf'],
    'script scheme' => ['<img src="javascript:alert(1)">', 'javascript:alert(1)'],
]);

test('an outside image the post already had is left alone', function () {
    $before = '<p>Old</p><img src="https://images.unsplash.com/photo-1.jpg">';
    $after = $before.'<p>More text</p>';

    expect($this->guard->newExternalSources($after, $before))->toBe([]);
});

test('a newly added outside image is still caught next to a grandfathered one', function () {
    $before = '<img src="https://images.unsplash.com/photo-1.jpg">';
    $after = $before.'<img src="https://evil.example/new.png">';

    expect($this->guard->newExternalSources($after, $before))->toBe(['https://evil.example/new.png']);
});

test('the rejection names the hosts, not the full addresses', function () {
    $message = $this->guard->rejectionMessage(['https://a.example/x.png?token=1', 'https://a.example/y.png']);

    expect($message)->toContain('a.example')
        ->and($message)->not->toContain('token=1')
        ->and(substr_count($message, 'a.example'))->toBe(1);
});
