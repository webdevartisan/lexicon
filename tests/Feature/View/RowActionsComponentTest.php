<?php

declare(strict_types=1);

use App\Services\ThemeService;
use Framework\View\RouteContext;
use Framework\View\TemplateRenderer;
use Framework\View\ViewNameResolverInterface;

function renderRowActions(array $props): string
{
    $path = ROOT_PATH.'/views/components/row-actions.lex.php';
    $themes = Mockery::mock(ThemeService::class);
    $resolver = Mockery::mock(ViewNameResolverInterface::class);
    $resolver->shouldReceive('resolveToRelativePath')->andReturn($path);
    $themes->shouldReceive('resolveView')->andReturn($path);

    return (new TemplateRenderer($themes, $resolver, new RouteContext()))->renderComponent('row-actions', $props);
}

test('the button is named after the row and controls its menu', function () {
    $html = renderRowActions([
        'title' => 'Tom & "Jerry"',
        'items' => [['label' => 'Edit', 'icon' => 'pencil', 'href' => '/admin/tags/1/edit']],
    ]);

    preg_match('/aria-controls="([^"]+)"/', $html, $controls);

    expect($html)->toContain('aria-label="Actions for Tom &amp; &quot;Jerry&quot;"')
        ->and($html)->toContain('aria-haspopup="menu"')
        ->and($html)->toContain('aria-expanded="false"')
        ->and($html)->toContain('id="'.$controls[1].'"')
        ->and($html)->toContain('role="menuitem"');
});

test('an item the viewer may not use is left out entirely', function () {
    $html = renderRowActions([
        'title' => 'Admin',
        'items' => [
            ['label' => 'Edit user', 'icon' => 'pencil', 'href' => '/admin/users/1/edit'],
            ['label' => 'Delete user', 'icon' => 'trash-2', 'href' => '/admin/users/1/delete', 'danger' => true, 'can' => false],
        ],
    ]);

    expect($html)->not->toContain('Delete user')
        ->and($html)->not->toContain('/admin/users/1/delete')
        ->and($html)->not->toContain('role="separator"');
});

test('a row with nothing the viewer may do renders no menu at all', function () {
    $html = renderRowActions([
        'title' => 'System',
        'items' => [['label' => 'Delete', 'icon' => 'trash-2', 'href' => '/x', 'danger' => true, 'can' => false]],
    ]);

    expect(trim($html))->toBe('');
});

test('destructive items come last, after a divider, in red', function () {
    $html = renderRowActions([
        'title' => 'Post',
        'items' => [
            ['label' => 'Delete', 'icon' => 'trash-2', 'href' => '/admin/posts/1/delete', 'danger' => true],
            ['label' => 'Edit', 'icon' => 'pencil', 'href' => '/admin/posts/1/edit'],
        ],
    ]);

    $edit = strpos($html, 'Edit');
    $separator = strpos($html, 'role="separator"');
    $delete = strpos($html, 'Delete');

    expect($edit)->toBeLessThan($separator)
        ->and($separator)->toBeLessThan($delete)
        ->and(substr($html, $separator, $delete - $separator))->toContain('text-red-600');
});

test('a post action is a CSRF protected form that can ask for confirmation', function () {
    $html = renderRowActions([
        'title' => 'privacy:prune',
        'items' => [['label' => 'Delete task', 'icon' => 'trash-2', 'post' => '/admin/scheduled-tasks/3/delete', 'confirm' => 'Delete this task?', 'danger' => true]],
    ]);

    expect($html)->toContain('<form method="POST" action="/admin/scheduled-tasks/3/delete"')
        ->and($html)->toContain('name="_token"')
        ->and($html)->toContain('data-confirm="Delete this task?"')
        ->and($html)->toContain('type="submit" role="menuitem"');
});
