<?php

declare(strict_types=1);

namespace Framework\Core;

use ErrorException;
use Framework\Interfaces\TemplateViewerInterface;
use Throwable;

/**
 * Global error and exception handler.
 *
 * convert PHP errors to exceptions and render/output a safe response,
 * while logging full details for debugging. handle CLI and web contexts
 * differently to provide appropriate output for each environment.
 */
final class ErrorHandler
{
    /**
     * Convert PHP errors into ErrorException so they can be handled uniformly.
     *
     * @param  int  $severity  PHP error level.
     * @param  string  $message  Error message.
     * @param  string  $file  File where the error occurred.
     * @param  int  $line  Line number of the error.
     *
     * @throws ErrorException When error_reporting includes this severity.
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            // respect current error_reporting; ignore suppressed levels
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle uncaught exceptions.
     *
     * detect the execution context (CLI vs web) and handle exceptions
     * appropriately. In CLI mode, output plain text. In web mode, we
     * render HTML error pages. In production, log the details.
     *
     * @param  Throwable  $exception  Uncaught exception instance.
     */
    public static function handleException(Throwable $exception): void
    {
        // check if this is CLI execution first (before any other logic)
        $isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';

        if ($isCli) {
            // handle CLI exceptions separately to avoid HTML output
            self::handleCliException($exception);

            return;
        }

        // proceed with web exception handling (your existing logic)
        self::handleWebException($exception);
    }

    /**
     * Handle exceptions in CLI mode.
     *
     * output plain text error messages suitable for terminal display.
     * This prevents HTML error pages from appearing when running CLI commands.
     *
     * @param  Throwable  $exception  The exception to handle
     */
    protected static function handleCliException(Throwable $exception): void
    {
        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';

        // always log in production, optionally in dev
        if (!$isDev) {
            error_log(sprintf(
                '[%s] %s in %s:%d%s%s',
                date('Y-m-d H:i:s'),
                $exception::class.': '.$exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                PHP_EOL,
                $exception->getTraceAsString()
            ));
        }

        // output a clean, readable error message for the terminal
        echo "\n";
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║                     FATAL ERROR (CLI)                          ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n";
        echo "\n";

        // show the error details
        echo 'Error Type:  '.get_class($exception)."\n";
        echo 'Message:     '.$exception->getMessage()."\n";
        echo 'File:        '.$exception->getFile()."\n";
        echo 'Line:        '.$exception->getLine()."\n";
        echo "\n";

        // show stack trace in development mode
        if ($isDev) {
            echo "Stack Trace:\n";
            echo "────────────────────────────────────────────────────────────────\n";
            echo $exception->getTraceAsString()."\n";
            echo "\n";
        } else {
            echo "Run with APP_ENV=development for full stack trace.\n\n";
        }

        exit(1);
    }

    /**
     * Handle exceptions in web mode.
     *
     * use the existing ErrorRenderer to output HTML error pages.
     * This is your original implementation, unchanged.
     *
     * @param  Throwable  $exception  Uncaught exception instance.
     */
    protected static function handleWebException(Throwable $exception): void
    {
        $isDev = ($_ENV['APP_ENV'] ?? 'production') !== 'production';

        if (!$isDev) {
            error_log(sprintf(
                '[%s] %s in %s:%d%s%s',
                date('Y-m-d H:i:s'),
                $exception::class.': '.$exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                PHP_EOL,
                $exception->getTraceAsString()
            ));
        }

        try {
            $container = App::container();

            $viewer = $container->get(TemplateViewerInterface::class);
            $response = $container->get(Response::class);

            $renderer = new ErrorRenderer($viewer, $response, $isDev);
            $renderer->render($exception)->send();

            return;
        } catch (Throwable $fallbackFailure) {
            // should never let the error handler crash, so keep a minimal last-resort response
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>Something went wrong</h1><p>Please try again later..</p>';
        }
    }
}
