<?php

declare(strict_types=1);

use App\Services\ThemeService;
use Framework\View\RouteContext;
use Framework\View\TemplateRenderer;
use Framework\View\ViewNameResolverInterface;

afterEach(function () {
    foreach ($this->testFixtures ?? [] as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
});

function rendererServing(string $templatePath): TemplateRenderer
{
    $themes = Mockery::mock(ThemeService::class);
    $resolver = Mockery::mock(ViewNameResolverInterface::class);

    $resolver->shouldReceive('resolveToRelativePath')->andReturn($templatePath);
    $themes->shouldReceive('resolveView')->andReturn($templatePath);

    return new TemplateRenderer($themes, $resolver, new RouteContext());
}

test('a component that throws fails the render instead of printing a comment', function () {
    $component = sys_get_temp_dir().'/broken_component_'.uniqid().'.lex.php';
    $this->testFixtures[] = $component;
    file_put_contents($component, '<?php throw new RuntimeException("card exploded"); ?>');

    $renderer = rendererServing($component);

    expect(fn () => $renderer->renderComponent('post-card', []))
        ->toThrow(RuntimeException::class, 'card exploded');
});

test('an invalid component name is rejected loudly', function () {
    $renderer = rendererServing(sys_get_temp_dir().'/unused.lex.php');

    expect(fn () => $renderer->renderComponent('../etc/passwd', []))
        ->toThrow(InvalidArgumentException::class);
});

test('the post card renders a post that has no excerpt', function () {
    $card = file_get_contents(ROOT_PATH.'/views/components/post-card.lex.php');

    expect($card)->not->toContain('|isset')
        ->and($card)->not->toMatch('/\{%\s*elseif\s+[a-z_]+\.[a-z_]+/');
});
