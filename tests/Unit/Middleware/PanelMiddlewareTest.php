<?php

declare(strict_types=1);

use App\Middleware\PanelMiddleware;
use Framework\Core\Request;
use Framework\Interfaces\LayoutSwitchInterface;
use Tests\Helpers\MiddlewareTestHelper;

function panelRequest(array $headers = []): Request
{
    return new Request('/en/saved', 'GET', [], [], [], [], [], $headers);
}

test('a plain visit keeps the full page', function () {
    $layouts = Mockery::mock(LayoutSwitchInterface::class);
    $layouts->shouldNotReceive('useLayout');

    $response = (new PanelMiddleware($layouts))->process(panelRequest(), MiddlewareTestHelper::createHandler('page'));

    expect($response->hasHeader('X-Panel'))->toBeFalse()
        ->and($response->getHeader('Vary'))->toBe('X-Panel');
});

test('a modal fetch gets the panel layout and is marked as a panel', function () {
    $layouts = Mockery::mock(LayoutSwitchInterface::class);
    $layouts->shouldReceive('useLayout')->once()->with('panel.lex.php');

    $response = (new PanelMiddleware($layouts))->process(panelRequest(['x-panel' => '1']), MiddlewareTestHelper::createHandler('panel'));

    expect($response->getHeader('X-Panel'))->toBe('1')
        ->and($response->getHeader('Vary'))->toBe('X-Panel');
});
