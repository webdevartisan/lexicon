<?php

declare(strict_types=1);

namespace App\Traits;

use Framework\Core\Response;

/**
 * Serves an email body as its own page without letting it run anything.
 *
 * These pages are framable by design so the preview iframe can load them, and
 * an iframe's sandbox attribute only covers what it frames: open one of these
 * URLs on its own and that protection is not in the picture. A stored or
 * rendered email body is exactly what a Mailable escaping bug would turn into
 * a script on the real admin origin, so the CSP sandbox directive is set as
 * well. That one holds however the response was opened.
 *
 * Shared by the queue and the template previews deliberately. Two copies of a
 * header set drift, and the weaker copy is the one that gets found.
 */
trait SandboxesPreviewHtml
{
    /**
     * Build an HTML response that stays inert wherever it is opened.
     *
     * @param  string  $html  Raw body to serve
     * @param  int  $statusCode  HTTP status code
     */
    private function sandboxedHtml(string $html, int $statusCode = 200): Response
    {
        $response = $this->response->html($html, $statusCode);

        // Images and inline styles stay allowed so the preview still looks
        // like the email being checked.
        $response->addHeader('Content-Security-Policy', "sandbox; default-src 'none'; img-src https: data:; style-src 'unsafe-inline'");
        $response->addHeader('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
