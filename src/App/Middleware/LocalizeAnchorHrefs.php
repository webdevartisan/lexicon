<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Core\Request;
use Framework\Core\Response;
use Framework\Interfaces\MiddlewareInterface;
use Framework\Interfaces\RequestHandlerInterface;

final class LocalizeAnchorHrefs implements MiddlewareInterface
{
    /** @var string[] */
    private array $locales;

    public function __construct()
    {
        $cfg = require ROOT_PATH.'/config/localization.php';
        $supported = $cfg['supported'] ?? ['en'];
        $this->locales = array_map('strtolower', $supported);
    }

    private function currentLocale(): string
    {
        $loc = strtolower($_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'en');

        return in_array($loc, $this->locales, true) ? $loc : ($this->locales[0] ?? 'en');
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Let controller render the response first
        $response = $handler->handle($request);

        // Get response body as string
        $html = $response->getBody();

        if ($html === '') {
            return $response;
        }

        // Skip if Content-Type is not HTML
        $ct = $response->getHeader('Content-Type');
        if ($ct !== null && stripos($ct, 'text/html') === false) {
            return $response;
        }

        $locale = $this->currentLocale();

        if ($this->locales === []) {
            return $response;
        }

        // Build the "already localized" guard from the configured locales rather
        // than a generic [a-z]{2}. The old pattern treated any two-letter first
        // segment as a locale, so a future /go/... or /my/... route would have
        // silently stopped being localized.
        $localeAlt = implode('|', array_map('preg_quote', $this->locales));

        // Matches <a ... href="/..."> and skips hrefs that already start with a
        // known locale, plus protocol-relative "//host" targets.
        $final = preg_replace_callback(
            '#(<a\b[^>]*\bhref=(["\']))/(?!(?:'.$localeAlt.')(?:/|\2)|/)([^"\']*)(\2)#i',
            function (array $m) use ($locale): string {
                // $m[1] '<a ... href="', 
                // $m[2] quote, 
                // $m[3] path without the leading slash, 
                // $m[4] closing quote.
                return $m[1].'/'.$locale.'/'.$m[3].$m[4];
            },
            $html
        );

        $response->setBody($final);

        return $response;
    }
}
