<?php

declare(strict_types=1);

use App\Services\ThemeService;
use Framework\Exceptions\NotFoundException;
use Framework\View\ModuleViewPaths;
use Framework\View\RouteContext;
use Framework\View\TemplateRenderer;
use Framework\View\ViewNameResolverInterface;
use Tests\Helpers\ModuleFixtures;

/**
 * A theme view includes a module view by its key::name, and the theme resolver is never asked for it.
 */
beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/module_views_'.uniqid();
    mkdir($this->dir.'/compiled', 0777, true);
    file_put_contents($this->dir.'/page.lex.php', '<main>{% include "sample-extra::note" %}</main>');

    $themes = Mockery::mock(ThemeService::class);
    $resolver = Mockery::mock(ViewNameResolverInterface::class);
    $resolver->shouldReceive('resolveToRelativePath')->andReturnUsing(fn (string $name) => $name);
    $themes->shouldReceive('resolveView')->andReturnUsing(fn (string $path) => $this->dir.'/'.basename($path));

    $this->makeRenderer = fn (array $states) => new TemplateRenderer(
        $themes,
        $resolver,
        new RouteContext(),
        $this->dir.'/compiled',
        new ModuleViewPaths(ModuleFixtures::registry($states))
    );
});

afterEach(function () {
    ModuleFixtures::cleanUp();
    foreach (glob($this->dir.'/{,compiled/}*', GLOB_BRACE) ?: [] as $file) {
        is_file($file) && unlink($file);
    }
    @rmdir($this->dir.'/compiled');
    @rmdir($this->dir);
});

test('a module view renders by its key and name', function () {
    $renderer = ($this->makeRenderer)(['sample-extra' => 'on']);

    expect($renderer->render('sample-extra::note', ['note' => 'Day one']))
        ->toContain('<p class="sample-extra-note">Day one</p>');
});

test('a theme view can include a module view', function () {
    $renderer = ($this->makeRenderer)(['sample-extra' => 'on']);

    expect($renderer->render('page.lex.php', ['note' => 'Day two']))
        ->toContain('<main><p class="sample-extra-note">Day two</p>');
});

test('a module view of a module that is not installed is not found', function () {
    $renderer = ($this->makeRenderer)([]);

    expect(fn () => $renderer->render('sample-extra::note', ['note' => 'x']))->toThrow(NotFoundException::class);
});
