<?php

declare(strict_types=1);

use Framework\View\ModuleViewPaths;
use Tests\Helpers\ModuleFixtures;

beforeEach(function () {
    $this->paths = new ModuleViewPaths(ModuleFixtures::registry(['sample-extra' => 'on']));
});

afterEach(fn () => ModuleFixtures::cleanUp());

test('module views resolve inside the module folder only', function () {
    expect(str_replace('\\', '/', (string) $this->paths->resolve('sample-extra::note')))
        ->toEndWith('/sample-extra/views/note.lex.php')
        ->and($this->paths->resolve('sample-extra::../../secret'))->toBeNull()
        ->and($this->paths->resolve('not-installed::note'))->toBeNull();
});

test('a missing view file or a malformed name resolves to null', function () {
    expect($this->paths->resolve('sample-extra::nothing-here'))->toBeNull()
        ->and($this->paths->resolve('Sample_Extra::note'))->toBeNull()
        ->and($this->paths->resolve('sample-extra::'))->toBeNull()
        ->and($this->paths->resolve('no-separator'))->toBeNull();
});

test('a paused module has no views', function () {
    $paths = new ModuleViewPaths(ModuleFixtures::registry(['sample-extra' => 'paused']));

    expect($paths->resolve('sample-extra::note'))->toBeNull();
});
