<?php

declare(strict_types=1);

use App\Services\ThemeService;
use Framework\View\RouteContext;
use Framework\View\TemplateRenderer;
use Framework\View\ViewNameResolverInterface;

/**
 * Fragment cache keys, which decide what each cache block actually serves.
 *
 * A key the compiler could not read used to fall back to one key shared by
 * every block in the app, so the first fragment rendered was handed out
 * everywhere for the rest of its TTL. That is how four different status icons
 * on the mail queue page all came out as the same clock.
 *
 * Caching is off under phpunit, so these go at the key compiler itself rather
 * than at a render, which would pass either way.
 */
function compileCacheKey(string $expression): string
{
    $themes = Mockery::mock(ThemeService::class);
    $resolver = Mockery::mock(ViewNameResolverInterface::class);
    $renderer = new TemplateRenderer($themes, $resolver, new RouteContext());

    $method = new ReflectionMethod(TemplateRenderer::class, 'compileKeyExpression');
    $method->setAccessible(true);

    return $method->invoke($renderer, $expression);
}

test('a key built from an array subscript compiles as written', function () {
    expect(compileCacheKey("'icon:' . \$tile['icon']"))->toBe("'icon:' . \$tile['icon']")
        ->and(compileCacheKey('$row["id"]'))->toBe('$row["id"]');
});

test('the plain key forms still compile as written', function () {
    expect(compileCacheKey("'lucide:star'"))->toBe("'lucide:star'")
        ->and(compileCacheKey('$area . \':sidebar:nav\''))->toBe('$area . \':sidebar:nav\'')
        ->and(compileCacheKey('"sidebar:$area:nav"'))->toBe("'sidebar:' . \$area . ':nav'");
});

test('a key holding a call is still refused, so nothing runs from a template', function () {
    expect(compileCacheKey("'x:' . strtoupper(\$name)"))->toStartWith("'invalid_cache_key");
});

test('two refused keys fall back to a bucket each instead of sharing one', function () {
    $first = compileCacheKey("'first:' . strtoupper(\$name)");
    $second = compileCacheKey("'second:' . strtoupper(\$name)");

    expect($first)->not->toBe($second);
});
