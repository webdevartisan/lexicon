<?php

declare(strict_types=1);

/**
 * Modules may depend on core, never the other way round. If core, a view, a theme or
 * the config named a module, the site would break the day that module is removed.
 *
 * Two things count as naming a module: a class in a module namespace (Modules\JourneyMap\...)
 * and a module view rendered or included by name (journey-map::route). Theme wording keys
 * such as closest::post.backTo look similar but are neither, so only the view forms are matched.
 */
const MODULE_CLASS_PATTERN = '/(?<![\w\\\\])\\\\?Modules\\\\{1,2}[A-Z]/';

const MODULE_VIEW_PATTERN = '/(?:render|view)\(\s*["\'][a-z][a-z0-9-]+::[a-z]|\{%\s*include\s+"[a-z][a-z0-9-]+::[a-z]/';

/**
 * Files under the given folders that name a module, skipping the module system itself.
 *
 * @param list<string> $dirs
 * @return list<string>
 */
function moduleOffenders(array $dirs): array
{
    $allowed = ['src/Framework/Modules/', 'src/App/Controllers/ModuleController.php'];
    $offenders = [];

    foreach ($dirs as $dir) {
        $folder = new RecursiveDirectoryIterator(ROOT_PATH."/{$dir}", FilesystemIterator::SKIP_DOTS);
        foreach (new RecursiveIteratorIterator($folder) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(ROOT_PATH) + 1));
            if (!str_ends_with($relative, '.php') || array_filter($allowed, fn ($path) => str_starts_with($relative, $path))) {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());
            if (preg_match(MODULE_CLASS_PATTERN, $code) || preg_match(MODULE_VIEW_PATTERN, $code)) {
                $offenders[] = $relative;
            }
        }
    }

    return $offenders;
}

test('core, views, themes and config never reference a module', function () {
    expect(moduleOffenders(['src', 'views', 'themes', 'config']))->toBe([]);
});

test('the patterns catch a module class and a module view but not core module code or theme wording', function (string $code, bool $caught) {
    $hit = preg_match(MODULE_CLASS_PATTERN, $code) === 1 || preg_match(MODULE_VIEW_PATTERN, $code) === 1;

    expect($hit)->toBe($caught);
})->with([
    'module class import' => ['use Modules\JourneyMap\StopModel;', true],
    'module class as a string' => ["\$c->get('Modules\\\\JourneyMap\\\\Stops');", true],
    'root namespaced module class' => ['new \Modules\JourneyMap\Stop();', true],
    'module view rendered' => ["\$this->view('journey-map::route', []);", true],
    'module view included' => ['{% include "journey-map::stop" %}', true],
    'framework module system' => ['use Framework\Modules\ModuleRegistry;', false],
    'fixture namespace' => ['Tests\Fixtures\Modules\SampleExtra\NoteSlot', false],
    'namespace root in the dispatcher' => ["['Modules\\\\', 'Tests\\\\Fixtures\\\\Modules\\\\']", false],
    'theme wording key' => ["\$wording->html('closest::post.backTo')", false],
]);
