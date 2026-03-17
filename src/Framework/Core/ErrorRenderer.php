<?php

declare(strict_types=1);

namespace Framework\Core;

use Framework\Exceptions\PageNotFoundException;
use Framework\Exceptions\TemplateRenderException;
use Framework\Exceptions\UnauthorizedException;
use Framework\Interfaces\TemplateViewerInterface;
use ReflectionException;
use Throwable;

final class ErrorRenderer
{
    public function __construct(
        private TemplateViewerInterface $viewer,
        private Response $response,
        private bool $isDev,
    ) {}

    /**
     * Render an exception into an HTTP response using app templates.
     *
     * We must never throw from here, because we are already inside the global exception handler.
     */
    public function render(Throwable $e): Response
    {
        [$status, $template] = $this->mapException($e);

        $this->response->setStatusCode($status);
        $this->response->addHeader('Content-Type', 'text/html; charset=utf-8');
        $this->response->addHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $this->response->addHeader('Pragma', 'no-cache');
        $this->response->addHeader('X-Frame-Options', 'DENY');

        $data = [
            'status' => $status,
            'title' => $this->titleForStatus($status),
            'homeUrl' => function_exists('lurl') ? lurl('/') : '/',
            'isDev' => $this->isDev,
            // should keep production pages generic to avoid information disclosure.
            'exceptionClass' => $this->isDev ? $e::class : null,
            'exceptionMessage' => $this->isDev ? $e->getMessage() : null,
        ];

        if ($this->isDev && $e instanceof TemplateRenderException) {
            // only expose template file/line/snippet in development to avoid leaking internals in production.
            $data['templateMessage'] = $e->getMessage();
            $data['templateFile'] = $e->templateFile;
            $data['templateLine'] = $e->templateLine;
            $data['templateSnippet'] = $e->snippet();
        }

        if ($this->isDev) {
            $data['exceptionFile'] = $e->getFile();
            $data['exceptionLine'] = $e->getLine();
            $data['exceptionTrace'] = $e->getTraceAsString();
        }

        try {
            $html = $this->viewer->render($template, $data);
            $this->response->setBody($html);

            return $this->response;
        } catch (Throwable $renderFailure) {

            if ($this->isDev) {
                // show both exceptions because the real problem is often "template not found"
                $rend = e($renderFailure->getMessage());
                $rendClass = e($renderFailure::class);

                $body = <<<HTML
                <h2>Render failure</h2>
                <p><strong>Type:</strong> {$rendClass}</p>
                <p><strong>Message:</strong> {$rend}</p>
                <pre>{$renderFailure}</pre>
                HTML;

                $this->response->setStatusCode(500)->setBody($body);

                return $this->response;
            }

            // should fall back to a hardcoded, safe page if templates break, to avoid blank responses.
            $fallback = '<h1>Something went wrong</h1><p>Please try again later.</p>';
            $this->response->setStatusCode(500)->setBody($fallback);

            return $this->response;
        }
    }

    /**
     * @return array{0:int,1:string}
     */
    private function mapException(Throwable $e): array
    {
        if ($e instanceof PageNotFoundException) {
            return [404, 'errors/404.lex.php'];
        }

        if ($e instanceof ReflectionException) {
            return [404, 'errors/404.lex.php'];
        }

        if ($e instanceof UnauthorizedException) {
            return [403, 'errors/403.lex.php'];
        }

        // should keep a dedicated template error page if a view explodes, because it’s actionable in dev.
        if ($e instanceof TemplateRenderException) {
            return [500, 'errors/500-template.lex.php'];
        }

        return [500, 'errors/500.lex.php'];
    }

    private function titleForStatus(int $status): string
    {
        return match ($status) {
            404 => 'Page not found',
            403 => 'Access denied',
            default => 'Server error',
        };
    }
}
