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
        $html = method_exists($response, 'getBody') ? $response->getBody() : '';

        if ($html === '') {
            return $response;
        }

        // Optional: skip if Content-Type is not HTML
        if (method_exists($response, 'getHeader')) {
            $ct = $response->getHeader('Content-Type');
            if ($ct !== null && stripos($ct, 'text/html') === false) {
                return $response;
            }
        }

        $locale = $this->currentLocale();

        // Match <a ... href="/..."> only
        $pattern = '#<a\b[^>]*\bhref=(["\'])/(?![a-z]{2}(?:/|\1)|/|/|/)([^"\']*)\1#i';
        $replace = '<a $0'; // placeholder to keep structure? No, better use a callback to avoid duplicating <a

        $final = preg_replace_callback(
            '#(<a\b[^>]*\bhref=(["\']))/(?![a-z]{2}(?:/|\2)|/|/|/)([^"\']*)(\2)#i',
            function ($m) use ($locale) {
                // $m[1] is '<a ... href="'
                // $m[2] is the quote
                // $m[3] is the URL without the leading slash
                // $m[4] is the closing quote
                return $m[1].'/'.$locale.'/'.$m[3].$m[4];
            },
            $html
        );

        $response->setBody($final);

        return $response;
    }
}
