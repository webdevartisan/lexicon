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
     * especially on Windows with Laragon.
     *
     * @return string|null Path to node executable, or null if not found
     */
    private static function findNodePath(): ?string
    {
        // try to use 'where' command first (most reliable on Windows)
        $result = shell_exec('where node 2>nul');

        if ($result && trim($result)) {
            // get the first path if multiple are found
            $paths = explode("\n", trim($result));

            return trim($paths[0]);
        }

        // fall back to checking common locations manually
        $possiblePaths = [
            'C:\laragon\bin\nodejs\node-v18\node.exe',
            'C:\laragon\bin\nodejs\node-v20\node.exe',
            'C:\laragon\bin\nodejs\node.exe',
        ];

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
            trigger_error('Node.js not found in PATH, icons will render client-side', E_USER_WARNING);

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
