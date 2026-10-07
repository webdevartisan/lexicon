<?php

declare(strict_types=1);

use App\Mail\Templates\EmailHtmlLinter;
use App\Mail\Templates\HtmlToText;

/**
 * What an admin may put in an email or a layout, and how the plain-text part reads.
 */
test('anything that runs, submits or re-points the page is refused', function (string $html, string $expected) {
    expect(implode(' ', EmailHtmlLinter::lintHtml($html)['errors']))->toContain($expected);
})->with([
    'script' => ['<script>alert(1)</script>', '<script> tags are not allowed'],
    'iframe' => ['<IFRAME src="https://x">', '<iframe> tags are not allowed'],
    'form' => ['<form action="https://evil"><input name="password"></form>', '<form> tags are not allowed'],
    'handler' => ['<img src="x" onerror="alert(1)">', 'Event handler attributes'],
    'js link' => ['<a href=" JavaScript:alert(1)">x</a>', 'Script links'],
    'style tag' => ['<style>p{}</style>', "belongs in the layout's head"],
    'meta tag' => ['<meta http-equiv="refresh" content="0;url=https://evil">', "belongs in the layout's head"],
    'bad name' => ['<p>{{ First-Name }}</p>', 'is not valid'],
    'unquoted' => ['<a href={{ url }}>x</a>', 'not inside a quoted attribute'],
    'unclosed' => ['<p>{{ body </p>', 'unclosed'],
]);

test('a layout may have a head with meta and style, and Outlook markup, but nothing that runs', function () {
    $layout = '<!DOCTYPE html><html lang="{{ lang }}" xmlns:v="urn:schemas-microsoft-com:vml"><head><meta charset="UTF-8"><title>{{ subject }}</title>'
        .'<style>@media (max-width:620px){ .x { padding:0 !important; } }</style></head>'
        .'<body><!--[if mso]><v:roundrect href="{{ url }}" fillcolor="{{ primary_color }}"></v:roundrect><![endif]-->{{ content }}</body></html>';

    expect(EmailHtmlLinter::lintHtml($layout, 'https://example.test', true)['errors'])->toBe([])
        ->and(EmailHtmlLinter::lintHtml($layout.'<script>x</script>', 'https://example.test', true)['errors'])->not->toBe([])
        ->and(EmailHtmlLinter::lintHtml('<style>@import url(x.css);</style>', '', true)['errors'])->not->toBe([]);
});

test('ordinary email markup passes', function () {
    $result = EmailHtmlLinter::lintHtml('<p style="color:#333"><a href="{{ url }}" title=\'{{ title }}\'>{{ label }}</a><img src="https://example.test/logo.png" alt="Logo"></p>', 'https://example.test');

    expect($result)->toBe(['errors' => [], 'warnings' => []]);
});

test('images and stylesheets from other sites are flagged for the privacy cost', function () {
    $html = EmailHtmlLinter::lintHtml('<img src="https://tracker.example.com/p.gif" alt="">', 'https://example.test');
    $css = EmailHtmlLinter::lintCss('p { background: url(//cdn.example.net/bg.png) }', 'https://example.test');

    expect($html['errors'])->toBe([])
        ->and($html['warnings'][0])->toContain('tracker.example.com')
        ->and($css['warnings'][0])->toContain('cdn.example.net');
});

test('css cannot break out, run script, import or take placeholders', function (string $css) {
    expect(EmailHtmlLinter::lintCss($css)['errors'])->not->toBe([]);
})->with(['</style><script>', 'p { width: expression(alert(1)) }', '@import url(x.css);', 'p { color: {{ colour }} }']);

test('html becomes readable plain text with links kept', function () {
    $text = HtmlToText::convert(
        '<h2>Title</h2><p>Hello &amp; welcome,<br>friend.</p><ul><li>One</li><li>Two</li></ul>'
        .'<p><a href="https://x.test/a?b=1&amp;c=2">Open</a> or <a href="https://x.test/">https://x.test/</a></p>'
        .'<p><a href="mailto:hi@x.test">hi@x.test</a></p><style>p{}</style><!-- note -->'
    );

    expect($text)->toBe("Title\n\nHello & welcome,\nfriend.\n\n- One\n- Two\n\nOpen (https://x.test/a?b=1&c=2) or https://x.test/\n\nhi@x.test");
});
