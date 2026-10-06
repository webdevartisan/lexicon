<?php

declare(strict_types=1);

use App\Mail\Mailable;
use App\Mail\QueuedMail;
use App\Mail\Templates\CatalogTemplateSource;
use App\Mail\Templates\EmailHtmlLinter;
use App\Mail\Templates\HtmlFragment;
use App\Services\EmailTemplateRegistry;
use App\Services\TemplateRendererService;

/**
 * The shipped catalog is what every email falls back to, so it has to be
 * complete and correct on its own: every email bound, every sample rendering
 * strictly, and no markup left behind in the Mailable classes.
 */
beforeEach(function () {
    $_ENV['APP_URL'] = 'https://example.test';
    $_ENV['APP_NAME'] = 'Lexicon';
    $this->catalog = new CatalogTemplateSource();
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

test('every Mailable has a built-in binding to a template that exists', function () {
    foreach (concreteMailables() as $class) {
        $binding = $this->catalog->binding($class);

        expect($binding)->not->toBeNull("{$class} has no binding in resources/mail/catalog.php")
            ->and($this->catalog->template($binding['template']))->not->toBeNull("{$class} points at a missing template");
    }
});

test('every registered sample renders strictly against the built-in templates', function () {
    $renderer = new TemplateRendererService($this->catalog);

    foreach (array_keys($this->registry->getAll()) as $key) {
        $mail = Mailable::withTemplateRenderer($renderer, fn () => $this->registry->build($key));

        expect($mail->getBody())->toContain('<!DOCTYPE html>')
            ->and(trim((string) $mail->getTextBody()))->not->toBe('', "{$key} has an empty text part")
            ->and($mail->getSubject())->not->toBe('');
    }
});

test('no markup is left in the Mailable classes', function () {
    foreach (glob(ROOT_PATH.'/src/App/Mail/*.php') ?: [] as $file) {
        if (in_array(basename($file), ['Mailable.php', 'QueuedMail.php'], true)) {
            continue;
        }

        expect(preg_match('#<(?:p|div|h[1-6]|a|strong|table|br|ul|li|blockquote|html|body|span)\b#i', (string) file_get_contents($file)))
            ->toBe(0, basename($file).' still contains HTML; move it into a block or binding');
    }
});

test('every built-in block passes the same checks a control panel edit must', function () {
    foreach ($this->catalog->components() as $slug => $component) {
        expect(EmailHtmlLinter::lintHtml($component['html'], 'https://example.test')['errors'])->toBe([], "block {$slug}");
    }

    foreach ($this->catalog->bindings() as $class => $binding) {
        foreach ($binding['mapping'] as $name => $wording) {
            expect(EmailHtmlLinter::lintHtml($wording, 'https://example.test')['errors'])->toBe([], "{$class} {$name}");
        }
    }
});

test('user-written text in an email is escaped all the way through', function () {
    $mail = new App\Mail\ContactMessageMail('admin@example.test', '<b>Eve</b>', 'eve@example.test', 'Hi', "<script>x</script>\nsecond line");

    expect($mail->getBody())->not->toContain('<script>x</script>')
        ->and($mail->getBody())->not->toContain('<b>Eve</b>')
        ->and($mail->getBody())->toContain('&lt;script&gt;x&lt;/script&gt;<br>')
        ->and($mail->getTextBody())->toContain("<script>x</script>\nsecond line");
});

test('a link in the plain text is the real address, not escaped markup', function () {
    $mail = new App\Mail\NewPostMail('r@example.test', 'Blog', 'Title', 'my-blog', 'my post', 'tok');

    expect($mail->getTextBody())->toContain('Read it: https://example.test/blog/my-blog/my%20post')
        ->and($mail->getTextBody())->toContain('Unsubscribe (https://example.test/subscriptions/unsubscribe/tok)');
});

test('HtmlFragment::join keeps both renditions in order and skips empty parts', function () {
    $joined = HtmlFragment::join([HtmlFragment::fromText('a'), HtmlFragment::empty(), HtmlFragment::fromText('b')]);

    expect($joined->html)->toBe('ab')->and($joined->text)->toBe("a\nb");
});
