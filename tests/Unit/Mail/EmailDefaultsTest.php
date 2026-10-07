<?php

declare(strict_types=1);

use App\Mail\Mailable;
use App\Mail\QueuedMail;
use App\Mail\Templates\EmailHtmlLinter;
use App\Mail\Templates\HtmlFragment;
use App\Mail\Templates\ShippedEmailSource;
use App\Mail\Templates\TemplateDataException;
use App\Services\EmailRenderer;
use App\Services\EmailTemplateRegistry;

/**
 * The shipped files are what every email falls back to and what "Reset to
 * default" goes back to, so they have to be complete and correct on their
 * own: one English file per email, each building strictly from its email's
 * data, and every word in the files rather than in the code.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';
    $this->shipped = new ShippedEmailSource();
    $this->registry = new EmailTemplateRegistry();
});

/**
 * @return list<class-string<Mailable>>
 */
function concreteMailables(): array
{
    $classes = [];

    foreach (glob(ROOT_PATH.'/src/App/Mail/*.php') ?: [] as $file) {
        $class = 'App\\Mail\\'.basename($file, '.php');

        if (is_subclass_of($class, Mailable::class) && !(new ReflectionClass($class))->isAbstract() && $class !== QueuedMail::class) {
            $classes[] = $class;
        }
    }

    return $classes;
}

test('every email has exactly one shipped English file, and every file belongs to an email', function () {
    $names = array_map(TemplateDataException::shortName(...), concreteMailables());
    sort($names);

    expect($this->shipped->emailNames())->toBe($names);

    foreach (concreteMailables() as $class) {
        expect($this->shipped->locales($class))->toBe(['en'])
            ->and($this->shipped->layout($this->shipped->layoutFor($class)))->not->toBeNull(TemplateDataException::shortName($class).' names a layout that does not ship');
    }
});

test('every registered sample builds strictly from the shipped files', function () {
    $renderer = new EmailRenderer($this->shipped);

    foreach (array_keys($this->registry->getAll()) as $key) {
        $mail = Mailable::withTemplateRenderer($renderer, fn () => $this->registry->build($key));

        expect($mail->getBody())->toStartWith('<!DOCTYPE html>')
            ->and(trim((string) $mail->getTextBody()))->not->toBe('', "{$key} has an empty text part")
            ->and($mail->getSubject())->not->toBe('', "{$key} has no subject")
            ->and($mail->getSubject())->not->toContain('{{');
    }
});

test('every shipped file passes the checks a control panel edit must', function () {
    foreach ($this->shipped->layouts() as $slug => $layout) {
        expect(EmailHtmlLinter::lintHtml($layout['html'], 'https://example.test', true)['errors'])->toBe([], "layout {$slug}")
            ->and($layout['html'])->toContain('{{ content }}')
            ->and($layout['html'])->toContain('{{ footer_note }}');
    }

    foreach (concreteMailables() as $class) {
        $content = $this->shipped->content($class, 'en') ?? [];

        foreach (['body', 'footer_note', 'repeat'] as $part) {
            expect(EmailHtmlLinter::lintHtml((string) $content[$part], 'https://example.test')['errors'])->toBe([], TemplateDataException::shortName($class)." {$part}");
        }

        foreach (EmailRenderer::LAYOUT_ONLY as $name) {
            expect(EmailRenderer::placeholdersIn($content['body']))->not->toContain($name);
        }
    }
});

test('a layout has no words of its own, since it is not translated', function () {
    foreach ($this->shipped->layouts() as $slug => $layout) {
        $text = (string) preg_replace(['#<head\b.*?</head>#is', '#<!--.*?-->#s', EmailRenderer::PLACEHOLDER], '', $layout['html']);
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        expect(preg_replace('/[\s©]+/u', '', $text))->toBe('', "layout {$slug} has words in it");
    }
});

test('no words or markup are left in the Mailable classes', function () {
    foreach (glob(ROOT_PATH.'/src/App/Mail/*.php') ?: [] as $file) {
        if (in_array(basename($file), ['Mailable.php', 'QueuedMail.php'], true)) {
            continue;
        }

        $code = (string) file_get_contents($file);

        expect(preg_match('#<(?:p|div|h[1-6]|a|strong|table|br|ul|li|blockquote|html|body|span)\b#i', $code))->toBe(0, basename($file).' contains HTML')
            ->and(preg_match('/->subject\(/', $code))->toBe(0, basename($file).' sets a subject; it belongs in the email file');
    }
});

test('user-written text in an email is escaped all the way through', function () {
    $mail = new App\Mail\ContactMessageMail('admin@example.test', '<b>Eve</b>', 'eve@example.test', 'Hi', "<script>x</script>\nsecond line");

    expect($mail->getBody())->not->toContain('<script>x</script>')
        ->and($mail->getBody())->not->toContain('<b>Eve</b>')
        ->and($mail->getBody())->toContain('&lt;script&gt;x&lt;/script&gt;<br>')
        ->and($mail->getTextBody())->toContain("<script>x</script>\nsecond line");
});

test('the plain text keeps real link addresses, and leaves out the preheader and Outlook-only copies', function () {
    $mail = new App\Mail\NewPostMail('r@example.test', 'Blog', 'Title', 'my-blog', 'my post', 'tok');
    $text = (string) $mail->getTextBody();

    expect($text)->toContain('Read it (https://example.test/blog/my-blog/my%20post)')
        ->and($text)->toContain('Unsubscribe (https://example.test/subscriptions/unsubscribe/tok)')
        ->and(substr_count($text, 'Read it'))->toBe(1)
        ->and($text)->not->toContain('just published a new post')
        ->and($mail->getBody())->toContain('just published a new post');
});

test('HtmlFragment::join keeps both renditions in order and skips empty parts', function () {
    $joined = HtmlFragment::join([HtmlFragment::fromText('a'), HtmlFragment::empty(), HtmlFragment::fromText('b')]);

    expect($joined->html)->toBe('ab')->and($joined->text)->toBe("a\nb");
});
