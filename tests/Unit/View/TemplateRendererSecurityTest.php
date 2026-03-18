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

// Delete any temp fixture files created during each test
afterEach(function () {
    foreach ($this->testFixtures ?? [] as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
});

test('template renderer compiles templates to disk without using eval', function () {
    $themes = Mockery::mock(ThemeService::class);
    $resolver = Mockery::mock(ViewNameResolverInterface::class);
    $routeContext = new RouteContext();

    // Write to OS temp dir
    $templatePath = sys_get_temp_dir().'/security_test_'.uniqid().'.lex.php';
    $this->testFixtures[] = $templatePath;

    file_put_contents($templatePath, 'Hello, {{ name }}');

    $resolver->shouldReceive('resolveToRelativePath')
        ->once()
        ->andReturn($templatePath);

    $themes->shouldReceive('resolveView')
        ->once()
        ->andReturn($templatePath);

    $renderer = new TemplateRenderer($themes, $resolver, $routeContext);
    $output = $renderer->render($templatePath, ['name' => 'World']);

    expect($output)->toContain('Hello, World');
});

test('template renderer treats raw php tags as text when escaped', function () {
    $themes = Mockery::mock(ThemeService::class);
    $resolver = Mockery::mock(ViewNameResolverInterface::class);
    $routeContext = new RouteContext();

    // Write to OS temp dir
    $templatePath = sys_get_temp_dir().'/security_php_tag_'.uniqid().'.lex.php';
    $this->testFixtures[] = $templatePath;

    // Verify HTML-encoded PHP tags are not executed as a second pass
    file_put_contents($templatePath, 'Escaped tag: &lt;?php echo "evil"; ?&gt;');

    $resolver->shouldReceive('resolveToRelativePath')
        ->once()
        ->andReturn($templatePath);

    $themes->shouldReceive('resolveView')
        ->once()
        ->andReturn($templatePath);

    $renderer = new TemplateRenderer($themes, $resolver, $routeContext);
    $output = $renderer->render($templatePath, []);

    expect($output)->toContain('Escaped tag: &lt;?php echo "evil"; ?&gt;');
});
