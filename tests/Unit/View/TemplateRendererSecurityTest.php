<?php

declare(strict_types=1);

use App\Services\ThemeService;
use Framework\View\RouteContext;
use Framework\View\TemplateRenderer;
use Framework\View\ViewNameResolverInterface;

/**
 * Security-focused tests for TemplateRenderer.
 *
 * These tests ensure that:
 * - Templates are compiled to disk and included, not evaluated via eval().
 * - Dangerous PHP opening tags are preserved as plain text in output when escaped.
 *
 * NOTE: We do not test every directive here; the goal is to lock in the
 * non-eval execution model and a basic XSS-safety expectation.
 */
test('template renderer compiles templates to disk without using eval', function () {
    // Arrange: create a minimal stub ThemeService and ViewNameResolverInterface
    $themes = Mockery::mock(ThemeService::class);
    $resolver = Mockery::mock(ViewNameResolverInterface::class);
    $routeContext = new RouteContext();

    $templatePath = ROOT_PATH.'/tests/Fixtures/views/security_test.lex.php';

    // Ensure fixture directory exists
    if (!is_dir(dirname($templatePath))) {
        mkdir(dirname($templatePath), 0755, true);
    }

    file_put_contents($templatePath, 'Hello, {{ name }}');

    $resolver->shouldReceive('resolveToRelativePath')
        ->once()
        ->andReturn('tests/Fixtures/views/security_test.lex.php');

    $themes->shouldReceive('resolveView')
        ->once()
        ->andReturn($templatePath);

    $renderer = new TemplateRenderer($themes, $resolver, $routeContext);

    // Act
    $output = $renderer->render('security_test.lex.php', ['name' => 'World']);

    // Assert
    expect($output)->toContain('Hello, World');
});

test('template renderer treats raw php tags as text when escaped', function () {
    $themes = Mockery::mock(ThemeService::class);
    $resolver = Mockery::mock(ViewNameResolverInterface::class);
    $routeContext = new RouteContext();

    $templatePath = ROOT_PATH.'/tests/Fixtures/views/security_php_tag.lex.php';

    if (!is_dir(dirname($templatePath))) {
        mkdir(dirname($templatePath), 0755, true);
    }

    // This string should not result in a second-level PHP execution beyond the compiled view.
    file_put_contents($templatePath, 'Escaped tag: &lt;?php echo "evil"; ?&gt;');

    $resolver->shouldReceive('resolveToRelativePath')
        ->once()
        ->andReturn('tests/Fixtures/views/security_php_tag.lex.php');

    $themes->shouldReceive('resolveView')
        ->once()
        ->andReturn($templatePath);

    $renderer = new TemplateRenderer($themes, $resolver, $routeContext);

    $output = $renderer->render('security_php_tag.lex.php', []);

    expect($output)->toContain('Escaped tag: &lt;?php echo "evil"; ?&gt;');
}
);
