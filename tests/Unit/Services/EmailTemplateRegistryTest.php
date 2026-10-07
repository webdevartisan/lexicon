<?php

declare(strict_types=1);

use App\Mail\Mailable;
use App\Services\EmailTemplateRegistry;

/**
 * EmailTemplateRegistry Unit Test Suite
 *
 * Guards the admin Email Templates page: every registered template must
 * instantiate from its sample data, and every Mailable in the codebase
 * must be registered so it stays previewable and testable.
 */
beforeEach(function () {
    $this->registry = new EmailTemplateRegistry();
});

test('every registered template instantiates from its sample data', function () {
    foreach ($this->registry->getAll() as $key => $template) {
        $mailable = $this->registry->instantiate($key);

        expect($mailable)->toBeInstanceOf(Mailable::class);
    }
});

test('every Mailable class in the codebase is registered', function () {
    expect($this->registry->unregisteredClasses())->toBeEmpty();
});

test('every Mailable class is registered only once', function () {
    $counts = array_count_values(array_column($this->registry->getAll(), 'class'));

    // The admin page lists one email per class and stores wording per class,
    // so a second entry for the same class would be hidden behind the first.
    expect(array_keys(array_filter($counts, static fn (int $n): bool => $n > 1)))->toBe([]);
});

test('every sample value is a constructor parameter of its class', function () {
    foreach ($this->registry->getAll() as $key => $template) {
        $constructor = (new ReflectionClass($template['class']))->getConstructor();
        $params = array_map(static fn (ReflectionParameter $p): string => $p->getName(), $constructor?->getParameters() ?? []);

        // A key nothing reads is silently dropped, so the preview would not show what it claims to.
        expect(array_values(array_diff(array_keys($template['sample_data']), $params)))->toBe([], "{$key} has sample data its constructor does not take");
    }
});

test('every template declares a display group', function () {
    foreach ($this->registry->getAll() as $key => $template) {
        expect($template)->toHaveKeys(['name', 'description', 'group', 'class', 'sample_data']);
    }
});

test('grouped view keeps every template exactly once', function () {
    $flat = [];
    foreach ($this->registry->getGrouped() as $templates) {
        $flat = array_merge($flat, array_keys($templates));
    }

    expect($flat)->toEqualCanonicalizing(array_keys($this->registry->getAll()));
});

test('instantiate rejects unknown template keys', function () {
    $this->registry->instantiate('does_not_exist');
})->throws(Exception::class);
