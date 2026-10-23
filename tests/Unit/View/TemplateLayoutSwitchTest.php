<?php

declare(strict_types=1);

use App\Services\ThemeService;
use Framework\View\RouteContext;
use Framework\View\TemplateRenderer;
use Framework\View\ViewNameResolverInterface;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/layout_switch_'.uniqid();
    mkdir($this->dir.'/compiled', 0777, true);

    file_put_contents($this->dir.'/front.lex.php', '<html>front {% yield body %}</html>');
    file_put_contents($this->dir.'/panel.lex.php', '<div>panel {% yield body %}</div>');
    file_put_contents($this->dir.'/page.lex.php', '{% extends "front.lex.php" %}{% block body %}Saved posts{% endblock %}');

    $themes = Mockery::mock(ThemeService::class);
    $resolver = Mockery::mock(ViewNameResolverInterface::class);
    $resolver->shouldReceive('resolveToRelativePath')->andReturnUsing(fn (string $name) => $name);
    $themes->shouldReceive('resolveView')->andReturnUsing(fn (string $path) => $this->dir.'/'.basename($path));

    $this->renderer = new TemplateRenderer($themes, $resolver, new RouteContext(), $this->dir.'/compiled');
});

afterEach(function () {
    foreach (glob($this->dir.'/{,compiled/}*', GLOB_BRACE) ?: [] as $file) {
        is_file($file) && unlink($file);
    }
    @rmdir($this->dir.'/compiled');
    @rmdir($this->dir);
});

test('a view renders inside the layout it extends', function () {
    expect($this->renderer->render('page.lex.php'))->toBe('<html>front Saved posts</html>');
});

test('useLayout frames the same view in another layout', function () {
    $this->renderer->useLayout('panel.lex.php');

    expect($this->renderer->render('page.lex.php'))->toBe('<div>panel Saved posts</div>');
});

test('both framings are compiled side by side', function () {
    $this->renderer->render('page.lex.php');
    $this->renderer->useLayout('panel.lex.php');
    $this->renderer->render('page.lex.php');

    expect(glob($this->dir.'/compiled/*.php'))->toHaveCount(2);
});
