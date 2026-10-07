<?php

declare(strict_types=1);

use App\Mail\NewPostMail;
use App\Mail\PasswordResetEmail;
use App\Mail\Templates\HtmlFragment;
use App\Mail\Templates\ShippedEmailSource;
use App\Mail\Templates\TemplateDataException;
use App\Services\EmailRenderer;
use Tests\Helpers\InMemoryEmailSource;

/**
 * Words go into a layout through placeholders only. These pin down what a
 * placeholder can and cannot do, which language an email is written in, and
 * what happens when a saved version cannot be used.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';
    $_ENV['MAIL_FROM_ADDRESS'] = 'hello@example.test';
});

/**
 * A source whose PasswordResetEmail in English is $body, in a bare layout.
 *
 * @param  array<string, string|null>  $fields
 */
function bareSource(string $body, array $fields = [], string $layout = '<html lang="{{ lang }}" dir="{{ dir }}"><body><div class="pre">{{ preheader }}</div>{{ content }}<footer>{{ footer_note }}</footer></body></html>'): InMemoryEmailSource
{
    return new InMemoryEmailSource(
        [PasswordResetEmail::class => ['en' => ['body' => $body, 'subject' => 'Hi {{ name }}', 'preheader' => 'Pre {{ name }}', 'footer_note' => 'Note'] + $fields]],
        ['bare' => ['name' => 'Bare', 'html' => $layout]],
        [PasswordResetEmail::class => 'bare'],
    );
}

test('text is escaped, markup from code is not, and inside a tag a value is always escaped text', function () {
    $renderer = new EmailRenderer(bareSource('<p title="{{ name }}">{{ name }}</p>{{ fragment }}'));

    $email = $renderer->renderEmail(PasswordResetEmail::class, [
        'name' => '<b>"Eve"</b>',
        'fragment' => HtmlFragment::trusted('<em>made by code</em>'),
    ], 'en');

    expect($email->html)->toContain('<p title="&lt;b&gt;&quot;Eve&quot;&lt;/b&gt;">&lt;b&gt;&quot;Eve&quot;&lt;/b&gt;</p>')
        ->and($email->html)->toContain('<em>made by code</em>')
        ->and($email->subject)->toBe('Hi <b>"Eve"</b>');
});

test('a link can never become javascript:', function () {
    $renderer = new EmailRenderer(bareSource('<a href="{{ url }}">go</a><a href="{{ ok }}">ok</a>'));

    $html = $renderer->renderEmail(PasswordResetEmail::class, ['name' => 'Jo', 'url' => 'javascript:alert(1)', 'ok' => 'https://example.test/x'], 'en')->html;

    expect($html)->toContain('<a href="#">go</a>')
        ->and($html)->toContain('<a href="https://example.test/x">ok</a>');
});

test('a placeholder nothing fills is refused when strict and shown in place when previewing', function () {
    $source = bareSource('<p>{{ nonsense }}</p>');

    expect(fn () => (new EmailRenderer($source))->renderEmail(PasswordResetEmail::class, ['name' => 'Jo'], 'en'))
        ->toThrow(TemplateDataException::class, 'PasswordResetEmail body (en) uses {{ nonsense }}');

    expect((new EmailRenderer($source, false))->renderEmail(PasswordResetEmail::class, ['name' => 'Jo'], 'en')->html)
        ->toContain('{{ nonsense }}');
});

test("an email's words cannot reach the layout's own parts", function () {
    expect(fn () => (new EmailRenderer(bareSource('<p>{{ content }}</p>')))->renderEmail(PasswordResetEmail::class, ['name' => 'Jo'], 'en'))
        ->toThrow(TemplateDataException::class, '{{ content }}');
});

test('the preheader is in the HTML part only, and the plain text is read from the finished email', function () {
    $email = (new EmailRenderer(bareSource('<p>Hello <a href="{{ url }}">there</a></p>')))
        ->renderEmail(PasswordResetEmail::class, ['name' => 'Jo', 'url' => 'https://example.test/x'], 'en');

    expect($email->html)->toContain('<div class="pre">Pre Jo</div>')
        ->and($email->text)->not->toContain('Pre Jo')
        ->and($email->text)->toContain('Hello there (https://example.test/x)')
        ->and($email->text)->toContain('Note');
});

test("layout settings and every email's values are there, and an email's own data wins", function () {
    $renderer = new EmailRenderer(bareSource('<p>{{ primary_color }} {{ support_email }} {{ preferences_url }} {{ year }}</p>'));

    $plain = $renderer->renderEmail(PasswordResetEmail::class, ['name' => 'Jo'], 'en')->html;
    $own = $renderer->renderEmail(PasswordResetEmail::class, ['name' => 'Jo', 'preferences_url' => 'https://example.test/mine'], 'en')->html;

    expect($plain)->toContain('#4F46E5 hello@example.test https://example.test/account/notifications '.date('Y'))
        ->and($own)->toContain('https://example.test/mine');
});

test('an email is written in its reader\'s language when it has words in it, else the site default, else English', function () {
    $source = new InMemoryEmailSource([PasswordResetEmail::class => ['ar' => ['subject' => 'مرحبا {{ name }}'], 'el' => ['subject' => 'Γεια {{ name }}']]]);
    $renderer = new EmailRenderer($source);

    expect($renderer->localeFor(PasswordResetEmail::class, 'ar'))->toBe('ar')
        ->and($renderer->localeFor(PasswordResetEmail::class, 'el'))->toBe('el')
        ->and($renderer->localeFor(NewPostMail::class, 'el'))->toBe('en')
        ->and($renderer->renderEmail(PasswordResetEmail::class, ['name' => 'Jo', 'reset_url' => 'https://example.test/r', 'expires_minutes' => 60], 'ar')->html)
        ->toContain('<html lang="ar" dir="rtl"');
});

test('right-to-left languages turn the start and end of the layout around', function () {
    $html = (new EmailRenderer(new InMemoryEmailSource([PasswordResetEmail::class => ['ar' => ['body' => '<td align="{{ start }}" style="padding-{{ end }}:4px">x</td>']]])))
        ->renderEmail(PasswordResetEmail::class, ['name' => 'Jo', 'expires_minutes' => 60], 'ar')->html;

    expect($html)->toContain('<td align="right" style="padding-left:4px">');
});

test('a saved version that cannot be built is sent as shipped, in English, and someone is told', function () {
    $told = [];
    $broken = new InMemoryEmailSource([PasswordResetEmail::class => ['el' => ['body' => '<p>{{ no_such_value }}</p>']]]);
    $renderer = new EmailRenderer($broken, true, new ShippedEmailSource(), function (string $what, Throwable $e) use (&$told): void {
        $told[] = [$what, $e->getMessage()];
    });

    $email = $renderer->renderEmail(PasswordResetEmail::class, ['name' => 'Jo', 'reset_url' => 'https://example.test/r', 'expires_minutes' => 60], 'el');

    expect($email->subject)->toBe('Reset your Lexicon password')
        ->and($email->html)->toContain('<html lang="en"')
        ->and($told)->toHaveCount(1)
        ->and($told[0][0])->toBe('PasswordResetEmail')
        ->and($told[0][1])->toContain('no_such_value');
});

test('the repeated section is filled once per row with that row\'s values', function () {
    $source = new InMemoryEmailSource([PasswordResetEmail::class => ['en' => ['repeat' => '<li>{{ title }}: {{ app_name }}</li>']]]);

    $fragment = (new EmailRenderer($source))->renderRepeat(PasswordResetEmail::class, [['title' => 'One'], ['title' => '<Two>']], 'en');

    expect($fragment->html)->toBe('<li>One: Lexicon</li><li>&lt;Two&gt;: Lexicon</li>');
});
