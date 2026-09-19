<?php

declare(strict_types=1);

use App\Services\ThemeService;
use Framework\View\RouteContext;
use Framework\View\TemplateRenderer;
use Framework\View\ViewNameResolverInterface;

function renderButton(array $props): string
{
    $path = ROOT_PATH.'/views/components/btn.lex.php';
    $themes = Mockery::mock(ThemeService::class);
    $resolver = Mockery::mock(ViewNameResolverInterface::class);
    $resolver->shouldReceive('resolveToRelativePath')->andReturn($path);
    $themes->shouldReceive('resolveView')->andReturn($path);

    return (new TemplateRenderer($themes, $resolver, new RouteContext()))->renderComponent('btn', $props);
}

test('extra attributes are escaped and empty ones are left out', function () {
    $html = renderButton([
        'label' => 'Pick',
        'attrs' => ['data-media-picker' => '6', 'data-media-target' => 'x" onclick="alert(1)', 'data-media-alt-target' => ''],
    ]);

    expect($html)->toContain('data-media-picker="6"')
        ->and($html)->toContain('data-media-target="x&quot; onclick=&quot;alert(1)"')
        ->and($html)->not->toContain('onclick="alert')
        ->and($html)->not->toContain('data-media-alt-target');
});

test('the link form keeps the extra classes and attributes', function () {
    $html = renderButton([
        'label' => 'Edit',
        'href' => '/dashboard/blog/6/media?editUrl=%2Fa.jpg',
        'addClass' => 'mt-2 w-full',
        'attrs' => ['data-x' => '1'],
    ]);

    expect($html)->toContain('<a')
        ->and($html)->toContain('mt-2 w-full"')
        ->and($html)->toContain('data-x="1"');
});

test('data action and target are escaped', function () {
    $html = renderButton(['label' => 'Go', 'dataAction' => '"><script>', 'dataTarget' => 'a"b']);

    expect($html)->not->toContain('"><script>')
        ->and($html)->toContain('data-target="a&quot;b"');
});

test('a button that opens a modal names it for the modal script', function () {
    $html = renderButton(['label' => 'Clear log', 'dataModalTarget' => 'clearLogModal']);

    expect($html)->toContain('data-modal-target="clearLogModal"');
});
