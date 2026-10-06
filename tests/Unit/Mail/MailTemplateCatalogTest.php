<?php

declare(strict_types=1);

use App\Mail\Mailable;
use App\Mail\QueuedMail;
use App\Mail\Templates\CatalogTemplateSource;
use App\Mail\Templates\EmailHtmlLinter;
use App\Mail\Templates\HtmlFragment;
use App\Mail\Templates\MailTranslator;
use App\Mail\Templates\TemplateDataException;
use App\Services\EmailTemplateRegistry;
use App\Services\LocaleRegistry;
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

test('every email renders strictly in every language the site offers', function () {
    $renderer = new TemplateRendererService($this->catalog);

    foreach (LocaleRegistry::instance()->supported() as $locale) {
        foreach (array_keys($this->registry->getAll()) as $key) {
            $mail = Mailable::withTemplateRenderer($renderer, fn () => Mailable::inLocale($locale, fn () => $this->registry->build($key)));

            expect(str_contains($mail->getBody(), '<html lang="'.$locale.'"'))->toBeTrue("{$key} in {$locale}")
                ->and($mail->getSubject())->not->toBe('');
        }
    }
});

test('every language translates all built-in wording, with only the placeholders the English uses', function () {
    foreach (array_diff(LocaleRegistry::instance()->supported(), ['en']) as $locale) {
        foreach ($this->catalog->bindings() as $class => $binding) {
            $translated = MailTranslator::wording($locale, $class);

            foreach ($binding['mapping'] as $name => $english) {
                // Wording that is only a placeholder has nothing to translate.
                if (trim((string) preg_replace(TemplateRendererService::PLACEHOLDER, '', $english)) === '') {
                    continue;
                }

                $where = TemplateDataException::shortName($class).".{$name} in {$locale}";
                expect(isset($translated[$name]))->toBeTrue("{$where} is not translated");

                // A placeholder the email does not provide would fail every send, the built-in fallback included.
                $extra = array_diff(TemplateRendererService::placeholdersIn($translated[$name]), TemplateRendererService::placeholdersIn($english));
                expect($extra)->toBe([], "{$where} uses placeholders the English does not")
                    ->and(EmailHtmlLinter::lintHtml($translated[$name], 'https://example.test')['errors'])->toBe([], $where);
            }

            expect(array_diff(array_keys($translated), array_keys($binding['mapping'])))->toBe([], TemplateDataException::shortName($class)." in {$locale} translates wording that does not exist");
        }
    }
});

test('every plural in the mail strings is valid ICU for its language', function () {
    foreach (LocaleRegistry::instance()->supported() as $locale) {
        $section = json_decode((string) file_get_contents(ROOT_PATH."/locales/{$locale}.json"), true)['mail'];

        array_walk_recursive($section, function (string $pattern, string $key) use ($locale): void {
            if (str_contains($pattern, ', plural,')) {
                expect(MessageFormatter::create($locale, $pattern))->not->toBeNull("{$key} in {$locale}");
            }
        });
    }
});

test('subjects come from the translations, not from English written in the class', function () {
    foreach (glob(ROOT_PATH.'/src/App/Mail/*.php') ?: [] as $file) {
        expect(preg_match("/->subject\\(\\s*['\"]/", (string) file_get_contents($file)))->toBe(0, basename($file).' sets a literal subject');

        preg_match_all("/\\\$this->t\\('([a-z_.A-Z]+)'/", (string) file_get_contents($file), $keys);
        // Keys built at runtime ('digest.goals.'.$goal) end in a dot; the render test covers them.
        foreach (array_filter($keys[1], static fn (string $key): bool => !str_ends_with($key, '.')) as $key) {
            expect(MailTranslator::has('en', $key))->toBeTrue(basename($file)." uses mail.{$key}, which en.json does not have");
        }
    }
});
