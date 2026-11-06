<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

use Throwable;

/**
 * Renders the module's small slot templates, plain PHP files in views/slots/.
 *
 * Slots draw in the middle of a theme page, so they stay off the page renderer
 * and its globals; every value they print arrives as a variable and goes through e().
 */
final class Partial
{
    /**
     * @param string $name A file under views/slots/ without .php, always a fixed name from module code
     * @param array<string, mixed> $vars
     */
    public static function render(string $name, array $vars): string
    {
        $file = dirname(__DIR__).'/views/slots/'.$name.'.php';

        return (static function (string $file, array $vars): string {
            extract($vars, EXTR_SKIP);
            ob_start();
            try {
                require $file;
            } catch (Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return (string) ob_get_clean();
        })($file, $vars);
    }

    /**
     * Data for an HTML attribute or a JSON script block, encoded so it can never close the tag it sits in.
     *
     * @param array<mixed> $data
     */
    public static function json(array $data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    /**
     * A translator for templates, scoped to the module's text.
     *
     * @return \Closure(string, array<string, string|int>=): string
     */
    public static function translator(): \Closure
    {
        return static fn (string $key, array $params = []): string => chrome_translate('journey-map::'.$key, $params);
    }
}
