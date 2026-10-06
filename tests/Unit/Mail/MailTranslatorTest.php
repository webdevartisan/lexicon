<?php

declare(strict_types=1);

use App\Mail\Templates\MailTranslator;

/**
 * The words code puts into emails: plain phrases with {name} placeholders,
 * ICU plurals for counts (Arabic needs all six forms), and numbers and dates
 * the way each language writes them.
 */
test('a phrase is filled in and falls back to English for a key a language lacks', function () {
    expect(MailTranslator::text('el', 'subjects.PostApprovedMail', ['post_title' => 'X']))->toBe('Η ανάρτησή σου εγκρίθηκε: X')
        ->and(MailTranslator::text('xx', 'subjects.PostApprovedMail', ['post_title' => 'X']))->toBe('Your post was approved: X')
        ->and(MailTranslator::text('en', 'no.such.key'))->toBe('no.such.key');
});

test('counts take the plural form their language needs', function () {
    $minutes = static fn (string $locale, int $count): string => MailTranslator::text($locale, 'phrases.minutes', ['count' => $count]);

    expect($minutes('en', 1))->toBe('1 minute')
        ->and($minutes('en', 60))->toBe('60 minutes')
        ->and($minutes('el', 60))->toBe('60 λεπτά')
        ->and($minutes('ar', 1))->toBe('دقيقة واحدة')
        ->and($minutes('ar', 2))->toBe('دقيقتين')
        ->and($minutes('ar', 5))->toBe('٥ دقائق')
        ->and($minutes('ar', 60))->toBe('٦٠ دقيقة');
});

test('numbers and dates are written the way the language writes them', function () {
    $date = new DateTimeImmutable('2026-09-28', new DateTimeZone('UTC'));

    expect(MailTranslator::number('en', 1240))->toBe('1,240')
        ->and(MailTranslator::number('el', 1240))->toBe('1.240')
        ->and(MailTranslator::date('en', $date, 'MMMd'))->toBe('Sep 28')
        ->and(MailTranslator::date('el', $date, 'MMMd'))->toBe('28 Σεπ');
});

test('the built-in wording of an email is translated per placeholder, and English has none of its own', function () {
    expect(MailTranslator::wording('el', 'App\\Mail\\PostPublishedMail'))->toHaveKey('heading')
        ->and(MailTranslator::wording('en', 'App\\Mail\\PostPublishedMail'))->toBe([]);
});
