<?php

declare(strict_types=1);

namespace Framework\Helpers;

/**
 * Server-side icon renderer.
 *
 * We execute a Node.js script to convert data-lucide icons to SVGs
 * before caching. This ensures cached HTML contains fully-rendered icons.
 */
class IconRenderer
{
    /**
     * Find Node.js executable path.
     *
     * We check multiple locations where Node.js might be installed,
     * on Windows (Laragon included) and on Unix.
     *
     * @return string|null Path to node executable, or null if not found
     */
    private static function findNodePath(): ?string
    {
        // Each shell has its own lookup and its own null device; writing
        // "2>nul" on Linux leaves a file called "nul" in the working directory.
        $result = PHP_OS_FAMILY === 'Windows'
            ? shell_exec('where node 2>nul')
            : shell_exec('command -v node 2>/dev/null');

        if (is_string($result) && trim($result) !== '') {
            // get the first path if multiple are found
            $paths = preg_split('/\R/', trim($result)) ?: [];

            return trim($paths[0] ?? '') ?: null;
        }

        // fall back to checking common locations manually
        $possiblePaths = PHP_OS_FAMILY === 'Windows'
            ? [
                'C:\laragon\bin\nodejs\node-v18\node.exe',
                'C:\laragon\bin\nodejs\node-v20\node.exe',
                'C:\laragon\bin\nodejs\node.exe',
            ]
            : ['/usr/local/bin/node', '/usr/bin/node', '/opt/homebrew/bin/node'];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Render data-lucide icons to SVGs using Node.js.
     *
     * We pipe HTML through a Node.js script that uses the official Lucide
     * library to convert icon placeholders to actual SVG elements.
     *
     * @param  string  $html  HTML containing data-lucide icons
     * @return string HTML with SVGs rendered
     */
    public static function render(string $html): string
    {
        // check if HTML contains any icons to render
        if (strpos($html, 'data-lucide') === false) {
            return $html; // No icons to render
        }

        // get the path to the rendering script
        $scriptPath = dirname(dirname(dirname(__DIR__))).DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'utilities'.DIRECTORY_SEPARATOR.'render-icons.js';

        // check if the script exists
        if (!file_exists($scriptPath)) {
            trigger_error('Icon renderer script not found at: '.$scriptPath, E_USER_WARNING);

            return $html;
        }

        // find Node.js executable
        $nodePath = self::findNodePath();
        if (!$nodePath) {
            // Not an error worth breaking the page for: the icons still render
            // in the browser. Logged once per process so a log is not flooded.
            static $logged = false;
            if (!$logged) {
                error_log('IconRenderer: Node.js not found, icons will render client-side');
                $logged = true;
            }

            return $html;
        }

        // set up pipes for communication with Node.js
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin - we write HTML here
            1 => ['pipe', 'w'],  // stdout - we read rendered HTML from here
            2 => ['pipe', 'w'],  // stderr - we read errors from here
        ];

        // build the command (quote paths for Windows)
        $command = '"'.$nodePath.'" "'.$scriptPath.'"';

        // start the Node.js process
        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            trigger_error('Failed to start Node.js process', E_USER_WARNING);

            return $html;
        }

        // write the HTML to stdin
        fwrite($pipes[0], $html);
        fclose($pipes[0]);

        // read the rendered HTML from stdout
        $renderedHtml = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        // read any errors from stderr
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        // wait for the process to finish and get exit code
        $exitCode = proc_close($process);

        // log any errors
        if ($exitCode !== 0 || !empty($errors)) {
            error_log("IconRenderer error (exit code: {$exitCode}): {$errors}");

            return $html; // Return original HTML on error
        }

        // return the rendered HTML
        return $renderedHtml ?: $html;
    }
}
