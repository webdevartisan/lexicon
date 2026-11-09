<?php

declare(strict_types=1);

namespace App\Services\Modules;

use App\Models\BlogModuleModel;
use App\Models\ModuleModel;
use Framework\Cache\CacheService;
use Framework\Modules\ModuleBooter;
use Framework\Modules\ModuleManifest;
use Framework\Modules\ModuleRegistry;
use Framework\Modules\SlotContext;
use Framework\Modules\SlotRenderer;
use Framework\View\RouteContext;
use Framework\View\SlotRendererInterface;
use Throwable;

/**
 * Fills template slots with the output of the modules a blog switched on.
 *
 * A module that throws while drawing a slot is left out of the page and logged,
 * so a broken extra never breaks a reader's page. After repeated errors the
 * module is paused for everyone until an administrator resumes it.
 */
final class SlotRegistry implements SlotRendererInterface
{
    private const FAILURE_LIMIT = 20;

    /** Failures count toward the limit while each comes within ten minutes of the last. */
    private const FAILURE_WINDOW = 600;

    private const ASSET_PATTERN = '#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*\.(css|js)$#';

    /** @var array<int, list<string>> Blog id => keys of the modules it switched on */
    private array $activeKeys = [];

    /** @var array<string, list<SlotRenderer>> Module key => renderers that drew something on this page */
    private array $rendered = [];

    /** @var array<string, true> Modules paused during this request */
    private array $paused = [];

    /** @var array<string, list<array{module: string, renderer: SlotRenderer}>>|null */
    private ?array $slots = null;

    public function __construct(
        private ModuleBooter $booter,
        private BlogModuleModel $blogModules,
        private ModuleModel $modules,
        private ModuleRegistry $registry,
        private CacheService $cache,
        private RouteContext $route,
        private bool $debug,
    ) {}

    /**
     * @param  array<string, mixed>  $vars  The template's variables; the blog, when there is one, is $vars['blog']
     */
    public function render(string $slot, array $vars): string
    {
        // Most pages on most sites: no module has a slot, so skip the per-blog query entirely
        if ($this->allSlots() === []) {
            return '';
        }

        $blog = $this->row($vars['blog'] ?? null);
        $blogId = (int) ($blog['id'] ?? 0) ?: null;
        $active = $this->activeOn($blogId);
        $context = new SlotContext(
            $slot,
            $blogId,
            $blogId === null ? null : $blog,
            $this->row($vars['post'] ?? null),
            (string) $this->route->action()
        );
        $html = $this->renderModules($context, $active);

        if ($slot === 'head.end') {
            $html .= $this->stylesheets($active);
        }
        if ($slot === 'body.end') {
            $html .= $this->scripts();
        }

        return $html;
    }

    /**
     * @param  list<string>  $active
     */
    private function renderModules(SlotContext $context, array $active): string
    {
        $html = '';
        foreach ($this->renderersFor($context->slot, $active) as ['module' => $key, 'renderer' => $renderer]) {
            $context = $context->withRenderedModules(array_keys($this->rendered));

            try {
                if (!$renderer->shouldRender($context)) {
                    continue;
                }
                $html .= $renderer->render($context);
            } catch (Throwable $e) {
                $this->recordFailure($key, $context->slot, $e);

                continue;
            }

            $this->rendered[$key][] = $renderer;
        }

        return $html;
    }

    /**
     * Renderers for a slot from modules that are on for the blog, in module key order.
     *
     * @param  list<string>  $active
     * @return list<array{module: string, renderer: SlotRenderer}>
     */
    private function renderersFor(string $slot, array $active): array
    {
        $usable = fn (array $entry): bool => in_array($entry['module'], $active, true)
            && !isset($this->paused[$entry['module']]);
        $renderers = array_filter($this->allSlots()[$slot] ?? [], $usable);
        usort($renderers, fn (array $a, array $b): int => strcmp($a['module'], $b['module']));

        return $renderers;
    }

    /**
     * Stylesheets go in the head, before the body is drawn, so they cover every module
     * active on the page rather than only those that end up rendering.
     *
     * @param  list<string>  $active
     */
    private function stylesheets(array $active): string
    {
        $files = [];
        foreach ($this->allSlots() as $entries) {
            foreach ($entries as ['module' => $key, 'renderer' => $renderer]) {
                if (in_array($key, $active, true)) {
                    $files += $this->assetUrls($key, $renderer->assets()['css'] ?? []);
                }
            }
        }

        $tags = array_map(fn (string $url) => '<link rel="stylesheet" href="'.e($url).'">', array_keys($files));

        return implode('', $tags);
    }

    /**
     * Scripts only for modules that drew something on this page.
     */
    private function scripts(): string
    {
        $files = [];
        foreach ($this->rendered as $key => $renderers) {
            foreach ($renderers as $renderer) {
                $files += $this->assetUrls($key, $renderer->assets()['js'] ?? []);
            }
        }

        $tags = array_map(fn (string $url) => '<script src="'.e($url).'" defer></script>', array_keys($files));

        return implode('', $tags);
    }

    /**
     * @param  array<int, mixed>  $names  Paths relative to public/modules/<key>/; anything that is not a plain path is skipped
     * @return array<string, true> Versioned URL => true, so repeats collapse
     */
    private function assetUrls(string $key, array $names): array
    {
        $urls = [];
        foreach ($names as $name) {
            if (is_string($name) && preg_match(self::ASSET_PATTERN, $name)) {
                $urls[versioned_asset('/modules/'.$key.'/'.$name)] = true;
            }
        }

        return $urls;
    }

    /**
     * @return array<string, list<array{module: string, renderer: SlotRenderer}>>
     */
    private function allSlots(): array
    {
        return $this->slots ??= $this->booter->slots();
    }

    /**
     * Modules that may draw on this page: every running platform module, plus the blog
     * modules the page's blog switched on. A page outside any blog gets platform modules only.
     *
     * @return list<string>
     */
    private function activeOn(?int $blogId): array
    {
        $platform = array_keys(array_filter(
            $this->registry->running(),
            fn (ModuleManifest $manifest) => $manifest->scope === 'platform'
        ));

        return $blogId === null ? $platform : [...$platform, ...$this->activeFor($blogId)];
    }

    /**
     * @return list<string>
     */
    private function activeFor(int $blogId): array
    {
        return $this->activeKeys[$blogId] ??= $this->blogModules->activeKeys($blogId);
    }

    private function recordFailure(string $key, string $slot, Throwable $e): void
    {
        if ($this->debug) {
            throw $e;
        }

        error_log("[module {$key}] slot {$slot}: ".$e->getMessage());

        $counterKey = 'module-failures-'.$key;
        $count = (int) ($this->cache->get($counterKey) ?? 0) + 1;
        $this->cache->set($counterKey, (string) $count, self::FAILURE_WINDOW);

        if ($count >= self::FAILURE_LIMIT) {
            $this->modules->pause($key, "Paused after {$count} slot errors. Last error: ".$e->getMessage());
            $this->registry->rebuild();
            $this->cache->delete($counterKey);
            $this->paused[$key] = true;
        }
    }

    /**
     * Template variables hold rows as arrays or as resources that can turn into one.
     *
     * @return array<string, mixed>|null
     */
    private function row(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        return is_object($value) && method_exists($value, 'toArray') ? $value->toArray() : null;
    }
}
