<?php

declare(strict_types=1);

use App\Mail\Templates\CatalogTemplateSource;
use App\Mail\Templates\HtmlFragment;
use App\Mail\Templates\TemplateDataException;
use App\Services\TemplateRendererService;

/**
 * The renderer is where escaping and strictness are decided, so these pin the
 * rules a template author cannot change: values are text unless code says
 * otherwise, attributes never receive markup, links cannot become script, and
 * a template that needs something nobody provides is refused.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';
});

/**
 * One email (Tests\Fake) bound to a one-block template.
 *
 * @param  array<string, mixed>  $component  Overrides for the block
 * @param  array<string, string>  $mapping
 */
function rendererWith(array $component, array $mapping = [], ?string $subject = null, bool $strict = true): TemplateRendererService
{
    $source = new CatalogTemplateSource([
        'components' => [
            'block' => $component + ['label' => 'Block', 'html' => '<p>{{ body }}</p>'],
            'rule' => ['label' => 'Rule', 'html' => '<hr>', 'text' => ''],
        ],
        'templates' => ['only' => ['label' => 'Only', 'layout' => ['block', 'rule']]],
        'bindings' => ['Tests\\Fake' => ['template' => 'only', 'mapping' => $mapping, 'subject' => $subject]],
    ]);

    return new TemplateRendererService($source, $strict);
}

test('plain values are escaped, HtmlFragments are trusted, and substitution happens once', function () {
    $renderer = rendererWith(['html' => '<p>{{ body }}</p><div>{{ extra }}</div>']);

    $email = $renderer->renderEmail('Tests\\Fake', [
        'body' => '<script>alert(1)</script> {{ extra }}',
        'extra' => HtmlFragment::trusted('<strong>bold</strong>'),
    ]);

    expect($email->html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt; {{ extra }}')
        ->and($email->html)->toContain('<strong>bold</strong>')
        ->and($email->html)->not->toContain('<script>');
});

test('inside a tag a value is only ever escaped text, and links must be http, https or mailto', function () {
    $renderer = rendererWith(['html' => '<a href="{{ url }}" title="{{ body }}">{{ body }}</a>']);

    $email = $renderer->renderEmail('Tests\\Fake', [
        'url' => 'javascript:alert(1)',
        'body' => HtmlFragment::fromText("line one\n\"quoted\""),
    ]);

    expect($email->html)->toContain('href="#"')
        ->and($email->html)->toContain('title="line one'."\n".'&quot;quoted&quot;"')
        ->and($email->html)->toContain('line one<br>');

    $safe = $renderer->renderEmail('Tests\\Fake', ['url' => 'https://example.test/a?b=1&c=2', 'body' => 'x']);
    expect($safe->html)->toContain('href="https://example.test/a?b=1&amp;c=2"');
});

test('a link in binding wording is checked too, even in the first tag of the wording', function () {
    // The unsubscribe footer is shaped like this: text, then the only tag.
    $renderer = rendererWith(['html' => '<p>{{ note }}</p>'], ['note' => 'Bye <a href="{{ link }}">Unsubscribe</a>']);

    expect($renderer->renderEmail('Tests\\Fake', ['link' => 'javascript:steal()'])->html)->toContain('<a href="#">Unsubscribe</a>')
        ->and($renderer->renderEmail('Tests\\Fake', ['link' => 'https://example.test/u'])->html)->toContain('<a href="https://example.test/u">');
});

test('a binding maps placeholders to wording that can use the email data', function () {
    $renderer = rendererWith(
        ['html' => '<h2>{{ heading }}</h2>'],
        ['heading' => 'Hello <em>{{ name }}</em> from {{ app_name }}'],
        '{{ subject }} for {{ name }}'
    );

    $email = $renderer->renderEmail('Tests\\Fake', ['name' => 'Ana & Bo'], 'Welcome');

    expect($email->html)->toContain('<h2>Hello <em>Ana &amp; Bo</em> from Lexicon</h2>')
        ->and($email->subject)->toBe('Welcome for Ana & Bo')
        ->and($email->text)->toBe('Hello Ana & Bo from Lexicon');
});

test('a block whose placeholders are all empty is left out, but a block with none always shows', function () {
    $email = rendererWith(['html' => '<blockquote>{{ body }}</blockquote>'])->renderEmail('Tests\\Fake', ['body' => '']);

    expect($email->html)->not->toContain('blockquote')
        ->and($email->html)->toContain('<hr>');
});

test('strict mode names the email, block and placeholder that is missing', function () {
    rendererWith(['html' => '<p>{{ body }} {{ nobody }}</p>'])->renderEmail('Tests\\Fake', ['body' => 'x']);
})->throws(TemplateDataException::class, "The 'block' block (for Fake) uses {{ nobody }}, but nothing provides it.");

test('wording that refers to data the email does not provide is refused', function () {
    rendererWith(['html' => '<p>{{ heading }}</p>'], ['heading' => 'Hi {{ first_name }}'])->renderEmail('Tests\\Fake', []);
})->throws(TemplateDataException::class, 'The wording for {{ heading }} in Fake uses {{ first_name }}');

test('an email with no binding is refused', function () {
    rendererWith([])->renderEmail('Tests\\Other', []);
})->throws(TemplateDataException::class, 'No template is set up for Other.');

test('lenient mode shows what is missing instead of failing, for previews', function () {
    $email = rendererWith(['html' => '<a href="{{ url }}">{{ body }}</a>'], [], null, false)->renderEmail('Tests\\Fake', []);

    expect($email->html)->toContain('href="{{ url }}"')
        ->and($email->html)->toContain('<span style="background:#FEF3C7;color:#92400E;padding:0 2px;">{{ body }}</span>');
});

test('the plain text keeps link addresses, line breaks from values, and custom or omitted text', function () {
    $auto = rendererWith(['html' => '<p>Read <a href="{{ url }}">the post</a></p><p>{{ body }}</p>'])
        ->renderEmail('Tests\\Fake', ['url' => 'https://example.test/p', 'body' => HtmlFragment::fromText("one\ntwo")]);

    expect($auto->text)->toBe("Read the post (https://example.test/p)\n\none\ntwo");

    $custom = rendererWith(['html' => '<a href="{{ url }}">Go</a>', 'text' => 'Go: {{ url }}'])
        ->renderEmail('Tests\\Fake', ['url' => 'https://example.test/p?a=1&b=2']);
    expect($custom->text)->toBe('Go: https://example.test/p?a=1&b=2');

    $none = rendererWith(['html' => '<p>{{ body }}</p>', 'text' => ''])->renderEmail('Tests\\Fake', ['body' => 'hidden']);
    expect($none->text)->toBe('');
});

test('block css is gathered into the head once', function () {
    $email = rendererWith(['css' => '.x { color: red; }'])->renderEmail('Tests\\Fake', ['body' => 'b']);

    expect(substr_count($email->html, '.x { color: red; }'))->toBe(1)
        ->and($email->html)->toContain('<title>');
});

test('a failure in the live templates falls back to the built-in ones and reports it', function () {
    $broken = rendererWith(['html' => '<p>{{ missing }}</p>']);
    $working = rendererWith([]);
    $reported = [];

    $renderer = new TemplateRendererService($broken->source(), true, $working->source(), function (string $what, Throwable $e) use (&$reported): void {
        $reported[] = [$what, $e->getMessage()];
    });

    $email = $renderer->renderEmail('Tests\\Fake', ['body' => 'still sent']);

    expect($email->html)->toContain('still sent')
        ->and($reported)->toHaveCount(1)
        ->and($reported[0][0])->toBe('Fake')
        ->and($reported[0][1])->toContain('{{ missing }}');
});

test('data keys must be snake case and values must be text', function () {
    expect(fn () => rendererWith([])->renderEmail('Tests\\Fake', ['Body' => 'x']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => rendererWith([])->renderEmail('Tests\\Fake', ['body' => ['x']]))->toThrow(InvalidArgumentException::class);
});

test('placeholders are listed in order, once each', function () {
    expect(TemplateRendererService::placeholdersIn('{{ a }} {{b}} {{ a }} {{ Bad }}'))->toBe(['a', 'b']);
});

test('an email is marked with its language, and right-to-left ones are laid out from the right', function () {
    $renderer = rendererWith(['html' => '<blockquote style="border-{{ start }}:3px solid;padding-{{ end }}:0">{{ body }}</blockquote>']);

    $english = $renderer->renderEmail('Tests\\Fake', ['body' => 'Hi'], 'Hi', 'en')->html;
    $arabic = $renderer->renderEmail('Tests\\Fake', ['body' => 'Hi'], 'Hi', 'ar')->html;

    expect($english)->toContain('<html lang="en" dir="ltr">')
        ->and($english)->toContain('<div dir="ltr"')
        ->and($english)->toContain('border-left:3px solid;padding-right:0')
        ->and($arabic)->toContain('<html lang="ar" dir="rtl">')
        ->and($arabic)->toContain('<div dir="rtl"')
        ->and($arabic)->toContain('text-align:right;')
        ->and($arabic)->toContain('border-right:3px solid;padding-left:0');
});

test('a language the site does not offer falls back to the default', function () {
    $html = rendererWith([])->renderEmail('Tests\\Fake', ['body' => 'Hi'], 'Hi', 'xx')->html;

    expect($html)->toContain('<html lang="en" dir="ltr">');
});

test('direction placeholders never keep an otherwise empty block, nor hide one that only uses them', function () {
    $renderer = rendererWith(['html' => '<blockquote style="border-{{ start }}:3px solid">{{ body }}</blockquote>']);
    $alwaysShown = new TemplateRendererService(new CatalogTemplateSource([
        'components' => ['rule' => ['label' => 'Rule', 'html' => '<hr style="margin-{{ start }}:0">']],
        'templates' => ['only' => ['label' => 'Only', 'layout' => ['rule']]],
        'bindings' => ['Tests\\Fake' => ['template' => 'only', 'mapping' => []]],
    ]));

    expect($renderer->renderEmail('Tests\\Fake', ['body' => ''])->html)->not->toContain('<blockquote')
        ->and($alwaysShown->renderEmail('Tests\\Fake', [])->html)->toContain('<hr style="margin-left:0">');
});
