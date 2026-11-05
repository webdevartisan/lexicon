<?php

declare(strict_types=1);

use App\Services\ThemeService;
use Framework\View\RouteContext;
use Framework\View\SlotRendererInterface;
use Framework\View\TemplateRenderer;
use Framework\View\ViewNameResolverInterface;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/template_slots_'.uniqid();
    mkdir($this->dir.'/compiled', 0777, true);
    file_put_contents($this->dir.'/page.lex.php', '<article>Text{% slot "post.after-content" %}</article>');

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

test('a slot tag hands the slot name and the page variables to the slot renderer', function () {
    $seen = [];
    $this->renderer->setSlotRenderer(new class ($seen) implements SlotRendererInterface {
        public function __construct(private array &$seen)
        {
        }

        public function render(string $slot, array $vars): string
        {
            $this->seen = ['slot' => $slot, 'blogId' => $vars['blog']['id'] ?? null];

            return '<aside>X</aside>';
        }
    });

    expect($this->renderer->render('page.lex.php', ['blog' => ['id' => 4]]))
        ->toBe('<article>Text<aside>X</aside></article>')
        ->and($seen)->toBe(['slot' => 'post.after-content', 'blogId' => 4]);
});

test('a slot renders nothing when no slot renderer is set', function () {
    expect($this->renderer->render('page.lex.php', ['blog' => ['id' => 4]]))->toBe('<article>Text</article>');
});
