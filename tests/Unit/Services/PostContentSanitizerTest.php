<?php

declare(strict_types=1);

use App\Services\PostContentSanitizer;

beforeEach(function () {
    $this->sanitizer = new PostContentSanitizer();
});

test('scripting is removed', function (string $html, string $gone) {
    expect($this->sanitizer->clean($html))->not->toContain($gone);
})->with([
    'script tag' => ['<p>Hi</p><script>alert(1)</script>', 'alert(1)'],
    'script in svg' => ['<svg><script>alert(1)</script></svg>', 'alert(1)'],
    'onerror' => ['<img src="/uploads/a.png" onerror="alert(1)">', 'onerror'],
    'onload on svg' => ['<svg onload="alert(1)"></svg>', 'onload'],
    'onmouseover' => ['<p onmouseover="alert(1)">Hover</p>', 'onmouseover'],
    'uppercase handler' => ['<p ONCLICK="alert(1)">Click</p>', 'alert(1)'],
    'javascript href' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
    'javascript href spaced' => ['<a href=" javascript:alert(1)">x</a>', 'javascript:'],
    'data href' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:text/html'],
    'style block' => ['<style>body{display:none}</style><p>Hi</p>', 'display:none'],
    'iframe' => ['<iframe src="https://evil.example/x"></iframe>', 'iframe'],
    'object' => ['<object data="/x.swf"></object>', 'object'],
    'form and input' => ['<form action="/x"><input name="pw"></form>', '<input'],
    'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.example">', 'evil.example'],
    'base tag' => ['<base href="https://evil.example/">', 'evil.example'],
    'malformed comment smuggling' => ['<svg><style><!--</style><img src=x onerror=alert(1)>', 'onerror'],
]);

test('the markup writers actually use survives', function (string $html, string $kept) {
    expect($this->sanitizer->clean($html))->toContain($kept);
})->with([
    'bold' => ['<p>A <strong>bold</strong> word</p>', '<strong>bold</strong>'],
    'link' => ['<p><a href="/blog/x/post" rel="nofollow" target="_blank">Read</a></p>', 'href="/blog/x/post"'],
    'mail link' => ['<a href="mailto:hi@lexicon.test">Mail</a>', 'mailto:hi&#64;lexicon.test'],
    'image' => ['<img src="/uploads/a.webp" alt="A" width="800" height="600" loading="lazy">', 'loading="lazy"'],
    'responsive picture' => [
        '<picture><source srcset="/uploads/a.webp 1x, /uploads/b.webp 2x" sizes="50vw" type="image/webp"><img src="/uploads/a.webp" alt=""></picture>',
        'srcset="/uploads/a.webp 1x, /uploads/b.webp 2x"',
    ],
    'figure' => ['<figure><img src="/uploads/a.webp" alt=""><figcaption>Caption</figcaption></figure>', '<figcaption>Caption</figcaption>'],
    'table cell label' => ['<table><tbody><tr><td data-label="Name">Ada</td></tr></tbody></table>', 'data-label="Name"'],
    'column group' => ['<table><colgroup><col span="2"></colgroup><tbody><tr><td>A</td></tr></tbody></table>', '<colgroup>'],
    'section block' => ['<section data-section-type="callout"><header><h2>Title</h2></header></section>', 'data-section-type="callout"'],
    'class' => ['<div class="post-note"><p>Note</p></div>', 'class="post-note"'],
    'lists' => ['<ul><li>One</li><li>Two</li></ul>', '<li>Two</li>'],
    'code' => ['<pre><code>$a = 1;</code></pre>', '<code>$a &#61; 1;</code>'],
    'quote' => ['<blockquote><p>Quoted</p></blockquote>', '<blockquote>'],
    'heading and rule' => ['<h2>Part</h2><hr>', '<h2>Part</h2>'],
]);

test('style keeps presentation and drops the rest', function (string $style, string $expected) {
    $cleaned = $this->sanitizer->clean('<p style="'.$style.'">Text</p>');
    expect($cleaned)->toContain($expected);
})->with([
    'colour and alignment' => ['color: #333; text-align: center', 'color: #333; text-align: center'],
    'table borders' => ['border: 1px solid #ddd; padding: 8px', 'border: 1px solid #ddd; padding: 8px'],
    'custom property' => ['--text-color: #111', '--text-color: #111'],
    'overlay dropped' => ['position: fixed; top: 0; color: red', 'style="color: red"'],
    'outside image dropped' => ['background: url(https://evil.example/a.png); color: red', 'style="color: red"'],
    'expression dropped' => ['width: expression(alert(1)); color: red', 'style="color: red"'],
    'import dropped' => ['color: red; behavior: url(#default#x)', 'style="color: red"'],
]);

it('drops the style attribute entirely when nothing in it is allowed', function () {
    expect($this->sanitizer->clean('<p style="position: absolute; z-index: 99">Text</p>'))
        ->toBe('<p>Text</p>');
});

it('leaves plain content untouched', function () {
    $html = '<h2>Title</h2><p>A paragraph with <em>emphasis</em>.</p>';

    expect($this->sanitizer->clean($html))->toBe($html);
});

it('leaves already sanitized content alone the second time', function () {
    $once = $this->sanitizer->clean('<p>Rates &amp; terms: a=1 <a href="/x?a=1&amp;b=2">link</a></p>');

    expect($this->sanitizer->clean($once))->toBe($once);
});

it('returns nothing for empty content', function () {
    expect($this->sanitizer->clean(''))->toBe('');
});

it('refuses content too long to check instead of shortening it', function () {
    $this->sanitizer->clean('<p>'.str_repeat('a', 1_000_001).'</p>');
})->throws(RuntimeException::class, 'too long');
